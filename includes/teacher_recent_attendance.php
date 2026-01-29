<?php
session_start();
include __DIR__ . '/db.php';
if (!isset($_SESSION['teacher_id'])) {
    exit('Not authorized');
}
$teacher_id = $_SESSION['teacher_id'];
$recent_query = $conn->prepare("
    SELECT a.*, s.name, s.section 
    FROM attendance a 
    JOIN students s ON a.student_id = s.student_id 
    JOIN student_subjects ss ON s.student_id = ss.student_id
    JOIN subjects sub ON ss.subject_id = sub.id
    WHERE DATE(a.scan_time) = CURDATE()
    AND sub.teacher_id = (SELECT id FROM teachers WHERE teacher_id = ?)
    AND a.id = (
        SELECT MAX(id) FROM attendance
        WHERE student_id = a.student_id
          AND subject_id = a.subject_id
          AND DATE(scan_time) = CURDATE()
    )
    ORDER BY a.scan_time DESC 
    LIMIT 10
");
$recent_query->bind_param("s", $teacher_id);
$recent_query->execute();
$recent = $recent_query->get_result();
?>
<table class="min-w-full divide-y divide-gray-200 rounded-xl overflow-hidden shadow">
    <thead class="bg-gray-50">
        <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scan Time</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
        </tr>
    </thead>
    <tbody class="bg-white divide-y divide-gray-200">
        <?php while ($row = $recent->fetch_assoc()): ?>
        <tr class="hover:bg-blue-50 transition">
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                <?= htmlspecialchars($row['student_id']) ?>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                <?= htmlspecialchars($row['name']) ?>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                <?= htmlspecialchars($row['section']) ?>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                <?= date('h:i:s A', strtotime($row['scan_time'])) ?>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <?php if ($row['status'] === 'Present'): ?>
                    <span class="inline-flex px-2 py-1 font-semibold rounded-full" style="color:#00FF00; background: transparent; font-size:1rem;">
                        Present
                    </span>
                <?php elseif ($row['status'] === 'Late'): ?>
                    <span class="inline-flex px-2 py-1 font-semibold rounded-full" style="color:#FFD700; background: transparent; font-size:1rem;">
                        Late
                    </span>
                <?php elseif ($row['status'] === 'Pending Sign Out'): ?>
                    <!-- Presentation-only: show Pending Time Out while DB uses 'Pending Sign Out' internally -->
                    <span class="inline-flex px-2 py-1 font-semibold rounded-full" style="color:#38bdf8; background: transparent; font-size:1rem;">
                        Pending Time Out
                    </span>
                <?php else: ?>
                    <span class="inline-flex px-2 py-1 font-semibold rounded-full" style="color:#FF0000; background: transparent; font-size:1rem;">
                        Signed Out
                    </span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table> 