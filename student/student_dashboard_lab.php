<?php
// Standalone per-lab student dashboard with live feed and scan box
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/kiosk_config.php';

date_default_timezone_set('Asia/Manila');

$todayAbbrev = date('D'); // Mon, Tue, ...

// Auth: This page is now kiosk-friendly. If no session, it will still work via kiosk token with the AJAX endpoints.

// Prefer lab_id (schedule.lab_id) as URL parameter; keep legacy 'lab' (lab_name) as fallback.
$lab_id_param   = isset($_GET['lab_id']) ? (int)$_GET['lab_id'] : 0;
$lab_name_param = isset($_GET['lab']) ? trim($_GET['lab']) : '';

$lab_id   = 0;
$lab_name = '';

if ($lab_id_param > 0) {
    // Treat lab_id as authoritative for filtering schedule.lab_id.
    $lab_id = $lab_id_param;
    // Optional: try to resolve a friendly name from facility, but don't fail if missing.
    $lab_stmt = $conn->prepare("SELECT lab_name FROM facilities WHERE lab_id = ? LIMIT 1");
    if ($lab_stmt) {
        $lab_stmt->bind_param('i', $lab_id_param);
        $lab_stmt->execute();
        $lab_res = $lab_stmt->get_result();
        if ($lab_row = $lab_res->fetch_assoc()) {
            $lab_name = $lab_row['lab_name'];
        }
        $lab_stmt->close();
    }
} elseif ($lab_name_param !== '') {
    // Legacy: look up lab by name via facility with tolerant matching
    $tried = false;
    // 1) exact provided name
    if ($lab_stmt = $conn->prepare("SELECT lab_id, lab_name FROM facilities WHERE lab_name = ? LIMIT 1")) {
        $lab_stmt->bind_param('s', $lab_name_param);
        $lab_stmt->execute();
        $lab_res = $lab_stmt->get_result();
        if ($lab_row = $lab_res->fetch_assoc()) {
            $lab_id = (int)$lab_row['lab_id'];
            $lab_name = $lab_row['lab_name'];
        }
        $lab_stmt->close();
        $tried = true;
    }
    // 2) common synonyms ("Computer Lab X" <-> "Computer Laboratory X")
    if ($lab_id === 0) {
        $syn = $lab_name_param;
        if (stripos($syn, 'Laboratory') !== false) {
            $syn = str_ireplace('Laboratory', 'Lab', $syn);
        } else {
            $syn = str_ireplace('Lab', 'Laboratory', $syn);
        }
        if ($lab_stmt = $conn->prepare("SELECT lab_id, lab_name FROM facilities WHERE lab_name = ? LIMIT 1")) {
            $lab_stmt->bind_param('s', $syn);
            $lab_stmt->execute();
            $lab_res = $lab_stmt->get_result();
            if ($lab_row = $lab_res->fetch_assoc()) {
                $lab_id = (int)$lab_row['lab_id'];
                $lab_name = $lab_row['lab_name'];
            }
            $lab_stmt->close();
        }
    }
    // 3) LIKE match using last word (e.g., trailing letter A/B/C)
    if ($lab_id === 0) {
        $token = trim(preg_replace('/^.*\\s+([A-Z])$/i', '$1', $lab_name_param));
        if ($token !== '') {
            $like = '%' . $token . '%';
            if ($lab_stmt = $conn->prepare("SELECT lab_id, lab_name FROM facilities WHERE lab_name LIKE ? ORDER BY lab_id ASC LIMIT 1")) {
                $lab_stmt->bind_param('s', $like);
                $lab_stmt->execute();
                $lab_res = $lab_stmt->get_result();
                if ($lab_row = $lab_res->fetch_assoc()) {
                    $lab_id = (int)$lab_row['lab_id'];
                    $lab_name = $lab_row['lab_name'];
                }
                $lab_stmt->close();
            }
        }
    }
    // 4) As a last resort, any facility containing provided phrase
    if ($lab_id === 0) {
        $like = '%' . $lab_name_param . '%';
        if ($lab_stmt = $conn->prepare("SELECT lab_id, lab_name FROM facilities WHERE lab_name LIKE ? ORDER BY lab_id ASC LIMIT 1")) {
            $lab_stmt->bind_param('s', $like);
            $lab_stmt->execute();
            $lab_res = $lab_stmt->get_result();
            if ($lab_row = $lab_res->fetch_assoc()) {
                $lab_id = (int)$lab_row['lab_id'];
                $lab_name = $lab_row['lab_name'];
            }
            $lab_stmt->close();
        }
    }
}

