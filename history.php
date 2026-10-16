<?php
include('auth.php');
ini_set('display_errors', 1);
error_reporting(E_ALL);
include('db.php');

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
                OR books.class_no LIKE '%$search_safe%')";
}

if ($filter == 'borrowed') {
    $where[] = "borrowing.return_date IS NULL";
} else if ($filter == 'returned') {
    $where[] = "borrowing.return_date IS NOT NULL";
}

$where_clause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$query = "
    SELECT 
        borrowing.id,
        borrowing.borrow_date,
        borrowing.due_date,
        borrowing.return_date,
        COALESCE(students.name, '(Deleted student)') as student_name,
        COALESCE(students.student_no, '—') as student_no,
        COALESCE(books.title, '(Deleted book)') as book_title,
        COALESCE(books.acc_nos, '—') as acc_nos
    FROM borrowing
    LEFT JOIN students ON borrowing.student_id = students.id
    LEFT JOIN books ON borrowing.book_id = books.id
    $where_clause
    ORDER BY borrowing.borrow_date DESC, borrowing.id DESC
";

$result = mysqli_query($conn, $query);

// Group transactions by borrow date
$grouped = [];
while ($row = mysqli_fetch_assoc($result)) {
    $date = $row['borrow_date'];
    $grouped[$date][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History</title>
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
                    <i class="bi bi-calendar3 me-1"></i><?php echo date('F d, Y', strtotime($date)); ?>
                </span>
                <span class="badge badge-count"><?php echo count($transactions); ?> transaction(s)</span>
            </div>

            <div class="card card-table mb-2">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Book</th>
                                <th>Acc. No(s).</th>
                                <th>Date Borrowed</th>
                                <th>Due Date</th>
                                <th>Date Returned</th>
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
                                <td><?php echo date('M d, Y', strtotime($t['borrow_date'])); ?></td>
                                <td>
                                    <?php echo $t['due_date'] 
                                        ? date('M d, Y', strtotime($t['due_date'])) 
                                        : '<span class="text-muted">—</span>'; ?>
                                </td>
                                <td>
                                    <?php echo $t['return_date'] 
                                        ? date('M d, Y', strtotime($t['return_date'])) 
                                        : '<span class="text-muted">—</span>'; ?>
                                </td>
                                <td>
                                    <?php 
                                    $today = date('Y-m-d');
                                    if ($t['return_date']): ?>
                                        <span class="badge badge-returned">Returned</span>
                                    <?php elseif ($t['due_date'] && $today > $t['due_date']): ?>
                                        <span class="badge badge-overdue">Overdue</span>
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
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<?php
$export_today = date('Y-m-d');
$export_status_default = in_array($filter, ['borrowed', 'returned'], true) ? $filter : 'all';
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
                            <option value="overdue">Overdue only</option>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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

</body>
</html>