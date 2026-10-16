<?php
// Exports the transaction history as an Excel (.xlsx) or CSV file.
//
// Query string (GET):
//   action = count | download      count returns JSON, download returns the file
//   range  = all | custom          custom needs from + to (YYYY-MM-DD, both inclusive)
//   basis  = borrowed | returned   which date the range is matched against (custom only)
//   status = all | borrowed | returned | overdue
//   format = xlsx | csv
//
// Note: auth.php is NOT included here on purpose. It prints HTML, which would corrupt a
// downloaded file, so the login check is done directly below.

session_start();
ini_set('display_errors', '0');   // a stray warning must never end up inside the downloaded file
error_reporting(E_ALL);

$action = (isset($_GET['action']) && $_GET['action'] === 'count') ? 'count' : 'download';

// ---- Must be logged in ----
if (!isset($_SESSION['staff_logged_in'])) {
    if ($action === 'count') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
    } else {
        header('Location: login.php');
    }
    exit();
}

include('db.php');

// ---- Read + validate the options ----
function valid_date($d) {
    return is_string($d)
        && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)
        && checkdate((int)substr($d, 5, 2), (int)substr($d, 8, 2), (int)substr($d, 0, 4));
}

$range  = (isset($_GET['range']) && $_GET['range'] === 'custom') ? 'custom' : 'all';
$basis  = (isset($_GET['basis']) && $_GET['basis'] === 'returned') ? 'returned' : 'borrowed';
$status = (isset($_GET['status']) && in_array($_GET['status'], ['all', 'borrowed', 'returned', 'overdue'], true)) ? $_GET['status'] : 'all';
$format = (isset($_GET['format']) && $_GET['format'] === 'csv') ? 'csv' : 'xlsx';
$from   = isset($_GET['from']) ? $_GET['from'] : '';
$to     = isset($_GET['to']) ? $_GET['to'] : '';

$error = '';
if ($range === 'custom') {
    if (!valid_date($from) || !valid_date($to)) {
        $error = 'Please choose a valid start date and end date.';
    } elseif ($from > $to) {
        $error = 'The start date must be on or before the end date.';
    }
}

if ($error !== '') {
    if ($action === 'count') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $error]);
    } else {
        header('Location: history.php?export_msg=invalid');
    }
    exit();
}

// ---- Build the query (dates are validated above, so they are safe to use) ----
$today = date('Y-m-d');
$where = [];

if ($range === 'custom') {
    $date_col = ($basis === 'returned') ? 'borrowing.return_date' : 'borrowing.borrow_date';
    $where[] = "$date_col BETWEEN '$from' AND '$to'";
}

if ($status === 'borrowed') {
    $where[] = "borrowing.return_date IS NULL";
} elseif ($status === 'returned') {
    $where[] = "borrowing.return_date IS NOT NULL";
} elseif ($status === 'overdue') {
    $where[] = "borrowing.return_date IS NULL AND borrowing.due_date IS NOT NULL AND borrowing.due_date < '$today'";
}

$where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// LEFT JOIN so a transaction is never dropped just because its book (or student) was deleted later
$from_sql = "FROM borrowing
             LEFT JOIN students ON borrowing.student_id = students.id
             LEFT JOIN books ON borrowing.book_id = books.id";

$count_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total $from_sql $where_sql"));
$match_count = (int)$count_row['total'];

// ---- action=count: just report how many records match ----
if ($action === 'count') {
    $all_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM borrowing"));
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(['ok' => true, 'count' => $match_count, 'total' => (int)$all_row['total']]);
    exit();
}

// ---- action=download ----
if ($match_count === 0) {
    header('Location: history.php?export_msg=empty');
    exit();
}

