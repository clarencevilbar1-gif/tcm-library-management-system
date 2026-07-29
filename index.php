<?php 
include('auth.php');
include('db.php'); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Law Library System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark px-4 d-flex justify-content-between">
    <span class="navbar-brand fw-bold">⚖️ TCM College of Law Library</span>
    <div class="d-flex align-items-center gap-3">
        <span class="text-white-50 small">
            Logged in as <strong class="text-white"><?php echo $_SESSION['staff_username']; ?></strong>
        </span>
        <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
    </div>
</nav>

<div class="container mt-5">
    <h4 class="mb-4 text-secondary">Main Menu</h4>
    <div class="row g-3">

        <div class="col-md-4">
            <a href="students.php" class="btn btn-outline-primary w-100 py-4 fs-5">
                👤 Student Records
            </a>
        </div>

        <div class="col-md-4">
            <a href="books.php" class="btn btn-outline-success w-100 py-4 fs-5">
                📚 Book Inventory
            </a>
        </div>

        <div class="col-md-4">
            <a href="borrow.php" class="btn btn-outline-warning w-100 py-4 fs-5">
                📋 Borrow / Return
            </a>
        </div>
        
        <div class="col-md-4">
            <a href="import.php" class="btn btn-outline-secondary w-100 py-4 fs-5">
                📥 Import Data
            </a>
        </div>

        <div class="col-md-4">
            <a href="history.php" class="btn btn-outline-info w-100 py-4 fs-5">
                📜 Transaction History
            </a>
        </div>

    </div>
</div>

</body>
</html>