<?php 
ini_set('display_errors', 1);
error_reporting(E_ALL);
include('db.php');

$id = $_GET['id'];
$result = mysqli_query($conn, "SELECT * FROM books WHERE id = $id");
$book = mysqli_fetch_assoc($result);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $author = mysqli_real_escape_string($conn, $_POST['author']);
    $serial_no = mysqli_real_escape_string($conn, $_POST['serial_no']);

    mysqli_query($conn, "UPDATE books SET title='$title', author='$author', serial_no='$serial_no' WHERE id=$id");
    header('Location: books.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Book</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark px-4">
    <span class="navbar-brand fw-bold">⚖️ Law Department Library</span>
    <a href="books.php" class="btn btn-outline-light btn-sm">← Back to Inventory</a>
</nav>

<div class="container mt-4" style="max-width: 500px;">
    <h4 class="mb-4">Edit Book</h4>
    <div class="card p-4">
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control" value="<?php echo $book['title']; ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Author</label>
                <input type="text" name="author" class="form-control" value="<?php echo $book['author']; ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Serial Number</label>
                <input type="text" name="serial_no" class="form-control" value="<?php echo $book['serial_no']; ?>" required>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning w-100">Save Changes</button>
                <a href="books.php" class="btn btn-outline-secondary w-100">Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>