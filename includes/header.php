<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
$isSubfolder = in_array($currentDir, ['admin', 'student', 'teacher', 'ajax', 'includes']);
$basePath = $isSubfolder ? '../' : '';

// Allow pages to request a custom/external sidebar (for slimmer sidebars)
// Set $use_custom_sidebar = true in a page before including this file to enable.
$use_custom_sidebar = isset($use_custom_sidebar) ? (bool)$use_custom_sidebar : false;
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $basePath ?>assets/css/style.css">
    <link rel="stylesheet" href="<?= $basePath ?>assets/css/mobile.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Header styles */
            .pro-header {
            background: #2563eb !important; /* Royal Blue / Strong Blue */
            background-image: none !important;
            box-shadow: 0 2px 12px rgba(79,140,255,0.08);
            border-bottom: 1.5px solid #e0e0e0;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 36px;
            position: fixed;
            top: 0;
            right: 0;
            left: 20rem;
            z-index: 20;
        }
        .admin-icon {
            background: #fff;
            color: #0f52d8;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.7rem;
            box-shadow: 0 2px 8px rgba(79,140,255,0.10);
        }
        .admin-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: 1px;
            margin-left: 16px;
        }
        .clock-badge {
            background: #fff;
            color: #0f52d8;
            border-radius: 999px;
            padding: 7px 18px;
            font-weight: 600;
            font-size: 1.1rem;
            box-shadow: 0 1px 6px rgba(79,140,255,0.08);
            display: flex;
            align-items: center;
            gap: 7px;
        }
        /* Solid rectangular sidebar with consistent wider width */
        .glass-sidebar{
            background: #ffffff;
            box-shadow: 0 6px 24px rgba(31,38,135,0.06);
            backdrop-filter: none;
            border-radius: 0 !important;
            border: 1px solid rgba(0,0,0,0.06);
            transform: none !important;
            transition: none !important;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 20rem; /* match teacher sidebar (320px) */
            z-index: 10001;
            padding-top: 36px;
            overflow-y: auto; /* Enable scrolling */
            max-height: 100vh;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 28px; /* wider padding for better proportions */
            border-radius: 0 !important;
            font-weight: 700;
            color: #374151;
            transition: none !important;
            margin-bottom: 6px;
            font-size: 1.125rem; /* slightly larger text */
        }
        .sidebar-link i {
            font-size: 1.375em; /* larger icon */
            min-width: 1.75rem; /* consistent icon width */
        }
        .sidebar-link.active, .sidebar-link:hover {
            background: linear-gradient(90deg, #0f52d8 0%, #0f52d8 100%);
            color: #fff !important;
            box-shadow: 0 6px 18px rgba(99,102,241,0.08);
            transform: translateY(-1px);
        }
        .sidebar-logo {
            font-size: 1.5rem; /* larger logo text */
            font-weight: 800;
            color: #0f52d8;
            letter-spacing: 1px;
            margin-top: 6px;
        }

        .sidebar-hamburger { display: none; }

        /* larger logo size */
        .glass-sidebar img { 
            border-radius: 9999px !important; 
            width: 160px !important; /* larger profile image */
            height: 160px !important;
            border: 4px solid #93c5fd !important; /* blue-300 border */
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

    </style>

    <script>
        // Clock update function
        function updateHeaderClock() {
            const now = new Date();
            let hours = now.getHours();
            let minutes = now.getMinutes();
            let seconds = now.getSeconds();
            let ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;
            const timeString = hours + ':' + minutes + ':' + seconds + ' ' + ampm;
            document.getElementById('headerClock').innerText = timeString;
        }
        // Update clock every second
        setInterval(updateHeaderClock, 1000);
        updateHeaderClock(); // Initial call

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            if (!sidebar) return;
            sidebar.classList.toggle('open');
        }
        // Close sidebar on Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const sidebar = document.getElementById('sidebar');
                if (sidebar && sidebar.classList.contains('open')) sidebar.classList.remove('open');
            }
        });
        
        // NEW: Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            const sidebar = document.getElementById('sidebar');
            const hamburger = document.querySelector('.sidebar-hamburger');
            
            if (window.innerWidth <= 900 && sidebar && sidebar.classList.contains('open') && 
                !sidebar.contains(e.target) && !hamburger.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    </script>
</head>
<body class="bg-gradient-to-tr from-blue-100 via-purple-100 to-pink-100 min-h-screen">
<!-- Hamburger for mobile -->
<div class="sidebar-hamburger hidden lg:hidden" onclick="toggleSidebar()" aria-label="Open menu">
    <i class="fa fa-bars text-indigo-700 text-2xl"></i>
</div>

<div class="flex min-h-screen">
    <?php if (!$use_custom_sidebar): ?>
    <!-- Sidebar (desktop: wider w-80, mobile: slide-in) -->
    <div id="sidebar" class="glass-sidebar w-[20rem] lg:w-[20rem] bg-white shadow-lg rounded-2xl flex flex-col py-6 px-5 fixed lg:inset-y-6 lg:left-6 z-20 lg:translate-x-0 transform transition-transform duration-300 ease-in-out lg:static lg:rounded-xl">
        <!-- Logo -->
        <div class="flex flex-col items-center mb-8 px-2">
            <img src="<?= $basePath ?>assets/img/logo.png" alt="Logo" class="rounded-full object-cover shadow-md mb-3 bg-gray-100" onerror="this.style.display='none'">
            <h2 class="sidebar-logo"></h2>
            <p class="text-xs text-gray-500 mt-1"></p>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 flex flex-col gap-1 mt-3">
            <a href="<?= $basePath ?>admin/dashboard.php" class="sidebar-link <?php if($currentPage==='dashboard.php') echo 'active'; ?>"><i class="fa-solid fa-chart-pie text-indigo-500"></i>Dashboard</a>
            <a href="<?= $basePath ?>admin/students.php" class="sidebar-link <?php if($currentPage==='students.php') echo 'active'; ?>"><i class="fa-solid fa-user-graduate text-indigo-500"></i>Master List</a>
            <a href="<?= $basePath ?>admin/BSISstudents.php" class="sidebar-link <?php if($currentPage==='BSISstudents.php') echo 'active'; ?>"><i class="fa-solid fa-users-line text-indigo-500"></i>Students</a>
            <a href="<?= $basePath ?>admin/add_teacher.php" class="sidebar-link <?php if($currentPage==='add_teacher.php') echo 'active'; ?>"><i class="fa-solid fa-user-plus text-indigo-500"></i>Faculty</a>
            <a href="<?= $basePath ?>admin/attendance.php" class="sidebar-link <?php if($currentPage==='attendance.php') echo 'active'; ?>"><i class="fa-solid fa-calendar-check text-indigo-500"></i>Attendance</a>

            <hr class="my-3 border-gray-300">

            <a href="<?= $basePath ?>admin/add_subject.php" class="sidebar-link <?php if($currentPage==='add_subject.php') echo 'active'; ?>"><i class="fa-solid fa-book-medical text-indigo-500"></i>Add Subject</a>
            <a href="<?= $basePath ?>admin/assign_subjects.php" class="sidebar-link <?php if($currentPage==='assign_subjects.php') echo 'active'; ?>"><i class="fa-solid fa-book-open-reader text-indigo-500"></i>Assign Subjects</a>
            <a href="<?= $basePath ?>admin/academic_settings.php" class="sidebar-link <?php if($currentPage==='academic_settings.php') echo 'active'; ?>"><i class="fa-solid fa-gears text-indigo-500"></i>Academic Settings</a>
            <a href="<?= $basePath ?>admin/archives.php" class="sidebar-link <?php if($currentPage==='archives.php') echo 'active'; ?>"><i class="fa-solid fa-box-archive text-indigo-500"></i>System Archives</a>
            <a href="<?= $basePath ?>admin/manage_teachers.php" class="sidebar-link <?php if($currentPage==='manage_teachers.php') echo 'active'; ?>"><i class="fa-solid fa-users-gear text-indigo-500"></i>Manage Faculty</a>
            <a href="<?= $basePath ?>admin/manage_subjects.php" class="sidebar-link <?php if($currentPage==='manage_subjects.php') echo 'active'; ?>"><i class="fa-solid fa-book-open-reader text-indigo-500"></i>Manage Subjects</a>
            
            <div class="mt-auto pt-6 border-t border-gray-100">
                <a href="<?= $basePath ?>logout.php" class="sidebar-link text-red-600 hover:bg-red-50 hover:text-red-700 font-bold">
                    <i class="fa-solid fa-sign-out-alt"></i>Logout System
                </a>
            </div>
        </nav>
    </div>

    <!-- Main content wrapper: apply left margin to clear the larger sidebar on wide screens -->
    <div class="flex-1 ml-[20rem] lg:ml-[20rem]">
    <?php else: ?>
    <!-- Using external custom sidebar: include the page-specific sidebar here so pages only need to set $use_custom_sidebar = true before including header.php -->
    <?php
    $sidebarPath = __DIR__ . '/sidebar.php';
    if (file_exists($sidebarPath)) {
        include $sidebarPath;
    }
    ?>
    <div class="flex-1 main-content">
    <?php endif; ?>
        <!-- Header -->
        <header class="pro-header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <span class="admin-icon"><i class="fas fa-user-shield"></i></span>
                <div style="display: flex; flex-direction: column;">
                    <?php if (!$use_custom_sidebar): 
                        $user_role = $_SESSION['user']['role'] ?? 'User';
                        $user_name = trim(($_SESSION['user']['firstname'] ?? '') . ' ' . ($_SESSION['user']['lastname'] ?? ''));
                        $welcome_text = "Welcome, " . htmlspecialchars($user_role) . " " . htmlspecialchars($user_name);
                    ?>
                        <h1 class="admin-title" style="margin-left: 0; line-height: 1.2;"><?= $welcome_text ?></h1>
                    <?php else: ?>
                        <h1 class="admin-title" style="margin-left: 0; line-height: 1.2;">Welcome, Faculty <?= isset($teacher_name) ? htmlspecialchars($teacher_name) : 'User' ?></h1>
                    <?php endif; ?>
                </div>
            </div>
            <div class="clock-badge">
                <i class="fas fa-clock"></i>
                <span id="headerClock"></span>
            </div>
        </header>

        <?php if ($use_custom_sidebar): ?>
        <style>
            /* When using the teacher/custom sidebar (20rem), shift header to align flush with sidebar */
            .pro-header {
                left: 20rem !important; /* matches .sidebar w-80 (20rem) */
                width: calc(100% - 20rem) !important;
                border-radius: 0 !important;
            }
            /* ensure main content uses same margin-left (sidebar.php sets .main-content margin-left: 20rem) */
            @media (min-width: 901px) {
                .main-content { margin-left: 20rem !important; }
            }
            
            /* When using the teacher/custom sidebar (20rem), shift header to align flush with sidebar */
            .pro-header {
                left: 20rem !important; /* matches .sidebar w-80 (20rem) */
                width: calc(100% - 20rem) !important;
                border-radius: 0 !important;
            }
            /* ensure main content uses same margin-left (sidebar.php sets .main-content margin-left: 20rem) */
            @media (min-width: 901px) {
                .main-content { margin-left: 20rem !important; }
            }
        </style>
        <?php endif; ?>
        <!-- Add spacing to prevent content from going under header -->
        <div class="header-spacer"></div>
        <!-- Main content starts here -->