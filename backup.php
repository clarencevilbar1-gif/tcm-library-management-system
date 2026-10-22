<?php
include('auth.php');
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/backup_functions.php';

function bh($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['bk_csrf'])) $_SESSION['bk_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['bk_csrf'];

$flash = [];          // [type, text]
$s = bk_settings_load();
$view = 'main';
$restoreName = '';
$restoreResult = null;

$type_labels = [
    'manual' => 'Manual', 'logout' => 'At logout', 'auto' => 'Automatic', 'scheduled' => 'Scheduled',
    'pre-restore' => 'Before a restore', 'imported' => 'Added from a file',
];

function bk_valid_name($n) {
    return (bool)preg_match('/^TCM-Library-Backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}_[a-z-]+\.zip$/', $n);
}

// ------------------------------------------------------------------ actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if (!isset($_POST['csrf']) || !hash_equals($csrf, $_POST['csrf'])) {
        $flash[] = ['danger', 'The page was out of date, so nothing was changed. Please try again.'];
        $action = '';
    }

    // ---- save settings
    if ($action === 'save_settings' || $action === 'test_email') {
        $new = $s;
        $errors = [];

        $local = trim($_POST['local_dir'] ?? '');
        if ($local === '') $local = bk_base_dir();
        list($ok, $err) = bk_ensure_dir($local);
        if (!$ok) $errors[] = 'Backup folder: ' . $err; else $new['local_dir'] = $local;

        $mirror = trim($_POST['mirror_dir'] ?? '');
        if ($mirror !== '') {
            list($ok, $err) = bk_ensure_dir($mirror);
            if (!$ok) $errors[] = 'Second copy folder: ' . $err;
        }
        $new['mirror_dir'] = $mirror;

        $new['auto_enabled'] = !empty($_POST['auto_enabled']);
        $new['auto_hours'] = max(1, min(168, (int)($_POST['auto_hours'] ?? 24)));
        $new['auto_email'] = !empty($_POST['auto_email']);
        $new['keep'] = max(5, min(1000, (int)($_POST['keep'] ?? 60)));

        $to = trim($_POST['mail_to'] ?? '');
        if ($to !== '') {
            list($rcpt, $bad) = bk_parse_recipients($to);
            if ($bad !== null) $errors[] = 'Receiving e-mail: "' . $bad . '" is not a valid e-mail address.';
            else $to = implode(', ', $rcpt);
        }
        $new['mail_to'] = $to;

        $user = trim($_POST['smtp_user'] ?? '');
        if ($user !== '' && !filter_var($user, FILTER_VALIDATE_EMAIL)) $errors[] = 'Sending Gmail address: "' . $user . '" is not a valid e-mail address.';
        $new['smtp_user'] = $user;
        if (!empty($_POST['smtp_pass_clear'])) $new['smtp_pass'] = '';
        elseif (trim($_POST['smtp_pass'] ?? '') !== '') $new['smtp_pass'] = trim($_POST['smtp_pass']);

        $host = trim($_POST['smtp_host'] ?? '');
        $new['smtp_host'] = $host !== '' ? $host : 'smtp.gmail.com';
        $new['smtp_port'] = (int)($_POST['smtp_port'] ?? 587) ?: 587;
        $new['smtp_secure'] = in_array($_POST['smtp_secure'] ?? 'tls', ['tls', 'ssl', 'none'], true) ? $_POST['smtp_secure'] : 'tls';

        if (!empty($_POST['zip_password_clear'])) $new['zip_password'] = '';
        elseif (($_POST['zip_password'] ?? '') !== '') {
            if (strlen($_POST['zip_password']) < 6) $errors[] = 'The backup password must be at least 6 characters.';
            else $new['zip_password'] = $_POST['zip_password'];
        }

        if ($errors) {
            foreach ($errors as $e) $flash[] = ['danger', $e];
            $flash[] = ['warning', 'Nothing was saved. Fix the problem above and press Save again.'];
        } else {
            list($ok, $err) = bk_settings_save($new);
            if ($ok) {
                $s = $new;
                $flash[] = ['success', 'Settings saved.'];
                if ($action === 'test_email') {
                    list($okm, $msg) = bk_send_mail($s['mail_to'], 'TCM Law Library — test e-mail',
                        "This is a test message from the College of Law Library system.\nIf you can read this, backups can be e-mailed to this address.\n");
                    $flash[] = [$okm ? 'success' : 'danger', $okm ? 'Test e-mail sent. ' . $msg . ' — check the inbox (and Spam) in a minute.' : $msg];
                }
            } else {
                $flash[] = ['danger', $err];
            }
        }
    }

    // ---- add a backup file (from a USB drive, an e-mail download...) to the list
    if ($action === 'upload_backup') {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $code = isset($_FILES['file']) ? $_FILES['file']['error'] : -1;
            $flash[] = ['danger', ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE)
                ? 'That file is bigger than the upload limit of this computer (see upload_max_filesize in php.ini). Copy it into the backup folder by hand instead.'
                : 'No file was received. Choose a backup .zip file and try again.'];
        } else {
            list($man, $err) = bk_read_manifest($_FILES['file']['tmp_name']);
            if (!$man) {
                $flash[] = ['danger', $err];
            } else {
                list($ok, $e2) = bk_ensure_dir($s['local_dir']);
                $orig = basename($_FILES['file']['name']);
                $dest = bk_valid_name($orig) ? $orig : 'TCM-Library-Backup_' . date('Y-m-d_H-i-s', (int)($man['created_ts'] ?? time())) . '_imported.zip';
                $path = rtrim($s['local_dir'], '/\\') . DIRECTORY_SEPARATOR . $dest;
                if (!$ok) $flash[] = ['danger', $e2];
                elseif (file_exists($path)) $flash[] = ['warning', 'A backup with this name is already in the list: ' . $dest];
                elseif (!move_uploaded_file($_FILES['file']['tmp_name'], $path)) $flash[] = ['danger', 'The file could not be saved into the backup folder.'];
                else $flash[] = ['success', 'Added to the list: ' . $dest . ' (made ' . bk_when($man['created_ts'] ?? 0) . '). Press Restore next to it when you are ready.'];
            }
        }
    }

    // ---- restore
    if ($action === 'restore') {
        $name = $_POST['file'] ?? '';
        $restoreName = $name;
        if (!bk_valid_name($name) || !is_file(rtrim($s['local_dir'], '/\\') . DIRECTORY_SEPARATOR . $name)) {
            $flash[] = ['danger', 'That backup file was not found.'];
            $restoreName = '';
        } elseif (trim($_POST['confirm'] ?? '') !== 'RESTORE') {
            $flash[] = ['danger', 'To confirm, type the word RESTORE in capital letters. Nothing was changed.'];
            $view = 'restore';
        } else {
            $restoreResult = bk_restore_backup(rtrim($s['local_dir'], '/\\') . DIRECTORY_SEPARATOR . $name, ['password' => $_POST['password'] ?? '']);
            $view = 'restored';
        }
    }
}

