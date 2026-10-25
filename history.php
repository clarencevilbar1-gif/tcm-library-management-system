<?php
include('auth.php');
ini_set('display_errors', 1);
error_reporting(E_ALL);
include('db.php');
include('student_functions.php');
include('reminder_functions.php');
$has_email = st_email_ready($conn);

// Get search and filter values
$export_msg = isset($_GET['export_msg']) ? $_GET['export_msg'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build query based on filter
$where = [];

if ($search) {
    $search_safe = mysqli_real_escape_string($conn, $search);
    $where[] = "(students.name LIKE '%$search_safe%' 
                OR students.student_no LIKE '%$search_safe%'
                OR books.title LIKE '%$search_safe%'
                OR books.acc_nos LIKE '%$search_safe%'
                OR books.class_no LIKE '%$search_safe%'
                OR students.contact_number LIKE '%$search_safe%'" . ($has_email ? "
                OR students.email LIKE '%$search_safe%'" : "") . ")";
}

if ($filter == 'borrowed') {
    $where[] = "borrowing.return_date IS NULL";
} else if ($filter == 'returned') {
    $where[] = "borrowing.return_date IS NOT NULL";
} else if ($filter == 'overdue') {
    // same rule as the Main Menu: not returned yet and past the due date
    $overdue_today = date('Y-m-d');
    $where[] = "borrowing.return_date IS NULL AND borrowing.due_date IS NOT NULL AND borrowing.due_date < '$overdue_today'";
} else {
    $filter = 'all';
}

$where_clause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$query = "
    SELECT 
        borrowing.id,
        borrowing.student_id,
        borrowing.borrow_date,
        borrowing.due_date,
        borrowing.return_date,
        COALESCE(students.name, '(Deleted student)') as student_name,
        COALESCE(students.student_no, '—') as student_no,
        students.contact_number as contact_number,
        " . ($has_email ? "students.email" : "NULL") . " as email,
        COALESCE(books.title, '(Deleted book)') as book_title,
        COALESCE(books.acc_nos, '—') as acc_nos
    FROM borrowing
    LEFT JOIN students ON borrowing.student_id = students.id
    LEFT JOIN books ON borrowing.book_id = books.id
    $where_clause
    ORDER BY " . ($filter == 'overdue' ? "borrowing.due_date ASC, borrowing.id ASC" : "borrowing.borrow_date DESC, borrowing.id DESC") . "
";

$result = mysqli_query($conn, $query);

// who was already reminded by e-mail (overdue view only)
$rm_last = ($filter == 'overdue') ? rm_last_sent($conn) : [];

