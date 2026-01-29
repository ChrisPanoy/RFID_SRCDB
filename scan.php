<?php
date_default_timezone_set('Asia/Manila');
include 'includes/header.php';
include 'includes/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$student = null;
$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barcode = $_POST['barcode'];
    $student = $conn->query("SELECT * FROM students WHERE barcode='$barcode'")->fetch_assoc();

    if ($student) {
        $student_id = $student['student_id'];
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        $current_time = date('H:i:s');
        $current_day = date('D'); // e.g., Mon, Tue, etc.

        // 1. Mark Present for subjects scheduled now
        $subject_sql = "SELECT sub.id, sub.subject_name, sub.end_time FROM subjects sub
            JOIN student_subjects ss ON ss.subject_id = sub.id
            WHERE ss.student_id = ?
            AND FIND_IN_SET(?, sub.schedule_days) > 0
            AND sub.start_time <= ? AND sub.end_time >= ?";
        $subject_stmt = $conn->prepare($subject_sql);
        $subject_stmt->bind_param('ssss', $student_id, $current_day, $current_time, $current_time);
        $subject_stmt->execute();
        $subjects_now = $subject_stmt->get_result();

        $marked = 0;
        $already = 0;
        $subjects_marked = [];
        $dedupe_seconds = 5; // don't record duplicate scans within this many seconds
        while ($subj = $subjects_now->fetch_assoc()) {
            // Check if already marked present for this subject today
            $check_sql = "SELECT id FROM attendance WHERE student_id=? AND subject_id=? AND DATE(scan_time)=? AND status='Present'";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param('sis', $student_id, $subj['id'], $today);
            $check_stmt->execute();
            $check_stmt->store_result();
            if ($check_stmt->num_rows == 0) {
                // Dedupe guard: avoid inserting if last scan for this student+subject was within N seconds
                $dedupe_check = $conn->prepare("SELECT scan_time FROM attendance WHERE student_id=? AND subject_id=? ORDER BY scan_time DESC LIMIT 1");
                $dedupe_check->bind_param('si', $student_id, $subj['id']);
                $dedupe_check->execute();
                $dedupe_res = $dedupe_check->get_result();
                $shouldInsert = true;
                if ($dr = $dedupe_res->fetch_assoc()) {
                    $lastScanTime = strtotime($dr['scan_time']);
                    if (time() - $lastScanTime <= $dedupe_seconds) {
                        $shouldInsert = false;
                    }
                }
                $dedupe_check->close();

                if ($shouldInsert) {
                    // Insert Present record for this subject
                    $insert_sql = "INSERT INTO attendance (student_id, subject_id, semester_id, scan_time, status) VALUES (?, ?, ?, ?, 'Present')";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bind_param('siis', $student_id, $subj['id'], 1, $now);
                    $insert_stmt->execute();
                    $insert_stmt->close();
                    $marked++;
                    $subjects_marked[] = $subj['subject_name'];
                } else {
                    $already++;
                }
            } else {
                $already++;
            }
            $check_stmt->close();
        }
        $subject_stmt->close();

        // 2. Allow Sign Out only after class end time
        $signout_sql = "SELECT sub.id, sub.subject_name, sub.end_time FROM subjects sub
            JOIN student_subjects ss ON ss.subject_id = sub.id
            WHERE ss.student_id = ?
            AND FIND_IN_SET(?, sub.schedule_days) > 0
            AND sub.end_time < ?";
        $signout_stmt = $conn->prepare($signout_sql);
        $signout_stmt->bind_param('sss', $student_id, $current_day, $current_time);
        $signout_stmt->execute();
        $subjects_ended = $signout_stmt->get_result();

        $signed_out = 0;
        $subjects_signed_out = [];
        while ($subj = $subjects_ended->fetch_assoc()) {
            // Check if already marked present but not yet signed out for this subject today
            $check_present_sql = "SELECT id FROM attendance WHERE student_id=? AND subject_id=? AND DATE(scan_time)=? AND status='Present'";
            $check_present_stmt = $conn->prepare($check_present_sql);
            $check_present_stmt->bind_param('sis', $student_id, $subj['id'], $today);
            $check_present_stmt->execute();
            $check_present_stmt->store_result();
            $present_exists = $check_present_stmt->num_rows > 0;
            $check_present_stmt->close();

            $check_signout_sql = "SELECT id FROM attendance WHERE student_id=? AND subject_id=? AND DATE(scan_time)=? AND status='Signed Out'";
            $check_signout_stmt = $conn->prepare($check_signout_sql);
            $check_signout_stmt->bind_param('sis', $student_id, $subj['id'], $today);
            $check_signout_stmt->execute();
            $check_signout_stmt->store_result();
            $signout_exists = $check_signout_stmt->num_rows > 0;
            $check_signout_stmt->close();

            if ($present_exists && !$signout_exists) {
                // Insert Signed Out record for this subject
                $insert_sql = "INSERT INTO attendance (student_id, subject_id, semester_id, scan_time, status) VALUES (?, ?, ?, ?, 'Signed Out')";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param('siis', $student_id, $subj['id'], 1, $now);
                $insert_stmt->execute();
                $insert_stmt->close();
                $signed_out++;
                $subjects_signed_out[] = $subj['subject_name'];
            }
        }
        $signout_stmt->close();

        // Message logic
        if ($marked > 0) {
            $subject_list = $subjects_marked ? (': ' . implode(', ', $subjects_marked)) : '';
            $msg = "✅ Present recorded for <b>" . htmlspecialchars($student['name']) . "</b> in $marked subject(s)$subject_list!";
        } elseif ($signed_out > 0) {
            $subject_list = $subjects_signed_out ? (': ' . implode(', ', $subjects_signed_out)) : '';
            $msg = "📤 Signed Out for <b>" . htmlspecialchars($student['name']) . "</b> in $signed_out subject(s)$subject_list!";
        } elseif ($already > 0) {
            $msg = "ℹ️ Already marked present for today in $already subject(s).";
        } else {
            $msg = "⚠️ No scheduled subject for this time, or already signed out.";
        }
    } else {
        $msg = "<span style='color:red;'>❌ Student not found!</span>";
    }
}
?>

