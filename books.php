<?php 
include('auth.php');
include('db.php'); 
include('book_functions.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/header.css">
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

        /* The table follows the College of Law's checklist columns. It is wide, so it scrolls
           sideways inside its card on small screens instead of squeezing the columns. */
        .card-table .card-body {
            overflow-x: auto;
        }

        .table-books {
            width: 100%;
            min-width: 1100px;
            table-layout: auto;
            margin-bottom: 0;
        }

        .table-books thead th {
            padding: 0.8rem 0.55rem;
            font-size: 0.74rem;
            white-space: nowrap;
        }

        .table-books .col-avail .borrowed-count-text {
            display: block;
            margin-top: 3px;
            margin-left: 0;
            font-size: 0.74rem;
            white-space: normal;
        }

        .table-books tbody td {
            padding: 0.65rem 0.55rem;
            font-size: 0.88rem;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        /* Header alignment follows the data underneath:
           text columns are left-aligned, short/status columns (Copies, Availability, Actions) are centered */
        .table.table-books thead th {
            text-align: left;
        }

        .table.table-books th.col-center,
        .table.table-books td.col-center {
            text-align: center;
        }

        .table-books .col-accno,
        .table-books .col-class,
        .table-books .col-pages,
        .table-books .col-year,
        .table-books .col-copies {
            white-space: nowrap;
            word-break: normal;
        }

        .table-books .col-accno { min-width: 85px; }
        .table-books .col-class { min-width: 100px; }
        .table-books .col-title { min-width: 190px; width: 24%; }
        .table-books .col-author { min-width: 125px; }
        .table-books .col-edition { min-width: 70px; }
        .table-books .col-volume { min-width: 65px; }
        .table-books .col-pages { min-width: 65px; }
        .table-books .col-fund { min-width: 95px; }
        .table-books .col-publication { min-width: 105px; }
        .table-books .col-year { min-width: 55px; }
        .table-books .col-copies { min-width: 65px; }
        .table-books .col-avail { min-width: 110px; }
        .table-books .btn-edit { white-space: nowrap; }
        .table-books .col-actions { min-width: 70px; white-space: nowrap; }

        .table-books .badge,
        .table-books .borrowed-count-text {
            display: inline-block;
            white-space: nowrap;
        }

        /* Columns hidden with the "Columns" button */
        .table-books.hide-class .col-class,
        .table-books.hide-author .col-author,
        .table-books.hide-edition .col-edition,
        .table-books.hide-volume .col-volume,
        .table-books.hide-pages .col-pages,
        .table-books.hide-fund .col-fund,
        .table-books.hide-publication .col-publication,
        .table-books.hide-year .col-year,
        .table-books.hide-copies .col-copies,
        .table-books.hide-avail .col-avail {
            display: none;
        }

        .columns-menu {
            min-width: 230px;
        }

        .add-book-hint {
            font-size: 0.78rem;
            color: #6c757d;
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

<?php $nav_mode = 'page'; include('navbar.php'); ?>

<?php
// ---- Page data ----
$counts = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS titles, COALESCE(SUM(total_copies),0) AS total_copies, COALESCE(SUM(total_copies - available_copies),0) AS borrowed_copies FROM books"));

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = '';
if ($search !== '') {
    $s = mysqli_real_escape_string($conn, $search);
    $where = "WHERE acc_nos LIKE '%$s%' OR class_no LIKE '%$s%' OR title LIKE '%$s%'
                 OR author LIKE '%$s%' OR publication LIKE '%$s%'";
}
// Acc. No(s). are sorted as numbers (1000 comes after 999), then as text
$result = mysqli_query($conn, "SELECT * FROM books $where ORDER BY (acc_nos NOT REGEXP '^[0-9]') ASC, CAST(acc_nos AS UNSIGNED) ASC, acc_nos ASC, id ASC");
$shown = mysqli_num_rows($result);

// A failed "Add Book" brings the form back with what was typed
$flash = isset($_SESSION['book_form']) ? $_SESSION['book_form'] : null;
unset($_SESSION['book_form']);
$old = ($flash && isset($flash['old'])) ? $flash['old'] : [];
$old_val = function ($key, $default = '') use ($old) {
    return isset($old[$key]) ? $old[$key] : $default;
};
$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
?>

<div class="container-fluid px-4 mt-4 mb-5">
    <h4 class="page-heading mb-3">Book Inventory</h4>

    <?php if ($msg === 'added'): ?>
        <div class="alert alert-success alert-dismissible fade show">Book added successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php elseif ($msg === 'updated'): ?>
        <div class="alert alert-success alert-dismissible fade show">Book updated successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Live Counters -->
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
                placeholder="Search by title, author, Acc. No., class, or publication..."
                value="<?php echo h($search); ?>"
            >
            <button type="submit" class="btn btn-tcm-search"><i class="bi bi-search"></i></button>
        </div>
        <a href="books.php" class="btn btn-outline-secondary">Clear</a>
    </form>

    <!-- Toolbar -->
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <button class="btn btn-tcm-add" data-bs-toggle="modal" data-bs-target="#addBookModal">
            <i class="bi bi-journal-plus me-1"></i>Add New Book
        </button>

        <div class="dropdown">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                <i class="bi bi-layout-three-columns me-1"></i>Columns
            </button>
            <div class="dropdown-menu columns-menu p-3">
                <div class="small text-muted mb-2">Choose which columns to show</div>
                <?php
                $toggles = [
                    'class' => 'Class', 'author' => 'Author', 'edition' => 'Edition', 'volume' => 'Volume',
                    'pages' => 'Pages', 'fund' => 'Fund', 'publication' => 'Publication', 'year' => 'Year',
                    'copies' => 'Copies', 'avail' => 'Availability'
                ];
                ?>
                <div class="form-check"><input class="form-check-input" type="checkbox" checked disabled id="col-accno"><label class="form-check-label text-muted" for="col-accno">Acc. No(s).</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" checked disabled id="col-title"><label class="form-check-label text-muted" for="col-title">Title</label></div>
                <?php foreach ($toggles as $key => $label): ?>
                <div class="form-check">
                    <input class="form-check-input col-toggle" type="checkbox" value="<?php echo $key; ?>" id="toggle-<?php echo $key; ?>" checked>
                    <label class="form-check-label" for="toggle-<?php echo $key; ?>"><?php echo $label; ?></label>
                </div>
                <?php endforeach; ?>
                <button type="button" class="btn btn-link btn-sm p-0 mt-2" id="cols-reset">Show all columns</button>
            </div>
        </div>

        <span class="ms-auto text-muted small">
            <?php if ($search !== ''): ?>
                <?php echo $shown; ?> result<?php echo $shown == 1 ? '' : 's'; ?> for "<?php echo h($search); ?>"
            <?php else: ?>
                Showing <?php echo $shown; ?> title<?php echo $shown == 1 ? '' : 's'; ?>
            <?php endif; ?>
        </span>
    </div>

    <!-- Books Table -->
    <div class="card card-table">
        <div class="card-body p-0">
            <table class="table table-hover mb-0 table-books" id="books-table">
                <thead>
                    <tr>
                        <th class="col-accno">Acc. No(s).</th>
                        <th class="col-class">Class</th>
                        <th class="col-title">Title</th>
                        <th class="col-author">Author</th>
                        <th class="col-edition">Edition</th>
                        <th class="col-volume">Volume</th>
                        <th class="col-pages">Pages</th>
                        <th class="col-fund">Fund</th>
                        <th class="col-publication">Publication</th>
                        <th class="col-year">Year</th>
                        <th class="col-copies col-center">Copies</th>
                        <th class="col-avail col-center">Availability</th>
                        <th class="col-actions text-actions col-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($shown > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)):
                        $borrowed_count = $row['total_copies'] - $row['available_copies'];
                        $status_badge = $row['available_copies'] > 0
                            ? '<span class="badge badge-available">Available</span>'
                            : '<span class="badge badge-borrowed">Borrowed</span>';
                    ?>
                        <tr>
                            <td class="col-accno"><?php echo h($row['acc_nos']); ?></td>
                            <td class="col-class"><?php echo h($row['class_no']); ?></td>
                            <td class="col-title"><?php echo h($row['title']); ?></td>
                            <td class="col-author"><?php echo h($row['author']); ?></td>
                            <td class="col-edition"><?php echo h($row['edition']); ?></td>
                            <td class="col-volume"><?php echo h($row['volume']); ?></td>
                            <td class="col-pages"><?php echo h($row['pages']); ?></td>
                            <td class="col-fund"><?php echo h($row['fund']); ?></td>
                            <td class="col-publication"><?php echo h($row['publication']); ?></td>
                            <td class="col-year"><?php echo h($row['pub_year']); ?></td>
                            <td class="col-copies col-center"><?php echo (int)$row['available_copies'] . ' / ' . (int)$row['total_copies']; ?></td>
                            <td class="col-avail col-center"><?php echo $status_badge; ?><span class="borrowed-count-text">Currently Borrowed: <?php echo (int)$borrowed_count; ?></span></td>
                            <td class="col-actions text-actions col-center">
                                <a href="edit_book.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-edit">Edit</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="13" class="text-center text-muted py-3">No books found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Book Modal -->
<div class="modal fade" id="addBookModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Book</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="add_book.php">
            <div class="modal-body">
                <?php if ($flash && !empty($flash['errors'])): ?>
                    <div class="alert alert-danger py-2">
                        <?php foreach ($flash['errors'] as $err): ?>
                            <div><?php echo h($err); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Acc. No(s). <span class="text-danger">*</span></label>
                        <input type="text" name="acc_nos" class="form-control" value="<?php echo h($old_val('acc_nos')); ?>" required maxlength="255">
                        <div class="add-book-hint">One number (1847), or several for copies (1840–1842)</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Class</label>
                        <input type="text" name="class_no" class="form-control" value="<?php echo h($old_val('class_no')); ?>" maxlength="100">
                        <div class="add-book-hint">Call number, for example 345/R456</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" value="<?php echo h($old_val('title')); ?>" required maxlength="500">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Author</label>
                        <input type="text" name="author" class="form-control" value="<?php echo h($old_val('author')); ?>" maxlength="255">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Edition</label>
                        <input type="text" name="edition" class="form-control" value="<?php echo h($old_val('edition')); ?>" maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Volume</label>
                        <input type="text" name="volume" class="form-control" value="<?php echo h($old_val('volume')); ?>" maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Pages</label>
                        <input type="text" name="pages" class="form-control" value="<?php echo h($old_val('pages')); ?>" maxlength="50">
                        <div class="add-book-hint">Numbers or text, for example 706 or xvi,193</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Fund</label>
                        <input type="text" name="fund" class="form-control" value="<?php echo h($old_val('fund')); ?>" maxlength="150">
                        <div class="add-book-hint">For example School fund, or Donated/name</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Publication</label>
                        <input type="text" name="publication" class="form-control" value="<?php echo h($old_val('publication')); ?>" maxlength="255">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Year</label>
                        <input type="number" name="pub_year" class="form-control" value="<?php echo h($old_val('pub_year')); ?>" min="1000" max="<?php echo date('Y') + 1; ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Number of Copies <span class="text-danger">*</span></label>
                        <input type="number" name="total_copies" class="form-control" min="1" value="<?php echo (int)$old_val('total_copies', 1); ?>" required>
                    </div>
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
<script>
// "Columns" button: show or hide columns; the choice is remembered in this browser
(function () {
    var KEY = 'tcmBookColumns';
    var table = document.getElementById('books-table');
    var boxes = document.querySelectorAll('.col-toggle');

    function apply(hidden) {
        boxes.forEach(function (b) {
            var hide = hidden.indexOf(b.value) !== -1;
            b.checked = !hide;
            table.classList.toggle('hide-' + b.value, hide);
        });
    }
    function save(hidden) {
        try { localStorage.setItem(KEY, JSON.stringify(hidden)); } catch (e) {}
    }

    var hidden = [];
    try { hidden = JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { hidden = []; }
    if (!Array.isArray(hidden)) hidden = [];
    apply(hidden);

    boxes.forEach(function (b) {
        b.addEventListener('change', function () {
            var h = [];
            boxes.forEach(function (x) { if (!x.checked) h.push(x.value); });
            apply(h);
            save(h);
        });
    });

    document.getElementById('cols-reset').addEventListener('click', function () {
        apply([]);
        save([]);
    });
})();

var addModalEl = document.getElementById('addBookModal');
addModalEl.addEventListener('shown.bs.modal', function () {
    addModalEl.querySelector('input[name="acc_nos"]').focus();
});
<?php if ($flash): ?>
// the last "Add Book" attempt had a problem: reopen the form so nothing has to be retyped
new bootstrap.Modal(addModalEl).show();
<?php endif; ?>
</script>
</body>
</html>