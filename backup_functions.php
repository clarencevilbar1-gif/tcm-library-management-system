<?php
// =====================================================================
// Backup & Restore engine for the TCM Law Library system.
//
// This file does NOT check who is logged in — the pages that use it do.
// It also never depends on db.php being able to connect, so it keeps working
// when the database is damaged or missing (that is exactly when it is needed).
// =====================================================================

require_once __DIR__ . '/backup_config.php';

use PHPMailer\PHPMailer\PHPMailer;

// ---------------------------------------------------------------------
// Small helpers
// ---------------------------------------------------------------------
function bk_base_dir() {
    $env = getenv('TCM_BACKUP_BASE');           // used by automated tests only
    return $env ? rtrim($env, '/\\') : BK_BASE_DEFAULT;
}
function bk_settings_file() { return bk_base_dir() . DIRECTORY_SEPARATOR . 'settings.json'; }
function bk_log_file()      { return bk_base_dir() . DIRECTORY_SEPARATOR . 'backup-log.json'; }

function bk_human_size($bytes) {
    $bytes = (float)$bytes;
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return number_format($bytes / 1024, 0) . ' KB';
    return (int)$bytes . ' B';
}

function bk_ago($ts) {
    $d = time() - (int)$ts;
    if ($d < 60)        return 'just now';
    if ($d < 3600)      { $m = (int)floor($d / 60);    return $m . ' minute' . ($m == 1 ? '' : 's') . ' ago'; }
    if ($d < 86400)     { $h = (int)floor($d / 3600);  return $h . ' hour'   . ($h == 1 ? '' : 's') . ' ago'; }
    $dd = (int)floor($d / 86400);
    return $dd . ' day' . ($dd == 1 ? '' : 's') . ' ago';
}

function bk_when($ts) { return date('M j, Y g:i A', (int)$ts); }

function bk_ensure_dir($dir) {
    if ($dir === '' || $dir === null) return [false, 'No folder was given.'];
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return [false, 'The folder could not be created: ' . $dir];
        }
    }
    if (!is_writable($dir)) return [false, 'The folder is not writable: ' . $dir];
    bk_protect_if_public($dir);
    return [true, ''];
}

// If someone points the backup folder inside the website folder, make sure it can't be downloaded.
function bk_protect_if_public($dir) {
    $root = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $real = realpath($dir);
    if (!$root || !$real) return;
    $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
    $real = rtrim(str_replace('\\', '/', $real), '/') . '/';
    if (stripos($real, $root) === 0) {
        @file_put_contents($dir . DIRECTORY_SEPARATOR . '.htaccess', "Require all denied\nDeny from all\n");
        @file_put_contents($dir . DIRECTORY_SEPARATOR . 'index.html', '');
    }
}

// ---------------------------------------------------------------------
// Settings (stored in a small JSON file outside the website folder)
// ---------------------------------------------------------------------
function bk_settings_defaults() {
    return [
        'local_dir'    => bk_base_dir(),
        'mirror_dir'   => '',
        'auto_enabled' => true,
        'auto_hours'   => 24,
        'auto_email'   => false,
        'keep'         => 60,
        'mail_to'      => '',
        'smtp_user'    => '',
        'smtp_pass'    => '',
        'smtp_host'    => 'smtp.gmail.com',
        'smtp_port'    => 587,
        'smtp_secure'  => 'tls',
        'zip_password' => '',
        'last_target'  => 'local',
    ];
}

function bk_settings_load() {
    $s = bk_settings_defaults();
    $f = bk_settings_file();
    if (is_file($f)) {
        $j = json_decode((string)@file_get_contents($f), true);
        if (is_array($j)) {
            foreach ($s as $k => $def) {
                if (array_key_exists($k, $j)) $s[$k] = $j[$k];
            }
        }
    }
    $s['auto_enabled'] = (bool)$s['auto_enabled'];
    $s['auto_email']   = (bool)$s['auto_email'];
    $s['auto_hours']   = max(1, min(168, (int)$s['auto_hours']));
    $s['keep']         = max(5, min(1000, (int)$s['keep']));
    $s['smtp_port']    = (int)$s['smtp_port'] ?: 587;
    if (!in_array($s['smtp_secure'], ['tls', 'ssl', 'none'], true)) $s['smtp_secure'] = 'tls';
    if (!in_array($s['last_target'], ['local', 'email', 'both'], true)) $s['last_target'] = 'local';
    if (trim($s['local_dir']) === '') $s['local_dir'] = bk_base_dir();
    return $s;
}

