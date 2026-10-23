<?php
// =====================================================================
// Student helpers shared by the Student Records page, the Add / Edit forms
// and the Import page.
//
// The registrar's file can write the same thing in many ways ("M", "Male",
// "MALE", "JD", "Juris Doctor", "1", "1st", "First Year" ...). The table only ever
// holds ONE spelling of each, taken from the lists below, so the Student Records
// page never shows a mix. To add another course, add it to ST_COURSES.
// =====================================================================

const ST_GENDERS = ['Male', 'Female'];
const ST_LEVELS  = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
const ST_COURSES = ['Juris Doctor'];

// ---------------------------------------------------------------------
// Basic cleaning
// ---------------------------------------------------------------------

// Cell value -> clean text. Whole numbers from Excel (20222703.0) become "20222703".
function st_text($v) {
    if ($v === null) return '';
    if (is_float($v) && floor($v) == $v && abs($v) < 1e15) $v = (string)(int)$v;
    $v = str_replace("\xC2\xA0", ' ', (string)$v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v);
    $v = preg_replace('/\s+/u', ' ', $v);
    return trim($v);
}

// "", "-", "N/A" ... are the same as empty
function st_blankish($s) {
    return in_array(strtolower(trim((string)$s)), ['', '-', '--', '---', '.', '..', '_', '/', 'n/a', 'na', 'n.a.', 'none', 'null', 'nil', 'wala'], true);
}

function st_key($s) {
    return preg_replace('/[^a-z0-9]/', '', strtolower((string)$s));
}

function st_cut($s, $max) {
    return mb_strlen($s) > $max ? mb_substr($s, 0, $max) : $s;
}

// ---------------------------------------------------------------------
// Fixed sets. Each returns [value|null, status]  status: ok | blank | unknown
// ---------------------------------------------------------------------

function st_norm_gender($raw) {
    $s = st_text($raw);
    if (st_blankish($s)) return [null, 'blank'];
    $k = st_key($s);
    if (in_array($k, ['m', 'male', 'man', 'boy', 'lalaki', 'masculine'], true)) return ['Male', 'ok'];
    if (in_array($k, ['f', 'female', 'woman', 'girl', 'babae', 'feminine', 'fem'], true)) return ['Female', 'ok'];
    return [null, 'unknown'];
}

function st_norm_level($raw) {
    $s = strtolower(st_text($raw));
    if (st_blankish($s)) return [null, 'blank'];
    if (preg_match('/^(\d+)\.0+$/', $s, $m)) $s = $m[1];                  // "1.0"
    $s = preg_replace('/[^a-z0-9 ]+/', ' ', $s);
    $s = preg_replace('/\b(years?|yrs?|level|lvl|lv|grade|year-level)\b/', ' ', $s);
    $s = trim(preg_replace('/\s+/', ' ', $s));
    $n = 0;
    if (preg_match('/^(\d+)(st|nd|rd|th)?$/', $s, $m)) $n = (int)$m[1];
    elseif (preg_match('/^y(\d)$/', $s, $m)) $n = (int)$m[1];
    else {
        $words = ['first' => 1, 'one' => 1, 'second' => 2, 'two' => 2, 'third' => 3, 'three' => 3, 'fourth' => 4, 'four' => 4,
                  'i' => 1, 'ii' => 2, 'iii' => 3, 'iv' => 4];
        if (isset($words[$s])) $n = $words[$s];
    }
    if ($n >= 1 && $n <= count(ST_LEVELS)) return [ST_LEVELS[$n - 1], 'ok'];
    return [null, 'unknown'];
}

function st_norm_course($raw) {
    $s = st_text($raw);
    if (st_blankish($s)) return [null, 'blank'];
    $k = st_key($s);
    if ($k === 'jd' || strpos($k, 'jurisdoctor') === 0) return ['Juris Doctor', 'ok'];
    foreach (ST_COURSES as $c) if (st_key($c) === $k) return [$c, 'ok'];
    return [null, 'unknown'];
}

// Philippine mobile numbers that lost their leading 0 in Excel (9171234567 -> 09171234567)
function st_norm_contact($raw) {
    $s = st_text($raw);
    if (st_blankish($s)) return null;
    if (preg_match('/^\d{10}$/', $s) && $s[0] === '9') $s = '0' . $s;
    return st_cut($s, 20);
}

