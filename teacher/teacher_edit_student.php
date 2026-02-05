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

// Make sure student_id is provided
if (!isset($_GET['student_id'])) {
    header("Location: teacher_students.php");
    exit();
}

$student_id = $_GET['student_id'];
$message = "";
$message_type = "";

// Get student details joined with admission for active session
$ay_id  = (int)($_SESSION['active_ay_id'] ?? 0);
$sem_id = (int)($_SESSION['active_sem_id'] ?? 0);

$stmt = $conn->prepare("
    SELECT st.*, adm.section_id, adm.year_level_id, sec.section_name, yl.year_name
    FROM students st
    LEFT JOIN admissions adm ON st.student_id = adm.student_id 
        AND adm.academic_year_id = ? 
        AND adm.semester_id = ?
    LEFT JOIN sections sec ON adm.section_id = sec.section_id
    LEFT JOIN year_levels yl ON adm.year_level_id = yl.year_id
    WHERE st.student_id = ?
    LIMIT 1
");
$stmt->bind_param("iis", $ay_id, $sem_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    header("Location: teacher_students.php");
    exit();
}

// Build full name from first/middle/last
$first_name  = $student['first_name']  ?? '';
$middle_name = $student['middle_name'] ?? '';
$last_name   = $student['last_name']   ?? '';
$full_name_parts = [trim($last_name), trim($first_name), trim($middle_name)];
$student['name'] = trim(implode(', ', array_filter([$full_name_parts[0]])) . ' ' . implode(' ', array_filter([$full_name_parts[1], $full_name_parts[2]])));

// Map barcode from rfid_number for display
$student['barcode'] = $student['rfid_number'] ?? '';

// Load all sections for dropdown
$sections = [];
$sec_res = $conn->query("SELECT section_id, section_name FROM sections ORDER BY section_name ASC");
while ($sec_row = $sec_res->fetch_assoc()) {
    $sections[] = $sec_row;
}

// Load all year levels for dropdown
$year_levels = [];
$yl_res = $conn->query("SELECT year_id, year_name FROM year_levels ORDER BY year_id ASC");
while ($yl_row = $yl_res->fetch_assoc()) {
    $year_levels[] = $yl_row;
}

// Load labs for dropdown (facility table)
$labs = [];
$lab_stmt = $conn->prepare("SELECT lab_id, lab_name FROM facilities ORDER BY lab_name ASC");
if ($lab_stmt && $lab_stmt->execute()) {
    $lab_res = $lab_stmt->get_result();
    while ($lab_row = $lab_res->fetch_assoc()) {
        $labs[] = $lab_row;
    }
}
if ($lab_stmt) {
    $lab_stmt->close();
}

// Determine selected lab (from GET/POST, default first lab if available)
$selected_lab_id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_lab_id = isset($_POST['lab_id']) ? (int)$_POST['lab_id'] : 0;
} else {
    $selected_lab_id = isset($_GET['lab_id']) ? (int)$_GET['lab_id'] : 0;
}
if ($selected_lab_id === 0 && !empty($labs)) {
    $selected_lab_id = (int)$labs[0]['lab_id'];
}

// Helper: load current pc_number for this student + selected lab from pc_assignment
$current_pc_number = '';
if ($selected_lab_id > 0) {
    $pc_stmt = $conn->prepare("SELECT pc_number FROM pc_assignment WHERE student_id = ? AND lab_id = ? LIMIT 1");
    if ($pc_stmt) {
        $pc_stmt->bind_param("si", $student_id, $selected_lab_id);

        if ($pc_stmt->execute()) {
            $pc_res = $pc_stmt->get_result();
            if ($pc_row = $pc_res->fetch_assoc()) {
                $current_pc_number = $pc_row['pc_number'] ?? '';
            }
        }
        $pc_stmt->close();
    }
}