function bk_settings_save($s) {
    list($ok, $err) = bk_ensure_dir(bk_base_dir());
    if (!$ok) return [false, $err];
    $tmp = bk_settings_file() . '.tmp';
    if (@file_put_contents($tmp, json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
        return [false, 'The settings file could not be written in ' . bk_base_dir()];
    }
    if (!@rename($tmp, bk_settings_file())) {
        @unlink($tmp);
        return [false, 'The settings file could not be saved.'];
    }
    return [true, ''];
}

function bk_email_configured($s = null) {
    if ($s === null) $s = bk_settings_load();
    return $s['smtp_user'] !== '' && $s['smtp_pass'] !== '';
}

function bk_parse_recipients($text) {
    $out = [];
    foreach (preg_split('/[,;\s]+/', (string)$text, -1, PREG_SPLIT_NO_EMPTY) as $e) {
        $e = trim($e);
        if (!filter_var($e, FILTER_VALIDATE_EMAIL)) return [null, $e];
        if (!isset($out[strtolower($e)])) $out[strtolower($e)] = $e;
    }
    return [array_values($out), null];
}

// ---------------------------------------------------------------------
// Log (what was backed up, when, where) — a file, so it survives a database loss
// ---------------------------------------------------------------------
function bk_log_read() {
    $f = bk_log_file();
    if (!is_file($f)) return [];
    $j = json_decode((string)@file_get_contents($f), true);
    return is_array($j) ? $j : [];
}

function bk_log_append($entry) {
    bk_ensure_dir(bk_base_dir());
    $log = bk_log_read();
    $log[] = $entry;
    if (count($log) > 300) $log = array_slice($log, -300);
    @file_put_contents(bk_log_file(), json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// ---------------------------------------------------------------------
// Database connection (credentials are read from db.php, so they always match)
// ---------------------------------------------------------------------
function bk_db_credentials() {
    $c = ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'db' => 'law_library_db'];
    $src = is_file(BK_APP_DIR . '/db.php') ? (string)file_get_contents(BK_APP_DIR . '/db.php') : '';
    $map = ['host' => 'host', 'user' => 'user', 'pass' => 'password', 'db' => 'database'];
    foreach ($map as $key => $var) {
        if (preg_match('/\$' . $var . '\s*=\s*(["\'])(.*?)\1\s*;/', $src, $m)) $c[$key] = $m[2];
    }
    return $c;
}

function bk_main_db() { $c = bk_db_credentials(); return $c['db']; }

function bk_all_databases() {
    return array_values(array_unique(array_merge([bk_main_db()], BK_EXTRA_DATABASES)));
}

// Returns [mysqli|null, error]. $db = null connects to the server without choosing a database.
function bk_connect($db = null) {
    $c = bk_db_credentials();
    mysqli_report(MYSQLI_REPORT_OFF);
    $m = @new mysqli($c['host'], $c['user'], $c['pass'], $db === null ? '' : $db);
    if ($m->connect_errno) return [null, $m->connect_error];
    $m->set_charset('utf8mb4');
    return [$m, ''];
}

function bk_q($name) { return '`' . str_replace('`', '``', $name) . '`'; }

function bk_fingerprint($m, $db) {
    $tables = bk_list_tables($m, $db);
    if (!$tables) return '';
    $parts = [];
    foreach ($tables as $t) {
        $r = $m->query('CHECKSUM TABLE ' . bk_q($db) . '.' . bk_q($t));
        $row = $r ? $r->fetch_row() : null;
        $parts[] = $t . ':' . ($row ? $row[1] : '?');
    }
    return md5(implode('|', $parts));
}

function bk_list_tables($m, $db, $views = false) {
    $out = [];
    $r = $m->query('SHOW FULL TABLES FROM ' . bk_q($db));
    if (!$r) return $out;
    while ($row = $r->fetch_row()) {
        $isView = (strtoupper($row[1]) === 'VIEW');
        if ($isView === $views) $out[] = $row[0];
    }
    return $out;
}

// ---------------------------------------------------------------------
// Dump a database to a plain .sql file (also readable by phpMyAdmin)
// Returns [ok, error, ['table' => rowCount, ...]]
// ---------------------------------------------------------------------
function bk_dump_database($m, $db, $outfile) {
    $fh = @fopen($outfile, 'wb');
    if (!$fh) return [false, 'Could not write the temporary database file.', []];

    $counts = [];
    $numeric = [0, 1, 2, 3, 4, 5, 8, 9, 13, 246];   // decimal, tiny..bigint, float, double, year, newdecimal
    fwrite($fh, "-- TCM College of Law Library — database backup\n");
    fwrite($fh, "-- Database: {$db}\n-- Created: " . date('Y-m-d H:i:s') . " (Asia/Manila)\n");
    fwrite($fh, "-- Server: " . $m->server_info . "\n-- You can import this file in phpMyAdmin (Import tab) if everything else fails.\n\n");
    fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

    foreach (bk_list_tables($m, $db) as $t) {
        $r = $m->query('SHOW CREATE TABLE ' . bk_q($db) . '.' . bk_q($t));
        if (!$r) { fclose($fh); return [false, 'Could not read the structure of table ' . $t . ': ' . $m->error, []]; }
        $row = $r->fetch_row();
        fwrite($fh, "-- ----------------------------------------\n-- Table: {$t}\n-- ----------------------------------------\n");
        fwrite($fh, 'DROP TABLE IF EXISTS ' . bk_q($t) . ";\n" . $row[1] . ";\n\n");

        $res = $m->query('SELECT * FROM ' . bk_q($db) . '.' . bk_q($t));
        if (!$res) { fclose($fh); return [false, 'Could not read the rows of table ' . $t . ': ' . $m->error, []]; }
        $fields = $res->fetch_fields();
        $cols = [];
        foreach ($fields as $f) $cols[] = bk_q($f->name);
        $head = 'INSERT INTO ' . bk_q($t) . ' (' . implode(', ', $cols) . ") VALUES\n";
        $n = 0; $batch = []; $size = 0;
        while ($row = $res->fetch_row()) {
            $vals = [];
            foreach ($row as $i => $v) {
                if ($v === null) { $vals[] = 'NULL'; continue; }
                $f = $fields[$i];
                if (in_array($f->type, $numeric, true) && is_numeric($v)) { $vals[] = $v; continue; }
                if ($f->charsetnr == 63 && in_array($f->type, [16, 249, 250, 251, 252, 253, 254, 255], true) && $v !== '') { $vals[] = '0x' . bin2hex($v); continue; }   // binary data (not dates)
                $vals[] = "'" . $m->real_escape_string($v) . "'";
            }
            $line = '(' . implode(', ', $vals) . ')';
            $batch[] = $line; $size += strlen($line); $n++;
            if (count($batch) >= 100 || $size > 500000) {
                fwrite($fh, $head . implode(",\n", $batch) . ";\n");
                $batch = []; $size = 0;
            }
        }
        if ($batch) fwrite($fh, $head . implode(",\n", $batch) . ";\n");
        $res->free();
        fwrite($fh, "\n");
        $counts[$t] = $n;
    }

    foreach (bk_list_tables($m, $db, true) as $v) {          // views
        $r = $m->query('SHOW CREATE VIEW ' . bk_q($db) . '.' . bk_q($v));
        if ($r && ($row = $r->fetch_row())) {
            $sql = preg_replace('/DEFINER\s*=\s*(`[^`]*`(@`[^`]*`)?|\S+)\s*/i', '', $row[1]);
            $sql = str_replace(bk_q($db) . '.', '', $sql);
            fwrite($fh, 'DROP VIEW IF EXISTS ' . bk_q($v) . ";\n" . $sql . ";\n\n");
        }
    }

    $r = $m->query('SHOW TRIGGERS FROM ' . bk_q($db));        // triggers
    if ($r) {
        $names = [];
        while ($row = $r->fetch_assoc()) $names[] = $row['Trigger'];
        foreach ($names as $tr) {
            $r2 = $m->query('SHOW CREATE TRIGGER ' . bk_q($db) . '.' . bk_q($tr));
            if ($r2 && ($row = $r2->fetch_assoc())) {
                $sql = isset($row['SQL Original Statement']) ? $row['SQL Original Statement'] : '';
                $sql = preg_replace('/DEFINER\s*=\s*(`[^`]*`(@`[^`]*`)?|\S+)\s*/i', '', $sql);
                if ($sql !== '') fwrite($fh, "DELIMITER \$\$\nDROP TRIGGER IF EXISTS " . bk_q($tr) . "\$\$\n" . $sql . "\$\$\nDELIMITER ;\n\n");
            }
        }
    }

    fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fh);
    return [true, '', $counts];
}

// ---------------------------------------------------------------------
// Split a .sql file into single statements (handles quotes, comments, DELIMITER)
// ---------------------------------------------------------------------
function bk_sql_statements($sql) {
    $out = [];
    $len = strlen($sql);
    $delim = ';';
    $buf = '';
    $has = false;
    $i = 0;
    $atLineStart = true;
    while ($i < $len) {
        $c = $sql[$i];

        if ($atLineStart && !$has && preg_match('/\G[ \t]*DELIMITER[ \t]+(\S+)[ \t]*\r?(\n|$)/Ai', $sql, $mm, 0, $i)) {
            $delim = $mm[1];
            $i += strlen($mm[0]);
            continue;
        }
        if ($c === "\n") { $atLineStart = true; $buf .= $c; $i++; continue; }
        if (!ctype_space($c)) $atLineStart = false;

        // comments
        if ($c === '#' || ($c === '-' && substr($sql, $i, 2) === '--' && ($i + 2 >= $len || ctype_space($sql[$i + 2])))) {
            $e = strpos($sql, "\n", $i);
            if ($e === false) $e = $len;
            $i = $e;
            continue;
        }
        if ($c === '/' && substr($sql, $i, 2) === '/*') {
            $e = strpos($sql, '*/', $i + 2);
            $e = ($e === false) ? $len : $e + 2;
            $chunk = substr($sql, $i, $e - $i);
            if (substr($chunk, 0, 3) === '/*!') { $buf .= $chunk; $has = true; }   // executable comment, keep
            $i = $e;
            continue;
        }
        // quoted text
        if ($c === "'" || $c === '"' || $c === '`') {
            $q = $c; $j = $i + 1;
            while ($j < $len) {
                if ($sql[$j] === '\\' && $q !== '`') { $j += 2; continue; }
                if ($sql[$j] === $q) {
                    if ($j + 1 < $len && $sql[$j + 1] === $q) { $j += 2; continue; }   // doubled quote
                    break;
                }
                $j++;
            }
            $buf .= substr($sql, $i, $j - $i + 1);
            $has = true;
            $i = $j + 1;
            continue;
        }
        // end of statement
        if (substr($sql, $i, strlen($delim)) === $delim) {
            if ($has) $out[] = trim($buf);
            $buf = ''; $has = false;
            $i += strlen($delim);
            continue;
        }
        $buf .= $c;
        if (!ctype_space($c)) $has = true;
        $i++;
    }
    if ($has && trim($buf) !== '') $out[] = trim($buf);
    return $out;
}

// ---------------------------------------------------------------------
// System files that go into the backup
// ---------------------------------------------------------------------
function bk_collect_files() {
    $files = [];
    $root = rtrim(str_replace('\\', '/', BK_APP_DIR), '/');
    $it = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator(BK_APP_DIR, FilesystemIterator::SKIP_DOTS),
            function ($cur) {
                if ($cur->isDir()) return !in_array($cur->getFilename(), BK_SKIP_DIRS, true);
                return true;
            }
        )
    );
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $name = $f->getFilename();
        if (preg_match('/\.(zip|part|tmp|log)$/i', $name)) continue;
        if ($f->getSize() > BK_MAX_FILE_BYTES) continue;
        $rel = ltrim(substr(str_replace('\\', '/', $f->getPathname()), strlen($root)), '/');
        $files[$rel] = $f->getPathname();
    }
    ksort($files);
    return $files;
}

