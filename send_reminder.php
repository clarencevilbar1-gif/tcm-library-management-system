<?php
// Overdue reminder e-mails (answers with JSON).
//   POST action=preview    student_id, note   -> who gets it and the exact text
//   POST action=send       student_id, note   -> sends one reminder
//   POST action=remind_all                    -> one reminder to every overdue student that has an e-mail
//   POST action=count                         -> how many "remind all" would send
$auth_json = true;
include('auth_quiet.php');
ini_set('display_errors', '0');
include('db.php');
include('reminder_functions.php');
header('Content-Type: application/json');

function rm_out($arr) { echo json_encode($arr); exit(); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); rm_out(['ok' => false, 'error' => 'Wrong request.']); }

$action = isset($_POST['action']) ? $_POST['action'] : '';
$staff = isset($_SESSION['staff_username']) ? (string)$_SESSION['staff_username'] : 'staff';
$note = isset($_POST['note']) ? mb_substr((string)$_POST['note'], 0, 300) : '';
$sid = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
rm_ensure($conn);

$cfg = bk_settings_load();
$configured = bk_email_configured($cfg);
$cfg_problem = !$configured ? 'E-mail is not set up yet. On the Backup page, fill in the Gmail address and App Password first.' : (!bk_mailer_available() ? 'The e-mail component is not installed on this computer. Please call the system developer.' : '');

if ($action === 'preview') {
    $all = rm_overdue_students($conn, $sid);
    if (!isset($all[$sid])) rm_out(['ok' => false, 'error' => 'This student has no overdue books any more. Reload the page.']);
    $stu = $all[$sid];
    list($email, $st) = st_email($stu['email']);
    if ($st !== 'ok') rm_out(['ok' => false, 'error' => 'This student has no e-mail address on file.']);
    list($subject, $body) = rm_build_message($stu, $note);
    rm_out(['ok' => true, 'to' => $email, 'name' => $stu['name'], 'from' => $configured ? $cfg['smtp_user'] : '', 'subject' => $subject, 'body' => $body, 'problem' => $cfg_problem]);
}

if ($action === 'send') {
    if ($cfg_problem !== '') rm_out(['ok' => false, 'error' => $cfg_problem]);
    list($ok, $msg) = rm_send($conn, $sid, $note, $staff);
    rm_out(['ok' => $ok, 'message' => $msg, 'error' => $ok ? '' : $msg, 'when' => rm_when(date('Y-m-d H:i:s'))]);
}

if ($action === 'count' || $action === 'remind_all') {
    $last = rm_last_sent($conn);
    $targets = []; $no_email = 0; $recent = 0;
    foreach (rm_overdue_students($conn) as $id => $stu) {
        list($email, $st) = st_email($stu['email']);
        if ($st !== 'ok') { $no_email++; continue; }
        if (isset($last[$id]) && time() - strtotime($last[$id]) < RM_REPEAT_HOURS * 3600) { $recent++; continue; }
        $targets[] = $id;
    }
    if ($action === 'count') rm_out(['ok' => true, 'to_send' => count($targets), 'no_email' => $no_email, 'recent' => $recent, 'problem' => $cfg_problem]);

    if ($cfg_problem !== '') rm_out(['ok' => false, 'error' => $cfg_problem]);
    @set_time_limit(600);
    $sent = 0; $failed = 0; $stopped = ''; $errors = [];
    foreach ($targets as $id) {
        list($ok, $msg) = rm_send($conn, $id, '', $staff);
        if ($ok) { $sent++; continue; }
        $failed++;
        if (count($errors) < 3) $errors[] = $msg;
        // a problem with the Gmail account or the internet will repeat for everybody: stop instead of waiting for each one
        if (stripos($msg, 'Gmail did not accept') !== false || stripos($msg, 'Could not reach') !== false) { $stopped = $msg; break; }
    }
    rm_out(['ok' => $failed === 0, 'sent' => $sent, 'failed' => $failed, 'no_email' => $no_email, 'recent' => $recent, 'stopped' => $stopped, 'errors' => $errors,
            'error' => $failed ? ($stopped !== '' ? $stopped : 'Some reminders could not be sent: ' . implode(' / ', $errors)) : '']);
}

rm_out(['ok' => false, 'error' => 'Unknown request.']);
