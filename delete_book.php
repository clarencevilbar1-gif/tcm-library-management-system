<?php
include('db.php');

$id = $_GET['id'];

// Safety check — don't delete if book is currently borrowed
$check = mysqli_query($conn, "SELECT * FROM borrowing WHERE book_id = $id AND return_date IS NULL");

if (mysqli_num_rows($check) > 0) {
    echo "<script>alert('Cannot delete — this book is currently borrowed.'); window.location='books.php';</script>";
} else {
    mysqli_query($conn, "DELETE FROM books WHERE id = $id");
    header('Location: books.php');
    exit();
}
?>