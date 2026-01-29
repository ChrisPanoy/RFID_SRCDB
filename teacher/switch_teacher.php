<?php
session_start();

// Only allow switching if teachers array exists
if (!isset($_GET['key']) || !isset($_SESSION['teachers']) || !is_array($_SESSION['teachers'])) {
    header('Location: teacher_dashboard.php');
    exit();
}

$key = $_GET['key'];
if (!isset($_SESSION['teachers'][$key])) {
    header('Location: teacher_dashboard.php');
    exit();
}

// Set active teacher and backfill legacy vars
$_SESSION['active_teacher_id'] = $key;
$rec = $_SESSION['teachers'][$key];
$_SESSION['teacher_id'] = $rec['teacher_id'] ?? '';
$_SESSION['teacher_name'] = $rec['teacher_name'] ?? '';
$_SESSION['teacher_email'] = $rec['teacher_email'] ?? '';
$_SESSION['teacher_department'] = $rec['teacher_department'] ?? '';

// Redirect back to referring page or dashboard
$redirect = 'teacher_dashboard.php';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $redirect = $_SERVER['HTTP_REFERER'];
}
header('Location: ' . $redirect);
exit();



