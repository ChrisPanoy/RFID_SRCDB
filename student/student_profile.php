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

$student_id = intval($_GET['id']);
$student = $conn->query("SELECT * FROM students WHERE id = $student_id")->fetch_assoc();

if (!$student) {
    header("Location: students.php");
    exit();
}

// Get student's assigned subjects with teachers
$subjects_query = $conn->prepare("
    SELECT sub.subject_code, sub.subject_name, t.name as teacher_name, t.teacher_id
    FROM student_subjects ss
    JOIN subjects sub ON ss.subject_id = sub.id
    LEFT JOIN teachers t ON sub.teacher_id = t.id
    WHERE ss.student_id = ?
    ORDER BY sub.subject_code
");
$subjects_query->bind_param("s", $student['student_id']);
$subjects_query->execute();
$subjects = $subjects_query->get_result();

// Get recent attendance records
$attendance_query = $conn->prepare("
    SELECT scan_time, status
    FROM attendance
    WHERE student_id = ?
    ORDER BY scan_time DESC
    LIMIT 10
");
$attendance_query->bind_param("s", $student['student_id']);
$attendance_query->execute();
$attendance = $attendance_query->get_result();

// Get attendance statistics
$stats_query = $conn->prepare("
    SELECT 
        COUNT(*) as total_records,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as time_in_count,
        SUM(CASE WHEN status = 'Signed Out' THEN 1 ELSE 0 END) as time_out_count
    FROM attendance
    WHERE student_id = ?
");
$stats_query->bind_param("s", $student['student_id']);
$stats_query->execute();
$stats = $stats_query->get_result()->fetch_assoc();
?>

<div class="min-h-screen bg-gradient-to-r from-blue-100 via-purple-100 to-pink-100 py-6 px-4 sm:px-6 lg:px-8">
    <div class="max-w-6xl mx-auto">
        <!-- Back Button -->
        <div class="mb-6">
            <a href="students.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition duration-300">
                <i class="fas fa-arrow-left mr-2"></i>Back to Students
            </a>
        </div>

        <!-- Student Profile Card -->
        <div class="bg-white shadow-xl rounded-lg overflow-hidden mb-8">
            <div class="bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-8">
                <div class="flex flex-col sm:flex-row items-center sm:items-start">
                    <div class="flex-shrink-0">
                        <?php if ($student['profile_pic']): ?>
                            <img src="../assets/img/<?= htmlspecialchars($student['profile_pic']) ?>" 
                                 alt="Profile" class="w-24 h-24 object-cover rounded-full border-4 border-white shadow-lg">
                        <?php else: ?>
                            <div class="w-24 h-24 bg-gray-300 rounded-full border-4 border-white shadow-lg flex items-center justify-center">
                                <i class="fas fa-user text-gray-600 text-3xl"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="mt-4 sm:mt-0 sm:ml-6 text-center sm:text-left text-white">
                        <h1 class="text-3xl font-bold"><?= htmlspecialchars($student['name']) ?></h1>
                        <p class="text-xl opacity-90"><?= htmlspecialchars($student['student_id']) ?></p>
                        <div class="flex flex-wrap justify-center sm:justify-start mt-2 gap-2">
                            <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full text-sm">
                                Year <?= htmlspecialchars($student['year_level']) ?>
                            </span>
                            <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full text-sm">
                                Section <?= htmlspecialchars($student['section']) ?>
                            </span>
                            <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full text-sm">
                                <?= htmlspecialchars($student['course']) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Assigned Subjects -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white shadow-xl rounded-lg p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                        <i class="fas fa-book-open mr-3 text-blue-500"></i>Assigned Subjects
                    </h2>
                    <?php if ($subjects->num_rows > 0): ?>
                        <div class="space-y-4">
                            <?php while ($subject = $subjects->fetch_assoc()): ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition duration-300">
                                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between">
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-800">
                                                <?= htmlspecialchars($subject['subject_name']) ?>
                                            </h3>
                                            <p class="text-gray-600"><?= htmlspecialchars($subject['subject_code']) ?></p>
                                        </div>
                                        <div class="text-left sm:text-right mt-2 sm:mt-0">
                                            <?php if ($subject['teacher_name']): ?>
                                                <p class="text-sm text-gray-600">Teacher:</p>
                                                <p class="font-semibold text-blue-600">
                                                    <?= htmlspecialchars($subject['teacher_name']) ?>
                                                </p>
                                                <p class="text-xs text-gray-500"><?= htmlspecialchars($subject['teacher_id']) ?></p>
                                            <?php else: ?>
                                                <p class="text-sm text-gray-500 italic">No teacher assigned</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-book-open text-4xl text-gray-400 mb-4"></i>
                            <p class="text-gray-600">No subjects assigned yet.</p>
                            <p class="text-sm text-gray-500">Contact the administrator to assign subjects.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white shadow-xl rounded-lg p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-history mr-2 text-blue-500"></i>Recent Activity
                    </h3>
                    <?php if ($attendance->num_rows > 0): ?>
                        <div class="divide-y divide-gray-100">
                            <?php while ($record = $attendance->fetch_assoc()): ?>
                                <div class="flex flex-col sm:flex-row items-center justify-between py-2 px-2 hover:bg-gray-50 transition rounded group">
                                    <div class="flex items-center gap-2">
                                        <span class="text-gray-800 font-medium text-sm"><?= date('M j, Y', strtotime($record['scan_time'])) ?></span>
                                        <span class="text-gray-500 text-xs"><?= date('g:i A', strtotime($record['scan_time'])) ?></span>
                                    </div>
                                    <?php if ($record['status'] === 'Present'): ?>
                                        <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full bg-green-50 text-green-700 border border-green-200 shadow-sm group-hover:scale-105 transition mt-2 sm:mt-0" style="min-width: 80px; text-align: center;">
                                            <i class="fas fa-sign-in-alt mr-1"></i>Time In
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full bg-red-50 text-red-700 border border-red-200 shadow-sm group-hover:scale-105 transition mt-2 sm:mt-0" style="min-width: 80px; text-align: center;">
                                            <i class="fas fa-sign-out-alt mr-1"></i>Time Out
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-clock text-2xl text-gray-400 mb-2"></i>
                            <p class="text-gray-500 text-sm">No recent activity</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Attendance Statistics -->
            <div class="space-y-6">
                <!-- Stats Cards -->
                <div class="bg-white shadow-xl rounded-lg p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-chart-bar mr-2 text-blue-500"></i>Attendance Statistics
                    </h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                            <div>
                                <p class="text-sm text-gray-600">Total Records</p>
                                <p class="text-xl font-bold text-blue-600"><?= $stats['total_records'] ?></p>
                            </div>
                            <i class="fas fa-calendar-check text-blue-500 text-xl"></i>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                            <div>
                                <p class="text-sm text-gray-600">Time In</p>
                                <p class="text-xl font-bold text-green-600"><?= $stats['time_in_count'] ?></p>
                            </div>
                            <i class="fas fa-sign-in-alt text-green-500 text-xl"></i>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                            <div>
                                <p class="text-sm text-gray-600">Time Out</p>
                                <p class="text-xl font-bold text-red-600"><?= $stats['time_out_count'] ?></p>
                            </div>
                            <i class="fas fa-sign-out-alt text-red-500 text-xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Student Info -->
                <div class="bg-white shadow-xl rounded-lg p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-info-circle mr-2 text-blue-500"></i>Student Information
                    </h3>
                    <div class="space-y-3">
                        <div>
                            <p class="text-sm text-gray-600">Student ID</p>
                            <p class="font-semibold"><?= htmlspecialchars($student['student_id']) ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Barcode</p>
                            <p class="font-mono text-sm bg-gray-100 px-2 py-1 rounded">
                                <?= htmlspecialchars($student['barcode']) ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Year Level</p>
                            <p class="font-semibold"><?= htmlspecialchars($student['year_level']) ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Section</p>
                            <p class="font-semibold"><?= htmlspecialchars($student['section']) ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Course</p>
                            <p class="font-semibold"><?= htmlspecialchars($student['course']) ?></p>
                        </div>
                        <?php if (!empty($student['pc_number'])): ?>
                            <div>
                                <p class="text-sm text-gray-600">PC Number</p>
                                <p class="font-semibold"><?= htmlspecialchars($student['pc_number']) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>



