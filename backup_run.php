<?php
// Small JSON endpoint used by the dashboard tile and the logout / "Back up now" window.
//   GET  ?action=status            -> current backup status (+ the security token for the next calls)
//   POST action=run   target=local|email|both  [to=address]   type=manual|logout
//   POST action=auto                                          -> only runs if an automatic backup is due
require_once __DIR__ . '/backup_functions.php';
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function bk_json($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

if (empty($_SESSION['staff_logged_in'])) {
    bk_json(['ok' => false, 'login' => true, 'error' => 'Your session has ended. Please log in again.'], 401);
}
if (empty($_SESSION['bk_csrf'])) $_SESSION['bk_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['bk_csrf'];
session_write_close();      // don't block other pages while a backup is running

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

if ($action === 'status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $st = bk_status();
    $st['ok'] = true;
    $st['csrf'] = $csrf;
    bk_json($st);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') bk_json(['ok' => false, 'error' => 'Invalid request.'], 405);
$sent = isset($_SERVER['HTTP_X_BK_CSRF']) ? $_SERVER['HTTP_X_BK_CSRF'] : '';
if (!hash_equals($csrf, $sent)) bk_json(['ok' => false, 'error' => 'The page is out of date. Please reload it and try again.'], 403);

@set_time_limit(300);
$s = bk_settings_load();

if ($action === 'auto') {
    $st = bk_status();
    if (!$st['auto_due']) bk_json(['ok' => true, 'skipped' => true, 'status' => $st]);
    $res = bk_create_backup('auto', ['keep_local' => true, 'email' => $st['auto_email']]);
    bk_json(bk_public_result($res));
}

if ($action === 'run') {
    $target = isset($_POST['target']) ? $_POST['target'] : 'local';
    $type = (isset($_POST['type']) && $_POST['type'] === 'logout') ? 'logout' : 'manual';
    if (!in_array($target, ['local', 'email', 'both'], true)) bk_json(['ok' => false, 'error' => 'Choose where to save the backup.'], 400);

    $wantMail = ($target !== 'local');
    $to = trim(isset($_POST['to']) ? $_POST['to'] : '');
    if ($wantMail) {
        if (!bk_email_configured($s)) bk_json(['ok' => false, 'error' => 'E-mail is not set up yet. Open the Backup page and fill in the Gmail details first.'], 400);
        if ($to === '') $to = $s['mail_to'];
        if ($to === '') bk_json(['ok' => false, 'error' => 'Type the e-mail address that should receive the backup.'], 400);
        list($rcpt, $bad) = bk_parse_recipients($to);
        if ($bad !== null) bk_json(['ok' => false, 'error' => 'This is not a valid e-mail address: ' . $bad], 400);
    }

    $s['last_target'] = $target;
    bk_settings_save($s);

    $res = bk_create_backup($type, ['keep_local' => ($target !== 'email'), 'email' => $wantMail, 'email_to' => $to]);
    bk_json(bk_public_result($res));
}

bk_json(['ok' => false, 'error' => 'Unknown request.'], 400);

// What the browser needs to know about a finished backup.
function bk_public_result($res) {
    $out = [
        'ok'     => (bool)$res['ok'],
        'error'  => $res['error'],
        'file'   => $res['file'],
        'size'   => $res['size'] ? bk_human_size($res['size']) : '',
        'local'  => $res['local'],
        'mirror' => $res['mirror'],
        'mail'   => $res['mail'],
        'status' => bk_status(),
    ];
    return $out;
}
