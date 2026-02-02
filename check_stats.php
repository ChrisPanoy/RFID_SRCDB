<?php
include 'includes/db.php';
header('Content-Type: text/plain');

$subject_id = 58;
echo "--- CHECKING ALL ATTENDANCE FOR SUBJECT $subject_id ---\n";

$sql = "SELECT a.attendance_id, a.attendance_date, a.status, st.first_name, st.last_name, adm.student_id
        FROM attendance a
        JOIN admission adm ON a.admission_id = adm.admission_id
        JOIN students st ON adm.student_id = st.student_id
        JOIN schedule sc ON adm.schedule_id = sc.schedule_id
        WHERE sc.subject_id = $subject_id";

$res = $conn->query($sql);
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        echo "Date: {$row['attendance_date']} | Status: {$row['status']} | Student: {$row['first_name']} {$row['last_name']} ({$row['student_id']})\n";
    }
} else {
    echo "No attendance records found for Subject $subject_id.\n";
}

echo "\n--- TOTALS PER STATUS ---\n";
$sql_stats = "SELECT a.status, COUNT(*) as count
              FROM attendance a
              JOIN admission adm ON a.admission_id = adm.admission_id
              JOIN schedule sc ON adm.schedule_id = sc.schedule_id
              WHERE sc.subject_id = $subject_id
              GROUP BY a.status";
$res_stats = $conn->query($sql_stats);
if ($res_stats) {
    while ($row = $res_stats->fetch_assoc()) {
        echo "Status: {$row['status']} | Count: {$row['count']}\n";
    }
}
?>
