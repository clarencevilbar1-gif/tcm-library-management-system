<?php
// Shared helpers for the book fields. The fields follow the College of Law's
// "LAW LIBRARY CHECKLIST": Acc. Nos., Class, Title, Author, Edition, Volume,
// Pages, Fund, Publication, Year.

// Text columns: database column => [label, max length, required?]
function book_text_fields() {
    return [
        'acc_nos'     => ['Acc. No(s).', 255, true],
        'class_no'    => ['Class',       100, false],
        'title'       => ['Title',       500, true],
        'author'      => ['Author',      255, false],
        'edition'     => ['Edition',     100, false],
        'volume'      => ['Volume',      100, false],
        'pages'       => ['Pages',        50, false],
        'fund'        => ['Fund',        150, false],
        'publication' => ['Publication', 255, false],
    ];
}

// Trim, turn odd spaces into normal ones and collapse repeated spaces
// (the department file has stray and double spaces in many cells).
function clean_text($v) {
    if ($v === null) return '';
    $v = str_replace("\xC2\xA0", ' ', (string)$v);          // non-breaking space
    $v = preg_replace('/\s+/u', ' ', $v);
    return trim($v);
}

// Empty text is stored as NULL ("not recorded")
function clean_or_null($v) {
    $v = clean_text($v);
    return $v === '' ? null : $v;
}

// Year: blank -> null. Otherwise a 4-digit year (1000 .. next year). Returns [value, isValid].
function parse_pub_year($v) {
    $v = clean_text($v);
    if ($v === '') return [null, true];
    if (preg_match('/^\d{4}(\.0+)?$/', $v)) {
        $y = (int)$v;
        if ($y >= 1000 && $y <= (int)date('Y') + 1) return [$y, true];
    }
    return [null, false];
}

// Is this Acc. No(s). already used by another book?
function acc_no_exists($conn, $acc_nos, $exclude_id = 0) {
    $stmt = mysqli_prepare($conn, "SELECT id FROM books WHERE acc_nos = ? AND id <> ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'si', $acc_nos, $exclude_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $found = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $found;
}

// Reads + checks the book form (used by Add Book and Edit Book).
// Returns [values, errors]; values are cleaned and ready to save.
function read_book_form($src) {
    $values = [];
    $errors = [];

    foreach (book_text_fields() as $col => $def) {
        $label = $def[0];
        $max = $def[1];
        $required = $def[2];
        $val = clean_or_null(isset($src[$col]) ? $src[$col] : '');

        if ($val === null && $required) {
            $errors[] = "$label is required.";
        } elseif ($val !== null && mb_strlen($val) > $max) {
            $errors[] = "$label is too long (maximum $max characters).";
        }
        $values[$col] = $val;
    }

    list($year, $year_ok) = parse_pub_year(isset($src['pub_year']) ? $src['pub_year'] : '');
    if (!$year_ok) $errors[] = 'Year must be a 4-digit year (for example 2022), or left blank.';
    $values['pub_year'] = $year;

    return [$values, $errors];
}

// Adds one book (copies are tracked the same way as before)
function insert_book($conn, $d, $copies) {
    $sql = "INSERT INTO books (acc_nos, class_no, title, author, edition, volume, pages, fund, publication, pub_year,
                               total_copies, available_copies, is_available)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
    $stmt = mysqli_prepare($conn, $sql);
    $acc = $d['acc_nos']; $cls = $d['class_no']; $title = $d['title']; $author = $d['author'];
    $edition = $d['edition']; $volume = $d['volume']; $pages = $d['pages']; $fund = $d['fund'];
    $publication = $d['publication']; $year = $d['pub_year'];
    mysqli_stmt_bind_param($stmt, 'sssssssssiii', $acc, $cls, $title, $author, $edition, $volume, $pages, $fund,
                           $publication, $year, $copies, $copies);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

// Short helper for printing values safely (NULL prints as empty)
function h($v) {
    return htmlspecialchars($v === null ? '' : (string)$v, ENT_QUOTES, 'UTF-8');
}
?>
