<?php
include('db.php');

$student_no = mysqli_real_escape_string($conn, trim($_POST['student_no']));
$name = mysqli_real_escape_string($conn, trim($_POST['name']));
$course = mysqli_real_escape_string($conn, trim($_POST['course']));

// Check if student number already exists
$check = mysqli_query($conn, "SELECT * FROM students WHERE student_no = '$student_no'");

if (mysqli_num_rows($check) > 0) {
    echo "<script>alert('Student number already exists!'); window.location='students.php';</script>";
} else {
    mysqli_query($conn, "INSERT INTO students (student_no, name, course) VALUES ('$student_no', '$name', '$course')");
    header('Location: students.php');
    exit();
}
?>