// If nothing was passed, default to lab_id = 1 (first lab)
// Only default when neither lab_id nor lab name was provided.
if ($lab_id_param === 0 && $lab_name_param === '' && $lab_id === 0 && $lab_name === '') {
    $lab_id = 1;
    // Try to resolve a friendly name from facility, but fall back if not found
    $lab_stmt = $conn->prepare("SELECT lab_name FROM facilities WHERE lab_id = 1 LIMIT 1");
    if ($lab_stmt) {
        $lab_stmt->execute();
        $lab_res = $lab_stmt->get_result();
        if ($lab_row = $lab_res->fetch_assoc()) {
            $lab_name = $lab_row['lab_name'];
        }
        $lab_stmt->close();
    }
}

// Fallback label if we have an ID but no resolved name
if ($lab_id > 0 && $lab_name === '') {
    $lab_name = 'Lab #' . $lab_id;
}

// Determine active schedule for this lab (today + time window)
$active_schedule_id = 0;
$active_subject_name = '';
$nowTimeForSchedule = date('H:i:s');

if ($lab_id > 0) {
    $activeSql = "SELECT 
                    sch.schedule_id,
                    sch.subject_id,
                    sch.time_start,
                    sch.time_end,
                    sch.schedule_days,
                    sub.subject_name
                 FROM schedule sch
                 JOIN subjects sub ON sch.subject_id = sub.subject_id
                 WHERE sch.lab_id = ?
                   AND (
                        sch.schedule_days IS NULL
                     OR sch.schedule_days = ''
                     OR FIND_IN_SET(?, REPLACE(sch.schedule_days, ' ', '')) > 0
                   )
                   AND (
                       (sch.time_start <= sch.time_end AND sch.time_start <= ? AND sch.time_end >= ?)
                    OR (sch.time_start > sch.time_end  AND (? >= sch.time_start OR ? <= sch.time_end))
                   )
                 ORDER BY sch.time_end ASC
                 LIMIT 1";
    if ($activeStmt = $conn->prepare($activeSql)) {
        $activeStmt->bind_param('isssss', $lab_id, $todayAbbrev, $nowTimeForSchedule, $nowTimeForSchedule, $nowTimeForSchedule, $nowTimeForSchedule);
        $activeStmt->execute();
        $activeRow = $activeStmt->get_result()->fetch_assoc();
        $activeStmt->close();

        if (!empty($activeRow)) {
            $active_schedule_id = (int)$activeRow['schedule_id'];
            $active_subject_name = (string)$activeRow['subject_name'];
        }
    }
}

