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
            box-shadow: 0 4px 16px rgba(75, 46, 131, 0.1);
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

<?php $nav_mode = 'back'; $nav_back_url = 'students.php'; $nav_back_label = 'Back to Students'; include('navbar.php'); ?>

<div class="container mt-4" style="max-width: 500px;">
    <h4 class="page-heading mb-4"><i class="bi bi-pencil-square me-2"></i>Edit Student</h4>
    <div class="card card-tcm p-4">
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
                <button type="submit" class="btn btn-tcm-gold w-100">Save Changes</button>
                <a href="students.php" class="btn btn-outline-secondary w-100">Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>