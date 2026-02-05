<?php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../includes/db.php';
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Use normalized schema similar to universal_recent_attendance.php:
// attendance -> admission -> students -> year_level -> section -> schedule -> subject -> facility
$date_safe = $conn->real_escape_string($date);

$sql = "
    SELECT 
        st.student_id,
        sec.section_name AS section,
        yl.year_name     AS year_level,
        subj.subject_name,
        fac.lab_name     AS lab,
        pa.pc_number,
        a.status,
        a.attendance_date,
        a.time_in,
        a.time_out
    FROM attendance a
    JOIN admissions ad ON a.admission_id = ad.admission_id
    JOIN students st  ON ad.student_id   = st.student_id
    LEFT JOIN year_levels yl ON ad.year_level_id = yl.year_id
    LEFT JOIN sections    sec ON ad.section_id    = sec.section_id
    JOIN schedule sc ON a.schedule_id = sc.schedule_id
    LEFT JOIN subjects  subj ON sc.subject_id = subj.subject_id
    LEFT JOIN facilities fac  ON sc.lab_id     = fac.lab_id
    LEFT JOIN pc_assignment pa ON pa.student_id = st.student_id AND pa.lab_id = sc.lab_id
    WHERE a.attendance_date = '$date_safe'
    ORDER BY a.attendance_date DESC, a.time_in DESC
    LIMIT 5
";

$recent = $conn->query($sql);
?>
<table>
    <thead>
        <tr>
            <th>Student ID</th>
            <th>Section</th>
            <th>Year</th>
            <th>Subject</th>
            <th>Lab</th>
            <th>PC Number</th>
            <th>Status</th>
            <th>Scan Time</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($recent): while ($row = $recent->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['student_id']) ?></td>
                <td><?= htmlspecialchars($row['section'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['year_level'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['subject_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['lab'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['pc_number'] ?? '') ?></td>
                <td>
                    <?php if (($row['status'] ?? '') === 'Present'): ?>
                        <span style="color:#00FF00; font-weight:700; font-size:1.05rem;">Present</span>
                    <?php else: ?>
                        <span style="color:#FF0000; font-weight:700; font-size:1.05rem;">Signed Out</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    $timePart = $row['time_in'] ?: ($row['time_out'] ?? null);
                    echo $timePart
                        ? date('Y-m-d h:i:s A', strtotime(($row['attendance_date'] ?? $date) . ' ' . $timePart))
                        : '-';
                    ?>
                </td>
            </tr>
        <?php endwhile; endif; ?>
    </tbody>
</table>