// Gmail refuses an attachment when a .zip contains certain file types (scripts and programs),
// even though they are harmless here. In the copy that is e-mailed, such files get ".txt" added to
// their name; a restore takes the ".txt" off again.
define('BK_MAIL_BLOCKED_EXT', '/\.(ade|adp|apk|appx|bat|cab|chm|cmd|com|cpl|dll|dmg|ex|ex_|exe|hta|ins|isp|iso|jar|js|jse|lib|lnk|mde|msc|msi|msp|mst|nsh|pif|scr|sct|shb|sys|vb|vbe|vbs|vxd|wsc|wsf|wsh)$/i');

// Writes the backup .zip. $mailSafe = true makes the Gmail-friendly variant. Returns bool.
function bk_write_zip($part, $dbFiles, $dbDir, $files, $manifest, $password, $mailSafe) {
    $zip = new ZipArchive();
    if ($zip->open($part, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return false;
    if ($password !== '') $zip->setPassword($password);
    $encNames = [];
    foreach ($dbFiles as $name => $_) { $zip->addFile($dbDir . DIRECTORY_SEPARATOR . $name, $name); $encNames[] = $name; }
    foreach ($files as $rel => $path) {
        $inside = 'system-files/' . $rel . (($mailSafe && preg_match(BK_MAIL_BLOCKED_EXT, $rel)) ? '.txt' : '');
        $zip->addFile($path, $inside);
        $encNames[] = $inside;
    }
    $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $zip->addFromString('RECOVERY-GUIDE.txt', bk_recovery_text());
    if ($password !== '') {
        foreach ($encNames as $n) $zip->setEncryptionName($n, ZipArchive::EM_AES_256);
    }
    return $zip->close();
}

function bk_recovery_text() {
    $f = BK_APP_DIR . '/RECOVERY-GUIDE.txt';
    if (is_file($f)) return (string)file_get_contents($f);
    return "TCM Law Library — recovery\n\nImport database.sql from this zip in phpMyAdmin (Import tab) to get the data back.\n"
         . "The folder system-files/ holds a copy of the website files.\n";
}

// ---------------------------------------------------------------------
// Create a backup
//   $type   : manual | logout | auto | scheduled | pre-restore
//   $opts   : keep_local (bool, default true), email (bool), email_to (string)
// Returns an array: ok, error, file, path, size, tables, local, mirror, mail, fp, time ...
// ---------------------------------------------------------------------
function bk_create_backup($type = 'manual', $opts = []) {
    @set_time_limit(300);
    $type = preg_match('/^[a-z-]{3,15}$/', $type) ? $type : 'manual';
    $s = bk_settings_load();
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'error' => 'The zip extension of PHP is switched off. In C:\\xampp\\php\\php.ini make sure the line "extension=zip" has no semicolon in front, then restart Apache.',
                'type' => $type, 'ts' => time(), 'time' => date('c'), 'file' => '', 'size' => 0, 'tables' => [], 'local' => null, 'mirror' => null, 'mail' => null, 'fp' => ''];
    }
    $keepLocal = array_key_exists('keep_local', $opts) ? (bool)$opts['keep_local'] : true;
    $wantMail  = !empty($opts['email']);
    $res = ['ok' => false, 'error' => '', 'type' => $type, 'ts' => time(), 'time' => date('c'),
            'file' => '', 'size' => 0, 'tables' => [], 'local' => null, 'mirror' => null, 'mail' => null, 'fp' => ''];

    list($okb, $errb) = bk_ensure_dir(bk_base_dir());
    if (!$okb) { $res['error'] = $errb; return $res; }

    // one backup at a time
    $lock = @fopen(bk_base_dir() . DIRECTORY_SEPARATOR . '.lock', 'c');
    if ($lock && !flock($lock, LOCK_EX | LOCK_NB)) {
        $res['error'] = 'Another backup is running right now. Please wait a moment and try again.';
        return $res;
    }

    $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tcm-bk-' . bin2hex(random_bytes(5));
    @mkdir($tmpDir, 0775, true);
    $cleanup = function () use ($tmpDir, $lock) {
        foreach (glob($tmpDir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) @unlink($f);
        @rmdir($tmpDir);
        if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
    };

    $targetDir = null;
    if ($keepLocal) {
        list($okd, $errd) = bk_ensure_dir($s['local_dir']);
        if (!$okd) {
            $res['local'] = ['ok' => false, 'error' => $errd];
            if (!$wantMail) { $res['error'] = $errd; $cleanup(); bk_log_append(bk_log_entry($res)); return $res; }
            $keepLocal = false;      // folder unusable: still try the e-mail copy
        } else {
            $targetDir = $s['local_dir'];
        }
    }
    if ($targetDir === null) $targetDir = $tmpDir;

    // 1) database(s)
    $dbFiles = [];
    $allCounts = [];
    $mainDb = bk_main_db();
    $fp = '';
    foreach (bk_all_databases() as $dbName) {
        list($m, $cerr) = bk_connect($dbName);
        if (!$m) { $res['error'] = "Cannot connect to the database '{$dbName}': " . $cerr; $cleanup(); bk_log_append(bk_log_entry($res)); return $res; }
        $name = ($dbName === $mainDb) ? 'database.sql' : 'database_' . preg_replace('/[^A-Za-z0-9_]/', '_', $dbName) . '.sql';
        list($okd, $errd, $counts) = bk_dump_database($m, $dbName, $tmpDir . DIRECTORY_SEPARATOR . $name);
        if (!$okd) { $res['error'] = $errd; $cleanup(); bk_log_append(bk_log_entry($res)); return $res; }
        if ($dbName === $mainDb) $fp = bk_fingerprint($m, $dbName);
        $dbFiles[$name] = ['db' => $dbName, 'sha256' => hash_file('sha256', $tmpDir . DIRECTORY_SEPARATOR . $name), 'tables' => $counts];
        $allCounts[$dbName] = $counts;
        $m->close();
    }
    $res['fp'] = $fp;
    $res['tables'] = isset($allCounts[$mainDb]) ? $allCounts[$mainDb] : [];

    // 2) the zip
    // the name carries the date and time; if one with the same second already exists, wait for the next second
    for ($try = 0; $try < 4; $try++) {
        $stamp = date('Y-m-d_H-i-s');
        $fileName = 'TCM-Library-Backup_' . $stamp . '_' . $type . '.zip';
        $final = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $fileName;
        if (!file_exists($final)) break;
        sleep(1);
    }
    $part = $final . '.part';
    $password = (string)$s['zip_password'];
    $files = bk_collect_files();

    $manifest = [
        'format'      => 1,
        'system'      => 'TCM College of Law Library Management System',
        'created'     => date('c'),
        'created_ts'  => time(),
        'type'        => $type,
        'database'    => $mainDb,
        'databases'   => $dbFiles,
        'files'       => count($files),
        'encrypted'   => $password !== '',
        'php'         => PHP_VERSION,
        'computer'    => php_uname('n'),
        'fingerprint' => $fp,
    ];

    if (@!is_dir(dirname($part))) { $res['error'] = 'The backup file could not be created in ' . $targetDir; $cleanup(); bk_log_append(bk_log_entry($res)); return $res; }
    $okz = bk_write_zip($part, $dbFiles, $tmpDir, $files, $manifest, $password, false);
    if (!$okz || !is_file($part)) {
        @unlink($part);
        $res['error'] = 'The backup file could not be written (is the disk full?).';
        $cleanup(); bk_log_append(bk_log_entry($res)); return $res;
    }
    if (!@rename($part, $final)) { @unlink($part); $res['error'] = 'The backup file could not be finished.'; $cleanup(); bk_log_append(bk_log_entry($res)); return $res; }

    $res['file'] = $fileName;
    $res['path'] = $final;
    $res['size'] = filesize($final);
    if ($keepLocal) $res['local'] = ['ok' => true, 'dir' => $targetDir];

    // 3) second copy (USB drive, Google Drive folder, ...)
    if ($keepLocal && trim($s['mirror_dir']) !== '') {
        list($okm, $errm) = bk_ensure_dir($s['mirror_dir']);
        if ($okm && @copy($final, rtrim($s['mirror_dir'], '/\\') . DIRECTORY_SEPARATOR . $fileName)) {
            $res['mirror'] = ['ok' => true, 'dir' => $s['mirror_dir']];
        } else {
            $res['mirror'] = ['ok' => false, 'dir' => $s['mirror_dir'], 'error' => $okm ? 'The copy could not be written.' : $errm];
        }
    }

    // 4) e-mail
    if ($wantMail) {
        $to = isset($opts['email_to']) && trim($opts['email_to']) !== '' ? $opts['email_to'] : $s['mail_to'];
        // the e-mailed copy is the Gmail-friendly variant (same contents, scripts renamed *.txt)
        $mailDir = $tmpDir . DIRECTORY_SEPARATOR . 'mail';
        @mkdir($mailDir, 0775, true);
        $mailZip = $mailDir . DIRECTORY_SEPARATOR . $fileName;
        if (bk_write_zip($mailZip, $dbFiles, $tmpDir, $files, $manifest, $password, true)) {
            list($okm, $msg) = bk_send_backup_email($mailZip, $to, $res, $manifest);
        } else {
            list($okm, $msg) = [false, 'The e-mail copy of the backup could not be prepared.'];
        }
        @unlink($mailZip); @rmdir($mailDir);
        $res['mail'] = ['ok' => $okm, 'to' => $to, 'error' => $okm ? '' : $msg];
    }

    // 5) tidy up
    if ($keepLocal) { bk_prune($s['local_dir'], $s['keep']); if (trim($s['mirror_dir']) !== '') bk_prune($s['mirror_dir'], $s['keep']); }
    else { @unlink($final); $res['path'] = ''; }

    $localOk  = $keepLocal && $res['local'] && $res['local']['ok'];
    $mailOk   = $res['mail'] && $res['mail']['ok'];
    $res['ok'] = $wantMail ? ($mailOk || $localOk) : $localOk;
    if (!$res['ok'] && $res['error'] === '') $res['error'] = $wantMail && !$localOk && !$mailOk ? ($res['mail']['error'] ?? 'The backup could not be saved.') : 'The backup could not be saved.';

    $cleanup();
    bk_log_append(bk_log_entry($res));
    return $res;
}

function bk_log_entry($res) {
    return [
        'time'   => date('c'), 'ts' => time(), 'type' => $res['type'], 'ok' => (bool)$res['ok'],
        'file'   => $res['file'], 'size' => $res['size'], 'fp' => $res['fp'],
        'local'  => $res['local'], 'mirror' => $res['mirror'], 'mail' => $res['mail'],
        'tables' => $res['tables'], 'error' => $res['error'],
    ];
}

function bk_list_backups($dir = null) {
    if ($dir === null) { $s = bk_settings_load(); $dir = $s['local_dir']; }
    $out = [];
    foreach (glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'TCM-Library-Backup_*.zip') ?: [] as $f) {
        $b = basename($f);
        if (!preg_match('/^TCM-Library-Backup_(\d{4}-\d{2}-\d{2})_(\d{2}-\d{2}-\d{2})_([a-z-]+)\.zip$/', $b, $m)) continue;
        $ts = strtotime($m[1] . ' ' . str_replace('-', ':', $m[2]));
        $out[] = ['name' => $b, 'path' => $f, 'size' => filesize($f), 'ts' => $ts ?: filemtime($f), 'type' => $m[3]];
    }
    usort($out, function ($a, $b) { return $b['ts'] <=> $a['ts'] ?: strcmp($b['name'], $a['name']); });
    return $out;
}

