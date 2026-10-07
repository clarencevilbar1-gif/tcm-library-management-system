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
            width: 56px;
            height: 56px;
            object-fit: contain;
        }

        .navbar-tcm .logout-btn {
            border: 1px solid rgba(255,255,255,0.5);
            color: #fff;
            font-size: 0.85rem;
        }

        .navbar-tcm .logout-btn:hover {
            background-color: var(--tcm-gold);
            border-color: var(--tcm-gold);
            color: var(--tcm-purple-dark);
        }

        .page-heading {
            color: var(--tcm-purple-dark);
            font-weight: 700;
        }

        .page-subheading {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .menu-tile {
            background-color: #fff;
            border: 1px solid #e5dfee;
            border-radius: 14px;
            padding: 2rem 1.25rem;
            text-align: center;
            text-decoration: none;
            display: block;
            transition: all 0.18s ease;
            box-shadow: 0 2px 8px rgba(75, 46, 131, 0.06);
        }

        .menu-tile:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(75, 46, 131, 0.15);
            border-color: var(--tcm-purple);
        }

        .menu-tile .tile-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background-color: rgba(75, 46, 131, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: var(--tcm-purple);
        }

        .menu-tile .tile-label {
            font-weight: 600;
            color: #212529;
            font-size: 1.02rem;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-tcm d-flex justify-content-between">
    <span class="navbar-brand">
        <img src="assets/images/tcm_logo.png" alt="TCM Seal">
        TCM College of Law Library Management System
    </span>
    <div class="d-flex align-items-center gap-3">
        <span class="text-white-50 small">
            Logged in as <strong class="text-white"><?php echo $_SESSION['staff_username']; ?></strong>
        </span>
        <a href="logout.php" class="btn btn-outline-light logout-btn btn-sm">
            <i class="bi bi-box-arrow-right me-1"></i>Logout
        </a>
    </div>
</nav>

<div class="container mt-5">
    <h4 class="page-heading mb-1">Main Menu</h4>
    <p class="page-subheading mb-4">Select a module to get started</p>

    <div class="row g-4">

        <div class="col-md-4">
            <a href="students.php" class="menu-tile">
                <div class="tile-icon"><i class="bi bi-person-badge"></i></div>
                <div class="tile-label">Student Records</div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="books.php" class="menu-tile">
                <div class="tile-icon"><i class="bi bi-book"></i></div>
                <div class="tile-label">Book Inventory</div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="borrow.php" class="menu-tile">
                <div class="tile-icon"><i class="bi bi-journal-arrow-up"></i></div>
                <div class="tile-label">Borrow Book</div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="import.php" class="menu-tile">
                <div class="tile-icon"><i class="bi bi-file-earmark-arrow-down"></i></div>
                <div class="tile-label">Import Data</div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="history.php" class="menu-tile">
                <div class="tile-icon"><i class="bi bi-clock-history"></i></div>
                <div class="tile-label">Transaction History</div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="return.php" class="menu-tile">
                <div class="tile-icon"><i class="bi bi-journal-arrow-down"></i></div>
                <div class="tile-label">Return Book</div>
            </a>
        </div>

    </div>
</div>

</body>
</html>