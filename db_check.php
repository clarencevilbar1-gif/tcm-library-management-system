<?php
// Used by fix_mysql.bat and start_library.bat (command line only).
// Prints DBOK when the library database can be opened and read.
if (php_sapi_name() !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_OFF);
$done = false;
register_shutdown_function(function () use (&$done) { if (!$done) { while (ob_get_level()) ob_end_clean(); echo "DBFAIL\n"; } });
ob_start();
include __DIR__ . '/db.php';          // dies with "Connection failed" if MySQL/database is not usable
ob_end_clean();
if (isset($conn) && $conn) {
    $r = @mysqli_query($conn, 'SELECT COUNT(*) FROM students');
    $r2 = @mysqli_query($conn, 'SELECT COUNT(*) FROM books');
    $r3 = @mysqli_query($conn, 'SELECT COUNT(*) FROM borrowing');
    if ($r && $r2 && $r3) { $done = true; echo "DBOK\n"; exit(0); }
}
$done = true;
echo "DBFAIL\n";
exit(1);
