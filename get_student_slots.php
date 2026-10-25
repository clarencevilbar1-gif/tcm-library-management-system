<?php
$auth_json = true;
include('auth_quiet.php');
include('db.php');
include('student_functions.php');

$student_id = intval(isset($_GET['student_id']) ? $_GET['student_id'] : 0);

$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM borrowing WHERE student_id = $student_id AND return_date IS NULL");
$row = mysqli_fetch_assoc($result);

// the contact already on the student's record (used to pre-fill the Borrow page)
$has_email = st_email_ready($conn);
$c = mysqli_query($conn, "SELECT contact_number" . ($has_email ? ", email" : "") . " FROM students WHERE id = $student_id");
$crow = $c ? mysqli_fetch_assoc($c) : null;

echo json_encode([
    'borrowed' => (int)$row['total'],
    'phone'    => $crow && $crow['contact_number'] !== null ? (string)$crow['contact_number'] : '',
    'email'    => $crow && $has_email && $crow['email'] !== null ? (string)$crow['email'] : '',
    'has_email' => $has_email,
]);
?>