function bk_prune($dir, $keep) {
    $list = bk_list_backups($dir);
    foreach (array_slice($list, max(5, (int)$keep)) as $b) @unlink($b['path']);
}

// ---------------------------------------------------------------------
// Status for the dashboard tile, the Backup page and the logout prompt
// ---------------------------------------------------------------------
function bk_status($m = null) {
    $s = bk_settings_load();
    $log = bk_log_read();
    $lastOk = null; $lastLocal = null; $lastMail = null;
    foreach (array_reverse($log) as $e) {
        if (empty($e['ok'])) continue;
        // a backup whose file has since been deleted from the folder no longer counts (unless it was e-mailed)
        $fileThere = !empty($e['file']) && (is_file(rtrim($s['local_dir'], '/\\') . DIRECTORY_SEPARATOR . $e['file'])
                     || (trim($s['mirror_dir']) !== '' && is_file(rtrim($s['mirror_dir'], '/\\') . DIRECTORY_SEPARATOR . $e['file'])));
        $mailed = !empty($e['mail']['ok']);
        if (!$fileThere && !$mailed) continue;
        if (!$lastOk) $lastOk = $e;
        if (!$lastLocal && $fileThere && !empty($e['local']['ok'])) $lastLocal = $e;
        if (!$lastMail && !empty($e['mail']['ok'])) $lastMail = $e;
    }
    $own = false;
    if ($m === null) { list($m, $e) = bk_connect(bk_main_db()); $own = true; }
    $fpNow = $m ? bk_fingerprint($m, bk_main_db()) : '';
    if ($own && $m) $m->close();

    $dirty = !$lastOk || ($fpNow !== '' && $fpNow !== ($lastOk['fp'] ?? ''));
    $age = $lastOk ? time() - (int)$lastOk['ts'] : null;
    if (!$lastOk)                                  $state = 'none';
    elseif (!$dirty)                               $state = 'ok';
    elseif ($age >= $s['auto_hours'] * 3600)       $state = 'overdue';
    else                                           $state = 'changed';

    $autoDue = $s['auto_enabled'] && $dirty && (!$lastOk || $age >= $s['auto_hours'] * 3600);
    return [
        'state'      => $state,
        'dirty'      => (bool)$dirty,
        'auto_due'   => (bool)$autoDue,
        'auto_email' => (bool)($s['auto_email'] && bk_email_configured($s)),
        'last_ts'    => $lastOk ? (int)$lastOk['ts'] : 0,
        'last_when'  => $lastOk ? bk_when($lastOk['ts']) : '',
        'last_ago'   => $lastOk ? bk_ago($lastOk['ts']) : '',
        'last_type'  => $lastOk ? $lastOk['type'] : '',
        'local_when' => $lastLocal ? bk_when($lastLocal['ts']) : '',
        'local_ago'  => $lastLocal ? bk_ago($lastLocal['ts']) : '',
        'mail_when'  => $lastMail ? bk_when($lastMail['ts']) : '',
        'mail_ago'   => $lastMail ? bk_ago($lastMail['ts']) : '',
        'mail_to'    => $s['mail_to'],
        'email_ok'   => bk_email_configured($s),
        'last_target'=> $s['last_target'],
        'auto_hours' => $s['auto_hours'],
        'local_dir'  => $s['local_dir'],
    ];
}

