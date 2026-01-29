<?php
session_start();

// If using multiple teacher sessions, remove only the active one
if (isset($_SESSION['active_teacher_id']) && isset($_SESSION['teachers']) && is_array($_SESSION['teachers'])) {
	$key = $_SESSION['active_teacher_id'];
	if (isset($_SESSION['teachers'][$key])) {
		unset($_SESSION['teachers'][$key]);
	}

	// Remove active pointer
	unset($_SESSION['active_teacher_id']);

	// If there are still other teachers logged in, pick the first as active
	if (!empty($_SESSION['teachers'])) {
		reset($_SESSION['teachers']);
		$first_key = key($_SESSION['teachers']);
		$_SESSION['active_teacher_id'] = $first_key;
		$rec = $_SESSION['teachers'][$first_key];
		// Backfill legacy single-teacher vars for compatibility
		$_SESSION['teacher_id'] = $rec['teacher_id'] ?? '';
		$_SESSION['teacher_name'] = $rec['teacher_name'] ?? '';
		$_SESSION['teacher_email'] = $rec['teacher_email'] ?? '';
		$_SESSION['teacher_department'] = $rec['teacher_department'] ?? '';
	} else {
		// No more teachers logged in: clear legacy vars and teachers array
		unset($_SESSION['teacher_id']);
		unset($_SESSION['teacher_name']);
		unset($_SESSION['teacher_email']);
		unset($_SESSION['teacher_department']);
		unset($_SESSION['teachers']);
	}
} else {
	// Legacy single-teacher logout fallback
	unset($_SESSION['teacher_id']);
	unset($_SESSION['teacher_name']);
	unset($_SESSION['teacher_email']);
	unset($_SESSION['teacher_department']);
	unset($_SESSION['active_teacher_id']);
	unset($_SESSION['teachers']);
}

// Redirect to main page
header("Location: ../index.php");
exit();
?> 


