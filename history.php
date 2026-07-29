<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include('db.php');

// Get search and filter values
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build query based on filter
$where = [];

if ($search) {
    $search_safe = mysqli_real_escape_string($conn, $search);
    $where[] = "(students.name LIKE '%$search_safe%' 
                OR students.student_no LIKE '%$search_safe%'
                OR books.title LIKE '%$search_safe%'
                OR books.serial_no LIKE '%$search_safe%')";
}

if ($filter == 'borrowed') {
    $where[] = "borrowing.return_date IS NULL";
} else if ($filter == 'returned') {
    $where[] = "borrowing.return_date IS NOT NULL";
}

$where_clause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$query = "
    SELECT 
        borrowing.id,
        borrowing.borrow_date,
        borrowing.return_date,
        students.name as student_name,
        students.student_no,
        books.title as book_title,
        books.serial_no
    FROM borrowing
    JOIN students ON borrowing.student_id = students.id
    JOIN books ON borrowing.book_id = books.id
    $where_clause
    ORDER BY borrowing.borrow_date DESC, borrowing.id DESC
";

$result = mysqli_query($conn, $query);

// Group transactions by borrow date
$grouped = [];
while ($row = mysqli_fetch_assoc($result)) {
    $date = $row['borrow_date'];
    $grouped[$date][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History</title>
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
    <h4 class="mb-3">Transaction History</h4>

    <!-- Search and Filter -->
    <form method="GET" class="row g-2 mb-4">
        <div class="col-md-6">
            <input 
                type="text" 
                name="search" 
                class="form-control" 
                placeholder="Search by student name, ID, book title, or serial no..."
                value="<?php echo htmlspecialchars($search); ?>"
            >
        </div>
        <div class="col-md-3">
            <select name="filter" class="form-select">
                <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>All Transactions</option>
                <option value="borrowed" <?php echo $filter == 'borrowed' ? 'selected' : ''; ?>>Still Borrowed</option>
                <option value="returned" <?php echo $filter == 'returned' ? 'selected' : ''; ?>>Returned</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Search</button>
        </div>
        <div class="col-md-1">
            <a href="history.php" class="btn btn-outline-secondary w-100">Clear</a>
        </div>
    </form>

    <!-- Transaction Summary -->
    <?php
    $total_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM borrowing");
    $total_row = mysqli_fetch_assoc($total_query);

    $borrowed_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM borrowing WHERE return_date IS NULL");
    $borrowed_row = mysqli_fetch_assoc($borrowed_query);

    $returned_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM borrowing WHERE return_date IS NOT NULL");
    $returned_row = mysqli_fetch_assoc($returned_query);
    ?>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card text-center p-3">
                <div class="fs-2 fw-bold"><?php echo $total_row['total']; ?></div>
                <div class="text-muted small">Total Transactions</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center p-3 border-danger">
                <div class="fs-2 fw-bold text-danger"><?php echo $borrowed_row['total']; ?></div>
                <div class="text-muted small">Still Borrowed</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center p-3 border-success">
                <div class="fs-2 fw-bold text-success"><?php echo $returned_row['total']; ?></div>
                <div class="text-muted small">Returned</div>
            </div>
        </div>
    </div>

    <!-- Grouped Transactions -->
    <?php if (empty($grouped)): ?>
        <div class="alert alert-secondary text-center">No transactions found.</div>
    <?php else: ?>
        <?php foreach ($grouped as $date => $transactions): ?>
            <!-- Date Group Header -->
            <div class="d-flex align-items-center gap-3 mb-2 mt-4">
                <span class="fw-bold text-dark fs-6">
                    📅 <?php echo date('F d, Y', strtotime($date)); ?>
                </span>
                <span class="badge bg-secondary"><?php echo count($transactions); ?> transaction(s)</span>
            </div>

            <div class="card mb-2">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Book</th>
                                <th>Serial No.</th>
                                <th>Date Borrowed</th>
                                <th>Date Returned</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($t['student_name']); ?>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($t['student_no']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($t['book_title']); ?></td>
                                <td><?php echo htmlspecialchars($t['serial_no']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($t['borrow_date'])); ?></td>
                                <td>
                                    <?php echo $t['return_date'] 
                                        ? date('M d, Y', strtotime($t['return_date'])) 
                                        : '<span class="text-muted">—</span>'; ?>
                                </td>
                                <td>
                                    <?php if ($t['return_date']): ?>
                                        <span class="badge bg-success">Returned</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Still Borrowed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

</body>
</html>