// After a form was processed, go to a fresh page so that pressing reload can never send it again
// (a repeated restore, for example).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($view === 'restored') {
        $_SESSION['bk_restored'] = ['result' => $restoreResult, 'name' => $restoreName];
        header('Location: backup.php?done=1');
        exit();
    }
    if ($view === 'main') {
        $_SESSION['bk_flash'] = $flash;
        header('Location: backup.php');
        exit();
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_SESSION['bk_flash'])) { $flash = array_merge($flash, $_SESSION['bk_flash']); }
    unset($_SESSION['bk_flash']);
    if (isset($_GET['done']) && !empty($_SESSION['bk_restored'])) {
        $restoreResult = $_SESSION['bk_restored']['result'];
        $restoreName = $_SESSION['bk_restored']['name'];
        $view = 'restored';
    }
    unset($_SESSION['bk_restored']);
}

if ($view === 'main' && isset($_GET['restore'])) {
    $restoreName = $_GET['restore'];
    if (bk_valid_name($restoreName) && is_file(rtrim($s['local_dir'], '/\\') . DIRECTORY_SEPARATOR . $restoreName)) $view = 'restore';
    else { $flash[] = ['danger', 'That backup file was not found.']; $restoreName = ''; }
}

list($conn_status, $conn_err) = bk_connect(bk_main_db());
$status = bk_status($conn_status ?: null);
$backups = bk_list_backups($s['local_dir']);
$emailOk = bk_email_configured($s);
$mailerOk = bk_mailer_available();
$stateText = [
    'none'    => ['danger',  'bi-exclamation-octagon-fill', 'No backup has been made yet'],
    'ok'      => ['success', 'bi-shield-check',             'Everything is backed up'],
    'changed' => ['warning', 'bi-shield-exclamation',       'There are changes that are not backed up yet'],
    'overdue' => ['danger',  'bi-shield-exclamation',       'Backup is overdue — please back up now'],
][$status['state']];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup &amp; Restore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/header.css">
    <style>
        :root {
            --tcm-purple: #4B2E83;
            --tcm-purple-dark: #35205E;
            --tcm-gold: #D4A72C;
            --tcm-gold-dark: #B88F22;
        }
        body {
            background: linear-gradient(180deg, #f6f4f9 0%, #ece5f3 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        .page-heading { color: var(--tcm-purple-dark); font-weight: 700; }
        .card-tcm {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 16px rgba(75, 46, 131, 0.08);
            overflow: hidden;
        }
        .card-header-purple { background-color: var(--tcm-purple); color: #fff; font-weight: 700; }
        .card-header-gold { background-color: var(--tcm-gold); color: var(--tcm-purple-dark); font-weight: 700; }
        .btn-tcm-purple { background-color: var(--tcm-purple); color: #fff; font-weight: 600; border: none; }
        .btn-tcm-purple:hover { background-color: var(--tcm-purple-dark); color: #fff; }
        .btn-tcm-gold { background-color: var(--tcm-gold); color: var(--tcm-purple-dark); font-weight: 600; border: none; }
        .btn-tcm-gold:hover { background-color: var(--tcm-gold-dark); color: var(--tcm-purple-dark); }

        .status-card { border-left: 6px solid var(--tcm-purple); }
        .status-card.is-success { border-left-color: #2e8b57; }
        .status-card.is-warning { border-left-color: var(--tcm-gold); }
        .status-card.is-danger  { border-left-color: #c0392b; }
        .status-icon { font-size: 2.4rem; line-height: 1; }
        .is-success .status-icon { color: #2e8b57; }
        .is-warning .status-icon { color: var(--tcm-gold-dark); }
        .is-danger  .status-icon { color: #c0392b; }

        .section-title { font-weight: 700; color: var(--tcm-purple-dark); font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.04em; }
        .form-text { font-size: 0.8rem; }
        .path-text { font-family: Consolas, 'Courier New', monospace; font-size: 0.85rem; word-break: break-all; }
        .table-backups th { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em; color: #fff; background: var(--tcm-purple); border: none; }
        .table-backups td { vertical-align: middle; }
        .badge-type { background: rgba(75, 46, 131, 0.1); color: var(--tcm-purple-dark); font-weight: 600; }
        .recovery-step { border-left: 3px solid var(--tcm-gold); padding-left: 0.9rem; }
        .accordion-button:not(.collapsed) { background: rgba(75, 46, 131, 0.08); color: var(--tcm-purple-dark); }
        .accordion-button:focus { box-shadow: 0 0 0 0.2rem rgba(75, 46, 131, 0.2); }
    </style>
</head>
<body>

<?php $nav_mode = 'page'; include('navbar.php'); ?>

<div class="container mt-4 mb-5" style="max-width: 1000px;">

    <h4 class="page-heading mb-3"><i class="bi bi-shield-check me-2"></i>Backup &amp; Restore</h4>

    <?php foreach ($flash as $f): ?>
        <div class="alert alert-<?php echo bh($f[0]); ?> py-2"><?php echo bh($f[1]); ?></div>
    <?php endforeach; ?>

<?php if ($view === 'restored' && $restoreResult !== null): ?>
    <!-- ============================ RESTORE RESULT ============================ -->
    <div class="card card-tcm">
        <div class="card-header <?php echo $restoreResult['ok'] ? 'card-header-purple' : 'bg-danger text-white fw-bold'; ?>">
            <?php echo $restoreResult['ok'] ? 'Restore finished' : 'The restore did not go through'; ?>
        </div>
        <div class="card-body">
            <?php if ($restoreResult['ok']): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle-fill me-1"></i> The data was restored from <strong><?php echo bh($restoreName); ?></strong>.</div>
            <?php else: ?>
                <div class="alert alert-danger"><i class="bi bi-x-octagon-fill me-1"></i> <?php echo bh($restoreResult['error']); ?></div>
            <?php endif; ?>
            <ul class="mb-3">
                <?php foreach ($restoreResult['steps'] as $st): ?><li><?php echo bh($st); ?></li><?php endforeach; ?>
            </ul>
            <?php foreach ($restoreResult['warnings'] as $w): ?><div class="alert alert-warning py-2"><?php echo bh($w); ?></div><?php endforeach; ?>
            <?php if ($restoreResult['ok'] && $restoreResult['counts']): ?>
                <div class="table-responsive mb-3">
                    <table class="table table-sm w-auto">
                        <thead><tr><th>Table</th><th class="text-end">Records now</th></tr></thead>
                        <tbody>
                        <?php foreach ($restoreResult['counts'] as $db => $tables): foreach ($tables as $t => $n): ?>
                            <tr><td><?php echo bh($t); ?></td><td class="text-end"><?php echo (int)$n; ?></td></tr>
                        <?php endforeach; endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small">Tip: open the Book Inventory, Student Records and Transaction History once to check that everything looks right.</p>
            <?php endif; ?>
            <a href="backup.php" class="btn btn-tcm-purple">Back to Backup &amp; Restore</a>
            <a href="index.php" class="btn btn-outline-secondary">Main Menu</a>
        </div>
    </div>

<?php elseif ($view === 'restore'): ?>
    <!-- ============================ RESTORE: CONFIRM ============================ -->
    <?php
        $rpath = rtrim($s['local_dir'], '/\\') . DIRECTORY_SEPARATOR . $restoreName;
        list($man, $merr) = bk_read_manifest($rpath);
        $now = [];
        if ($conn_status) {
            foreach (($man['databases'] ?? []) as $info) {
                if ($info['db'] !== bk_main_db()) continue;
                foreach ($info['tables'] as $t => $n) {
                    $r = $conn_status->query('SELECT COUNT(*) FROM ' . bk_q($t));
                    $now[$t] = $r ? (int)$r->fetch_row()[0] : null;
                }
            }
        }
    ?>
    <div class="card card-tcm">
        <div class="card-header bg-danger text-white fw-bold"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore from a backup</div>
        <div class="card-body">
            <?php if (!$man): ?>
                <div class="alert alert-danger"><?php echo bh($merr); ?></div>
                <a href="backup.php" class="btn btn-outline-secondary">Back</a>
            <?php else: ?>
                <p class="mb-1"><strong>Backup file:</strong> <span class="path-text"><?php echo bh($restoreName); ?></span></p>
                <p class="mb-3"><strong>Made on:</strong> <?php echo bh(bk_when($man['created_ts'] ?? 0)); ?>
                    (<?php echo bh(isset($type_labels[$man['type'] ?? '']) ? $type_labels[$man['type']] : ($man['type'] ?? '')); ?>)</p>

                <div class="table-responsive mb-3">
                    <table class="table table-sm w-auto align-middle">
                        <thead><tr><th>Records</th><th class="text-end">In this backup</th><th class="text-end">In the system now</th></tr></thead>
                        <tbody>
                        <?php foreach (($man['databases'] ?? []) as $info): if ($info['db'] !== bk_main_db()) continue; foreach ($info['tables'] as $t => $n): ?>
                            <tr><td><?php echo bh($t); ?></td><td class="text-end"><?php echo (int)$n; ?></td><td class="text-end"><?php echo isset($now[$t]) && $now[$t] !== null ? (int)$now[$t] : '—'; ?></td></tr>
                        <?php endforeach; endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-warning">
                    <strong>Everything entered after <?php echo bh(bk_when($man['created_ts'] ?? 0)); ?> will be replaced</strong> by what is in this backup.
                    Don't worry: the system first saves the data it has right now as a safety backup (type "Before a restore"),
                    and nothing is replaced unless the whole backup loads without errors.
                </div>

                <form method="POST">
                    <input type="hidden" name="csrf" value="<?php echo bh($csrf); ?>">
                    <input type="hidden" name="action" value="restore">
                    <input type="hidden" name="file" value="<?php echo bh($restoreName); ?>">
                    <?php if (!empty($man['encrypted'])): ?>
                        <div class="mb-3" style="max-width: 380px;">
                            <label class="form-label">This backup is password-protected. Password:</label>
                            <input type="password" name="password" class="form-control" autocomplete="off" required>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3" style="max-width: 380px;">
                        <label class="form-label">Type <strong>RESTORE</strong> to confirm:</label>
                        <input type="text" name="confirm" class="form-control" autocomplete="off" required>
                    </div>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore now</button>
                    <a href="backup.php" class="btn btn-outline-secondary">Cancel</a>
                </form>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <!-- ============================ STATUS ============================ -->
    <div class="card card-tcm status-card is-<?php echo bh($stateText[0]); ?> mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div class="status-icon"><i class="bi <?php echo bh($stateText[1]); ?>"></i></div>
                <div class="flex-grow-1">
                    <div class="fw-bold fs-5"><?php echo bh($stateText[2]); ?></div>
                    <?php if ($status['last_ts']): ?>
                        <div class="text-muted">Last backup: <strong><?php echo bh($status['last_when']); ?></strong> (<?php echo bh($status['last_ago']); ?>)</div>
                        <div class="small text-muted">
                            Saved on this computer: <?php echo $status['local_when'] ? bh($status['local_when']) : 'never'; ?>
                            &nbsp;·&nbsp; E-mailed: <?php echo $status['mail_when'] ? bh($status['mail_when']) . ' to ' . bh($status['mail_to']) : 'never'; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-muted">Press <strong>Back up now</strong> to make the first one.</div>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-tcm-gold" id="btn-backup-now"><i class="bi bi-cloud-arrow-up me-1"></i>Back up now</button>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$conn_status): ?>
        <div class="alert alert-danger"><strong>The database cannot be reached right now:</strong> <?php echo bh($conn_err); ?><br>
            If MySQL is running and the data looks damaged, use the recovery steps at the bottom of this page.</div>
    <?php endif; ?>

    <!-- ============================ BACKUP LIST ============================ -->
    <div class="card card-tcm mb-4">
        <div class="card-header card-header-purple d-flex justify-content-between align-items-center">
            <span><i class="bi bi-folder2-open me-1"></i>Backups on this computer</span>
            <span class="small fw-normal"><?php echo count($backups); ?> file(s)</span>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-3">Folder: <span class="path-text"><?php echo bh($s['local_dir']); ?></span></p>
            <?php if (!$backups): ?>
                <p class="text-muted mb-0">No backup files in this folder yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-backups table-hover mb-0">
                        <thead><tr><th>Date &amp; time</th><th>Type</th><th>Size</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($backups, 0, 30) as $b): ?>
                            <tr>
                                <td><?php echo bh(bk_when($b['ts'])); ?> <span class="text-muted small">(<?php echo bh(bk_ago($b['ts'])); ?>)</span></td>
                                <td><span class="badge badge-type"><?php echo bh(isset($type_labels[$b['type']]) ? $type_labels[$b['type']] : $b['type']); ?></span></td>
                                <td><?php echo bh(bk_human_size($b['size'])); ?></td>
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-outline-secondary" href="backup_download.php?file=<?php echo urlencode($b['name']); ?>"><i class="bi bi-download me-1"></i>Download</a>
                                    <a class="btn btn-sm btn-outline-danger" href="backup.php?restore=<?php echo urlencode($b['name']); ?>"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($backups) > 30): ?><p class="small text-muted mt-2 mb-0">Showing the newest 30. Older files are still in the folder.</p><?php endif; ?>
            <?php endif; ?>

            <hr>
            <form method="POST" enctype="multipart/form-data" class="row g-2 align-items-end">
                <input type="hidden" name="csrf" value="<?php echo bh($csrf); ?>">
                <input type="hidden" name="action" value="upload_backup">
                <div class="col-md-8">
                    <label class="form-label small mb-1">Have a backup file from somewhere else (USB drive, e-mail download)? Add it to the list:</label>
                    <input type="file" name="file" class="form-control form-control-sm" accept=".zip" required>
                </div>
                <div class="col-md-4"><button type="submit" class="btn btn-sm btn-tcm-purple w-100"><i class="bi bi-box-arrow-in-down me-1"></i>Add to list</button></div>
            </form>
        </div>
    </div>

    <!-- ============================ SETTINGS ============================ -->
    <form method="POST" autocomplete="off">
    <input type="hidden" name="csrf" value="<?php echo bh($csrf); ?>">
    <div class="card card-tcm mb-4" id="settings">
        <div class="card-header card-header-gold"><i class="bi bi-gear me-1"></i>Settings</div>
        <div class="card-body">

            <div class="section-title mb-2">Where backups are saved</div>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Backup folder (this computer)</label>
                    <input type="text" name="local_dir" class="form-control" value="<?php echo bh($s['local_dir']); ?>">
                    <div class="form-text">Every backup is a file named with its date and time. The folder is created if it does not exist.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Second copy folder <span class="text-muted">(optional)</span></label>
                    <input type="text" name="mirror_dir" class="form-control" value="<?php echo bh($s['mirror_dir']); ?>" placeholder="e.g. E:\LibraryBackups  or  G:\My Drive\LibraryBackups">
                    <div class="form-text">A copy of each backup goes here too — a USB drive, or a folder synced by <em>Google Drive for desktop</em> (that puts it in the cloud automatically).</div>
                </div>
            </div>

            <div class="section-title mb-2">Automatic backups</div>
            <div class="row g-3 mb-4">
                <div class="col-md-12">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" name="auto_enabled" id="auto_enabled" value="1" <?php echo $s['auto_enabled'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="auto_enabled">Back up automatically when the Main Menu is opened and the last backup is old (and something has changed)</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">"Old" means more than</label>
                    <div class="input-group"><input type="number" name="auto_hours" min="1" max="168" class="form-control" value="<?php echo (int)$s['auto_hours']; ?>"><span class="input-group-text">hours</span></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Keep the newest</label>
                    <div class="input-group"><input type="number" name="keep" min="5" max="1000" class="form-control" value="<?php echo (int)$s['keep']; ?>"><span class="input-group-text">backups</span></div>
                    <div class="form-text">Older ones are removed automatically.</div>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="auto_email" id="auto_email" value="1" <?php echo $s['auto_email'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="auto_email">Also e-mail the automatic backups</label>
                    </div>
                </div>
            </div>

            <div class="section-title mb-2">E-mail (Gmail) <?php echo $emailOk ? '<span class="badge bg-success ms-1">ready</span>' : '<span class="badge bg-secondary ms-1">not set up</span>'; ?></div>
            <?php if (!$mailerOk): ?>
                <div class="alert alert-warning py-2 small">The e-mail component is not installed on this computer yet. Open a terminal in the project folder and run <code>composer install</code>. Saving to this computer works without it.</div>
            <?php endif; ?>
            <div class="row g-3 mb-2">
                <div class="col-md-6">
                    <label class="form-label">Receiving e-mail <span class="text-muted">(where backups are sent)</span></label>
                    <input type="text" name="mail_to" class="form-control" value="<?php echo bh($s['mail_to']); ?>" placeholder="leave empty for now — e.g. collegeoflaw@gmail.com">
                    <div class="form-text">Can be changed any time. You can also type a different address each time you back up.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Sending Gmail address</label>
                    <input type="text" name="smtp_user" class="form-control" value="<?php echo bh($s['smtp_user']); ?>" placeholder="e.g. collegeoflaw@gmail.com">
                    <div class="form-text">Can be the same address as the receiving one.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Gmail App Password</label>
                    <input type="password" name="smtp_pass" class="form-control" value="" placeholder="<?php echo $s['smtp_pass'] !== '' ? '•••••••• saved — leave empty to keep it' : '16-letter App Password'; ?>" autocomplete="new-password">
                    <?php if ($s['smtp_pass'] !== ''): ?>
                        <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="smtp_pass_clear" id="smtp_pass_clear" value="1"><label class="form-check-label small" for="smtp_pass_clear">Remove the saved App Password</label></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="accordion mb-3" id="gmailHelp">
                <div class="accordion-item">
                    <h2 class="accordion-header"><button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#gmailHelpBody">How to get a Gmail App Password (one time, about 3 minutes)</button></h2>
                    <div id="gmailHelpBody" class="accordion-collapse collapse" data-bs-parent="#gmailHelp">
                        <div class="accordion-body small">
                            <p class="mb-2">Gmail does not let other programs use the normal password. It gives a separate 16-letter <strong>App Password</strong> instead. It can be removed at any time without changing the real password.</p>
                            <ol class="mb-2">
                                <li>Sign in to the Gmail account that will <strong>send</strong> the backups.</li>
                                <li>Open <strong>myaccount.google.com</strong> → <strong>Security</strong>.</li>
                                <li>Turn on <strong>2-Step Verification</strong> (needed once; Google asks for a phone number).</li>
                                <li>Search for <strong>App passwords</strong> (on the same Security page), type a name such as <em>TCM Library</em>, and press <strong>Create</strong>.</li>
                                <li>Google shows 16 letters (like <code>abcd efgh ijkl mnop</code>). Paste them in the box above — spaces don't matter — and press <strong>Save &amp; send test e-mail</strong>.</li>
                            </ol>
                            <p class="mb-0 text-muted">Backups contain student and borrowing records. Use the department's own Gmail account and do not share its password.</p>
                        </div>
                    </div>
                </div>
            </div>

            <details class="mb-4">
                <summary class="small text-muted" style="cursor:pointer">Advanced e-mail settings (only if you do not use Gmail)</summary>
                <div class="row g-3 mt-1">
                    <div class="col-md-5"><label class="form-label small">Mail server</label><input type="text" name="smtp_host" class="form-control form-control-sm" value="<?php echo bh($s['smtp_host']); ?>"></div>
                    <div class="col-md-3"><label class="form-label small">Port</label><input type="number" name="smtp_port" class="form-control form-control-sm" value="<?php echo (int)$s['smtp_port']; ?>"></div>
                    <div class="col-md-4"><label class="form-label small">Security</label>
                        <select name="smtp_secure" class="form-select form-select-sm">
                            <option value="tls" <?php echo $s['smtp_secure'] === 'tls' ? 'selected' : ''; ?>>STARTTLS (Gmail, port 587)</option>
                            <option value="ssl" <?php echo $s['smtp_secure'] === 'ssl' ? 'selected' : ''; ?>>SSL (port 465)</option>
                            <option value="none" <?php echo $s['smtp_secure'] === 'none' ? 'selected' : ''; ?>>None</option>
                        </select></div>
                </div>
            </details>

            <div class="section-title mb-2">Protect backups with a password <span class="text-muted fw-normal text-capitalize">(optional)</span></div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <input type="password" name="zip_password" class="form-control" placeholder="<?php echo $s['zip_password'] !== '' ? '•••••••• saved — type a new one to change it' : 'leave empty for no password'; ?>" autocomplete="new-password">
                    <?php if ($s['zip_password'] !== ''): ?>
                        <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="zip_password_clear" id="zip_password_clear" value="1"><label class="form-check-label small" for="zip_password_clear">Stop using a password for new backups</label></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6"><div class="form-text">The student and borrowing records inside each backup are locked with this password (so an e-mailed copy can't be read by anyone else). <strong>Write it down somewhere safe: without it a protected backup cannot be restored.</strong> It applies to backups made from now on.</div></div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button type="submit" name="action" value="save_settings" class="btn btn-tcm-purple"><i class="bi bi-save me-1"></i>Save settings</button>
                <button type="submit" name="action" value="test_email" class="btn btn-outline-secondary"><i class="bi bi-envelope-check me-1"></i>Save &amp; send test e-mail</button>
            </div>
        </div>
    </div>
    </form>

    <!-- ============================ EXTRA PROTECTION ============================ -->
    <div class="card card-tcm mb-4">
        <div class="card-header card-header-purple"><i class="bi bi-clock-history me-1"></i>Backups even when nobody logs in</div>
        <div class="card-body small">
            <p class="mb-2">The system already backs itself up when the Main Menu is opened and the last backup is old. To also have a backup <strong>every day at a fixed time</strong> (the computer must be on):</p>
            <ol class="mb-2">
                <li>Open the project folder (<span class="path-text"><?php echo bh(BK_APP_DIR); ?></span>).</li>
                <li>Double-click <strong>schedule_backup.bat</strong> and type the time you want (for example <code>17:00</code>).</li>
            </ol>
            <p class="mb-0 text-muted">You can also double-click <strong>backup.bat</strong> at any time to back up without opening the system.</p>
        </div>
    </div>

    <!-- ============================ RECOVERY GUIDE ============================ -->
    <div class="card card-tcm" id="recovery">
        <div class="card-header bg-dark text-white fw-bold"><i class="bi bi-life-preserver me-1"></i>If something goes wrong — what to do</div>
        <div class="card-body">
            <div class="accordion" id="recoveryAcc">
                <div class="accordion-item">
                    <h2 class="accordion-header"><button class="accordion-button py-2" type="button" data-bs-toggle="collapse" data-bs-target="#rec1"><strong>A.</strong>&nbsp;The system opens, but data is wrong or missing</button></h2>
                    <div id="rec1" class="accordion-collapse collapse show" data-bs-parent="#recoveryAcc"><div class="accordion-body small recovery-step">
                        Use <strong>Restore</strong> next to the last good backup in the list above. The system saves the current data first, so you can undo a restore too.</div></div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header"><button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#rec2"><strong>B.</strong>&nbsp;Cannot log in, or pages show database errors</button></h2>
                    <div id="rec2" class="accordion-collapse collapse" data-bs-parent="#recoveryAcc"><div class="accordion-body small recovery-step">
                        On the computer that runs the system, open <span class="path-text">http://localhost/<?php echo bh(basename(BK_APP_DIR)); ?>/emergency_restore.php</span>. It works without logging in (only on that computer),
                        lists the backup files, and restores the chosen one.</div></div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header"><button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#rec3"><strong>C.</strong>&nbsp;The website will not open at all</button></h2>
                    <div id="rec3" class="accordion-collapse collapse" data-bs-parent="#recoveryAcc"><div class="accordion-body small recovery-step">
                        1. Open the XAMPP Control Panel and start <strong>MySQL</strong> (Apache is not needed for this).<br>
                        2. In the project folder, double-click <strong>restore.bat</strong>, pick a backup (just press Enter for the newest) and answer <strong>y</strong> when asked about system files.<br>
                        3. Start <strong>Apache</strong> too and open the system again.<br>
                        If the program files themselves are damaged, <strong>restore.bat</strong> can put them back from the backup. If it cannot start, download the project again from GitHub and then run it.</div></div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header"><button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#rec4"><strong>D.</strong>&nbsp;The computer is lost or broken (new computer)</button></h2>
                    <div id="rec4" class="accordion-collapse collapse" data-bs-parent="#recoveryAcc"><div class="accordion-body small recovery-step">
                        1. Install XAMPP and download the project from GitHub into <span class="path-text">C:\xampp\htdocs\law-library</span>; run <code>composer install</code> in that folder.<br>
                        2. Get the newest backup .zip: from the <strong>e-mail</strong> (download the attachment) or from the <strong>USB / Google Drive</strong> second-copy folder.<br>
                        3. Copy it into <span class="path-text">C:\xampp\law-library-backups</span> and double-click <strong>restore.bat</strong>.</div></div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header"><button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#rec5"><strong>E.</strong>&nbsp;Last resort: do it by hand</button></h2>
                    <div id="rec5" class="accordion-collapse collapse" data-bs-parent="#recoveryAcc"><div class="accordion-body small recovery-step">
                        Open the backup .zip (it is a normal zip file). Inside is <strong>database.sql</strong>. In phpMyAdmin choose the database <code><?php echo bh(bk_main_db()); ?></code> (create it if missing) → <strong>Import</strong> → pick <strong>database.sql</strong> → Go.
                        The folder <strong>system-files</strong> in the zip holds a copy of the website files. A file named RECOVERY-GUIDE.txt inside the zip repeats all of these steps.
                        (If the backup is password-protected, a program such as 7-Zip is needed to open it.)</div></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var b = document.getElementById('btn-backup-now');
    if (b) b.addEventListener('click', function () {
        if (window.BKPrompt) window.BKPrompt.open({ mode: 'manual', onDone: function () { window.location.reload(); } });
    });
    if (location.hash === '#settings') { var el = document.getElementById('settings'); if (el) el.scrollIntoView(); }
});
</script>
</body>
</html>
