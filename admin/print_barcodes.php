<?php
include '../includes/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }

$year = isset($_GET['year']) ? trim($_GET['year']) : null;
$section = isset($_GET['section']) ? trim($_GET['section']) : null;
$all = isset($_GET['all']) ? true : false;

$where = "";
$params = [];
$types = "";
if (!$all) {
    if ($year !== null && $year !== '') {
        $where .= ($where ? " AND " : " WHERE ") . "year_level = ?";
        $types .= 's';
        $params[] = $year;
    }
    if ($section !== null && $section !== '') {
        $where .= ($where ? " AND " : " WHERE ") . "section = ?";
        $types .= 's';
        $params[] = $section;
    }
}

$sql = "SELECT id, student_id, name, section, year_level, barcode FROM students" . $where . " ORDER BY year_level, section, name";
if ($where) {
    $stmt = $conn->prepare($sql);
    if ($types) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = $conn->query($sql);
}
$students = [];
while ($row = $res->fetch_assoc()) { $students[] = $row; }
if (isset($stmt)) { $stmt->close(); }

$title = 'Print Barcodes';
if ($all) {
    $subtitle = 'All Students';
} else {
    $subtitleParts = [];
    if ($year) { $subtitleParts[] = "Year " . htmlspecialchars($year); }
    if ($section) { $subtitleParts[] = "Section " . htmlspecialchars($section); }
    $subtitle = $subtitleParts ? implode(' • ', $subtitleParts) : 'All Students';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  :root { --label-w: 62mm; --label-h: 30mm; }
  * { box-sizing: border-box; }
  body { font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif; margin: 0; background:#f5f6fb; }
  .toolbar { position: sticky; top: 0; background: white; padding: 12px 16px; border-bottom: 1px solid #e5e7eb; display:flex; align-items:center; gap:12px; z-index: 10; }
  .title { font-weight: 700; color:#1f2937; }
  .subtitle { color:#6b7280; }
  .btn { background:#4f46e5; color:white; border:none; padding:10px 14px; border-radius:10px; font-weight:600; cursor:pointer; }
  .btn.secondary { background:#6b7280; }
  .container { padding: 16px; }
  .grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(var(--label-w), 1fr)); gap: 8mm; }
  .label { width: var(--label-w); height: var(--label-h); background:white; border:1px solid #e5e7eb; border-radius:8px; padding:6px 8px; display:flex; flex-direction:column; justify-content:space-between; }
  .meta { display:flex; justify-content:space-between; font-size:11px; color:#374151; font-weight:600; }
  .name { font-size:12px; font-weight:700; color:#111827; margin-top:2px; }
  .barcode { width:100%; }
  @media print {
    .toolbar { display:none; }
    body { background: white; }
    .container { padding: 0; }
    .grid { gap: 4mm; }
    @page { size: A4; margin: 8mm; }
  }
</style>
</head>
<body>
  <div class="toolbar">
    <div>
      <div class="title"><?= htmlspecialchars($title) ?></div>
      <div class="subtitle"><?= htmlspecialchars($subtitle) ?> • <?= count($students) ?> labels</div>
    </div>
    <div style="margin-left:auto; display:flex; gap:8px;">
      <button class="btn" onclick="window.print()">Print</button>
      <a class="btn secondary" href="students.php">Back</a>
    </div>
  </div>
  <div class="container">
    <div class="grid">
      <?php foreach ($students as $s): ?>
        <div class="label">
          <div>
            <div class="meta"><span>ID: <?= htmlspecialchars($s['student_id']) ?></span><span>Y<?= htmlspecialchars($s['year_level']) ?> • Sec <?= htmlspecialchars($s['section']) ?></span></div>
            <div class="name"><?= htmlspecialchars($s['name']) ?></div>
          </div>
          <div>
            <svg class="barcode" id="bc-<?= (int)$s['id'] ?>"></svg>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/jsbarcode/dist/JsBarcode.all.min.js"></script>
  <script>
  <?php foreach ($students as $s): ?>
    JsBarcode("#bc-<?= (int)$s['id'] ?>", "<?= addslashes($s['barcode']) ?>", {format:"CODE128", width:1.2, height:32, displayValue:true, font:"Inter", fontSize:10, margin:0});
  <?php endforeach; ?>
  </script>
</body>
</html>




