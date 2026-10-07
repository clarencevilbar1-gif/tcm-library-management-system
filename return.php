<?php
include('auth.php');
ini_set('display_errors', 1);
error_reporting(E_ALL);
include('db.php');
include('loan_functions.php');

$message = '';
$selected_student = 0;

// Handle Return (one or several books of the selected student)
if (isset($_POST['return'])) {
    $student_id = intval($_POST['student_id'] ?? 0);
    $selected_student = $student_id;
    $borrowing_ids = array_unique(array_map('intval', $_POST['borrowing_ids'] ?? []));

    if ($student_id <= 0) {
        $message = '<div class="alert alert-danger">Please select a student.</div>';
    } elseif (count($borrowing_ids) == 0) {
        $message = '<div class="alert alert-danger">Please select at least one book to return.</div>';
    } else {
        $date = date('Y-m-d');
        $returned = 0;
        $skipped = 0;

        foreach ($borrowing_ids as $borrowing_id) {
            // Only records that belong to this student and are still unreturned
            $rec = mysqli_query($conn, "SELECT book_id FROM borrowing WHERE id = $borrowing_id AND student_id = $student_id AND return_date IS NULL");
            $row = mysqli_fetch_assoc($rec);
            if (!$row) {
                $skipped++;
                continue;
            }

            mysqli_query($conn, "UPDATE borrowing SET return_date = '$date' WHERE id = $borrowing_id AND return_date IS NULL");
            if (mysqli_affected_rows($conn) > 0) {
                $book_id = intval($row['book_id']);
                mysqli_query($conn, "UPDATE books SET available_copies = LEAST(available_copies + 1, total_copies), is_available = 1 WHERE id = $book_id");
                $returned++;
            } else {
                $skipped++;
            }
        }

        if ($returned > 0) {
            $message = '<div class="alert alert-success">' . $returned . ' book(s) returned successfully!</div>';
        }
        if ($skipped > 0) {
            $message .= '<div class="alert alert-warning">' . $skipped . ' record(s) were already returned or could not be found.</div>';
        }
    }
}

$loans = get_active_loans($conn);

// Group current loans by student for the dropdown + checklist.
// Includes deactivated students too: they may still be holding books.
$by_student = [];
foreach ($loans as $l) {
    $sid = $l['student_id'];
    if (!isset($by_student[$sid])) {
        $by_student[$sid] = [
            'id' => (int)$sid,
            'student_no' => $l['student_no'],
            'name' => $l['student_name'],
            'is_active' => (int)$l['is_active'],
            'loans' => []
        ];
    }
    $by_student[$sid]['loans'][] = [
        'id' => (int)$l['id'],
        'title' => $l['book_title'],
        'serial' => $l['serial_no'],
        'borrow_date' => $l['borrow_date'],
        'due_date' => $l['due_date'],
        'overdue' => (bool)$l['is_overdue']
    ];
}
uasort($by_student, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });

// Keep the student selected after a return only if they still hold books
if (!isset($by_student[$selected_student])) {
    $selected_student = 0;
}