$result = mysqli_query($conn,
    "SELECT borrowing.id, borrowing.borrow_date, borrowing.borrow_days, borrowing.due_date, borrowing.return_date,
            students.student_no, students.name AS student_name, students.course,
            books.acc_nos, books.class_no, books.title AS book_title, books.author,
            books.edition, books.volume, books.pub_year
     $from_sql
     $where_sql
     ORDER BY borrowing.borrow_date ASC, borrowing.id ASC");

// Status text matches the Transaction History page; "days late" counts how long past the due date
function tx_status($r, $today) {
    if ($r['return_date']) return 'Returned';
    if ($r['due_date'] && $today > $r['due_date']) return 'Overdue';
    return 'Still Borrowed';
}

function tx_days_late($r, $today) {
    if (!$r['due_date']) return 0;
    $end = $r['return_date'] ? $r['return_date'] : $today;
    $days = (int)(new DateTime($r['due_date']))->diff(new DateTime($end))->format('%r%a');
    return $days > 0 ? $days : 0;
}

$rows = [];
$n_returned = 0;
$n_borrowed = 0;
$n_overdue = 0;
$first_date = null;
$last_date = null;

while ($r = mysqli_fetch_assoc($result)) {
    // placeholders for records whose book/student no longer exists
    if ($r['student_no'] === null) $r['student_no'] = '—';
    if ($r['student_name'] === null) $r['student_name'] = '(Deleted student)';
    if ($r['acc_nos'] === null) $r['acc_nos'] = '—';
    if ($r['book_title'] === null) $r['book_title'] = '(Deleted book)';
    $r['status'] = tx_status($r, $today);
    $r['days_late'] = tx_days_late($r, $today);
    if ($r['status'] === 'Returned') {
        $n_returned++;
    } else {
        $n_borrowed++;
        if ($r['status'] === 'Overdue') $n_overdue++;
    }
    if ($first_date === null || $r['borrow_date'] < $first_date) $first_date = $r['borrow_date'];
    if ($last_date === null || $r['borrow_date'] > $last_date) $last_date = $r['borrow_date'];
    $rows[] = $r;
}

// ---- Text shown in the file ----
function nice_date($d) {
    return date('M j, Y', strtotime($d));
}

if ($range === 'custom') {
    $period_text = nice_date($from) . ' – ' . nice_date($to) . ' (by date ' . $basis . ')';
    $file_part = $from . '_to_' . $to;
} else {
    $period_text = 'All recorded transactions (' . nice_date($first_date) . ' – ' . nice_date($last_date) . ')';
    $file_part = 'All_' . $today;
}

$status_labels = [
    'all' => 'All statuses',
    'borrowed' => 'Still borrowed (including overdue)',
    'returned' => 'Returned only',
    'overdue' => 'Overdue only'
];
$status_file = ($status === 'all') ? '' : '_' . ucfirst($status);
$file_name = 'Transaction_History' . $status_file . '_' . $file_part . '.' . $format;

$staff = isset($_SESSION['staff_username']) ? $_SESSION['staff_username'] : 'staff';
$generated = date('M j, Y g:i A');

// ---- The columns of the file (one list used by both CSV and Excel) ----
// The student, then the book (only the details that identify WHICH book was borrowed), then the dates.
//   type: text | int | date | status | late
$cols = [
    ['key' => 'no',           'label' => 'No.',          'width' => 7,  'type' => 'int',    'align' => 'center'],
    ['key' => 'student_no',   'label' => 'Student No.',  'width' => 16, 'type' => 'text',   'align' => 'center'],
    ['key' => 'student_name', 'label' => 'Student Name', 'width' => 26, 'type' => 'text',   'align' => 'left',   'wrap' => true],
    ['key' => 'course',       'label' => 'Course',       'width' => 16, 'type' => 'text',   'align' => 'left',   'wrap' => true],
    ['key' => 'acc_nos',      'label' => 'Acc. No(s).',  'width' => 13, 'type' => 'text',   'align' => 'center'],
    ['key' => 'class_no',     'label' => 'Class',        'width' => 17, 'type' => 'text',   'align' => 'left'],
    ['key' => 'book_title',   'label' => 'Book Title',   'width' => 38, 'type' => 'text',   'align' => 'left',   'wrap' => true],
    ['key' => 'author',       'label' => 'Author',       'width' => 24, 'type' => 'text',   'align' => 'left',   'wrap' => true],
    ['key' => 'edition',      'label' => 'Edition',      'width' => 11, 'type' => 'text',   'align' => 'left'],
    ['key' => 'volume',       'label' => 'Volume',       'width' => 10, 'type' => 'text',   'align' => 'left'],
    ['key' => 'pub_year',     'label' => 'Year',         'width' => 8,  'type' => 'int',    'align' => 'center'],
    ['key' => 'borrow_date',  'label' => 'Date Borrowed','width' => 14, 'type' => 'date',   'align' => 'center'],
    ['key' => 'due_date',     'label' => 'Due Date',     'width' => 14, 'type' => 'date',   'align' => 'center'],
    ['key' => 'return_date',  'label' => 'Date Returned','width' => 14, 'type' => 'date',   'align' => 'center'],
    ['key' => 'status',       'label' => 'Status',       'width' => 15, 'type' => 'status', 'align' => 'center'],
    ['key' => 'days_late',    'label' => 'Days Late',    'width' => 10, 'type' => 'late',   'align' => 'center'],
];

// ===================================================================
// CSV
// ===================================================================
if ($format === 'csv') {
    // Cells starting with = + - @ would be run as formulas by Excel, so they get a leading '
    $safe = function ($v) {
        $v = (string)$v;
        return ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;
    };

    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // UTF-8 marker so Excel shows names like "Peña" correctly
    fputcsv($out, array_map(function ($c) { return $c['label']; }, $cols), ',', '"', '\\', "\r\n");

    foreach ($rows as $idx => $r) {
        $r['no'] = $idx + 1;
        $line = [];
        foreach ($cols as $c) {
            $v = isset($r[$c['key']]) ? $r[$c['key']] : '';
            if ($c['type'] === 'text') {
                $line[] = $safe($v);
            } elseif ($c['type'] === 'late') {
                $line[] = $v > 0 ? $v : '';
            } else {
                $line[] = $v === null ? '' : $v;
            }
        }
        fputcsv($out, $line, ',', '"', '\\', "\r\n");
    }
    fclose($out);
    exit();
}

// ===================================================================
// EXCEL (.xlsx)
// ===================================================================
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

$PURPLE = '4B2E83';
$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator('The College of Maasin - College of Law Library')
    ->setTitle('Transaction History')
    ->setSubject($period_text);

$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Transactions');
$sheet->setShowGridlines(false);

$ncols = count($cols);
$last_col = Coordinate::stringFromColumnIndex($ncols);            // e.g. P
$col_letter = [];
foreach ($cols as $i => $c) {
    $col_letter[$c['key']] = Coordinate::stringFromColumnIndex($i + 1);
    $sheet->getColumnDimension($col_letter[$c['key']])->setWidth($c['width']);
}

// ---- Header block: logos on the left, title text beside them ----
$sheet->getRowDimension(1)->setRowHeight(26);
$sheet->getRowDimension(2)->setRowHeight(21);
$sheet->getRowDimension(3)->setRowHeight(18);
$sheet->getRowDimension(4)->setRowHeight(18);
$sheet->getRowDimension(5)->setRowHeight(18);
$sheet->getRowDimension(6)->setRowHeight(8);

$logo_files = [
    ['assets/images/tcm_logo.png', 'A1', 2],
    ['assets/images/col_logo.png', 'B1', 32],
];
foreach ($logo_files as $lf) {
    $path = __DIR__ . '/' . $lf[0];
    if (is_file($path)) {
        $d = new Drawing();
        $d->setPath($path);
        $d->setCoordinates($lf[1]);
        $d->setOffsetX($lf[2]);
        $d->setOffsetY(8);
        $d->setHeight(76);
        $d->setWorksheet($sheet);
    }
}

$sheet->setCellValue('C1', 'The College of Maasin');
$sheet->setCellValue('C2', 'College of Law Library — Transaction History');
$sheet->setCellValue('C3', 'Period: ' . $period_text);
$sheet->setCellValue('C4', 'Status: ' . $status_labels[$status] . '   |   Generated ' . $generated . ' by ' . $staff);
$sheet->setCellValue('C5', 'Total: ' . count($rows) . '   |   Returned: ' . $n_returned
    . '   |   Still borrowed: ' . $n_borrowed . '   |   Overdue: ' . $n_overdue);
foreach (['C1', 'C2', 'C3', 'C4', 'C5'] as $c) {
    $sheet->mergeCells($c . ':' . $last_col . substr($c, 1));
    $sheet->getStyle($c)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_LEFT)->setIndent(1);
}
$sheet->getStyle('C1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB($PURPLE);
$sheet->getStyle('C2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('B8860B');
$sheet->getStyle('C3:C4')->getFont()->setSize(10)->getColor()->setRGB('444444');
$sheet->getStyle('C5')->getFont()->setBold(true)->setSize(10)->getColor()->setRGB($PURPLE);

// ---- Table header ----
$head_row = 7;
foreach ($cols as $i => $c) {
    $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($i + 1) . $head_row, $c['label'], DataType::TYPE_STRING);
}
$sheet->getRowDimension($head_row)->setRowHeight(24);
$sheet->getStyle("A{$head_row}:{$last_col}{$head_row}")->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $PURPLE]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '35205E']]],
]);

