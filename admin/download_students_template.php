<?php
// Force download of a CSV template compatible with Excel
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="students_template.csv"');

$out = fopen('php://output', 'w');
// UTF-8 BOM for Excel compatibility
fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Headers expected by the importer in students.php
fputcsv($out, [
    'ID',        // Student ID
    'Name',      // Full name
    'Section',   // e.g., A or B
    'Year',      // 1..4
    'Gender',    // Male/Female
    'Photo',     // optional: existing filename in ../assets/img or URL
    'Barcode',   // optional: defaults to ID if empty
    'PC No.'     // optional
]);

// Prefill first data row with a formula so Barcode mirrors ID when opened in Excel.
// Note: CSV can hold the formula text; Excel will evaluate it when opening.
$firstRowIndex = 2; // header is row 1, first data row is row 2
fputcsv($out, [
    '2025-0001',     // ID sample
    'Juan Dela Cruz',// Name sample
    'A',             // Section sample
    '1',             // Year sample
    'Male',          // Gender sample
    '',              // Photo
    "=A{$firstRowIndex}", // Barcode = ID (Excel formula)
    ''               // PC No.
]);

// Optional hint row (purely informational). Users can delete it.
fputcsv($out, [
    'HINT: Duplicate rows below and change values. Barcode will follow ID via formula (=A[row]).',
]);

fclose($out);
exit;




