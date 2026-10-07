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

        .stat-card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 16px rgba(75, 46, 131, 0.08);
        }

        .stat-total .fs-2 { color: var(--tcm-purple-dark); }
        .stat-borrowed .fs-2 { color: #b02a37; }

        .search-bar .form-control {
            border-radius: 8px 0 0 8px;
        }

        .btn-tcm-search {
            background-color: var(--tcm-purple);
            color: #fff;
            border-radius: 0 8px 8px 0;
        }

        .btn-tcm-search:hover {
            background-color: var(--tcm-purple-dark);
            color: #fff;
        }

        .btn-tcm-add {
            background-color: var(--tcm-gold);
            color: var(--tcm-purple-dark);
            font-weight: 600;
            border: none;
        }

        .btn-tcm-add:hover {
            background-color: var(--tcm-gold-dark);
            color: var(--tcm-purple-dark);
        }

        .card-table {
            border: none;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(75, 46, 131, 0.08);
        }

        .table thead th {
            background-color: var(--tcm-purple) !important;
            color: #fff;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border: none;
            padding: 0.9rem 1rem;
            text-align: center;
            white-space: nowrap;
        }

        .table th, .table td {
            text-align: left;
        }

        /* Fixed column widths so long titles wrap inside their own column
           instead of squeezing Author and Availability */
        .card-table .card-body {
            overflow-x: auto;
        }

        .table-books {
            table-layout: fixed;
            width: 100%;
            min-width: 900px;
        }

        .table-books thead th {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }

        /* Header alignment follows the data underneath:
           text columns (Serial No., Title, Author) are left-aligned,
           short/status columns (Copies, Availability, Actions) are centered */
        .table.table-books thead th {
            text-align: left;
        }

        .table.table-books th.col-center,
        .table.table-books td.col-center {
            text-align: center;
        }

        .table-books tbody td {
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .table-books .badge,
        .table-books .borrowed-count-text {
            display: inline-block;
            white-space: nowrap;
        }

        .table th.text-actions, .table td.text-actions {
            text-align: center;
        }

        .table tbody td {
            vertical-align: middle;
            padding: 0.8rem 1rem;
        }

        .table tbody tr:hover {
            background-color: rgba(75, 46, 131, 0.05);
        }

        .badge-available {
            background-color: rgba(46, 204, 113, 0.15);
            color: #1e8449;
            font-weight: 600;
            padding: 0.4em 0.7em;
        }

        .badge-borrowed {
            background-color: rgba(220, 53, 69, 0.12);
            color: #b02a37;
            font-weight: 600;
            padding: 0.4em 0.7em;
        }

        .borrowed-count-text {
            color: #6c757d;
            font-size: 0.85rem;
            margin-left: 0.4rem;
        }

        .btn-edit {
            background-color: transparent;
            border: 1px solid var(--tcm-gold);
            color: var(--tcm-gold-dark);
        }

        .btn-edit:hover {
            background-color: var(--tcm-gold);
            color: var(--tcm-purple-dark);
        }

        .modal-header {
            background-color: var(--tcm-purple);
            color: #fff;
            border-radius: 0.5rem 0.5rem 0 0;
        }

        .modal-header .btn-close {
            filter: invert(1);
        }

        .modal-footer .btn-success {
            background-color: var(--tcm-purple);
            border-color: var(--tcm-purple);
        }

        .modal-footer .btn-success:hover {
            background-color: var(--tcm-purple-dark);
            border-color: var(--tcm-purple-dark);
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

    <!-- Live Counters -->
    <?php
    $counts = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS titles, COALESCE(SUM(total_copies),0) AS total_copies, COALESCE(SUM(total_copies - available_copies),0) AS borrowed_copies FROM books"));
    ?>
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card stat-card stat-total text-center p-3">
                <div class="fs-2 fw-bold"><?php echo $counts['total_copies']; ?></div>
                <div class="text-muted small">Total Books <span class="text-secondary">(<?php echo $counts['titles']; ?> title<?php echo $counts['titles'] == 1 ? '' : 's'; ?>)</span></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card stat-card stat-borrowed text-center p-3">
                <div class="fs-2 fw-bold"><?php echo $counts['borrowed_copies']; ?></div>
                <div class="text-muted small">Currently Borrowed</div>
            </div>
        </div>
    </div>

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
            <table class="table table-hover mb-0 table-books">
                <colgroup>
                    <col style="width: 11%">
                    <col style="width: 32%">
                    <col style="width: 14%">
                    <col style="width: 8%">
                    <col style="width: 25%">
                    <col style="width: 10%">
                </colgroup>
                <thead>
                    <tr>
                        <th>Serial No.</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th class="col-center">Copies</th>
                        <th class="col-center">Availability</th>
                        <th class="text-actions col-center">Actions</th>
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
                              OR serial_no LIKE '%$search_safe%'
                              ORDER BY serial_no ASC";
                } else {
                    $query = "SELECT * FROM books ORDER BY serial_no ASC";
                }

                $result = mysqli_query($conn, $query);

                if (mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $borrowed_count = $row['total_copies'] - $row['available_copies'];

                        $status_badge = $row['available_copies'] > 0
                            ? '<span class="badge badge-available">Available</span>'
                            : '<span class="badge badge-borrowed">Borrowed</span>';

                        $availability = $status_badge . ' <span class="borrowed-count-text">| Currently Borrowed: ' . $borrowed_count . '</span>';

                        echo "<tr>
                            <td>{$row['serial_no']}</td>
                            <td>{$row['title']}</td>
                            <td>{$row['author']}</td>
                            <td class='col-center'>{$row['available_copies']} / {$row['total_copies']}</td>
                            <td class='col-center'>{$availability}</td>
                            <td class='text-actions col-center'>
                                <a href='edit_book.php?id={$row['id']}' class='btn btn-sm btn-edit'>Edit</a>
                            </td>
                        </tr>";
                    }
                } else {
                    echo "<tr><td colspan='6' class='text-center text-muted py-3'>No books found.</td></tr>";
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
                <div class="mb-3">
                    <label class="form-label">Number of Copies</label>
                    <input type="number" name="total_copies" class="form-control" min="1" value="1" required>
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