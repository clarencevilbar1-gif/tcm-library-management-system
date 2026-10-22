<?php
// =====================================================================
// EMERGENCY RESTORE — for when the normal system cannot be used
// (cannot log in, database errors, empty or damaged database ...).
//
//  * It does NOT need a login, because the login itself needs the database.
//  * To stay safe it only answers on the computer that runs the system
//    (http://localhost/...), never over the network.
//  * It does not use the internet (no external styles or scripts).
//  * It only needs MySQL to be running.
// =====================================================================
require_once __DIR__ . '/backup_functions.php';
session_start();
header('Cache-Control: no-store');

function eh($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if (!bk_is_local_request()) {
    http_response_code(403);
    echo '<!DOCTYPE html><meta charset="utf-8"><title>Emergency restore</title><body style="font-family:Segoe UI,Arial;padding:2rem">'
       . '<h2>This page only works on the computer that runs the system.</h2>'
       . '<p>Open <strong>http://localhost/' . eh(basename(BK_APP_DIR)) . '/emergency_restore.php</strong> on that computer.</p></body>';
    exit();
}

if (empty($_SESSION['er_csrf'])) $_SESSION['er_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['er_csrf'];

$s = bk_settings_load();
$dirs = array_values(array_unique(array_filter([$s['local_dir'], bk_base_dir()])));
$msg = []; $result = null; $chosen = '';

function er_find($dirs, $name) {
    if (!preg_match('/^TCM-Library-Backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}_[a-z-]+\.zip$/', $name)) return null;
    foreach ($dirs as $d) { $p = rtrim($d, '/\\') . DIRECTORY_SEPARATOR . $name; if (is_file($p)) return $p; }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !hash_equals($csrf, $_POST['csrf'])) {
        $msg[] = ['bad', 'The page was out of date. Nothing was changed. Please try again.'];
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'upload' && isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            list($man, $err) = bk_read_manifest($_FILES['file']['tmp_name']);
            if (!$man) { $msg[] = ['bad', $err]; }
            else {
                list($ok, $e2) = bk_ensure_dir($s['local_dir']);
                $name = 'TCM-Library-Backup_' . date('Y-m-d_H-i-s', (int)($man['created_ts'] ?? time())) . '_imported.zip';
                $dest = rtrim($s['local_dir'], '/\\') . DIRECTORY_SEPARATOR . $name;
                if ($ok && !file_exists($dest) && move_uploaded_file($_FILES['file']['tmp_name'], $dest)) $msg[] = ['ok', 'Added to the list: ' . $name];
                else $msg[] = ['bad', 'The file could not be added to the backup folder.' . ($ok ? '' : ' ' . $e2)];
            }
        } elseif ($action === 'restore') {
            $chosen = $_POST['file'] ?? '';
            $path = er_find($dirs, $chosen);
            if (!$path) $msg[] = ['bad', 'Choose one of the backup files from the list.'];
            elseif (trim($_POST['confirm'] ?? '') !== 'RESTORE') $msg[] = ['bad', 'To confirm, type the word RESTORE in capital letters. Nothing was changed.'];
            else {
                $result = bk_restore_backup($path, [
                    'password' => $_POST['password'] ?? '',
                    'restore_files' => !empty($_POST['restore_files']),
                    'skip_safety' => false,
                ]);
            }
        }
    }
}

// Go to a fresh page after a form was processed, so that reload can never repeat a restore.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['er_done'] = ['msg' => $msg, 'result' => $result, 'chosen' => $chosen];
    header('Location: emergency_restore.php');
    exit();
}
if (!empty($_SESSION['er_done'])) {
    $msg = $_SESSION['er_done']['msg']; $result = $_SESSION['er_done']['result']; $chosen = $_SESSION['er_done']['chosen'];
    unset($_SESSION['er_done']);
}

