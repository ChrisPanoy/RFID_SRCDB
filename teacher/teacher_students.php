<?php
session_start();
include '../includes/db.php';

// Redirect if not logged in as teacher
if (!isset($_SESSION['teacher_id'])) {
    header("Location: teacher_login.php");
    exit();
}

$teacher_id   = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'];
$teacher_id_int = (int)$teacher_id; // employees.employee_id in src_db

// Get teacher's subjects via schedule + subject (src_db schema)
$subjects_sql = "
    SELECT DISTINCT
        subj.subject_id    AS id,
        subj.subject_code,
        subj.subject_name
    FROM schedule sc
    JOIN subjects subj ON sc.subject_id = subj.subject_id
    WHERE sc.employee_id = ?
    ORDER BY subj.subject_name
";
$subjects_query = $conn->prepare($subjects_sql);
if ($subjects_query) {
    $subjects_query->bind_param("i", $teacher_id_int);
    $subjects_query->execute();
    $subjects = $subjects_query->get_result();
} else {
    $subjects = null;
}

// Get students assigned to teacher's subjects (filtered by schedule_id)
$subject_filter = isset($_GET['subject']) ? intval($_GET['subject']) : 0; // schedule_id
if ($subject_filter && isset($conn)) {
    $students_sql = "
        SELECT DISTINCT
            st.student_id,
            st.first_name,
            st.middle_name,
            st.last_name,
            st.gender,
            st.rfid_number AS barcode,
            st.profile_picture AS profile_pic,
            sec.section_name AS section,
            yl.year_name     AS year_level,
            (SELECT COUNT(*) FROM attendance a 
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
        LEFT JOIN sections sec     ON adm.section_id    = sec.section_id
        LEFT JOIN year_levels yl   ON adm.year_level_id = yl.year_id
        JOIN schedule sc   ON adm.schedule_id = sc.schedule_id
        WHERE sc.subject_id = ? AND sc.employee_id = ?
        ORDER BY yl.year_name, sec.section_name, st.last_name, st.first_name
    ";

    $students_query = $conn->prepare($students_sql);
    $students_query->bind_param("iiiii", $subject_filter, $subject_filter, $subject_filter, $subject_filter, $teacher_id_int);
    $students_query->execute();
    $students = $students_query->get_result();
} else {
    $students = null;
}

// Group students by year then section, with males first
$grouped = [];
if ($students) {
    while ($row = $students->fetch_assoc()) {
        // Build a simple full name for potential sorting/use in UI
        $full_name_parts = [
            trim($row['first_name']  ?? ''),
            trim($row['middle_name'] ?? ''),
            trim($row['last_name']   ?? ''),
        ];
        $row['name'] = trim(implode(' ', array_filter($full_name_parts)));

        $year_key    = $row['year_level'] ?? 'Unknown';
        $section_key = $row['section'] ?? 'Unknown';
        $grouped[$year_key][$section_key][] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Students - Teacher Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', 'Segoe UI', Arial, sans-serif; }
        .sidebar-link.active, .sidebar-link:hover { background: linear-gradient(90deg, #4f8cff 0%, #a18fff 100%); color: #fff !important; }
        .sidebar-link i { min-width: 1.5rem; }
        @media (max-width: 900px) {
            .sidebar { left: -220px; }
            .sidebar.open { left: 0; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex">
    <!-- Shared sidebar -->
    <?php include '../includes/sidebar.php'; ?>
    <!-- Main Content -->
    <div class="flex-1 ml-80 min-h-screen main-content">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Page Header -->
            <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-user-graduate text-blue-500"></i>My Students
                        </h1>
                        <p class="text-gray-600 mt-2">Students assigned to your subjects</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-600">Total Students</p>
                        <p class="text-2xl font-bold text-blue-600"><?= $students ? $students->num_rows : 0 ?></p>
                    </div>
                </div>
            </div>
            <!-- My Subjects Summary -->
            <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-book-open text-blue-500"></i>My Teaching Subjects
                </h2>
                <?php if ($subjects->num_rows > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php while ($subject = $subjects->fetch_assoc()): ?>
                            <a href="teacher_students.php?subject=<?= $subject['id'] ?>" class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-5 border-2 border-blue-200 shadow flex flex-col transition-transform duration-150 hover:-translate-y-1 hover:shadow-lg cursor-pointer">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-xl font-bold text-sky-800 mb-1"><?php
                                        $full_name_parts = [
                                            trim($subject['subject_name'] ?? ''),
                                        ];
                                        echo htmlspecialchars(trim(implode(' ', array_filter($full_name_parts))));
                                    ?></h4>
                                    <span class="bg-blue-100 text-blue-800 text-xs font-bold px-3 py-1 rounded-full shadow">
                                        <?= htmlspecialchars($subject['subject_code']) ?>
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600">Assigned students only</p>
                            </a>
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
            <!-- Students by Year and Section -->
            <?php if (!empty($grouped)): ?>
                <?php foreach ($grouped as $year => $sections): ?>
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 mb-8">
                        <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                            <i class="fas fa-graduation-cap text-blue-500"></i>Year <?= htmlspecialchars($year) ?>
                        </h2>
                        <?php foreach ($sections as $section => $students_in_section): ?>
                            <div class="mb-8">
                                <h3 class="text-lg font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                    <i class="fas fa-users text-indigo-500"></i>
                                    Section <?= htmlspecialchars($section) ?>
                                    <span class="ml-2 bg-indigo-100 text-indigo-800 text-sm font-medium px-2.5 py-0.5 rounded">
                                        <?= count($students_in_section) ?> students
                                    </span>
                                </h3>
                                <div class="space-y-6">
                                    <?php
                                    // Separate students by gender
                                    $male_students = array_filter($students_in_section, function($student) {
                                        return $student['gender'] === 'Male';
                                    });
                                    $female_students = array_filter($students_in_section, function($student) {
                                        return $student['gender'] === 'Female';
                                    });
                                    ?>
                                    
                                    <!-- Male Students -->
                                    <?php if (!empty($male_students)): ?>
                                        <div>
                                            <h4 class="text-md font-bold text-blue-600 mb-3 flex items-center gap-2">
                                                <i class="fas fa-mars text-blue-500"></i>Male Students (<?= count($male_students) ?>)
                                            </h4>
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-200">
                                                    <thead class="bg-blue-50">
                                                        <tr>
                                                            <th class="px-6 py-3 text-left text-xs font-medium text-blue-600 uppercase tracking-wider">Student ID</th>
                                                            <th class="px-6 py-3 text-left text-xs font-medium text-blue-600 uppercase tracking-wider">Last Name</th>
                                                            <th class="px-6 py-3 text-left text-xs font-medium text-blue-600 uppercase tracking-wider">First Name</th>
                                                            <th class="px-6 py-3 text-left text-xs font-medium text-blue-600 uppercase tracking-wider">Middle Name</th>
                                                            <th class="px-6 py-3 text-left text-xs font-medium text-blue-600 uppercase tracking-wider">Section</th>
                                                            <th class="px-6 py-3 text-left text-xs font-medium text-blue-600 uppercase tracking-wider">Year</th>
                                                            <th class="px-6 py-3 text-left text-xs font-medium text-blue-600 uppercase tracking-wider">Gender</th>
                                                            <th class="px-6 py-3 text-left text-xs font-medium text-blue-600 uppercase tracking-wider text-center">Present Records</th>
                                                            <th class="px-6 py-3 text-left text-xs font-medium text-blue-600 uppercase tracking-wider">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="bg-white divide-y divide-gray-200">
                                                        <?php foreach ($male_students as $student): ?>
                                                            <?php
                                                                $lastName   = $student['last_name']   ?? '';
                                                                $firstName  = $student['first_name']  ?? '';
                                                                $middleName = $student['middle_name'] ?? '';
                                                            ?>
                                                            <tr class="hover:bg-blue-50">
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                                    <?= htmlspecialchars($student['student_id']) ?>
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-semibold">
                                                                    <?= htmlspecialchars($lastName) ?>
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-semibold">
                                                                    <?= htmlspecialchars($firstName) ?>
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-semibold">
                                                                    <?= htmlspecialchars($middleName) ?>
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                    <?= htmlspecialchars($student['section'] ?? '') ?>
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                    <?= htmlspecialchars($student['year_level'] ?? '') ?>
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                    <?= htmlspecialchars($student['gender'] ?? '') ?>
                                                                </td>
                                                                 <td class="px-6 py-4 text-sm">
                                                                    <div class="flex flex-col items-center cursor-pointer hover:scale-105 transition-transform attendance-container" 
                                                                         id="attendance-container-<?= $student['student_id'] ?>"
                                                                         data-student-id="<?= $student['student_id'] ?>"
                                                                         data-detailed-history="<?= $student['detailed_history'] ?>"
                                                                         onclick="viewAttendance('<?= htmlspecialchars($student['name']) ?>', String(this.getAttribute('data-detailed-history')))">
                                                                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full font-bold text-xs mb-1 shadow-sm total-badge">
                                                                            Present: <?= $student['total_present'] ?> / <?= $student['total_sessions'] ?>
                                                                        </span>
                                                                        <span class="text-[10px] text-blue-500 font-medium hover:underline">
                                                                            View All Dates <i class="fas fa-external-link-alt ml-1"></i>
                                                                        </span>
                                                                    </div>
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap">
                                                                    <a href="teacher_edit_student.php?student_id=<?= urlencode($student['student_id']) ?>" class="inline-flex items-center px-3 py-1 bg-yellow-400 text-white rounded hover:bg-yellow-500 text-xs font-semibold">
                                                                        <i class="fas fa-edit mr-1"></i>Edit
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Female Students -->
                                    <?php if (!empty($female_students)): ?>
                                        <div>
                                            <h4 class="text-md font-bold text-pink-600 mb-3 flex items-center gap-2">
                                                <i class="fas fa-venus text-pink-500"></i>Female Students (<?= count($female_students) ?>)
                                            </h4>
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-200">
                                                    <thead class="bg-pink-50">
                                                        <tr>
                                                            <th class="px-6 py-3 text-left text-xs font-medium text-pink-600 uppercase tracking-wider">Student ID</th>
                                                            <th class="px-6 py-3 text-left text-xs font-medium text-pink-600 uppercase tracking-wider">Last Name</th>
                                                            <th class="px-6 py-3 text-left text-xs font-medium text-pink-600 uppercase tracking-wider">First Name</th>
                                                            <th class="px-6 py-3 text-left text-xs font-medium text-pink-600 uppercase tracking-wider">Middle Name</th>
                                                            <th class="px-6 py-3 text-left text-xs font-medium text-pink-600 uppercase tracking-wider">Section</th>
                                                            <th class="px-6 py-3 text-left text-xs font-medium text-pink-600 uppercase tracking-wider">Year</th>
                                                            <th class="px-6 py-3 text-left text-xs font-medium text-pink-600 uppercase tracking-wider">Gender</th>
                                                            <th class="px-6 py-3 text-left text-xs font-medium text-pink-600 uppercase tracking-wider text-center">Present Records</th>
                                                            <th class="px-6 py-3 text-left text-xs font-medium text-pink-600 uppercase tracking-wider">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="bg-white divide-y divide-gray-200">
                                                        <?php foreach ($female_students as $student): ?>
                                                            <?php
                                                                $lastName   = $student['last_name']   ?? '';
                                                                $firstName  = $student['first_name']  ?? '';
                                                                $middleName = $student['middle_name'] ?? '';
                                                            ?>
                                                            <tr class="hover:bg-pink-50">
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                                    <?= htmlspecialchars($student['student_id']) ?>
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-semibold">
                                                                    <?= htmlspecialchars($lastName) ?>
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-semibold">
                                                                    <?= htmlspecialchars($firstName) ?>
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-semibold">
                                                                    <?= htmlspecialchars($middleName) ?>
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                    <?= htmlspecialchars($student['section'] ?? '') ?>
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                    <?= htmlspecialchars($student['year_level'] ?? '') ?>
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                    <?= htmlspecialchars($student['gender'] ?? '') ?>
                                                                </td>
                                                                <td class="px-6 py-4 text-sm text-center">
                                                                    <div class="flex flex-col items-center cursor-pointer hover:scale-105 transition-transform attendance-container"
                                                                         id="attendance-container-<?= $student['student_id'] ?>"
                                                                         data-student-id="<?= $student['student_id'] ?>"
                                                                         data-detailed-history="<?= $student['detailed_history'] ?>"
                                                                         onclick="viewAttendance('<?= htmlspecialchars($student['name']) ?>', String(this.getAttribute('data-detailed-history')))">
                                                                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full font-bold text-xs mb-1 shadow-sm total-badge">
                                                                            Present: <?= $student['total_present'] ?> / <?= $student['total_sessions'] ?>
                                                                        </span>
                                                                        <span class="text-[10px] text-pink-500 font-medium hover:underline">
                                                                            View All Dates <i class="fas fa-external-link-alt ml-1"></i>
                                                                        </span>
                                                                    </div>
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap">
                                                                    <a href="teacher_edit_student.php?student_id=<?= urlencode($student['student_id']) ?>" class="inline-flex items-center px-3 py-1 bg-yellow-400 text-white rounded hover:bg-yellow-500 text-xs font-semibold">
                                                                        <i class="fas fa-edit mr-1"></i>Edit
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 text-center">
                    <i class="fas fa-user-graduate text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-600 mb-2">No Students Loaded</h3>
                    <p class="text-gray-500">Select a subject above to view its students.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Attendance Modal -->
    <div id="attendanceModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-2xl rounded-2xl bg-white animate-fade-in-down">
            <div class="flex flex-col">
                <div class="flex justify-between items-center mb-6 border-b pb-4">
                    <div>
                        <h3 class="text-2xl font-bold text-gray-800" id="modalStudentName">Student Attendance</h3>
                        <p class="text-sm text-gray-500 mt-1">Full history for this subject</p>
                    </div>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
                
                <div class="overflow-x-auto rounded-xl border border-gray-100">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Time In</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Time Out</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody id="attendanceLogs" class="bg-white divide-y divide-gray-200">
                            <!-- Logs will be injected here -->
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-8 text-right">
                    <button onclick="closeModal()" class="px-6 py-2 bg-gray-100 text-gray-700 font-bold rounded-lg hover:bg-gray-200 transition">Close</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .animate-fade-in-down {
            animation: fadeInDown 0.3s ease-out;
        }
        @keyframes fadeInDown {
            0% { opacity: 0; transform: translateY(-20px); }
            100% { opacity: 1; transform: translateY(0); }
        }
    </style>

    <script>
        function viewAttendance(name, history) {
            document.getElementById('modalStudentName').innerText = name + "'s Attendance History";
            const logsContainer = document.getElementById('attendanceLogs');
            logsContainer.innerHTML = '';
            
            if (!history || history === '') {
                logsContainer.innerHTML = '<tr><td colspan="4" class="px-6 py-10 text-center text-gray-500 bg-gray-50">No detailed logs available</td></tr>';
            } else {
                const logs = history.split('||');
                logs.forEach(log => {
                    const parts = log.split('|');
                    const row = document.createElement('tr');
                    row.className = 'hover:bg-blue-50 transition-colors';
                    
                    let statusClass = 'text-gray-600 bg-gray-50';
                    if (parts[3] === 'Present') statusClass = 'text-green-600 bg-green-50';
                    else if (parts[3] === 'Late') statusClass = 'text-yellow-600 bg-yellow-50';
                    else if (parts[3] === 'Absent') statusClass = 'text-red-600 bg-red-50';
                    
                    row.innerHTML = `
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-800">${parts[0]}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 font-medium">${parts[1]}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 font-medium">${parts[2]}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold ${statusClass}">
                                ${parts[3]}
                            </span>
                        </td>
                    `;
                    logsContainer.appendChild(row);
                });
            }
            
            document.getElementById('attendanceModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Prevent scroll
        }

        function closeModal() {
            document.getElementById('attendanceModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Polling for real-time sync with teacher_attendance_records and scanner
        const subjectFilter = <?= $subject_filter ?>;
        if (subjectFilter > 0) {
            setInterval(() => {
                fetch(`../ajax/get_student_attendance_v2.php?subject=${subjectFilter}`)
                    .then(response => response.json())
                    .then(res => {
                        if (res.success && res.data) {
                            Object.entries(res.data).forEach(([sid, stats]) => {
                                const container = document.getElementById(`attendance-container-${sid}`);
                                if (container) {
                                    // Update Badge
                                    const badge = container.querySelector('.total-badge');
                                    if (badge) badge.innerText = `Present: ${stats.total} / ${stats.sessions}`;
                                    
                                    // Update history attribute for modal
                                    container.setAttribute('data-detailed-history', stats.history);
                                }
                            });
                        }
                    })
                    .catch(err => console.error('Sync error:', err));
            }, 5000); // Check every 5 seconds
        }
    </script>
</body>
</html>