<div class="min-h-screen bg-gradient-to-r from-blue-100 via-purple-100 to-pink-100 py-20 px-4 flex items-center justify-center">
    <div class="bg-white p-12 rounded-3xl shadow-2xl w-full max-w-xl">
        <h2 class="text-3xl font-bold mb-8 text-center text-indigo-700">📷 Scan Attendance</h2>
        <?php if (!empty($msg)) echo "<p class='text-center mb-8 text-xl font-semibold'>$msg</p>"; ?>
        <form method="post" id="scan-form" autocomplete="off" class="mb-10 flex flex-col items-center gap-6">
            <input type="text" name="barcode" id="barcode" placeholder="Scan barcode here" autofocus autocomplete="off"
                class="w-96 p-5 border-2 border-indigo-200 rounded-xl shadow focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 transition text-2xl placeholder-gray-700 text-gray-800 font-semibold text-center">
            <button type="submit" class="bg-indigo-600 text-white rounded-xl p-4 px-10 hover:bg-indigo-700 transition text-xl font-bold shadow">Submit</button>
        </form>
        <?php if ($student): ?>
        <div class="mt-8 p-8 border rounded-2xl bg-gray-50 flex gap-8 items-center">
            <div>
                <h3 class="font-semibold mb-2 text-lg">Student Info</h3>
                <p class="mb-1"><b>ID:</b> <?php echo htmlspecialchars($student['student_id']); ?></p>
                <p class="mb-1"><b>Name:</b> <?php echo htmlspecialchars($student['name']); ?></p>
                <p class="mb-1"><b>Section:</b> <?php echo htmlspecialchars($student['section']); ?></p>
                <p class="mb-1"><b>Course:</b> <?php echo htmlspecialchars($student['course']); ?></p>
                <p class="mb-1"><b>Year Level:</b> <?php echo htmlspecialchars($student['year_level']); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
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
</script>

<?php include 'includes/footer.php'; ?>
