<?php
ob_start();
include('auth.php');
ini_set('display_errors', 1);
error_reporting(E_ALL);
include('db.php');
include('book_functions.php');
include('student_functions.php');
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
$student_view = '';       // the student preview / result (shown instead of the two upload cards)

// ======================================================================
// Students: reads the registrar's enrollment export (or any file with similar headings).
//   Step 1  upload  -> a PREVIEW is shown, nothing is saved
//   Step 2  confirm -> the import runs (all or nothing)
// The file is kept in the server's temp folder between the two steps and removed afterwards.
// ======================================================================
function st_tmp_path($token, $ext) {
    return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'tcm-import-' . $token . '.' . $ext;
}

function st_forget_upload() {
    if (!empty($_SESSION['st_import'])) {
        $u = $_SESSION['st_import'];
        @unlink(st_tmp_path($u['token'], $u['ext']));
    }
    unset($_SESSION['st_import']);
}

function st_purge_old_uploads() {
    foreach (glob(rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'tcm-import-*') ?: [] as $f) {
        if (@filemtime($f) < time() - 7200) @unlink($f);
    }
}

function st_alert($type, $html) {
    return '<div class="alert alert-' . $type . '">' . $html . '</div>';
}

// the little list of column choices for one field
function st_col_options($rows, $hdr, $selected) {
    $labels = isset($rows[$hdr]) ? $rows[$hdr] : [];
    $width = 0;
    foreach (array_slice($rows, 0, 30) as $r) $width = max($width, count($r));
    $html = '<option value="">— not in this file —</option>';
    for ($c = 0; $c < $width; $c++) {
        $t = isset($labels[$c]) ? st_text($labels[$c]) : '';
        if ($t === '' && $selected !== $c) continue;
        $html .= '<option value="' . $c . '"' . ($selected === $c ? ' selected' : '') . '>' . st_col_letter($c) . ($t !== '' ? ' — ' . st_h(mb_substr($t, 0, 40)) : '') . '</option>';
    }
    return $html;
}

