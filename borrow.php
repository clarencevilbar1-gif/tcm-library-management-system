<?php
include('auth.php');
ini_set('display_errors', 1);
error_reporting(E_ALL);
include('db.php');

$message = '';

// Handle Borrow
if (isset($_POST['borrow'])) {
    $student_id = intval($_POST['student_id']);
    $book_ids = $_POST['book_ids'] ?? [];

    // Remove duplicates
    $book_ids = array_unique($book_ids);

    // Check current borrowed count
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
        $success_count = 0;
        $errors = [];

        foreach ($book_ids as $book_id) {
            $book_id = intval($book_id);
            $book_check = mysqli_query($conn, "SELECT * FROM books WHERE id = $book_id AND is_available = 1");
            if (mysqli_num_rows($book_check) == 0) {
                $errors[] = "Book ID $book_id is no longer available.";
            } else {
                mysqli_query($conn, "INSERT INTO borrowing (student_id, book_id, borrow_date) VALUES ($student_id, $book_id, '$date')");
                mysqli_query($conn, "UPDATE books SET is_available = 0 WHERE id = $book_id");
                $success_count++;
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

// Handle Return
if (isset($_POST['return'])) {
    $borrowing_id = $_POST['borrowing_id'];
    $book_id = $_POST['book_id'];

    $date = date('Y-m-d');
    mysqli_query($conn, "UPDATE borrowing SET return_date = '$date' WHERE id = $borrowing_id");
    mysqli_query($conn, "UPDATE books SET is_available = 1 WHERE id = $book_id");
    $message = '<div class="alert alert-success">Book returned successfully!</div>';
}

// Get all active students only — deactivated students shouldn't be borrowable
$students = mysqli_query($conn, "SELECT * FROM students WHERE is_active = 1");

// Get available books
$books = mysqli_query($conn, "SELECT * FROM books WHERE is_available = 1");
$books_array = [];
while ($b = mysqli_fetch_assoc($books)) {
    $books_array[] = $b;
}
$books_json = json_encode($books_array);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrow / Return</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <style>
        :root {
            --tcm-green: #1B5E45;
            --tcm-green-dark: #144534;
            --tcm-gold: #D4A72C;
            --tcm-gold-dark: #B88F22;
        }

        body {
            background: linear-gradient(180deg, #f4f7f6 0%, #e8f0ec 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .navbar-tcm {
            background-color: var(--tcm-green);
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
            color: var(--tcm-green-dark);
        }

        .section-heading {
            color: var(--tcm-green-dark);
            font-weight: 700;
        }

        .card-tcm {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 16px rgba(27, 94, 69, 0.08);
            overflow: hidden;
        }

        .card-header-borrow {
            background-color: var(--tcm-gold);
            color: var(--tcm-green-dark);
            font-weight: 700;
        }

        .card-header-return {
            background-color: var(--tcm-green);
            color: #fff;
            font-weight: 700;
        }

        #book-counter {
            background-color: var(--tcm-green-dark) !important;
        }

        .btn-tcm-gold {
            background-color: var(--tcm-gold);
            color: var(--tcm-green-dark);
            font-weight: 600;
            border: none;
        }

        .btn-tcm-gold:hover {
            background-color: var(--tcm-gold-dark);
            color: var(--tcm-green-dark);
        }

        .btn-outline-tcm-gold {
            border: 1px solid var(--tcm-gold-dark);
            color: var(--tcm-gold-dark);
            background: transparent;
        }

        .btn-outline-tcm-gold:hover {
            background-color: var(--tcm-gold);
            color: var(--tcm-green-dark);
        }

        .btn-tcm-green {
            background-color: var(--tcm-green);
            color: #fff;
            font-weight: 600;
            border: none;
        }

        .btn-tcm-green:hover {
            background-color: var(--tcm-green-dark);
            color: #fff;
        }

        .card-table {
            border: none;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(27, 94, 69, 0.08);
        }

        .table thead th {
            background-color: var(--tcm-green) !important;
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
            background-color: rgba(27, 94, 69, 0.05);
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

<div class="container mt-4">

    <?php echo $message; ?>

    <div class="row g-4">

        <!-- Borrow Form -->
        <div class="col-md-6">
            <div class="card card-tcm h-100">
                <div class="card-header card-header-borrow d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-journal-arrow-up me-1"></i>Borrow a Book</span>
                    <span id="book-counter" class="badge bg-dark">0 / 3</span>
                </div>
                <div class="card-body">
                    <form method="POST" id="borrow-form">

                        <!-- Student Selection -->
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

                        <!-- Remaining slots info -->
                        <div id="slot-info" class="alert alert-info py-2 small mb-3" style="display:none;"></div>

                        <!-- Book Rows Container -->
                        <div id="book-rows">
                            <!-- Book rows will appear here after student is selected -->
                        </div>

                        <!-- Add Another Book Button -->
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

        <!-- Return Form -->
        <div class="col-md-6">
            <div class="card card-tcm h-100">
                <div class="card-header card-header-return">
                    <i class="bi bi-journal-arrow-down me-1"></i>Return a Book
                </div>
                <div class="card-body">
                    <?php
                    $borrowed = mysqli_query($conn, "
                        SELECT borrowing.id, borrowing.book_id, borrowing.borrow_date,
                               students.name as student_name,
                               books.title as book_title
                        FROM borrowing
                        JOIN students ON borrowing.student_id = students.id
                        JOIN books ON borrowing.book_id = books.id
                        WHERE borrowing.return_date IS NULL
                        ORDER BY borrowing.borrow_date ASC
                    ");

                    if (mysqli_num_rows($borrowed) > 0): ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Select Borrowed Book to Return</label>
                            <select name="borrowing_id" class="form-select" required id="return-select">
                                <option value="">-- Choose Record --</option>
                                <?php while ($br = mysqli_fetch_assoc($borrowed)): ?>
                                <option value="<?php echo $br['id']; ?>" 
                                        data-bookid="<?php echo $br['book_id']; ?>">
                                    <?php echo $br['student_name'] . ' — ' . $br['book_title'] . ' (since ' . $br['borrow_date'] . ')'; ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <input type="hidden" name="book_id" value="">
                        <button type="submit" name="return" class="btn btn-tcm-green w-100">
                            Return Book
                        </button>
                    </form>
                    <?php else: ?>
                        <p class="text-muted text-center mt-4">No books are currently borrowed.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- Active Borrowing Records -->
    <h5 class="section-heading mt-5 mb-3">Currently Borrowed Books</h5>
    <div class="card card-table">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Book</th>
                        <th>Serial No.</th>
                        <th>Date Borrowed</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $active = mysqli_query($conn, "
                    SELECT students.name as student_name,
                           students.student_no,
                           books.title as book_title,
                           books.serial_no,
                           borrowing.borrow_date
                    FROM borrowing
                    JOIN students ON borrowing.student_id = students.id
                    JOIN books ON borrowing.book_id = books.id
                    WHERE borrowing.return_date IS NULL
                    ORDER BY borrowing.borrow_date ASC
                ");

                if (mysqli_num_rows($active) > 0) {
                    while ($row = mysqli_fetch_assoc($active)) {
                        echo "<tr>
                            <td>{$row['student_name']} ({$row['student_no']})</td>
                            <td>{$row['book_title']}</td>
                            <td>{$row['serial_no']}</td>
                            <td>{$row['borrow_date']}</td>
                        </tr>";
                    }
                } else {
                    echo "<tr><td colspan='4' class='text-center text-muted py-3'>No active borrowing records.</td></tr>";
                }
                ?>
                </tbody>
            </table>
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
    $('select[name="book_id"]').select2({
        theme: 'bootstrap-5',
        placeholder: '-- Search Book by title or serial no --',
        allowClear: true
    });
    $('select[name="borrowing_id"]').select2({
        theme: 'bootstrap-5',
        placeholder: '-- Search borrowed record --',
        allowClear: true
    });
    $('#return-select').on('change', function() {
        var selected = $(this).find(':selected');
        $('input[name="book_id"]').val(selected.data('bookid'));
    });
    // Student select change — check remaining slots
    $('#student-select').on('change', function() {
        var studentId = $(this).val();
        if (!studentId) {
            $('#book-rows').html('');
            $('#add-btn-container').hide();
            $('#borrow-btn').hide();
            $('#slot-info').hide();
            updateCounter(0);
            return;
        }

        // Ask server how many books this student already has
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

                // Clear existing rows and add first row
                $('#book-rows').html('');
                updateCounter(0, remaining);
                addBookRow(remaining);
                $('#borrow-btn').show();
            }
        });
    });

    // Add Another Book button
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

    // Remove book row
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

            // Rebuild options
            var options = '<option value="">-- Choose Book --</option>';
            availableBooks.forEach(function(book) {
                var isSelectedElsewhere = selected.includes(String(book.id)) && String(book.id) !== thisVal;
                if (!isSelectedElsewhere) {
                    options += '<option value="' + book.id + '"' + (String(book.id) === thisVal ? ' selected' : '') + '>' + book.serial_no + ' — ' + book.title + '</option>';
                }
            });

            // Update without destroying Select2
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
                options += '<option value="' + book.id + '">' + book.serial_no + ' — ' + book.title + '</option>';
            }
        });

        var row = '<div class="book-row d-flex gap-2 mb-2 align-items-center">' +
            '<select name="book_ids[]" class="form-select book-select" required>' + options + '</select>' +
            '<button type="button" class="btn btn-outline-danger btn-sm remove-book-btn">✕</button>' +
            '</div>';

        $('#book-rows').append(row);

        // Apply Select2 to the new dropdown
        var newSelect = $('#book-rows .book-row:last .book-select');
        newSelect.select2({
            theme: 'bootstrap-5',
            placeholder: '-- Search Book --',
            allowClear: true,
            width: '100%'
        });

        // When a book is selected, refresh all other dropdowns
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
});
</script>