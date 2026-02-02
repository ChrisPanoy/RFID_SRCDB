<?php
if (!isset($teacher_name)) {
    $teacher_name = isset($_SESSION['teacher_name']) ? $_SESSION['teacher_name'] : '';
}

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentDir = basename(dirname($_SERVER['PHP_SELF']));
$isSubfolder = in_array($currentDir, ['admin', 'student', 'teacher', 'ajax', 'includes']);
$basePath = $isSubfolder ? '../' : '';

// Ensure DB connection exists; include if missing
if (!isset($conn)) {
    $dbPath = __DIR__ . '/db.php';
    if (file_exists($dbPath)) {
        include_once $dbPath;
    }
}

// Fetch profile picture for logged-in teacher (if any) from employees table
$sidebarProfilePic = $basePath . 'assets/img/logo.png';
$sidebarTeacherName = htmlspecialchars($teacher_name);
if (isset($_SESSION['teacher_id']) && isset($conn)) {
    $tId = (int)$_SESSION['teacher_id']; // employees.employee_id
    $pstmt = $conn->prepare("SELECT profile_pic, firstname, lastname FROM employees WHERE employee_id = ? LIMIT 1");
    if ($pstmt) {
        $pstmt->bind_param('i', $tId);
        $pstmt->execute();
        $pstmt->bind_result($db_profile_pic, $db_firstname, $db_lastname);
        if ($pstmt->fetch()) {
            if (!empty($db_profile_pic)) {
                $sidebarProfilePic = $basePath . 'assets/img/' . $db_profile_pic;
            }
            $fullName = trim(($db_firstname ?? '') . ' ' . ($db_lastname ?? ''));
            if (!empty($fullName)) {
                $sidebarTeacherName = htmlspecialchars($fullName);
            }
        }
        $pstmt->close();
    }
}

?>

<!-- Wider Sidebar -->
<aside class="sidebar fixed top-0 left-0 h-full w-80 bg-white shadow-lg z-30 flex flex-col transition-all duration-300 overflow-y-auto">
    <div class="flex flex-col items-center py-8 border-b border-gray-200">
        <div class="w-24 h-24 rounded-full overflow-hidden border-4 border-blue-300 mb-2 bg-blue-100">
            <img src="<?= $sidebarProfilePic ?>" alt="profile" class="w-full h-full object-cover" onerror="this.onerror=null;this.src='<?= $basePath ?>assets/img/logo.png'">
        </div>
        <div class="mt-2 text-center">
            <div class="font-bold text-xl text-gray-800"><?= $sidebarTeacherName ?></div>
            <div class="text-sm text-gray-500">Faculty</div>
        </div>
    </div>
    <nav class="flex-1 flex flex-col gap-2 mt-6 px-4">
        <a href="<?= $basePath ?>teacher/teacher_dashboard.php" class="sidebar-link px-6 py-4 rounded-xl flex items-center gap-3 text-gray-700 font-semibold hover:bg-blue-500 hover:text-white transition-all <?php if(basename($_SERVER['PHP_SELF'])=='teacher_dashboard.php') echo 'bg-blue-600 text-white'; ?>">
            <i class="fas fa-tachometer-alt text-xl" style="color: blue;"></i> 
            <span class="text-lg">Dashboard</span>
        </a>
        <a href="<?= $basePath ?>teacher/teacher_scan.php" class="sidebar-link px-6 py-4 rounded-xl flex items-center gap-3 text-gray-700 font-semibold hover:bg-blue-500 hover:text-white transition-all <?php if(basename($_SERVER['PHP_SELF'])=='teacher_scan.php') echo 'bg-blue-600 text-white'; ?>">
            <i class="fas fa-barcode text-xl" style="color: blue;"></i> 
            <span class="text-lg">Scan Attendance</span>
        </a>
        <a href="<?= $basePath ?>teacher/teacher_students.php" class="sidebar-link px-6 py-4 rounded-xl flex items-center gap-3 text-gray-700 font-semibold hover:bg-blue-500 hover:text-white transition-all <?php if(basename($_SERVER['PHP_SELF'])=='teacher_students.php') echo 'bg-blue-600 text-white'; ?>">
            <i class="fas fa-user-graduate text-xl" style="color: blue;"></i> 
            <span class="text-lg">View Students</span>
        </a>

        <a href="<?= $basePath ?>teacher/teacher_attendance_records.php" class="sidebar-link px-6 py-4 rounded-xl flex items-center gap-3 text-gray-700 font-semibold hover:bg-blue-500 hover:text-white transition-all <?php if(basename($_SERVER['PHP_SELF'])=='teacher_attendance_records.php') echo 'bg-blue-600 text-white'; ?>">
            <i class="fas fa-chart-bar text-xl" style="color: blue;"></i> 
            <span class="text-lg">Attendance Records</span>
        </a>

        <a href="<?= $basePath ?>teacher/teacher_dismissal.php" class="sidebar-link px-6 py-4 rounded-xl flex items-center gap-3 text-gray-700 font-semibold hover:bg-blue-500 hover:text-white transition-all <?php if(basename($_SERVER['PHP_SELF'])=='teacher_dismissal.php') echo 'bg-blue-600 text-white'; ?>">
            <i class="fas fa-door-open text-xl" style="color: blue;"></i> 
            <span class="text-lg">Class Dismissal</span>
        </a>

        <div class="mt-auto px-4 pb-8">
            <a href="<?= $basePath ?>teacher/teacher_logout.php" class="sidebar-link px-6 py-4 rounded-xl flex items-center gap-3 text-red-600 font-bold hover:bg-red-50 transition-all border border-red-100">
                <i class="fas fa-sign-out-alt text-xl"></i> 
                <span class="text-lg">Logout System</span>
            </a>
        </div>
    </nav>
</aside>

<!-- Mobile Menu Toggle Button (Add this to your main content area) -->
<button id="mobileMenuToggle" class="lg:hidden fixed top-4 left-4 z-40 bg-blue-600 text-white p-3 rounded-lg shadow-lg">
    <i class="fas fa-bars text-xl"></i>
</button>

<!-- Mobile Overlay -->
<div id="mobileOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-20 hidden"></div>

<!-- Adjust main content margin to match wider sidebar -->


<script>
// Mobile sidebar toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const mobileOverlay = document.getElementById('mobileOverlay');
    const mainContent = document.querySelector('.main-content');
    
    // Toggle sidebar
    function toggleSidebar() {
        sidebar.classList.toggle('open');
        mobileOverlay.classList.toggle('show');
        document.body.classList.toggle('sidebar-open');
    }
    
    // Toggle button event
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', toggleSidebar);
    }
    
    // Close sidebar when clicking on overlay
    if (mobileOverlay) {
        mobileOverlay.addEventListener('click', toggleSidebar);
    }
    
    // Close sidebar when clicking on a link (mobile only)
    if (window.innerWidth <= 900) {
        const sidebarLinks = document.querySelectorAll('.sidebar-link');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (sidebar.classList.contains('open')) {
                    toggleSidebar();
                }
            });
        });
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 900) {
            // Desktop view - ensure sidebar is visible
            sidebar.classList.remove('open');
            mobileOverlay.classList.remove('show');
            document.body.classList.remove('sidebar-open');
        }
    });
});
</script>