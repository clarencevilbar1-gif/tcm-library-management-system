<?php
// Sends one backup file from the backup folder to the browser (staff only).
require_once __DIR__ . '/backup_functions.php';
session_start();
if (empty($_SESSION['staff_logged_in'])) {
    header('Location: login.php');
    exit();
}
session_write_close();

$name = isset($_GET['file']) ? $_GET['file'] : '';
if (!preg_match('/^TCM-Library-Backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}_[a-z-]+\.zip$/', $name)) {
    http_response_code(400);
    exit('Invalid file name.');
}
$s = bk_settings_load();
$path = rtrim($s['local_dir'], '/\\') . DIRECTORY_SEPARATOR . $name;
if (!is_file($path)) {
    http_response_code(404);
    exit('That backup file was not found.');
}
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-store');
readfile($path);
