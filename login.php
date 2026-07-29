<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (isset($_SESSION['staff_logged_in'])) {
    header('Location: index.php');
    exit();
}

include('db.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = MD5($_POST['password']);

    $result = mysqli_query($conn, "SELECT * FROM staff WHERE username = '$username' AND password = '$password'");

    if (mysqli_num_rows($result) > 0) {
        $staff = mysqli_fetch_assoc($result);
        $_SESSION['staff_logged_in'] = true;
        $_SESSION['staff_username'] = $staff['username'];
        header('Location: index.php');
        exit();
    } else {
        $error = 'Incorrect username or password. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Law Library — Staff Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
    </style>
</head>
<body>

<div class="card shadow-sm" style="width: 100%; max-width: 400px;">
    <div class="card-body p-4">

        <div class="text-center mb-4">
            <div class="fs-2 mb-1">⚖️</div>
            <h5 class="fw-bold mb-0">Law Department Library</h5>
            <p class="text-muted small">Staff Login</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-dark w-100">Login</button>
        </form>

    </div>
</div>

</body>
</html>