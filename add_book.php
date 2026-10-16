<?php
// Saves a new book from the "Add New Book" form on the Book Inventory page.
// (auth.php is not included because it prints HTML; the login check is done directly.)
session_start();
if (!isset($_SESSION['staff_logged_in'])) {
    header('Location: login.php');
    exit();
}

include('db.php');
include('book_functions.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: books.php');
    exit();
}

list($d, $errors) = read_book_form($_POST);

$copies = isset($_POST['total_copies']) ? intval($_POST['total_copies']) : 1;
if ($copies < 1) {
    $errors[] = 'Number of copies must be at least 1.';
}

if (!$errors && acc_no_exists($conn, $d['acc_nos'])) {
    $errors[] = 'Acc. No(s). "' . $d['acc_nos'] . '" is already used by another book.';
}

if (!$errors && !insert_book($conn, $d, $copies)) {
    $errors[] = 'The book could not be saved. Please try again.';
}

if ($errors) {
    // go back to the form with what was typed, so nothing has to be retyped
    $_SESSION['book_form'] = ['errors' => $errors, 'old' => $_POST];
    header('Location: books.php');
    exit();
}

header('Location: books.php?msg=added');
exit();
