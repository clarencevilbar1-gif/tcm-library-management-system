<?php
include('auth_quiet.php');
include('db.php');
include('student_functions.php');

function add_student_back($msg) {
    echo "<script>alert(" . json_encode($msg) . "); window.location='students.php';</script>";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: students.php'); exit(); }
if (!st_columns_ready($conn)) add_student_back('The student table has not been updated yet. Run migration_students_format.sql in phpMyAdmin first.');

$student_no = st_cut(st_text(isset($_POST['student_no']) ? $_POST['student_no'] : ''), 50);

// The name is typed in parts and saved as "Last, First M. Suffix" (an older form that sends one "name" still works)
if (isset($_POST['last_name']) || isset($_POST['first_name'])) {
    $name = st_merge_name(isset($_POST['last_name']) ? $_POST['last_name'] : '', isset($_POST['first_name']) ? $_POST['first_name'] : '',
                          isset($_POST['mi']) ? $_POST['mi'] : '', isset($_POST['suffix']) ? $_POST['suffix'] : '');
} else {
    $name = st_text(isset($_POST['name']) ? $_POST['name'] : '');
}
$name = st_cut($name, 150);

$gender = isset($_POST['gender']) && in_array($_POST['gender'], ST_GENDERS, true) ? $_POST['gender'] : null;
$level  = isset($_POST['year_level']) && in_array($_POST['year_level'], ST_LEVELS, true) ? $_POST['year_level'] : null;
$course = st_text(isset($_POST['course']) ? $_POST['course'] : '');
if ($course === '') $course = ST_COURSES[0];
$course = st_cut($course, 100);
$contact = st_norm_contact(isset($_POST['contact']) ? $_POST['contact'] : '');

if ($student_no === '' || $name === '') add_student_back('Please fill in the student number and the name.');

$check = mysqli_prepare($conn, "SELECT id FROM students WHERE student_no = ? LIMIT 1");
mysqli_stmt_bind_param($check, 's', $student_no);
mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);
if (mysqli_stmt_num_rows($check) > 0) add_student_back('Student number already exists!');

$ins = mysqli_prepare($conn, "INSERT INTO students (student_no, name, gender, course, year_level, contact_number) VALUES (?, ?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($ins, 'ssssss', $student_no, $name, $gender, $course, $level, $contact);
if (!mysqli_stmt_execute($ins)) add_student_back('The student could not be saved.');
header('Location: students.php');
exit();