list($m, $dberr) = bk_connect(null);   // is the MySQL server running at all?
$all = [];
foreach ($dirs as $d) foreach (bk_list_backups($d) as $b) { $b['dir'] = $d; $all[$b['name']] = $b; }
usort($all, function ($a, $b) { return $b['ts'] <=> $a['ts']; });
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Emergency restore — TCM Law Library</title>
<style>
    body { font-family: "Segoe UI", Arial, sans-serif; background: #f6f4f9; margin: 0; color: #222; }
    header { background: #4B2E83; color: #fff; padding: 1rem 1.5rem; }
    header h1 { margin: 0; font-size: 1.25rem; }
    header small { color: #D4A72C; }
    main { max-width: 820px; margin: 1.5rem auto; padding: 0 1rem 3rem; }
    .card { background: #fff; border-radius: 12px; padding: 1.2rem 1.4rem; box-shadow: 0 3px 12px rgba(75,46,131,.1); margin-bottom: 1.2rem; }
    .ok { background: #e7f6ec; border: 1px solid #b6e2c3; color: #1d6b3a; padding: .6rem .8rem; border-radius: 8px; margin-bottom: .7rem; }
    .bad { background: #fdecea; border: 1px solid #f5c2c0; color: #8a2a24; padding: .6rem .8rem; border-radius: 8px; margin-bottom: .7rem; }
    .warn { background: #fff8e6; border: 1px solid #f0e0ad; color: #7a5b00; padding: .6rem .8rem; border-radius: 8px; margin-bottom: .7rem; }
    table { border-collapse: collapse; width: 100%; }
    td, th { padding: .45rem .5rem; border-bottom: 1px solid #eee; text-align: left; font-size: .92rem; }
    label.row { display: block; cursor: pointer; }
    input[type=text], input[type=password], input[type=file] { padding: .4rem .5rem; border: 1px solid #bbb; border-radius: 6px; width: 100%; box-sizing: border-box; }
    button { background: #b02a2a; color: #fff; border: 0; border-radius: 8px; padding: .6rem 1.1rem; font-weight: 600; cursor: pointer; }
    button.alt { background: #4B2E83; }
    code { background: #eee; padding: 0 .3rem; border-radius: 4px; }
    .small { font-size: .85rem; color: #666; }
</style>
</head>
<body>
<header><h1>Emergency restore <small>— TCM College of Law Library</small></h1></header>
<main>

<?php foreach ($msg as $x): ?><div class="<?php echo $x[0]; ?>"><?php echo eh($x[1]); ?></div><?php endforeach; ?>

<?php if ($result !== null): ?>
    <div class="card">
        <?php if ($result['ok']): ?>
            <div class="ok"><strong>Restore finished.</strong> The data was restored from <?php echo eh($chosen); ?>.</div>
        <?php else: ?>
            <div class="bad"><strong>The restore did not go through.</strong><br><?php echo eh($result['error']); ?></div>
        <?php endif; ?>
        <ul><?php foreach ($result['steps'] as $st): ?><li><?php echo eh($st); ?></li><?php endforeach; ?></ul>
        <?php foreach ($result['warnings'] as $w): ?><div class="warn"><?php echo eh($w); ?></div><?php endforeach; ?>
        <?php if ($result['ok']): ?><p><a href="login.php">Go to the login page</a> and check that everything looks right.</p><?php endif; ?>
    </div>
<?php endif; ?>

<?php if (!$m): ?>
    <div class="bad"><strong>The database server (MySQL) is not running or cannot be reached.</strong><br>
        <?php echo eh($dberr); ?><br>Open the XAMPP Control Panel, press <strong>Start</strong> next to <strong>MySQL</strong>, then reload this page.</div>
<?php else: $m->close(); ?>
    <div class="ok">The database server is running.</div>
<?php endif; ?>

<div class="card">
    <h2 style="margin-top:0">1. Choose the backup to restore</h2>
    <?php if (!$all): ?>
        <div class="warn">No backup files were found in:<br>
            <?php foreach ($dirs as $d): ?><code><?php echo eh($d); ?></code><br><?php endforeach; ?>
            Copy a backup .zip file into the first folder (or add it below), then reload this page.</div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf" value="<?php echo eh($csrf); ?>">
        <input type="hidden" name="action" value="restore">
        <?php if ($all): ?>
        <table>
            <tr><th></th><th>Date &amp; time</th><th>Type</th><th>Size</th></tr>
            <?php
                // preselect the newest real backup (not the safety copy that a restore leaves behind)
                $default = ''; foreach ($all as $b0) { if ($b0['type'] !== 'pre-restore') { $default = $b0['name']; break; } }
                if ($default === '' && $all) $default = reset($all)['name'];
                if ($chosen === '') $chosen = $default;
                $i = 0; foreach ($all as $b): ?>
                <tr><td><input type="radio" name="file" id="f<?php echo $i; ?>" value="<?php echo eh($b['name']); ?>" <?php echo ($chosen === $b['name']) ? 'checked' : ''; ?>></td>
                    <td><label for="f<?php echo $i; ?>"><?php echo eh(bk_when($b['ts'])); ?> <span class="small">(<?php echo eh(bk_ago($b['ts'])); ?>)</span></label></td>
                    <td><?php echo eh($b['type']); ?></td><td><?php echo eh(bk_human_size($b['size'])); ?></td></tr>
            <?php $i++; if ($i >= 40) break; endforeach; ?>
        </table>
        <?php endif; ?>

        <h2>2. Options</h2>
        <p><label><input type="checkbox" name="restore_files" value="1"> Also put back the <strong>program files</strong> (use this if pages are missing or broken; it overwrites the PHP files with the ones in the backup)</label></p>
        <p>Password of the backup <span class="small">(only if the backups are password-protected)</span><br><input type="password" name="password" autocomplete="off" style="max-width:320px"></p>

        <h2>3. Confirm</h2>
        <div class="warn">The data in the system is replaced by the backup. The current data is saved first as a safety backup, and nothing is replaced unless the backup loads completely.</div>
        <p>Type <strong>RESTORE</strong> to confirm:<br><input type="text" name="confirm" autocomplete="off" style="max-width:220px"></p>
        <button type="submit"<?php echo $all ? '' : ' disabled'; ?>>Restore now</button>
    </form>
</div>

<div class="card">
    <h2 style="margin-top:0">Backup file from somewhere else?</h2>
    <p class="small">Downloaded from your e-mail or copied from a USB drive: add it to the list first.</p>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?php echo eh($csrf); ?>">
        <input type="hidden" name="action" value="upload">
        <input type="file" name="file" accept=".zip" required style="margin-bottom:.6rem">
        <button type="submit" class="alt">Add to list</button>
    </form>
</div>

<p class="small">If this page does not work either, double-click <strong>restore.bat</strong> in the system folder, or follow RECOVERY-GUIDE.txt.</p>
</main>
</body>
</html>
