<?php
// =====================================================================
// Backup & Restore — fixed settings (everything staff can change from the
// Backup page lives in settings.json instead, see backup_functions.php).
// =====================================================================

date_default_timezone_set('Asia/Manila');

// The folder of the system itself (where this file is).
define('BK_APP_DIR', __DIR__);

// Where backups, the settings file and the backup log live by default.
// With XAMPP this is  C:\xampp\law-library-backups  — next to "htdocs", NOT inside it,
// so nobody can download a backup by typing a web address.
define('BK_BASE_DEFAULT', dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'law-library-backups');

// Other databases to include in every backup, besides the library's own database
// (which is read automatically from db.php). Example:  ['entrance_exam_db']
// Left empty on purpose: that database belongs to the Examination System group.
define('BK_EXTRA_DATABASES', []);

// Folders that are never copied into a backup (they can be rebuilt, or are not part of the system).
define('BK_SKIP_DIRS', ['vendor', 'node_modules', '.git', '.github', 'backups', 'law-library-backups', 'incoming']);

// Biggest single system file that goes into a backup (bytes).
define('BK_MAX_FILE_BYTES', 5 * 1024 * 1024);

// Gmail accepts attachments up to 25 MB; stay safely below that.
define('BK_MAX_EMAIL_BYTES', 20 * 1024 * 1024);