// Update when form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pc_number = trim($_POST['pc_number'] ?? '');
    $section_id = isset($_POST['section_id']) ? (int)$_POST['section_id'] : 0;
    $year_level_id = isset($_POST['year_level_id']) ? (int)$_POST['year_level_id'] : 0;

    // Update Year and Section in admission table
    if ($section_id > 0 && $year_level_id > 0) {
        $upd_adm = $conn->prepare("UPDATE admissions SET section_id = ?, year_level_id = ? WHERE student_id = ? AND academic_year_id = ? AND semester_id = ?");
        $upd_adm->bind_param("iisii", $section_id, $year_level_id, $student_id, $ay_id, $sem_id);
        if ($upd_adm->execute()) {
            // Update local student array for display after update
            $student['section_id'] = $section_id;
            $student['year_level_id'] = $year_level_id;
        }
        $upd_adm->close();
    }

    if ($selected_lab_id <= 0) {
        $message = "❌ Please select a lab before assigning a PC.";
        $message_type = "error";
    } else {
        // Ensure 1 PC per student per lab in pc_assignment
        if ($pc_number !== '') {
            // Check if this PC is already assigned to another student in the same lab
            $check_stmt = $conn->prepare("SELECT student_id FROM pc_assignment WHERE pc_number = ? AND lab_id = ? AND student_id != ? LIMIT 1");
            if ($check_stmt) {
                $check_stmt->bind_param("sis", $pc_number, $selected_lab_id, $student_id);

                $check_stmt->execute();
                $check_res = $check_stmt->get_result();

                if ($check_res && $check_res->num_rows > 0) {
                    $message = "❌ This PC is already assigned to another student in this lab.";
                    $message_type = "error";
                } else {
                    // Upsert into pc_assignment
                    $existing_stmt = $conn->prepare("SELECT pc_assignment_id FROM pc_assignment WHERE student_id = ? AND lab_id = ? LIMIT 1");
                    if ($existing_stmt) {
                        $existing_stmt->bind_param("si", $student_id, $selected_lab_id);

                        $existing_stmt->execute();
                        $existing_res = $existing_stmt->get_result();
                        $existing = $existing_res ? $existing_res->fetch_assoc() : null;
                        $existing_stmt->close();

                        if ($existing) {
                            $update_stmt = $conn->prepare("UPDATE pc_assignment SET pc_number = ?, date_assigned = CURDATE() WHERE pc_assignment_id = ?");
                            if ($update_stmt) {
                                $update_stmt->bind_param("si", $pc_number, $existing['pc_assignment_id']);

                                if ($update_stmt->execute()) {
                                    $message = "✅ PC number updated successfully for this lab.";
                                    $message_type = "success";
                                    $current_pc_number = $pc_number;
                                } else {
                                    $message = "❌ Update failed: " . $conn->error;
                                    $message_type = "error";
                                }
                                $update_stmt->close();
                            }
                        } else {
                            $insert_stmt = $conn->prepare("INSERT INTO pc_assignment (student_id, lab_id, pc_number, date_assigned) VALUES (?, ?, ?, CURDATE())");
                            if ($insert_stmt) {
                                $insert_stmt->bind_param("sis", $student_id, $selected_lab_id, $pc_number);

                                if ($insert_stmt->execute()) {
                                    $message = "✅ PC number assigned successfully for this lab.";
                                    $message_type = "success";
                                    $current_pc_number = $pc_number;
                                } else {
                                    $message = "❌ Insert failed: " . $conn->error;
                                    $message_type = "error";
                                }
                                $insert_stmt->close();
                            }
                        }
                    }
                }
                $check_stmt->close();
            }
        } else {
            // Empty PC number means unassign PC for this student in this lab
            $delete_stmt = $conn->prepare("DELETE FROM pc_assignment WHERE student_id = ? AND lab_id = ?");
            if ($delete_stmt) {
                $delete_stmt->bind_param("si", $student_id, $selected_lab_id);

                if ($delete_stmt->execute()) {
                    $message = "✅ PC unassigned for this lab.";
                    $message_type = "success";
                    $current_pc_number = '';
                } else {
                    $message = "❌ Unassign failed: " . $conn->error;
                    $message_type = "error";
                }
                $delete_stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student - Teacher Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { 
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif; 
            background-color: #f5f7fa;
        }
        .sidebar-link.active, .sidebar-link:hover { 
            background: linear-gradient(90deg, #4f8cff 0%, #a18fff 100%); 
            color: #fff !important; 
        }
        .sidebar-link i { min-width: 1.5rem; }
        
        /* Mobile Responsive Styles */
        @media (max-width: 1024px) {
            .sidebar { 
                left: -280px; 
                width: 280px;
                transition: left 0.3s ease;
                z-index: 1000;
            }
            .sidebar.open { 
                left: 0; 
                box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
            }
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            .overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }
            .overlay.active {
                display: block;
            }
        }
        
        @media (max-width: 640px) {
            .container-padding {
                padding-left: 1rem;
                padding-right: 1rem;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            .action-buttons {
                flex-direction: column;
                gap: 1rem;
            }
            .action-buttons a, .action-buttons button {
                width: 100%;
                justify-content: center;
            }
        }
        
        .readonly-field {
            background-color: #f9fafb !important;
            color: #6b7280 !important;
            border-color: #d1d5db !important;
            cursor: not-allowed !important;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Mobile Header -->
    <div class="lg:hidden bg-white shadow-sm border-b border-gray-200 p-4 flex items-center justify-between sticky top-0 z-50">
        <div class="flex items-center">
            <button onclick="toggleSidebar()" class="p-2 rounded-md text-gray-600 hover:bg-gray-100 mr-2">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <h1 class="text-xl font-bold text-gray-800">Edit Student</h1>
        </div>
        <div class="flex items-center">
            <span class="text-sm font-medium text-gray-700 mr-2"><?php echo htmlspecialchars($teacher_name); ?></span>
            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                <?php echo strtoupper(substr($teacher_name, 0, 1)); ?>
            </div>
        </div>
    </div>

    <!-- Overlay for mobile sidebar -->
    <div id="overlay" class="overlay lg:hidden" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <div class="main-content lg:ml-72 min-h-screen">
        <div class="max-w-4xl mx-auto container-padding py-8">
            <!-- Page Header -->
            <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6 border border-gray-200 mb-8">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between">
                    <div class="mb-4 sm:mb-0">
                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-edit text-blue-500"></i>Edit Student
                        </h1>
                        <p class="text-gray-600 mt-2">Update PC number assignment</p>
                    </div>
                    <div class="text-right">
                        <a href="teacher_students.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors w-full sm:w-auto justify-center">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Students
                        </a>
                    </div>
                </div>
            </div>

            <!-- Edit Form -->
            <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6 border border-gray-200">
                <?php if ($message): ?>
                    <div class="mb-6 p-4 rounded-lg text-sm font-semibold 
                        <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>">
                        <div class="flex items-center">
                            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
                            <?= $message ?>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <input type="hidden" name="lab_id" value="<?= htmlspecialchars($selected_lab_id) ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 form-grid">
                        <!-- Student ID (Read-only) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-id-card mr-1"></i>Student ID
                            </label>
                            <input type="text" value="<?= htmlspecialchars($student['student_id']) ?>" readonly
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg readonly-field">
                        </div>

                        <!-- Name (Read-only) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-user mr-1"></i>Full Name
                            </label>
                            <input type="text" value="<?= htmlspecialchars($student['name']) ?>" readonly
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg readonly-field">
                        </div>

                        <!-- Section (Editable) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-users mr-1"></i>Section
                            </label>
                            <select name="section_id" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select Section</option>
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?= $sec['section_id'] ?>" <?= (int)$student['section_id'] === (int)$sec['section_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sec['section_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Year Level (Editable) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-graduation-cap mr-1"></i>Year Level
                            </label>
                            <select name="year_level_id" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select Year Level</option>
                                <?php foreach ($year_levels as $yl): ?>
                                    <option value="<?= $yl['year_id'] ?>" <?= (int)$student['year_level_id'] === (int)$yl['year_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($yl['year_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Course (Read-only) -->
                

                        <!-- Barcode (Read-only) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-barcode mr-1"></i>RFID Number
                            </label>
                            <input type="text" value="<?= htmlspecialchars($student['barcode']) ?>" readonly
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg readonly-field">
                        </div>

                        <!-- Lab selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-network-wired mr-1"></i>Lab
                            </label>
                            <select name="lab_id" onchange="window.location.href='teacher_edit_student.php?student_id=<?= urlencode($student_id) ?>&lab_id=' + this.value;" 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <?php foreach ($labs as $lab): ?>
                                    <option value="<?= htmlspecialchars($lab['lab_id']) ?>" <?= (int)$lab['lab_id'] === (int)$selected_lab_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($lab['lab_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Select the lab where this PC assignment applies.</p>
                        </div>

                        <!-- PC Number (Editable per lab) -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-desktop mr-1"></i>PC Number (for selected lab)
                            </label>
                            <input type="text" name="pc_number" value="<?= htmlspecialchars($current_pc_number) ?>" 
                                   placeholder="Enter PC number for this lab"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <p class="text-xs text-gray-500 mt-1">One PC per student per lab. Leave empty to unassign PC for this lab.</p>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-between items-center pt-6 border-t border-gray-200 action-buttons">
                        <a href="teacher_students.php" class="inline-flex items-center px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-save mr-2"></i>Update PC Number
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Information Box -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-6">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                    <div>
                        <h3 class="font-semibold text-blue-800 mb-1">Information</h3>
                        <p class="text-blue-700 text-sm">
                            Teachers can now update the Section, Year Level, and PC Number assignment for this student. Student ID, Name, and RFID Number remain fixed.
                            If you encounter any issues, please contact the system administrator.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mobile sidebar toggle function
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('overlay');
            
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
            
            // Prevent body scrolling when sidebar is open on mobile
            if (sidebar.classList.contains('open')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = 'auto';
            }
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const menuButton = document.querySelector('button[onclick="toggleSidebar()"]');
            const overlay = document.getElementById('overlay');
            
            if (window.innerWidth <= 1024 && 
                !sidebar.contains(event.target) && 
                !menuButton.contains(event.target) &&
                overlay.classList.contains('active')) {
                toggleSidebar();
            }
        });

        // Adjust layout on window resize
        window.addEventListener('resize', function() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('overlay');
            
            if (window.innerWidth > 1024) {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        });
    </script>
</body>
</html>