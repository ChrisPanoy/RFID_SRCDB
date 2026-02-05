<?php
session_start();
include '../includes/db.php';

date_default_timezone_set('Asia/Manila');

// Only allow teachers
if (!isset($_SESSION['teacher_id'])) {
    header("Location: teacher_login.php");
    exit();
}

$teacher_id   = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'];
$teacher_id_int = (int)$teacher_id; // employees.employee_id in src_db

// Handle dismissal action
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dismiss_subject'])) {
    // Here subject_id actually represents schedule.schedule_id
    $schedule_id = intval($_POST['subject_id']);
    $today       = date('Y-m-d');
    $now_time    = date('H:i:s');

    // Verify schedule belongs to this teacher and get subject name
    $verify_sql = "
        SELECT subj.subject_name
        FROM schedule sc
        JOIN subjects subj ON sc.subject_id = subj.subject_id
        WHERE sc.schedule_id = ? AND sc.employee_id = ?
    ";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $schedule_id, $teacher_id_int);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();

    if ($verify_result->num_rows > 0) {
        $subject_info = $verify_result->fetch_assoc();
        $subject_name = $subject_info['subject_name'];

        // Get all students who have time_in but no time_out today for this schedule
        $present_sql = "
            SELECT DISTINCT
                st.student_id,
                CONCAT(st.last_name, ', ', st.first_name) AS student_name,
                a.attendance_id
            FROM attendance a
            JOIN admissions adm ON a.admission_id = adm.admission_id
            JOIN students st   ON adm.student_id  = st.student_id
            WHERE a.schedule_id = ?
              AND a.attendance_date = ?
              AND a.time_in IS NOT NULL
              AND (a.time_out IS NULL OR a.time_out = '')
        ";
        $present_stmt = $conn->prepare($present_sql);
        $present_stmt->bind_param("is", $schedule_id, $today);
        $present_stmt->execute();
        $present_result = $present_stmt->get_result();

        $dismissed_count    = 0;
        $dismissed_students = [];

        // Set Time Out and status for all present students
        while ($student = $present_result->fetch_assoc()) {
            $update_stmt = $conn->prepare("UPDATE attendance SET time_out = ?, status = 'Signed Out' WHERE attendance_id = ?");
            $update_stmt->bind_param("si", $now_time, $student['attendance_id']);

            if ($update_stmt->execute()) {
                $dismissed_count++;
                $dismissed_students[] = $student['student_name'];
            }
            $update_stmt->close();
        }

        if ($dismissed_count > 0) {
            $message = "Successfully dismissed {$dismissed_count} students from {$subject_name}";
            $message_type = 'success';
        } else {
            $message = "No students found to dismiss from {$subject_name} (all students have already timed out)";
            $message_type = 'info';
        }

        $present_stmt->close();
    } else {
        $message      = "Subject not found or not assigned to you";
        $message_type = 'error';
    }
    $verify_stmt->close();
}

