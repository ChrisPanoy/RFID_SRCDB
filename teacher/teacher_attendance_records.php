<?php
session_start();
include '../includes/db.php';

// Redirect if not logged in as teacher - MUST be before any output
if (!isset($_SESSION['teacher_id'])) {
    header("Location: teacher_login.php");
    exit();
}

$teacher_id   = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'];
$teacher_id_int = (int)$teacher_id; // employees.employee_id in src_db

// Show all errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$subject_filter = isset($_GET['subject']) ? intval($_GET['subject']) : 0;
$date_filter    = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$status_filter  = isset($_GET['status']) ? $_GET['status'] : 'all';

// Load only subjects that the logged-in teacher actually teaches, via schedule.employee_id
// Each schedule row represents a subject offering handled by this teacher.
$subjects_query = $conn->prepare("SELECT DISTINCT 
        sub.subject_id AS id,
        sub.subject_name,
        sub.subject_code
    FROM schedule sc
    JOIN subjects sub ON sc.subject_id = sub.subject_id
    WHERE sc.employee_id = ?
    ORDER BY sub.subject_name");
$subjects_query->bind_param('i', $teacher_id_int);
$subjects_query->execute();
$subjects = $subjects_query->get_result();

$subject_filter = isset($_GET['subject']) ? intval($_GET['subject']) : 0;
$date_filter    = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$status_filter  = isset($_GET['status']) ? $_GET['status'] : 'all';

$students = [];
$error    = null;

// If requested, export CSV for the current subject+date
$do_csv = (isset($_GET['export']) && $_GET['export'] === 'csv' && $subject_filter);

// Validate subject belongs to teacher and gather attendance
if ($subject_filter) {
    // Fetch subject details from src_db.subject by primary key
    $sub_stmt = $conn->prepare("SELECT * FROM subjects WHERE subject_id = ?");
    $sub_stmt->bind_param("i", $subject_filter);
    $sub_stmt->execute();
    $sub_res = $sub_stmt->get_result();

    if ($sub_res->num_rows === 0) {
        $error  = 'Selected subject not found or not assigned to you.';
        $totals = ['total' => 0, 'present' => 0, 'late' => 0, 'absent' => 0];
    } else {
        $subject_row = $sub_res->fetch_assoc();

        // Get students admitted to any schedule for this subject (src_db: admission + schedule)
        $stmt = $conn->prepare("
            SELECT DISTINCT
                st.student_id,
                st.first_name,
                st.middle_name,
                st.last_name,
                st.gender,
                st.rfid_number    AS barcode,
                st.profile_picture AS profile_pic,
                sec.section_name  AS section,
                yl.year_name      AS year_level
            FROM admissions adm
            JOIN schedule sc    ON adm.schedule_id   = sc.schedule_id
            JOIN students st    ON adm.student_id    = st.student_id
            LEFT JOIN sections sec     ON adm.section_id    = sec.section_id
            LEFT JOIN year_levels yl   ON adm.year_level_id = yl.year_id
            WHERE sc.subject_id = ? AND sc.employee_id = ?
            ORDER BY yl.year_name, sec.section_name, st.last_name, st.first_name
        ");
        $stmt->bind_param("ii", $subject_filter, $teacher_id_int);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            // Build a legacy-style full name field for existing UI and sorting
            $full_name_parts = [
                trim($row['first_name']  ?? ''),
                trim($row['middle_name'] ?? ''),
                trim($row['last_name']   ?? ''),
            ];
            $row['name'] = trim(implode(' ', array_filter($full_name_parts)));
            $students[$row['student_id']] = $row;
        }

        // Collect attendance dates for this subject using src_db.attendance
        $attendance_dates = [];
        $ad_stmt = $conn->prepare("
            SELECT DISTINCT a.attendance_date AS d
            FROM attendance a
            JOIN schedule sc ON a.schedule_id = sc.schedule_id
            WHERE sc.subject_id = ? AND sc.employee_id = ?
            ORDER BY d DESC
        ");
        $ad_stmt->bind_param("ii", $subject_filter, $teacher_id_int);
        $ad_stmt->execute();
        $ad_res = $ad_stmt->get_result();
        while ($ar = $ad_res->fetch_assoc()) {
            $attendance_dates[] = $ar['d'];
        }

        // Map attendance (time_in/time_out/status) per student for this subject+date
        if (!empty($students)) {
            $att_stmt = $conn->prepare("
                SELECT
                    adm.student_id,
                    a.time_in,
                    a.time_out,
                    a.status
                FROM attendance a
                JOIN admissions adm ON a.admission_id = adm.admission_id
                JOIN schedule sc   ON a.schedule_id  = sc.schedule_id
                WHERE sc.subject_id = ?
                  AND a.attendance_date = ?
                  AND sc.employee_id = ?
            ");
            $att_stmt->bind_param("isi", $subject_filter, $date_filter, $teacher_id_int);
            $att_stmt->execute();
            $att_res = $att_stmt->get_result();

            $attendance_by_student = [];
            while ($a = $att_res->fetch_assoc()) {
                $sid = $a['student_id'];
                $attendance_by_student[$sid] = [
                    'time_in'  => $a['time_in'],
                    'time_out' => $a['time_out'],
                    'status'   => $a['status'],
                ];
            }

            // Compute totals using the recorded status; default to Absent when no attendance row
            $totals = ['total' => count($students), 'present' => 0, 'late' => 0, 'absent' => 0];

            foreach ($students as $sid => $s) {
                if (isset($attendance_by_student[$sid])) {
                    $att = $attendance_by_student[$sid];
                    $status = $att['status'] ?: 'Absent';
                    $students[$sid]['first_time']  = $att['time_in'];
                    $students[$sid]['second_time'] = $att['time_out'];
                } else {
                    $status = 'Absent';
                    $students[$sid]['first_time']  = null;
                    $students[$sid]['second_time'] = null;
                }

                $students[$sid]['computed_status'] = $status;
                if ($status === 'Present') {
                    $totals['present']++;
                } elseif ($status === 'Late') {
                    $totals['late']++;
                } else {
                    $totals['absent']++;
                }
            }
        } else {
            $totals = ['total' => 0, 'present' => 0, 'late' => 0, 'absent' => 0];
        }
    }
} else {
    $totals = ['total' => 0, 'present' => 0, 'late' => 0, 'absent' => 0];
}

// Gender groups
$gender_groups = ['Male' => [], 'Female' => [], 'Other' => []];
foreach ($students as $sid => $s) {
    $g = isset($s['gender']) ? strtolower(trim($s['gender'])) : '';
    if ($g === 'male' || $g === 'm') {
        $gender_groups['Male'][$sid] = $s;
    } elseif ($g === 'female' || $g === 'f') {
        $gender_groups['Female'][$sid] = $s;
    } else {
        $gender_groups['Other'][$sid] = $s;
    }
}

// Sort each gender group by attendance status (Present -> Late -> Absent)
foreach ($gender_groups as $gender => &$group) {
    if (!empty($group)) {
        // Create sub-arrays for each status
        $present_students = [];
        $late_students = [];
        $absent_students = [];
        
        // Group students by status
        foreach ($group as $sid => $student) {
            $status = $student['computed_status'] ?? 'Absent';
            
            if ($status === 'Present') {
                $present_students[$sid] = $student;
            } elseif ($status === 'Late') {
                $late_students[$sid] = $student;
            } else {
                $absent_students[$sid] = $student;
            }
        }
        
        // Sort each status group by last name then first name
        uasort($present_students, function($a, $b) {
            $last_cmp = strcasecmp($a['last_name'], $b['last_name']);
            if ($last_cmp !== 0) return $last_cmp;
            return strcasecmp($a['first_name'], $b['first_name']);
        });
        uasort($late_students, function($a, $b) {
            $last_cmp = strcasecmp($a['last_name'], $b['last_name']);
            if ($last_cmp !== 0) return $last_cmp;
            return strcasecmp($a['first_name'], $b['first_name']);
        });
        uasort($absent_students, function($a, $b) {
            $last_cmp = strcasecmp($a['last_name'], $b['last_name']);
            if ($last_cmp !== 0) return $last_cmp;
            return strcasecmp($a['first_name'], $b['first_name']);
        });
        
        // Apply status filter and merge back in order: Present -> Late -> Absent
        $filtered_group = [];
        
        if ($status_filter === 'all') {
            $filtered_group = array_merge($present_students, $late_students, $absent_students);
        } elseif ($status_filter === 'present') {
            $filtered_group = $present_students;
        } elseif ($status_filter === 'late') {
            $filtered_group = $late_students;
        } elseif ($status_filter === 'absent') {
            $filtered_group = $absent_students;
        }
        
        $group = $filtered_group;
    }
}

// If CSV export was requested
if ($do_csv) {
    // Get subject name and format filename
    $subject_name = isset($subject_row) ? $subject_row['subject_name'] : 'Unknown_Subject';
    $subject_code = isset($subject_row) ? $subject_row['subject_code'] : '';
    
    // Clean subject name for filename (remove special characters)
    $clean_subject_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $subject_name);
    $clean_subject_code = preg_replace('/[^A-Za-z0-9_\-]/', '_', $subject_code);
    
    // Format date for filename (e.g., 2024-01-15 becomes Jan_15_2024)
    $formatted_date = date('M_d_Y', strtotime($date_filter));
    
    // Create filename: SubjectName_SubjectCode_Date.csv
    $filename = $clean_subject_name;
    if (!empty($clean_subject_code)) {
        $filename .= '_' . $clean_subject_code;
    }
    $filename .= '_' . $formatted_date . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Student ID', 'Name', 'Section', 'Year', 'Status', 'Time In', 'Time Out']);

    foreach ($students as $s) {
        fputcsv($output, [
            $s['student_id'],
            $s['name'],
            $s['section'],
            $s['year_level'],
            $s['computed_status'] ?? 'Absent',
            $s['first_time'] ? date('g:i A', strtotime($s['first_time'])) : '',
            $s['second_time'] ? date('g:i A', strtotime($s['second_time'])) : ''
        ]);
    }

    fclose($output);
    exit();
}

// Display subject info
$display_subject_name = '';
$display_subject_code = '';
$display_start_time   = '';
$present_deadline_str = '';
$late_deadline_str    = '';

if ($subject_filter && isset($subject_row)) {
    $display_subject_name = $subject_row['subject_name'] ?? '';
    $display_subject_code = $subject_row['subject_code'] ?? '';
    // No reliable start time at subject level in src_db; leave time-related fields blank for now
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Records - Teacher</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style> body { font-family: 'Inter', 'Segoe UI', Arial, sans-serif; } .sidebar-link.active, .sidebar-link:hover { background: linear-gradient(90deg, #4f8cff 0%, #a18fff 100%); color: #fff !important; } </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex">
    <!-- Shared sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-1 ml-80 min-h-screen main-content">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 mb-8">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="rounded-full bg-blue-50 border border-blue-100 p-3">
                            <i class="fas fa-clipboard-list text-blue-600 text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-extrabold text-gray-800">Class Record — <?= htmlspecialchars($display_subject_name ?: 'Attendance Records') ?></h1>
                            <div class="text-sm text-gray-500"><?= htmlspecialchars($display_subject_code) ?> • <?= htmlspecialchars($display_start_time) ?> • <?= htmlspecialchars($date_filter) ?></div>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-600">Total Students</p>
                        <p class="text-2xl font-bold text-blue-600"><?= $totals['total'] ?></p>
                    </div>
                </div>
                <?php if ($subject_filter): ?>
                    <div class="mt-4 text-sm text-gray-600">
                        <strong>Attendance Rules:</strong> 
                        Present: <?= $display_start_time ?> - <?= $present_deadline_str ?: '-' ?> | 
                        Late: <?= $present_deadline_str ? date('g:i A', strtotime($date_filter . ' ' . $subject_row['start_time']) + 16 * 60) : '-' ?> - <?= $late_deadline_str ?: '-' ?> | 
                        Absent: After <?= $late_deadline_str ?: '-' ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Realtime Recent Attendance removed (dashboard provides this) -->

                <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 mb-8">
                <style>
                    /* Print styles: hide sidebar and controls */
                    @media print {
                        .sidebar-link, .main-controls, .no-print { display: none !important; }
                        .main-content { margin-left: 0 !important; }
                        body { background: #fff !important; }
                    }
                        /* Table polish */
                        .class-table thead th { position: sticky; top: 0; background: #fff; z-index: 10; }
                        .badge-present { background: #ECFDF5; color: #065F46; padding: 4px 8px; border-radius: 6px; font-weight:600; }
                        .badge-late { background: #FFF7ED; color: #92400E; padding: 4px 8px; border-radius: 6px; font-weight:600; }
                        .badge-absent { background: #FEF2F2; color: #991B1B; padding: 4px 8px; border-radius: 6px; font-weight:600; }
                </style>

                <form method="get" class="flex flex-wrap gap-4 items-end main-controls">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Subject</label>
                        <select name="subject" class="block mt-1 px-3 py-2 border rounded">
                            <option value="">-- Select subject --</option>
                            <?php while ($sub = $subjects->fetch_assoc()): ?>
                                <option value="<?= $sub['id'] ?>" <?= $subject_filter == $sub['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sub['subject_name']) ?> (<?= htmlspecialchars($sub['subject_code']) ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Date</label>
                        <!-- Use a text input so Flatpickr can attach when a subject is selected -->
                        <input id="datePicker" type="text" name="date" value="<?= htmlspecialchars($date_filter) ?>" class="block mt-1 px-3 py-2 border rounded" autocomplete="off" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Status Filter</label>
                        <select name="status" class="block mt-1 px-3 py-2 border rounded">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Students</option>
                            <option value="present" <?= $status_filter === 'present' ? 'selected' : '' ?>>Present Only</option>
                            <option value="late" <?= $status_filter === 'late' ? 'selected' : '' ?>>Late Only</option>
                            <option value="absent" <?= $status_filter === 'absent' ? 'selected' : '' ?>>Absent Only</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Show</button>
                        <?php if ($subject_filter): ?>
                            <a href="?subject=<?= $subject_filter ?>&date=<?= htmlspecialchars($date_filter) ?>&status=<?= htmlspecialchars($status_filter) ?>&export=csv" class="ml-2 inline-block px-4 py-2 bg-green-600 text-white rounded">Export CSV</a>
                            <button type="button" onclick="window.print();" class="ml-2 inline-block px-4 py-2 bg-gray-600 text-white rounded no-print">Print</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if ($subject_filter): ?>
                <!-- Flatpickr assets and highlighting only when a subject is selected -->
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
                <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
                <style>
                    .flatpickr-day.has-attendance { background:#D1FAE5; color:#065F46 !important; border-radius:6px; }
                </style>
                <script>
                    (function(){
                        var attended = <?= json_encode(array_values($attendance_dates)) ?> || [];
                        // Debug - remove or comment out in production
                        console.log('attendance dates for subject <?= $subject_filter ?>:', attended);

                        function markDay(dateObj, dayElem){
                            if (!dayElem) return;
                            var y = dateObj.getFullYear();
                            var m = (dateObj.getMonth()+1).toString().padStart(2,'0');
                            var d = dateObj.getDate().toString().padStart(2,'0');
                            var iso = y+'-'+m+'-'+d;
                            if (attended.indexOf(iso) !== -1) {
                                dayElem.classList.add('has-attendance');
                            }
                        }

                        flatpickr('#datePicker', {
                            dateFormat: 'Y-m-d',
                            defaultDate: '<?= htmlspecialchars($date_filter) ?>',
                            onDayCreate: function(dObj, dStr, fpDay){
                                markDay(fpDay.dateObj, fpDay.node);
                            },
                            onChange: function(selectedDates, dateStr){
                                var f = document.querySelector('form');
                                if (f){
                                    var inp = document.querySelector('input[name="date"]');
                                    if (inp) inp.value = dateStr;
                                    f.submit();
                                }
                            }
                        });
                    })();
                </script>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg mb-6">
                    <div class="flex items-start gap-3">
                        <div class="flex-1">
                            <strong>Error:</strong>
                            <div><?= htmlspecialchars($error) ?></div>
                        </div>
                        <div>
                            <button onclick="this.parentNode.parentNode.parentNode.remove()" class="text-red-600 font-bold">Dismiss</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>


            <?php if (!$subject_filter): ?>
                <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 text-center">
                    <i class="fas fa-info-circle text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-600 mb-2">No subject selected</h3>
                    <p class="text-gray-500">Please choose a subject and date above to view attendance.</p>
                </div>
            <?php else: ?>
                <?php if ($totals['total'] === 0): ?>
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 text-center mb-6">
                        <i class="fas fa-user-slash text-4xl text-gray-400 mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-600 mb-2">No students assigned</h3>
                        <p class="text-gray-500">There are no students assigned to this subject.</p>
                    </div>
                <?php endif; ?>
                <?php foreach ($gender_groups as $glabel => $gstudents): ?>
                    <?php if (empty($gstudents)) continue; ?>
                    <?php
                        // compute group totals
                        $g_tot = ['total' => count($gstudents), 'present' => 0, 'late' => 0, 'absent' => 0, 'second_scan' => 0];
                        foreach ($gstudents as $gs) {
                            if (isset($gs['computed_status'])) {
                                if ($gs['computed_status'] === 'Present') $g_tot['present']++;
                                elseif ($gs['computed_status'] === 'Late') $g_tot['late']++;
                                else $g_tot['absent']++;
                            } else {
                                $g_tot['absent']++;
                            }
                            if (!empty($gs['second_time'])) $g_tot['second_scan']++;
                        }
                    ?>
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 mb-8">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h2 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($glabel) ?> — <?= htmlspecialchars($display_subject_name ?: 'Attendance') ?></h2>
                                <p class="text-sm text-gray-600">Date: <?= htmlspecialchars($date_filter) ?> • Students: <?= $g_tot['total'] ?></p>
                            </div>
                            <div>
                                    <div class="inline-flex rounded-lg overflow-hidden shadow-sm border border-gray-100">
                                    <div class="px-4 py-2 bg-green-50 text-green-700 border-r border-gray-100 text-center">
                                        <div class="text-xs">Present</div>
                                        <div class="text-xl font-bold"><?= $g_tot['present'] ?></div>
                                    </div>
                                    <div class="px-4 py-2 bg-yellow-50 text-yellow-800 border-r border-gray-100 text-center">
                                        <div class="text-xs">Late</div>
                                        <div class="text-xl font-bold"><?= $g_tot['late'] ?></div>
                                    </div>
                                    <div class="px-4 py-2 bg-red-50 text-red-700 text-center">
                                        <div class="text-xs">Absent</div>
                                        <div class="text-xl font-bold"><?= $g_tot['absent'] ?></div>
                                    </div>
                                        <div class="px-4 py-2 bg-blue-50 text-blue-700 text-center border-l border-gray-100">
                                            <div class="text-xs">Time Out</div>
                                            <div class="text-xl font-bold"><?= $g_tot['second_scan'] ?></div>
                                        </div>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 class-table">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">#</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Student ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Section</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Year</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Time In</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Time Out</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php $i = 1; foreach ($gstudents as $s): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600"><?= $i++ ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($s['student_id']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($s['name']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($s['section']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($s['year_level']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php if ($s['computed_status'] === 'Present'): ?>
                                                    <span class="badge-present">Present</span>
                                                <?php elseif ($s['computed_status'] === 'Late'): ?>
                                                    <span class="badge-late">Late</span>
                                                <?php else: ?>
                                                    <span class="badge-absent">Absent</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $s['first_time'] ? htmlspecialchars(date('g:i A', strtotime($s['first_time']))) : '-' ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $s['second_time'] ? htmlspecialchars(date('g:i A', strtotime($s['second_time']))) : '-' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <script>
        // Real-time attendance updates for teacher records
        let refreshInterval;
        
        function startRealTimeUpdates() {
            // Only enable real-time updates if a subject is selected
            const subjectFilter = <?= $subject_filter ? $subject_filter : 'null' ?>;
            const dateFilter = '<?= htmlspecialchars($date_filter) ?>';
            
            if (subjectFilter && dateFilter) {
                refreshInterval = setInterval(function() {
                    // Check if page is visible to avoid unnecessary requests
                    if (!document.hidden) {
                        refreshAttendanceData();
                    }
                }, 5000); // Refresh every 5 seconds
                
                console.log('Real-time attendance updates started for subject:', subjectFilter);
            }
        }
        
        function refreshAttendanceData() {
            const subjectFilter = <?= $subject_filter ? $subject_filter : 'null' ?>;
            const dateFilter = '<?= htmlspecialchars($date_filter) ?>';
            
            if (!subjectFilter) return;
            
            // Create a hidden form to refresh the page with current filters
            const form = document.createElement('form');
            form.method = 'GET';
            form.style.display = 'none';
            
            const subjectInput = document.createElement('input');
            subjectInput.name = 'subject';
            subjectInput.value = subjectFilter;
            form.appendChild(subjectInput);
            
            const dateInput = document.createElement('input');
            dateInput.name = 'date';
            dateInput.value = dateFilter;
            form.appendChild(dateInput);
            
            document.body.appendChild(form);
            
            // Use fetch to get updated data without full page reload
            const params = new URLSearchParams();
            params.append('subject', subjectFilter);
            params.append('date', dateFilter);
            params.append('ajax', '1');
            
            fetch('?' + params.toString())
                .then(response => response.text())
                .then(html => {
                    // Update only the statistics without full reload
                    updateAttendanceStats();
                })
                .catch(error => {
                    console.error('Error refreshing attendance data:', error);
                });
                
            document.body.removeChild(form);
        }
        
        function updateAttendanceStats() {
            // Update the total counts in the header
            const subjectFilter = <?= $subject_filter ? $subject_filter : 'null' ?>;
            const dateFilter = '<?= htmlspecialchars($date_filter) ?>';
            
            if (!subjectFilter) return;
            
            // Fetch fresh stats via AJAX
            fetch('../ajax/teacher_attendance_stats.php?subject=' + subjectFilter + '&date=' + dateFilter)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update total students count
                        const totalEl = document.querySelector('.text-2xl.font-bold.text-blue-600');
                        if (totalEl) {
                            totalEl.textContent = data.totals.total;
                        }
                        
                        // Update individual group stats
                        data.groups.forEach(group => {
                            const groupStats = document.querySelectorAll('.inline-flex.rounded-lg.overflow-hidden');
                            groupStats.forEach(statGroup => {
                                const presentEl = statGroup.querySelector('.bg-green-50 .text-xl.font-bold');
                                const lateEl = statGroup.querySelector('.bg-yellow-50 .text-xl.font-bold');
                                const absentEl = statGroup.querySelector('.bg-red-50 .text-xl.font-bold');
                                const timeoutEl = statGroup.querySelector('.bg-blue-50 .text-xl.font-bold');
                                
                                if (presentEl) presentEl.textContent = group.present;
                                if (lateEl) lateEl.textContent = group.late;
                                if (absentEl) absentEl.textContent = group.absent;
                                if (timeoutEl) timeoutEl.textContent = group.timeout;
                            });
                        });
                        
                        console.log('Attendance stats updated:', data.totals);
                    }
                })
                .catch(error => {
                    console.error('Error updating attendance stats:', error);
                });
        }
        
        // Start real-time updates when page loads
        document.addEventListener('DOMContentLoaded', function() {
            startRealTimeUpdates();
        });
        
        // Stop updates when page is unloaded
        window.addEventListener('beforeunload', function() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });
        
        // Pause updates when page is hidden, resume when visible
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                if (refreshInterval) {
                    clearInterval(refreshInterval);
                }
            } else {
                startRealTimeUpdates();
            }
        });
    </script>
</body>
</html>
<!-- Real-time attendance updates enabled for teacher records -->



