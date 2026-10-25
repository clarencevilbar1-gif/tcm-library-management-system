<?php
// =====================================================================
// Overdue reminders: one e-mail per student, sent from the Gmail that is set up on the
// Backup page, to the e-mail saved on the student's record.
// Every send is written to the table reminder_log (created automatically).
// =====================================================================

require_once __DIR__ . '/backup_functions.php';
require_once __DIR__ . '/student_functions.php';

const RM_REPEAT_HOURS = 24;     // "Remind all" skips students reminded within this time

function rm_ensure($conn) {
    static $done = false;
    if ($done) return;
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS reminder_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        sent_at DATETIME NOT NULL,
        sent_to VARCHAR(150) NOT NULL,
        sent_by VARCHAR(100) NULL,
        book_count INT NOT NULL DEFAULT 0,
        ok TINYINT(1) NOT NULL DEFAULT 1,
        error VARCHAR(255) NULL,
        INDEX idx_student (student_id, sent_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
}

// "Dela Cruz, Juan A. Jr."  ->  "Juan A. Jr. Dela Cruz"
function rm_display_name($name) {
    $name = trim((string)$name);
    if (strpos($name, ',') !== false) {
        list($last, $given) = array_map('trim', explode(',', $name, 2));
        if ($given !== '' && $last !== '') return $given . ' ' . $last;
    }
    return $name;
}

// Overdue loans grouped by student: [student_id => ['id','student_no','name','email','books'=>[...]]]
function rm_overdue_students($conn, $only_student = 0) {
    $today = date('Y-m-d');
    $has_email = st_email_ready($conn);
    $sql = "SELECT s.id AS sid, s.student_no, s.name, " . ($has_email ? "s.email" : "NULL") . " AS email,
                   b.title, b.acc_nos, bw.due_date
            FROM borrowing bw
            JOIN students s ON s.id = bw.student_id
            LEFT JOIN books b ON b.id = bw.book_id
            WHERE bw.return_date IS NULL AND bw.due_date IS NOT NULL AND bw.due_date < '$today'"
            . ($only_student ? " AND s.id = " . (int)$only_student : "") . "
            ORDER BY bw.due_date ASC, bw.id ASC";
    $r = mysqli_query($conn, $sql);
    $out = [];
    while ($r && ($row = mysqli_fetch_assoc($r))) {
        $id = (int)$row['sid'];
        if (!isset($out[$id])) $out[$id] = ['id' => $id, 'student_no' => $row['student_no'], 'name' => $row['name'], 'email' => trim((string)$row['email']), 'books' => []];
        $late = (int)(new DateTime($row['due_date']))->diff(new DateTime($today))->days;
        $out[$id]['books'][] = ['title' => $row['title'] !== null ? $row['title'] : '(book removed from the system)', 'acc' => (string)$row['acc_nos'], 'due' => $row['due_date'], 'late' => $late];
    }
    return $out;
}

// Last successful reminder per student: [student_id => datetime string]
function rm_last_sent($conn) {
    rm_ensure($conn);
    $out = [];
    $r = mysqli_query($conn, "SELECT student_id, MAX(sent_at) AS last FROM reminder_log WHERE ok = 1 GROUP BY student_id");
    while ($r && ($row = mysqli_fetch_assoc($r))) $out[(int)$row['student_id']] = $row['last'];
    return $out;
}

function rm_when($datetime) { return date('M j, Y g:i A', strtotime($datetime)); }

// Returns [subject, body]
function rm_build_message($stu, $note = '') {
    $lines = [];
    foreach ($stu['books'] as $i => $b) {
        $lines[] = ($i + 1) . '. ' . $b['title'] . ($b['acc'] !== '' ? ' (Acc. No. ' . $b['acc'] . ')' : '')
                 . "\n   Due date: " . date('F j, Y', strtotime($b['due'])) . ' — ' . $b['late'] . ' day' . ($b['late'] == 1 ? '' : 's') . ' overdue';
    }
    $n = count($stu['books']);
    $note = trim(preg_replace('/\s+/', ' ', (string)$note));
    $body = 'Dear ' . rm_display_name($stu['name']) . ",\n\n"
          . "This is a friendly reminder from the College of Law Library of The College of Maasin. Our records show that "
          . ($n == 1 ? "the following book borrowed under your name is" : "the following books borrowed under your name are") . " past the due date:\n\n"
          . implode("\n\n", $lines) . "\n\n"
          . "Please return " . ($n == 1 ? 'it' : 'them') . " to the College of Law Library as soon as possible so that other students can use "
          . ($n == 1 ? 'it' : 'them') . ".\n"
          . ($note !== '' ? "\n" . $note . "\n" : '')
          . "\nIf you have already returned " . ($n == 1 ? 'it' : 'them') . ", or if you have any questions, please reply to this e-mail.\n\n"
          . "Thank you.\n\nCollege of Law Library\nThe College of Maasin\n";
    return ['Overdue library ' . ($n == 1 ? 'book' : 'books') . ' — College of Law Library, The College of Maasin', $body];
}

// Sends one reminder. Returns [ok, message]
function rm_send($conn, $student_id, $note, $staff) {
    rm_ensure($conn);
    $all = rm_overdue_students($conn, $student_id);
    if (!isset($all[$student_id])) return [false, 'This student has no overdue books any more (they may have just been returned). Reload the page.'];
    $stu = $all[$student_id];
    list($email, $st) = st_email($stu['email']);
    if ($st !== 'ok') return [false, 'This student has no e-mail address on file.'];
    list($subject, $body) = rm_build_message($stu, $note);
    list($ok, $msg) = bk_send_mail($email, $subject, $body, null, ['from_name' => 'College of Law Library — The College of Maasin', 'reply_to' => true]);
    $stmt = mysqli_prepare($conn, "INSERT INTO reminder_log (student_id, sent_at, sent_to, sent_by, book_count, ok, error) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $cnt = count($stu['books']); $okv = $ok ? 1 : 0; $err = $ok ? null : mb_substr($msg, 0, 250);
    $now = date('Y-m-d H:i:s');
    mysqli_stmt_bind_param($stmt, 'isssiis', $student_id, $now, $email, $staff, $cnt, $okv, $err);
    mysqli_stmt_execute($stmt);
    return [$ok, $ok ? 'Reminder sent to ' . $email : $msg];
}
