<?php
// =====================================================================
// Command-line backup / restore. Used by backup.bat, restore.bat and the
// Windows scheduled task. It works even when the website cannot be opened
// (it only needs PHP and MySQL, not Apache).
//
//   php backup_cli.php backup [--scheduled] [--email]
//   php backup_cli.php restore [file.zip | file.sql]
//   php backup_cli.php list
// =====================================================================
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This tool can only be started from the command line (backup.bat / restore.bat).');
}
require_once __DIR__ . '/backup_functions.php';

function out($t = '') { echo $t . PHP_EOL; }
function ask($prompt) { echo $prompt; $l = fgets(STDIN); return $l === false ? '' : trim($l); }

$cmd = isset($argv[1]) ? $argv[1] : 'help';
$flags = array_slice($argv, 2);

if ($cmd === 'backup') {
    $scheduled = in_array('--scheduled', $flags, true);
    $s = bk_settings_load();
    $mail = in_array('--email', $flags, true) || ($scheduled && $s['auto_email'] && bk_email_configured($s));
    out('Backing up the TCM Law Library system...');
    $res = bk_create_backup($scheduled ? 'scheduled' : 'manual', ['keep_local' => true, 'email' => $mail]);
    if ($res['local']) out(($res['local']['ok'] ? '  Saved:   ' : '  FAILED:  ') . ($res['local']['ok'] ? $res['path'] . ' (' . bk_human_size($res['size']) . ')' : $res['local']['error']));
    if ($res['mirror']) out(($res['mirror']['ok'] ? '  Copy:    ' : '  COPY FAILED: ') . ($res['mirror']['ok'] ? $res['mirror']['dir'] : $res['mirror']['error']));
    if ($res['mail']) out(($res['mail']['ok'] ? '  E-mail:  sent to ' . $res['mail']['to'] : '  E-MAIL FAILED: ' . $res['mail']['error']));
    if (!$res['ok']) { out('  The backup FAILED: ' . $res['error']); exit(1); }
    out('Done.');
    exit(0);
}

if ($cmd === 'list') {
    $s = bk_settings_load();
    out('Backup folder: ' . $s['local_dir']);
    foreach (bk_list_backups($s['local_dir']) as $i => $b) out(sprintf('  %2d. %s   %s   %s', $i + 1, bk_when($b['ts']), str_pad($b['type'], 11), bk_human_size($b['size'])));
    exit(0);
}

if ($cmd === 'restore') {
    out('=== RESTORE the TCM Law Library system from a backup ===');
    out();
    $s = bk_settings_load();
    $file = isset($flags[0]) ? $flags[0] : '';

    if ($file === '') {
        $list = bk_list_backups($s['local_dir']);
        if (!$list && bk_base_dir() !== $s['local_dir']) $list = bk_list_backups(bk_base_dir());
        if (!$list) {
            out('No backup files were found in ' . $s['local_dir']);
            $file = ask('Type the full path of a backup file (.zip or .sql), or press Enter to quit: ');
            if ($file === '') exit(1);
            $file = trim($file, "\"' ");
        } else {
            out('Backups found in ' . dirname($list[0]['path']) . ':');
            foreach (array_slice($list, 0, 15) as $i => $b) out(sprintf('  %2d. %s   %-11s %s', $i + 1, bk_when($b['ts']), $b['type'], bk_human_size($b['size'])));
            $def = 1;                                   // the newest real backup (not the safety copy a restore leaves)
            foreach ($list as $i => $b) { if ($b['type'] !== 'pre-restore') { $def = $i + 1; break; } }
            $n = ask('Which one? Type its number, or press Enter for the newest backup (' . $def . '): ');
            $n = $n === '' ? $def : (int)$n;
            if ($n < 1 || $n > min(15, count($list))) { out('That is not on the list. Nothing was changed.'); exit(1); }
            $file = $list[$n - 1]['path'];
        }
    }
    if (!is_file($file)) { out('File not found: ' . $file); exit(1); }
    out();
    out('Selected: ' . $file);

    $isSql = strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'sql';
    $password = ''; $withFiles = false;
    if (!$isSql) {
        list($man, $err) = bk_read_manifest($file);
        if (!$man) { out('This is not a valid backup: ' . $err); exit(1); }
        out('Made on: ' . bk_when($man['created_ts'] ?? 0));
        foreach ($man['databases'] as $info) foreach ($info['tables'] as $t => $n) out('  ' . str_pad($t, 14) . $n . ' record(s)');
        if (!empty($man['encrypted'])) $password = ask('This backup is password-protected. Password (it will be visible as you type): ');
        $a = strtolower(ask('Also restore the PROGRAM FILES (overwrites the PHP files)? y/N: '));
        $withFiles = ($a === 'y' || $a === 'yes');
    } else {
        out('This is a plain database file. It will replace the data in database "' . bk_main_db() . '".');
    }
    out();
    out('WARNING: the data now in the system will be replaced by this backup.');
    out('The current data is saved first as a safety backup, and nothing is replaced unless the backup loads completely.');
    if (ask('Type RESTORE to continue: ') !== 'RESTORE') { out('Cancelled. Nothing was changed.'); exit(1); }
    out();

    if ($isSql) {
        $r = bk_restore_plain_sql($file);
        out($r['ok'] ? 'Database restored.' : 'FAILED: ' . $r['error']);
        if ($r['safety']) out('Safety backup of the old data: ' . $r['safety']);
        exit($r['ok'] ? 0 : 1);
    }
    $r = bk_restore_backup($file, ['password' => $password, 'restore_files' => $withFiles]);
    foreach ($r['steps'] as $st) out('  - ' . $st);
    foreach ($r['warnings'] as $w) out('  ! ' . $w);
    if (!$r['ok']) { out(); out('FAILED: ' . $r['error']); exit(1); }
    out();
    out('Restore finished. Start Apache in XAMPP and open the system again.');
    exit(0);
}

out('Usage:');
out('  php backup_cli.php backup [--scheduled] [--email]');
out('  php backup_cli.php restore [file.zip | file.sql]');
out('  php backup_cli.php list');
exit(0);
