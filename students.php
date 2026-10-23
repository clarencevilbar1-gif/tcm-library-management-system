<?php 
include('auth.php');
include('db.php'); 
include('student_functions.php');
$cols_ready = st_columns_ready($conn);

$search = isset($_GET['search']) ? $_GET['search'] : '';
$view = isset($_GET['view']) ? $_GET['view'] : 'active';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Records</title>
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

        .search-bar .form-control {
            border-radius: 8px 0 0 8px;
        }

        .btn-tcm-search {
            background-color: var(--tcm-purple);
            color: #fff;
            border-radius: 0 8px 8px 0;
        }

        .btn-tcm-search:hover {
            background-color: var(--tcm-purple-dark);
            color: #fff;
        }

        .btn-tcm-add {
            background-color: var(--tcm-gold);
            color: var(--tcm-purple-dark);
            font-weight: 600;
            border: none;
        }

        .btn-tcm-add:hover {
            background-color: var(--tcm-gold-dark);
            color: var(--tcm-purple-dark);
        }

        .card-table {
            border: none;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(75, 46, 131, 0.08);
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

        .table th, .table td {
            text-align: center;
        }

        .table tbody td {
            vertical-align: middle;
            padding: 0.8rem 1rem;
        }

        .table tbody tr:hover {
            background-color: rgba(75, 46, 131, 0.05);
        }

        .status-dot-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }

        .status-dot.dot-active {
            background-color: #2ecc71;
            box-shadow: 0 0 0 0 rgba(46, 204, 113, 0.7);
            animation: pulse-green 1.6s infinite;
        }

        .status-dot.dot-inactive {
            background-color: #e74c3c;
            box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.7);
            animation: pulse-red 1.6s infinite;
        }

        @keyframes pulse-green {
            0% { box-shadow: 0 0 0 0 rgba(46, 204, 113, 0.6); }
            70% { box-shadow: 0 0 0 7px rgba(46, 204, 113, 0); }
            100% { box-shadow: 0 0 0 0 rgba(46, 204, 113, 0); }
        }

        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.6); }
            70% { box-shadow: 0 0 0 7px rgba(231, 76, 60, 0); }
            100% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0); }
        }

        .badge-male   { background-color: rgba(13, 110, 253, 0.12); color: #0a58ca; font-weight: 600; padding: 0.4em 0.7em; }
        .badge-female { background-color: rgba(214, 51, 132, 0.12); color: #ab296a; font-weight: 600; padding: 0.4em 0.7em; }
        .students-wrap { max-width: 1320px; }
        .students-wrap .table tbody td, .students-wrap .table thead th { padding-left: 0.6rem; padding-right: 0.6rem; }
        .students-wrap .table td.name-cell { text-align: left; }
        .students-wrap .table th.name-head { text-align: left; }

        .badge-can-borrow {
            background-color: rgba(75, 46, 131, 0.12);
            color: var(--tcm-purple-dark);
            font-weight: 600;
            padding: 0.4em 0.7em;
        }

        .badge-limit-reached {
            background-color: rgba(220, 53, 69, 0.12);
            color: #b02a37;
            font-weight: 600;
            padding: 0.4em 0.7em;
        }

        .badge-active {
            background-color: rgba(75, 46, 131, 0.12);
            color: var(--tcm-purple-dark);
            font-weight: 600;
            padding: 0.4em 0.7em;
        }

        .badge-inactive {
            background-color: rgba(108, 117, 125, 0.15);
            color: #6c757d;
            font-weight: 600;
            padding: 0.4em 0.7em;
        }

        .filter-tabs .btn {
            border: 1px solid #dcd3e6;
            color: #495057;
            background-color: #fff;
        }

        .filter-tabs .btn.active-tab {
            background-color: var(--tcm-purple);
            border-color: var(--tcm-purple);
            color: #fff;
        }

        .btn-deactivate {
            background-color: transparent;
            border: 1px solid #6c757d;
            color: #6c757d;
        }

        .btn-deactivate:hover {
            background-color: #6c757d;
            color: #fff;
        }

        .btn-reactivate {
            background-color: transparent;
            border: 1px solid var(--tcm-purple);
            color: var(--tcm-purple);
        }

        .btn-reactivate:hover {
            background-color: var(--tcm-purple);
            color: #fff;
        }

        .btn-edit {
            background-color: transparent;
            border: 1px solid var(--tcm-gold);
            color: var(--tcm-gold-dark);
        }

        .btn-edit:hover {
            background-color: var(--tcm-gold);
            color: var(--tcm-purple-dark);
        }

        .btn-remove {
            background-color: transparent;
            border: 1px solid #dc3545;
            color: #dc3545;
        }

        .btn-remove:hover {
            background-color: #dc3545;
            color: #fff;
        }

        .modal-header {
            background-color: var(--tcm-purple);
            color: #fff;
            border-radius: 0.5rem 0.5rem 0 0;
        }

        .modal-header .btn-close {
            filter: invert(1);
        }

        .modal-footer .btn-success {
            background-color: var(--tcm-purple);
            border-color: var(--tcm-purple);
        }

        .modal-footer .btn-success:hover {
            background-color: var(--tcm-purple-dark);
            border-color: var(--tcm-purple-dark);
        }
    </style>
</head>
<body>

<?php $nav_mode = 'page'; include('navbar.php'); ?>

<div class="container students-wrap mt-4">
    <h4 class="page-heading mb-3">Student Records</h4>
    <?php if (!$cols_ready) echo st_migration_notice(); ?>

    <!-- Search Form -->
    <form method="GET" class="d-flex gap-2 mb-3 search-bar">
        <div class="d-flex flex-grow-1">
            <input 
                type="text" 
                name="search" 
                class="form-control" 
                placeholder="Search by name or student number..."
                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
            >
            <button type="submit" class="btn btn-tcm-search"><i class="bi bi-search"></i></button>
        </div>
        <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
        <a href="students.php" class="btn btn-outline-secondary">Clear</a>
    </form>

    <!-- Active / Inactive / All Filter Tabs -->
    <div class="btn-group filter-tabs mb-3" role="group">
        <a href="?view=active<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
           class="btn btn-sm <?php echo $view == 'active' ? 'active-tab' : ''; ?>">Active</a>
        <a href="?view=inactive<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
           class="btn btn-sm <?php echo $view == 'inactive' ? 'active-tab' : ''; ?>">Deactivated</a>
        <a href="?view=all<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
           class="btn btn-sm <?php echo $view == 'all' ? 'active-tab' : ''; ?>">All</a>
    </div>

    <!-- Add Student Button -->
    <div class="mb-3">
    <button class="btn btn-tcm-add" data-bs-toggle="modal" data-bs-target="#addStudentModal">
        <i class="bi bi-person-plus me-1"></i>Add New Student
    </button>
    </div>

    <!-- Students Table -->
    <div class="card card-table">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Student No.</th>
                        <th class="name-head">Name</th>
                        <th>Gender</th>
                        <th>Course</th>
                        <th>Year Level</th>
                        <th>Books Borrowed</th> 
                        <th>Borrow Status</th>
                        <th>Record Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $conditions = [];

                if ($search) {
                    $search_safe = mysqli_real_escape_string($conn, $search);
                    $conditions[] = "(name LIKE '%$search_safe%' OR student_no LIKE '%$search_safe%' OR course LIKE '%$search_safe%'" . ($cols_ready ? " OR year_level LIKE '%$search_safe%' OR gender LIKE '$search_safe%'" : "") . ")";
                }

                if ($view == 'active') {
                    $conditions[] = "is_active = 1";
                } elseif ($view == 'inactive') {
                    $conditions[] = "is_active = 0";
                }
                // 'all' adds no is_active condition

                $where_sql = count($conditions) > 0 ? 'WHERE ' . implode(' AND ', $conditions) : '';
                $query = "SELECT * FROM students $where_sql ORDER BY name ASC";

                $result = mysqli_query($conn, $query);

                if (mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        // Count how many books this student currently has
                        $sid = $row['id'];
                        $count_query = "SELECT COUNT(*) as total FROM borrowing 
                                        WHERE student_id = $sid 
                                        AND return_date IS NULL";
                        $count_result = mysqli_query($conn, $count_query);
                        $count_row = mysqli_fetch_assoc($count_result);
                        $borrowed = $count_row['total'];

                        $status = $borrowed >= 3 
                            ? '<span class="badge badge-limit-reached">Limit Reached</span>' 
                            : '<span class="badge badge-can-borrow">Can Borrow</span>';

                        $record_status = $row['is_active'] == 1
                            ? '<span class="status-dot-wrap"><span class="status-dot dot-active"></span><span class="badge badge-active">Active</span></span>'
                            : '<span class="status-dot-wrap"><span class="status-dot dot-inactive"></span><span class="badge badge-inactive">Deactivated</span></span>';

                        $action_btn = $row['is_active'] == 1
                            ? "<a href='deactivate_student.php?id={$row['id']}' 
                                    class='btn btn-sm btn-deactivate'
                                    onclick='return confirm(\"Deactivate this student? Their records and borrowing history will be kept, but they will no longer appear as an active student.\")'>
                                    Deactivate
                                </a>"
                            : "<a href='reactivate_student.php?id={$row['id']}' 
                                    class='btn btn-sm btn-reactivate'
                                    onclick='return confirm(\"Reactivate this student?\")'>
                                    Reactivate
                                </a>";

                        $g = isset($row['gender']) ? $row['gender'] : '';
                        $gender_cell = $g === 'Male' ? '<span class="badge badge-male">Male</span>'
                                     : ($g === 'Female' ? '<span class="badge badge-female">Female</span>'
                                     : ($g !== '' && $g !== null ? st_h($g) : '<span class="text-muted">—</span>'));
                        $lvl = isset($row['year_level']) && $row['year_level'] !== '' && $row['year_level'] !== null ? st_h($row['year_level']) : '<span class="text-muted">—</span>';
                        $course_cell = $row['course'] !== '' ? st_h($row['course']) : '<span class="text-muted">—</span>';

                        echo "<tr>
                            <td>" . st_h($row['student_no']) . "</td>
                            <td class='name-cell'>" . st_h($row['name']) . "</td>
                            <td>{$gender_cell}</td>
                            <td>{$course_cell}</td>
                            <td>{$lvl}</td>
                            <td>{$borrowed} / 3</td>
                            <td>{$status}</td>
                            <td>{$record_status}</td>
                            <td>
                                <a href='edit_student.php?id={$row['id']}' 
                                class='btn btn-sm btn-edit'>
                                Edit
                                </a>
                                {$action_btn}
                            </td>
                        </tr>";
                        
                    }
                } else {
                    echo "<tr><td colspan='9' class='text-center text-muted py-3'>No students found.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="add_student.php">
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Student Number</label>
                    <input type="text" name="student_no" class="form-control" required>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-sm-6">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" id="f-last" class="form-control" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" id="f-first" class="form-control" required>
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label">Middle Initial <span class="text-muted small">(optional)</span></label>
                        <input type="text" name="mi" id="f-mi" class="form-control" maxlength="3">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Suffix <span class="text-muted small">(optional)</span></label>
                        <input type="text" name="suffix" id="f-sfx" class="form-control" maxlength="10" placeholder="Jr., III ...">
                    </div>
                </div>
                <div class="small text-muted mb-3">Saved as: <strong id="name-preview">—</strong></div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="">— not set —</option>
                            <?php foreach (ST_GENDERS as $o) echo '<option>' . st_h($o) . '</option>'; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Year Level</label>
                        <select name="year_level" class="form-select">
                            <option value="">— not set —</option>
                            <?php foreach (ST_LEVELS as $o) echo '<option>' . st_h($o) . '</option>'; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Course</label>
                    <select name="course" class="form-select" required>
                        <?php foreach (ST_COURSES as $o) echo '<option>' . st_h($o) . '</option>'; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Contact Number <span class="text-muted small">(optional)</span></label>
                    <input type="text" name="contact" class="form-control" maxlength="20">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success"<?php echo $cols_ready ? '' : ' disabled'; ?>>Add Student</button>
            </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// shows how the typed name will be saved: Last, First M. Suffix
(function () {
    var ids = ['f-last', 'f-first', 'f-mi', 'f-sfx'], el = {};
    ids.forEach(function (i) { el[i] = document.getElementById(i); });
    if (!el['f-last']) return;
    function clean(v) { return (v || '').replace(/\s+/g, ' ').trim(); }
    function render() {
        var last = clean(el['f-last'].value), first = clean(el['f-first'].value), mi = clean(el['f-mi'].value).replace(/[\s.]+/g, ''), sfx = clean(el['f-sfx'].value);
        if (mi.length === 1) mi = mi.toUpperCase() + '.';
        else if (mi.length > 1) mi = clean(el['f-mi'].value);
        if (/^(jr|sr)\.?$/i.test(sfx)) sfx = sfx.charAt(0).toUpperCase() + sfx.slice(1, 2).toLowerCase() + '.';
        var given = [first, mi, sfx].filter(Boolean).join(' ');
        var out = last && given ? last + ', ' + given : (last || given);
        document.getElementById('name-preview').textContent = out || '—';
    }
    ids.forEach(function (i) { el[i].addEventListener('input', render); });
})();
</script>

</body>
</html>