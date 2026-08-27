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
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
            text-align: center;
        }

        .school-logo {
            width: 110px;
            height: 110px;
            object-fit: contain;
            margin-bottom: 1rem;
        }

        .login-title {
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 0.25rem;
        }

        .login-subtitle {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 1.75rem;
        }

        .login-card {
            background-color: var(--tcm-green);
            border-radius: 16px;
            padding: 2rem 1.75rem;
            box-shadow: 0 20px 45px rgba(27, 94, 69, 0.25);
        }

        .form-label-custom {
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 0.4rem;
            display: block;
            text-align: left;
        }

        .input-group-custom {
            position: relative;
            margin-bottom: 1.25rem;
        }

        .input-group-custom .bi {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--tcm-green);
            opacity: 0.7;
        }

        .input-group-custom input {
            width: 100%;
            padding: 0.65rem 1rem 0.65rem 2.5rem;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            color: var(--tcm-green-dark);
        }

        .input-group-custom input:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(212, 167, 44, 0.4);
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--tcm-green);
            opacity: 0.7;
            border: none;
            background: none;
            padding: 0;
        }

        .btn-signin {
            width: 100%;
            background-color: var(--tcm-gold);
            border: none;
            color: var(--tcm-green-dark);
            font-weight: 700;
            padding: 0.7rem;
            border-radius: 10px;
            margin-top: 0.5rem;
            transition: background-color 0.15s ease;
        }

        .btn-signin:hover {
            background-color: var(--tcm-gold-dark);
            color: var(--tcm-green-dark);
        }

        .login-footer {
            margin-top: 1.5rem;
            font-size: 0.8rem;
            color: var(--tcm-green);
        }
    </style>
</head>
<body>

<div class="login-wrapper">

    <img src="assets/images/tcm_logo.png" alt="TCM Seal" class="school-logo">
    <h4 class="login-title">The College of Law Library</h4>
    <p class="login-subtitle">Sign in to your staff account</p>

    <div class="login-card">

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small mb-3"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <label class="form-label-custom">Username</label>
            <div class="input-group-custom">
                <i class="bi bi-person"></i>
                <input type="text" name="username" placeholder="Enter your username" required autofocus>
            </div>

            <label class="form-label-custom">Password</label>
            <div class="input-group-custom">
                <i class="bi bi-lock"></i>
                <input type="password" name="password" id="passwordField" placeholder="Enter your password" required>
                <button type="button" class="toggle-password" onclick="togglePassword()">
                    <i class="bi bi-eye" id="toggleIcon"></i>
                </button>
            </div>

            <button type="submit" class="btn btn-signin">
                <i class="bi bi-box-arrow-in-right me-1"></i> Login
            </button>
        </form>

    </div>

    <p class="login-footer">Law Department Library &middot; <?php echo date('Y'); ?></p>

</div>

<script>
    function togglePassword() {
        const field = document.getElementById('passwordField');
        const icon = document.getElementById('toggleIcon');
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }
</script>

</body>
</html>