// ---------------------------------------------------------------------
// E-mail (Gmail by default, through PHPMailer)
// ---------------------------------------------------------------------
function bk_mailer_available() {
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) return true;
    $auto = BK_APP_DIR . '/vendor/autoload.php';
    if (is_file($auto)) require_once $auto;
    return class_exists('PHPMailer\\PHPMailer\\PHPMailer');
}

function bk_friendly_mail_error($msg) {
    $m = strtolower($msg);
    if (strpos($m, 'could not authenticate') !== false || strpos($m, 'username and password not accepted') !== false || strpos($m, '535') !== false)
        return 'Gmail did not accept the sign-in. Use an App Password (not the normal Gmail password) — the steps are on the Backup page.';
    if (strpos($m, 'smtp connect() failed') !== false || strpos($m, 'connection') !== false || strpos($m, 'could not connect') !== false)
        return 'Could not reach the mail server. Check that this computer is connected to the internet.';
    if (strpos($m, 'data not accepted') !== false)
        return 'Gmail refused the message (' . $msg . '). Sign-in was fine, so the problem is the message itself: Gmail may be blocking the attachment, or the account is limited. Press "Save & send test e-mail" to check the account separately.';
    return 'The e-mail could not be sent: ' . $msg;
}

// Wraps a plain-text message in a simple e-mail layout with the College of Law logo at the top
function bk_mail_html($text, $logo_src) {
    $h = nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'), false);
    return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f4f1f8;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f1f8;padding:20px 0;"><tr><td align="center">'
        . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;font-family:Segoe UI,Arial,sans-serif;">'
        . '<tr><td style="background:#4B2E83;padding:14px 20px;"><table role="presentation" cellpadding="0" cellspacing="0"><tr>'
        . '<td style="padding-right:12px;"><img src="' . $logo_src . '" width="52" height="52" alt="College of Law" style="display:block;border:0;"></td>'
        . '<td style="color:#ffffff;"><div style="font-size:17px;font-weight:bold;">College of Law Library</div>'
        . '<div style="font-size:12px;color:#D4A72C;">The College of Maasin</div></td></tr></table></td></tr>'
        . '<tr><td style="padding:22px 24px;font-size:14px;line-height:1.55;color:#222222;">' . $h . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

// Returns [ok, message]
// $opts: 'from_name' (display name of the sender), 'reply_to' (true = replies go to the sending Gmail)
function bk_send_mail($to, $subject, $body, $attachment = null, $opts = []) {
    $s = bk_settings_load();
    if (!bk_email_configured($s)) return [false, 'E-mail is not set up yet. Fill in the Gmail address and App Password on the Backup page.'];
    list($rcpt, $bad) = bk_parse_recipients($to);
    if ($bad !== null) return [false, 'This is not a valid e-mail address: ' . $bad];
    if (!$rcpt) return [false, 'Type the e-mail address that should receive the backup.'];
    if (!bk_mailer_available()) return [false, 'The e-mail component is not installed. Open a terminal in the project folder and run:  composer install'];
    if ($attachment && filesize($attachment) > BK_MAX_EMAIL_BYTES) {
        return [false, 'The backup (' . bk_human_size(filesize($attachment)) . ') is too big to e-mail (limit ' . bk_human_size(BK_MAX_EMAIL_BYTES) . '). It was saved on this computer instead.'];
    }
    try {
        $mail = null;
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $s['smtp_host'];
        $mail->Port = $s['smtp_port'];
        $mail->SMTPAuth = true;
        $mail->Username = $s['smtp_user'];
        $mail->Password = preg_replace('/\s+/', '', $s['smtp_pass']);
        if ($s['smtp_secure'] === 'ssl')      $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        elseif ($s['smtp_secure'] === 'tls')  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        else { $mail->SMTPSecure = ''; $mail->SMTPAutoTLS = false; }
        $mail->Timeout = 25;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($s['smtp_user'], isset($opts['from_name']) ? $opts['from_name'] : 'TCM Law Library Backup');
        if (!empty($opts['reply_to'])) $mail->addReplyTo($s['smtp_user']);
        foreach ($rcpt as $r) $mail->addAddress($r);
        $mail->Subject = $subject;
        // HTML version with the College of Law logo; the plain-text version stays as the fallback
        $logo = BK_APP_DIR . '/assets/images/col_logo.png';
        if (is_file($logo)) {
            $mail->addEmbeddedImage($logo, 'lawlogo', 'col_logo.png', 'base64', 'image/png');
            $mail->isHTML(true);
            $mail->Body = bk_mail_html($body, 'cid:lawlogo');
            $mail->AltBody = $body;
        } else {
            $mail->Body = $body;
        }
        if ($attachment) $mail->addAttachment($attachment);
        $mail->send();
        return [true, 'Sent to ' . implode(', ', $rcpt)];
    } catch (\Throwable $e) {
        $detail = '';
        if (isset($mail) && $mail instanceof PHPMailer) { $er = $mail->getSMTPInstance()->getError(); if (!empty($er['detail'])) $detail = trim($er['detail']); if (!empty($er['smtp_code'])) $detail = trim($er['smtp_code'] . ' ' . $detail); }
        $friendly = bk_friendly_mail_error($e->getMessage());
        return [false, $detail !== '' && strpos($friendly, $detail) === false ? $friendly . ' [Server said: ' . $detail . ']' : $friendly];
    }
}

function bk_send_backup_email($path, $to, $res, $manifest) {
    $when = date('F j, Y g:i A');
    $lines = [];
    foreach ($res['tables'] as $t => $n) $lines[] = '  - ' . $t . ': ' . $n . ' record(s)';
    $body = "TCM College of Law Library Management System — backup\n\n"
          . "Created: {$when}\nType: {$res['type']}\nFile: " . basename($path) . ' (' . bk_human_size(filesize($path)) . ")\n"
          . ($manifest['encrypted'] ? "This file is password-protected (the backup password set on the Backup page).\n" : '')
          . "\nContents:\n" . implode("\n", $lines) . "\n\n"
          . "KEEP THIS E-MAIL. If the system ever breaks, download the attached .zip file and restore it\n"
          . "(Backup page > Restore, or open RECOVERY-GUIDE.txt inside the .zip for the step-by-step instructions).\n"
          . "The file contains student and borrowing records: do not forward it to anyone outside the College of Law.\n";
    return bk_send_mail($to, 'TCM Law Library backup — ' . date('Y-m-d H:i') . ' (' . $res['type'] . ')', $body, $path);
}

// ---------------------------------------------------------------------
// Reading a backup file
// ---------------------------------------------------------------------
// Returns [manifest|null, error]
function bk_read_manifest($zipPath) {
    $zip = new ZipArchive();
    if (!is_file($zipPath)) return [null, 'The backup file was not found.'];
    $r = $zip->open($zipPath);
    if ($r !== true) return [null, 'This file is not a valid backup (it cannot be opened as a .zip).'];
    $raw = $zip->getFromName('manifest.json');
    $zip->close();
    if ($raw === false) return [null, 'This zip file is not a backup made by this system (manifest.json is missing).'];
    $j = json_decode($raw, true);
    if (!is_array($j) || empty($j['databases'])) return [null, 'The backup information inside this file is damaged.'];
    return [$j, ''];
}

// Extracts one entry to a temp file after checking it. Returns [path|null, error]
function bk_extract_sql($zipPath, $entry, $sha, $password, $tmpDir) {
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) return [null, 'The backup file cannot be opened.'];
    if ($password !== '') $zip->setPassword($password);
    $stream = $zip->getStream($entry);
    if (!$stream) {
        $zip->close();
        return [null, $password === '' ? 'The database inside the backup could not be read. If the backup is password-protected, enter its password.'
                                       : 'The database inside the backup could not be read. The password is probably wrong.'];
    }
    $out = $tmpDir . DIRECTORY_SEPARATOR . basename($entry);
    $fh = fopen($out, 'wb');
    $n = stream_copy_to_stream($stream, $fh);
    fclose($fh); fclose($stream); $zip->close();
    if (hash_file('sha256', $out) !== $sha) {
        @unlink($out);
        return [null, ($password !== '' ? 'Wrong password, or the' : 'The') . ' backup file is damaged (its checksum does not match). Do not use this file; try an older backup.'];
    }
    return [$out, ''];
}

