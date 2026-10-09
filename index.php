<?php 
include('auth.php');
include('db.php');

// ---- Dashboard statistics ----
$today = date('Y-m-d');

// All books in the inventory (every copy counts)
$inv = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_copies), 0) AS total FROM books"));
$total_books = (int)$inv['total'];

// Books currently borrowed (not yet returned) and how many of those are past due
$loan = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS borrowed,
            COALESCE(SUM(due_date IS NOT NULL AND due_date < '$today'), 0) AS overdue
     FROM borrowing WHERE return_date IS NULL"));
$borrowed_books = (int)$loan['borrowed'];
$overdue_books = (int)$loan['overdue'];

// Percentages: borrowed vs. whole inventory, overdue vs. what is currently borrowed.
// A non-zero amount always shows at least 1% so the bar never looks empty by mistake.
function stat_percent($part, $whole) {
    if ($whole <= 0 || $part <= 0) return 0;
    return (int)min(100, max(1, round($part / $whole * 100)));
}
$borrowed_pct = stat_percent($borrowed_books, $total_books);
$overdue_pct = stat_percent($overdue_books, $borrowed_books);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Law Library System</title>
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

        .page-subheading {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .menu-tile {
            background-color: #fff;
            border: 1px solid #e5dfee;
            border-radius: 14px;
            padding: 1.6rem 1.25rem;
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
            margin: 0 auto 0.85rem;
            font-size: 1.5rem;
            color: var(--tcm-purple);
        }

        .menu-tile .tile-label {
            font-weight: 600;
            color: #212529;
            font-size: 1.02rem;
        }

        /* ---- Progress bars (dashboard overview) ---- */
        .stat-card {
            background-color: #fff;
            border: 1px solid #e5dfee;
            border-top: 4px solid var(--tcm-purple);
            border-radius: 14px;
            padding: 1.1rem 1.4rem 1rem;
            box-shadow: 0 2px 8px rgba(75, 46, 131, 0.06);
            height: 100%;
        }

        .stat-card.stat-overdue {
            border-top-color: #c0392b;
        }

        .stat-card.stat-overdue.is-clear {
            border-top-color: #2e8b57;
        }

        .stat-top {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.9rem;
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background-color: rgba(75, 46, 131, 0.1);
            color: var(--tcm-purple);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .stat-overdue .stat-icon {
            background-color: rgba(192, 57, 43, 0.1);
            color: #c0392b;
        }

        .stat-overdue.is-clear .stat-icon {
            background-color: rgba(46, 139, 87, 0.12);
            color: #2e8b57;
        }

        .stat-title {
            font-weight: 600;
            color: #212529;
            flex-grow: 1;
        }

        .stat-count {
            font-size: 1.9rem;
            font-weight: 700;
            line-height: 1;
            color: var(--tcm-purple-dark);
        }

        .stat-overdue .stat-count {
            color: #c0392b;
        }

        .stat-overdue.is-clear .stat-count {
            color: #2e8b57;
        }

        .stat-of {
            font-size: 1rem;
            font-weight: 500;
            color: #8a8397;
        }

        .stat-bar-row {
            display: flex;
            align-items: center;
            gap: 0.9rem;
        }

        .stat-progress {
            flex-grow: 1;
            height: 18px;
            border-radius: 999px;
            background-color: #eee7f5;
            overflow: hidden;
            box-shadow: inset 0 1px 3px rgba(53, 32, 94, 0.15);
        }

        .stat-fill {
            height: 100%;
            border-radius: 999px;
            background-color: var(--tcm-purple);
            background-image:
                linear-gradient(45deg, rgba(255,255,255,0.18) 25%, transparent 25%, transparent 50%,
                                       rgba(255,255,255,0.18) 50%, rgba(255,255,255,0.18) 75%, transparent 75%, transparent),
                linear-gradient(90deg, var(--tcm-purple-dark), #7a55c4);
            background-size: 1rem 1rem, 100% 100%;
            transition: width 1.1s cubic-bezier(0.22, 0.8, 0.3, 1);
            animation: stat-stripes 1.2s linear infinite;
        }

        .stat-overdue .stat-fill {
            background-image:
                linear-gradient(45deg, rgba(255,255,255,0.2) 25%, transparent 25%, transparent 50%,
                                       rgba(255,255,255,0.2) 50%, rgba(255,255,255,0.2) 75%, transparent 75%, transparent),
                linear-gradient(90deg, #a93226, #e5533d);
        }

        @keyframes stat-stripes {
            from { background-position: 1rem 0, 0 0; }
            to   { background-position: 0 0, 0 0; }
        }

        .stat-pct {
            min-width: 3.4rem;
            text-align: right;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--tcm-purple-dark);
        }

        .stat-overdue .stat-pct {
            color: #c0392b;
        }

        .stat-overdue.is-clear .stat-pct {
            color: #2e8b57;
        }

        .stat-caption {
            margin-top: 0.6rem;
            font-size: 0.85rem;
            color: #6c757d;
        }

        .stat-overdue.is-clear .stat-caption {
            color: #2e8b57;
            font-weight: 600;
        }

        @media (prefers-reduced-motion: reduce) {
            .stat-fill {
                transition: none;
                animation: none;
            }
        }
    </style>
</head>
<body>

<?php $nav_mode = 'home'; include('navbar.php'); ?>

<div class="container mt-4 mb-5">

    <h4 class="page-heading mb-1">Library Overview</h4>
    <p class="page-subheading mb-3">Current status of the library's books</p>

    <div class="row g-4 mb-4">

        <div class="col-md-6">
            <div class="stat-card stat-borrowed">
                <div class="stat-top">
                    <div class="stat-icon"><i class="bi bi-journal-bookmark-fill"></i></div>
                    <div class="stat-title">Books Borrowed</div>
                    <div class="stat-count">
                        <span class="count-up" data-target="<?php echo $borrowed_books; ?>"><?php echo $borrowed_books; ?></span><span class="stat-of"> / <?php echo $total_books; ?></span>
                    </div>
                </div>
                <div class="stat-bar-row">
                    <div class="progress stat-progress" role="progressbar" aria-label="Books borrowed"
                         aria-valuenow="<?php echo $borrowed_pct; ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="stat-fill" data-width="<?php echo $borrowed_pct; ?>" style="width: <?php echo $borrowed_pct; ?>%"></div>
                    </div>
                    <div class="stat-pct"><span class="count-up" data-target="<?php echo $borrowed_pct; ?>"><?php echo $borrowed_pct; ?></span>%</div>
                </div>
                <div class="stat-caption">
                    <?php echo $borrowed_books; ?> of <?php echo $total_books; ?> books in the library are currently borrowed
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="stat-card stat-overdue<?php echo ($overdue_books == 0) ? ' is-clear' : ''; ?>">
                <div class="stat-top">
                    <div class="stat-icon">
                        <i class="bi <?php echo ($overdue_books == 0) ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?>"></i>
                    </div>
                    <div class="stat-title">Books Overdue</div>
                    <div class="stat-count">
                        <span class="count-up" data-target="<?php echo $overdue_books; ?>"><?php echo $overdue_books; ?></span><span class="stat-of"> / <?php echo $borrowed_books; ?></span>
                    </div>
                </div>
                <div class="stat-bar-row">
                    <div class="progress stat-progress" role="progressbar" aria-label="Books overdue"
                         aria-valuenow="<?php echo $overdue_pct; ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="stat-fill" data-width="<?php echo $overdue_pct; ?>" style="width: <?php echo $overdue_pct; ?>%"></div>
                    </div>
                    <div class="stat-pct"><span class="count-up" data-target="<?php echo $overdue_pct; ?>"><?php echo $overdue_pct; ?></span>%</div>
                </div>
                <div class="stat-caption">
                    <?php
                    if ($borrowed_books == 0) {
                        echo 'No books are currently borrowed';
                    } elseif ($overdue_books == 0) {
                        echo ($borrowed_books == 1)
                            ? 'No overdue books — the 1 borrowed book is within its due date'
                            : 'No overdue books — all ' . $borrowed_books . ' borrowed books are within their due dates';
                    } else {
                        echo $overdue_books . ' of ' . $borrowed_books . ' borrowed ' . ($borrowed_books == 1 ? 'book is' : 'books are') . ' past the due date';
                    }
                    ?>
                </div>
            </div>
        </div>

    </div>

    <h4 class="page-heading mb-1">Main Menu</h4>
    <p class="page-subheading mb-3">Select a module to get started</p>

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

<script>
// Loading-bar effect: bars fill and numbers count up when the dashboard opens.
// The final values are already in the page, so everything is correct even without JavaScript.
document.addEventListener('DOMContentLoaded', function () {
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce) return;

    var fills = document.querySelectorAll('.stat-fill');
    fills.forEach(function (el) {
        el.style.transition = 'none';   // jump to empty instantly (no animation)...
        el.style.width = '0%';
        void el.offsetWidth;
        el.style.transition = '';       // ...then animate up to the real value
    });
    requestAnimationFrame(function () {
        fills.forEach(function (el) { el.style.width = el.getAttribute('data-width') + '%'; });
    });

    var duration = 1100;
    document.querySelectorAll('.count-up').forEach(function (el) {
        var target = parseInt(el.getAttribute('data-target'), 10) || 0;
        if (target === 0) return;
        var start = null;
        el.textContent = '0';
        function step(ts) {
            if (start === null) start = ts;
            var t = Math.min((ts - start) / duration, 1);
            var eased = 1 - Math.pow(1 - t, 3);
            el.textContent = Math.round(target * eased);
            if (t < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    });
});
</script>

</body>
</html>