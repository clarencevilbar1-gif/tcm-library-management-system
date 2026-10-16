<?php 
include('auth.php');
ini_set('display_errors', 1);
error_reporting(E_ALL);
include('db.php');
include('book_functions.php');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$result = mysqli_query($conn, "SELECT * FROM books WHERE id = $id");
$book = mysqli_fetch_assoc($result);
if (!$book) {
    header('Location: books.php');
    exit();
}

// Copies currently out (based on real borrowing records)
$out_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM borrowing WHERE book_id = $id AND return_date IS NULL"));
$copies_out = (int)$out_row['total'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    list($d, $errors) = read_book_form($_POST);
    $total_copies = intval(isset($_POST['total_copies']) ? $_POST['total_copies'] : 1);

    if ($total_copies < 1) {
        $errors[] = 'Number of copies must be at least 1.';
    } elseif ($total_copies < $copies_out) {
        $errors[] = "Cannot set total copies below $copies_out — that many copies are currently borrowed.";
    }

    if (!$errors && acc_no_exists($conn, $d['acc_nos'], $id)) {
        $errors[] = 'Acc. No(s). "' . $d['acc_nos'] . '" is already used by another book.';
    }

    if (!$errors) {
        $available_copies = $total_copies - $copies_out;
        $is_available = $available_copies > 0 ? 1 : 0;

        $stmt = mysqli_prepare($conn, "UPDATE books SET acc_nos=?, class_no=?, title=?, author=?, edition=?, volume=?,
                                       pages=?, fund=?, publication=?, pub_year=?,
                                       total_copies=?, available_copies=?, is_available=? WHERE id=?");
        $acc = $d['acc_nos']; $cls = $d['class_no']; $title = $d['title']; $author = $d['author'];
        $edition = $d['edition']; $volume = $d['volume']; $pages = $d['pages']; $fund = $d['fund'];
        $publication = $d['publication']; $year = $d['pub_year'];
        mysqli_stmt_bind_param($stmt, 'sssssssssiiiii', $acc, $cls, $title, $author, $edition, $volume, $pages,
                               $fund, $publication, $year, $total_copies, $available_copies, $is_available, $id);
        if (mysqli_stmt_execute($stmt)) {
            header('Location: books.php?msg=updated');
            exit();
        }
        $errors[] = 'The changes could not be saved. Please try again.';
    }

    // keep what was typed if something was wrong
    foreach ($d as $k => $v) { $book[$k] = $v; }
    $book['total_copies'] = $total_copies;
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

<?php $nav_mode = 'back'; $nav_back_url = 'books.php'; $nav_back_label = 'Back to Inventory'; include('navbar.php'); ?>

<div class="container mt-4 mb-5" style="max-width: 820px;">
    <h4 class="page-heading mb-4"><i class="bi bi-pencil-square me-2"></i>Edit Book</h4>
    <div class="card card-tcm p-4">
        <?php if ($errors): ?>
            <div class="alert alert-danger py-2">
                <?php foreach ($errors as $err): ?>
                    <div><?php echo h($err); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Acc. No(s). <span class="text-danger">*</span></label>
                    <input type="text" name="acc_nos" class="form-control" value="<?php echo h($book['acc_nos']); ?>" required maxlength="255">
                    <div class="form-text">One number (1847), or several for copies (1840–1842)</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Class</label>
                    <input type="text" name="class_no" class="form-control" value="<?php echo h($book['class_no']); ?>" maxlength="100">
                    <div class="form-text">Call number, for example 345/R456</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" value="<?php echo h($book['title']); ?>" required maxlength="500">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Author</label>
                    <input type="text" name="author" class="form-control" value="<?php echo h($book['author']); ?>" maxlength="255">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Edition</label>
                    <input type="text" name="edition" class="form-control" value="<?php echo h($book['edition']); ?>" maxlength="100">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Volume</label>
                    <input type="text" name="volume" class="form-control" value="<?php echo h($book['volume']); ?>" maxlength="100">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Pages</label>
                    <input type="text" name="pages" class="form-control" value="<?php echo h($book['pages']); ?>" maxlength="50">
                    <div class="form-text">Numbers or text, for example 706 or xvi,193</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Fund</label>
                    <input type="text" name="fund" class="form-control" value="<?php echo h($book['fund']); ?>" maxlength="150">
                    <div class="form-text">For example School fund, or Donated/name</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Publication</label>
                    <input type="text" name="publication" class="form-control" value="<?php echo h($book['publication']); ?>" maxlength="255">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Year</label>
                    <input type="number" name="pub_year" class="form-control" value="<?php echo h($book['pub_year']); ?>" min="1000" max="<?php echo date('Y') + 1; ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Total Copies <span class="text-danger">*</span></label>
                    <input type="number" name="total_copies" class="form-control" min="<?php echo max(1, $copies_out); ?>" value="<?php echo (int)$book['total_copies']; ?>" required>
                    <div class="form-text">Currently borrowed: <?php echo $copies_out; ?> &nbsp;|&nbsp; Available now: <?php echo (int)$book['total_copies'] - $copies_out; ?></div>
                </div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-tcm-gold w-100">Save Changes</button>
                <a href="books.php" class="btn btn-outline-secondary w-100">Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>