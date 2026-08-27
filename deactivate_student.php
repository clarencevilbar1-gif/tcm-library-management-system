<?php
include('db.php');

$id = intval($_GET['id']);

// Safety check — don't deactivate if student has currently borrowed books.
// They need to return their books first, otherwise those borrowed books
// would be "stuck" with a student who no longer shows up in the active list.
$check = mysqli_query($conn, "SELECT * FROM borrowing WHERE student_id = $id AND return_date IS NULL");

if (mysqli_num_rows($check) > 0) {
    echo "<script>
        alert('Cannot deactivate — this student currently has borrowed book(s). Please process the return first.');
        window.location='students.php';
    </script>";
} else {
    mysqli_query($conn, "UPDATE students SET is_active = 0 WHERE id = $id");
    header('Location: students.php');
    exit();
}
?>