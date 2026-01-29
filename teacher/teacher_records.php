<?php 
date_default_timezone_set('Asia/Manila'); 
include '../includes/header.php'; 
include '../includes/db.php';  

// Only allow teachers
if (!isset($_SESSION['teacher_id'])) {     
    header("Location: teacher_login.php");     
    exit(); 
}  

$teacher_id = $_SESSION['teacher_id']; 
$teacher_name = $_SESSION['teacher_name'];  

// Get filter parameters
$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$subject_filter = isset($_GET['subject']) ? $_GET['subject'] : '';

// Get teacher's subjects for filter dropdown
$subjects = []; 
$subject_stmt = $conn->prepare("SELECT id, subject_name FROM subjects WHERE teacher_id = (SELECT id FROM teachers WHERE teacher_id = ?)"); 
$subject_stmt->bind_param("s", $teacher_id); 
$subject_stmt->execute(); 
$subject_result = $subject_stmt->get_result(); 
while ($row = $subject_result->fetch_assoc()) {     
    $subjects[] = $row; 
}  

// Build attendance query
$where_conditions = ["DATE(a.scan_time) = ?"];
$params = [$date_filter];
$param_types = "s";

if (!empty($subject_filter)) {
    $where_conditions[] = "a.subject_id = ?";
    $params[] = $subject_filter;
    $param_types .= "i";
}

$where_clause = implode(" AND ", $where_conditions);

$attendance_query = $conn->prepare("
    SELECT a.*, s.name, s.section, s.year_level, s.pc_number, sub.subject_name
    FROM attendance a 
    JOIN students s ON a.student_id = s.student_id 
    JOIN student_subjects ss ON s.student_id = ss.student_id
    JOIN subjects sub ON ss.subject_id = sub.id
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
        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN a.status = 'Signed Out' THEN 1 ELSE 0 END) as signed_out_count
    FROM attendance a 
    JOIN students s ON a.student_id = s.student_id 
    JOIN student_subjects ss ON s.student_id = ss.subject_id
    JOIN subjects sub ON ss.subject_id = sub.id
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
    <title>Attendance Records - Teacher Dashboard</title>     
    <script src="https://cdn.tailwindcss.com"></script>     
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">     
</head> 

<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex">     
    <!-- Shared sidebar -->
    <?php include '../includes/sidebar.php'; ?>
                            <p class="text-sm font-medium text-gray-600">Total Records</p>
                            <p class="text-2xl font-bold text-gray-900"><?= $stats['total_records'] ?? 0 ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-user-graduate text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Unique Students</p>
                            <p class="text-2xl font-bold text-gray-900"><?= $stats['unique_students'] ?? 0 ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-check text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Present</p>
                            <p class="text-2xl font-bold text-gray-900"><?= $stats['present_count'] ?? 0 ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-red-100 text-red-600">
                            <i class="fas fa-sign-out-alt text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Signed Out</p>
                            <p class="text-2xl font-bold text-gray-900"><?= $stats['signed_out_count'] ?? 0 ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-filter mr-2 text-blue-500"></i>Filters
                </h2>
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                            <?php foreach ($subjects as $sub): ?>
                                <option value="<?= htmlspecialchars($sub['id']) ?>" 
                                        <?= $subject_filter == $sub['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sub['subject_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition duration-300">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Attendance Records Table -->
            <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-list mr-2 text-blue-500"></i>Attendance Records
                    </h2>
                    <div class="flex space-x-2">
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
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PC Number</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
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
                                            <?= htmlspecialchars($record['pc_number'] ?? '-') ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= htmlspecialchars($record['subject_name']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?= date('h:i:s A', strtotime($record['scan_time'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($record['status'] === 'Present'): ?>
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                    <i class="fas fa-check mr-1"></i>Present
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                                    <i class="fas fa-sign-out-alt mr-1"></i>Signed Out
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

            <!-- Quick Actions -->
            <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 mt-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-bolt mr-2 text-blue-500"></i>Quick Actions
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="teacher_../scan.php" class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-4 rounded-lg text-center hover:from-blue-600 hover:to-blue-700 transition duration-300 transform hover:scale-105">
                        <i class="fas fa-barcode text-2xl mb-2"></i>
                        <p class="font-semibold">Scan Attendance</p>
                        <p class="text-sm opacity-90">Record new attendance</p>
                    </a>
                    <a href="teacher_students.php" class="bg-gradient-to-r from-green-500 to-green-600 text-white p-4 rounded-lg text-center hover:from-green-600 hover:to-green-700 transition duration-300 transform hover:scale-105">
                        <i class="fas fa-user-graduate text-2xl mb-2"></i>
                        <p class="font-semibold">View Students</p>
                        <p class="text-sm opacity-90">Student list</p>
                    </a>
                    <a href="teacher_dashboard.php" class="bg-gradient-to-r from-purple-500 to-purple-600 text-white p-4 rounded-lg text-center hover:from-purple-600 hover:to-purple-700 transition duration-300 transform hover:scale-105">
                        <i class="fas fa-tachometer-alt text-2xl mb-2"></i>
                        <p class="font-semibold">Dashboard</p>
                        <p class="text-sm opacity-90">Back to dashboard</p>
                    </a>
                </div>
            </div>
        </main>     
    </div>     

    <script>
        function exportToCSV() {
            const table = document.querySelector('table');
            const rows = table.querySelectorAll('tr');
            let csv = [];
            
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                for (let j = 0; j < cols.length; j++) {
                    let text = cols[j].innerText.replace(/"/g, '""');
                    row.push('"' + text + '"');
                }
                csv.push(row.join(','));
            }
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'attendance_records_<?= date('Y-m-d') ?>.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body> 
</html> 

<?php include '../includes/footer.php'; ?>