// Handle scan submission (aligned to src_db schema)
$student = null;
$msg = "";
$scan_status = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barcode = trim($_POST['barcode'] ?? '');

    if (!$barcode) {
        $msg = "Please scan your ID.";
        $scan_status = "warn";
    } else {
        // 1) Find student by RFID
        $stmt = $conn->prepare("SELECT student_id, rfid_number, profile_picture, first_name, middle_name, last_name
                                FROM students
                                WHERE rfid_number = ?");
        $stmt->bind_param("s", $barcode);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
        $stmt->close();

        // 1.1) Fallback: Fetch student's most recent enrollment/admission info regardless of schedule
        // if the specific admission for active schedule isn't loaded later.
        $student_latest_info = null;
        if ($student) {
            $infoSql = "SELECT yl.year_name, sec.section_name 
                        FROM admissions adm
                        LEFT JOIN year_levels yl ON adm.year_level_id = yl.year_id
                        LEFT JOIN sections sec ON adm.section_id = sec.section_id
                        WHERE adm.student_id = ?
                        ORDER BY adm.admission_id DESC LIMIT 1";
            $infoStmt = $conn->prepare($infoSql);
            $infoStmt->bind_param('s', $student['student_id']);
            $infoStmt->execute();
            $student_latest_info = $infoStmt->get_result()->fetch_assoc();
            $infoStmt->close();
        }

        if ($student) {
            $student_id = $student['student_id'];
            $now        = date('Y-m-d H:i:s');
            $today      = date('Y-m-d');
            $nowTime    = date('H:i:s');

            // 2) Find active schedule in this lab right now (schedule.lab_id + subject)
            // Use lab_id (schedule.lab_id) for filtering to avoid lab_name spelling issues.
            // Time-window logic mirrors teacher_../scan.php: supports normal and overnight ranges.
            $schedSql = "SELECT 
                             sch.schedule_id,
                             sch.subject_id,
                             sch.time_start,
                             sch.time_end,
                             sch.schedule_days,
                             sub.subject_name
                         FROM schedule sch
                         JOIN subjects sub  ON sch.subject_id = sub.subject_id
                         WHERE sch.lab_id = ?
                           AND (
                                sch.schedule_days IS NULL
                             OR sch.schedule_days = ''
                             OR FIND_IN_SET(?, REPLACE(sch.schedule_days, ' ', '')) > 0
                           )
                           AND (
                               (sch.time_start <= sch.time_end AND sch.time_start <= ? AND sch.time_end >= ?)
                            OR (sch.time_start > sch.time_end  AND (? >= sch.time_start OR ? <= sch.time_end))
                           )
                         ORDER BY sch.time_end ASC
                         LIMIT 1";

            $schedStmt = $conn->prepare($schedSql);
            $schedStmt->bind_param('isssss', $lab_id, $todayAbbrev, $nowTime, $nowTime, $nowTime, $nowTime);
            $schedStmt->execute();
            $active = $schedStmt->get_result()->fetch_assoc();
            $schedStmt->close();

            if (!$active) {
                $msg = "No active schedule in this lab right now";
                $scan_status = "warn";
            } else {
                $schedule_id  = (int)$active['schedule_id'];
                $subject_id   = (int)$active['subject_id'];
                $subject_name = $active['subject_name'];

                // 3) Check if student is enrolled in this schedule via admission in CURRENT session
                $ay_id  = (int)($_SESSION['active_ay_id'] ?? 0);
                $sem_id = (int)($_SESSION['active_sem_id'] ?? 0);
                $admSql = "SELECT adm.admission_id, sec.section_name, yl.year_name
                           FROM admissions adm
                           LEFT JOIN sections sec     ON adm.section_id    = sec.section_id
                           LEFT JOIN year_levels yl   ON adm.year_level_id = yl.year_id
                           WHERE adm.student_id = ? AND adm.schedule_id = ? AND adm.academic_year_id = ? AND adm.semester_id = ?
                           LIMIT 1";
                $admStmt = $conn->prepare($admSql);
                $admStmt->bind_param('siii', $student_id, $schedule_id, $ay_id, $sem_id);
                $admStmt->execute();
                $admRes = $admStmt->get_result();
                $admission = $admRes->fetch_assoc();
                $admStmt->close();

                if (!$admission) {
                    $msg = "Student is not enrolled in the active schedule";
                    $scan_status = "warn";
                } else {
                    $admission_id = (int)$admission['admission_id'];


                    $attSql = "SELECT attendance_id, time_in, time_out, status
                               FROM attendance
                               WHERE attendance_date = ? AND schedule_id = ? AND admission_id = ?
                               LIMIT 1";
                    $attStmt = $conn->prepare($attSql);
                    $attStmt->bind_param('sii', $today, $schedule_id, $admission_id);
                    $attStmt->execute();
                    $attRes  = $attStmt->get_result();
                    $attRow  = $attRes->fetch_assoc();
                    $attStmt->close();

                 
                    $timeOnly = date('H:i:s', strtotime($now));

                    if (!$attRow) {
                       
                        $status = 'Present';
                        $startTime = $active['time_start'] ?? null;
                        if ($startTime) {
                            $startDT = DateTime::createFromFormat('H:i:s', $startTime);
                            $scanDT  = DateTime::createFromFormat('H:i:s', $timeOnly);
                            if ($startDT && $scanDT) {
                                $diffSeconds = $scanDT->getTimestamp() - $startDT->getTimestamp();
                                $diffMinutes = $diffSeconds / 60;
                                if ($diffMinutes > 15 && $diffMinutes <= 30) {
                                    $status = 'Late';
                                } elseif ($diffMinutes > 30) {
                                    $status = 'Absent';
                                } else {
                                    // Early or on time up to 15 minutes after start
                                    $status = 'Present';
                                }
                            }
                        }

                        $insSql = "INSERT INTO attendance (attendance_date, schedule_id, time_in, status, admission_id)
                                   VALUES (?, ?, ?, ?, ?)";
                        $insStmt = $conn->prepare($insSql);
                        $insStmt->bind_param('sissi', $today, $schedule_id, $timeOnly, $status, $admission_id);
                        $insStmt->execute();
                        $insStmt->close();

                        $msg = "Time In recorded (Status: " . $status . ") — " . $subject_name;
                        $scan_status = "ok";
                    } else {
                        // Already has a row today
                        if (!empty($attRow['time_out'])) {
                            // Already completed attendance (has time_out)
                            $msg = "Already completed attendance for this schedule today";
                            $scan_status = "ok";
                        } else {
                            // time_out is NULL => decide if Time Out is allowed (10 minutes before end)
                            $endTime = $active['time_end'];
                            $endDT   = DateTime::createFromFormat('H:i:s', $endTime);
                            if ($endDT) {
                                $tenMinBefore = clone $endDT;
                                $tenMinBefore->modify('-10 minutes');
                                $isTimeoutAllowed = ($timeOnly >= $tenMinBefore->format('H:i:s'));

                                if ($isTimeoutAllowed) {
                                    // Update row with Time Out (keep existing status)
                                    $upSql = "UPDATE attendance
                                              SET time_out = ?
                                              WHERE attendance_id = ?";
                                    $upStmt = $conn->prepare($upSql);
                                    $upStmt->bind_param('si', $timeOnly, $attRow['attendance_id']);
                                    $upStmt->execute();
                                    $upStmt->close();

                                    $msg = "Time Out recorded — " . $subject_name;
                                    $scan_status = "ok";
                                } else {
                                    $msg = "Time Out only allowed in the last 10 minutes of class";
                                    $scan_status = "warn";
                                }
                            } else {
                                $msg = "Time Out not available - invalid schedule";
                                $scan_status = "warn";
                            }
                        }
                    }
                }
            }
        } else {
            $msg = "Student not found for this RFID";
            $scan_status = "warn";
        }
    }
}

