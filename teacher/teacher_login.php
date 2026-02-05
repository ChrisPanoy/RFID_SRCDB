<?php
session_start();
include '../includes/db.php';

// NOTE: allow logging in another teacher even if one is already active.
// Previous behavior redirected to the dashboard when an active teacher existed,
// which prevented adding additional teacher sessions in the same browser.

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle email/password login using employees table
    if (isset($_POST['email_login'])) {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
    
        if (empty($email) || empty($password)) {
            $error = "Please enter both Email and Password";
        } else {
            // Check if employee exists with faculty/dean role - Join with roles table
            $stmt = $conn->prepare("SELECT e.*, r.role_name 
                                    FROM employees e 
                                    JOIN roles r ON e.role_id = r.role_id 
                                    WHERE e.email = ? AND LOWER(r.role_name) IN ('dean','faculty')");
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

        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">
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
        // Auto-focus on email field initially
        document.getElementById('email').focus();
    </script>
</body>
</html>
