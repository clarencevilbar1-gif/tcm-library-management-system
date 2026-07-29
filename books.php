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
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark px-4 d-flex justify-content-between">
    <span class="navbar-brand fw-bold">⚖️ Law Department Library</span>
    <div class="d-flex align-items-center gap-3">
        <a href="index.php" class="btn btn-outline-light btn-sm">← Back to Menu</a>
        <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
    </div>
</nav>

<div class="container mt-4">
    <h4 class="mb-3">Book Inventory</h4>

    <!-- Search Form -->
    <form method="GET" class="d-flex gap-2 mb-4">
        <input 
            type="text" 
            name="search" 
            class="form-control" 
            placeholder="Search by title, author, or serial number..."
            value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
        >
        <button type="submit" class="btn btn-primary">Search</button>
        <a href="books.php" class="btn btn-outline-secondary">Clear</a>
    </form>

    <!-- Add Book Button -->
    <div class="mb-3">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addBookModal">
            + Add New Book
        </button>
    </div>

    <!-- Books Table -->
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
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
                            ? '<span class="badge bg-success">Available</span>'
                            : '<span class="badge bg-danger">Borrowed</span>';

                        echo "<tr>
                            <td>{$row['serial_no']}</td>
                            <td>{$row['title']}</td>
                            <td>{$row['author']}</td>
                            <td>{$availability}</td>
                            <td>
                                <a href='edit_book.php?id={$row['id']}' class='btn btn-sm btn-warning'>Edit</a>
                                <a href='delete_book.php?id={$row['id']}' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure you want to delete this book?\")'>Delete</a>
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