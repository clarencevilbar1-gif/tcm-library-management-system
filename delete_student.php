<?php
include('db.php');

$id = intval($_GET['id']);

// Safety check — don't delete if student has currently borrowed books
$check = mysqli_query($conn, "SELECT * FROM borrowing WHERE student_id = $id AND return_date IS NULL");

if (mysqli_num_rows($check) > 0) {
    echo "<script>
        alert('Cannot remove — this student currently has borrowed book(s). Please process the return first.');
        window.location='students.php';
    </script>";
} else {
    mysqli_query($conn, "DELETE FROM students WHERE id = $id");
    header('Location: students.php');
    exit();
}
?>