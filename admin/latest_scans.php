<?php
session_start();
include '../includes/db.php';

// Ensure teacher is active (legacy backfill compatible)
if (!isset($_SESSION['teacher_id'])) {
    header('Location: ../teacher/teacher_login.php');
    exit();
}
$teacher_id = $_SESSION['teacher_id'];

// Fetch recent scans for subjects owned by the teacher
$limit = 100; // show up to 100 recent scans
$stmt = $conn->prepare("\n    SELECT a.*, s.name as student_name, s.profile_pic as student_pic, sub.subject_name\n    FROM attendance a\n    JOIN students s ON a.student_id = s.student_id\n    LEFT JOIN subjects sub ON a.subject_id = sub.id\n    WHERE a.subject_id IN (SELECT id FROM subjects WHERE teacher_id = (SELECT id FROM teachers WHERE teacher_id = ?))\n    ORDER BY a.scan_time DESC\n    LIMIT ?\n");
$stmt->bind_param('si', $teacher_id, $limit);
$stmt->execute();
$res = $stmt->get_result();
$scans = [];
while ($row = $res->fetch_assoc()) {
    $scans[] = $row;
}
$stmt->close();

function format_time($ts) {
    if (empty($ts)) return '--:--:--';
    return date('M j, Y g:i:s A', strtotime($ts));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Latest Live Scans - Attendance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', 'Segoe UI', Arial, sans-serif; }
        .scan-card { background: white; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 6px 12px rgba(0,0,0,0.04); }
        .status-present { background: linear-gradient(90deg,#06b6d4 0%,#10b981 100%); color: white; }
        .status-signedout { background: linear-gradient(90deg,#fb7185 0%,#ef4444 100%); color: white; }
        .status-other { background: linear-gradient(90deg,#9ca3af 0%,#6b7280 100%); color: white; }
        @media (max-width: 900px) { .sidebar { left: -220px; } .sidebar.open { left: 0; } }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include '../includes/sidebar.php'; ?>

    <div class="ml-80 p-6" style="margin-left:20rem;">
        <div class="max-w-6xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold">Latest Live Scans</h1>
            </div>

            <div class="grid grid-cols-1 gap-4">
                <?php if (empty($scans)): ?>
                    <div class="p-8 text-center text-gray-500">No recent scans.</div>
                <?php else: ?>
                    <?php foreach ($scans as $scan): ?>
                        <div class="scan-card p-4 flex items-center gap-4">
                            <div class="w-20 h-20 rounded-full overflow-hidden bg-gray-100 flex-shrink-0">
                                <img src="<?= htmlspecialchars(!empty($scan['student_pic']) ? '../assets/img/'.$scan['student_pic'] : '../assets/img/logo.png') ?>" alt="photo" class="w-full h-full object-cover" onerror="this.onerror=null;this.src='../assets/img/logo.png'">
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-bold text-lg"><?= htmlspecialchars($scan['student_name'] ?? $scan['student_id']) ?></div>
                                        <div class="text-sm text-gray-500">ID: <?= htmlspecialchars($scan['student_id']) ?> · Subject: <?= htmlspecialchars($scan['subject_name'] ?? 'N/A') ?></div>
                                    </div>
                                    <div class="text-right">
                                        <?php $status = $scan['status'] ?? ''; ?>
                                        <div class="inline-block px-3 py-1 rounded-full text-xs font-semibold <?= (stripos($status,'present')!==false)?'status-present':((stripos($status,'signed out')!==false)?'status-signedout':'status-other') ?>">
                                            <?= htmlspecialchars($status) ?: 'Unknown' ?>
                                        </div>
                                        <div class="text-xs text-gray-400 mt-1"><?= htmlspecialchars(format_time($scan['scan_time'])) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>




