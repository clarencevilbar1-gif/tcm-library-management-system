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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --tcm-purple: #4B2E83;
            --tcm-purple-dark: #35205E;
            --tcm-gold: #D4A72C;
            --tcm-gold-dark: #B88F22;
        }

        body {
            background: linear-gradient(180deg, #f6f4f9 0%, #ece5f3 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .navbar-tcm {
            background-color: var(--tcm-purple);
            padding: 0.9rem 2rem;
        }

        .navbar-tcm .navbar-brand {
            color: #fff;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .navbar-tcm .navbar-brand img {
            width: 40px;
            height: 40px;
            object-fit: contain;
        }

        .navbar-tcm .btn-outline-light {
            border-color: rgba(255,255,255,0.5);
            font-size: 0.85rem;
        }

        .navbar-tcm .btn-outline-light:hover {
            background-color: var(--tcm-gold);
            border-color: var(--tcm-gold);
            color: var(--tcm-purple-dark);
        }

        .page-heading {
            color: var(--tcm-purple-dark);
            font-weight: 700;
        }

        .card-tcm {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 16px rgba(75, 46, 131, 0.1);
        }

        .btn-tcm-gold {
            background-color: var(--tcm-gold);
            color: var(--tcm-purple-dark);
            font-weight: 600;
            border: none;
        }

        .btn-tcm-gold:hover {
            background-color: var(--tcm-gold-dark);
            color: var(--tcm-purple-dark);
        }
    </style>
</head>
<body>

<nav class="navbar navbar-tcm d-flex justify-content-between">
    <span class="navbar-brand">
        <img src="assets/images/tcm_logo.png" alt="TCM Seal">
        Law Department Library
    </span>
    <a href="books.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Inventory</a>
</nav>

<div class="container mt-4" style="max-width: 500px;">
    <h4 class="page-heading mb-4"><i class="bi bi-pencil-square me-2"></i>Edit Book</h4>
    <div class="card card-tcm p-4">
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($book['title']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Author</label>
                <input type="text" name="author" class="form-control" value="<?php echo htmlspecialchars($book['author']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Serial Number</label>
                <input type="text" name="serial_no" class="form-control" value="<?php echo htmlspecialchars($book['serial_no']); ?>" required>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-tcm-gold w-100">Save Changes</button>
                <a href="books.php" class="btn btn-outline-secondary w-100">Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
