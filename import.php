<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include('db.php');
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file']['tmp_name'];
    $filename = $_FILES['file']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
        $message = '<div class="alert alert-danger">Invalid file type. Please upload an Excel (.xlsx) or CSV file.</div>';
    } else {
        try {
            $spreadsheet = IOFactory::load($file);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            $type = $_POST['type']; // 'students' or 'books'
            $success = 0;
            $skipped = 0;

            // Skip the header row
            array_shift($rows);

            foreach ($rows as $row) {
                // Skip completely empty rows
                if (empty(array_filter($row))) continue;

                if ($type == 'students') {
                    $student_no = mysqli_real_escape_string($conn, trim($row[0]));
                    $name = mysqli_real_escape_string($conn, trim($row[1]));
                    $course = mysqli_real_escape_string($conn, trim($row[2] ?? 'Juris Doctor'));

                    if (empty($student_no) || empty($name)) {
                        $skipped++;
                        continue;
                    }

                    // Skip duplicates
                    $check = mysqli_query($conn, "SELECT id FROM students WHERE student_no = '$student_no'");
                    if (mysqli_num_rows($check) > 0) {
                        $skipped++;
                        continue;
                    }

                    mysqli_query($conn, "INSERT INTO students (student_no, name, course) 
                                        VALUES ('$student_no', '$name', '$course')");
                    $success++;

                } else if ($type == 'books') {
                    $title = mysqli_real_escape_string($conn, trim($row[0]));
                    $author = mysqli_real_escape_string($conn, trim($row[1]));
                    $serial_no = mysqli_real_escape_string($conn, trim($row[2]));

                    if (empty($title) || empty($serial_no)) {
                        $skipped++;
                        continue;
                    }

                    // Skip duplicates
                    $check = mysqli_query($conn, "SELECT id FROM books WHERE serial_no = '$serial_no'");
                    if (mysqli_num_rows($check) > 0) {
                        $skipped++;
                        continue;
                    }

                    mysqli_query($conn, "INSERT INTO books (title, author, serial_no, is_available) 
                                        VALUES ('$title', '$author', '$serial_no', 1)");
                    $success++;
                }
            }

            $message = '<div class="alert alert-success">
                            ✅ Import complete! <strong>' . $success . '</strong> record(s) added. 
                            <strong>' . $skipped . '</strong> skipped (duplicates or empty rows).
                        </div>';

        } catch (Exception $e) {
            $message = '<div class="alert alert-danger">Error reading file: ' . $e->getMessage() . '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark px-4 d-flex justify-content-between">
    <span class="navbar-brand fw-bold">⚖️ Law Department Library</span>
    <div class="d-flex align-items-center gap-3">
        <a href="index.php" class="btn btn-outline-light btn-sm">← Back to Menu</a>
        <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
    </div>
</nav>

<div class="container mt-4" style="max-width: 600px;">
    <h4 class="mb-4">Import Data</h4>

    <?php echo $message; ?>

    <!-- Import Students -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white fw-bold">
            👤 Import Students
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Upload an Excel or CSV file with columns in this exact order:<br>
                <strong>Column A:</strong> Student Number &nbsp;|&nbsp;
                <strong>Column B:</strong> Full Name &nbsp;|&nbsp;
                <strong>Column C:</strong> Course <span class="text-muted">(optional — defaults to Juris Doctor)</span>
            </p>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="type" value="students">
                <div class="mb-3">
                    <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Import Students</button>
            </form>
        </div>
    </div>

    <!-- Import Books -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white fw-bold">
            📚 Import Books
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Upload an Excel or CSV file with columns in this exact order:<br>
                <strong>Column A:</strong> Title &nbsp;|&nbsp;
                <strong>Column B:</strong> Author &nbsp;|&nbsp;
                <strong>Column C:</strong> Serial Number
            </p>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="type" value="books">
                <div class="mb-3">
                    <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
                </div>
                <button type="submit" class="btn btn-success w-100">Import Books</button>
            </form>
        </div>
    </div>

    <!-- Instructions -->
    <div class="card border-warning">
        <div class="card-header bg-warning text-dark fw-bold">
            ⚠️ Before Importing
        </div>
        <div class="card-body small text-muted">
            <ul class="mb-0">
                <li>Make sure Row 1 of your file is the <strong>header row</strong> (e.g. "Student No", "Name", "Course") — it will be skipped automatically.</li>
                <li>For Google Sheets — go to <strong>File → Download → CSV</strong> and upload that file.</li>
                <li>For Excel — save as <strong>.xlsx</strong> and upload directly.</li>
                <li>Duplicate student numbers and serial numbers will be <strong>skipped automatically</strong> — no double entries.</li>
                <li>You can import multiple times safely — only new records get added.</li>
            </ul>
        </div>
    </div>

</div>

</body>
</html>