// Group transactions by borrow date
$grouped = [];
while ($row = mysqli_fetch_assoc($result)) {
    // the overdue list is one list, the longest overdue first; every other view is grouped by borrow date
    $date = ($filter == 'overdue') ? 'overdue' : $row['borrow_date'];
    $grouped[$date][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History</title>
    <link rel="icon" type="image/png" href="assets/images/col_logo.png">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
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

        .btn-tcm-search {
            background-color: var(--tcm-purple);
            color: #fff;
            font-weight: 600;
            border: none;
        }

        .btn-tcm-search:hover {
            background-color: var(--tcm-purple-dark);
            color: #fff;
        }

        .stat-card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 16px rgba(75, 46, 131, 0.08);
        }

        .stat-total .fs-2 { color: var(--tcm-purple-dark); }
        .stat-borrowed .fs-2 { color: #b02a37; }
        .stat-returned .fs-2 { color: var(--tcm-purple); }

        .date-group-label {
            color: var(--tcm-purple-dark);
            font-weight: 700;
        }

        .badge-count {
            background-color: rgba(75, 46, 131, 0.12);
            color: var(--tcm-purple-dark);
            font-weight: 600;
        }

        .card-table {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(75, 46, 131, 0.06);
        }

        .table thead th {
            background-color: #e2d9ee !important;
            color: var(--tcm-purple-dark);
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border: none;
            padding: 0.75rem 1rem;
        }

        .table tbody td {
            vertical-align: middle;
            padding: 0.75rem 1rem;
        }

        .table tbody tr:hover {
            background-color: rgba(75, 46, 131, 0.05);
        }

        .badge-returned {
            background-color: rgba(46, 204, 113, 0.15);
            color: #1e8449;
            font-weight: 600;
            padding: 0.4em 0.7em;
        }

        .badge-still-borrowed {
            background-color: rgba(220, 53, 69, 0.12);
            color: #b02a37;
            font-weight: 600;
            padding: 0.4em 0.7em;
        }
        .table-history {
            table-layout: fixed;
            width: 100%;
            min-width: 860px;
        }

        .table-history th,
        .table-history td {
            overflow-wrap: anywhere;
        }

        /* the date columns never break a date over two lines */
        .table-history td.nw {
            white-space: nowrap;
        }

        .contact-line {
            font-size: 0.88rem;
            line-height: 1.35;
        }

        .contact-line a {
            color: var(--tcm-purple);
        }

        .btn-remind {
            border: 1px solid var(--tcm-purple);
            color: var(--tcm-purple);
            background: #fff;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.15rem 0.55rem;
            margin-top: 0.3rem;
        }

        .btn-remind:hover {
            background: var(--tcm-purple);
            color: #fff;
        }

        .rm-status {
            font-size: 0.75rem;
            color: #1e8449;
            margin-top: 0.2rem;
        }

        .rm-preview {
            background: #faf8fd;
            border: 1px solid #e2d9ee;
            border-radius: 10px;
            padding: 0.8rem 1rem;
            white-space: pre-wrap;
            font-size: 0.88rem;
            max-height: 260px;
            overflow: auto;
        }

        .no-contact {
            display: inline-block;
            background-color: #fff3d1;
            color: #8a6100;
            font-weight: 600;
            font-size: 0.78rem;
            border-radius: 6px;
            padding: 0.3em 0.55em;
        }

        .badge-overdue {
            background-color: rgba(184, 28, 28, 0.9);
            color: #fff;
            font-weight: 600;
            padding: 0.4em 0.7em;
        }

        /* ---- Export ---- */
        .btn-export {
            background-color: var(--tcm-gold, #D4A72C);
            color: var(--tcm-purple-dark, #35205E);
            font-weight: 700;
            border: none;
            padding: 0.45rem 1.1rem;
        }

        .btn-export:hover,
        .btn-export:focus {
            background-color: #c19620;
            color: var(--tcm-purple-dark, #35205E);
        }

        .export-header {
            background-color: var(--tcm-purple, #4B2E83);
            color: #fff;
        }

        .export-option {
            display: flex;
            align-items: flex-start;
            gap: 0.7rem;
            padding: 0.7rem 0.9rem;
            border: 1px solid #ddd5ea;
            border-radius: 10px;
            cursor: pointer;
            margin-bottom: 0.5rem;
            transition: border-color 0.15s, background-color 0.15s;
        }

        .export-option:hover {
            border-color: var(--tcm-purple, #4B2E83);
        }

        .export-option:has(input:checked) {
            border-color: var(--tcm-purple, #4B2E83);
            background-color: rgba(75, 46, 131, 0.06);
        }

        .export-option input {
            margin-top: 0.25rem;
            flex-shrink: 0;
        }

        .export-option small {
            display: block;
            color: #6c757d;
        }

        .range-fields {
            background: #faf8fd;
            border: 1px solid #eee7f5;
            border-radius: 10px;
            padding: 0.8rem 0.9rem;
            margin-bottom: 0.9rem;
        }

        .preset-chip {
            border: 1px solid #cfc4e3;
            background: #fff;
            color: var(--tcm-purple, #4B2E83);
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            padding: 0.2rem 0.7rem;
        }

        .preset-chip:hover,
        .preset-chip.active {
            background-color: var(--tcm-purple, #4B2E83);
            border-color: var(--tcm-purple, #4B2E83);
            color: #fff;
        }

        .export-count {
            border-radius: 10px;
            padding: 0.65rem 0.9rem;
            font-size: 0.9rem;
            background: #f2f0f6;
            color: #555;
        }

        .export-count.ok {
            background: #e3f4e8;
            color: #1e6b34;
        }

        .export-count.warn {
            background: #fff3d1;
            color: #8a6100;
        }

        .export-count.bad {
            background: #fbe4e4;
            color: #b3261e;
        }

        .btn-export-go {
            background-color: var(--tcm-purple, #4B2E83);
            color: #fff;
            font-weight: 600;
            border: none;
        }

        .btn-export-go:hover {
            background-color: var(--tcm-purple-dark, #35205E);
            color: #fff;
        }

        .btn-export-go:disabled {
            background-color: var(--tcm-purple, #4B2E83);
            color: #fff;
            opacity: 0.4;
        }
    </style>
</head>
<body>

<?php $nav_mode = 'page'; include('navbar.php'); ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h4 class="page-heading mb-0">Transaction History</h4>
        <button type="button" class="btn btn-export" data-bs-toggle="modal" data-bs-target="#exportModal">
            <i class="bi bi-download me-1"></i>Export
        </button>
    </div>

    <?php if ($export_msg == 'empty'): ?>
        <div class="alert alert-warning">There were no transactions to export for that selection.</div>
    <?php elseif ($export_msg == 'invalid'): ?>
        <div class="alert alert-danger">The export could not be created because the dates were not valid. Please try again.</div>
    <?php endif; ?>

    <!-- Search and Filter -->
    <form method="GET" class="row g-2 mb-4">
        <div class="col-md-6">
            <input 
                type="text" 
                name="search" 
                class="form-control" 
                placeholder="Search by student name, ID, book title, Acc. No., or class..."
                value="<?php echo htmlspecialchars($search); ?>"
            >
        </div>
        <div class="col-md-3">
            <select name="filter" class="form-select">
                <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>All Transactions</option>
                <option value="borrowed" <?php echo $filter == 'borrowed' ? 'selected' : ''; ?>>Still Borrowed</option>
                <option value="overdue" <?php echo $filter == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                <option value="returned" <?php echo $filter == 'returned' ? 'selected' : ''; ?>>Returned</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-tcm-search w-100">Search</button>
        </div>
        <div class="col-md-1">
            <a href="history.php" class="btn btn-outline-secondary w-100">Clear</a>
        </div>
    </form>

    <!-- Transaction Summary -->
    <?php
    $total_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM borrowing");
    $total_row = mysqli_fetch_assoc($total_query);

    $borrowed_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM borrowing WHERE return_date IS NULL");
    $borrowed_row = mysqli_fetch_assoc($borrowed_query);

    $returned_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM borrowing WHERE return_date IS NOT NULL");
    $returned_row = mysqli_fetch_assoc($returned_query);
    ?>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card stat-card stat-total text-center p-3">
                <div class="fs-2 fw-bold"><?php echo $total_row['total']; ?></div>
                <div class="text-muted small">Total Transactions</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card stat-borrowed text-center p-3">
                <div class="fs-2 fw-bold"><?php echo $borrowed_row['total']; ?></div>
                <div class="text-muted small">Still Borrowed</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card stat-returned text-center p-3">
                <div class="fs-2 fw-bold"><?php echo $returned_row['total']; ?></div>
                <div class="text-muted small">Returned</div>
            </div>
        </div>
    </div>

    <!-- Grouped Transactions -->
    <?php if (empty($grouped)): ?>
        <div class="alert alert-secondary text-center">No transactions found.</div>
    <?php else: ?>
        <?php foreach ($grouped as $date => $transactions): ?>
            <!-- Date Group Header -->
            <div class="d-flex align-items-center gap-3 mb-2 mt-4">
                <span class="date-group-label fs-6">
                    <?php if ($date === 'overdue'): ?>
                        <i class="bi bi-exclamation-triangle-fill me-1 text-danger"></i>Overdue books &mdash; the longest overdue first
                    <?php else: ?>
                        <i class="bi bi-calendar3 me-1"></i><?php echo date('F d, Y', strtotime($date)); ?>
                    <?php endif; ?>
                </span>
                <span class="badge badge-count"><?php echo count($transactions); ?> transaction(s)</span>
            </div>
            <?php if ($date === 'overdue'): ?>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <span class="small text-muted">Students with an e-mail can be reminded by e-mail. Use <strong>Send reminder</strong> in the Contact column, or remind everyone at once.</span>
                    <button type="button" class="btn btn-remind" id="remind-all-btn" style="margin-top:0;"><i class="bi bi-envelope-paper me-1"></i>Remind all</button>
                </div>
            <?php
                $no_contact_students = [];
                foreach ($transactions as $t) {
                    if (trim((string)$t['contact_number']) === '' && trim((string)$t['email']) === '') $no_contact_students[$t['student_no'] . '|' . $t['student_name']] = true;
                }
                if ($no_contact_students): ?>
                <div class="alert alert-warning py-2 small mb-2">
                    <i class="bi bi-info-circle me-1"></i><strong><?php echo count($no_contact_students); ?> student<?php echo count($no_contact_students) == 1 ? ' has' : 's have'; ?> no contact on file.</strong>
                    Follow up in person or through their class, and ask for a number or e-mail when they come in.
                </div>
            <?php endif; endif; ?>

            <div class="card card-table mb-2">
                <div class="card-body p-0">
                    <div class="table-responsive">
                    <table class="table table-hover mb-0 table-history">
                        <colgroup>
                            <?php if ($filter == 'overdue'): ?>
                            <col style="width: 17%">
                            <col style="width: 19%">
                            <col style="width: 10%">
                            <col style="width: 11%">
                            <col style="width: 11%">
                            <col style="width: 20%">
                            <col style="width: 12%">
                            <?php else: ?>
                            <col style="width: 20%">
                            <col style="width: 22%">
                            <col style="width: 11%">
                            <col style="width: 12%">
                            <col style="width: 12%">
                            <col style="width: 12%">
                            <col style="width: 11%">
                            <?php endif; ?>
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Book</th>
                                <th>Acc. No(s).</th>
                                <th>Date Borrowed</th>
                                <th>Due Date</th>
                                <?php if ($filter == 'overdue'): ?>
                                <th>Contact</th>
                                <?php else: ?>
                                <th>Date Returned</th>
                                <?php endif; ?>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($t['student_name']); ?>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($t['student_no']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($t['book_title']); ?></td>
                                <td><?php echo htmlspecialchars($t['acc_nos']); ?></td>
                                <td class="nw"><?php echo date('M d, Y', strtotime($t['borrow_date'])); ?></td>
                                <td class="nw">
                                    <?php echo $t['due_date'] 
                                        ? date('M d, Y', strtotime($t['due_date'])) 
                                        : '<span class="text-muted">—</span>'; ?>
                                </td>
                                <?php if ($filter == 'overdue'): ?>
                                <td>
                                    <?php echo st_contact_html($t['contact_number'], $t['email'], '<span class="no-contact">No contact on file</span>'); ?>
                                    <?php
                                    list($rm_em, $rm_st) = st_email($t['email']);
                                    if ($rm_st === 'ok' && !empty($t['student_id'])):
                                        $rm_sid = (int)$t['student_id'];
                                        $rm_when_txt = isset($rm_last[$rm_sid]) ? rm_when($rm_last[$rm_sid]) : '';
                                    ?>
                                    <div class="rm-box" data-student="<?php echo $rm_sid; ?>">
                                        <button type="button" class="btn btn-remind rm-open" data-student="<?php echo $rm_sid; ?>"><i class="bi bi-envelope-paper me-1"></i><span class="rm-label"><?php echo $rm_when_txt ? 'Send again' : 'Send reminder'; ?></span></button>
                                        <div class="rm-status"<?php echo $rm_when_txt ? '' : ' hidden'; ?>><i class="bi bi-check2-circle me-1"></i><span class="rm-when"><?php echo $rm_when_txt ? 'Reminded ' . st_h($rm_when_txt) : ''; ?></span></div>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <?php else: ?>
                                <td class="nw">
                                    <?php echo $t['return_date'] 
                                        ? date('M d, Y', strtotime($t['return_date'])) 
                                        : '<span class="text-muted">—</span>'; ?>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <?php 
                                    $today = date('Y-m-d');
                                    if ($t['return_date']): ?>
                                        <span class="badge badge-returned">Returned</span>
                                    <?php elseif ($t['due_date'] && $today > $t['due_date']): ?>
                                        <span class="badge badge-overdue">Overdue</span>
                                        <?php $days_late = (int)(new DateTime($t['due_date']))->diff(new DateTime($today))->days; ?>
                                        <div class="small text-danger mt-1"><?php echo $days_late . ' day' . ($days_late == 1 ? '' : 's') . ' late'; ?></div>
                                    <?php else: ?>
                                        <span class="badge badge-still-borrowed">Still Borrowed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<?php
$export_today = date('Y-m-d');
$export_status_default = in_array($filter, ['borrowed', 'returned', 'overdue'], true) ? $filter : 'all';
?>
<!-- Export dialog -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="export-form" method="GET" action="export_history.php">
                <input type="hidden" name="action" value="download">

                <div class="modal-header export-header">
                    <h5 class="modal-title" id="exportModalLabel"><i class="bi bi-download me-2"></i>Export Transactions</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <label class="form-label fw-semibold">What do you want to export?</label>

                    <label class="export-option">
                        <input class="form-check-input" type="radio" name="range" value="all" checked>
                        <span>
                            <strong>All transactions</strong>
                            <small>Every recorded transaction (<?php echo (int)$total_row['total']; ?>)</small>
                        </span>
                    </label>
                    <label class="export-option">
                        <input class="form-check-input" type="radio" name="range" value="custom">
                        <span>
                            <strong>Select a date range</strong>
                            <small>Only transactions within the dates you choose</small>
                        </span>
                    </label>

                    <div id="range-fields" class="range-fields" hidden>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <button type="button" class="preset-chip" data-preset="today">Today</button>
                            <button type="button" class="preset-chip" data-preset="week">Last 7 days</button>
                            <button type="button" class="preset-chip" data-preset="month">This month</button>
                            <button type="button" class="preset-chip" data-preset="lastmonth">Last month</button>
                            <button type="button" class="preset-chip" data-preset="year">This year</button>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small mb-1" for="export-from">From</label>
                                <input type="date" id="export-from" name="from" class="form-control" max="<?php echo $export_today; ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-1" for="export-to">To</label>
                                <input type="date" id="export-to" name="to" class="form-control" max="<?php echo $export_today; ?>">
                            </div>
                        </div>
                        <div class="mt-2">
                            <label class="form-label small mb-1" for="export-basis">Match the dates against</label>
                            <select id="export-basis" name="basis" class="form-select form-select-sm">
                                <option value="borrowed">Date borrowed</option>
                                <option value="returned">Date returned</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label class="form-label fw-semibold" for="export-status">Include</label>
                        <select id="export-status" name="status" class="form-select">
                            <option value="all" <?php echo $export_status_default == 'all' ? 'selected' : ''; ?>>All statuses</option>
                            <option value="borrowed" <?php echo $export_status_default == 'borrowed' ? 'selected' : ''; ?>>Still borrowed (includes overdue)</option>
                            <option value="overdue" <?php echo $export_status_default == 'overdue' ? 'selected' : ''; ?>>Overdue only</option>
                            <option value="returned" <?php echo $export_status_default == 'returned' ? 'selected' : ''; ?>>Returned only</option>
                        </select>
                    </div>

                    <label class="form-label fw-semibold">File format</label>
                    <div class="d-flex gap-2 mb-1">
                        <label class="export-option flex-fill mb-0">
                            <input class="form-check-input" type="radio" name="format" value="xlsx" checked>
                            <span><strong>Excel (.xlsx)</strong><small>Formatted report</small></span>
                        </label>
                        <label class="export-option flex-fill mb-0">
                            <input class="form-check-input" type="radio" name="format" value="csv">
                            <span><strong>CSV (.csv)</strong><small>Plain data</small></span>
                        </label>
                    </div>
                    <div class="form-text mb-3">Use Excel for reports. A CSV opened in Excel may drop the leading zeros of student numbers.</div>

                    <div id="export-count" class="export-count" role="status" aria-live="polite">Checking…</div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="export-go" class="btn btn-export-go" disabled>
                        <i class="bi bi-download me-1"></i>Export
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var TODAY = '<?php echo $export_today; ?>';

    var modalEl = document.getElementById('exportModal');
    var form = document.getElementById('export-form');
    var fromEl = document.getElementById('export-from');
    var toEl = document.getElementById('export-to');
    var rangeFields = document.getElementById('range-fields');
    var box = document.getElementById('export-count');
    var goBtn = document.getElementById('export-go');
    var goHtml = goBtn.innerHTML;
    var timer = null;
    var seq = 0;

    function parseDate(s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
    function fmt(d) {
        var m = d.getMonth() + 1, day = d.getDate();
        return d.getFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (day < 10 ? '0' : '') + day;
    }

    var presets = {
        today:     function (t) { return [t, t]; },
        week:      function (t) { var f = new Date(t); f.setDate(f.getDate() - 6); return [f, t]; },
        month:     function (t) { return [new Date(t.getFullYear(), t.getMonth(), 1), t]; },
        lastmonth: function (t) { return [new Date(t.getFullYear(), t.getMonth() - 1, 1), new Date(t.getFullYear(), t.getMonth(), 0)]; },
        year:      function (t) { return [new Date(t.getFullYear(), 0, 1), t]; }
    };

    function selectedRange() { return form.querySelector('input[name="range"]:checked').value; }

    function setState(kind, html) {
        box.className = 'export-count ' + kind;
        box.innerHTML = html;
        goBtn.disabled = (kind !== 'ok');
    }

    function refresh() {
        var custom = selectedRange() === 'custom';
        rangeFields.hidden = !custom;

        if (custom) {
            var f = fromEl.value, t = toEl.value;
            if (!f || !t) { setState('warn', 'Choose both a start date and an end date.'); return; }
            if (f > t) { setState('bad', 'The start date must be on or before the end date.'); return; }
            if (f > TODAY || t > TODAY) { setState('bad', "Dates can't be in the future."); return; }
        }

        setState('', 'Checking…');
        var id = ++seq;
        var p = new URLSearchParams(new FormData(form));
        p.set('action', 'count');

        fetch('export_history.php?' + p.toString(), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (id !== seq) return;               // a newer check is already running
                if (!d.ok) { setState('bad', d.error || 'Could not check the records.'); return; }
                if (d.count === 0) { setState('warn', 'No transactions match this selection.'); return; }
                setState('ok', '<strong>' + d.count + '</strong> transaction' + (d.count === 1 ? '' : 's') + ' will be exported.');
            })
            .catch(function () {
                if (id !== seq) return;
                setState('bad', 'Could not check the records. Please log in again and retry.');
            });
    }

    function schedule() { clearTimeout(timer); timer = setTimeout(refresh, 200); }

    // quick date buttons
    document.querySelectorAll('.preset-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            var r = presets[chip.getAttribute('data-preset')](parseDate(TODAY));
            fromEl.value = fmt(r[0]);
            toEl.value = fmt(r[1]);
            document.querySelectorAll('.preset-chip').forEach(function (c) { c.classList.toggle('active', c === chip); });
            schedule();
        });
    });

    // picking a date by hand clears the highlighted quick button
    [fromEl, toEl].forEach(function (el) {
        el.addEventListener('input', function () {
            document.querySelectorAll('.preset-chip').forEach(function (c) { c.classList.remove('active'); });
        });
    });

    // switching to "date range" pre-fills this month so the fields are never empty
    form.querySelectorAll('input[name="range"]').forEach(function (r) {
        r.addEventListener('change', function () {
            if (selectedRange() === 'custom' && !fromEl.value && !toEl.value) {
                document.querySelector('.preset-chip[data-preset="month"]').click();
            }
            schedule();
        });
    });

    form.addEventListener('change', schedule);
    fromEl.addEventListener('input', schedule);
    toEl.addEventListener('input', schedule);
    modalEl.addEventListener('shown.bs.modal', refresh);

    form.addEventListener('submit', function (e) {
        if (goBtn.disabled) { e.preventDefault(); return; }
        goBtn.disabled = true;
        goBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Preparing…';
        setTimeout(function () {                      // the file downloads in the background
            var m = bootstrap.Modal.getInstance(modalEl);
            if (m) m.hide();
            goBtn.innerHTML = goHtml;
            refresh();
        }, 1500);
    });
})();
</script>

<?php if ($filter == 'overdue'): ?>
<!-- Send one reminder -->
<div class="modal fade" id="reminderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header export-header">
                <h5 class="modal-title"><i class="bi bi-envelope-paper me-2"></i>Send overdue reminder</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="rm-problem" class="alert alert-warning py-2 small" hidden></div>
                <div class="small mb-1"><strong>To:</strong> <span id="rm-to">…</span></div>
                <div class="small mb-1"><strong>From:</strong> <span id="rm-from">…</span></div>
                <div class="small mb-2"><strong>Subject:</strong> <span id="rm-subject">…</span></div>
                <div id="rm-body" class="rm-preview mb-3">Loading…</div>
                <label class="form-label small mb-1" for="rm-note">Add a short note <span class="text-muted">(optional)</span></label>
                <textarea id="rm-note" class="form-control form-control-sm" rows="2" maxlength="300" placeholder="e.g. Please return the book before Friday."></textarea>
                <div id="rm-result" class="mt-3" hidden></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="rm-close">Cancel</button>
                <button type="button" class="btn btn-export-go" id="rm-send" disabled><i class="bi bi-send me-1"></i>Send e-mail</button>
            </div>
        </div>
    </div>
</div>

<!-- Remind everybody -->
<div class="modal fade" id="remindAllModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header export-header">
                <h5 class="modal-title"><i class="bi bi-envelope-paper me-2"></i>Remind all overdue students</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="ra-info" class="export-count">Checking…</div>
                <div id="ra-result" class="mt-3" hidden></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="ra-close">Cancel</button>
                <button type="button" class="btn btn-export-go" id="ra-send" disabled><i class="bi bi-send me-1"></i>Send</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    function post(data) {
        return fetch('send_reminder.php', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
            body: new URLSearchParams(data).toString()
        }).then(function (r) { return r.json(); });
    }
    function esc(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
    function show(el, kind, html) { el.hidden = false; el.className = 'alert alert-' + kind + ' py-2 small mt-3'; el.innerHTML = html; }

    // ---------- one student ----------
    var modalEl = document.getElementById('reminderModal');
    var modal = new bootstrap.Modal(modalEl);
    var sendBtn = document.getElementById('rm-send');
    var noteEl = document.getElementById('rm-note');
    var result = document.getElementById('rm-result');
    var problem = document.getElementById('rm-problem');
    var current = 0, timer = null, canSend = false;

    function loadPreview() {
        post({ action: 'preview', student_id: current, note: noteEl.value }).then(function (d) {
            if (!d.ok) { document.getElementById('rm-body').textContent = d.error || 'Could not prepare the e-mail.'; sendBtn.disabled = true; return; }
            document.getElementById('rm-to').textContent = d.name + ' <' + d.to + '>';
            document.getElementById('rm-from').textContent = d.from ? 'College of Law Library <' + d.from + '>' : '(not set up)';
            document.getElementById('rm-subject').textContent = d.subject;
            document.getElementById('rm-body').textContent = d.body;
            problem.hidden = !d.problem;
            problem.innerHTML = d.problem ? esc(d.problem) + ' <a href="backup.php">Open the Backup page</a>' : '';
            canSend = !d.problem;
            sendBtn.disabled = !canSend;
        }).catch(function () { document.getElementById('rm-body').textContent = 'Could not reach the system. Please log in again.'; });
    }

    document.addEventListener('click', function (e) {
        var b = e.target.closest('.rm-open');
        if (!b) return;
        current = parseInt(b.getAttribute('data-student'), 10);
        noteEl.value = ''; result.hidden = true; sendBtn.disabled = true; sendBtn.hidden = false;
        document.getElementById('rm-close').textContent = 'Cancel';
        ['rm-to', 'rm-from', 'rm-subject'].forEach(function (i) { document.getElementById(i).textContent = '…'; });
        document.getElementById('rm-body').textContent = 'Loading…';
        problem.hidden = true;
        modal.show();
        loadPreview();
    });

    noteEl.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(loadPreview, 350); });

    sendBtn.addEventListener('click', function () {
        if (!canSend) return;
        sendBtn.disabled = true;
        var old = sendBtn.innerHTML;
        sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending…';
        post({ action: 'send', student_id: current, note: noteEl.value }).then(function (d) {
            sendBtn.innerHTML = old;
            if (d.ok) {
                show(result, 'success', '<i class="bi bi-check-circle me-1"></i>' + esc(d.message) + '. It can take a minute to arrive. The student should also check the Spam folder.');
                document.querySelectorAll('.rm-box[data-student="' + current + '"]').forEach(function (box) {
                    box.querySelector('.rm-label').textContent = 'Send again';
                    box.querySelector('.rm-status').hidden = false;
                    box.querySelector('.rm-when').textContent = 'Reminded ' + d.when;
                });
                sendBtn.hidden = true;
                document.getElementById('rm-close').textContent = 'Close';
            } else {
                show(result, 'danger', esc(d.error || 'The e-mail could not be sent.'));
                sendBtn.disabled = false;
            }
        }).catch(function () { sendBtn.innerHTML = old; sendBtn.disabled = false; show(result, 'danger', 'Could not reach the system. Please log in again.'); });
    });

    // ---------- everybody ----------
    var allEl = document.getElementById('remindAllModal');
    var allModal = new bootstrap.Modal(allEl);
    var raInfo = document.getElementById('ra-info'), raSend = document.getElementById('ra-send'), raRes = document.getElementById('ra-result');
    var raBtn = document.getElementById('remind-all-btn');

    if (raBtn) raBtn.addEventListener('click', function () {
        raRes.hidden = true; raSend.hidden = false; raSend.disabled = true;
        document.getElementById('ra-close').textContent = 'Cancel';
        raInfo.className = 'export-count'; raInfo.textContent = 'Checking…';
        allModal.show();
        post({ action: 'count' }).then(function (d) {
            if (!d.ok) { raInfo.className = 'export-count bad'; raInfo.textContent = d.error || 'Could not check.'; return; }
            var extra = '';
            if (d.recent) extra += '<br>' + d.recent + ' student' + (d.recent === 1 ? ' was' : 's were') + ' reminded in the last 24 hours and will be skipped.';
            if (d.no_email) extra += '<br>' + d.no_email + ' student' + (d.no_email === 1 ? ' has' : 's have') + ' no e-mail: follow up with ' + (d.no_email === 1 ? 'that student' : 'them') + ' in person.';
            if (d.problem) { raInfo.className = 'export-count bad'; raInfo.innerHTML = esc(d.problem) + ' <a href="backup.php">Open the Backup page</a>'; return; }
            if (d.to_send === 0) { raInfo.className = 'export-count warn'; raInfo.innerHTML = 'There is nobody to remind right now.' + extra; return; }
            raInfo.className = 'export-count ok';
            raInfo.innerHTML = '<strong>' + d.to_send + '</strong> e-mail' + (d.to_send === 1 ? '' : 's') + ' will be sent, one to each overdue student that has an e-mail.' + extra;
            raSend.disabled = false;
        }).catch(function () { raInfo.className = 'export-count bad'; raInfo.textContent = 'Could not reach the system. Please log in again.'; });
    });

    raSend.addEventListener('click', function () {
        raSend.disabled = true;
        var old = raSend.innerHTML;
        raSend.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending…';
        post({ action: 'remind_all' }).then(function (d) {
            raSend.innerHTML = old; raSend.hidden = true;
            document.getElementById('ra-close').textContent = 'Close';
            var msg = '<strong>' + (d.sent || 0) + '</strong> reminder' + (d.sent === 1 ? '' : 's') + ' sent.';
            if (d.failed) msg += ' <strong>' + d.failed + '</strong> could not be sent: ' + esc(d.error || '');
            show(raRes, d.failed || !d.ok && d.error ? 'warning' : 'success', msg + ' <a href="" onclick="location.reload();return false;">Reload the list</a>');
        }).catch(function () { raSend.innerHTML = old; raSend.disabled = false; show(raRes, 'danger', 'Could not reach the system. Please log in again.'); });
    });
})();
</script>
<?php endif; ?>
</body>
</html>