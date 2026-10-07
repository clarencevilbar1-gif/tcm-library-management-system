<?php 
include('auth.php');
include('db.php'); 

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
    <h4 class="page-heading mb-3">Student Records</h4>

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
                        <th>Name</th>
                        <th>Course</th>
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
                    $conditions[] = "(name LIKE '%$search_safe%' OR student_no LIKE '%$search_safe%')";
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

                        echo "<tr>
                            <td>{$row['student_no']}</td>
                            <td>{$row['name']}</td>
                            <td>{$row['course']}</td>
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
                    echo "<tr><td colspan='7' class='text-center text-muted py-3'>No students found.</td></tr>";
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
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Course</label>
                    <input type="text" name="course" class="form-control" value="Juris Doctor" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Add Student</button>
            </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>