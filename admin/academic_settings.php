<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../includes/header.php';
include '../includes/db.php';

$message = '';
$error = '';

// Handle updating active status
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['set_active_ay'])) {
        $ay_id = (int)$_POST['ay_id'];
        $conn->query("UPDATE academic_years SET status = 'Inactive'");
        $stmt = $conn->prepare("UPDATE academic_years SET status = 'Active' WHERE ay_id = ?");
        $stmt->bind_param("i", $ay_id);
        if ($stmt->execute()) {
            $message = "Academic Year updated successfully!";
        } else {
            $error = "Failed to update Academic Year.";
        }
    }

    if (isset($_POST['set_active_sem'])) {
        $sem_id = (int)$_POST['sem_id'];
        $conn->query("UPDATE semesters SET status = 'Inactive'");
        $stmt = $conn->prepare("UPDATE semesters SET status = 'Active' WHERE semester_id = ?");
        $stmt->bind_param("i", $sem_id);
        if ($stmt->execute()) {
            $message = "Semester updated successfully!";
        } else {
            $error = "Failed to update Semester.";
        }
    }

    if (isset($_POST['add_ay'])) {
        $ay_name = trim($_POST['ay_name']);
        if (!empty($ay_name)) {
            $stmt = $conn->prepare("INSERT INTO academic_years (ay_name, status) VALUES (?, 'Inactive')");
            $stmt->bind_param("s", $ay_name);
            if ($stmt->execute()) {
                $message = "Academic Year added successfully!";
            } else {
                $error = "Failed to add Academic Year.";
            }
        }
    }

    if (isset($_POST['add_semester'])) {
        $semester_now = trim($_POST['semester_now']);
        $ay_id = (int)$_POST['ay_id'];
        if (!empty($semester_now) && $ay_id > 0) {
            $stmt = $conn->prepare("INSERT INTO semesters (semester_now, ay_id, status) VALUES (?, ?, 'Inactive')");
            $stmt->bind_param("si", $semester_now, $ay_id);
            if ($stmt->execute()) {
                $message = "Semester added successfully!";
            } else {
                $error = "Failed to add Semester.";
            }
        }
    }
}

$academic_years = $conn->query("SELECT * FROM academic_years ORDER BY ay_id DESC");
$semesters = $conn->query("SELECT s.*, ay.ay_name FROM semesters s JOIN academic_years ay ON s.ay_id = ay.ay_id ORDER BY s.semester_id DESC");
?>

