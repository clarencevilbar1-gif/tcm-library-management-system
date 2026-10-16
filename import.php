<?php
include('auth.php');
ini_set('display_errors', 1);
error_reporting(E_ALL);
include('db.php');
include('book_functions.php');
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;


// ======================================================================
// Books: reads the College of Law "LAW LIBRARY CHECKLIST" layout
//   Acc. Nos. | Class | Title of the Book | Author | Edition | Volume | Pages | Fund | Publication | Year
// The heading row is found automatically (rows above it, like the title banner, are ignored)
// and columns are matched by their heading, so their order in the file does not matter.
// ======================================================================
function norm_header($v) {
    return preg_replace('/[^a-z0-9]/', '', strtolower((string)$v));
}

function book_header_aliases() {
    return [
        'acc_nos'     => ['accnos', 'accno', 'accessionnos', 'accessionno', 'accessionnumber', 'accessionnumbers', 'serialno', 'serialnumber'],
        'class_no'    => ['class', 'classno', 'callno', 'callnumber'],
        'title'       => ['titleofthebook', 'title', 'booktitle'],
        'author'      => ['author', 'authors'],
        'edition'     => ['edition', 'ed'],
        'volume'      => ['volume', 'volumes', 'vol'],
        'pages'       => ['pages', 'page', 'pp'],
        'fund'        => ['fund'],
        'publication' => ['publication', 'publisher', 'publishers'],
        'pub_year'    => ['year', 'yearpublished', 'publicationyear', 'pubyear'],
        'copies'      => ['copies', 'noofcopies', 'numberofcopies'],
    ];
}

// Looks in the first rows for the heading row. Returns [row index, [column index => field]] or null.
function find_book_header($rows) {
    $aliases = book_header_aliases();
    $limit = min(15, count($rows));
    for ($i = 0; $i < $limit; $i++) {
        $map = [];
        foreach ($rows[$i] as $c => $cell) {
            $n = norm_header($cell);
            if ($n === '') continue;
            foreach ($aliases as $field => $names) {
                if (in_array($n, $names, true) && !in_array($field, $map, true)) {
                    $map[$c] = $field;
                    break;
                }
            }
        }
        if (in_array('acc_nos', $map, true) && in_array('title', $map, true)) {
            return [$i, $map];
        }
    }
    return null;
}

// Excel hands numbers back as numbers: 1847 (or 1847.0) must become the text "1847"
function cell_to_text($v) {
    if (is_float($v) && floor($v) == $v) return (string)(int)$v;
    return $v === null ? '' : (string)$v;
}

