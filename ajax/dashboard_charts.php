<?php
header('Content-Type: application/json');
include __DIR__ . '/../includes/db.php';

date_default_timezone_set('Asia/Manila');
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

$response = [
    'realtime' => [],
    'by_year' => [],
    'by_subject' => []
];

if (session_status() === PHP_SESSION_NONE) session_start();
$ay_id  = (int)($_SESSION['active_ay_id'] ?? 0);
$sem_id = (int)($_SESSION['active_sem_id'] ?? 0);

// =============================
// Realtime: Lab A vs Lab B
// =============================
// Count Present records per lab for the given attendance_date using src_db schema:
// attendance -> schedule -> facility
$sqlRealtime = "
    SELECT 
        CASE 
            WHEN fac.lab_name = 'Computer Lab A' THEN 'Lab A'
            WHEN fac.lab_name = 'Computer Lab B' THEN 'Lab B'
        END AS lab_name,
        COUNT(*) AS cnt
    FROM attendance a
    JOIN schedule sc ON a.schedule_id = sc.schedule_id
    JOIN facilities fac ON sc.lab_id = fac.lab_id
    JOIN admissions ad ON a.admission_id = ad.admission_id
    WHERE a.attendance_date = ?
      AND a.status = 'Present'
      AND fac.lab_name IN ('Computer Lab A', 'Computer Lab B')
      AND ad.academic_year_id = ?
      AND ad.semester_id = ?
    GROUP BY lab_name
";

if ($stmt = $conn->prepare($sqlRealtime)) {
    $stmt->bind_param('sii', $date, $ay_id, $sem_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        if ($r['lab_name'] !== null) {
            $response['realtime'][$r['lab_name']] = (int)$r['cnt'];
        }
    }
    $stmt->close();
}

// Ensure keys exist for chart even if 0
if (!isset($response['realtime']['Lab A'])) $response['realtime']['Lab A'] = 0;
if (!isset($response['realtime']['Lab B'])) $response['realtime']['Lab B'] = 0;

// =============================
// By year: stacked counts per status
// =============================
// attendance -> admission -> (optional) year_level
// We use LEFT JOIN so rows without a year_level_id are still counted under '0' / 'Unknown'.
$sqlByYear = "
    SELECT COALESCE(yl.level, 0) AS year, a.status, COUNT(*) AS cnt
    FROM attendance a
    JOIN admissions ad   ON a.admission_id = ad.admission_id
    LEFT JOIN year_levels yl ON ad.year_level_id = yl.year_id
    WHERE a.attendance_date = ?
      AND ad.academic_year_id = ?
      AND ad.semester_id = ?
    GROUP BY COALESCE(yl.level, 0), a.status
    ORDER BY COALESCE(yl.level, 0)
";

if ($stmt = $conn->prepare($sqlByYear)) {
    $stmt->bind_param('sii', $date, $ay_id, $sem_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $y = (string)$r['year'];
        if (!isset($response['by_year'][$y])) $response['by_year'][$y] = [];
        $response['by_year'][$y][$r['status']] = (int)$r['cnt'];
    }
    $stmt->close();
}

// =============================
// By subject: top subjects by Time In (Present/Late) In CURRENT session
// =============================
// attendance -> schedule -> subject
$sqlBySubject = "
    SELECT subj.subject_name AS subject, COUNT(*) AS cnt
    FROM attendance a
    JOIN schedule sc ON a.schedule_id = sc.schedule_id
    JOIN subjects  subj ON sc.subject_id = subj.subject_id
    JOIN admissions ad ON a.admission_id = ad.admission_id
    WHERE a.attendance_date = ?
      AND a.status IN ('Present','Late')
      AND ad.academic_year_id = ?
      AND ad.semester_id = ?
    GROUP BY subj.subject_name
    ORDER BY cnt DESC
    LIMIT 10
";

if ($stmt = $conn->prepare($sqlBySubject)) {
    $stmt->bind_param('sii', $date, $ay_id, $sem_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $response['by_subject'][] = [
            'subject' => $r['subject'],
            'count'   => (int)$r['cnt']
        ];
    }
    $stmt->close();
}

echo json_encode($response);

?>
