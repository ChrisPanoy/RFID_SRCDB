<?php
include '../includes/db.php';

// Ensure session is started before checking auth so header redirects work
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../includes/header.php';

$student = null;
$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Accept RFID number only
    $input = trim($_POST['rfid_number'] ?? '');
    $stmt = $conn->prepare("SELECT 
            student_id,
            CONCAT(COALESCE(last_name,''), ', ', COALESCE(first_name,''), ' ', COALESCE(middle_name,'')) AS name,
            profile_picture AS profile_pic,
            rfid_number,
            NULL AS section,
            NULL AS pc_number,
            NULL AS course
        FROM students 
        WHERE rfid_number = ?");
    $stmt->bind_param("s", $input);

    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();

    if ($student) {
        $student_id = $student['student_id'];
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        // Resolve the current schedule and admission for this student in CURRENT active session
        $ay_id  = (int)($_SESSION['active_ay_id'] ?? 0);
        $sem_id = (int)($_SESSION['active_sem_id'] ?? 0);

        $schedStmt = $conn->prepare("SELECT sc.schedule_id, sc.time_start, sc.time_end, subj.subject_name
                                     FROM admissions adm
                                     JOIN schedule sc ON adm.schedule_id = sc.schedule_id
                                     JOIN subjects subj ON sc.subject_id = subj.subject_id
                                     WHERE adm.student_id = ? 
                                       AND adm.academic_year_id = ?
                                       AND adm.semester_id = ?
                                     ORDER BY sc.time_start
                                     LIMIT 1");
        $schedStmt->bind_param('sii', $student_id, $ay_id, $sem_id);
        $schedStmt->execute();
        $schedRow = $schedStmt->get_result()->fetch_assoc();
        $schedStmt->close();

        if (!$schedRow) {
            $msg = "<span style='color:red;'>❌ No schedule found for this student.</span>";
        } else {
            $schedule_id = (int)$schedRow['schedule_id'];
            $subject_name = $schedRow['subject_name'] ?? '';
            $nowTime = date('H:i:s');

            // admission_id for this student + schedule in current session
            $admIdStmt = $conn->prepare("SELECT admission_id FROM admissions WHERE student_id = ? AND schedule_id = ? AND academic_year_id = ? AND semester_id = ? LIMIT 1");
            $admIdStmt->bind_param('siii', $student_id, $schedule_id, $ay_id, $sem_id);
            $admIdStmt->execute();
            $admRes = $admIdStmt->get_result();
            $admRow = $admRes->fetch_assoc();
            $admIdStmt->close();

            if (!$admRow) {
                $msg = "<span style='color:red;'>❌ No admission record found for this student and schedule.</span>";
            } else {
                $admission_id = (int)$admRow['admission_id'];

                // Check existing attendance row for today
                $check = $conn->prepare("SELECT attendance_id, time_in, time_out FROM attendance WHERE admission_id = ? AND schedule_id = ? AND attendance_date = ? LIMIT 1");
                $check->bind_param('iis', $admission_id, $schedule_id, $today);
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
                    $msg = "<span class='text-gray-600'>ℹ️ Scan ignored (too fast, possible duplicate).</span>";
                } else {
                    if (!$attRow) {
                        // FIRST SCAN TODAY → time_in + status (Present/Late/Absent) based on schedule start
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
                                    $status = 'Present';
                                }
                            }
                        }

                        $ins = $conn->prepare("INSERT INTO attendance (attendance_date, schedule_id, time_in, status, admission_id) VALUES (?, ?, ?, ?, ?)");
                        $ins->bind_param('sissi', $today, $schedule_id, $nowTime, $status, $admission_id);
                        $ins->execute();
                        $ins->close();

                        $msg = "✅ Time In — " . htmlspecialchars($student['name']) . " for <b>" . htmlspecialchars($subject_name) . "</b> (Status: {$status}).";
                    } else {
                        // SECOND SCAN → set time_out (if not set yet)
                        if (empty($attRow['time_out'])) {
                            // Optionally enforce time-out window similar to teacher_scan's is_timeout_allowed
                            $upd = $conn->prepare("UPDATE attendance SET time_out = ? WHERE attendance_id = ?");
                            $attendance_id = (int)$attRow['attendance_id'];
                            $upd->bind_param('si', $nowTime, $attendance_id);
                            $upd->execute();
                            $upd->close();

                            $msg = "📤 Time Out — " . htmlspecialchars($student['name']) . " from <b>" . htmlspecialchars($subject_name) . "</b>. ✓ Completed!";
                        } else {
                            $msg = "ℹ️ Attendance already completed today (Time In + Time Out).";
                        }
                    }
                }
            }
        }
    } else {
        $msg = "<span style='color:red;'>❌ Student not found!</span>";
    }
}

?>

