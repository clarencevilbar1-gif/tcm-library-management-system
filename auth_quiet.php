<?php
// Login check for pages that redirect or answer with data (add / edit / deactivate ... / JSON).
// Same rule as auth.php, but prints nothing, so a redirect afterwards still works.
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!isset($_SESSION['staff_logged_in'])) {
    if (isset($auth_json) && $auth_json) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Please log in again.']);
    } else {
        header('Location: login.php');
    }
    exit();
}
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