function st_preview_html($file, $data, $hdr, $map, $opts, $rep, $err) {
    $labels = st_field_labels();
    $ok = ($rep !== null);
    $total = $ok ? $rep['new'] + $rep['updated'] : 0;
    $changes = $ok ? $total + $rep['reactivated'] + $rep['deactivated'] : 0;
    $h = '';
    $h .= '<form method="POST" id="st-form">';
    $h .= '<input type="hidden" name="type" value="students"><input type="hidden" name="opts_shown" value="1"><input type="hidden" name="token" value="' . st_h($_SESSION['st_import']['token']) . '">';
    $h .= '<button type="submit" name="action" value="preview" class="d-none" tabindex="-1" aria-hidden="true"></button>';

    $h .= '<div class="card card-tcm mb-3"><div class="card-header card-header-green"><i class="bi bi-person-lines-fill me-1"></i>Check before importing</div><div class="card-body">';
    $h .= '<p class="small text-muted mb-2"><strong>Nothing has been saved yet.</strong> File: <strong>' . st_h($file) . '</strong>'
        . (count($data['sheets']) > 1 ? ' &middot; sheet <strong>' . st_h($data['sheet']) . '</strong>' : '') . ' &middot; column headings found on row <strong>' . ($hdr + 1) . '</strong></p>';
    if ($err) $h .= st_alert('danger', $err);

    if ($ok) {
        $h .= '<ul class="mb-2">';
        $h .= '<li><strong>' . $rep['new'] . '</strong> new student(s) will be added.</li>';
        if (!empty($opts['update_existing'])) {
            $h .= '<li><strong>' . $rep['updated'] . '</strong> student(s) already in the system will be updated; <strong>' . $rep['unchanged'] . '</strong> are already up to date.</li>';
        } else {
            $h .= '<li><strong>' . $rep['already'] . '</strong> student(s) are already in the system and will be left as they are.</li>';
        }
        if ($rep['reactivated']) $h .= '<li><strong>' . $rep['reactivated'] . '</strong> deactivated student(s) are in this file and will be <strong>reactivated</strong>.</li>';
        if ($rep['deactivated']) $h .= '<li><strong>' . $rep['deactivated'] . '</strong> active student(s) who are not in this file will be <strong>deactivated</strong>.</li>';
        $h .= '</ul>';
        $skip = [];
        if ($rep['missing_id']) $skip[] = '<li><strong>' . count($rep['missing_id']) . '</strong> row(s) have no student number (rows ' . st_h(implode(', ', array_slice($rep['missing_id'], 0, 15))) . (count($rep['missing_id']) > 15 ? ' …' : '') . ').</li>';
        if ($rep['missing_name']) $skip[] = '<li><strong>' . count($rep['missing_name']) . '</strong> row(s) have no name (student number ' . st_h(implode(', ', array_map(function ($x) { return $x['no']; }, array_slice($rep['missing_name'], 0, 8)))) . (count($rep['missing_name']) > 8 ? ' …' : '') . ').</li>';
        if ($rep['dup_in_file']) $skip[] = '<li><strong>' . count($rep['dup_in_file']) . '</strong> row(s) repeat a student number that is already in this file (only the first is used): ' . st_h(implode(', ', array_map(function ($x) { return $x['no'] . ' (row ' . $x['row'] . ')'; }, array_slice($rep['dup_in_file'], 0, 6)))) . (count($rep['dup_in_file']) > 6 ? ' …' : '') . '</li>';
        if ($rep['filtered']) $skip[] = '<li><strong>' . $rep['filtered'] . '</strong> row(s) left out by the course / status choice below.</li>';
        if ($rep['truncated']) $skip[] = '<li><strong>' . $rep['truncated'] . '</strong> value(s) were too long and were shortened.</li>';
        if ($skip) $h .= '<div class="small"><div class="text-muted mb-1">Skipped:</div><ul class="mb-2">' . implode('', $skip) . '</ul></div>';
        if ($rep['no_course_column']) $h .= '<div class="small text-muted mb-2">The file has no Course column, so every new student gets <strong>' . st_h(ST_COURSES[0]) . '</strong>.</div>';
        if ($rep['samples']) $h .= '<div class="small mb-2"><span class="text-muted">Names will look like this:</span><br><strong>' . implode('</strong><br><strong>', array_map('st_h', $rep['samples'])) . '</strong></div>';
        if ($rep['changes']) {
            $h .= '<details class="small mb-2"><summary>Show some of the updates</summary><ul class="mt-1 mb-0">';
            $lab = ['name' => 'name', 'gender' => 'gender', 'course' => 'course', 'year_level' => 'year level', 'contact_number' => 'contact'];
            foreach ($rep['changes'] as $c) {
                $parts = [];
                foreach ($c['diff'] as $f => $d) $parts[] = $lab[$f] . ': ' . st_h($d[0] === null || $d[0] === '' ? '(empty)' : $d[0]) . ' → ' . st_h($d[1]);
                $h .= '<li>' . st_h($c['no']) . ' — ' . implode('; ', $parts) . '</li>';
            }
            $h .= '</ul></details>';
        }
    } else {
        $h .= '<p class="mb-0">Tell the system which column holds what (see <strong>Columns</strong> below), then press <strong>Check again</strong>.</p>';
    }
    $h .= '</div></div>';

    // ---- values that are not in the fixed lists
    $unk = $ok ? array_filter($rep['unknown']) : [];
    if ($unk) {
        $h .= '<div class="card card-tcm card-notice mb-3"><div class="card-header card-header-notice"><i class="bi bi-question-circle me-1"></i>Values that were not recognized</div><div class="card-body small">';
        $h .= '<p class="text-muted">The table only keeps one spelling for each of these. Choose what each value below should become. If you leave it as &ldquo;leave blank&rdquo;, those students are still imported, with that field empty.</p>';
        $h .= '<table class="table table-sm align-middle mb-0"><thead><tr><th>Field</th><th>Value in the file</th><th>Students</th><th>Becomes</th></tr></thead><tbody>';
        $sets = ['gender' => ['Gender', ST_GENDERS], 'year_level' => ['Year level', ST_LEVELS], 'course' => ['Course', ST_COURSES]];
        foreach ($unk as $field => $vals) {
            foreach ($vals as $raw => $info) {
                $h .= '<tr><td>' . $sets[$field][0] . '</td><td><strong>' . st_h($raw) . '</strong></td><td>' . $info['count'] . '</td><td><select class="form-select form-select-sm" style="min-width:140px" name="vm[' . $field . '][' . bin2hex($raw) . ']"><option value="">leave blank</option>';
                foreach ($sets[$field][1] as $o) $h .= '<option' . ($info['chosen'] === $o ? ' selected' : '') . '>' . st_h($o) . '</option>';
                $h .= '</select></td></tr>';
            }
        }
        $h .= '</tbody></table></div></div>';
    }

    // ---- which courses / statuses to take
    $filters = '';
    if ($ok && count($rep['courses']) > 1) {
        $filters .= '<div class="mb-2"><div class="fw-semibold small mb-1">Courses to import</div>';
        foreach ($rep['courses'] as $v => $n) {
            $on = !isset($opts['filter_course']) || in_array((string)$v, array_map('strval', (array)$opts['filter_course']), true);
            $filters .= '<div class="form-check"><input class="form-check-input" type="checkbox" name="fc[]" value="' . st_h($v) . '" id="fc' . md5($v) . '"' . ($on ? ' checked' : '') . '><label class="form-check-label small" for="fc' . md5($v) . '">' . st_h($v) . ' <span class="text-muted">(' . $n . ')</span></label></div>';
        }
        $filters .= '<input type="hidden" name="fc_shown" value="1"></div>';
    }
    if ($ok && count($rep['statuses']) > 1) {
        $filters .= '<div class="mb-2"><div class="fw-semibold small mb-1">Enrollment status to import</div>';
        foreach ($rep['statuses'] as $v => $n) {
            $on = !isset($opts['filter_status']) || in_array((string)$v, array_map('strval', (array)$opts['filter_status']), true);
            $filters .= '<div class="form-check"><input class="form-check-input" type="checkbox" name="fs[]" value="' . st_h($v) . '" id="fs' . md5($v) . '"' . ($on ? ' checked' : '') . '><label class="form-check-label small" for="fs' . md5($v) . '">' . st_h($v) . ' <span class="text-muted">(' . $n . ')</span></label></div>';
        }
        $filters .= '<input type="hidden" name="fs_shown" value="1"></div>';
    }

    // ---- students that are not in the file / students that came back
    if ($ok && $rep['missing']) {
        $nm = count($rep['missing']);
        $loanN = 0; foreach ($rep['missing'] as $m) if ($m['loan']) $loanN++;
        $h2  = '<div class="alert alert-warning small mb-3"><div class="mb-2"><strong>⚠ ' . $nm . ' active student' . ($nm == 1 ? ' is' : 's are') . ' not in this file</strong> (they may not have enrolled this semester).</div>';
        $h2 .= '<div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="deactivate_missing" value="1" id="deact"' . (!empty($opts['deactivate_missing']) ? ' checked' : '') . '><label class="form-check-label" for="deact"><strong>Deactivate them</strong> (their records and borrowing history are kept; you can reactivate anyone later)</label></div>';
        if ($loanN) $h2 .= '<div class="text-muted mb-1">' . $loanN . ' of them still ha' . ($loanN == 1 ? 's' : 've') . ' borrowed books and will be skipped until the books are returned.</div>';
        if ($rep['mass']) {
            $h2 .= '<div class="alert alert-danger py-2 my-2"><strong>This looks like a partial list.</strong> More than half of all active students would be deactivated. Is this file really the complete enrollment list?'
                 . '<div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="confirm_mass" value="1" id="mass"' . (!empty($opts['confirm_mass']) ? ' checked' : '') . '><label class="form-check-label" for="mass">I understand, deactivate them anyway</label></div></div>';
        }
        $h2 .= '<details><summary>Show the ' . $nm . ' student' . ($nm == 1 ? '' : 's') . '</summary><ul class="mb-0 mt-1">';
        foreach (array_slice($rep['missing'], 0, 40) as $m) $h2 .= '<li>' . st_h($m['no']) . ' — ' . st_h($m['name']) . ($m['loan'] ? ' <span class="text-danger">(has borrowed books)</span>' : '') . '</li>';
        if ($nm > 40) $h2 .= '<li>… and ' . ($nm - 40) . ' more.</li>';
        $h2 .= '</ul></details></div>';
        $filters .= $h2;
    }
    if ($ok && $rep['returning']) {
        $rn = count($rep['returning']);
        $filters .= '<div class="alert alert-info small mb-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="reactivate" value="1" id="react"' . (!empty($opts['reactivate']) ? ' checked' : '') . '><label class="form-check-label" for="react"><strong>Reactivate ' . $rn . ' returning student' . ($rn == 1 ? '' : 's') . '</strong> — deactivated before, but in this file again ('
            . st_h(implode(', ', array_map(function ($x) { return $x['name']; }, array_slice($rep['returning'], 0, 3)))) . ($rn > 3 ? ', …' : '') . ').</label></div></div>';
    }
    $h .= '<div class="card card-tcm mb-3"><div class="card-header card-header-gold"><i class="bi bi-sliders me-1"></i>Options</div><div class="card-body">';
    $h .= $filters;
    $h .= '<div class="form-check"><input class="form-check-input" type="checkbox" name="update_existing" value="1" id="upd"' . (!empty($opts['update_existing']) ? ' checked' : '') . '>'
        . '<label class="form-check-label small" for="upd"><strong>Update students already in the system</strong> (name, gender, course, year level, contact) with what is in this file. Use this each semester so year levels stay current. Empty cells in the file never erase anything.</label></div>';
    $h .= '</div></div>';

    // ---- the columns
    $h .= '<div class="card card-tcm mb-3"><details' . (($ok && !empty($map) && !$err) ? '' : ' open') . '><summary class="card-header" style="cursor:pointer;"><i class="bi bi-columns-gap me-1"></i>Columns — which column holds what <span class="small text-muted">(found automatically; change only if something is wrong)</span></summary><div class="card-body">';
    $h .= '<div class="row g-2 align-items-center mb-3"><div class="col-auto"><label class="small" for="hdr">Column headings are on row</label></div><div class="col-auto"><input type="number" min="1" max="60" class="form-control form-control-sm" style="width:80px" name="hdr" id="hdr" value="' . ($hdr + 1) . '"></div></div>';
    $h .= '<div class="row g-2">';
    foreach ($labels as $f => $label) {
        $req = ($f === 'student_no');
        $h .= '<div class="col-sm-6"><label class="form-label small mb-1">' . st_h($label) . ($req ? ' <span class="text-danger">*</span>' : '') . '</label>'
            . '<select class="form-select form-select-sm" name="map[' . $f . ']">' . st_col_options($data['rows'], $hdr, isset($map[$f]) ? $map[$f] : null) . '</select></div>';
    }
    $h .= '</div><p class="small text-muted mt-2 mb-0">Name: use <strong>Last name</strong> and <strong>First name</strong> (with the middle initial and suffix if the file has them) &mdash; or just <strong>Full name</strong> if the file has a single name column. The other columns of the file are ignored.</p>';
    $h .= '</div></details></div>';

    $h .= '<div class="d-flex flex-wrap gap-2 mb-4">';
    $h .= '<button type="submit" name="action" value="import" class="btn btn-tcm-purple px-4"' . (($changes > 0 && !($ok && $rep['mass_blocked'])) ? '' : ' disabled') . ' id="st-go"><i class="bi bi-check2-circle me-1"></i>' . ($ok ? ($total > 0 ? 'Import ' . $total . ' student' . ($total == 1 ? '' : 's') : 'Apply changes') : 'Import') . '</button>';
    $h .= '<button type="submit" name="action" value="preview" class="btn btn-outline-secondary"><i class="bi bi-arrow-repeat me-1"></i>Check again</button>';
    $h .= '<button type="submit" name="action" value="cancel" class="btn btn-outline-danger ms-auto" formnovalidate>Cancel</button>';
    $h .= '</div></form>';
    $h .= '<script>document.getElementById("st-form").addEventListener("submit",function(e){var b=e.submitter;if(b&&b.value==="import"){b.disabled=true;b.innerHTML="Importing…";var i=document.createElement("input");i.type="hidden";i.name="action";i.value="import";this.appendChild(i);}});</script>';
    return $h;
}

