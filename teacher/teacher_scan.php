<?php
session_start();
include '../includes/db.php';

// Only allow teachers - MUST be before any output (including include '../includes/header.php')
if (!isset($_SESSION['teacher_id'])) {
    header("Location: teacher_login.php");
    exit();
}

date_default_timezone_set('Asia/Manila');
// Tell header.php that this page will provide its own (teacher) sidebar markup
$use_custom_sidebar = true;
include '../includes/header.php';
include '../includes/db.php';

$teacher_id = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'];
$teacher_id_int = (int)$teacher_id; // employees.employee_id in src_db

// Fetch teacher's schedules/subjects for dropdown using src_db schema
// We treat schedule.schedule_id as the "subject" choice for scanning
$subjects = [];
$auto_subject_id = null;   // actually schedule_id
$auto_subject_name = null; // subject_name

$subject_sql = "
    SELECT 
        sc.schedule_id,
        subj.subject_name,
        sc.time_start,
        sc.time_end
    FROM schedule sc
    JOIN subjects subj ON sc.subject_id = subj.subject_id
    WHERE sc.employee_id = ?
    ORDER BY subj.subject_name
";
$subject_stmt = $conn->prepare($subject_sql);
if ($subject_stmt) {
    $subject_stmt->bind_param("i", $teacher_id_int);
    $subject_stmt->execute();
    $subject_result = $subject_stmt->get_result();
    while ($row = $subject_result->fetch_assoc()) {
        $subjects[] = [
            'id'           => (int)$row['schedule_id'],
            'subject_name' => $row['subject_name'],
            'start_time'   => $row['time_start'] ?? null,
            'end_time'     => $row['time_end'] ?? null,
        ];
    }
    $subject_stmt->close();
}

// Determine current schedule/subject by time range (auto-select)
$nowTime = date('H:i:s');
foreach ($subjects as $subj) {
    $start = $subj['start_time'] ?? null;
    $end   = $subj['end_time'] ?? null;
    if (!$start || !$end) {
        continue;
    }
    // Handle ranges that cross midnight
    if ($end < $start) {
        if ($nowTime >= $start || $nowTime <= $end) {
            $auto_subject_id = (int)$subj['id'];
            $auto_subject_name = $subj['subject_name'];
            break;
        }
    } else {
        if ($nowTime >= $start && $nowTime <= $end) {
            $auto_subject_id = (int)$subj['id'];
            $auto_subject_name = $subj['subject_name'];
            break;
        }
    }
}

$student = null;
$msg = "";
$force_subject_id = null;
$force_subject_name = null;

// Helper: check if student has a 'Present' record for a schedule on the given date
// In the new src_db schema, attendance is linked via admission_id; admission links student_id + schedule_id.
function has_present_today($conn, $student_id, $schedule_id, $date) {
    $sql = "SELECT COUNT(*) AS c
            FROM attendance a
            JOIN admissions adm ON a.admission_id = adm.admission_id
            WHERE adm.student_id = ?
              AND adm.schedule_id = ?
              AND a.attendance_date = ?
              AND a.status = 'Present'";
    $chk = $conn->prepare($sql);
    $sid_int = (int)$schedule_id;
    $chk->bind_param('sis', $student_id, $sid_int, $date);
    $chk->execute();
    $res = $chk->get_result();
    $row = $res->fetch_assoc();
    $chk->close();
    return intval($row['c'] ?? 0) > 0;
}

