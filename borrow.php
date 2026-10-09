<?php
include('auth.php');
ini_set('display_errors', 1);
error_reporting(E_ALL);
include('db.php');
include('loan_functions.php');

$message = '';
$selected_student = 0;

// Handle Borrow
if (isset($_POST['borrow'])) {
    $student_id = intval($_POST['student_id']);
    $selected_student = $student_id;
    $book_ids = $_POST['book_ids'] ?? [];
    $borrow_days = intval($_POST['borrow_days'] ?? 1);

    if ($borrow_days < 1 || $borrow_days > 3) {
        $borrow_days = 1;
    }

    $book_ids = array_unique($book_ids);

    $limit_check = mysqli_query($conn, "SELECT COUNT(*) as total FROM borrowing WHERE student_id = $student_id AND return_date IS NULL");
    $limit_row = mysqli_fetch_assoc($limit_check);
    $currently_borrowed = $limit_row['total'];
    $remaining_slots = 3 - $currently_borrowed;

    if (count($book_ids) == 0) {
        $message = '<div class="alert alert-danger">Please select at least one book.</div>';
    } elseif (count($book_ids) > $remaining_slots) {
        $message = '<div class="alert alert-danger">Too many books selected. This student can only borrow ' . $remaining_slots . ' more book(s).</div>';
    } else {
        $date = date('Y-m-d');
        $due_date = date('Y-m-d', strtotime($date . ' + ' . $borrow_days . ' days'));
        $success_count = 0;
        $errors = [];

        foreach ($book_ids as $book_id) {
            $book_id = intval($book_id);

            // A student can't hold two copies of the same title at once
            $dup_check = mysqli_query($conn, "SELECT id FROM borrowing WHERE student_id = $student_id AND book_id = $book_id AND return_date IS NULL");
            if (mysqli_num_rows($dup_check) > 0) {
                $errors[] = "This student already has a copy of Book ID $book_id borrowed.";
                continue;
            }

            // Take one copy atomically; fails (0 rows) if no copies are left
            mysqli_query($conn, "UPDATE books SET available_copies = available_copies - 1, is_available = (available_copies > 0) WHERE id = $book_id AND available_copies > 0");
            if (mysqli_affected_rows($conn) == 0) {
                $errors[] = "Book ID $book_id is no longer available.";
                continue;
            }

            $insert_ok = mysqli_query($conn, "INSERT INTO borrowing (student_id, book_id, borrow_date, borrow_days, due_date) VALUES ($student_id, $book_id, '$date', $borrow_days, '$due_date')");
            if ($insert_ok && mysqli_affected_rows($conn) > 0) {
                $success_count++;
            } else {
                // Put the copy back if the record could not be saved
                mysqli_query($conn, "UPDATE books SET available_copies = LEAST(available_copies + 1, total_copies), is_available = 1 WHERE id = $book_id");
                $errors[] = "Could not record borrowing for Book ID $book_id. Please try again.";
            }
        }

        if ($success_count > 0) {
            $message = '<div class="alert alert-success">' . $success_count . ' book(s) borrowed successfully!</div>';
        }
        if (!empty($errors)) {
            $message .= '<div class="alert alert-warning">' . implode('<br>', $errors) . '</div>';
        }
    }
}

$students = mysqli_query($conn, "SELECT * FROM students WHERE is_active = 1");

$books = mysqli_query($conn, "SELECT * FROM books WHERE available_copies > 0 ORDER BY serial_no ASC");
$books_array = [];
while ($b = mysqli_fetch_assoc($books)) {
    $books_array[] = $b;
}
$books_json = json_encode($books_array);

