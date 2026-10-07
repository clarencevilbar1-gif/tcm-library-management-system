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
                    $copies = isset($row[3]) && trim($row[3]) !== '' ? intval($row[3]) : 1;
                    if ($copies < 1) $copies = 1;

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

                    mysqli_query($conn, "INSERT INTO books (title, author, serial_no, total_copies, available_copies, is_available) 
                                        VALUES ('$title', '$author', '$serial_no', $copies, $copies, 1)");
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
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

        .navbar-tcm {
            background-color: var(--tcm-purple);
            padding: 0.9rem 2rem;
        }

        .navbar-tcm .navbar-brand {
            color: #fff;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .navbar-tcm .navbar-brand img {
            width: 40px;
            height: 40px;
            object-fit: contain;
        }

        .navbar-tcm .btn-outline-light {
            border-color: rgba(255,255,255,0.5);
            font-size: 0.85rem;
        }

        .navbar-tcm .btn-outline-light:hover {
            background-color: var(--tcm-gold);
            border-color: var(--tcm-gold);
            color: var(--tcm-purple-dark);
        }

        .page-heading {
            color: var(--tcm-purple-dark);
            font-weight: 700;
        }

        .card-tcm {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 16px rgba(75, 46, 131, 0.08);
            overflow: hidden;
        }

        .card-header-green {
            background-color: var(--tcm-purple);
            color: #fff;
            font-weight: 700;
        }

        .card-header-gold {
            background-color: var(--tcm-gold);
            color: var(--tcm-purple-dark);
            font-weight: 700;
        }

        .card-header-notice {
            background-color: #fff8e6;
            color: var(--tcm-gold-dark);
            font-weight: 700;
            border-bottom: 1px solid #f0e0ad;
        }

        .card-notice {
            border: 1px solid #f0e0ad;
        }

        .btn-tcm-purple {
            background-color: var(--tcm-purple);
            color: #fff;
            font-weight: 600;
            border: none;
        }

        .btn-tcm-purple:hover {
            background-color: var(--tcm-purple-dark);
            color: #fff;
        }

        .btn-tcm-gold {
            background-color: var(--tcm-gold);
            color: var(--tcm-purple-dark);
            font-weight: 600;
            border: none;
        }

        .btn-tcm-gold:hover {
            background-color: var(--tcm-gold-dark);
            color: var(--tcm-purple-dark);
        }
    </style>
</head>
<body>

<nav class="navbar navbar-tcm d-flex justify-content-between">
    <span class="navbar-brand">
        <img src="assets/images/tcm_logo.png" alt="TCM Seal">
        Law Department Library
    </span>
    <div class="d-flex align-items-center gap-3">
        <a href="index.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Menu</a>
        <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
    </div>
</nav>

<div class="container mt-4" style="max-width: 600px;">
    <h4 class="page-heading mb-4">Import Data</h4>

    <?php echo $message; ?>

    <!-- Import Students -->
    <div class="card card-tcm mb-4">
        <div class="card-header card-header-green">
            <i class="bi bi-person-badge me-1"></i>Import Students
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
                <button type="submit" class="btn btn-tcm-purple w-100">Import Students</button>
            </form>
        </div>
    </div>

    <!-- Import Books -->
    <div class="card card-tcm mb-4">
        <div class="card-header card-header-gold">
            <i class="bi bi-book me-1"></i>Import Books
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Upload an Excel or CSV file with columns in this exact order:<br>
                <strong>Column A:</strong> Title &nbsp;|&nbsp;
                <strong>Column B:</strong> Author &nbsp;|&nbsp;
                <strong>Column C:</strong> Serial Number &nbsp;|&nbsp;
                <strong>Column D:</strong> Number of Copies <span class="text-muted">(optional — defaults to 1)</span>
            </p>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="type" value="books">
                <div class="mb-3">
                    <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
                </div>
                <button type="submit" class="btn btn-tcm-gold w-100">Import Books</button>
            </form>
        </div>
    </div>

    <!-- Instructions -->
    <div class="card card-notice">
        <div class="card-header card-header-notice">
            <i class="bi bi-exclamation-triangle me-1"></i>Before Importing
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