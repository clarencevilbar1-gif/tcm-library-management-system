<?php 
include('auth.php');
include('db.php'); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark px-4 d-flex justify-content-between">
    <span class="navbar-brand fw-bold">⚖️ Law Department Library</span>
    <div class="d-flex align-items-center gap-3">
        <a href="index.php" class="btn btn-outline-light btn-sm">← Back to Menu</a>
        <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
    </div>
</nav>

<div class="container mt-4">
    <h4 class="mb-3">Student Records</h4>

    <!-- Search Form -->
    <form method="GET" class="d-flex gap-2 mb-4">
        <input 
            type="text" 
            name="search" 
            class="form-control" 
            placeholder="Search by name or student number..."
            value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
        >
        <button type="submit" class="btn btn-primary">Search</button>
        <a href="students.php" class="btn btn-outline-secondary">Clear</a>
    </form>

    <!-- Add Student Button -->
    <div class="mb-3">
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addStudentModal">
        + Add New Student
    </button>
    </div>

    <!-- Students Table -->
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Student No.</th>
                        <th>Name</th>
                        <th>Course</th>
                        <th>Books Borrowed</th> 
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $search = isset($_GET['search']) ? $_GET['search'] : '';

                if ($search) {
                    $search_safe = mysqli_real_escape_string($conn, $search);
                    $query = "SELECT * FROM students 
                              WHERE name LIKE '%$search_safe%' 
                              OR student_no LIKE '%$search_safe%'";
                } else {
                    $query = "SELECT * FROM students";
                }

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
                            ? '<span class="badge bg-danger">Limit Reached</span>' 
                            : '<span class="badge bg-success">Can Borrow</span>';

                        echo "<tr>
                            <td>{$row['student_no']}</td>
                            <td>{$row['name']}</td>
                            <td>{$row['course']}</td>
                            <td>{$borrowed} / 3</td>
                            <td>{$status}</td>
                            <td>
                                <a href='edit_student.php?id={$row['id']}' 
                                class='btn btn-sm btn-warning'>
                                Edit
                                </a>
                                <a href='delete_student.php?id={$row['id']}' 
                                    class='btn btn-sm btn-danger'
                                    onclick='return confirm(\"Are you sure you want to remove this student?\")'>
                                    Remove
                                </a>
                            </td>
                        </tr>";
                        
                    }
                } else {
                    echo "<tr><td colspan='5' class='text-center text-muted py-3'>No students found.</td></tr>";
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