// Imports (or, with $preview, only checks) the rows of the checklist. Returns a report array.
function import_books($conn, $rows, $preview) {
    $found = find_book_header($rows);
    if (!$found) {
        throw new Exception('Could not find the column headings. The first rows of the file must include "Acc. Nos." and "Title of the Book", as in the College of Law checklist.');
    }
    list($hdr, $map) = $found;

    $rep = [
        'header_row' => $hdr + 1,
        'imported' => 0,
        'blank_rows' => 0,
        'no_title' => 0,
        'no_acc' => [],
        'duplicates' => [],
        'bad_years' => [],
        'columns' => array_values($map),
    ];

    $limits = [];
    foreach (book_text_fields() as $col => $def) $limits[$col] = $def[1];

    $exists = mysqli_prepare($conn, "SELECT title FROM books WHERE acc_nos = ? LIMIT 1");
    $seen = [];      // Acc. No(s). already met in this file => [row, title]

    if (!$preview) mysqli_begin_transaction($conn);
    try {
        for ($i = $hdr + 1; $i < count($rows); $i++) {
            $excel_row = $i + 1;
            $r = $rows[$i];

            $has_data = false;
            foreach ($r as $cell) { if ($cell !== null && trim((string)$cell) !== '') { $has_data = true; break; } }
            if (!$has_data) { $rep['blank_rows']++; continue; }

            $d = [];
            foreach (['acc_nos', 'class_no', 'title', 'author', 'edition', 'volume', 'pages', 'fund', 'publication'] as $field) {
                $col = array_search($field, $map, true);
                $val = ($col === false || !isset($r[$col])) ? null : clean_or_null(cell_to_text($r[$col]));
                if ($val !== null && mb_strlen($val) > $limits[$field]) $val = mb_substr($val, 0, $limits[$field]);
                $d[$field] = $val;
            }

            $ycol = array_search('pub_year', $map, true);
            $yraw = ($ycol === false || !isset($r[$ycol])) ? '' : cell_to_text($r[$ycol]);
            list($year, $year_ok) = parse_pub_year($yraw);
            if (!$year_ok) $rep['bad_years'][] = ['row' => $excel_row, 'value' => clean_text($yraw)];
            $d['pub_year'] = $year;

            $copies = 1;
            $ccol = array_search('copies', $map, true);
            if ($ccol !== false && isset($r[$ccol]) && intval($r[$ccol]) > 0) $copies = intval($r[$ccol]);

            // numbered rows that hold no book details (the department file has a block of these)
            if ($d['title'] === null) { $rep['no_title']++; continue; }
            if ($d['acc_nos'] === null) { $rep['no_acc'][] = ['row' => $excel_row, 'title' => $d['title']]; continue; }

            $key = mb_strtolower($d['acc_nos']);
            if (isset($seen[$key])) {
                $rep['duplicates'][] = ['row' => $excel_row, 'acc' => $d['acc_nos'], 'title' => $d['title'],
                                        'why' => 'used twice in this file (first on row ' . $seen[$key][0] . ': "' . $seen[$key][1] . '")'];
                continue;
            }
            mysqli_stmt_bind_param($exists, 's', $d['acc_nos']);
            mysqli_stmt_execute($exists);
            $res = mysqli_stmt_get_result($exists);
            if ($old = mysqli_fetch_assoc($res)) {
                $rep['duplicates'][] = ['row' => $excel_row, 'acc' => $d['acc_nos'], 'title' => $d['title'],
                                        'why' => 'already in the system ("' . $old['title'] . '")'];
                continue;
            }

            $seen[$key] = [$excel_row, $d['title']];
            if (!$preview && !insert_book($conn, $d, $copies)) {
                throw new Exception('A book could not be saved (row ' . $excel_row . ').');
            }
            $rep['imported']++;
        }
        if (!$preview) mysqli_commit($conn);
    } catch (Exception $e) {
        if (!$preview) mysqli_rollback($conn);
        throw $e;
    }
    return $rep;
}