$js_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$students_json = json_encode($by_student, $js_flags);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Book</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
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

        .navbar-tcm {
            background-color: var(--tcm-purple);
            padding: 0.9rem 2rem;
        }

        .navbar-tcm .navbar-brand {
            color: #fff;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .navbar-tcm .navbar-brand img {
            width: 40px;
            height: 40px;
            object-fit: contain;
        }

        .navbar-tcm .btn-outline-light {
            border-color: rgba(255,255,255,0.5);
            font-size: 0.85rem;
        }

        .navbar-tcm .btn-outline-light:hover {
            background-color: var(--tcm-gold);
            border-color: var(--tcm-gold);
            color: var(--tcm-purple-dark);
        }

        .section-heading {
            color: var(--tcm-purple-dark);
            font-weight: 700;
        }

        .card-tcm {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 16px rgba(75, 46, 131, 0.1);
            overflow: hidden;
        }

        .card-header-borrow {
            background-color: var(--tcm-gold);
            color: var(--tcm-purple-dark);
            font-weight: 700;
        }

        .card-header-return {
            background-color: var(--tcm-purple);
            color: #fff;
            font-weight: 700;
        }

        #book-counter {
            background-color: var(--tcm-purple-dark) !important;
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

        .btn-outline-tcm-gold {
            border: 1px solid var(--tcm-gold-dark);
            color: var(--tcm-gold-dark);
            background: transparent;
        }

        .btn-outline-tcm-gold:hover {
            background-color: var(--tcm-gold);
            color: var(--tcm-purple-dark);
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

        .card-table {
            border: none;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(75, 46, 131, 0.1);
        }

        .table thead th {
            background-color: var(--tcm-purple) !important;
            color: #fff;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border: none;
            padding: 0.9rem 1rem;
        }

        .table tbody td {
            vertical-align: middle;
            padding: 0.8rem 1rem;
        }

        .table tbody tr:hover {
            background-color: rgba(75, 46, 131, 0.05);
        }

        /* ---- Borrow Book page layout ---- */
        .page-heading {
            color: var(--tcm-purple-dark);
            font-weight: 700;
        }

        .page-subheading {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .btn-outline-tcm-purple {
            border: 1px solid var(--tcm-purple);
            color: var(--tcm-purple);
            background: transparent;
            font-weight: 600;
        }

        .btn-outline-tcm-purple:hover {
            background-color: var(--tcm-purple);
            color: #fff;
        }

        .card-header-loans {
            background-color: var(--tcm-purple);
            color: #fff;
            font-weight: 700;
        }

        .card-header-loans .badge {
            background-color: var(--tcm-gold) !important;
            color: var(--tcm-purple-dark);
        }

        .loan-filter-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
            padding: 0.55rem 1rem;
            background: #faf8fd;
            border-bottom: 1px solid #eee7f5;
            font-size: 0.85rem;
            color: #6c757d;
        }

        .loan-filter-bar a {
            color: var(--tcm-purple);
            text-decoration: none;
            font-weight: 600;
            white-space: nowrap;
        }

        .loan-filter-bar a:hover {
            text-decoration: underline;
        }

        .loans-scroll {
            max-height: 560px;
            overflow: auto;
        }

        .table-loans {
            table-layout: fixed;
            width: 100%;
            min-width: 700px;
            margin-bottom: 0;
        }

        .table-loans thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background-color: var(--tcm-purple) !important;
            color: #fff;
            font-weight: 600;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border: none;
            padding: 0.75rem;
            line-height: 1.2;
            text-align: left;
        }

        .table-loans tbody td {
            vertical-align: middle;
            padding: 0.7rem 0.75rem;
            font-size: 0.9rem;
            text-align: left;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .table-loans .student-no {
            white-space: nowrap;
            font-size: 0.8rem;
        }

        .table-loans td.nowrap {
            white-space: nowrap;
        }

        .table-loans th.col-center,
        .table-loans td.col-center {
            text-align: center;
        }

        /* Disabled Return button: dimmed purple instead of Bootstrap's transparent ghost */
        .btn-tcm-purple:disabled {
            background-color: var(--tcm-purple);
            color: #fff;
            opacity: 0.45;
        }

        /* ---- Return Book checklist ---- */
        .return-item {
            cursor: pointer;
            border-color: #eee7f5;
        }

        .return-item:hover {
            background-color: rgba(75, 46, 131, 0.05);
        }

        .return-item-body {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .form-check-input:checked {
            background-color: var(--tcm-purple);
            border-color: var(--tcm-purple);
        }
    </style>
</head>
<body>

<nav class="navbar navbar-tcm d-flex justify-content-between">
    <span class="navbar-brand">
        <img src="assets/images/tcm_logo.png" alt="TCM Seal">
        Law Department Library
    </span>
    <div class="d-flex align-items-center gap-3">
        <a href="index.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Menu</a>
        <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
    </div>
</nav>

<div class="container mt-4 mb-5">

    <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
        <div>
            <h4 class="page-heading mb-1"><i class="bi bi-journal-arrow-down me-2"></i>Return Book</h4>
            <p class="page-subheading mb-0">Select a student, then tick the book(s) being returned.</p>
        </div>
        <a href="borrow.php" class="btn btn-outline-tcm-purple btn-sm">
            <i class="bi bi-journal-arrow-up me-1"></i>Go to Borrow Book
        </a>
    </div>

    <?php echo $message; ?>

    <div class="row g-4">

        <div class="col-xl-4">
            <div class="card card-tcm">
                <div class="card-header card-header-return d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-journal-arrow-down me-1"></i>Return Books</span>
                    <span id="return-counter" class="badge bg-dark">0 selected</span>
                </div>
                <div class="card-body">
                    <?php if (count($by_student) == 0): ?>
                        <p class="text-muted text-center my-4">No books are currently borrowed.</p>
                    <?php else: ?>
                    <form method="POST" id="return-form">
                        <div class="mb-3">
                            <label class="form-label">Select Student</label>
                            <select name="student_id" id="student-select" class="form-select">
                                <option value=""></option>
                                <?php foreach ($by_student as $st): ?>
                                <option value="<?php echo $st['id']; ?>">
                                    <?php
                                    echo htmlspecialchars($st['student_no'] . ' — ' . $st['name']);
                                    echo ' (' . count($st['loans']) . (count($st['loans']) == 1 ? ' book)' : ' books)');
                                    echo $st['is_active'] ? '' : ' [inactive]';
                                    ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="return-panel" style="display:none;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Books to return</label>
                                <div class="form-check mb-0">
                                    <input type="checkbox" class="form-check-input" id="select-all">
                                    <label class="form-check-label small" for="select-all">Select all</label>
                                </div>
                            </div>
                            <div id="return-list" class="list-group mb-3"></div>
                            <button type="submit" name="return" class="btn btn-tcm-purple w-100" id="return-btn" disabled>
                                Return Selected Book(s)
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card card-tcm">
                <div class="card-header card-header-loans d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-check me-1"></i>Currently Borrowed Books</span>
                    <span class="badge"><?php echo count($loans) . (count($loans) == 1 ? ' record' : ' records'); ?></span>
                </div>
                <div class="loans-scroll">
                    <?php render_loans_table($loans, 'No books are currently borrowed.'); ?>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
const studentsData = <?php echo $students_json; ?>;

$(document).ready(function() {
    $('#student-select').select2({
        theme: 'bootstrap-5',
        placeholder: '-- Search Student by name or ID --',
        allowClear: true,
        width: '100%'
    });

    function updateSelection() {
        var checked = $('#return-list .return-check:checked').length;
        var total = $('#return-list .return-check').length;
        $('#return-counter').text(checked + ' selected');
        $('#return-btn').prop('disabled', checked === 0)
            .text(checked > 0 ? 'Return ' + checked + ' Selected Book(s)' : 'Return Selected Book(s)');
        $('#select-all').prop('checked', total > 0 && checked === total);
    }

    function renderList(studentId) {
        var $list = $('#return-list').empty();
        var student = studentsData[studentId];

        if (!student) {
            $('#return-panel').hide();
            updateSelection();
            return;
        }

        student.loans.forEach(function(l) {
            var $item = $('<label class="list-group-item d-flex gap-2 align-items-start return-item"></label>');
            $item.append($('<input type="checkbox" class="form-check-input mt-1 flex-shrink-0 return-check" name="borrowing_ids[]">').val(l.id));

            var $body = $('<div class="flex-grow-1 return-item-body"></div>');
            $body.append($('<div class="fw-semibold"></div>').text(l.title));
            $body.append($('<div class="small text-muted"></div>').text(
                l.serial + '  ·  Borrowed ' + l.borrow_date + '  ·  Due ' + (l.due_date ? l.due_date : '—')
            ));
            $item.append($body);

            $item.append(l.overdue
                ? '<span class="badge bg-danger align-self-center">Overdue</span>'
                : '<span class="badge bg-success align-self-center">Active</span>');

            $list.append($item);
        });

        $('#return-panel').show();
        updateSelection();
    }

    $('#student-select').on('change', function() {
        renderList($(this).val());
    });

    $(document).on('change', '.return-check', updateSelection);

    $('#select-all').on('change', function() {
        $('#return-list .return-check').prop('checked', $(this).is(':checked'));
        updateSelection();
    });

    <?php if ($selected_student): ?>
    $('#student-select').val('<?php echo $selected_student; ?>').trigger('change');
    <?php endif; ?>
});
</script>

</body>
</html>