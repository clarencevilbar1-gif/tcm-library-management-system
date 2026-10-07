<?php
// Shared helpers for the Borrow Book and Return Book pages.

// All books that are currently borrowed (not yet returned), oldest first.
function get_active_loans($conn) {
    $sql = "SELECT borrowing.id, borrowing.student_id, borrowing.book_id,
                   borrowing.borrow_date, borrowing.due_date,
                   students.name AS student_name, students.student_no, students.is_active,
                   books.title AS book_title, books.serial_no
            FROM borrowing
            JOIN students ON borrowing.student_id = students.id
            JOIN books ON borrowing.book_id = books.id
            WHERE borrowing.return_date IS NULL
            ORDER BY borrowing.borrow_date ASC, borrowing.id ASC";
    $result = mysqli_query($conn, $sql);
    $loans = [];
    $today = date('Y-m-d');
    while ($row = mysqli_fetch_assoc($result)) {
        $row['is_overdue'] = ($row['due_date'] && $today > $row['due_date']);
        $loans[] = $row;
    }
    return $loans;
}

// Prints the "Currently Borrowed Books" table (all loans passed in).
// Each row carries data-student so the Borrow page can filter by student.
function render_loans_table($loans, $empty_text) {
    ?>
    <table class="table table-hover table-loans">
        <colgroup>
            <col style="width: 23%">
            <col style="width: 22%">
            <col style="width: 12%">
            <col style="width: 14%">
            <col style="width: 14%">
            <col style="width: 15%">
        </colgroup>
        <thead>
            <tr>
                <th>Student</th>
                <th>Book</th>
                <th>Serial No.</th>
                <th>Date Borrowed</th>
                <th>Due Date</th>
                <th class="col-center">Status</th>
            </tr>
        </thead>
        <tbody id="loans-body">
        <?php foreach ($loans as $l): ?>
            <tr class="loan-row" data-student="<?php echo (int)$l['student_id']; ?>">
                <td>
                    <?php echo htmlspecialchars($l['student_name']); ?>
                    <div class="small text-muted student-no"><?php echo htmlspecialchars($l['student_no']); ?></div>
                </td>
                <td><?php echo htmlspecialchars($l['book_title']); ?></td>
                <td><?php echo htmlspecialchars($l['serial_no']); ?></td>
                <td class="nowrap"><?php echo htmlspecialchars($l['borrow_date']); ?></td>
                <td class="nowrap"><?php echo $l['due_date'] ? htmlspecialchars($l['due_date']) : '—'; ?></td>
                <td class="col-center">
                    <?php if ($l['is_overdue']): ?>
                        <span class="badge bg-danger">Overdue</span>
                    <?php else: ?>
                        <span class="badge bg-success">Active</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
            <tr id="loans-empty" <?php echo count($loans) > 0 ? 'style="display:none;"' : ''; ?>>
                <td colspan="6" class="text-center text-muted py-4"><?php echo htmlspecialchars($empty_text); ?></td>
            </tr>
        </tbody>
    </table>
    <?php
}
?>