// The result box shown after importing / previewing books
function book_import_report_html($rep, $preview) {
    $dup = count($rep['duplicates']);
    $html = '<div class="alert ' . ($preview ? 'alert-info' : 'alert-success') . '">';
    if ($preview) {
        $html .= '<strong>Preview — nothing was saved.</strong><br>';
        $html .= '<strong>' . $rep['imported'] . '</strong> book(s) are ready to import.';
    } else {
        $html .= '✅ Import complete! <strong>' . $rep['imported'] . '</strong> book(s) added.';
    }
    $html .= '<ul class="mb-0 mt-2 small">';
    $html .= '<li>Column headings found on row ' . $rep['header_row'] . '.</li>';
    if ($rep['no_title'] > 0) {
        $html .= '<li><strong>' . $rep['no_title'] . '</strong> row(s) skipped: they have an Acc. No. but no title.</li>';
    }
    if ($dup > 0) {
        $html .= '<li><strong>' . $dup . '</strong> row(s) skipped because the Acc. No. is already used (details below).</li>';
    }
    if (count($rep['no_acc']) > 0) {
        $html .= '<li><strong>' . count($rep['no_acc']) . '</strong> row(s) skipped: they have a title but no Acc. No.</li>';
    }
    if (count($rep['bad_years']) > 0) {
        $html .= '<li><strong>' . count($rep['bad_years']) . '</strong> year(s) were not a valid 4-digit year and were left blank.</li>';
    }
    $html .= '</ul>';

    if ($dup > 0) {
        $html .= '<details class="mt-2 small"><summary>Show the skipped duplicates</summary><ul class="mb-0 mt-1">';
        foreach (array_slice($rep['duplicates'], 0, 40) as $x) {
            $html .= '<li>Row ' . $x['row'] . ' — Acc. No. <strong>' . h($x['acc']) . '</strong> ("' . h($x['title']) . '") ' . h($x['why']) . '</li>';
        }
        if ($dup > 40) $html .= '<li>… and ' . ($dup - 40) . ' more.</li>';
        $html .= '</ul></details>';
    }
    return $html . '</div>';
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file']['tmp_name'];
    $filename = $_FILES['file']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
        $message = '<div class="alert alert-danger">Invalid file type. Please upload an Excel (.xlsx) or CSV file.</div>';
    } else {
        try {
            $spreadsheet = IOFactory::load($file);
            $sheet = $spreadsheet->getActiveSheet();
            $type = $_POST['type']; // 'students' or 'books'

            if ($type == 'books') {
                // raw cell values, so numbers such as 1847 stay exactly as typed
                $raw_rows = $sheet->toArray(null, true, false, false);
                $preview = isset($_POST['preview']);
                $report = import_books($conn, $raw_rows, $preview);
                $message = book_import_report_html($report, $preview);
                $rows = [];
            } else {
                $rows = $sheet->toArray();
                // Skip the header row
                array_shift($rows);
            }
            $success = 0;
            $skipped = 0;

            foreach ($rows as $row) {
                // Skip completely empty rows
                if (empty(array_filter($row))) continue;

                if ($type == 'students') {
                    $student_no = mysqli_real_escape_string($conn, trim($row[0]));
                    $name = mysqli_real_escape_string($conn, trim($row[1]));
                    $course = mysqli_real_escape_string($conn, trim($row[2] ?? 'Juris Doctor'));

                    if (empty($student_no) || empty($name)) {
                        $skipped++;
                        continue;
                    }

                    // Skip duplicates
                    $check = mysqli_query($conn, "SELECT id FROM students WHERE student_no = '$student_no'");
                    if (mysqli_num_rows($check) > 0) {
                        $skipped++;
                        continue;
                    }

                    mysqli_query($conn, "INSERT INTO students (student_no, name, course) 
                                        VALUES ('$student_no', '$name', '$course')");
                    $success++;

                }
            }

            if ($type != 'books') {
                $message = '<div class="alert alert-success">
                            ✅ Import complete! <strong>' . $success . '</strong> record(s) added. 
                            <strong>' . $skipped . '</strong> skipped (duplicates or empty rows).
                        </div>';
            }

        } catch (Exception $e) {
            $message = '<div class="alert alert-danger">Error reading file: ' . $e->getMessage() . '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/header.css">
    <style>
        :root {
            --tcm-purple: #4B2E83;
            --tcm-purple-dark: #35205E;
            --tcm-gold: #D4A72C;
            --tcm-gold-dark: #B88F22;
        }

        body {
            background: linear-gradient(180deg, #f6f4f9 0%, #ece5f3 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .page-heading {
            color: var(--tcm-purple-dark);
            font-weight: 700;
        }

        .card-tcm {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 16px rgba(75, 46, 131, 0.08);
            overflow: hidden;
        }

        .card-header-green {
            background-color: var(--tcm-purple);
            color: #fff;
            font-weight: 700;
        }

        .card-header-gold {
            background-color: var(--tcm-gold);
            color: var(--tcm-purple-dark);
            font-weight: 700;
        }

        .card-header-notice {
            background-color: #fff8e6;
            color: var(--tcm-gold-dark);
            font-weight: 700;
            border-bottom: 1px solid #f0e0ad;
        }

        .card-notice {
            border: 1px solid #f0e0ad;
        }

        .btn-tcm-purple {
            background-color: var(--tcm-purple);
            color: #fff;
            font-weight: 600;
            border: none;
        }

        .btn-tcm-purple:hover {
            background-color: var(--tcm-purple-dark);
            color: #fff;
        }

        .btn-tcm-gold {
            background-color: var(--tcm-gold);
            color: var(--tcm-purple-dark);
            font-weight: 600;
            border: none;
        }

        .btn-tcm-gold:hover {
            background-color: var(--tcm-gold-dark);
            color: var(--tcm-purple-dark);
        }
    </style>
</head>
<body>

<?php $nav_mode = 'page'; include('navbar.php'); ?>

<div class="container mt-4" style="max-width: 600px;">
    <h4 class="page-heading mb-4">Import Data</h4>

    <?php echo $message; ?>

    <!-- Import Students -->
    <div class="card card-tcm mb-4">
        <div class="card-header card-header-green">
            <i class="bi bi-person-badge me-1"></i>Import Students
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Upload an Excel or CSV file with columns in this exact order:<br>
                <strong>Column A:</strong> Student Number &nbsp;|&nbsp;
                <strong>Column B:</strong> Full Name &nbsp;|&nbsp;
                <strong>Column C:</strong> Course <span class="text-muted">(optional — defaults to Juris Doctor)</span>
            </p>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="type" value="students">
                <div class="mb-3">
                    <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
                </div>
                <button type="submit" class="btn btn-tcm-purple w-100">Import Students</button>
            </form>
        </div>
    </div>

    <!-- Import Books -->
    <div class="card card-tcm mb-4">
        <div class="card-header card-header-gold">
            <i class="bi bi-book me-1"></i>Import Books
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Upload the College of Law <strong>Library Checklist</strong> (Excel or CSV) with these columns:<br>
                <strong>Acc. Nos.</strong> &nbsp;|&nbsp; <strong>Class</strong> &nbsp;|&nbsp; <strong>Title of the Book</strong> &nbsp;|&nbsp;
                <strong>Author</strong> &nbsp;|&nbsp; <strong>Edition</strong> &nbsp;|&nbsp; <strong>Volume</strong> &nbsp;|&nbsp;
                <strong>Pages</strong> &nbsp;|&nbsp; <strong>Fund</strong> &nbsp;|&nbsp; <strong>Publication</strong> &nbsp;|&nbsp; <strong>Year</strong><br>
                The headings are found automatically, so a title line above them is fine. Each row is one book.
                An optional <strong>Copies</strong> column is also understood.
            </p>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="type" value="books">
                <div class="mb-3">
                    <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="preview" value="1" id="preview-check">
                    <label class="form-check-label small" for="preview-check">
                        <strong>Preview only</strong> — check the file and show what would happen, without saving anything
                    </label>
                </div>
                <button type="submit" class="btn btn-tcm-gold w-100">Import Books</button>
            </form>
        </div>
    </div>

    <!-- Instructions -->
    <div class="card card-notice">
        <div class="card-header card-header-notice">
            <i class="bi bi-exclamation-triangle me-1"></i>Before Importing
        </div>
        <div class="card-body small text-muted">
            <ul class="mb-0">
                <li><strong>Students:</strong> Row 1 of the file must be the header row (e.g. "Student No", "Name", "Course") — it is skipped automatically.</li>
                <li><strong>Books:</strong> the headings are found automatically, wherever they are in the first rows.</li>
                <li>For Google Sheets — go to <strong>File → Download → CSV</strong> and upload that file.</li>
                <li>For Excel — save as <strong>.xlsx</strong> and upload directly.</li>
                <li>Duplicate student numbers and Acc. Nos. are <strong>skipped automatically</strong> — no double entries.</li>
                <li>You can import multiple times safely — only new records get added.</li>
            </ul>
        </div>
    </div>

</div>

</body>
</html>