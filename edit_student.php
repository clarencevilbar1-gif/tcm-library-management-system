<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include('db.php');

$id = intval($_GET['id']);
$result = mysqli_query($conn, "SELECT * FROM students WHERE id = $id");
$student = mysqli_fetch_assoc($result);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_no = mysqli_real_escape_string($conn, trim($_POST['student_no']));
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $course = mysqli_real_escape_string($conn, trim($_POST['course']));

    // Check if student number already exists on another student
    $check = mysqli_query($conn, "SELECT id FROM students WHERE student_no = '$student_no' AND id != $id");
    if (mysqli_num_rows($check) > 0) {
        echo "<script>alert('Student number already exists on another record!'); window.location='edit_student.php?id=$id';</script>";
    } else {
        mysqli_query($conn, "UPDATE students SET student_no='$student_no', name='$name', course='$course' WHERE id=$id");
        header('Location: students.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark px-4">
    <span class="navbar-brand fw-bold">⚖️ Law Department Library</span>
    <a href="students.php" class="btn btn-outline-light btn-sm">← Back to Students</a>
</nav>

<div class="container mt-4" style="max-width: 500px;">
    <h4 class="mb-4">Edit Student</h4>
    <div class="card p-4">
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Student Number</label>
                <input type="text" name="student_no" class="form-control" 
                       value="<?php echo htmlspecialchars($student['student_no']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="form-control" 
                       value="<?php echo htmlspecialchars($student['name']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Course</label>
                <input type="text" name="course" class="form-control" 
                       value="<?php echo htmlspecialchars($student['course']); ?>" required>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning w-100">Save Changes</button>
                <a href="students.php" class="btn btn-outline-secondary w-100">Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>