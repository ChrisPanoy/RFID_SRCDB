<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../includes/header.php';
include '../includes/db.php';

$ay_id = isset($_GET['ay_id']) ? (int)$_GET['ay_id'] : ($_SESSION['active_ay_id'] ?? 0);
$sem_id = isset($_GET['semester_id']) ? (int)$_GET['semester_id'] : ($_SESSION['active_sem_id'] ?? 0);

$academic_years = $conn->query("SELECT * FROM academic_years ORDER BY ay_id DESC");
$semesters = $conn->query("SELECT s.*, ay.ay_name FROM semesters s JOIN academic_years ay ON s.ay_id = ay.ay_id ORDER BY s.semester_id DESC");
?>

<div class="app-content ml-80 p-8 min-h-screen bg-gray-50">
    <div class="max-w-6xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-bold text-gray-800">System Archives</h1>
            <div class="px-4 py-2 bg-indigo-600 text-white rounded-lg shadow-sm text-sm font-semibold">
                Historical Data Review
            </div>
        </div>

        <!-- Selection Form -->
        <div class="bg-white rounded-2xl shadow-xl p-6 mb-8 border border-gray-100">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Academic Year</label>
                    <select name="ay_id" class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-400">
                        <option value="0">All Years</option>
                        <?php $academic_years->data_seek(0); while($ay = $academic_years->fetch_assoc()): ?>
                            <option value="<?= $ay['ay_id'] ?>" <?= $ay_id == $ay['ay_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ay['ay_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Semester</label>
                    <select name="semester_id" class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-indigo-400">
                        <option value="0">All Semesters</option>
                        <?php $semesters->data_seek(0); while($sem = $semesters->fetch_assoc()): ?>
                            <option value="<?= $sem['semester_id'] ?>" <?= $sem_id == $sem['semester_id'] ? 'selected' : '' ?>>
                                Sem <?= htmlspecialchars($sem['semester_now']) ?> (<?= htmlspecialchars($sem['ay_name']) ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl transition-all shadow-lg flex items-center justify-center gap-2">
                        <i class="fas fa-search"></i> Load Archive
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabs for different data types -->
        <div class="flex gap-4 mb-6">
            <a href="?ay_id=<?= $ay_id ?>&semester_id=<?= $sem_id ?>&view=attendance" 
               class="px-6 py-2 rounded-full font-bold transition-all <?= (!isset($_GET['view']) || $_GET['view'] == 'attendance') ? 'bg-indigo-600 text-white shadow-lg' : 'bg-white text-gray-600 hover:bg-gray-100' ?>">
                Attendance Records
            </a>
            <a href="?ay_id=<?= $ay_id ?>&semester_id=<?= $sem_id ?>&view=subjects" 
               class="px-6 py-2 rounded-full font-bold transition-all <?= (isset($_GET['view']) && $_GET['view'] == 'subjects') ? 'bg-indigo-600 text-white shadow-lg' : 'bg-white text-gray-600 hover:bg-gray-100' ?>">
                Subject Assignments
            </a>
        </div>

        <!-- Results Section -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100">
            <div class="bg-gray-800 p-4 flex items-center justify-between">
                <h3 class="text-white font-bold flex items-center gap-2">
                    <i class="fas fa-database"></i> 
                    <?php 
                        $view = $_GET['view'] ?? 'attendance';
                        if ($view == 'subjects') {
                            echo "Subject Assignments for Selected Period";
                        } else {
                            echo "Attendance Records for Selected Period";
                        }
                    ?>
                </h3>
            </div>
            <div class="p-6">
                <?php if (!isset($_GET['view']) || $_GET['view'] == 'attendance'): ?>
                    <!-- Attendance View -->
                    <?php 
                        $_GET['show_all'] = '1'; 
                        include '../includes/all_attendance.php'; 
                    ?>
                <?php else: ?>
                    <!-- Subjects View -->
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="bg-indigo-600 text-white">
                                    <th class="p-4 text-left">Subject</th>
                                    <th class="p-4 text-left">Instructor</th>
                                    <th class="p-4 text-left">Schedule</th>
                                    <th class="p-4 text-left">Lab</th>
                                    <th class="p-4 text-center">Enrolled</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                    $archSql = "SELECT sc.*, sub.subject_code, sub.subject_name, fac.lab_name, emp.firstname, emp.lastname,
                                                   (SELECT COUNT(*) FROM admissions WHERE schedule_id = sc.schedule_id AND academic_year_id = ? AND semester_id = ?) as enrolled
                                                FROM schedule sc
                                                JOIN subjects sub ON sc.subject_id = sub.subject_id
                                                JOIN facilities fac ON sc.lab_id = fac.lab_id
                                                JOIN employees emp ON sc.employee_id = emp.employee_id
                                                WHERE sc.academic_year_id = ? AND sc.semester_id = ?
                                                ORDER BY sub.subject_code";
                                    $stmtArch = $conn->prepare($archSql);
                                    $stmtArch->bind_param('iiii', $ay_id, $sem_id, $ay_id, $sem_id);
                                    $stmtArch->execute();
                                    $archRes = $stmtArch->get_result();
                                    
                                    if ($archRes->num_rows > 0):
                                        while ($row = $archRes->fetch_assoc()):
                                ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="p-4">
                                            <div class="font-bold text-indigo-700"><?= htmlspecialchars($row['subject_code']) ?></div>
                                            <div class="text-xs text-gray-500"><?= htmlspecialchars($row['subject_name']) ?></div>
                                        </td>
                                        <td class="p-4">
                                            <?= htmlspecialchars($row['lastname'] . ', ' . $row['firstname']) ?>
                                        </td>
                                        <td class="p-4">
                                            <div class="text-sm"><?= htmlspecialchars($row['schedule_days'] ?? 'N/A') ?></div>
                                            <div class="text-xs text-gray-400"><?= date('h:i A', strtotime($row['time_start'])) ?> - <?= date('h:i A', strtotime($row['time_end'])) ?></div>
                                        </td>
                                        <td class="p-4">
                                            <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs"><?= htmlspecialchars($row['lab_name']) ?></span>
                                        </td>
                                        <td class="p-4 text-center font-bold">
                                            <?= $row['enrolled'] ?>
                                        </td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="5" class="p-8 text-center text-gray-500">No subject schedules found for this period.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .app-content {
        margin-left: 20rem;
    }
    @media (max-width: 900px) {
        .app-content {
            margin-left: 0;
        }
    }
</style>

<?php include '../includes/footer.php'; ?>