<!-- Tailwind Container -->
<style>
    .app-content { margin-left: 20rem; padding: 3.5rem 2rem; min-height:100vh; box-sizing:border-box; background: linear-gradient(90deg,#f0f7ff 0%, #fff0fb 100%); }
    .app-container { max-width: 3100px; margin: 0 auto; }
    @media (max-width:1900px){ .app-content{margin-left:0;padding:2rem;} }
</style>

<div class="app-content">
    <div class="app-container">
        <?php if (!empty($msg)) echo "<p class='text-center mb-8 text-xl font-semibold'>$msg</p>"; ?>
        <form method="post" id="scan-form" autocomplete="off" class="mb-10 flex flex-col items-center gap-6">
            <input type="text" name="rfid_number" id="rfid_number"
                class="w-96 p-5 border-2 border-indigo-200 rounded-xl shadow focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 transition text-2xl placeholder-gray-700 text-gray-800 font-semibold text-center" placeholder="Scan RFID or enter Student ID" autofocus autocomplete="off">
        </form>
        
        <?php if ($student): ?>
        <div class="p-6">
            <div class="bg-white rounded-2xl shadow-lg border border-sky-100 p-6 w-full max-w-sm mx-auto">
                <!-- Profile -->
                <div class="flex flex-col items-center mb-6">
                    <?php if ($student['profile_pic']): ?>
                        <img src="../assets/img/<?php echo htmlspecialchars($student['profile_pic']); ?>" 
                             alt="Profile" 
                             class="w-28 h-28 object-cover rounded-full border-4 border-sky-200 shadow-md mb-3">
                    <?php else: ?>
                        <div class="w-28 h-28 bg-gradient-to-br from-sky-100 to-blue-100 rounded-full flex items-center justify-center border-4 border-sky-200 shadow-md mb-3">
                            <i class="fas fa-user-graduate text-4xl text-sky-500"></i>
                        </div>
                    <?php endif; ?>
                    
                    <h4 class="text-2xl font-bold text-sky-800 mb-1">
                        <?php echo htmlspecialchars($student['name']); ?>
                    </h4>
                    <span class="text-sky-600 text-sm font-medium">Student</span>
                </div>

                <!-- Student Info -->
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div class="text-center p-4 bg-sky-50 rounded-xl shadow-sm hover:shadow-md transition">
                        <span class="block text-sky-600 font-medium text-xs mb-1">Student ID</span>
                        <span class="block font-bold text-sky-800">
                            <?php echo htmlspecialchars($student['student_id']); ?>
                        </span>
                    </div>
                    <div class="text-center p-4 bg-sky-50 rounded-xl shadow-sm hover:shadow-md transition">
                        <span class="block text-sky-600 font-medium text-xs mb-1">Section</span>
                        <span class="block font-bold text-sky-800">
                            <?php echo htmlspecialchars($student['section']); ?>
                        </span>
                    </div>
                    <div class="text-center p-4 bg-sky-50 rounded-xl shadow-sm hover:shadow-md transition">
                        <span class="block text-sky-600 font-medium text-xs mb-1">PC Number</span>
                        <span class="block font-bold text-sky-800">
                            <?php echo htmlspecialchars($student['pc_number']); ?>
                        </span>
                    </div>
                    <div class="text-center p-4 bg-sky-50 rounded-xl shadow-sm hover:shadow-md transition">
                        <span class="block text-sky-600 font-medium text-xs mb-1">Course</span>
                        <span class="block font-bold text-sky-800">
                            <?php echo htmlspecialchars($student['course']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="max-w-6xl mx-auto mt-12 bg-white shadow-2xl rounded-3xl p-12">
        <h2 class="text-2xl font-bold mb-8 text-center text-indigo-700">List of All Attendance Records</h2>
        <div class="text-center mb-4">
            <a href="../includes/all_attendance.php" target="_blank" class="inline-block bg-indigo-100 text-indigo-700 px-4 py-2 rounded-lg font-semibold hover:bg-indigo-200 transition">See All</a>
        </div>
        <div class="overflow-x-auto" id="all-attendance-table"></div>
    </div>
</div>

<!-- JS Script -->
<script>
const rfidInput = document.getElementById('rfid_number');
rfidInput.focus();
rfidInput.addEventListener('input', function() {
    if (rfidInput.value.length > 0) {
        document.getElementById('scan-form').submit();
    }
});
window.onload = function() {
    rfidInput.focus();
};

// Auto-refresh all attendance table every 3 seconds (increased from 1s to be gentler on DB)
// and include current URL parameters for filtering
setInterval(function() {
    const params = window.location.search;
    fetch('../includes/all_attendance.php' + params)
        .then(res => res.text())
        .then(html => {
            const tableDiv = document.getElementById('all-attendance-table');
            // Only update if content changed or if we are typing/operating (prevent losing focus if there was an input, though here it's just a table)
            tableDiv.innerHTML = html;
        });
}, 3000);
</script>
 
<?php include '../includes/footer.php'; ?>



