<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../includes/header.php';
include '../includes/db.php';

// Default to today
date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');
$date_filter = isset($_GET['date']) ? $_GET['date'] : $today;

// Get stats using normalized src_db schema
// Total students from src_db.students
$total_students = $conn->query("SELECT COUNT(*) AS cnt FROM students")->fetch_assoc()['cnt'];

// Total attendance records for the selected date (any status)
$stmtTotal = $conn->prepare("SELECT COUNT(*) AS cnt FROM attendance WHERE attendance_date = ?");
$stmtTotal->bind_param('s', $date_filter);
$stmtTotal->execute();
$filtered_count = $stmtTotal->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmtTotal->close();

// Total distinct students present for the selected date inside CURRENT active session
$ay_id  = (int)($_SESSION['active_ay_id'] ?? 0);
$sem_id = (int)($_SESSION['active_sem_id'] ?? 0);

$stmtPresent = $conn->prepare("SELECT COUNT(DISTINCT ad.student_id) AS cnt
    FROM attendance a
    JOIN admissions ad ON a.admission_id = ad.admission_id
    WHERE a.attendance_date = ? AND a.status = 'Present'
      AND ad.academic_year_id = ? AND ad.semester_id = ?");
$stmtPresent->bind_param('sii', $date_filter, $ay_id, $sem_id);
$stmtPresent->execute();
$present_today = $stmtPresent->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmtPresent->close();

// Prepare attendance data for drilldown chart based on year level and section
// Uses: attendance -> admission -> students -> year_level -> section
$attendance_data = [
    'years' => [], // year_name => [section_name => [student_id, name]]
];

$stmtDrill = $conn->prepare("SELECT yl.year_name AS year_level,
                                    sct.section_name AS section,
                                    st.student_id,
                                    CONCAT(st.first_name, ' ', st.last_name) AS name
                               FROM attendance a
                               JOIN admissions ad   ON a.admission_id = ad.admission_id
                               JOIN students st    ON ad.student_id = st.student_id
                               JOIN year_levels yl  ON ad.year_level_id = yl.year_id
                               JOIN sections sct    ON ad.section_id = sct.section_id
                              WHERE a.attendance_date = ?
                                AND a.status = 'Present'
                                AND ad.academic_year_id = ?
                                AND ad.semester_id = ?");
$stmtDrill->bind_param('sii', $date_filter, $ay_id, $sem_id);
$stmtDrill->execute();
$present_query = $stmtDrill->get_result();
while ($row = $present_query->fetch_assoc()) {
    $year = $row['year_level'];
    $section = $row['section'];
    if (!isset($attendance_data['years'][$year])) $attendance_data['years'][$year] = [];
    if (!isset($attendance_data['years'][$year][$section])) $attendance_data['years'][$year][$section] = [];
    $attendance_data['years'][$year][$section][] = [
        'student_id' => $row['student_id'],
        'name' => $row['name']
    ];
}
$stmtDrill->close();

// After session_start() and before header HTML, get admin name
$admin_name = isset($_SESSION['user']['name']) ? $_SESSION['user']['name'] : 'Admin';
?>

<!-- Modernized CSS -->
<style>
    :root{
        --bg1: #f0f9ff; /* very light sky */
        --bg2: #eefcff; /* light sky */
        --primary: #0369a1; /* sky/blue */
        --primary-600: #035b82; /* darker sky */
        --accent: #38bdf8; /* sky-400 */
        --success: #16a34a;
        --danger: #ef4444;
        --card-bg: linear-gradient(135deg, #ffffff 0%, #f0fbff 100%);
        --muted: #123441;
        --border: #dbeffd;
    }
    body {
        font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
        margin: 0;
        padding: 0;
        background: linear-gradient(120deg, var(--bg1) 0%, var(--bg2) 100%);
        color: var(--muted);
        min-height: 100vh;
    }
    .modern-dashboard {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        padding: 0;
        margin: 0;
    }
    main {
        flex: 1;
        width: calc(100% - 20rem);
        max-width: none;
        margin-left: 20rem;
        padding: 36px 32px !important;
        box-sizing: border-box;
    }
    
    /* Responsive main content */
    @media (max-width: 1200px) {
        main {
            width: calc(100% - 16rem);
            margin-left: 16rem;
            padding: 24px 20px !important;
        }
    }
    
    @media (max-width: 900px) {
        main {
            width: 100%;
            margin-left: 0;
            padding: 20px 16px !important;
        }
    }
    
    @media (max-width: 600px) {
        main {
            padding: 16px 12px !important;
        }
    }

    .stat-cards {
        display: flex;
        gap: 24px;
        margin-bottom: 32px;
        flex-wrap: wrap;
    }
    .card {
        flex: 1 1 260px !important;
        min-width: 240px !important;
        background: var(--card-bg);
        border-radius: 16px;
        padding: 32px 24px !important;
        box-shadow: 0 6px 22px rgba(16,24,40,0.04);
        text-align: center;
        transition: box-shadow 0.2s;
        border: 1px solid var(--border);
    }
    .card:hover {
        box-shadow: 0 8px 32px rgba(0,0,0,0.12);
    }
    .card h3 {
        margin-bottom: 12px;
        color: var(--primary);
        font-size: 1.2rem;
        font-weight: 600;
    }
    .card p {
        font-size: 2.1rem;
        font-weight: 700;
        margin: 0;
        color: #2a3b4c;
    }
    .admin-actions {
        background: #fff;
        padding: 28px 22px !important;
        border-radius: 16px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.07);
        margin-bottom: 32px;
        border: 1px solid #e0eafc;
    }
    .admin-actions h3 {
        margin: 0 0 18px 0;
        color: var(--primary);
        text-align: center;
        font-size: 1.2rem;
        font-weight: 700;
    }
    .action-buttons {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 18px;
    }
    .action-btn {
        display: block;
        padding: 16px 0;
        text-decoration: none;
        border-radius: 10px;
        font-weight: 700;
        font-size: 1.1rem;
        text-align: center;
        transition: all 0.2s;
        color: white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .action-btn.teacher {
        background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
    }
    .action-btn.subject {
        background: linear-gradient(135deg, #7dd3fc 0%, #38bdf8 100%);
    }
    .action-btn.manage {
        background: linear-gradient(135deg, #38bdf8 0%, #06b6d4 100%);
    }
    .action-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 15px rgba(0,0,0,0.13);
        opacity: 0.95;
    }
    .filter-form {
        margin: 32px 0 24px 0;
        display: flex;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
        background: #fff;
        padding: 18px 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        border: 1px solid var(--border);
    }
    .filter-form label {
        font-weight: 600;
        color: var(--primary);
    }
    .filter-form input[type="date"],
    .filter-form button {
        padding: 10px 16px;
        border-radius: 8px;
        border: 1px solid #ccc;
        font-size: 1rem;
    }
    .filter-form button {
        background: linear-gradient(135deg, var(--accent) 0%, var(--primary) 100%);
        color: white;
        border: none;
        cursor: pointer;
        font-weight: 600;
        transition: background 0.2s;
    }
    .filter-form button:hover {
        background: linear-gradient(135deg, var(--primary-600) 0%, var(--accent) 100%);
    }
    .table-section {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.07);
        padding: 28px 22px !important;
        border: 1px solid #e0eafc;
        margin-bottom: 32px;
    }
    .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    .table-header h3 {
        margin: 0;
        color: var(--primary);
        font-size: 1.1rem;
        font-weight: 600;
    }
    .see-all-btn {
        background: linear-gradient(135deg, var(--accent) 0%, var(--primary) 100%);
        color: white;
        border: none;
        border-radius: 8px;
        padding: 10px 24px;
        font-size: 1rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .see-all-btn:hover {
        background: linear-gradient(135deg, var(--primary-600) 0%, var(--accent) 100%);
        transform: translateY(-2px);
    }
    .table-responsive {
        overflow-x: auto;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
        background: #f8fafc;
        border-radius: 10px;
        overflow: hidden;
    }
    thead {
        background-color: var(--primary);
        color: white;
    }
    th, td {
        padding: 12px 10px;
        border: 1px solid #e0eafc;
        text-align: left;
        font-size: 1rem;
    }
    tbody tr:nth-child(even) {
        background: #e0eafc;
    }
    tbody tr:hover {
        background: #d0e2f7;
    }
    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0,0,0,0.4);
        justify-content: center;
        align-items: center;
    }
    .modal-content {
        background: #fff;
        border-radius: 15px;
        max-width: 1100px !important;
        width: 95vw;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        animation: fadeIn 0.2s;
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 30px 10px 30px;
        border-bottom: 2px solid #f0f0f0;
    }
    .modal-header h3 {
        margin: 0;
        color: var(--primary);
    }
    .close {
        color: #aaa;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }
    .close:hover {
        color: #000;
    }
    .modal-body {
        padding: 20px 30px 30px 30px;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }

    /* Top charts styles (larger, more prominent) */
    .top-charts {
        display: flex;
        gap: 20px;
        align-items: stretch;
        margin-bottom: 22px;
        width: 100%;
        flex-wrap: wrap;
    }
    .top-charts .card {
        background: linear-gradient(135deg, #ffffff 0%, #f4f8ff 100%);
        padding: 18px;
        border-radius: 14px;
        box-shadow: 0 6px 20px rgba(16,24,40,0.06);
        min-height: 240px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        flex: 1 1 300px;
    }
    .top-charts .card.realtime { 
        flex: 0 0 320px; 
        min-width: 300px;
    }
    .top-charts .card.other { 
        flex: 1 1 0; 
        min-width: 300px;
    }
    .top-charts canvas { 
        width: 100% !important; 
        height: 220px !important; 
    }

    /* Mobile Responsive Styles */
    @media (max-width: 1200px) {
        .top-charts .card.realtime,
        .top-charts .card.other {
            flex: 1 1 45%;
            min-width: 280px;
        }
    }

    @media (max-width: 900px) {
        .stat-cards .card {
            padding: 18px 12px !important;
            min-width: calc(50% - 12px) !important;
            flex: 1 1 calc(50% - 12px) !important;
        }
        
        .top-charts {
            flex-direction: column;
        }
        
        .top-charts .card.realtime,
        .top-charts .card.other {
            flex: 1 1 100%;
            min-width: 100%;
        }
        
        .table-responsive table {
            min-width: 900px;
        }
        
        .modal-content {
            max-width: 95vw !important;
            margin: 20px;
        }
        
        .filter-form {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
    }

    @media (max-width: 600px) {
        .stat-cards {
            flex-direction: column;
            gap: 14px;
        }
        
        .stat-cards .card {
            min-width: 100% !important;
            flex: 1 1 100% !important;
        }
        
        .pro-header {
            flex-direction: column;
            height: auto;
            padding: 12px 10px;
        }
        
        .admin-title {
            font-size: 1.2rem;
        }
        
        .admin-icon {
            width: 36px;
            height: 36px;
            font-size: 1.1rem;
        }
        
        .clock-badge {
            font-size: 0.95rem;
            padding: 5px 10px;
        }
        
        .action-buttons {
            grid-template-columns: 1fr;
        }
        
        .table-section, .admin-actions {
            padding: 12px 4px;
        }
        
        .modal-content {
            padding: 0;
            max-width: 98vw;
            margin: 10px;
        }
        
        .modal-header, .modal-body {
            padding: 12px 8px;
        }
        
        .logo-title {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
            padding-left: 10px !important;
        }
        
        .clock-badge.ml-6 {
            margin-left: 0;
        }
        
        .header-logo {
            width: 32px;
            height: 32px;
            margin-right: 8px;
        }
        
        .top-charts .card {
            min-height: 200px;
        }
        
        .top-charts canvas {
            height: 180px !important;
        }
    }

    /* Remove any sidebar or attendance tab styles if present */
    .sidebar, .attendance-tab, .attendance-sidebar, .nav, .side-nav, .side-menu {
        display: none !important;
        width: 0 !important;
        min-width: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
        border: none !important;
        background: none !important;
    }
</style>

<!-- Modern Dashboard UI -->
<div class="modern-dashboard">
    <main class="pt-8">
        <div style="display:flex;justify-content:flex-end;margin-bottom:12px;flex-wrap:wrap;gap:10px;">
            <label style="color:var(--primary);font-weight:600;">Date:</label>
            <input type="date" id="chartDate" value="<?php echo $date_filter; ?>" style="padding:6px 10px;border-radius:6px;border:1px solid #ccc;min-width:150px;">
        </div>
        
        <!-- Top charts: more prominent and placed above stat cards -->
        <div class="top-charts">
            <div class="card realtime">
                <h3 style="margin:0 0 8px 0;color:var(--primary);font-size:1rem;">Lab Attendance: Lab A vs Lab B</h3>
                <div style="display:flex;align-items:center;justify-content:center;flex:1;">
                    <canvas id="realtimeChartTop" style="max-width:280px;max-height:200px;"></canvas>
                </div>
                <div style="margin-top:8px;display:flex;align-items:center;gap:8px;justify-content:center;font-size:0.95rem;color:#555;flex-wrap:wrap;">
                    <span style="color:var(--success);font-weight:700;">Lab A</span>
                    <span style="color:#38bdf8;font-weight:700;">Lab B</span>
                </div>
            </div>
            <div class="card other">
                <h3 style="margin:0 0 8px 0;color:var(--primary);font-size:1rem;">By Year: Time In / Time Out</h3>
                <div style="height:200px;">
                    <canvas id="yearChartTop"></canvas>
                </div>
            </div>
            <div class="card other">
                <h3 style="margin:0 0 8px 0;color:var(--primary);font-size:1rem;">By Subject: Time In</h3>
                <div style="height:200px;">
                    <canvas id="subjectChartTop"></canvas>
                </div>
            </div>
        </div>

        <section class="stat-cards">
            <div class="card">
                <h3><i class="fas fa-user-graduate" style="margin-right: 8px; color: var(--primary);"></i>Total Students</h3>
                <p><?= $total_students ?></p>
            </div>
            <div class="card">
                <h3><i class="fas fa-chalkboard-teacher" style="margin-right: 8px; color: var(--primary);"></i>Total Faculty</h3>
                <p><?= $conn->query("SELECT COUNT(*) AS cnt FROM employees e JOIN roles r ON e.role_id = r.role_id WHERE LOWER(r.role_name) IN ('dean','faculty')")->fetch_assoc()['cnt'] ?></p>
            </div>
            <div class="card">
                <h3><i class="fas fa-sign-in-alt" style="margin-right: 8px; color: var(--primary);"></i>Time In and Out of Students (<?= $date_filter ?>)</h3>
                <p><?= $filtered_count ?></p>
            </div>
            <div class="card">
                <h3><i class="fas fa-check-circle" style="margin-right: 8px; color: var(--success);"></i>Present Today</h3>
                <p style="color:var(--success);"><?= $present_today ?></p>
            </div>
        </section>
        
        <section class="table-section">
            <div class="table-header">
                <h3>📊 Recent Attendance</h3>
            </div>
            <div class="table-responsive" id="recent-attendance-table">
                <!-- Content will be loaded via JavaScript -->
            </div>
        </section>

        <!-- Modal for all attendance -->
        <div id="allAttendanceModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>All Attendance for <?= htmlspecialchars($date_filter) ?></h3>
                    <span class="close" onclick="closeModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <div class="table-responsive" id="all-attendance-table">
                        <?php include '../includes/all_attendance.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    function openModal() {
        document.getElementById('allAttendanceModal').style.display = 'flex';
    }
    
    function closeModal() {
        document.getElementById('allAttendanceModal').style.display = 'none';
    }
    
    window.onclick = function(event) {
        const modal = document.getElementById('allAttendanceModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    // Keep the recent attendance polling
    setInterval(function() {
        fetch('../includes/universal_recent_attendance.php?type=all&limit=5&_=' + new Date().getTime())
            .then(res => res.text())
            .then(html => { 
                document.getElementById('recent-attendance-table').innerHTML = html; 
            })
            .catch(err => console.error('Error loading recent attendance:', err));
    }, 1000);

    // Charts
    let realtimeChart, yearChart, subjectChart;

    function createCharts() {
        // Top charts (larger)
        const rtCtx = document.getElementById('realtimeChartTop').getContext('2d');
        realtimeChart = new Chart(rtCtx, {
            type: 'doughnut',
            data: {
                labels: ['Lab A', 'Lab B'],
                datasets: [{ 
                    data: [0,0], 
                    backgroundColor: ['#16a34a', '#38bdf8'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: { 
                plugins: { 
                    legend: { 
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true
                        }
                    } 
                }, 
                maintainAspectRatio: false,
                cutout: '60%'
            }
        });

        const yCtx = document.getElementById('yearChartTop').getContext('2d');
        yearChart = new Chart(yCtx, {
            type: 'bar',
            data: { 
                labels: [], 
                datasets: [] 
            },
            options: {
                responsive: true,
                scales: { 
                    x: { 
                        stacked: true,
                        grid: {
                            display: false
                        }
                    }, 
                    y: { 
                        stacked: true, 
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    } 
                },
                plugins: { 
                    legend: { 
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true
                        }
                    } 
                },
                maintainAspectRatio: false
            }
        });

        const sCtx = document.getElementById('subjectChartTop').getContext('2d');
        subjectChart = new Chart(sCtx, {
            type: 'bar',
            data: { 
                labels: [], 
                datasets: [{ 
                    label: 'Time Ins', 
                    data: [], 
                    backgroundColor: '#38bdf8',
                    borderWidth: 0,
                    borderRadius: 5
                }] 
            },
            options: { 
                indexAxis: 'y', 
                responsive: true, 
                scales: { 
                    x: { 
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        }
                    }
                }, 
                plugins: { 
                    legend: { 
                        display: false 
                    } 
                }, 
                maintainAspectRatio: false 
            }
        });
    }

    function updateCharts(data) {
        // Realtime - Lab A vs Lab B
        const labA = data.realtime['Lab A'] || 0;
        const labB = data.realtime['Lab B'] || 0;
        
        if (realtimeChart && realtimeChart.data) {
            realtimeChart.data.datasets[0].data = [labA, labB];
            realtimeChart.update('none');
        }

        // By year
        const years = Object.keys(data.by_year || {}).sort((a,b) => a - b);
        const presentSeries = [];
        const signedOutSeries = [];
        
        years.forEach(y => {
            presentSeries.push((data.by_year[y] && (data.by_year[y].Present || data.by_year[y].present)) || 0);
            signedOutSeries.push((data.by_year[y] && (data.by_year[y]['Signed Out'] || data.by_year[y].SignedOut || data.by_year[y].signed_out)) || 0);
        });
        
        if (yearChart && yearChart.data) {
            yearChart.data.labels = years;
            yearChart.data.datasets = [
                { 
                    label: 'Time In', 
                    data: presentSeries, 
                    backgroundColor: '#16a34a',
                    borderWidth: 0,
                    borderRadius: 5
                },
                { 
                    label: 'Time Out', 
                    data: signedOutSeries, 
                    backgroundColor: '#38bdf8',
                    borderWidth: 0,
                    borderRadius: 5
                }
            ];
            yearChart.update('none');
        }

        // By subject
        const subjects = data.by_subject || [];
        if (subjectChart && subjectChart.data) {
            subjectChart.data.labels = subjects.map(s => s.subject);
            subjectChart.data.datasets[0].data = subjects.map(s => s.count);
            subjectChart.update('none');
        }
    }

    async function fetchChartData(date) {
        try {
            const res = await fetch('../ajax/dashboard_charts.php?date=' + encodeURIComponent(date));
            if (!res.ok) throw new Error('Network response not ok');
            return await res.json();
        } catch (err) {
            console.error('Failed to fetch chart data', err);
            return { realtime: {}, by_year: {}, by_subject: [] };
        }
    }

    document.addEventListener('DOMContentLoaded', async function() {
        createCharts();
        const dateInput = document.getElementById('chartDate');
        let currentDate = dateInput.value || '<?php echo $date_filter; ?>';

        try {
            const initial = await fetchChartData(currentDate);
            updateCharts(initial);
        } catch (error) {
            console.error('Error loading initial chart data:', error);
        }

        // Set up auto-refresh
        const refreshInterval = setInterval(async function() {
            const d = dateInput.value || currentDate;
            try {
                const data = await fetchChartData(d);
                updateCharts(data);
            } catch (error) {
                console.error('Error refreshing chart data:', error);
            }
        }, 5000);

        // Date change handler
        dateInput.addEventListener('change', async function() {
            const d = this.value;
            currentDate = d;
            try {
                const data = await fetchChartData(d);
                updateCharts(data);
                
                // Update page URL without reload
                const url = new URL(window.location);
                url.searchParams.set('date', d);
                window.history.replaceState({}, '', url);
            } catch (error) {
                console.error('Error updating charts on date change:', error);
            }
        });

        // Load initial recent attendance
        fetch('../includes/universal_recent_attendance.php?type=all&limit=5&_=' + new Date().getTime())
            .then(res => res.text())
            .then(html => { 
                document.getElementById('recent-attendance-table').innerHTML = html; 
            })
            .catch(err => console.error('Error loading initial recent attendance:', err));
    });

    // Handle window resize for chart responsiveness
    window.addEventListener('resize', function() {
        if (realtimeChart) realtimeChart.resize();
        if (yearChart) yearChart.resize();
        if (subjectChart) subjectChart.resize();
    });
</script>

<?php
$footerPath = __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php';
if (file_exists($footerPath)) {
    include $footerPath;
} else {
    // Graceful fallback footer to avoid PHP warnings when ../includes/footer.php is missing
    ?>
    <footer style="background: linear-gradient(90deg, #e0eafc 0%, #cfdef3 100%); border-top: 1.5px solid #e0eafc; padding: 24px 0 32px 0; text-align: center; font-family: 'Segoe UI', Arial, sans-serif; font-size: 1.1rem; color: #26324d; margin-top: 40px; margin-bottom: 24px;">
        <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
            <span style="font-size: 2rem; color: #6366f1;"><i class="fa-solid fa-school"></i></span>
            <p style="margin: 0; font-weight: 500; letter-spacing: 0.5px;">&copy; <?php echo date('Y'); ?> Santa Rita College of Pampanga</p>
            <small style="color: #6b7280; font-size: 0.95rem;">Automated Ingress And Egress</small>
        </div>
    </footer>
    </body>
    </html>
    <?php
}
?>