$loans = get_active_loans($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrow Book</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
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
    </style>
</head>
<body>

<?php $nav_mode = 'page'; include('navbar.php'); ?>

<div class="container mt-4 mb-5">

    <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
        <div>
            <h4 class="page-heading mb-1"><i class="bi bi-journal-arrow-up me-2"></i>Borrow Book</h4>
            <p class="page-subheading mb-0">Select a student to borrow books. The table shows that student's current records.</p>
        </div>
        <a href="return.php" class="btn btn-outline-tcm-purple btn-sm">
            <i class="bi bi-journal-arrow-down me-1"></i>Go to Return Book
        </a>
    </div>

    <?php echo $message; ?>

    <div class="row g-4">

        <div class="col-xl-4">
            <div class="card card-tcm">
                <div class="card-header card-header-borrow d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-journal-arrow-up me-1"></i>Borrow a Book</span>
                    <span id="book-counter" class="badge bg-dark">0 / 3</span>
                </div>
                <div class="card-body">
                    <form method="POST" id="borrow-form">

                        <div class="mb-3">
                            <label class="form-label">Select Student</label>
                            <select name="student_id" id="student-select" class="form-select" required>
                                <option value="">-- Choose Student --</option>
                                <?php 
                                mysqli_data_seek($students, 0);
                                while ($s = mysqli_fetch_assoc($students)): ?>
                                <option value="<?php echo $s['id']; ?>">
                                    <?php echo $s['student_no'] . ' — ' . $s['name']; ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div id="slot-info" class="alert alert-info py-2 small mb-3" style="display:none;"></div>

                        <div class="mb-3">
                             <label class="form-label">Number of Days to Borrow</label>
                            <select name="borrow_days" class="form-select" required>
                                <option value="1">1 Day</option>
                                <option value="2">2 Days</option>
                                <option value="3">3 Days</option>
                            </select>
                        </div>

                        <div id="book-rows">
                        </div>

                        <div class="mb-3" id="add-btn-container" style="display:none;">
                            <button type="button" class="btn btn-outline-tcm-gold btn-sm w-100" id="add-book-btn">
                                <i class="bi bi-plus-lg me-1"></i>Add Another Book
                            </button>
                        </div>

                        <button type="submit" name="borrow" class="btn btn-tcm-gold w-100" id="borrow-btn" style="display:none;">
                            Borrow Book
                        </button>

                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card card-tcm">
                <div class="card-header card-header-loans d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-check me-1"></i>Currently Borrowed Books</span>
                    <span id="loan-count" class="badge"><?php echo count($loans) . (count($loans) == 1 ? ' record' : ' records'); ?></span>
                </div>
                <div class="loan-filter-bar">
                    <span id="loan-filter-label">Showing all students</span>
                    <a href="#" id="loan-clear" style="display:none;">Show all students</a>
                </div>
                <div class="loans-scroll">
                    <?php render_loans_table($loans, 'No active borrowing records.'); ?>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    const availableBooks = <?php echo $books_json; ?>;
$(document).ready(function() {
    $('select[name="student_id"]').select2({
        theme: 'bootstrap-5',
        placeholder: '-- Search Student by name or ID --',
        allowClear: true
    });
    // Show only the selected student's records in the Currently Borrowed table
    function filterLoans(studentId) {
        var visible = 0;
        $('#loans-body tr.loan-row').each(function() {
            var show = !studentId || String($(this).data('student')) === String(studentId);
            $(this).toggle(show);
            if (show) visible++;
        });

        $('#loan-count').text(visible + (visible === 1 ? ' record' : ' records'));

        if (visible === 0) {
            $('#loans-empty td').text(studentId ? 'This student has no current borrowing records.' : 'No active borrowing records.');
            $('#loans-empty').show();
        } else {
            $('#loans-empty').hide();
        }

        if (studentId) {
            var name = $('#student-select option:selected').text().replace(/\s+/g, ' ').trim();
            $('#loan-filter-label').empty().append('Showing records of: ').append($('<strong></strong>').text(name));
            $('#loan-clear').show();
        } else {
            $('#loan-filter-label').text('Showing all students');
            $('#loan-clear').hide();
        }
    }

    $('#loan-clear').on('click', function(e) {
        e.preventDefault();
        $('#student-select').val(null).trigger('change');
    });

    $('#student-select').on('change', function() {
        var studentId = $(this).val();
        filterLoans(studentId);
        if (!studentId) {
            $('#book-rows').html('');
            $('#add-btn-container').hide();
            $('#borrow-btn').hide();
            $('#slot-info').hide();
            updateCounter(0);
            return;
        }

        $.ajax({
            url: 'get_student_slots.php',
            method: 'GET',
            data: { student_id: studentId },
            success: function(response) {
                var data = JSON.parse(response);
                var borrowed = data.borrowed;
                var remaining = 3 - borrowed;

                if (remaining <= 0) {
                    $('#book-rows').html('');
                    $('#add-btn-container').hide();
                    $('#borrow-btn').hide();
                    $('#slot-info')
                        .removeClass('alert-info alert-warning')
                        .addClass('alert-danger')
                        .html('⛔ This student has already reached the 3-book limit.')
                        .show();
                    updateCounter(0, 3 - borrowed);
                    return;
                }

                $('#slot-info')
                    .removeClass('alert-danger alert-warning')
                    .addClass('alert-info')
                    .html('This student currently has <strong>' + borrowed + '</strong> book(s) borrowed. They can borrow <strong>' + remaining + '</strong> more.')
                    .show();

                $('#book-rows').html('');
                updateCounter(0, remaining);
                addBookRow(remaining);
                $('#borrow-btn').show();
            }
        });
    });

    $('#add-book-btn').on('click', function() {
        var currentRows = $('#book-rows .book-row').length;
        var studentId = $('#student-select').val();

        $.ajax({
            url: 'get_student_slots.php',
            method: 'GET',
            data: { student_id: studentId },
            success: function(response) {
                var data = JSON.parse(response);
                var remaining = 3 - data.borrowed;
                var totalAllowed = remaining;
                if (currentRows < totalAllowed) {
                    addBookRow(remaining);
                    updateCounter(currentRows + 1, remaining);
                }
            }
        });
    });

    $(document).on('click', '.remove-book-btn', function() {
        $(this).closest('.book-row').remove();
        var currentRows = $('#book-rows .book-row').length;
        var studentId = $('#student-select').val();
        refreshDropdowns();
        $.ajax({
            url: 'get_student_slots.php',
            method: 'GET',
            data: { student_id: studentId },
            success: function(response) {
                var data = JSON.parse(response);
                var remaining = 3 - data.borrowed;
                updateCounter(currentRows, remaining);
            }
        });
    });

    function getSelectedBookIds() {
        var selected = [];
        $('#book-rows .book-select').each(function() {
            var val = $(this).val();
            if (val) selected.push(val);
        });
        return selected;
    }

    function refreshDropdowns() {
        var selected = getSelectedBookIds();
        $('#book-rows .book-select').each(function() {
            var thisVal = $(this).val();
            var select = $(this);
            var currentSelect2 = select.data('select2') !== undefined;

            var options = '<option value="">-- Choose Book --</option>';
            availableBooks.forEach(function(book) {
                var isSelectedElsewhere = selected.includes(String(book.id)) && String(book.id) !== thisVal;
                if (!isSelectedElsewhere) {
                    options += '<option value="' + book.id + '"' + (String(book.id) === thisVal ? ' selected' : '') + '>' + book.serial_no + ' — ' + book.title + ' (' + book.available_copies + ' left)</option>';
                }
            });

            var tempVal = thisVal;
            select.html(options).val(tempVal).trigger('change.select2');
        });
    }

    function addBookRow(remaining) {
        var currentRows = $('#book-rows .book-row').length;

        var options = '<option value="">-- Choose Book --</option>';
        availableBooks.forEach(function(book) {
            var selected = getSelectedBookIds();
            if (!selected.includes(String(book.id))) {
                options += '<option value="' + book.id + '">' + book.serial_no + ' — ' + book.title + ' (' + book.available_copies + ' left)</option>';
            }
        });

        var row = '<div class="book-row d-flex gap-2 mb-2 align-items-center">' +
            '<select name="book_ids[]" class="form-select book-select" required>' + options + '</select>' +
            '<button type="button" class="btn btn-outline-danger btn-sm remove-book-btn">✕</button>' +
            '</div>';

        $('#book-rows').append(row);

        var newSelect = $('#book-rows .book-row:last .book-select');
        newSelect.select2({
            theme: 'bootstrap-5',
            placeholder: '-- Search Book --',
            allowClear: true,
            width: '100%'
        });

        newSelect.on('change', function() {
            refreshDropdowns();
        });

        updateCounter($('#book-rows .book-row').length, remaining);
    }

    function updateCounter(current, remaining) {
        var max = typeof remaining !== 'undefined' ? (current + remaining) : 3;
        var total = Math.min(max, 3);
        $('#book-counter').text(current + ' / ' + total);

        if (current >= total) {
            $('#add-btn-container').hide();
        } else {
            $('#add-btn-container').show();
        }
    }

    <?php if ($selected_student): ?>
    $('#student-select').val('<?php echo $selected_student; ?>').trigger('change');
    <?php endif; ?>
});
</script></body>
</html>