// ---- Data rows ----
$status_style = [
    'Returned'       => ['fill' => 'E3F4E8', 'font' => '1E6B34'],
    'Overdue'        => ['fill' => 'FBE4E4', 'font' => 'B3261E'],
    'Still Borrowed' => ['fill' => 'FFF3D1', 'font' => '8A6100'],
];
$r_i = $head_row;
foreach ($rows as $idx => $r) {
    $r_i++;
    $r['no'] = $idx + 1;
    foreach ($cols as $i => $c) {
        $cell = Coordinate::stringFromColumnIndex($i + 1) . $r_i;
        $v = isset($r[$c['key']]) ? $r[$c['key']] : null;
        if ($v === null || $v === '') continue;
        if ($c['type'] === 'text' || $c['type'] === 'status') {
            // plain text, so IDs like 000008 keep their zeros and nothing runs as a formula
            $sheet->setCellValueExplicit($cell, (string)$v, DataType::TYPE_STRING);
        } elseif ($c['type'] === 'date') {
            $sheet->setCellValue($cell, Date::stringToExcel($v));
        } else {                       // int | late
            $sheet->setCellValue($cell, (int)$v);
        }
    }

    $sc = $col_letter['status'] . $r_i;
    $st = $status_style[$r['status']];
    $sheet->getStyle($sc)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => $st['font']]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $st['fill']]],
    ]);
    if ($idx % 2 === 1) {   // light banding on every column except Status
        foreach ($cols as $i => $c) {
            if ($c['key'] === 'status') continue;
            $sheet->getStyle(Coordinate::stringFromColumnIndex($i + 1) . $r_i)->getFill()
                  ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8F5FC');
        }
    }
}
$last_row = $r_i;
$first_data = $head_row + 1;

