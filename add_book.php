<?php
include('db.php');

$title = mysqli_real_escape_string($conn, $_POST['title']);
$author = mysqli_real_escape_string($conn, $_POST['author']);
$serial_no = mysqli_real_escape_string($conn, $_POST['serial_no']);
$total_copies = max(1, intval($_POST['total_copies'] ?? 1));

$query = "INSERT INTO books (title, author, serial_no, total_copies, available_copies, is_available) 
          VALUES ('$title', '$author', '$serial_no', $total_copies, $total_copies, 1)";

mysqli_query($conn, $query);

header('Location: books.php');
exit();
?>