// ---------------------------------------------------------------------
// Names: "Last, First M. Suffix"
// ---------------------------------------------------------------------

// MI cell -> "M."  (a full middle name is cut down to its first letter when $fromFullName)
function st_initial($raw, $fromFullName = false) {
    $s = st_text($raw);
    if (st_blankish($s)) return '';
    $letters = preg_replace('/[\s.]+/u', '', $s);
    if ($fromFullName) {
        $first = preg_split('/\s+/u', $s)[0];
        return mb_strtoupper(mb_substr($first, 0, 1)) . '.';
    }
    if (preg_match('/^\p{L}$/u', $letters)) return mb_strtoupper($letters) . '.';                 // M  /  M.
    if (preg_match('/^\p{L}{2,3}$/u', $letters) && strpos($s, '.') !== false)                    // M.R.
        return implode('.', preg_split('//u', mb_strtoupper($letters), -1, PREG_SPLIT_NO_EMPTY)) . '.';
    return $s;                                                                                    // anything else: as written
}

function st_suffix($raw) {
    $s = st_text($raw);
    if (st_blankish($s)) return '';
    if (preg_match('/^(jr|sr)\.?$/i', $s, $m)) return ucfirst(strtolower($m[1])) . '.';
    if (preg_match('/^(i{1,3}|iv|vi{0,3}|ix|x)$/i', $s)) return strtoupper($s);
    return $s;
}

function st_merge_name($last, $first, $mi = '', $sfx = '', $middleIsFull = false) {
    $last = st_text($last); $first = st_text($first);
    $mi = st_initial($mi, $middleIsFull);
    $sfx = st_suffix($sfx);
    $given = trim($first . ($mi !== '' ? ' ' . $mi : '') . ($sfx !== '' ? ' ' . $sfx : ''));
    if ($last !== '' && $given !== '') return $last . ', ' . $given;
    return $last !== '' ? $last : $given;
}

// ---------------------------------------------------------------------
// Finding the columns of the file by their headings
// ---------------------------------------------------------------------

function st_field_labels() {
    return [
        'student_no'  => 'Student number (ID)',
        'last_name'   => 'Last name',
        'first_name'  => 'First name',
        'mi'          => 'Middle initial',
        'middle_name' => 'Middle name (full)',
        'suffix'      => 'Suffix (Jr., III ...)',
        'full_name'   => 'Full name (one column)',
        'gender'      => 'Gender',
        'course'      => 'Course',
        'year_level'  => 'Year level',
        'contact'     => 'Contact number',
        'status'      => 'Enrollment status (to filter)',
    ];
}

// Ordered: a heading earlier in the list wins over a later one.
function st_header_aliases() {
    return [
        'student_no'  => ['id', 'studentid', 'studentno', 'studentnumber', 'idno', 'idnumber', 'studno', 'studentidno', 'stuid', 'studid', 'idnum'],
        'last_name'   => ['lname', 'lastname', 'surname', 'familyname', 'last'],
        'first_name'  => ['fname', 'firstname', 'givenname', 'first'],
        'mi'          => ['mi', 'middleinitial', 'minitial', 'mid'],
        'middle_name' => ['middlename', 'mname', 'middle'],
        'suffix'      => ['sfx', 'suffix', 'nameext', 'nameextension', 'extension', 'ext'],
        'full_name'   => ['fullname', 'studentname', 'nameofstudent', 'name'],
        'gender'      => ['gender', 'sex'],
        'course'      => ['course', 'coursecode', 'degree', 'program', 'programcode'],
        'year_level'  => ['level', 'yearlevel', 'yrlevel', 'yearlvl', 'lvl', 'year', 'yr', 'gradelevel'],
        'contact'     => ['contactno', 'contactnumber', 'contact', 'mobile', 'mobileno', 'mobilenumber', 'cellphone', 'cellno', 'phone', 'phoneno', 'cp'],
        'status'      => ['enrstatus', 'enrollmentstatus', 'enrolmentstatus', 'status'],
    ];
}

