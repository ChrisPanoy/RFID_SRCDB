<?php
include 'includes/db.php';
header('Content-Type: text/plain');

$student_name = "CHRISTOPHER"; // searching for Panoy
echo "--- SEARCHING FOR STUDENT: $student_name ---\n";
$res_st = $conn->query("SELECT student_id, first_name, last_name FROM students WHERE first_name LIKE '%$student_name%' OR last_name LIKE '%$student_name%'");
while ($s = $res_st->fetch_assoc()) {
    echo "SId: {$s['student_id']} | Name: {$s['first_name']} {$s['last_name']}\n";
    $sid = $s['student_id'];
    
    echo "  Admissions:\n";
    $res_adm = $conn->query("SELECT adm.admission_id, sc.subject_id, subj.subject_name 
                             FROM admission adm 
                             JOIN schedule sc ON adm.schedule_id = sc.schedule_id
                             JOIN subject subj ON sc.subject_id = subj.subject_id
                             WHERE adm.student_id = '$sid'");
    while ($adm = $res_adm->fetch_assoc()) {
        echo "    AdmID: {$adm['admission_id']} | SubjID: {$adm['subject_id']} | Subj: {$adm['subject_name']}\n";
        $aid = $adm['admission_id'];
        
        echo "      Attendance:\n";
        $res_att = $conn->query("SELECT * FROM attendance WHERE admission_id = $aid");
        while ($att = $res_att->fetch_assoc()) {
            echo "        AttID: {$att['attendance_id']} | Date: {$att['attendance_date']} | Status: {$att['status']}\n";
        }
    }
}
?>