$table = "A{$head_row}:{$last_col}{$last_row}";
$sheet->getStyle($table)->applyFromArray([
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9D2E9']]],
]);
$sheet->getStyle("A{$first_data}:{$last_col}{$last_row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

foreach ($cols as $i => $c) {
    $L = Coordinate::stringFromColumnIndex($i + 1);
    $range = "{$L}{$first_data}:{$L}{$last_row}";
    $al = $sheet->getStyle($range)->getAlignment();
    $al->setHorizontal($c['align'] === 'center' ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_LEFT);
    if (!empty($c['wrap'])) $al->setWrapText(true);
    if ($c['type'] === 'date') $sheet->getStyle($range)->getNumberFormat()->setFormatCode('mmm d, yyyy');
    if ($c['type'] === 'late') $sheet->getStyle($range)->getNumberFormat()->setFormatCode('0;-0;;@');   // zero shows blank
}

// ---- Usability: frozen header, filter buttons, print layout ----
$sheet->freezePane('A' . $first_data);
$sheet->setAutoFilter($table);

$ps = $sheet->getPageSetup();
$ps->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
$ps->setPaperSize(PageSetup::PAPERSIZE_A4);
$ps->setFitToWidth(1);
$ps->setFitToHeight(0);
$sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($head_row, $head_row);
$sheet->getPageMargins()->setLeft(0.4)->setRight(0.4)->setTop(0.5)->setBottom(0.6);
$sheet->getHeaderFooter()->setOddFooter('&L&8The College of Maasin — College of Law Library&R&8Page &P of &N');
$sheet->getSheetView()->setZoomScale(100);
$sheet->setSelectedCell('A1');

// ---- Send it ----
while (ob_get_level()) ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $file_name . '"');
header('Cache-Control: no-store');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();