// Maps field => column index for ONE heading row.
function st_match_headings($row) {
    $norm = [];
    foreach ($row as $c => $cell) { $k = st_key(st_text($cell)); if ($k !== '') $norm[$c] = $k; }
    $map = []; $used = [];
    foreach (st_header_aliases() as $field => $aliases) {
        foreach ($aliases as $a) {
            $c = array_search($a, $norm, true);
            if ($c !== false && !isset($used[$c])) { $map[$field] = $c; $used[$c] = true; break; }
        }
    }
    return $map;
}

function st_map_is_usable($map) {
    return isset($map['student_no']) && (isset($map['full_name']) || isset($map['last_name']) || isset($map['first_name']));
}

// Looks at the first rows for the heading row. Returns [row index, map] or null.
function st_find_header($rows, $limit = 25) {
    $best = null; $bestScore = 0;
    $n = min($limit, count($rows));
    for ($i = 0; $i < $n; $i++) {
        $map = st_match_headings($rows[$i]);
        if (!st_map_is_usable($map)) continue;
        if (count($map) > $bestScore) { $best = [$i, $map]; $bestScore = count($map); }
    }
    return $best;
}

// ---------------------------------------------------------------------
// Reading the file (xls, xlsx or csv)
// ---------------------------------------------------------------------

// Returns ['rows' => raw cells, 'fmt' => cells as displayed (keeps leading zeros), 'sheet' => name,
//          'sheets' => [names], 'found' => [hdr, map]|null]
function st_read_file($path, $ext = '') {
    @set_time_limit(180);
    @ini_set('memory_limit', '768M');
    $type = \PhpOffice\PhpSpreadsheet\IOFactory::identify($path);
    $ext = strtolower($ext);
    $okTypes = ($ext === 'csv') ? ['Csv'] : (($ext === 'xls' || $ext === 'xlsx') ? ['Xls', 'Xlsx'] : ['Xls', 'Xlsx', 'Csv']);
    if (!in_array($type, $okTypes, true)) {
        throw new Exception('This is not a real ' . ($ext === 'csv' ? 'CSV' : 'Excel') . ' file (its content does not match its name). Open it in Excel and save it again as .xlsx.');
    }
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($type);
    if ($type === 'Csv') $reader->setInputEncoding(\PhpOffice\PhpSpreadsheet\Reader\Csv::GUESS_ENCODING);
    $book = $reader->load($path);
    $names = $book->getSheetNames();
    $pickIdx = 0; $found = null; $rowsPick = null;
    foreach ($book->getAllSheets() as $i => $sh) {
        $rows = $sh->toArray(null, true, false, false);
        $f = st_find_header($rows);
        if ($rowsPick === null) $rowsPick = $rows;           // the first sheet is the fallback
        if ($f) { $pickIdx = $i; $found = $f; $rowsPick = $rows; break; }
    }
    $sheet = $book->getSheet($pickIdx);
    $fmt = $sheet->toArray(null, true, true, false);
    $out = ['rows' => $rowsPick, 'fmt' => $fmt, 'sheet' => $names[$pickIdx], 'sheets' => $names, 'found' => $found];
    $book->disconnectWorksheets();
    return $out;
}

// The ID as it is shown in the cell, so "0012345" keeps its zeros
function st_id($raw, $shown) {
    $r = st_text($raw);
    $s = st_text($shown);
    if ($r !== '' && $s !== '' && $r !== $s && ctype_digit($s) && ctype_digit($r) && ltrim($s, '0') === ltrim($r, '0')) return $s;
    return $r;
}