// Fetch initial feed data (top 10 most recent scans for today in this lab)
$initial_feed = [];
if ($lab_id > 0) {
    $feedSql = "SELECT 
                    a.attendance_id AS id,
                    stu.student_id AS student_id,
                    CONCAT(COALESCE(stu.last_name, ''), ', ', COALESCE(stu.first_name, ''), ' ', COALESCE(stu.middle_name, '')) AS name,
                    a.status,
                    CASE 
                        WHEN a.time_out IS NOT NULL AND a.time_out <> '' THEN CONCAT(a.attendance_date, ' ', a.time_out)
                        ELSE CONCAT(a.attendance_date, ' ', a.time_in)
                    END AS scan_time,
                    sub.subject_name,
                    stu.profile_picture,
                    pa.pc_number
                FROM attendance a
                JOIN admissions adm      ON a.admission_id = adm.admission_id
                JOIN students stu       ON adm.student_id = stu.student_id
                JOIN schedule sch       ON a.schedule_id = sch.schedule_id
                JOIN subjects sub        ON sch.subject_id = sub.subject_id
                LEFT JOIN pc_assignment pa ON pa.student_id = stu.student_id AND pa.lab_id = sch.lab_id
                WHERE sch.lab_id = ?
                  AND a.attendance_date = CURDATE()
                ORDER BY scan_time DESC
                LIMIT 10";
    if ($feedStmt = $conn->prepare($feedSql)) {
        $feedStmt->bind_param('i', $lab_id);
        $feedStmt->execute();
        $feedRes = $feedStmt->get_result();
        while ($frow = $feedRes->fetch_assoc()) {
            $fphoto = '';
            if (!empty($frow['profile_picture'])) {
                $fphoto = '../assets/img/' . basename($frow['profile_picture']);
            }
            $frow['photo'] = $fphoto;
            $initial_feed[] = $frow;
        }
        $feedStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student Dashboard — <?= htmlspecialchars($lab_name) ?></title>
  <style>
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; margin:0; background:#f4f6f9; color:#1a1f26; line-height: 1.5; }
    .wrap { padding: 2rem; max-width: 1400px; margin: 0 auto; }
    .header { display:flex; justify-content:space-between; align-items:flex-end; gap:16px; margin-bottom:2rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 1rem; }
    .title { font-size:2.4rem; font-weight:800; letter-spacing:-0.5px; color: #1e293b; }
    .subtitle { color:#64748b; font-size:1.1rem; font-weight: 500; }
    
    /* Layout - 2 Equal Columns */
    .dashboard-grid { 
      display: grid; 
      grid-template-columns: 1fr 1fr; 
      gap: 2rem; 
      align-items: stretch; 
    }
    
    .card { 
      background:#fff; 
      border-radius:24px; 
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); 
      padding:2.5rem; 
      border: 1px solid rgba(226, 232, 240, 0.8); 
      display: flex; 
      flex-direction: column;
      min-height: 580px; /* Reduced height for a cleaner look */
    }

    /* Active Transaction / Scan Card */
    .active-card {background-color: darkblue;}
    .header-scanner { display: flex; align-items: center; gap: 8px; }
    .scan-input { 
      width: 90px; 
      padding: 5px 12px; 
      font-size: 0.75rem; 
      text-align: center; 
      border-radius: 999px; 
      border: 1px solid rgba(255,255,255,0.15); 
      background: rgba(255,255,255,0.08); 
      color: #fff; 
      transition: all .3s ease; 
      font-family: inherit;
    }
    .scan-input:focus { width: 130px; outline:none; border-color:#3b82f6; background: rgba(255,255,255,0.12); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); }
    .scan-input::placeholder { color: rgba(255,255,255,0.4); }

    .student-body { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; gap: 1rem; position: relative; }
    .student-photo { width:160px; height:160px; border-radius:999px; object-fit:cover; border: 5px solid rgba(255,255,255,0.1); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.4); }
    .student-details h2 { margin: 0; font-size: 2rem; font-weight: 800; color: #fff; }
    .student-meta { color:#cbd5e1; font-size:1.1rem; opacity: 0.8; }
    .subject-tag { margin-top: 1rem; display:inline-flex; align-items: center; padding:8px 16px; border-radius:12px; background:rgba(255, 255, 255, 0.1); color:#fff; font-weight:700; font-size:1.1rem; border: 1px solid rgba(255, 255, 255, 0.2); }

    /* Floating Status Message */
    .status { 
      position: absolute; 
      top: -10px; 
      left: 50%; 
      transform: translateX(-50%); 
      white-space: nowrap;
      font-weight:600; 
      font-size:0.85rem; 
      padding:8px 16px; 
      border-radius:999px; 
      z-index: 10;
      animation: fadeInDown 0.3s ease;
    }
    @keyframes fadeInDown {
      from { opacity: 0; transform: translate(-50%, -20px); }
      to { opacity: 1; transform: translate(-50%, -10px); }
    }
    .status.ok { background:#10b981; color:#fff; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }
    .status.warn { background:#f59e0b; color:#fff; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3); }

    .title-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; }
    .active-card .title-row { justify-content: center; gap: 15px; flex-wrap: wrap; }
    .pill { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:999px; background:#eff6ff; color:#1e40af; font-weight:700; font-size:0.85rem; border:1px solid #dbeafe; }

    @media (max-width: 900px) {
      .dashboard-grid { grid-template-columns: 1fr; }
      .card { min-height: auto; }
    }
    @media (max-width: 640px) {
      .wrap { padding: 1rem; }
      .title { font-size: 1.8rem; }
    }

    /* Feed Styles */
    .feed { display: flex; flex-direction: column; gap: 1rem; overflow-y: auto; max-height: 520px; padding-right: 0.5rem; }
    .feed::-webkit-scrollbar { width: 6px; }
    .feed::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
    .feed::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .feed::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

    .item { 
      display: flex; 
      align-items: center; 
      gap: 1rem; 
      padding: 1rem; 
      background: #f8fafc; 
      border-radius: 16px; 
      border: 1px solid #e2e8f0; 
      transition: all 0.2s ease; 
    }
    .item:hover { transform: translateX(5px); background: #fff; border-color: #3b82f6; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
    
    .item.in { border-left: 4px solid #10b981; }
    .item.out { border-left: 4px solid #ef4444; }
    .item.late { border-left: 4px solid #f59e0b; }

    .item .avatar { width: 48px; height: 48px; border-radius: 12px; object-fit: cover; background: #e2e8f0; border: none; box-shadow: none; }
    .item .meta { font-size: 0.85rem; color: #64748b; margin-top: 2px; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="header">
      <div>
        <div class="title">Lab Dashboard</div>
        <div class="subtitle"><?= htmlspecialchars($lab_name) ?> • Live Attendance Feed</div>
      </div>
      <div style="text-align: right;">
        <div class="subtitle" style="font-size: 0.9rem;">Server Time</div>
        <div id="last-update" style="font-weight: 700; color: #1e293b; font-size: 1.2rem;">—</div>
      </div>
    </div>

    <div class="dashboard-grid">
      <!-- Card 1: Active Transaction (Merged Scanner + Focus) -->
      <div class="card active-card" id="student-card">
        <div class="title-row">
          <div style="display:flex; align-items:center; gap:8px;">
            <div style="width:32px; height:32px; border-radius:8px; background:rgba(59, 130, 246, 0.2); display:flex; align-items:center; justify-content:center; color:#60a5fa; font-size:1rem;">⚡</div>
            <h3 style="margin:0; font-size:1rem; font-weight:800; color: #fff;">Active Transaction</h3>
          </div>
          <div class="header-scanner">
            <form method="post" id="scan-form" autocomplete="off" style="margin:0;">
              <input type="text" name="barcode" id="barcode" class="scan-input" placeholder="Tap ID" autocomplete="off" autofocus />
            </form>
            <div class="pill" style="background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.1); color: #fff;">
              <span style="width:6px;height:6px;border-radius:999px;background:#22c55e;display:inline-block;"></span>
              <?= htmlspecialchars($lab_name) ?>
            </div>
          </div>
        </div>
        
        <div class="student-body">
          <?php if ($msg): ?>
            <div id="status" class="status <?= $scan_status ?>">
              <?= htmlspecialchars($msg) ?>
            </div>
          <?php else: ?>
            <div id="status" class="status" style="display:none;"></div>
          <?php endif; ?>

          <img id="stu-photo" class="student-photo" src="../assets/img/logo.png" alt="photo" />
          <div class="student-details">
            <h2 id="stu-name">—</h2>
            <div class="student-meta" id="stu-id">Student ID: —</div>
            <div class="student-meta" id="stu-year">Year Level: —</div>
            <div class="student-meta" id="stu-sec">Section: —</div>
            <span class="subject-tag" id="stu-subject">Subject: —</span>
          </div>
        </div>
      </div>

      <!-- Card 2: Recent Scans -->
      <div class="card recent-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
          <h3 style="margin:0; font-size:1.3rem; font-weight:800; color: #1e293b;">Recent Scans</h3>
          <span style="background:#f1f5f9; padding:4px 12px; border-radius:8px; font-size:0.8rem; font-weight:700; color:#64748b;">LIST 10</span>
        </div>
        <div id="feed" class="feed">
          <?php if (empty($initial_feed)): ?>
            <div style="text-align:center; padding: 2rem; color: #94a3b8;">
              <div style="font-size: 2rem; margin-bottom: 0.5rem;">🔌</div>
              No scans recorded today
            </div>
          <?php else: ?>
            <?php foreach ($initial_feed as $r): 
              $statusStr = strtolower($r['status'] ?? '');
              $isOut = (strpos($statusStr, 'out') !== false);
              $isIn = ($statusStr === 'present');
              $isLate = ($statusStr === 'late');
              
              $cls = 'item';
              if ($isOut) $cls .= ' out';
              elseif ($isLate) $cls .= ' late';
              elseif ($isIn) $cls .= ' in';

              $pcInfo = !empty($r['pc_number']) ? ' • PC: ' . htmlspecialchars($r['pc_number']) : '';
              $scanTime = date('h:i:s A', strtotime($r['scan_time']));
            ?>
              <div class="<?= $cls ?>">
                <img class="avatar" src="<?= !empty($r['photo']) ? htmlspecialchars($r['photo']) : '../assets/img/logo.png' ?>" alt="photo" onerror="this.src='../assets/img/logo.png'">
                <div style="flex:1; min-width:0;">
                  <div style="font-weight:700; color:#1e293b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($r['name'] ?? '-') ?></div>
                  <div class="meta" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($r['subject_name'] ?? '-') ?></div>
                  <div class="meta" style="color: #475569; font-weight: 700; margin-top: 4px;">
                    <?= htmlspecialchars($r['status'] ?? '-') ?> <span style="font-weight:400; color:#94a3b8;">•</span> <?= $scanTime ?><?= $pcInfo ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <script>
  const LAB = <?= json_encode($lab_name) ?>;
  const LAB_ID = <?= json_encode((int)$lab_id) ?>;
  const ACTIVE_SCHEDULE_ID = <?= json_encode((int)$active_schedule_id) ?>;
  const KIOSK_TOKEN = <?= json_encode(defined('KIOSK_TOKEN') ? KIOSK_TOKEN : '') ?>;
  const FEED_URL = '../ajax/student_attendance_feed.php'
    + '?lab_id=' + encodeURIComponent(String(LAB_ID))
    + (ACTIVE_SCHEDULE_ID ? ('&schedule_id=' + encodeURIComponent(String(ACTIVE_SCHEDULE_ID))) : '')
    + '&kiosk=' + encodeURIComponent(KIOSK_TOKEN)
    + '&lab=' + encodeURIComponent(LAB);
  const POLL_MS = 3000;
  let lastHash = null;

  const barcode = document.getElementById('barcode');
  const feed = document.getElementById('feed');
  const lastUpd = document.getElementById('last-update');
  // student card refs
  const stuPhoto = document.getElementById('stu-photo');
  const stuName = document.getElementById('stu-name');
  const stuId = document.getElementById('stu-id');
  const stuYear = document.getElementById('stu-year');
  const stuSec = document.getElementById('stu-sec');
  const stuSubject = document.getElementById('stu-subject');

  function fmtTime(sql){
    const d = new Date(sql.replace(' ', 'T'));
    if (isNaN(d)) return sql;
    return d.toLocaleTimeString(undefined, {hour:'numeric', minute:'2-digit', second:'2-digit', hour12:true});
  }
  function renderFeed(rows){
    const top10 = Array.isArray(rows) ? rows.slice(0,10) : [];
    if (top10.length === 0) {
      feed.innerHTML = `<div style="text-align:center; padding: 2rem; color: #94a3b8;">No scans recorded today</div>`;
      return;
    }
    feed.innerHTML = top10.map(r => {
      const statusStr = String(r.status||'').toLowerCase();
      const isOut = statusStr.includes('out');
      const isIn = statusStr === 'present';
      const isLate = statusStr === 'late';
      
      let cls = 'item';
      if (isOut) cls += ' out';
      else if (isLate) cls += ' late';
      else if (isIn) cls += ' in';

      const pcInfo = r.pc_number ? ' • PC: ' + r.pc_number : '';
      
      return `
      <div class="${cls}">
        <img class="avatar" src="${r.photo || '../assets/img/logo.png'}" alt="photo" onerror="this.src='../assets/img/logo.png'">
        <div style="flex:1; min-width:0;">
          <div style="font-weight:700; color:#1e293b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${(r.name||'-')}</div>
          <div class="meta" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${r.subject_name||'-'}</div>
          <div class="meta" style="color: #475569; font-weight: 700; margin-top: 4px;">
            ${r.status} <span style="font-weight:400; color:#94a3b8;">•</span> ${fmtTime(r.scan_time)}${pcInfo}
          </div>
        </div>
      </div>
    `;
    }).join('');
  }
  function renderStudent(stu, subject){
    try {
      if (!stu) return;
      if (stuPhoto) stuPhoto.src = (stu.profile_pic && stu.profile_pic.length) ? stu.profile_pic : '../assets/img/logo.png';
      if (stuName) stuName.textContent = (stu.name||'-');
      if (stuId) stuId.textContent = 'Student ID: ' + (stu.student_id||'-');
      if (stuYear) stuYear.textContent = 'Year Level: ' + (stu.year_level||'-');
      if (stuSec) stuSec.textContent = 'Section: ' + (stu.section||'-');
      if (stuSubject) stuSubject.textContent = 'Subject: ' + (subject || '—');
    } catch (e) { console.error(e); }
  }
  async function loadFeed(){
    try{
      const res = await fetch(FEED_URL, {cache:'no-store'});
      const text = await res.text();
      let data; try{ data = JSON.parse(text); }catch(e){ return; }
      if (!Array.isArray(data)) return;
      const h = JSON.stringify(data);
      if (h !== lastHash) {
        renderFeed(data);
        lastHash = h;
      }
      lastUpd.textContent = new Date().toLocaleTimeString();
    }catch(e){ /* ignore */ }
  }

  // Simple form submission logic (copied from teacher_../scan.php)
  barcode.focus();
  barcode.addEventListener('input', function() {
    if (barcode.value.length > 0) {
      document.getElementById('scan-form').submit();
    }
  });
  
  window.onload = function() {
    barcode.focus();
  };

  // Update student card if we have student data from PHP (src_db students schema)
  <?php if ($student): ?>
  renderStudent({
    profile_pic: <?= json_encode(!empty($student['profile_picture']) ? ('../assets/img/' . basename($student['profile_picture'])) : '') ?>,
    name: <?= json_encode(trim(($student['last_name'] ?? '') . ', ' . ($student['first_name'] ?? '') . ' ' . ($student['middle_name'] ?? ''))) ?>,
    student_id: <?= json_encode($student['student_id'] ?? '') ?>,
    section: <?= json_encode($admission['section_name'] ?? $student_latest_info['section_name'] ?? '-') ?>,
    year_level: <?= json_encode($admission['year_name'] ?? $student_latest_info['year_name'] ?? '-') ?>
  }, <?= json_encode($subject_name ?? '') ?>);
  <?php endif; ?>

  loadFeed();
  setInterval(loadFeed, POLL_MS);
  </script>
</body>
</html>