// Helper: check if Time Out is allowed based on schedule (src_db: schedule table)
// NOTE: here $subject_id is actually schedule_id from the dropdown
function is_timeout_allowed($conn, $schedule_id) {
    // Get schedule info
    $stmt = $conn->prepare("SELECT time_start, time_end FROM schedule WHERE schedule_id = ?");
    $stmt->bind_param('i', $schedule_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $schedule = $result->fetch_assoc();
    $stmt->close();

    if (!$schedule || !$schedule['time_start'] || !$schedule['time_end']) {
        return false;
    }

    $nowTime   = date('H:i:s');
    $startTime = $schedule['time_start'];
    $endTime   = $schedule['time_end'];

    // If class hasn't started yet, don't allow timeout
    if ($nowTime < $startTime) {
        return false;
    }

    $endDateTime = DateTime::createFromFormat('H:i:s', $endTime);
    if (!$endDateTime) {
        return false;
    }

    $tenMinutesBefore = clone $endDateTime;
    $tenMinutesBefore->modify('-10 minutes');
    $tenMinutesBeforeTime = $tenMinutesBefore->format('H:i:s');

    // Allow timeout only if current time is 10 minutes before end time or after end time
    return ($nowTime >= $tenMinutesBeforeTime);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barcode = trim($_POST['barcode']);
    $subject_id = intval($_POST['subject_id'] ?? 0);
    if (!$subject_id && $auto_subject_id) {
        $subject_id = $auto_subject_id;
    }
    if (!$subject_id || !$barcode) {
        $msg = "<span class='text-red-600 font-bold'>❌ Please select a subject and scan a barcode.</span>";
    } else {
        // Get student info using current students schema (rfid_number or student_id)
        // We alias fields to match legacy keys used later in this file.
        $stmt = $conn->prepare("SELECT 
                student_id,
                CONCAT(COALESCE(last_name,''), ', ', COALESCE(first_name,''), ' ', COALESCE(middle_name,'')) AS name,
                NULL AS section,
                NULL AS course,
                NULL AS pc_number,
                NULL AS profile_pic
            FROM students
            WHERE rfid_number = ? OR student_id = ?");
        $stmt->bind_param("ss", $barcode, $barcode);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
        $stmt->close();

        if ($student) {
            $student_id = $student['student_id'];
            $now = date('Y-m-d H:i:s');
            $today = date('Y-m-d');

            // Get current subject name based on schedule_id using src_db schema
            // Here $subject_id is actually schedule.schedule_id
            $sub_stmt = $conn->prepare("SELECT subj.subject_name
                                        FROM schedule sc
                                        JOIN subjects subj ON sc.subject_id = subj.subject_id
                                        WHERE sc.schedule_id = ?");
            $sub_stmt->bind_param("i", $subject_id);
            $sub_stmt->execute();
            $sub_stmt->bind_result($current_subject_name);
            $sub_stmt->fetch();
            $sub_stmt->close();

            // Semester handling: use a default semester_id (1) for inserts in this flow
            $current_semester_id = 1;

            // Use this variable throughout to avoid confusion
            $subject_name = $current_subject_name;

            // Check if student is registered for this schedule/subject in src_db admission table
            // Here $subject_id is actually schedule.schedule_id
            $regStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM admissions WHERE student_id = ? AND schedule_id = ?");
            $regStmt->bind_param("si", $student_id, $subject_id);
            $regStmt->execute();
            $regStmt->bind_result($regCount);
            $regStmt->fetch();
            $regStmt->close();

            if (empty($regCount)) {
                $msg = "<span class='text-red-600 font-bold'>❌ Student is not registered in the selected subject.</span>";
            } else {
                // NEW LOGIC (src_db.attendance): mirror attendance.php style
                // 1) Get admission_id for this student + schedule
                $admIdStmt = $conn->prepare("SELECT admission_id FROM admissions WHERE student_id = ? AND schedule_id = ? LIMIT 1");
                $admIdStmt->bind_param('si', $student_id, $subject_id);
                $admIdStmt->execute();
                $admRes = $admIdStmt->get_result();
                $admRow = $admRes->fetch_assoc();
                $admIdStmt->close();

                if (!$admRow) {
                    $msg = "<span class='text-red-600 font-bold'>❌ No admission record found for this student and schedule.</span>";
                } else {
                    $admission_id = (int)$admRow['admission_id'];
                    $nowTime       = date('H:i:s');

                    // 2) Check existing attendance row for today
                    $check = $conn->prepare("SELECT attendance_id, time_in, time_out FROM attendance WHERE admission_id = ? AND schedule_id = ? AND attendance_date = ? LIMIT 1");
                    $check->bind_param('iis', $admission_id, $subject_id, $today);
                    $check->execute();
                    $attRow = $check->get_result()->fetch_assoc();

                    // Small dedupe: ignore scan if within 3 seconds of last time_in/out
                    $dedupeSeconds = 3;
                    $doWrite = true;
                    if ($attRow) {
                        $lastTime = $attRow['time_out'] ?: $attRow['time_in'];
                        if ($lastTime) {
                            $lastTs = strtotime($today . ' ' . $lastTime);
                            if (time() - $lastTs <= $dedupeSeconds) {
                                $doWrite = false;
                            }
                        }
                    }

                    if (!$doWrite) {
                        $msg = "<span class='text-gray-600 font-medium'>ℹ️ Scan ignored (too fast, possible duplicate).</span>";
                    } else {
                        if (!$attRow) {
                            // FIRST SCAN TODAY FOR THIS CLASS → time_in + status (Present/Late/Absent)
                            // We need schedule start time for delay-based status
                            $schedStmt = $conn->prepare("SELECT time_start, time_end FROM schedule WHERE schedule_id = ?");
                            $schedStmt->bind_param('i', $subject_id);
                            $schedStmt->execute();
                            $schedRow = $schedStmt->get_result()->fetch_assoc();
                            $schedStmt->close();

                            $timeStart = $schedRow['time_start'] ?? $nowTime;
                            $status = 'Present';
                            if (!empty($timeStart)) {
                                $startDT = DateTime::createFromFormat('H:i:s', $timeStart);
                                $scanDT  = DateTime::createFromFormat('H:i:s', $nowTime);
                                if ($startDT && $scanDT) {
                                    $diffSeconds = $scanDT->getTimestamp() - $startDT->getTimestamp();
                                    $diffMinutes = $diffSeconds / 60;
                                    if ($diffMinutes > 15 && $diffMinutes <= 30) {
                                        $status = 'Late';
                                    } elseif ($diffMinutes > 30) {
                                        $status = 'Absent';
                                    } else {
                                        $status = 'Present'; // early or within 15 minutes after start
                                    }
                                }
                            }

                            $ins = $conn->prepare("INSERT INTO attendance (attendance_date, schedule_id, time_in, status, admission_id) VALUES (?, ?, ?, ?, ?)");
                            $ins->bind_param('sissi', $today, $subject_id, $nowTime, $status, $admission_id);
                            $ins->execute();
                            $ins->close();

                            $msg = "✅ Time In for <b>" . htmlspecialchars($current_subject_name) . "</b> — " . htmlspecialchars($student['name']) . " (Status: {$status}).";
                        } else {
                            // SECOND SCAN → set time_out (if not set yet)
                            if (empty($attRow['time_out'])) {
                                // Optionally enforce time-out window using is_timeout_allowed
                                if (!is_timeout_allowed($conn, $subject_id)) {
                                    $msg = "⏰ You have already Time In for <b>" . htmlspecialchars($current_subject_name) . "</b>. Time Out is only allowed in the last 10 minutes of class or after class ends.";
                                } else {
                                    $upd = $conn->prepare("UPDATE attendance SET time_out = ? WHERE attendance_id = ?");
                                    $attendance_id = (int)$attRow['attendance_id'];
                                    $upd->bind_param('si', $nowTime, $attendance_id);
                                    $upd->execute();
                                    $upd->close();

                                    $msg = "📤 Time Out from <b>" . htmlspecialchars($current_subject_name) . "</b> — " . htmlspecialchars($student['name']) . ". ✓ Completed!";
                                }
                            } else {
                                $msg = "ℹ️ You have already completed attendance for <b>" . htmlspecialchars($current_subject_name) . "</b> today (Time In + Time Out).";
                            }
                        }
                    }
                }
            }
        } else {
            $msg = "<span class='text-red-600 font-bold'>❌ Student not found!</span>";
        }
    }
}
?>

<!-- Main Content -->
<main class="max-w-7xl mx-auto px-6 pt-8 pb-8 w-full">
            <div class="grid grid-cols-1 gap-10">
                <!-- Left Side: Attendance Scanner -->
                <div class="flex flex-col">
                    <div class="text-center mb-6">
                        <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-r from-sky-500 to-blue-600 rounded-full shadow-lg mb-6">
                            <i class="fas fa-qrcode text-blue text-3xl"></i>
                        </div>
                        <h1 class="text-3xl font-bold text-sky-800">Attendance Scanner</h1>
                        <p class="text-sky-600 text-md">Scan student barcodes to record attendance</p>

                    </div>
                    <?php if (!empty($msg)): ?>
                    <div class="mb-6">
                        <div class="bg-white rounded-xl p-4 text-center shadow-lg border border-sky-400">
                            <p class="text-lg font-semibold text-sky-800"><?= $msg ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    <form method="post" id="scan-form" autocomplete="off" class="space-y-8 bg-white rounded-2xl shadow-xl p-8 border-2 border-sky-400">
                        <div>
                            <?php if (!empty($auto_subject_id)): ?>
                                <label class="block text-sky-700 font-bold mb-3 text-xl">Current Subject</label>
                                <div class="w-full p-5 border-2 border-sky-300 rounded-lg bg-sky-50 text-sky-900 font-semibold">
                                    <?= htmlspecialchars($auto_subject_name) ?>
                                </div>
                                <input type="hidden" name="subject_id" value="<?= (int)$auto_subject_id ?>">
                            <?php else: ?>
                                <label class="block text-sky-700 font-bold mb-3 text-xl">Select Subject</label>
                                <select name="subject_id" id="subject_id" class="w-full p-5 border-2 border-sky-300 rounded-lg shadow-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-200 transition text-xl">
                                    <option value="">Choose a subject...</option>
                                    <?php foreach ($subjects as $sub): ?>
                                        <?php
                                            $isSelected = false;
                                            if (isset($_POST['subject_id']) && $_POST['subject_id'] == $sub['id']) {
                                                $isSelected = true;
                                            }
                                        ?>
                                        <option value="<?= $sub['id'] ?>" <?= $isSelected ? 'selected' : '' ?>><?= htmlspecialchars($sub['subject_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-sky-700 font-bold mb-3 text-xl">Student Barcode</label>
                            <div class="relative">
                                <input type="text" name="barcode" id="barcode" class="w-full p-6 border-2 border-sky-300 rounded-lg shadow-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-200 transition text-2xl text-center font-bold placeholder-sky-400" placeholder="Scan barcode here" autofocus autocomplete="off">
                                <div class="absolute right-4 top-1/2 transform -translate-y-1/2">
                                    <i class="fas fa-barcode text-sky-400 text-xl"></i>
                                </div>
                            </div>
                        </div>
                    </form>
                    <?php if ($student): ?>
                    <div class="mt-6">
                        <div class="bg-white rounded-xl border border-sky-200 shadow-lg overflow-hidden">
                            <div class="w-full bg-gradient-to-r from-sky-500 to-blue-600 py-3 text-center">
                                <h3 class="font-bold text-lg text-white flex items-center justify-center gap-2">
                                    <i class="fas fa-id-card"></i> Student Information
                                </h3>
                            </div>
                            <div class="p-6">
                                <div class="flex flex-col items-center mb-4">
                                    <?php if ($student['profile_pic']): ?>
                                        <img src="../assets/img/<?php echo htmlspecialchars($student['profile_pic']); ?>" alt="Profile" class="w-24 h-24 object-cover rounded-full border-4 border-sky-200 shadow mb-3">
                                    <?php else: ?>
                                        <div class="w-24 h-24 bg-gradient-to-br from-sky-100 to-blue-100 rounded-full flex items-center justify-center border-4 border-sky-200 shadow mb-3">
                                            <i class="fas fa-user-graduate text-3xl text-sky-500"></i>
                                        </div>
                                    <?php endif; ?>
                                    <h4 class="text-xl font-bold text-sky-800 mb-1"><?php echo htmlspecialchars($student['name']); ?></h4>
                                    <span class="text-sky-600 text-sm">Student</span>
                                </div>
                                <div class="grid grid-cols-2 gap-3 text-sm">
                                    <div class="text-center p-3 bg-sky-50 rounded-lg">
                                        <span class="block text-sky-600 font-medium text-xs mb-1">Student ID</span>
                                        <span class="block font-bold text-sky-800"><?php echo htmlspecialchars($student['student_id']); ?></span>
                                    </div>
                                    <div class="text-center p-3 bg-sky-50 rounded-lg">
                                        <span class="block text-sky-600 font-medium text-xs mb-1">Section</span>
                                        <span class="block font-bold text-sky-800"><?php echo htmlspecialchars($student['section']); ?></span>
                                    </div>
                                    <div class="text-center p-3 bg-sky-50 rounded-lg">
                                        <span class="block text-sky-600 font-medium text-xs mb-1">PC Number</span>
                                        <span class="block font-bold text-sky-800"><?php echo htmlspecialchars($student['pc_number']); ?></span>
                                    </div>
                                    <div class="text-center p-3 bg-sky-50 rounded-lg">
                                        <span class="block text-sky-600 font-medium text-xs mb-1">Course</span>
                                        <span class="block font-bold text-sky-800"><?php echo htmlspecialchars($student['course']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
    const barcodeInput = document.getElementById('barcode');
    barcodeInput.focus();
    barcodeInput.addEventListener('input', function() {
        if (barcodeInput.value.length > 0) {
            document.getElementById('scan-form').submit();
        }
    });
    window.onload = function() {
        barcodeInput.focus();
    };
    // If server suggests a subject to force (Time Out candidate), apply it so next scan will sign out.
    <?php if (!empty($force_subject_id)): ?>
    (function(){
        try {
            // If the page shows a selectable <select> (no auto subject), set its value.
            var sel = document.getElementById('subject_id');
            if (sel) {
                sel.value = <?= (int)$force_subject_id ?>;
            }
            // If page uses a hidden input for auto-selected subject, replace it with the forced id
            var hidden = document.querySelector('input[type=hidden][name="subject_id"]');
            if (hidden) {
                hidden.value = <?= (int)$force_subject_id ?>;
            }
            // If there's a visible Current Subject display, update its text (best-effort)
            var cs = document.querySelector('div[aria-label="current-subject"]');
            if (cs) cs.textContent = <?= json_encode($force_subject_name) ?>;
            // Keep focus on barcode input so teacher can immediately rescan to sign out
            barcodeInput.focus();
        } catch(e) { console.error('apply forced subject error', e); }
    })();
    <?php endif; ?>
    </script>

    <?php include '../includes/footer.php'; ?>