// "A", "B" ... "AA"
function st_col_letter($i) {
    $s = '';
    for ($i = (int)$i + 1; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s;
    return $s;
}

// ---------------------------------------------------------------------
// Do the new columns exist yet? (migration_students_format.sql)
// ---------------------------------------------------------------------
function st_columns_ready($conn) {
    $have = [];
    $r = @mysqli_query($conn, "SHOW COLUMNS FROM students");
    if (!$r) return false;
    while ($c = mysqli_fetch_assoc($r)) $have[$c['Field']] = true;
    return isset($have['gender'], $have['year_level']);
}

function st_migration_notice() {
    return '<div class="alert alert-warning mb-3"><strong>One more step is needed.</strong> The student table has not been updated yet. '
         . 'In phpMyAdmin choose <strong>law_library_db</strong> &rarr; <strong>SQL</strong> tab, paste the contents of <code>migration_students_format.sql</code> and press <strong>Go</strong>, then reload this page.</div>';
}

// ---------------------------------------------------------------------
// The import itself (also used for the preview: $commit = false)
//
//   $opts: filter_course (array of raw values to keep | null = all), filter_status (same),
//          update_existing (bool), valmap [field][bin2hex(raw)] => chosen value | '' (leave blank)
// Returns a report array. Throws Exception when the mapping is unusable.
// ---------------------------------------------------------------------
function st_import($conn, $rows, $fmt, $hdr, $map, $opts, $commit) {
    if (!st_map_is_usable($map)) {
        throw new Exception('Choose the column that holds the student number and the column(s) that hold the name.');
    }
    $useParts = isset($map['last_name']) || isset($map['first_name']);
    $update   = !empty($opts['update_existing']);
    $fCourse  = isset($opts['filter_course']) && is_array($opts['filter_course']) ? array_flip($opts['filter_course']) : null;
    $fStatus  = isset($opts['filter_status']) && is_array($opts['filter_status']) ? array_flip(array_map('mb_strtolower', $opts['filter_status'])) : null;
    $statusLabel = [];
    $reactivate = array_key_exists('reactivate', $opts) ? !empty($opts['reactivate']) : true;     // returning students come back
    $deactivateMissing = !empty($opts['deactivate_missing']);                                       // active students not in the file
    $confirmMass = !empty($opts['confirm_mass']);
    $valmap   = isset($opts['valmap']) && is_array($opts['valmap']) ? $opts['valmap'] : [];

    $rep = [
        'rows' => 0, 'new' => 0, 'updated' => 0, 'unchanged' => 0, 'already' => 0, 'filtered' => 0, 'blank_rows' => 0,
        'missing_id' => [], 'missing_name' => [], 'dup_in_file' => [], 'truncated' => 0,
        'unknown' => ['gender' => [], 'year_level' => [], 'course' => []],
        'courses' => [], 'statuses' => [], 'samples' => [], 'changes' => [], 'no_course_column' => !isset($map['course']),
        'returning' => [], 'reactivated' => 0, 'deactivate_asked' => false, 'mass_blocked' => false, 'missing' => [], 'deactivated' => 0, 'kept_active' => 0, 'active_total' => 0, 'mass' => false,
    ];

    // everybody already in the system, by student number
    $existing = [];
    $q = mysqli_query($conn, "SELECT id, student_no, name, gender, course, year_level, contact_number, is_active FROM students");
    while ($r = mysqli_fetch_assoc($q)) $existing[mb_strtolower(trim($r['student_no']))] = $r;

    $ins = mysqli_prepare($conn, "INSERT INTO students (student_no, name, gender, course, year_level, contact_number) VALUES (?, ?, ?, ?, ?, ?)");
    $upd = mysqli_prepare($conn, "UPDATE students SET name = ?, gender = ?, course = ?, year_level = ?, contact_number = ? WHERE id = ?");
    $setActive = mysqli_prepare($conn, "UPDATE students SET is_active = ? WHERE id = ?");
    $present = [];       // student numbers that are in this file (after the course / status choice)

    $cell = function ($row, $field) use ($map) {
        return (isset($map[$field]) && array_key_exists($map[$field], $row)) ? $row[$map[$field]] : null;
    };

    $seen = [];
    if ($commit) mysqli_begin_transaction($conn);
    try {
        for ($i = $hdr + 1; $i < count($rows); $i++) {
            $excelRow = $i + 1;
            $row = $rows[$i];
            $has = false;
            foreach ($row as $c) { if ($c !== null && trim((string)$c) !== '') { $has = true; break; } }
            if (!$has) { $rep['blank_rows']++; continue; }
            $rep['rows']++;

            // ---- raw values for the filters (counted for every row, before anything is skipped)
            $courseRaw = st_text($cell($row, 'course'));
            $courseGroup = $courseRaw;                                    // JD / Jd / Juris Doctor count as ONE course
            if ($courseRaw !== '') { list($cg, $cgs) = st_norm_course($courseRaw); if ($cgs === 'ok') $courseGroup = $cg; }
            $statusRaw = st_text($cell($row, 'status'));
            $statusKey = mb_strtolower($statusRaw);
            if ($statusRaw !== '' && !isset($statusLabel[$statusKey])) $statusLabel[$statusKey] = $statusRaw;
            if (isset($map['course']) && $courseGroup !== '') $rep['courses'][$courseGroup] = ($rep['courses'][$courseGroup] ?? 0) + 1;
            if (isset($map['status']) && $statusRaw !== '') $rep['statuses'][$statusLabel[$statusKey]] = ($rep['statuses'][$statusLabel[$statusKey]] ?? 0) + 1;

            $fmtRow = isset($fmt[$i]) ? $fmt[$i] : [];
            $no = isset($map['student_no']) ? st_id($row[$map['student_no']] ?? null, $fmtRow[$map['student_no']] ?? null) : '';
            if ($no === '') { $rep['missing_id'][] = $excelRow; continue; }

            if ($useParts) {
                $name = st_merge_name($cell($row, 'last_name'), $cell($row, 'first_name'),
                                      isset($map['mi']) ? $cell($row, 'mi') : (isset($map['middle_name']) ? $cell($row, 'middle_name') : ''),
                                      $cell($row, 'suffix'), !isset($map['mi']) && isset($map['middle_name']));
            } else {
                $name = st_text($cell($row, 'full_name'));
            }
            $presentKey = mb_strtolower($no);
            if ($name === '') { $rep['missing_name'][] = ['row' => $excelRow, 'no' => $no]; $present[$presentKey] = true; continue; }

            if ($fCourse !== null && isset($map['course']) && !isset($fCourse[$courseGroup])) { $rep['filtered']++; continue; }
            if ($fStatus !== null && isset($map['status']) && !isset($fStatus[$statusKey])) { $rep['filtered']++; continue; }

            $present[$presentKey] = true;
            $key = mb_strtolower($no);
            if (isset($seen[$key])) { $rep['dup_in_file'][] = ['row' => $excelRow, 'no' => $no, 'first' => $seen[$key]]; continue; }
            $seen[$key] = $excelRow;

            // ---- the three fixed sets
            $vals = [];
            foreach (['gender' => 'st_norm_gender', 'year_level' => 'st_norm_level', 'course' => 'st_norm_course'] as $field => $fn) {
                if (!isset($map[$field])) { $vals[$field] = null; continue; }
                $raw = st_text($cell($row, $field));
                list($v, $status) = $fn($raw);
                if ($status === 'unknown') {
                    $hex = bin2hex($raw);
                    $chosen = isset($valmap[$field][$hex]) ? (string)$valmap[$field][$hex] : null;
                    $allowed = $field === 'gender' ? ST_GENDERS : ($field === 'year_level' ? ST_LEVELS : ST_COURSES);
                    if (!isset($rep['unknown'][$field][$raw])) $rep['unknown'][$field][$raw] = ['count' => 0, 'chosen' => ''];
                    $rep['unknown'][$field][$raw]['count']++;
                    if ($chosen !== null && in_array($chosen, $allowed, true)) { $v = $chosen; $rep['unknown'][$field][$raw]['chosen'] = $chosen; }
                }
                $vals[$field] = $v;
            }
            $contact = isset($map['contact']) ? st_norm_contact($cell($row, 'contact')) : null;

            $before = mb_strlen($name) + mb_strlen($no);
            $no = st_cut($no, 50); $name = st_cut($name, 150);
            if (mb_strlen($name) + mb_strlen($no) < $before) $rep['truncated']++;

            // course is NOT NULL in the table: no Course column at all -> the only course; unknown & left blank -> ''
            $courseVal = $vals['course'];
            if ($courseVal === null) $courseVal = isset($map['course']) ? '' : ST_COURSES[0];

            $old = $existing[$key] ?? null;
            if ($old && (int)$old['is_active'] === 0) {
                $rep['returning'][] = ['no' => $old['student_no'], 'name' => $old['name']];
                if ($reactivate) {
                    $rep['reactivated']++;
                    if ($commit) {
                        $one = 1; $oid = (int)$old['id'];
                        mysqli_stmt_bind_param($setActive, 'ii', $one, $oid);
                        if (!mysqli_stmt_execute($setActive)) throw new Exception('A student could not be reactivated (row ' . $excelRow . ').');
                    }
                }
            }
            if ($old) {
                if (!$update) { $rep['already']++; continue; }
                $new = ['name' => $name, 'gender' => $vals['gender'], 'course' => $courseVal, 'year_level' => $vals['year_level'], 'contact_number' => $contact];
                $diff = [];
                foreach ($new as $f => $v) {
                    if ($v === null || $v === '') continue;                      // the file has nothing for it: keep what we have
                    if ((string)$old[$f] !== (string)$v) $diff[$f] = [$old[$f], $v];
                }
                if (!$diff) { $rep['unchanged']++; continue; }
                $rep['updated']++;
                if (count($rep['changes']) < 8) $rep['changes'][] = ['no' => $no, 'diff' => $diff];
                if ($commit) {
                    $f = ['name' => $old['name'], 'gender' => $old['gender'], 'course' => $old['course'], 'year_level' => $old['year_level'], 'contact_number' => $old['contact_number']];
                    foreach ($diff as $k => $d) $f[$k] = $d[1];
                    $oid = (int)$old['id'];
                    mysqli_stmt_bind_param($upd, 'sssssi', $f['name'], $f['gender'], $f['course'], $f['year_level'], $f['contact_number'], $oid);
                    if (!mysqli_stmt_execute($upd)) throw new Exception('A student could not be updated (row ' . $excelRow . ').');
                }
                continue;
            }

            $rep['new']++;
            if (count($rep['samples']) < 6) $rep['samples'][] = $name;
            if ($commit) {
                mysqli_stmt_bind_param($ins, 'ssssss', $no, $name, $vals['gender'], $courseVal, $vals['year_level'], $contact);
                if (!mysqli_stmt_execute($ins)) throw new Exception('A student could not be saved (row ' . $excelRow . '): ' . mysqli_error($conn));
            }
        }

        // ---- active students that are not in this file
        $loans = [];
        $lq = mysqli_query($conn, "SELECT DISTINCT student_id FROM borrowing WHERE return_date IS NULL");
        if ($lq) while ($l = mysqli_fetch_row($lq)) $loans[(int)$l[0]] = true;
        $activeTotal = 0; $todo = [];
        foreach ($existing as $k => $o) {
            if ((int)$o['is_active'] !== 1) continue;
            $activeTotal++;
            if (isset($present[$k])) continue;
            $hasLoan = isset($loans[(int)$o['id']]);
            $rep['missing'][] = ['id' => (int)$o['id'], 'no' => $o['student_no'], 'name' => $o['name'], 'loan' => $hasLoan];
            if (!$hasLoan) $todo[] = (int)$o['id'];
        }
        $rep['active_total'] = $activeTotal;
        $rep['mass'] = $activeTotal > 0 && count($todo) > 0.5 * $activeTotal;
        $rep['kept_active'] = count($rep['missing']) - count($todo);
        $rep['deactivate_asked'] = $deactivateMissing;
        if ($deactivateMissing && $rep['mass'] && !$confirmMass) {
            if ($commit) {
                throw new Exception('This would deactivate ' . count($todo) . ' of the ' . $activeTotal . ' active students. If the file is only a part of the list, import it without ticking "Deactivate them". Otherwise tick "I understand" and try again.');
            }
            $rep['mass_blocked'] = true;       // preview: show the warning, keep the Import button off
        } elseif ($deactivateMissing) {
            $rep['deactivated'] = count($todo);
            if ($commit) {
                foreach ($todo as $sid) {
                    $zero = 0;
                    mysqli_stmt_bind_param($setActive, 'ii', $zero, $sid);
                    if (!mysqli_stmt_execute($setActive)) throw new Exception('A student could not be deactivated.');
                }
            }
        }
        if ($commit) mysqli_commit($conn);
    } catch (Throwable $e) {
        if ($commit) mysqli_rollback($conn);
        throw new Exception($e->getMessage());
    }
    return $rep;
}

function st_h($v) {
    return htmlspecialchars($v === null ? '' : (string)$v, ENT_QUOTES, 'UTF-8');
}
