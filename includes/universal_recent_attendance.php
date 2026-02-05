<?php
// Ensure timezone is set
if (!ini_get('date.timezone')) {
    date_default_timezone_set('Asia/Manila');
}
session_start();
include __DIR__ . '/db.php';

$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$teacher_id = isset($_GET['teacher_id']) ? $_GET['teacher_id'] : null;

if ($type === 'teacher' && !$teacher_id && isset($_SESSION['teacher_id'])) {
    // In new schema, teacher_id in session is employees.employee_id
    $teacher_id = $_SESSION['teacher_id'];
}

if ($type === 'teacher' && !$teacher_id) {
    exit('Not authorized');
}

$limit_int = max(1, intval($limit));

// =============================
// Build base SQL using src_db.attendance
// attendance -> admission -> students -> year_level -> section -> schedule -> subject -> facility
// =============================
$baseSql = "
    SELECT 
        st.student_id,
        CONCAT(COALESCE(st.last_name,''), ', ', COALESCE(st.first_name,''), ' ', COALESCE(st.middle_name,'')) AS name,
        sec.section_name AS section,
        yl.year_name     AS year_level,
        pa.pc_number,

        a.attendance_date,
        a.time_in,
        a.time_out,
        subj.subject_name,
        fac.lab_name
    FROM attendance a
    JOIN admissions ad ON a.admission_id = ad.admission_id
    JOIN students st  ON ad.student_id   = st.student_id
    LEFT JOIN year_levels yl ON ad.year_level_id = yl.year_id
    LEFT JOIN sections    sec ON ad.section_id    = sec.section_id
    JOIN schedule sc ON a.schedule_id = sc.schedule_id
    LEFT JOIN subjects  subj ON sc.subject_id = subj.subject_id
    LEFT JOIN facilities fac  ON sc.lab_id     = fac.lab_id
    LEFT JOIN pc_assignment pa ON pa.student_id = st.student_id AND pa.lab_id = sc.lab_id
";

$where = [];
if ($type === 'teacher') {
    // Filter by this teacher's schedules (employees.employee_id stored in schedule.employee_id)
    $where[] = 'sc.employee_id = ?';
}
if ($type === 'admin') {
    // Admin today only
    $where[] = 'a.attendance_date = CURDATE()';
}
// type === 'all' => no date restriction

if ($where) {
    $baseSql .= ' WHERE ' . implode(' AND ', $where);
}

$baseSql .= ' ORDER BY a.attendance_date DESC, a.time_in DESC LIMIT ' . intval($limit_int);

// Prepare and bind
if ($type === 'teacher') {
    $stmt = $conn->prepare($baseSql);
    $tid_int = (int)$teacher_id;
    $stmt->bind_param('i', $tid_int);
} else {
    $stmt = $conn->prepare($baseSql);
}

$rows = [];
if ($stmt && $stmt->execute()) {
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $stmt->close();
}

// Fallback empty result set if needed
if (!$rows) {
    $rows = [];
}
?>

<table class="min-w-full divide-y divide-gray-200 rounded-xl overflow-hidden shadow">
    <thead class="bg-gray-50">
        <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
            <?php if ($type === 'admin' || $type === 'all'): ?>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
            <?php endif; ?>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
            <?php if ($type === 'admin' || $type === 'all'): ?>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year</th>
            <?php endif; ?>
            <?php if ($type === 'admin' || $type === 'all'): ?>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subjects</th>
            <?php endif; ?>
            <?php if ($type === 'admin' || $type === 'all'): ?>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lab</th> <!-- ADDED: Lab Column -->
            <?php endif; ?>
            <?php if ($type === 'admin' || $type === 'all'): ?>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PC Number</th>
            <?php endif; ?>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time In</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time Out</th>
        </tr>
    </thead>
    <tbody class="bg-white divide-y divide-gray-200">
        <?php foreach ($rows as $row): ?>
        <tr class="hover:bg-blue-50 transition">
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                <?= htmlspecialchars($row['student_id']) ?>
            </td>
            <?php if ($type === 'admin' || $type === 'all'): ?>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <?= htmlspecialchars($row['name'] ?? '-') ?>
                </td>
            <?php endif; ?>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                <?= htmlspecialchars($row['section'] ?? '-') ?>
            </td>
            <?php if ($type === 'admin' || $type === 'all'): ?>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <?= isset($row['year_level']) ? htmlspecialchars($row['year_level']) : '-' ?>
                </td>
            <?php endif; ?>
            <?php if ($type === 'admin' || $type === 'all'): ?>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <?= htmlspecialchars($row['subject_name'] ?? '-') ?>
                </td>
            <?php endif; ?>
            <?php if ($type === 'admin' || $type === 'all'): ?>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <?= htmlspecialchars($row['lab_name'] ?? '-') ?> <!-- Lab Data from facility -->
                </td>
            <?php endif; ?>
            <?php if ($type === 'admin' || $type === 'all'): ?>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <?= htmlspecialchars($row['pc_number'] ?? '') ?>
                </td>
            <?php endif; ?>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                <?= $row['time_in'] ? date('Y-m-d h:i:s A', strtotime($row['attendance_date'] . ' ' . $row['time_in'])) : '-' ?>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                <?= $row['time_out'] ? date('Y-m-d h:i:s A', strtotime($row['attendance_date'] . ' ' . $row['time_out'])) : '-' ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php
// No open statements here; everything was closed after fetching rows.
?>