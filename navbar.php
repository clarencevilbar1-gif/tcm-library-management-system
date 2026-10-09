<?php
// Shared header for every page after login.
// Set these BEFORE including this file:
//   $nav_mode = 'home'  -> dashboard: "Logged in as ..." + Logout
//   $nav_mode = 'page'  -> (default) "Back to Menu" + Logout
//   $nav_mode = 'back'  -> a single back button: also set $nav_back_url and $nav_back_label
$nav_mode = isset($nav_mode) ? $nav_mode : 'page';
$nav_back_url = isset($nav_back_url) ? $nav_back_url : 'index.php';
$nav_back_label = isset($nav_back_label) ? $nav_back_label : 'Back to Menu';
?>
<nav class="navbar navbar-tcm d-flex justify-content-between">
    <div class="navbar-brand">
        <div class="brand-logos">
            <img src="assets/images/tcm_logo.png" alt="The College of Maasin Seal" class="brand-logo brand-seal">
            <img src="assets/images/col_logo.png" alt="College of Law Logo" class="brand-logo">
        </div>
        <div class="brand-text">
            <span class="brand-school">The College of Maasin</span>
            <span class="brand-system">College of Law Library Management System</span>
        </div>
    </div>
    <?php if ($nav_mode === 'back'): ?>
    <a href="<?php echo htmlspecialchars($nav_back_url); ?>" class="btn btn-outline-light btn-sm">
        <i class="bi bi-arrow-left me-1"></i><?php echo htmlspecialchars($nav_back_label); ?>
    </a>
    <?php elseif ($nav_mode === 'home'): ?>
    <div class="d-flex align-items-center gap-3">
        <span class="text-white-50 small">
            Logged in as <strong class="text-white"><?php echo htmlspecialchars(isset($_SESSION['staff_username']) ? $_SESSION['staff_username'] : ''); ?></strong>
        </span>
        <a href="logout.php" class="btn btn-outline-light btn-sm">
            <i class="bi bi-box-arrow-right me-1"></i>Logout
        </a>
    </div>
    <?php else: ?>
    <div class="d-flex align-items-center gap-3">
        <a href="index.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Menu</a>
        <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
    </div>
    <?php endif; ?>
</nav>