function st_result_html($rep) {
    $h = '<div class="alert alert-success"><div class="fs-6 mb-1">✅ Import complete!</div><ul class="mb-0">';
    $h .= '<li><strong>' . $rep['new'] . '</strong> student(s) added.</li>';
    if ($rep['updated'] || $rep['unchanged']) $h .= '<li><strong>' . $rep['updated'] . '</strong> student(s) updated, <strong>' . $rep['unchanged'] . '</strong> already up to date.</li>';
    if ($rep['reactivated']) $h .= '<li><strong>' . $rep['reactivated'] . '</strong> returning student(s) reactivated.</li>';
    if ($rep['deactivated']) $h .= '<li><strong>' . $rep['deactivated'] . '</strong> student(s) who were not in the file were deactivated.</li>';
    if ($rep['kept_active'] && !empty($rep['deactivate_asked'])) $h .= '<li><strong>' . $rep['kept_active'] . '</strong> were not deactivated because they still have borrowed books.</li>';
    if ($rep['already']) $h .= '<li><strong>' . $rep['already'] . '</strong> already in the system (left as they were).</li>';
    $skipped = count($rep['missing_id']) + count($rep['missing_name']) + count($rep['dup_in_file']) + $rep['filtered'];
    if ($skipped) $h .= '<li><strong>' . $skipped . '</strong> row(s) skipped (no student number, no name, repeated number, or left out by your choice).</li>';
    $blank = 0;
    foreach ($rep['unknown'] as $vals) foreach ($vals as $info) if ($info['chosen'] === '') $blank += $info['count'];
    if ($blank) $h .= '<li><strong>' . $blank . '</strong> value(s) were not recognized and were left blank — you can set them with <em>Edit</em> on the Student Records page.</li>';
    $h .= '</ul></div><div class="d-flex gap-2 mb-4"><a href="students.php" class="btn btn-tcm-purple"><i class="bi bi-people me-1"></i>Open Student Records</a><a href="import.php" class="btn btn-outline-secondary">Import another file</a></div>';
    return $h;
}

