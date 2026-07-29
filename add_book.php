<?php
include('db.php');

$title = mysqli_real_escape_string($conn, $_POST['title']);
$author = mysqli_real_escape_string($conn, $_POST['author']);
$serial_no = mysqli_real_escape_string($conn, $_POST['serial_no']);

$query = "INSERT INTO books (title, author, serial_no, is_available) 
          VALUES ('$title', '$author', '$serial_no', 1)";

mysqli_query($conn, $query);

header('Location: books.php');
exit();
?>