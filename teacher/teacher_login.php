<?php
session_start();
include '../includes/db.php';

// NOTE: allow logging in another teacher even if one is already active.
// Previous behavior redirected to the dashboard when an active teacher existed,
// which prevented adding additional teacher sessions in the same browser.

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle barcode login (using employees table; barcode treated as employee_id for now)
    if (isset($_POST['barcode_login'])) {
        $teacher_id = trim($_POST['teacher_barcode']);
        
        if (empty($teacher_id)) {
            $error = "Please scan your Teacher ID barcode or enter your Teacher ID";
        } else {
            // Query employees by employee_id and ensure they are faculty/dean
            $stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ? AND role IN ('Dean','Faculty')");
            $emp_id_int = (int)$teacher_id;
            $stmt->bind_param("i", $emp_id_int);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $teacher_data = $result->fetch_assoc();

                // Build teacher session record (keeping legacy keys used by other pages)
                $teacher_record = [
                    'teacher_id' => (string)$teacher_data['employee_id'],
                    'teacher_name' => $teacher_data['firstname'] . ' ' . $teacher_data['lastname'],
                    'teacher_email' => $teacher_data['email'],
                    'teacher_department' => ''
                ];

                // Ensure session structure exists for multiple teachers
                if (!isset($_SESSION['teachers']) || !is_array($_SESSION['teachers'])) {
                    $_SESSION['teachers'] = [];
                }

                // Add or replace this teacher's entry
                $key = (string)$teacher_data['employee_id'];
                $_SESSION['teachers'][$key] = $teacher_record;

                // Set active teacher id/key for backward compatibility
                $_SESSION['active_teacher_id'] = $key;

                // Also set legacy single-teacher session variables for existing pages
                $_SESSION['teacher_id'] = $teacher_record['teacher_id'];
                $_SESSION['teacher_name'] = $teacher_record['teacher_name'];
                $_SESSION['teacher_email'] = $teacher_record['teacher_email'];
                $_SESSION['teacher_department'] = $teacher_record['teacher_department'];

                header("Location: teacher_dashboard.php");
                exit();
            } else {
                $error = "Teacher ID not found or not allowed. Please contact administrator.";
            }
            $stmt->close();
        }
    }
    // Handle email/password login using employees table
    elseif (isset($_POST['email_login'])) {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
    
        if (empty($email) || empty($password)) {
            $error = "Please enter both Email and Password";
        } else {
            // Check if employee exists with faculty/dean role
            $stmt = $conn->prepare("SELECT * FROM employees WHERE email = ? AND role IN ('Dean','Faculty')");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();

                if (!password_verify($password, $user['password'])) {
                    $error = "Invalid password. Please try again.";
                } else {
                    // Build teacher session record from employees row
                    $teacher_id = (string)$user['employee_id'];
                    $teacher_record = [
                        'teacher_id' => $teacher_id,
                        'teacher_name' => $user['firstname'] . ' ' . $user['lastname'],
                        'teacher_email' => $email,
                        'teacher_department' => ''
                    ];

                    // Ensure session structure exists for multiple teachers
                    if (!isset($_SESSION['teachers']) || !is_array($_SESSION['teachers'])) {
                        $_SESSION['teachers'] = [];
                    }

                    // Add or replace this teacher's entry
                    $key = $teacher_id !== '' ? $teacher_id : md5(strtolower($email));
                    $_SESSION['teachers'][$key] = $teacher_record;

                    // Set active teacher id/key for backward compatibility
                    $_SESSION['active_teacher_id'] = $key;

                    // Also set legacy single-teacher session variables for existing pages
                    $_SESSION['teacher_id'] = $teacher_record['teacher_id'];
                    $_SESSION['teacher_name'] = $teacher_record['teacher_name'];
                    $_SESSION['teacher_email'] = $teacher_record['teacher_email'];
                    $_SESSION['teacher_department'] = $teacher_record['teacher_department'];

                    header("Location: teacher_dashboard.php");
                    exit();
                }
            } else {
                $error = "Email not found or not allowed. Please contact administrator.";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Login - Attendance System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    /* Shared site utilities for consistency */
    @keyframes fadeUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-up { animation: fadeUp 700ms cubic-bezier(.2,.8,.2,1) both; }
    @media (prefers-reduced-motion: reduce) { .animate-fade-up { animation: none !important; } }
    .hero-card { width: 100%; max-width: 720px; margin-left: auto; margin-right: auto; }
    @media (max-width: 420px) { .hero-card { padding-left: 1rem; padding-right: 1rem; } }
    
    /* Tab styling */
    .flex-1 { flex: 1 1 0%; }
    .transition-colors { transition-property: color, background-color, border-color, text-decoration-color, fill, stroke; }
    .duration-200 { transition-duration: 200ms; }
    </style>
</head>
<body class="bg-gradient-to-br from-sky-100 via-sky-200 to-white min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-2xl shadow-lg w-full max-w-md md:max-w-lg lg:max-w-xl border border-gray-100">
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-gradient-to-r from-sky-500 to-sky-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-md">
                <img src="../assets/img/logo.png" alt="Description" width="350" height="350" >
            </div>
            <h1 class="text-2xl font-bold text-gray-800">Faculty Login</h1>
            <p class="text-gray-600 mt-2">Access your dashboard</p>
        </div>
        
        <!-- Login Method Toggle -->
        <div class="flex mb-6 bg-gray-100 rounded-lg p-1">
            <button type="button" id="emailTab" class="flex-1 py-2 px-4 text-sm font-medium rounded-md transition-colors duration-200 bg-white text-sky-600 shadow-sm">
                <i class="fas fa-envelope mr-2"></i>Email Login
            </button>
            <button type="button" id="barcodeTab" class="flex-1 py-2 px-4 text-sm font-medium rounded-md transition-colors duration-200 text-gray-600 hover:text-sky-600">
                <i class="fas fa-qrcode mr-2"></i>Barcode Scan
            </button>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Email Login Form -->
        <form method="POST" id="emailForm" class="space-y-6">
            <input type="hidden" name="email_login" value="1">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-envelope mr-2"></i>Email
                </label>
                <input type="email" id="email" name="email" 
                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-400 focus:border-transparent"
                       placeholder="Enter your email" required autofocus>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-lock mr-2"></i>Password
                </label>
                <input type="password" id="password" name="password" 
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-400 focus:border-transparent"
                       placeholder="Enter your password" required>
            </div>

            <button type="submit" 
                class="w-full bg-gradient-to-r from-sky-500 to-sky-600 text-white py-3 px-4 rounded-lg font-semibold hover:from-sky-600 hover:to-sky-700 transition duration-300 transform hover:scale-105 shadow-sm">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login
                </button>
        </form>
        
        <!-- Barcode Login Form -->
        <form method="POST" id="barcodeForm" class="space-y-6" style="display: none;">
            <input type="hidden" name="barcode_login" value="1">
            <div>
                <label for="teacher_barcode" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-qrcode mr-2"></i>Faculty ID Barcode
                </label>
                <input type="text" id="teacher_barcode" name="teacher_barcode" 
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-400 focus:border-transparent"
                       placeholder="Scan your Teacher ID barcode" required>
                <small class="text-gray-600 text-sm mt-1 block">
                    <i class="fas fa-info-circle mr-1"></i>
                    Scan your Faculty ID barcode or type your Faculty ID manually
                </small>
            </div>

            <button type="submit" 
                class="w-full bg-gradient-to-r from-green-500 to-green-600 text-white py-3 px-4 rounded-lg font-semibold hover:from-green-600 hover:to-green-700 transition duration-300 transform hover:scale-105 shadow-sm">
                    <i class="fas fa-qrcode mr-2"></i>Login with Barcode
                </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600" id="loginHelp">
                <i class="fas fa-info-circle mr-1"></i>
                Use the email and password provided by administrator
            </p>
        </div>

        <div class="mt-8 text-center">
            <a href="../index.php" class="text-blue-600 hover:text-blue-800 text-sm">
                <i class="fas fa-arrow-left mr-1"></i>Back to Main Page
            </a>
        </div>
    </div>

    <script>
        // Login method toggle functionality
        const emailTab = document.getElementById('emailTab');
        const barcodeTab = document.getElementById('barcodeTab');
        const emailForm = document.getElementById('emailForm');
        const barcodeForm = document.getElementById('barcodeForm');
        const loginHelp = document.getElementById('loginHelp');
        
        let barcodeTimeout;
        
        // Tab switching
        emailTab.addEventListener('click', function() {
            // Switch to email login
            emailTab.classList.add('bg-white', 'text-sky-600', 'shadow-sm');
            emailTab.classList.remove('text-gray-600');
            barcodeTab.classList.remove('bg-white', 'text-sky-600', 'shadow-sm');
            barcodeTab.classList.add('text-gray-600');
            
            emailForm.style.display = 'block';
            barcodeForm.style.display = 'none';
            
            loginHelp.innerHTML = '<i class="fas fa-info-circle mr-1"></i>Use the email and password provided by administrator';
            
            document.getElementById('email').focus();
        });
        
        barcodeTab.addEventListener('click', function() {
            // Switch to barcode login
            barcodeTab.classList.add('bg-white', 'text-sky-600', 'shadow-sm');
            barcodeTab.classList.remove('text-gray-600');
            emailTab.classList.remove('bg-white', 'text-sky-600', 'shadow-sm');
            emailTab.classList.add('text-gray-600');
            
            emailForm.style.display = 'none';
            barcodeForm.style.display = 'block';
            
            loginHelp.innerHTML = '';
            
            document.getElementById('teacher_barcode').focus();
        });
        
        // Auto-submit barcode form after scanning (with delay)
        document.getElementById('teacher_barcode').addEventListener('input', function() {
            const value = this.value.trim();
            if (value.length > 0) {
                // Clear any existing timeout
                if (barcodeTimeout) {
                    clearTimeout(barcodeTimeout);
                }
                
                // Set timeout to auto-submit after 500ms of no input
                barcodeTimeout = setTimeout(function() {
                    if (document.getElementById('teacher_barcode').value.trim().length > 0) {
                        document.getElementById('barcodeForm').submit();
                    }
                }, 500);
            }
        });
        
        // Auto-focus on email field initially
        document.getElementById('email').focus();
    </script>
</body>
</html> 