function student_import_handle($conn) {
    $action = isset($_POST['action']) ? $_POST['action'] : 'upload';
    if (!st_columns_ready($conn)) return st_migration_notice();

    if ($action === 'cancel') {
        st_forget_upload();
        $_SESSION['st_notice'] = st_alert('secondary', 'Import cancelled. Nothing was saved.');
        header('Location: import.php?done=1'); exit();
    }

    $name = '';
    if ($action === 'upload') {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            return st_alert('danger', 'The file could not be uploaded' . (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_INI_SIZE ? ' (it is too big for this server).' : '.'));
        }
        $name = $_FILES['file']['name'];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            return st_alert('danger', 'Invalid file type. Please upload an Excel (.xls, .xlsx) or CSV file.');
        }
        st_purge_old_uploads();
        st_forget_upload();
        $token = bin2hex(random_bytes(12));
        if (!move_uploaded_file($_FILES['file']['tmp_name'], st_tmp_path($token, $ext))) {
            return st_alert('danger', 'The file could not be saved for checking.');
        }
        $_SESSION['st_import'] = ['token' => $token, 'ext' => $ext, 'name' => $name];
    } else {
        $u = isset($_SESSION['st_import']) ? $_SESSION['st_import'] : null;
        if (!$u || !isset($_POST['token']) || !hash_equals($u['token'], (string)$_POST['token']) || !is_file(st_tmp_path($u['token'], $u['ext']))) {
            return st_alert('warning', 'This import has expired (or was already done). Please upload the file again.');
        }
        $name = $u['name'];
    }
    $u = $_SESSION['st_import'];
    $path = st_tmp_path($u['token'], $u['ext']);

    try {
        $data = st_read_file($path, $u['ext']);
    } catch (Throwable $e) {
        st_forget_upload();
        return st_alert('danger', 'The file could not be read: ' . st_h($e->getMessage()));
    }

    // columns: found automatically on upload, or taken from the form
    $opts = ['update_existing' => true, 'reactivate' => true, 'deactivate_missing' => false, 'confirm_mass' => false, 'filter_course' => null, 'filter_status' => null, 'valmap' => []];
    if ($action === 'upload') {
        if ($data['found']) { $hdr = $data['found'][0]; $map = $data['found'][1]; }
        else {
            $hdr = 0;
            foreach ($data['rows'] as $i => $r) { if (array_filter($r, function ($c) { return trim((string)$c) !== ''; })) { $hdr = $i; break; } }
            $map = st_match_headings(isset($data['rows'][$hdr]) ? $data['rows'][$hdr] : []);
        }
    } else {
        $hdr = max(0, (isset($_POST['hdr']) ? (int)$_POST['hdr'] : 1) - 1);
        $map = [];
        foreach (st_field_labels() as $f => $_) {
            if (isset($_POST['map'][$f]) && $_POST['map'][$f] !== '') $map[$f] = (int)$_POST['map'][$f];
        }
        $opts['update_existing'] = !empty($_POST['update_existing']);
        $opts['reactivate'] = isset($_POST['opts_shown']) ? !empty($_POST['reactivate']) : true;
        $opts['deactivate_missing'] = !empty($_POST['deactivate_missing']);
        $opts['confirm_mass'] = !empty($_POST['confirm_mass']);
        if (isset($_POST['fc_shown'])) $opts['filter_course'] = isset($_POST['fc']) ? array_map('strval', (array)$_POST['fc']) : [];
        if (isset($_POST['fs_shown'])) $opts['filter_status'] = isset($_POST['fs']) ? array_map('strval', (array)$_POST['fs']) : [];
        if (isset($_POST['vm']) && is_array($_POST['vm'])) $opts['valmap'] = $_POST['vm'];
    }

    $commit = ($action === 'import');
    $rep = null; $err = '';
    try {
        $rep = st_import($conn, $data['rows'], $data['fmt'], $hdr, $map, $opts, $commit);
    } catch (Throwable $e) {
        $err = st_h($e->getMessage());
        if ($commit) $err = 'Nothing was imported. ' . $err;
    }

    if ($commit && $rep !== null) {
        st_forget_upload();
        $_SESSION['st_flash'] = st_result_html($rep);
        header('Location: import.php?done=1'); exit();
    }
    return st_preview_html($name, $data, $hdr, $map, $opts, $rep, $err);
}

