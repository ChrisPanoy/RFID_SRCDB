<?php
session_start();
include '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['teacher_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$teacher_id_int = (int)$teacher_id;
$subject_id = isset($_GET['subject']) ? intval($_GET['subject']) : 0;

if (!$subject_id) {
    echo json_encode(['error' => 'Subject ID required']);
    exit();
}

// Fetch all students for this subject and their attendance stats
// Using the same logic as teacher_students.php
$sql = "
    SELECT DISTINCT
        st.student_id,
        (SELECT COUNT(*) 
         FROM attendance a 
         JOIN admissions adm2 ON a.admission_id = adm2.admission_id
         JOIN schedule sc2 ON adm2.schedule_id = sc2.schedule_id
         WHERE adm2.student_id = st.student_id 
           AND sc2.subject_id = ?
           AND a.status IN ('Present', 'Late')) AS total_present,
        (SELECT COUNT(*) FROM attendance a 
         JOIN admissions adm2 ON a.admission_id = adm2.admission_id
         JOIN schedule sc2 ON adm2.schedule_id = sc2.schedule_id
         WHERE adm2.student_id = st.student_id 
           AND sc2.subject_id = ?
        ) AS total_sessions,
        (SELECT GROUP_CONCAT(CONCAT_WS('|', DATE_FORMAT(a.attendance_date, '%M %d, %Y'), TIME_FORMAT(a.time_in, '%h:%i %p'), COALESCE(TIME_FORMAT(a.time_out, '%h:%i %p'), '---'), a.status) ORDER BY a.attendance_date DESC SEPARATOR '||')
         FROM attendance a
         JOIN admissions adm2 ON a.admission_id = adm2.admission_id
         JOIN schedule sc2 ON adm2.schedule_id = sc2.schedule_id
         WHERE adm2.student_id = st.student_id 
           AND sc2.subject_id = ?
        ) AS detailed_history
    FROM admissions adm
    JOIN students st   ON adm.student_id   = st.student_id
    JOIN schedule sc   ON adm.schedule_id = sc.schedule_id
    WHERE sc.subject_id = ? AND sc.employee_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiiii", $subject_id, $subject_id, $subject_id, $subject_id, $teacher_id_int);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[$row['student_id']] = [
        'total' => (int)$row['total_present'],
        'sessions' => (int)$row['total_sessions'],
        'history' => $row['detailed_history'] ?: ''
    ];
}

echo json_encode(['success' => true, 'data' => $data]);
