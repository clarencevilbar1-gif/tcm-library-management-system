<?php
include('db.php');

$id = intval($_GET['id']);

mysqli_query($conn, "UPDATE students SET is_active = 1 WHERE id = $id");
header('Location: students.php');
exit();
?>