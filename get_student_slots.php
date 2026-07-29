<?php
include('db.php');

$student_id = intval($_GET['student_id']);

$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM borrowing WHERE student_id = $student_id AND return_date IS NULL");
$row = mysqli_fetch_assoc($result);

echo json_encode(['borrowed' => (int)$row['total']]);
?>