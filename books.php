<?php 
include('auth.php');
include('db.php'); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --tcm-green: #1B5E45;
            --tcm-green-dark: #144534;
            --tcm-gold: #D4A72C;
            --tcm-gold-dark: #B88F22;
        }

        body {
            background: linear-gradient(180deg, #f4f7f6 0%, #e8f0ec 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .navbar-tcm {
            background-color: var(--tcm-green);
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
            color: var(--tcm-green-dark);
        }

        .page-heading {
            color: var(--tcm-green-dark);
            font-weight: 700;
        }

        .search-bar .form-control {
            border-radius: 8px 0 0 8px;
        }

        .btn-tcm-search {
            background-color: var(--tcm-green);
            color: #fff;
            border-radius: 0 8px 8px 0;
        }

        .btn-tcm-search:hover {
            background-color: var(--tcm-green-dark);
            color: #fff;
        }

        .btn-tcm-add {
            background-color: var(--tcm-gold);
            color: var(--tcm-green-dark);
            font-weight: 600;
            border: none;
        }

        .btn-tcm-add:hover {
            background-color: var(--tcm-gold-dark);
            color: var(--tcm-green-dark);
        }

        .card-table {
            border: none;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(27, 94, 69, 0.08);
        }

        .table thead th {
            background-color: var(--tcm-green) !important;
            color: #fff;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border: none;
            padding: 0.9rem 1rem;
        }

        .table th, .table td {
            text-align: center;
        }

        .table tbody td {
            vertical-align: middle;
            padding: 0.8rem 1rem;
        }

        .table tbody tr:hover {
            background-color: rgba(27, 94, 69, 0.05);
        }

        .badge-available {
            background-color: rgba(27, 94, 69, 0.12);
            color: var(--tcm-green-dark);
            font-weight: 600;
            padding: 0.4em 0.7em;
        }

        .badge-borrowed {
            background-color: rgba(220, 53, 69, 0.12);
            color: #b02a37;
            font-weight: 600;
            padding: 0.4em 0.7em;
        }

        .btn-edit {
            background-color: transparent;
            border: 1px solid var(--tcm-gold);
            color: var(--tcm-gold-dark);
        }

        .btn-edit:hover {
            background-color: var(--tcm-gold);
            color: var(--tcm-green-dark);
        }

        .modal-header {
            background-color: var(--tcm-green);
            color: #fff;
            border-radius: 0.5rem 0.5rem 0 0;
        }

        .modal-header .btn-close {
            filter: invert(1);
        }

        .modal-footer .btn-success {
            background-color: var(--tcm-green);
            border-color: var(--tcm-green);
        }

        .modal-footer .btn-success:hover {
            background-color: var(--tcm-green-dark);
            border-color: var(--tcm-green-dark);
        }
    </style>
</head>
<body>

<nav class="navbar navbar-tcm d-flex justify-content-between">
    <span class="navbar-brand">
        <img src="assets/images/tcm_logo.png" alt="TCM Seal">
        Law Department Library
    </span>
    <div class="d-flex align-items-center gap-3">
        <a href="index.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Menu</a>
        <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
    </div>
</nav>

<div class="container mt-4">
    <h4 class="page-heading mb-3">Book Inventory</h4>

    <!-- Search Form -->
    <form method="GET" class="d-flex gap-2 mb-4 search-bar">
        <div class="d-flex flex-grow-1">
            <input 
                type="text" 
                name="search" 
                class="form-control" 
                placeholder="Search by title, author, or serial number..."
                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
            >
            <button type="submit" class="btn btn-tcm-search"><i class="bi bi-search"></i></button>
        </div>
        <a href="books.php" class="btn btn-outline-secondary">Clear</a>
    </form>

    <!-- Add Book Button -->
    <div class="mb-3">
        <button class="btn btn-tcm-add" data-bs-toggle="modal" data-bs-target="#addBookModal">
            <i class="bi bi-journal-plus me-1"></i>Add New Book
        </button>
    </div>

    <!-- Books Table -->
    <div class="card card-table">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Serial No.</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Availability</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $search = isset($_GET['search']) ? $_GET['search'] : '';

                if ($search) {
                    $search_safe = mysqli_real_escape_string($conn, $search);
                    $query = "SELECT * FROM books 
                              WHERE title LIKE '%$search_safe%' 
                              OR author LIKE '%$search_safe%'
                              OR serial_no LIKE '%$search_safe%'";
                } else {
                    $query = "SELECT * FROM books";
                }

                $result = mysqli_query($conn, $query);

                if (mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $availability = $row['is_available'] == 1
                            ? '<span class="badge badge-available">Available</span>'
                            : '<span class="badge badge-borrowed">Borrowed</span>';

                        echo "<tr>
                            <td>{$row['serial_no']}</td>
                            <td>{$row['title']}</td>
                            <td>{$row['author']}</td>
                            <td>{$availability}</td>
                            <td>
                                <a href='edit_book.php?id={$row['id']}' class='btn btn-sm btn-edit'>Edit</a>
                            </td>
                        </tr>";
                    }
                } else {
                    echo "<tr><td colspan='4' class='text-center text-muted py-3'>No books found.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Book Modal -->
<div class="modal fade" id="addBookModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Book</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="add_book.php">
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Author</label>
                    <input type="text" name="author" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Serial Number</label>
                    <input type="text" name="serial_no" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Add Book</button>
            </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>