<?php
include('auth_quiet.php');
ini_set('display_errors', 1);
error_reporting(E_ALL);
include('db.php');
include('student_functions.php');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$stmt = mysqli_prepare($conn, "SELECT * FROM students WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$student = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$student) { header('Location: students.php'); exit(); }

$ready = st_columns_ready($conn);
$student += ['gender' => '', 'year_level' => '', 'contact_number' => ''];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $ready) {
    $student_no = st_cut(st_text($_POST['student_no']), 50);
    $name = st_cut(st_text($_POST['name']), 150);
    $course = st_cut(st_text($_POST['course']), 100);
    $gender = in_array($_POST['gender'], ST_GENDERS, true) ? $_POST['gender'] : null;
    $level = in_array($_POST['year_level'], ST_LEVELS, true) ? $_POST['year_level'] : null;
    $contact = st_norm_contact($_POST['contact']);

    // Check if student number already exists on another student
    $check = mysqli_prepare($conn, "SELECT id FROM students WHERE student_no = ? AND id != ?");
    mysqli_stmt_bind_param($check, 'si', $student_no, $id);
    mysqli_stmt_execute($check);
    mysqli_stmt_store_result($check);
    if ($student_no === '' || $name === '') {
        echo "<script>alert('Please fill in the student number and the name.'); window.location='edit_student.php?id=$id';</script>";
        exit();
    } elseif (mysqli_stmt_num_rows($check) > 0) {
        echo "<script>alert('Student number already exists on another record!'); window.location='edit_student.php?id=$id';</script>";
        exit();
    } else {
        $upd = mysqli_prepare($conn, "UPDATE students SET student_no = ?, name = ?, gender = ?, course = ?, year_level = ?, contact_number = ? WHERE id = ?");
        mysqli_stmt_bind_param($upd, 'ssssssi', $student_no, $name, $gender, $course, $level, $contact, $id);
        mysqli_stmt_execute($upd);
        header('Location: students.php');
        exit();
    }
}

// a value that is stored but is not in the fixed list is still shown (so nothing is lost silently)
function st_options($list, $current) {
    $html = '<option value="">— not set —</option>';
    $found = false;
    foreach ($list as $o) { $sel = ($o === $current) ? ' selected' : ''; if ($sel) $found = true; $html .= '<option' . $sel . '>' . st_h($o) . '</option>'; }
    if (!$found && $current !== null && $current !== '') $html .= '<option selected>' . st_h($current) . '</option>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student</title>
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

        .page-heading {
            color: var(--tcm-purple-dark);
            font-weight: 700;
        }

        .card-tcm {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 16px rgba(75, 46, 131, 0.1);
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
    <script>
        // Force reload if the page is shown from the browser's back/forward cache
        window.addEventListener('pageshow', function(event) { if (event.persisted) window.location.reload(); });
    </script>
</head>
<body>

<?php $nav_mode = 'back'; $nav_back_url = 'students.php'; $nav_back_label = 'Back to Students'; include('navbar.php'); ?>

<div class="container mt-4" style="max-width: 500px;">
    <h4 class="page-heading mb-4"><i class="bi bi-pencil-square me-2"></i>Edit Student</h4>
    <?php if (!$ready) echo st_migration_notice(); ?>
    <div class="card card-tcm p-4">
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Student Number</label>
                <input type="text" name="student_no" class="form-control"
                       value="<?php echo st_h($student['student_no']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control"
                       value="<?php echo st_h($student['name']); ?>" required>
                <div class="form-text">Written as <strong>Last name, First name M. Suffix</strong> &mdash; for example <em>Dela Cruz, Juan A. Jr.</em></div>
            </div>
            <div class="row">
                <div class="col-6 mb-3">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select"><?php echo st_options(ST_GENDERS, $student['gender']); ?></select>
                </div>
                <div class="col-6 mb-3">
                    <label class="form-label">Year Level</label>
                    <select name="year_level" class="form-select"><?php echo st_options(ST_LEVELS, $student['year_level']); ?></select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Course</label>
                <select name="course" class="form-select" required>
                    <?php
                    $cur = (string)$student['course'];
                    $hasCur = false;
                    foreach (ST_COURSES as $c) { $sel = ($c === $cur) ? ' selected' : ''; if ($sel) $hasCur = true; echo '<option' . $sel . '>' . st_h($c) . '</option>'; }
                    if (!$hasCur) echo '<option selected value="' . st_h($cur) . '">' . ($cur === '' ? '— not set —' : st_h($cur)) . '</option>';
                    ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Contact Number <span class="text-muted small">(optional)</span></label>
                <input type="text" name="contact" class="form-control" maxlength="20"
                       value="<?php echo st_h($student['contact_number']); ?>">
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-tcm-gold w-100"<?php echo $ready ? '' : ' disabled'; ?>>Save Changes</button>
                <a href="students.php" class="btn btn-outline-secondary w-100">Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