// ---------------------------------------------------------------------
// Restore
// ---------------------------------------------------------------------
function bk_is_ddl_post($stmt) {
    return (bool)preg_match('/^\s*(DROP\s+(VIEW|TRIGGER)|CREATE\s+(OR\s+REPLACE\s+)?(ALGORITHM|DEFINER|SQL\s+SECURITY|VIEW|TRIGGER))/i', $stmt);
}
function bk_is_db_statement($stmt) {
    return (bool)preg_match('/^\s*(USE\s|CREATE\s+(DATABASE|SCHEMA)\s|DROP\s+(DATABASE|SCHEMA)\s)/i', $stmt);
}

// Restores one database from a .sql file.
// The old data is only replaced once the new data has loaded completely without errors.
// Returns [ok, error, countsAfter]
function bk_restore_sql_file($sqlFile, $db) {
    list($m, $err) = bk_connect(null);
    if (!$m) return [false, 'Cannot connect to the database server (is MySQL running in XAMPP?): ' . $err, []];
    $sql = (string)file_get_contents($sqlFile);
    $stmts = bk_sql_statements($sql);
    if (!$stmts) return [false, 'The database file is empty.', []];

    $tmp = $db . '__restore_tmp';
    $old = $db . '__restore_old';
    $m->query('DROP DATABASE IF EXISTS ' . bk_q($tmp));
    $m->query('DROP DATABASE IF EXISTS ' . bk_q($old));
    $canTemp = $m->query('CREATE DATABASE ' . bk_q($tmp) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');

    $main = []; $post = [];
    foreach ($stmts as $st) {
        if (bk_is_db_statement($st)) continue;
        if (bk_is_ddl_post($st)) $post[] = $st; else $main[] = $st;
    }

    $mode = 'swap';
    if ($canTemp) {
        $m->select_db($tmp);
        $m->query('SET FOREIGN_KEY_CHECKS=0');
        foreach ($main as $i => $st) {
            if (!$m->query($st)) {
                $e = $m->error;
                $m->query('DROP DATABASE IF EXISTS ' . bk_q($tmp));
                return [false, 'The backup could not be loaded (statement ' . ($i + 1) . '): ' . $e . '. Your current data was NOT changed.', []];
            }
        }
        // swap the finished copy in, in one atomic step
        $m->query('CREATE DATABASE IF NOT EXISTS ' . bk_q($db) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
        $m->query('CREATE DATABASE ' . bk_q($old));
        $liveTables = bk_list_tables($m, $db);
        $newTables  = bk_list_tables($m, $tmp);
        foreach (bk_list_tables($m, $db, true) as $v) $m->query('DROP VIEW IF EXISTS ' . bk_q($db) . '.' . bk_q($v));
        $pairs = [];
        foreach ($liveTables as $t) $pairs[] = bk_q($db) . '.' . bk_q($t) . ' TO ' . bk_q($old) . '.' . bk_q($t);
        foreach ($newTables as $t)  $pairs[] = bk_q($tmp) . '.' . bk_q($t) . ' TO ' . bk_q($db) . '.' . bk_q($t);
        if ($pairs && !$m->query('RENAME TABLE ' . implode(', ', $pairs))) {
            $mode = 'direct';          // e.g. triggers prevent moving tables between databases
        }
        $m->query('DROP DATABASE IF EXISTS ' . bk_q($tmp));
    } else {
        $mode = 'direct';
    }

    if ($mode === 'direct') {
        $m->query('CREATE DATABASE IF NOT EXISTS ' . bk_q($db) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
        $m->select_db($db);
        $m->query('SET FOREIGN_KEY_CHECKS=0');
        foreach ($main as $i => $st) {
            if (!$m->query($st)) {
                $e = $m->error;
                $m->query('DROP DATABASE IF EXISTS ' . bk_q($old));
                return [false, 'The restore stopped at statement ' . ($i + 1) . ': ' . $e . '. Use the automatic safety backup taken just before (it is in the backup folder, marked pre-restore).', []];
            }
        }
    }

    $m->select_db($db);
    $m->query('SET FOREIGN_KEY_CHECKS=0');
    $postErr = '';
    foreach ($post as $st) { if (!$m->query($st)) $postErr = $m->error; }
    $m->query('SET FOREIGN_KEY_CHECKS=1');
    $m->query('DROP DATABASE IF EXISTS ' . bk_q($old));

    $after = [];
    foreach (bk_list_tables($m, $db) as $t) {
        $r = $m->query('SELECT COUNT(*) FROM ' . bk_q($t));
        $after[$t] = $r ? (int)$r->fetch_row()[0] : -1;
    }
    $m->close();
    return [true, $postErr !== '' ? 'Restored, but one view/trigger could not be recreated: ' . $postErr : '', $after];
}

// Extracts the system files of a backup into $targetDir (safe against ../ tricks). Returns [count, skipped[]]
function bk_restore_files($zipPath, $password, $targetDir) {
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) return [0, ['The backup could not be opened.']];
    if ($password !== '') $zip->setPassword($password);
    $n = 0; $skipped = [];
    $root = rtrim($targetDir, '/\\');
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (strpos($name, 'system-files/') !== 0 || substr($name, -1) === '/') continue;
        $rel = substr($name, strlen('system-files/'));
        if (substr($rel, -4) === '.txt' && preg_match(BK_MAIL_BLOCKED_EXT, substr($rel, 0, -4))) $rel = substr($rel, 0, -4);   // e-mail copy: scripts were renamed *.txt
        if ($rel === '' || strpos($rel, '..') !== false || $rel[0] === '/' || preg_match('/^[A-Za-z]:/', $rel)) { $skipped[] = $rel; continue; }
        if ($rel === 'db.php' && is_file($root . '/db.php')) { $skipped[] = 'db.php (kept: this computer\'s database login)'; continue; }
        $dest = $root . '/' . $rel;
        if (!is_dir(dirname($dest))) @mkdir(dirname($dest), 0775, true);
        $data = $zip->getFromIndex($i);
        if ($data === false) { $skipped[] = $rel; continue; }
        if (@file_put_contents($dest, $data) === false) { $skipped[] = $rel; continue; }
        $n++;
    }
    $zip->close();
    return [$n, $skipped];
}

// The whole restore: validate -> safety backup -> load -> verify.
//   $opts: password, restore_files (bool), skip_safety (bool, only when the database is unusable)
// Returns ['ok'=>bool, 'error'=>string, 'steps'=>[...], 'safety'=>file name|null, 'counts'=>[], 'warnings'=>[]]
function bk_restore_backup($zipPath, $opts = []) {
    @set_time_limit(600);
    $password = isset($opts['password']) ? (string)$opts['password'] : '';
    $out = ['ok' => false, 'error' => '', 'steps' => [], 'safety' => null, 'counts' => [], 'warnings' => []];

    list($man, $err) = bk_read_manifest($zipPath);
    if (!$man) { $out['error'] = $err; return $out; }
    $out['steps'][] = 'Backup file checked (' . bk_when($man['created_ts'] ?? 0) . ').';

    $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tcm-rs-' . bin2hex(random_bytes(5));
    @mkdir($tmpDir, 0775, true);
    $cleanup = function () use ($tmpDir) { foreach (glob($tmpDir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) @unlink($f); @rmdir($tmpDir); };

    // 1) unpack + verify every database file BEFORE touching anything
    $sqlFiles = [];
    foreach ($man['databases'] as $entry => $info) {
        list($p, $e) = bk_extract_sql($zipPath, $entry, $info['sha256'], $password, $tmpDir);
        if (!$p) { $out['error'] = $e; $cleanup(); return $out; }
        $sqlFiles[$info['db']] = $p;
    }
    $out['steps'][] = 'Backup contents verified (checksums match).';

    // 2) safety backup of what exists now
    if (empty($opts['skip_safety'])) {
        $safe = bk_create_backup('pre-restore', ['keep_local' => true]);
        if ($safe['ok']) {
            $out['safety'] = $safe['file'];
            $out['steps'][] = 'Current data saved first as ' . $safe['file'] . '.';
        } else {
            list($mm, $ee) = bk_connect(bk_main_db());
            if ($mm) {
                $out['error'] = 'The safety backup of the current data failed, so nothing was changed. (' . $safe['error'] . ')';
                $cleanup(); return $out;
            }
            $out['warnings'][] = 'The current database could not be read, so no safety copy of it was made.';
        }
    }

    // 3) load
    foreach ($sqlFiles as $dbName => $file) {
        list($ok, $e, $counts) = bk_restore_sql_file($file, $dbName);
        if (!$ok) { $out['error'] = $e; $cleanup(); return $out; }
        if ($e !== '') $out['warnings'][] = $e;
        $out['counts'][$dbName] = $counts;
        $out['steps'][] = "Database '{$dbName}' restored (" . count($counts) . ' tables).';
        // verify against the manifest
        foreach ($man['databases'] as $info) {
            if ($info['db'] !== $dbName) continue;
            foreach ($info['tables'] as $t => $n) {
                if (!isset($counts[$t]) || $counts[$t] !== (int)$n) $out['warnings'][] = "Table {$t}: expected {$n} record(s), found " . ($counts[$t] ?? 'none') . '.';
            }
        }
    }

    // 4) system files (optional)
    if (!empty($opts['restore_files'])) {
        list($n, $skipped) = bk_restore_files($zipPath, $password, BK_APP_DIR);
        $out['steps'][] = "System files restored: {$n} file(s).";
        foreach ($skipped as $s) $out['warnings'][] = 'Skipped: ' . $s;
    }

    $cleanup();
    $out['ok'] = true;
    bk_log_append(['time' => date('c'), 'ts' => time(), 'type' => 'restore', 'ok' => false, 'file' => basename($zipPath), 'size' => 0, 'fp' => '', 'local' => null, 'mirror' => null, 'mail' => null, 'tables' => [], 'error' => '']);
    return $out;
}

// Restore directly from a plain .sql file (older exports, phpMyAdmin dumps). Used by the command-line tool.
function bk_restore_plain_sql($sqlPath, $db = null) {
    $db = $db ?: bk_main_db();
    $safe = bk_create_backup('pre-restore', ['keep_local' => true]);
    list($ok, $e, $counts) = bk_restore_sql_file($sqlPath, $db);
    return ['ok' => $ok, 'error' => $e, 'counts' => $counts, 'safety' => $safe['ok'] ? $safe['file'] : null];
}

// ---------------------------------------------------------------------
// Access check used by the emergency tools: only from the computer that runs the system
// ---------------------------------------------------------------------
function bk_is_local_request() {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    return in_array($ip, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true);
}
