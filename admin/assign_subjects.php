<?php
include '../includes/db.php';

// Ensure session is started before checking auth so header redirects work
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../includes/header.php';

$message = '';
$error = '';

// Handle subject assignment to students using Students + multiple Schedules
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_subject'])) {
    $student_ids_raw   = $_POST['student_ids'] ?? [];
    $student_ids       = is_array($student_ids_raw) ? array_map('trim', $student_ids_raw) : [];
    $schedule_ids_raw  = $_POST['schedule_ids'] ?? [];
    $schedule_ids      = is_array($schedule_ids_raw) ? array_filter(array_map('intval', $schedule_ids_raw)) : [];

    if (empty($student_ids) || empty($schedule_ids)) {
        $error = "Please select at least one student and at least one schedule.";
    } else {
        $ay_int = (int)($_SESSION['active_ay_id'] ?? 0);
        $sem_int = (int)($_SESSION['active_sem_id'] ?? 0);

        $checkDup = $conn->prepare("SELECT admission_id FROM admissions
                                      WHERE student_id = ? AND subject_id = ? AND schedule_id = ?
                                        AND academic_year_id = ? AND semester_id = ?");
        $checkNull = $conn->prepare("SELECT admission_id FROM admissions
                                      WHERE student_id = ? AND academic_year_id = ? AND semester_id = ?
                                        AND subject_id IS NULL AND schedule_id IS NULL LIMIT 1");
        $updFromNull = $conn->prepare("UPDATE admissions SET subject_id = ?, schedule_id = ? WHERE admission_id = ?");
        $ins = $conn->prepare("INSERT INTO admissions
                    (student_id, academic_year_id, semester_id, section_id, year_level_id, course_id, subject_id, schedule_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        $findAdmMeta = $conn->prepare("SELECT course_id, year_level_id, section_id
                                           FROM admissions
                                           WHERE student_id = ? AND academic_year_id = ? AND semester_id = ?
                                           ORDER BY admission_id DESC LIMIT 1");
        $findAnyMeta = $conn->prepare("SELECT course_id, year_level_id, section_id
                                           FROM admissions
                                           WHERE student_id = ?
                                           ORDER BY admission_id DESC LIMIT 1");

        $cid_int  = 0;
        $yl_int   = 0;
        $sec_int  = 0;

        if ($resCourse = $conn->query("SELECT course_id FROM courses ORDER BY course_id ASC LIMIT 1")) {
            if ($rowCourse = $resCourse->fetch_assoc()) {
                $cid_int = (int)$rowCourse['course_id'];
            }
        }

        if ($resYl = $conn->query("SELECT year_id FROM year_levels ORDER BY year_id ASC LIMIT 1")) {
            if ($rowYl = $resYl->fetch_assoc()) {
                $yl_int = (int)$rowYl['year_id'];
            }
        }

        if ($resSec = $conn->query("SELECT section_id FROM sections ORDER BY section_id ASC LIMIT 1")) {
            if ($rowSec = $resSec->fetch_assoc()) {
                $sec_int = (int)$rowSec['section_id'];
            }
        }

        $selected_year_level = isset($_POST['selected_year_level']) ? trim($_POST['selected_year_level']) : '';
        $selected_section_name = isset($_POST['selected_section_name']) ? trim($_POST['selected_section_name']) : '';

        $selected_yl_id = null;
        $selected_sec_id = null;
        if ($selected_year_level !== '') {
            if ($stmtYL = $conn->prepare("SELECT year_id FROM year_levels WHERE level = ? OR year_name = ? ORDER BY level LIMIT 1")) {
                $stmtYL->bind_param('ss', $selected_year_level, $selected_year_level);
                if ($stmtYL->execute()) {
                    $resYL = $stmtYL->get_result();
                    if ($resYL && ($rowYL = $resYL->fetch_assoc())) {
                        $selected_yl_id = (int)$rowYL['year_id'];
                    }
                }
                $stmtYL->close();
            }
        }
        if ($selected_section_name !== '') {
            if ($stmtSEC = $conn->prepare("SELECT section_id FROM sections WHERE section_name = ? ORDER BY level, section_name LIMIT 1")) {
                $stmtSEC->bind_param('s', $selected_section_name);
                if ($stmtSEC->execute()) {
                    $resSEC = $stmtSEC->get_result();
                    if ($resSEC && ($rowSEC = $resSEC->fetch_assoc())) {
                        $selected_sec_id = (int)$rowSEC['section_id'];
                    }
                }
                $stmtSEC->close();
            }
        }

        $enrolledCount  = 0;
        $duplicateCount = 0;
        $errorCount     = 0;

        foreach ($schedule_ids as $sid_int) {
            $stmt = $conn->prepare("SELECT subject_id FROM schedule WHERE schedule_id = ?");
            $stmt->bind_param('i', $sid_int);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();
            if (!$row) {
                $errorCount++;
                continue;
            }
            $subject_id = (int)$row['subject_id'];
            $sub_int  = (int)$subject_id;

            foreach ($student_ids as $student_id) {
                if ($student_id === '') {
                    continue;
                }

                $checkDup->bind_param('siiii', $student_id, $subject_id, $sid_int, $ay_int, $sem_int);
                $checkDup->execute();
                $checkDup->store_result();
                if ($checkDup->num_rows > 0) {
                    $duplicateCount++;
                    continue;
                }

                $checkNull->bind_param('sii', $student_id, $ay_int, $sem_int);
                $checkNull->execute();
                $nullRes = $checkNull->get_result();
                $nullRow = $nullRes ? $nullRes->fetch_assoc() : null;
                if ($nullRow && isset($nullRow['admission_id'])) {
                    $admId = (int)$nullRow['admission_id'];
                    $updFromNull->bind_param('iii', $sub_int, $sid_int, $admId);
                    if ($updFromNull->execute()) {
                        $enrolledCount++;
                        continue;
                    } else {
                        $errorCount++;
                        continue;
                    }
                }

                $cid_use = $cid_int; $yl_use = $yl_int; $sec_use = $sec_int;
                $findAdmMeta->bind_param('sii', $student_id, $ay_int, $sem_int);
                if ($findAdmMeta->execute()) {
                    $metaRes = $findAdmMeta->get_result();
                    if ($metaRes && ($meta = $metaRes->fetch_assoc())) {
                        if (!empty($meta['course_id']))     { $cid_use = (int)$meta['course_id']; }
                        if (!empty($meta['year_level_id']))  { $yl_use  = (int)$meta['year_level_id']; }
                        if (!empty($meta['section_id']))     { $sec_use = (int)$meta['section_id']; }
                    }
                }
                if ($selected_yl_id !== null) { $yl_use = $selected_yl_id; }
                if ($selected_sec_id !== null) { $sec_use = $selected_sec_id; }
                if (($yl_use === $yl_int && $sec_use === $sec_int) || $cid_use === $cid_int) {
                    $findAnyMeta->bind_param('s', $student_id);
                    if ($findAnyMeta->execute()) {
                        $metaAnyRes = $findAnyMeta->get_result();
                        if ($metaAnyRes && ($metaAny = $metaAnyRes->fetch_assoc())) {
                            if (!empty($metaAny['course_id']))     { $cid_use = (int)$metaAny['course_id']; }
                            if (!empty($metaAny['year_level_id']) && $selected_yl_id === null)  { $yl_use  = (int)$metaAny['year_level_id']; }
                            if (!empty($metaAny['section_id']) && $selected_sec_id === null)     { $sec_use = (int)$metaAny['section_id']; }
                        }
                    }
                }
                $ins->bind_param('siiiiiii', $student_id, $ay_int, $sem_int, $sec_use, $yl_use, $cid_use, $sub_int, $sid_int);
                if ($ins->execute()) {
                    $enrolledCount++;
                } else {
                    $errorCount++;
                }
            }
        }

        $checkDup->close();
        $checkNull->close();
        $updFromNull->close();
        $ins->close();
        $findAdmMeta->close();
        if (isset($findAnyMeta)) { $findAnyMeta->close(); }

        $msgParts = [];
        if ($enrolledCount > 0) {
            $msgParts[] = $enrolledCount === 1 ? 'Enrolled' : "Enrolled: {$enrolledCount}";
        }
        if ($duplicateCount > 0) {
            $msgParts[] = "Already enrolled: {$duplicateCount}";
        }
        if ($errorCount > 0) {
            $msgParts[] = "Errors: {$errorCount}";
        }

        if (!empty($msgParts)) {
            $message = implode("\n", $msgParts);
        } else {
            $message = 'No valid students were processed.';
        }
    }
}

// Handle teacher assignment to subjects
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_teacher'])) {
    $subject_id = $_POST['subject_id'];
    $teacher_id = $_POST['teacher_id'];
    
    if (empty($subject_id) || empty($teacher_id)) {
        $error = "Please select both subject and teacher!";
    } else {
        $stmt = $conn->prepare("UPDATE subjects SET teacher_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $teacher_id, $subject_id);
        
        if ($stmt->execute()) {
            $message = "Teacher assigned to subject successfully!";
        } else {
            $error = "Error assigning teacher: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle remove subject assignment
// Disabled for same reason: student_subjects table is not available in this DB.
if (isset($_GET['remove_assignment'])) {
    $error = "Removing assignments from this page is disabled because the legacy student_subjects table is not available. Please manage enrollments via the Enroll Student page.";
}

// Load reference data similar to enroll_students.php
$courses = $conn->query("SELECT course_id, course_code, course_name FROM courses ORDER BY course_code");
$years   = $conn->query("SELECT year_id, year_name, level FROM year_levels ORDER BY level");
$sections = $conn->query("SELECT section_id, section_name, level FROM sections ORDER BY level, section_name");
$academic_years = $conn->query("SELECT ay_id, ay_name FROM academic_years ORDER BY ay_id DESC");
$semesters = $conn->query("SELECT semester_id, ay_id, semester_now FROM semesters ORDER BY semester_id DESC");

// Get all students using new students schema (first_name, middle_name, last_name)
// We avoid non-existent columns like `name` or `year_level` here
$students = $conn->query("SELECT student_id, first_name, middle_name, last_name FROM students ORDER BY last_name, first_name, student_id");

// Preload schedules with joined info so we can show subject + time + lab + faculty for CURRENT session
$ay_id  = (int)($_SESSION['active_ay_id'] ?? 0);
$sem_id = (int)($_SESSION['active_sem_id'] ?? 0);

// Detect if 'days' column exists in schedule table
$dayColumn = 'schedule_days';
$checkCol = $conn->query("SHOW COLUMNS FROM schedule LIKE 'days'");
if ($checkCol && $checkCol->num_rows > 0) {
    $dayColumn = 'days';
}

$schedule_sql = "SELECT sc.schedule_id, sc.time_start, sc.time_end, sc.$dayColumn AS schedule_days,
                        fac.lab_name,
                        sub.subject_id, sub.subject_code, sub.subject_name,
                        emp.firstname, emp.lastname
                 FROM schedule sc
                 JOIN facilities fac ON sc.lab_id = fac.lab_id
                 JOIN subjects sub ON sc.subject_id = sub.subject_id
                 JOIN employees emp ON sc.employee_id = emp.employee_id
                 WHERE sc.academic_year_id = $ay_id AND sc.semester_id = $sem_id
                 ORDER BY sub.subject_code, sc.time_start";
$schedules = $conn->query($schedule_sql);

// Simple subject listing for 'List Of All Subjects' using normalized schema for CURRENT session
$subject_list_sql = "
    SELECT 
        sub.subject_id,
        sub.subject_code,
        sub.subject_name,
        COUNT(DISTINCT ad.student_id) AS enrolled_count
    FROM subjects sub
    LEFT JOIN admissions ad ON ad.subject_id = sub.subject_id AND ad.academic_year_id = $ay_id AND ad.semester_id = $sem_id
    GROUP BY sub.subject_id, sub.subject_code, sub.subject_name
    ORDER BY sub.subject_code
";
$subject_list = $conn->query($subject_list_sql);

// Get all teachers (disabled for now to avoid using old teachers table in src_db)
$teachers = false;

// Get current assignments grouped by subject and irregular
// Temporarily disabled listing (student_subjects table not available in this database)
$subject_assignments = [];
$irregular_assignments = [];

// Build studentOptions grouped by year_level (level) and section_name for Option B (filter by year/section)
// We derive the grouping from the admission records joined with year_level and section,
// so we do not rely on non-existent columns in the students table.
// Each key in the array is "level-section_name" (e.g., "4-A").
$studentOptions = [];
// Determine latest AY and Semester to match the term used when assigning
$latestAyId = 0; $latestSemId = 0;
if ($resAy = $conn->query("SELECT ay_id FROM academic_years ORDER BY ay_id DESC LIMIT 1")) {
    if ($rowAy = $resAy->fetch_assoc()) { $latestAyId = (int)$rowAy['ay_id']; }
}
if ($resSem = $conn->query("SELECT semester_id FROM semesters ORDER BY semester_id DESC LIMIT 1")) {
    if ($rowSem = $resSem->fetch_assoc()) { $latestSemId = (int)$rowSem['semester_id']; }
}

$student_group_sql = "
    SELECT
        s.student_id,
        s.first_name,
        s.middle_name,
        s.last_name,
        yl.level         AS year_level,
        sec.section_name AS section_name
    FROM admissions ad
    JOIN students s      ON ad.student_id = s.student_id
    JOIN year_levels yl   ON ad.year_level_id = yl.year_id
    JOIN sections sec     ON ad.section_id = sec.section_id
    WHERE ad.academic_year_id = " . (int)$latestAyId . "
      AND ad.semester_id = " . (int)$latestSemId . "
    GROUP BY s.student_id, s.first_name, s.middle_name, s.last_name, yl.level, sec.section_name
    ORDER BY yl.level, sec.section_name, s.last_name, s.first_name, s.student_id
";
if ($resStuGroup = $conn->query($student_group_sql)) {
    while ($stu = $resStuGroup->fetch_assoc()) {
        $yearLevel   = $stu['year_level'] ?? null;
        $sectionName = $stu['section_name'] ?? null;
        if ($yearLevel === null || $sectionName === null || $yearLevel === '' || $sectionName === '') {
            continue;
        }

        $key = $yearLevel . '-' . $sectionName;
        $fullName = trim(($stu['last_name'] ?? '') . ', ' . ($stu['first_name'] ?? '') . ' ' . ($stu['middle_name'] ?? ''));

        if (!isset($studentOptions[$key])) {
            $studentOptions[$key] = [];
        }

        $studentOptions[$key][] = [
            'id'   => $stu['student_id'],
            'name' => $fullName !== '' ? $fullName : $stu['student_id'],
        ];
    }
}
?>
<div class="container">
    <div class="manage-container">
        <div class="manage-header">
            <h2>Subject Assigning Management</h2>
            <div class="header-actions">
            </div>
        </div>
        <?php if ($message): ?>
            <div class="alert success">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Foldable Sections -->
        <div class="foldable-sections">
            <!-- Assign Subject to Student Section -->
            <div class="foldable-section">
                <div class="foldable-header" onclick="toggleSection('assign-student')">
                    <h3>Assign Subject to Student</h3>
                    <span class="foldable-arrow">▼</span>
                </div>
                <div class="foldable-content" id="assign-student">
                    <form method="POST" class="assignment-form" id="assignForm">
                        <input type="hidden" name="assign_subject" value="1">
                        <input type="hidden" name="selected_year_level" id="selected_year_level" value="">
                        <input type="hidden" name="selected_section_name" id="selected_section_name" value="">
                        <div class="form-row">
                            <div class="form-group" style="min-width:260px;">
                                <label>Students *</label>
                                <div style="display:flex; gap:8px; margin-bottom:8px; flex-wrap:wrap;">
                                    <div style="flex:1; min-width:120px;">
                                        <select id="filter_year" style="width:100%; padding:6px 8px; border-radius:6px; border:1px solid #e1e5e9;">
                                            <option value="">Select year level</option>
                                            <?php if ($years && $years->num_rows > 0): ?>
                                                <?php $years->data_seek(0); while ($y = $years->fetch_assoc()): ?>
                                                    <option value="<?= htmlspecialchars($y['level']) ?>"><?= htmlspecialchars($y['year_name']) ?></option>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div style="flex:1; min-width:120px;">
                                        <select id="filter_section" style="width:100%; padding:6px 8px; border-radius:6px; border:1px solid #e1e5e9;">
                                            <option value="">Select section</option>
                                            <?php if ($sections && $sections->num_rows > 0): ?>
                                                <?php $sections->data_seek(0); while ($sec = $sections->fetch_assoc()): ?>
                                                    <option value="<?= htmlspecialchars($sec['section_name']) ?>" data-level="<?= isset($sec['level']) ? (int)$sec['level'] : 0 ?>"><?= htmlspecialchars($sec['section_name']) ?></option>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>
                                <div style="margin-bottom: 8px; display:flex; gap:8px; align-items:center;">
                                    <input type="text" id="student_search" placeholder="Search students" style="flex:1; padding:6px 8px; border-radius:6px; border:1px solid #e1e5e9;">
                                    <label for="select_all_students" style="font-weight:normal; display:flex; align-items:center; gap:6px;"><input type="checkbox" id="select_all_students"> Select All</label>
                                </div>
                                <div id="student_checkbox_list" style="max-height:180px;overflow-y:auto;border:1px solid #e1e5e9;border-radius:8px;padding:8px;background:#fff;min-width:240px;">
                                    <span style="color:#888;">Select year and section</span>
                                </div>
                            </div>

                            <!-- Schedule (subject + lab + faculty + time) -->
                            <div class="form-group" style="min-width:260px;">
                                <label>Schedule (Subject / Lab / Faculty / Time) *</label>
                                <div style="margin-bottom:8px; display:flex; gap:8px;">
                                    <input type="text" id="schedule_search" placeholder="Search schedule" style="flex:1; padding:6px 8px; border-radius:6px; border:1px solid #e1e5e9;">
                                    <label style="display:flex; align-items:center; gap:6px; font-weight:normal;"><input type="checkbox" id="select_all_schedules"> Select All</label>
                                </div>
                                <div id="schedule_checkbox_list" style="max-height:180px;overflow-y:auto;border:1px solid #e1e5e9;border-radius:8px;padding:8px;background:#fff;min-width:240px;">
                                    <?php if ($schedules): while ($sc = $schedules->fetch_assoc()): ?>
                                        <?php
                                            $days_display = !empty($sc['schedule_days']) ? " ({$sc['schedule_days']})" : "";
                                            $label = $sc['subject_code'] . ' - ' . $sc['subject_name'] . ' | ' .
                                                     $sc['lab_name'] . ' | ' .
                                                     date('g:i A', strtotime($sc['time_start'])) . '-' . date('g:i A', strtotime($sc['time_end'])) . $days_display . ' | ' .
                                                     $sc['lastname'] . ', ' . $sc['firstname'];
                                        ?>
                                        <label style="display:block; margin-bottom:4px;" data-label="<?= htmlspecialchars(strtolower($label)) ?>">
                                            <input type="checkbox" name="schedule_ids[]" value="<?= (int)$sc['schedule_id'] ?>"> <?= htmlspecialchars($label) ?>
                                        </label>
                                    <?php endwhile; endif; ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn-primary">Assign Subject</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Assign Teacher to Subject Section -->
            
    

            <!-- Current Assignments Section -->
            <div class="foldable-section">
                <div class="foldable-header" onclick="toggleSection('current-assignments')">
                    <h3>List Of All Subjects</h3>
                    <span class="foldable-arrow">▼</span>
                </div>
                <div class="foldable-content" id="current-assignments">
                    <div class="table-container">
                        <?php if ($subject_list && $subject_list->num_rows > 0): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Subject Code</th>
                                        <th>Subject Name</th>
                                        <th>Enrolled Students</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($sub = $subject_list->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($sub['subject_code']) ?></td>
                                            <td><?= htmlspecialchars($sub['subject_name']) ?></td>
                                            <td><?= (int)$sub['enrolled_count'] ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="no-data">No subjects found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Student options by year-section
const studentOptions = <?php echo json_encode($studentOptions); ?>;
const studentCheckboxList = document.getElementById('student_checkbox_list');
const yearSelect = document.getElementById('filter_year');
const sectionSelect = document.getElementById('filter_section');
const selectAllCheckbox = document.getElementById('select_all_students');
const studentSearchInput = document.getElementById('student_search');

// Schedule controls
const scheduleCheckboxList = document.getElementById('schedule_checkbox_list');
const scheduleSearchInput = document.getElementById('schedule_search');
const selectAllSchedules = document.getElementById('select_all_schedules');

function updateStudentCheckboxList() {
    const year = yearSelect.value;
    const section = sectionSelect.value;
    studentCheckboxList.innerHTML = '';
    if (year && section) {
        const key = year + '-' + section;
        if (studentOptions[key]) {
            studentOptions[key].forEach(stu => {
                const label = document.createElement('label');
                label.style.display = 'block';
                label.style.marginBottom = '4px';
                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.name = 'student_ids[]';
                cb.value = stu.id;
                label.setAttribute('data-label', (stu.id + ' ' + stu.name).toLowerCase());
                label.appendChild(cb);
                label.appendChild(document.createTextNode(' ' + stu.id + ' - ' + stu.name));
                studentCheckboxList.appendChild(label);
            });
        } else {
            studentCheckboxList.innerHTML = '<span style="color:#888;">No students found for this year/section</span>';
        }
    } else {
        studentCheckboxList.innerHTML = '<span style="color:#888;">Select year and section</span>';
    }
    // Reset select all checkbox
    if (selectAllCheckbox) selectAllCheckbox.checked = false;
    // Reapply search filter after repopulating
    filterStudentList();
}
yearSelect.addEventListener('change', updateStudentCheckboxList);
sectionSelect.addEventListener('change', updateStudentCheckboxList);

// Filter Section options to those matching the selected Year Level
yearSelect.addEventListener('change', function(){
    const selectedYear = yearSelect.value ? parseInt(yearSelect.value, 10) : null;
    const opts = Array.from(sectionSelect.options);
    opts.forEach(function(opt, idx){
        if (idx === 0) { opt.style.display = ''; return; }
        const lvl = parseInt(opt.getAttribute('data-level') || '0', 10);
        opt.style.display = (selectedYear === null || isNaN(selectedYear)) ? '' : (lvl === selectedYear ? '' : 'none');
    });
    // Reset section if hidden by filter
    const cur = sectionSelect.options[sectionSelect.selectedIndex];
    if (cur && cur.style.display === 'none') { sectionSelect.selectedIndex = 0; }
});

// Sync selected year/section to hidden inputs so PHP can use them
const hiddenYear = document.getElementById('selected_year_level');
const hiddenSection = document.getElementById('selected_section_name');
function syncHiddenSelections() {
    if (hiddenYear) hiddenYear.value = yearSelect.value || '';
    if (hiddenSection) hiddenSection.value = sectionSelect.value || '';
}
yearSelect.addEventListener('change', syncHiddenSelections);
sectionSelect.addEventListener('change', syncHiddenSelections);
document.getElementById('assignForm').addEventListener('submit', function(){
    syncHiddenSelections();
});

// Select All functionality
if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener('change', function() {
        const checkboxes = studentCheckboxList.querySelectorAll('input[type="checkbox"][name="student_ids[]"]');
        checkboxes.forEach(cb => { cb.checked = selectAllCheckbox.checked; });
    });
    // When student checkboxes change, update select all state
    studentCheckboxList.addEventListener('change', function(e) {
        if (e.target && e.target.type === 'checkbox' && e.target.name === 'student_ids[]') {
            const checkboxes = studentCheckboxList.querySelectorAll('input[type="checkbox"][name="student_ids[]"]');
            const checked = studentCheckboxList.querySelectorAll('input[type="checkbox"][name="student_ids[]"]:checked');
            selectAllCheckbox.checked = checkboxes.length > 0 && checked.length === checkboxes.length;
        }
    });
}

// Student search filter
function filterStudentList() {
    const q = (studentSearchInput?.value || '').toLowerCase();
    const labels = studentCheckboxList.querySelectorAll('label');
    labels.forEach(lab => {
        const text = lab.getAttribute('data-label') || lab.textContent.toLowerCase();
        lab.style.display = text.includes(q) ? 'block' : 'none';
    });
}
if (studentSearchInput) {
    studentSearchInput.addEventListener('input', filterStudentList);
}

// Schedule search filter
function filterScheduleList() {
    const q = (scheduleSearchInput?.value || '').toLowerCase();
    const labels = scheduleCheckboxList.querySelectorAll('label');
    labels.forEach(lab => {
        const text = lab.getAttribute('data-label') || lab.textContent.toLowerCase();
        lab.style.display = text.includes(q) ? 'block' : 'none';
    });
}
if (scheduleSearchInput) {
    scheduleSearchInput.addEventListener('input', filterScheduleList);
}

// Select all schedules
if (selectAllSchedules) {
    selectAllSchedules.addEventListener('change', function() {
        const checkboxes = scheduleCheckboxList.querySelectorAll('input[type="checkbox"][name="schedule_ids[]"]');
        checkboxes.forEach(cb => { if (cb.offsetParent !== null) cb.checked = selectAllSchedules.checked; });
    });
    scheduleCheckboxList.addEventListener('change', function(e) {
        if (e.target && e.target.type === 'checkbox' && e.target.name === 'schedule_ids[]') {
            const visible = Array.from(scheduleCheckboxList.querySelectorAll('label')).filter(l => l.style.display !== 'none');
            const boxes = visible.map(l => l.querySelector('input[type="checkbox"][name="schedule_ids[]"]')).filter(Boolean);
            const allChecked = boxes.length > 0 && boxes.every(cb => cb.checked);
            selectAllSchedules.checked = allChecked;
        }
    });
}

// Foldable sections functionality
function toggleSection(sectionId) {
    const content = document.getElementById(sectionId);
    const arrow = content.previousElementSibling.querySelector('.foldable-arrow');
    
    if (content.style.display === 'none' || content.style.display === '') {
        content.style.display = 'block';
        arrow.textContent = '▼';
    } else {
        content.style.display = 'none';
        arrow.textContent = '▶';
    }
}

function toggleSubject(subjectId) {
    const content = document.getElementById(subjectId);
    const arrow = content.previousElementSibling.querySelector('.subject-arrow');
    
    if (content.style.display === 'none' || content.style.display === '') {
        content.style.display = 'block';
        arrow.textContent = '▼';
    } else {
        content.style.display = 'none';
        arrow.textContent = '▶';
    }
}

// Initialize all sections as expanded by default
document.addEventListener('DOMContentLoaded', function() {
    // Main sections
    document.querySelectorAll('.foldable-content').forEach(section => {
        section.style.display = 'block';
    });
    
    // Subject groups
    document.querySelectorAll('.subject-content').forEach(subject => {
        subject.style.display = 'block';
    });
});
</script>

<style>
    body {
        font-family: 'Segoe UI', sans-serif;
        margin: 0;
        padding: 0;
        background: linear-gradient(to right, #74ebd5, #ACB6E5);
        color: #333;
    }

    .container {
        max-width: 1300px;
        margin: 30px auto;
        padding: 0 24px;
        margin-left: 40rem;
        box-sizing: border-box;
    }
    @media screen and (max-width:900px) {
        .container { margin-left: 0; padding: 0 12px; }
    }

    .manage-container {
        background: white;
        border-radius: 18px;
        padding: 36px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }

    .manage-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 15px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .manage-header h2 {
        margin: 0;
        color: #003366;
        font-size: 2rem;
        font-weight: 800;
    }

    .foldable-sections {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .foldable-section {
        background: #f8f9fa;
        border-radius: 12px;
        border: 1px solid #e9ecef;
        overflow: hidden;
    }

    .foldable-header {
        background: #0074D9;
        color: white;
        padding: 20px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background-color 0.3s ease;
    }

    .foldable-header:hover {
        background: #005fa3;
    }

    .foldable-header h3 {
        margin: 0;
        font-size: 1.4rem;
        font-weight: 700;
    }

    .foldable-arrow {
        font-size: 1.2rem;
        transition: transform 0.3s ease;
    }

    .foldable-content {
        padding: 24px;
        background: white;
    }

    .subject-group {
        margin-bottom: 25px;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        overflow: hidden;
    }

    .subject-header {
        background: #f1f3f4;
        padding: 15px 20px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #e9ecef;
    }

    .subject-header:hover {
        background: #e8eaed;
    }

    .subject-header h4 {
        margin: 0;
        color: #0056b3;
        font-size: 1.2rem;
        font-weight: 600;
    }

    .subject-arrow {
        color: #666;
        transition: transform 0.3s ease;
    }

    .subject-content {
        padding: 20px;
        background: white;
    }

    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-weight: 500;
    }

    .alert.success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .alert.error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .assignment-form {
        margin-bottom: 0;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr auto;
        gap: 16px;
        align-items: end;
    }

    .form-group {
        margin-bottom: 0;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #333;
    }

    .form-group select {
        width: 100%;
        padding: 14px;
        border: 2px solid #e1e5e9;
        border-radius: 10px;
        font-size: 18px;
        transition: border-color 0.3s ease;
        box-sizing: border-box;
    }

    .form-group select:focus {
        outline: none;
        border-color: #0074D9;
        box-shadow: 0 0 0 3px rgba(0, 116, 217, 0.1);
    }

    .btn-primary {
        background: #0074D9;
        color: white;
        border: none;
        padding: 14px 26px;
        border-radius: 10px;
        font-size: 18px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        background: #005fa3;
        transform: translateY(-2px);
    }

    .table-container {
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }

    thead {
        background-color: #0074D9;
        color: white;
    }

    th, td {
        padding: 12px;
        border: 1px solid #ddd;
        text-align: left;
    }

    th {
        font-weight: 600;
    }

    .actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .btn-delete {
        background: #dc3545;
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 14px;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .btn-delete:hover {
        background: #c82333;
        transform: translateY(-1px);
    }

    .no-data {
        text-align: center;
        color: #6c757d;
        font-style: italic;
        padding: 40px;
    }

    .no-teacher {
        color: #6c757d;
        font-style: italic;
    }

    @media screen and (max-width: 768px) {
        .container {
            margin: 15px auto;
        }

        .manage-container {
            padding: 20px;
        }

        .manage-header {
            flex-direction: column;
            text-align: center;
        }

        .form-row {
            grid-template-columns: 1fr;
        }

        .actions {
            flex-direction: column;
        }
        
        table {
            font-size: 14px;
        }
        
        th, td {
            padding: 8px;
        }
        
        .foldable-header {
            padding: 15px;
        }
        
        .foldable-content {
            padding: 15px;
        }
        
        .subject-header {
            padding: 12px 15px;
        }
        
        .subject-content {
            padding: 15px;
        }
    }
</style>

<?php include '../includes/footer.php'; ?>



