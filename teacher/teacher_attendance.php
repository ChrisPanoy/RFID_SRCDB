<?php
session_start();
include '../includes/db.php';

// Redirect if not logged in as teacher
if (!isset($_SESSION['teacher_id'])) {
    header("Location: teacher_login.php");
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'];

// Get filter parameters
$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$student_filter = isset($_GET['student']) ? $_GET['student'] : '';
$subject_filter = isset($_GET['subject']) ? $_GET['subject'] : '';

// Get students assigned to teacher's subjects for filter dropdown
// Also get comma-separated list of subject IDs per student (so we can filter client-side)
$students_query = $conn->prepare("
    SELECT DISTINCT st.student_id, st.name, st.section, st.year_level,
           GROUP_CONCAT(DISTINCT ss.subject_id) AS subject_ids
    FROM students st
    JOIN student_subjects ss ON st.student_id = ss.student_id
    JOIN subjects sub ON ss.subject_id = sub.id
    WHERE sub.teacher_id = (SELECT id FROM teachers WHERE teacher_id = ?)
      AND st.course = 'BSIS'
    GROUP BY st.student_id
    ORDER BY st.name
");
$students_query->bind_param("s", $teacher_id);
$students_query->execute();
$students_result = $students_query->get_result();

// Get teacher subjects for subject filter
$subjects_query = $conn->prepare("
    SELECT id, subject_name
    FROM subjects
    WHERE teacher_id = (SELECT id FROM teachers WHERE teacher_id = ?)
    ORDER BY subject_name
");
$subjects_query->bind_param("s", $teacher_id);
$subjects_query->execute();
$subjects_result = $subjects_query->get_result();

// Build attendance query
$where_conditions = ["DATE(a.scan_time) = ?"];
$params = [$date_filter];
$param_types = "s";

if (!empty($student_filter)) {
    $where_conditions[] = "a.student_id = ?";
    $params[] = $student_filter;
    $param_types .= "s";
}

if (!empty($subject_filter)) {
    $where_conditions[] = "a.subject_id = ?";
    $params[] = $subject_filter;
    $param_types .= "i";
}

$where_clause = implode(" AND ", $where_conditions);

$attendance_query = $conn->prepare("
    SELECT a.*, s.name, s.section, s.year_level 
    FROM attendance a 
    JOIN students s ON a.student_id = s.student_id 
    JOIN subjects sub ON a.subject_id = sub.id
    WHERE $where_clause
    AND sub.teacher_id = (SELECT id FROM teachers WHERE teacher_id = ?)
    ORDER BY a.scan_time DESC
");
$all_params = array_merge($params, [$teacher_id]);
$attendance_query->bind_param($param_types . "s", ...$all_params);
$attendance_query->execute();
$attendance_records = $attendance_query->get_result();

// Get attendance statistics
$stats_query = $conn->prepare("
    SELECT 
        COUNT(*) as total_records,
        COUNT(DISTINCT a.student_id) as unique_students,
        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as time_in_count,
        SUM(CASE WHEN a.status = 'Signed Out' THEN 1 ELSE 0 END) as time_out_count
    FROM attendance a 
    JOIN students s ON a.student_id = s.student_id 
    JOIN subjects sub ON a.subject_id = sub.id
    WHERE $where_clause
    AND sub.teacher_id = (SELECT id FROM teachers WHERE teacher_id = ?)
");
$all_params2 = array_merge($params, [$teacher_id]);
$stats_query->bind_param($param_types . "s", ...$all_params2);
$stats_query->execute();
$stats = $stats_query->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Time In and Time Out Report - Teacher Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', 'Segoe UI', Arial, sans-serif; }
        .card { 
            background: white; 
            border-radius: 16px; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        .card:hover { 
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 16px 16px 0 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .card-body {
            padding: 1.5rem;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .stat-card:hover {
            border-color: #667eea;
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .bg-gradient-to-br { background: white !important; }
            .shadow-lg, .shadow { box-shadow: none !important; }
            .border { border: 1px solid #000 !important; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <!-- Header -->
    <?php include '../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="flex-1 ml-80 min-h-screen main-content">
        <header class="bg-white shadow-lg border-b border-gray-200 no-print">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center space-x-4">
                        <a href="teacher_dashboard.php" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                        </a>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-gray-600">
                            <i class="fas fa-user mr-2"></i><?= htmlspecialchars($teacher_name) ?>
                        </span>
                        <a href="teacher_../logout.php" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition duration-300">
                            <i class="fas fa-sign-out-alt mr-2"></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Page Header -->
            <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">
                            <i class="fas fa-chart-bar mr-3 text-blue-500"></i>Time In and Out Report
                        </h1>
                        <p class="text-gray-600 mt-2">View and analyze attendance records</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-600">Date: <?= date('F j, Y', strtotime($date_filter)) ?></p>
                        <p class="text-2xl font-bold text-blue-600"><?= $stats['total_records'] ?> records</p>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-500 rounded-lg flex items-center justify-center">
                            <i class="fas fa-calendar-check text-white text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Records</p>
                            <p class="text-2xl font-bold text-gray-900"><?= $stats['total_records'] ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-green-500 rounded-lg flex items-center justify-center">
                            <i class="fas fa-users text-white text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600"> Students</p>
                            <p class="text-2xl font-bold text-gray-900"><?= $stats['unique_students'] ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-green-500 rounded-lg flex items-center justify-center">
                            <i class="fas fa-sign-in-alt text-white text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Time In</p>
                            <p class="text-2xl font-bold text-gray-900"><?= $stats['time_in_count'] ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-red-500 rounded-lg flex items-center justify-center">
                            <i class="fas fa-sign-out-alt text-white text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Time Out</p>
                            <p class="text-2xl font-bold text-gray-900"><?= $stats['time_out_count'] ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 mb-8 no-print">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-filter mr-2 text-blue-500"></i>Filters
                </h2>
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="date" class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                        <input type="date" id="date" name="date" value="<?= htmlspecialchars($date_filter) ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
                        <select id="subject" name="subject"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Subjects</option>
                            <?php 
                            $subjects_result->data_seek(0);
                            while ($sub = $subjects_result->fetch_assoc()): 
                            ?>
                                <option value="<?= (int)$sub['id'] ?>" <?= ($subject_filter == $sub['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sub['subject_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Student filter dropdown -->
                    <div>
                        <label for="student" class="block text-sm font-medium text-gray-700 mb-2">Student</label>
                        <select id="student" name="student"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Students</option>
                            <?php 
                            $students_result->data_seek(0);
                            while ($st = $students_result->fetch_assoc()):
                                $studentId = htmlspecialchars($st['student_id']);
                                $studentLabel = htmlspecialchars($st['name'] . ' — ' . $st['year_level'] . ' / ' . $st['section']);
                                $subjectIdsAttr = htmlspecialchars($st['subject_ids']);
                            ?>
                                <option value="<?= $studentId ?>" data-subjects="<?= $subjectIdsAttr ?>" <?= ($student_filter == $st['student_id']) ? 'selected' : '' ?>>
                                    <?= $studentLabel ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <p id="student-help" class="text-xs text-gray-500 mt-1"></p>
                    </div>

                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition duration-300">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Attendance Records -->
            <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-list mr-2 text-blue-500"></i>Time In Time Out Records
                    </h2>
                    <div class="flex space-x-2 no-print">
                        <button onclick="exportToCSV()" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition duration-300">
                            <i class="fas fa-download mr-2"></i>Export CSV
                        </button>
                        <button onclick="window.print()" class="bg-purple-500 text-white px-4 py-2 rounded-lg hover:bg-purple-600 transition duration-300">
                            <i class="fas fa-print mr-2"></i>Print
                        </button>
                    </div>
                </div>
                
                <?php if ($attendance_records->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scan Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php while ($record = $attendance_records->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($record['student_id']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= htmlspecialchars($record['name']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= htmlspecialchars($record['section']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= htmlspecialchars($record['year_level']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= date('h:i:s A', strtotime($record['scan_time'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($record['status'] === 'Present'): ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                                    <i class="fas fa-sign-in-alt mr-1"></i>Time In
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                                    <i class="fas fa-sign-out-alt mr-1"></i>Time Out
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-calendar-times text-4xl text-gray-400 mb-4"></i>
                        <p class="text-gray-600">No attendance records found for the selected criteria.</p>
                    </div>
                <?php endif; ?>
            </div>


    <script>
        // Filter students dropdown based on selected subject.
        function filterStudents() {
            const subject = document.getElementById('subject').value;
            const studentSelect = document.getElementById('student');
            const options = studentSelect.querySelectorAll('option[data-subjects]');

            options.forEach(opt => {
                const subs = (opt.getAttribute('data-subjects') || '').split(',').map(s => s.trim()).filter(Boolean);
                if (!subject) {
                    opt.hidden = false;
                    opt.style.display = '';
                } else {
                    if (subs.includes(subject)) {
                        opt.hidden = false;
                        opt.style.display = '';
                    } else {
                        opt.hidden = true;
                        opt.style.display = 'none';
                    }
                }
            });

            if (studentSelect.value) {
                const selectedOpt = studentSelect.querySelector('option[value="' + studentSelect.value + '"]');
                if (selectedOpt && selectedOpt.hidden) {
                    studentSelect.value = '';
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            filterStudents();
            const subjectEl = document.getElementById('subject');
            subjectEl.addEventListener('change', filterStudents);
        });

        function exportToCSV() {
            const table = document.querySelector('table');
            const rows = table.querySelectorAll('tr');
            let csv = [];
            
            // Add headers
            const headerRow = [];
            const headers = table.querySelectorAll('th');
            headers.forEach(header => {
                headerRow.push('"' + header.textContent.trim() + '"');
            });
            csv.push(headerRow.join(','));
            
            // Add data rows
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const cols = row.querySelectorAll('td');
                const rowData = [];
                
                for (let j = 0; j < cols.length; j++) {
                    let text = cols[j].textContent.trim();
                    
                    // Clean up the status text for CSV
                    if (j === cols.length - 1) { // Status column
                        text = text.replace(/Time In|Time Out/g, '').trim();
                        text = text === '' ? 'Time In' : 'Time Out'; // Determine based on icon
                    }
                    
                    rowData.push('"' + text + '"');
                }
                
                csv.push(rowData.join(','));
            }
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'attendance_report_<?= $date_filter ?>.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // Enhanced print functionality
        function printReport() {
            window.print();
        }
    </script>
</body>
</html>