<div class="p-8">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">System Configuration</h1>

        <?php if ($message): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 shadow-sm rounded-r-lg">
                <p class="font-bold">Success</p>
                <p><?= $message ?></p>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 shadow-sm rounded-r-lg">
                <p class="font-bold">Error</p>
                <p><?= $error ?></p>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
            <!-- Academic Year Settings -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100 flex flex-col h-full">
                <div class="bg-indigo-900 p-5 flex justify-between items-center">
                    <h3 class="text-xl font-bold text-white flex items-center gap-3">
                        <i class="fas fa-calendar-alt text-indigo-200"></i> Academic Year
                    </h3>
                    <button onclick="openModal('ay-modal')" class="bg-white/20 hover:bg-white/30 text-white p-2 rounded-lg transition-all flex items-center gap-2 text-sm font-semibold backdrop-blur-sm">
                        <i class="fas fa-plus"></i> Add New
                    </button>
                </div>
                <div class="p-6 flex-grow">
                    <p class="text-sm text-gray-500 mb-6">Select the active academic year for the entire system.</p>
                    <div class="space-y-4">
                        <?php 
                        $academic_years = $conn->query("SELECT * FROM academic_years ORDER BY ay_name DESC");
                        while ($ay = $academic_years->fetch_assoc()): 
                        ?>
                            <div class="flex items-center justify-between p-4 rounded-xl border <?= $ay['status'] == 'Active' ? 'border-indigo-500 bg-indigo-50/50 ring-1 ring-indigo-500' : 'border-gray-200 hover:border-indigo-300' ?> transition-all group">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center <?= $ay['status'] == 'Active' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-400 group-hover:bg-indigo-100 group-hover:text-indigo-600' ?> transition-colors">
                                        <i class="fas fa-calendar"></i>
                                    </div>
                                    <div>
                                        <span class="font-bold text-gray-800 block"><?= htmlspecialchars($ay['ay_name']) ?></span>
                                        <?php if ($ay['status'] == 'Active'): ?>
                                            <span class="text-[10px] uppercase tracking-wider font-bold text-indigo-600">Active Now</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="ay_id" value="<?= $ay['ay_id'] ?>">
                                    <?php if ($ay['status'] != 'Active'): ?>
                                        <button type="submit" name="set_active_ay" class="px-4 py-2 bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-600 hover:text-white transition-all font-semibold text-sm border border-indigo-100">
                                            Set Active
                                        </button>
                                    <?php else: ?>
                                        <div class="px-4 py-2 bg-indigo-100 text-indigo-700 rounded-lg text-sm font-bold flex items-center gap-2">
                                            <i class="fas fa-check-circle text-xs"></i> Current
                                        </div>
                                    <?php endif; ?>
                                </form>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <!-- Semester Settings -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100 flex flex-col h-full">
                <div class="bg-indigo-900 p-5 flex justify-between items-center">
                    <h3 class="text-xl font-bold text-white flex items-center gap-3">
                        <i class="fas fa-layer-group text-sky-200"></i> Semester
                    </h3>
                    <button onclick="openModal('sem-modal')" class="bg-white/20 hover:bg-white/30 text-white p-2 rounded-lg transition-all flex items-center gap-2 text-sm font-semibold backdrop-blur-sm">
                        <i class="fas fa-plus"></i> Add New
                    </button>
                </div>
                <div class="p-6 flex-grow">
                    <p class="text-sm text-gray-500 mb-6">Select the active semester for the current academic year.</p>
                    <div class="space-y-4 max-h-[500px] overflow-y-auto pr-2 custom-scrollbar">
                        <?php 
                        $sem_res = $conn->query("SELECT s.*, ay.ay_name FROM semesters s JOIN academic_years ay ON s.ay_id = ay.ay_id ORDER BY ay.ay_name DESC, s.semester_now DESC");
                        while ($sem = $sem_res->fetch_assoc()): 
                        ?>
                            <div class="flex items-center justify-between p-4 rounded-xl border <?= $sem['status'] == 'Active' ? 'border-sky-500 bg-sky-50/50 ring-1 ring-sky-500' : 'border-gray-200 hover:border-sky-300' ?> transition-all group">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center <?= $sem['status'] == 'Active' ? 'bg-sky-500 text-white' : 'bg-gray-100 text-gray-400 group-hover:bg-sky-100 group-hover:text-sky-600' ?> transition-colors">
                                        <i class="fas fa-layer-group"></i>
                                    </div>
                                    <div>
                                        <span class="font-bold text-gray-800 block">Semester <?= htmlspecialchars($sem['semester_now']) ?></span>
                                        <div class="text-[11px] text-gray-400 font-medium"><?= htmlspecialchars($sem['ay_name']) ?></div>
                                        <?php if ($sem['status'] == 'Active'): ?>
                                            <span class="text-[10px] uppercase tracking-wider font-bold text-sky-600">Active Now</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="sem_id" value="<?= $sem['semester_id'] ?>">
                                    <?php if ($sem['status'] != 'Active'): ?>
                                        <button type="submit" name="set_active_sem" class="px-4 py-2 bg-sky-50 text-sky-600 rounded-lg hover:bg-sky-500 hover:text-white transition-all font-semibold text-sm border border-sky-100">
                                            Set Active
                                        </button>
                                    <?php else: ?>
                                        <div class="px-4 py-2 bg-sky-100 text-sky-700 rounded-lg text-sm font-bold flex items-center gap-2">
                                            <i class="fas fa-check-circle text-xs"></i> Current
                                        </div>
                                    <?php endif; ?>
                                </form>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Academic Year Modal -->
<div id="ay-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-in zoom-in duration-300">
        <div class="bg-indigo-900 p-6">
            <h3 class="text-xl font-bold text-white flex items-center gap-2">
                <i class="fas fa-plus"></i> Add Academic Year
            </h3>
        </div>
        <form method="POST" class="p-6">
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Academic Year Name</label>
                <input type="text" name="ay_name" placeholder="e.g. 2025-2026" required
                       class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
                <p class="mt-2 text-xs text-gray-400">Use format YYYY-YYYY for consistency.</p>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeModal('ay-modal')" class="px-6 py-2.5 text-gray-500 font-semibold hover:bg-gray-100 rounded-xl transition-all">Cancel</button>
                <button type="submit" name="add_ay" class="px-6 py-2.5 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">Add Year</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Semester Modal -->
<div id="sem-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-in zoom-in duration-300">
        <div class="bg-indigo-900 p-6">
            <h3 class="text-xl font-bold text-white flex items-center gap-2">
                <i class="fas fa-plus"></i> Add Semester
            </h3>
        </div>
        <form method="POST" class="p-6">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Semester</label>
                    <select name="semester_now" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-sky-500 outline-none transition-all">
                        <option value="1">Semester 1</option>
                        <option value="2">Semester 2</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Academic Year</label>
                    <select name="ay_id" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-sky-500 outline-none transition-all">
                        <?php 
                        $ay_list = $conn->query("SELECT * FROM academic_years ORDER BY ay_name DESC");
                        while($ay = $ay_list->fetch_assoc()): 
                        ?>
                            <option value="<?= $ay['ay_id'] ?>"><?= htmlspecialchars($ay['ay_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-8">
                <button type="button" onclick="closeModal('sem-modal')" class="px-6 py-2.5 text-gray-500 font-semibold hover:bg-gray-100 rounded-xl transition-all">Cancel</button>
                <button type="submit" name="add_semester" class="px-6 py-2.5 bg-sky-500 text-white font-semibold rounded-xl hover:bg-sky-600 shadow-lg shadow-sky-200 transition-all">Add Semester</button>
            </div>
        </form>
    </div>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 10px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #cbd5e1;
    }
</style>

<script>
function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.id.includes('-modal')) {
        closeModal(event.target.id);
    }
}
</script>

<?php 
// Clean up footer tags if needed, or rely on included files
?>
</body>
</html>