if (isset($_SESSION['st_notice'])) {
    $message = $_SESSION['st_notice'];
    unset($_SESSION['st_notice']);
}
if (isset($_SESSION['st_flash'])) {
    $student_view = $_SESSION['st_flash'];
    unset($_SESSION['st_flash']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['type']) && $_POST['type'] === 'students') {
    $student_view = student_import_handle($conn);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file']) && isset($_POST['type']) && $_POST['type'] === 'books') {
    $file = $_FILES['file']['tmp_name'];
    $filename = $_FILES['file']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
        $message = '<div class="alert alert-danger">Invalid file type. Please upload an Excel (.xlsx) or CSV file.</div>';
    } else {
        try {
            $spreadsheet = IOFactory::load($file);
            $sheet = $spreadsheet->getActiveSheet();
            // raw cell values, so numbers such as 1847 stay exactly as typed
            $raw_rows = $sheet->toArray(null, true, false, false);
            $preview = isset($_POST['preview']);
            $report = import_books($conn, $raw_rows, $preview);
            $message = book_import_report_html($report, $preview);
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger">Error reading file: ' . h($e->getMessage()) . '</div>';
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

<div class="container mt-4" style="max-width: <?php echo $student_view !== '' ? '860px' : '600px'; ?>;">
    <h4 class="page-heading mb-4">Import Data</h4>

    <?php echo $message; ?>
    <?php echo $student_view; ?>
<?php if ($student_view === ''): ?>

    <!-- Import Students -->
    <div class="card card-tcm mb-4">
        <div class="card-header card-header-green">
            <i class="bi bi-person-badge me-1"></i>Import Students
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Upload the registrar's <strong>enrollment list</strong> (Excel .xls / .xlsx, or CSV). The columns are found by their headings
                (<strong>ID</strong>, <strong>LName</strong>, <strong>FName</strong>, <strong>MI</strong>, <strong>Sfx</strong>, <strong>Gender</strong>,
                <strong>Course</strong>, <strong>Level</strong>), in any order &mdash; all other columns are ignored.
                Names are saved as <em>Last, First M. Suffix</em>. You will see a <strong>preview</strong> first; nothing is saved until you confirm.
            </p>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="type" value="students">
                <div class="mb-3">
                    <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
                </div>
                <button type="submit" class="btn btn-tcm-purple w-100">Upload &amp; Check Students</button>
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
                <li><strong>Students:</strong> the headings are found automatically, wherever they are in the first rows. If they are not recognized, you can pick the columns yourself on the preview page.</li>
                <li><strong>Books:</strong> the headings are found automatically, wherever they are in the first rows.</li>
                <li>For Google Sheets — go to <strong>File → Download → CSV</strong> and upload that file.</li>
                <li>For Excel — save as <strong>.xlsx</strong> and upload directly.</li>
                <li>Duplicate student numbers and Acc. Nos. are never added twice. For students, you can choose to <strong>update</strong> the ones already in the system.</li>
                <li>Gender, Course and Year Level are always saved in one fixed spelling (for example <em>Male</em>, <em>Juris Doctor</em>, <em>1st Year</em>), however the file writes them.</li>
            </ul>
        </div>
    </div>

<?php endif; ?>
</div>

</body>
</html>