// Get teacher's schedules/subjects with current attendance counts (aligned to src_db schema)
$subjects_sql = "\n    SELECT\n        sc.schedule_id AS id,\n        subj.subject_name,\n        subj.subject_code,\n        sc.time_start AS start_time,\n        sc.time_end   AS end_time,\n        COUNT(DISTINCT adm.student_id) AS total_enrolled,\n        SUM(\n            CASE\n                WHEN a.attendance_date = CURDATE()\n                 AND a.time_in IS NOT NULL\n                 AND (a.time_out IS NULL OR a.time_out = '')\n                THEN 1\n                ELSE 0\n            END\n        ) AS currently_present\n    FROM schedule sc\n    JOIN subjects subj ON sc.subject_id = subj.subject_id\n    LEFT JOIN admissions adm ON adm.schedule_id = sc.schedule_id\n    LEFT JOIN attendance a  ON a.schedule_id  = sc.schedule_id\n    WHERE sc.employee_id = ?\n    GROUP BY sc.schedule_id, subj.subject_name, subj.subject_code, sc.time_start, sc.time_end\n    ORDER BY subj.subject_name\n";
$subjects_query = $conn->prepare($subjects_sql);
$subjects_query->bind_param("i", $teacher_id_int);
$subjects_query->execute();
$subjects = $subjects_query->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Dismissal - Teacher</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', 'Segoe UI', Arial, sans-serif; }
        .sidebar-link.active, .sidebar-link:hover { background: linear-gradient(90deg, #4f8cff 0%, #a18fff 100%); color: #fff !important; }
        .dismiss-btn { transition: all 0.3s ease; }
        .dismiss-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .subject-card { transition: all 0.3s ease; }
        .subject-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex">
    <!-- Shared sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-1 ml-80 min-h-screen main-content">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 mb-8">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="rounded-full bg-blue-50 border border-blue-100 p-3">
                            <i class="fas fa-door-open text-blue-600 text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-extrabold text-gray-800">Class Dismissal</h1>
                            <p class="text-sm text-gray-500">Dismiss students who forgot to time out</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-600">Faculty</p>
                        <p class="text-xl font-bold text-blue-600"><?= htmlspecialchars($teacher_name) ?></p>
                    </div>
                </div>
            </div>

            <!-- Message Display -->
            <?php if ($message): ?>
                <div class="mb-6">
                    <?php if ($message_type === 'success'): ?>
                        <div class="bg-blue-50 border border-blue-200 text-blue-700 p-4 rounded-lg">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-check-circle text-blue-600"></i>
                                <div>
                                    <strong>Success!</strong>
                                    <div><?= htmlspecialchars($message) ?></div>
                                    <?php if (!empty($dismissed_students)): ?>
                                        <div class="mt-2 text-sm">
                                            <strong>Dismissed students:</strong> <?= htmlspecialchars(implode(', ', $dismissed_students)) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($message_type === 'info'): ?>
                        <div class="bg-blue-50 border border-blue-200 text-blue-700 p-4 rounded-lg">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-info-circle text-blue-600"></i>
                                <div>
                                    <strong>Info:</strong>
                                    <div><?= htmlspecialchars($message) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-exclamation-circle text-red-600"></i>
                                <div>
                                    <strong>Error:</strong>
                                    <div><?= htmlspecialchars($message) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Instructions -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-6 mb-8 border border-blue-100">
                <div class="flex items-start gap-4">
                    <div class="rounded-full bg-blue-100 p-2">
                        <i class="fas fa-lightbulb text-blue-600"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-blue-800 mb-2">How Class Dismissal Works</h3>
                        <ul class="text-blue-700 space-y-1 text-sm">
                    
                            <li>• Only students who have timed in but haven't timed out will be affected</li>
                            <li>• This creates "Signed Out" records for all present students at the current time</li>
                            <li>• Use this at the end of class if students forgot to scan their time out</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Subjects Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if ($subjects->num_rows > 0): ?>
                    <?php while ($subject = $subjects->fetch_assoc()): ?>
                        <div class="subject-card bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                            <div class="flex flex-col h-full">
                                <!-- Subject Header -->
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <h3 class="text-xl font-bold text-gray-800 mb-1">
                                            <?= htmlspecialchars($subject['subject_name']) ?>
                                        </h3>
                                        <p class="text-sm text-gray-600 mb-2">
                                            <?= htmlspecialchars($subject['subject_code']) ?>
                                        </p>
                                        <?php if (!empty($subject['start_time']) && !empty($subject['end_time'])): ?>
                                            <p class="text-xs text-gray-500">
                                                <i class="fas fa-calendar-alt mr-1"></i>
                                                <?= date('g:i A', strtotime($subject['start_time'])) ?> - <?= date('g:i A', strtotime($subject['end_time'])) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Statistics -->
                                <div class="flex-1 mb-4">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="bg-blue-50 rounded-lg p-3 text-center">
                                            <div class="text-2xl font-bold text-blue-600"><?= $subject['total_enrolled'] ?></div>
                                            <div class="text-xs text-blue-700">Total of Students</div>
                                        </div>
                                        <div class="bg-green-50 rounded-lg p-3 text-center">
                                            <div class="text-2xl font-bold text-green-600"><?= $subject['currently_present'] ?></div>
                                            <div class="text-xs text-green-700">Currently Present</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Dismiss Button -->
                                <div class="mt-auto">
                                    <?php if ($subject['currently_present'] > 0): ?>
                                        <form method="post" onsubmit="return confirmDismissal('<?= htmlspecialchars($subject['subject_name']) ?>', <?= $subject['currently_present'] ?>)">
                                            <input type="hidden" name="subject_id" value="<?= $subject['id'] ?>">
                                            <button type="submit" name="dismiss_subject" class="dismiss-btn w-full bg-gradient-to-r from-blue-500 to-blue-600 text-white font-semibold py-3 px-4 rounded-lg shadow-md hover:from-blue-600 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                                <i class="fas fa-door-open mr-2"></i>
                                                Dismiss Class (<?= $subject['currently_present'] ?> students)
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button disabled class="w-full bg-gray-300 text-gray-500 font-semibold py-3 px-4 rounded-lg cursor-not-allowed">
                                            <i class="fas fa-check-circle mr-2"></i>
                                            No Students to Dismiss
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-span-full bg-white rounded-xl shadow-lg p-8 text-center border border-gray-200">
                        <i class="fas fa-book-open text-4xl text-gray-400 mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-600 mb-2">No Subjects Assigned</h3>
                        <p class="text-gray-500">You don't have any subjects assigned yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Real-time Updates -->
            <div class="mt-8 bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-sync-alt text-blue-500"></i>
                        <span class="text-gray-700 font-medium">Auto-refresh enabled</span>
                    </div>
                    <div class="text-sm text-gray-500">
                        Last updated: <span id="last-update"><?= date('g:i:s A') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDismissal(subjectName, studentCount) {
            return confirm(`Are you sure you want to dismiss all ${studentCount} students from "${subjectName}"?\n\nThis will automatically time out all students who are currently present but haven't timed out yet.`);
        }

        // Real-time updates for dismissal page
        function updateDismissalStats() {
            fetch('../ajax/teacher_dismissal_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update each subject card
                        data.subjects.forEach(subject => {
                            // Find the subject card by subject ID
                            const subjectCards = document.querySelectorAll('.subject-card');
                            subjectCards.forEach(card => {
                                const form = card.querySelector('form');
                                if (form) {
                                    const subjectIdInput = form.querySelector('input[name="subject_id"]');
                                    if (subjectIdInput && subjectIdInput.value == subject.id) {
                                        // Update enrolled count
                                        const enrolledEl = card.querySelector('.bg-blue-50 .text-2xl');
                                        if (enrolledEl) enrolledEl.textContent = subject.total_enrolled;
                                        
                                        // Update present count
                                        const presentEl = card.querySelector('.bg-green-50 .text-2xl');
                                        if (presentEl) presentEl.textContent = subject.currently_present;
                                        
                                        // Update dismiss button
                                        const dismissBtn = card.querySelector('button[name="dismiss_subject"]');
                                        const disabledBtn = card.querySelector('button[disabled]');
                                        
                                        if (subject.currently_present > 0) {
                                            if (disabledBtn) {
                                                // Replace disabled button with active one
                                                const newBtn = document.createElement('button');
                                                newBtn.type = 'submit';
                                                newBtn.name = 'dismiss_subject';
                                                newBtn.className = 'dismiss-btn w-full bg-gradient-to-r from-blue-500 to-blue-600 text-white font-semibold py-3 px-4 rounded-lg shadow-md hover:from-blue-600 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2';
                                                newBtn.innerHTML = `<i class="fas fa-door-open mr-2"></i>Dismiss Class (${subject.currently_present} students)`;
                                                newBtn.onclick = function() {
                                                    return confirmDismissal(subject.subject_name, subject.currently_present);
                                                };
                                                disabledBtn.parentNode.replaceChild(newBtn, disabledBtn);
                                            } else if (dismissBtn) {
                                                // Update existing button text
                                                dismissBtn.innerHTML = `<i class="fas fa-door-open mr-2"></i>Dismiss Class (${subject.currently_present} students)`;
                                            }
                                        } else {
                                            if (dismissBtn) {
                                                // Replace active button with disabled one
                                                const newBtn = document.createElement('button');
                                                newBtn.disabled = true;
                                                newBtn.className = 'w-full bg-gray-300 text-gray-500 font-semibold py-3 px-4 rounded-lg cursor-not-allowed';
                                                newBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i>No Students to Dismiss';
                                                dismissBtn.parentNode.replaceChild(newBtn, dismissBtn);
                                            }
                                        }
                                    }
                                }
                            });
                        });
                        
                        console.log('Dismissal stats updated:', data.statistics);
                    }
                })
                .catch(error => {
                    console.error('Error updating dismissal stats:', error);
                });
        }

        // Auto-refresh every 15 seconds
        setInterval(updateDismissalStats, 15000);
        
        // Initial update
        setTimeout(updateDismissalStats, 2000);

        // Update last update time
        setInterval(function() {
            document.getElementById('last-update').textContent = new Date().toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
        }, 1000);
    </script>
</body>
</html>



