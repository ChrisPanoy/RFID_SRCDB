<?php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../includes/db.php';

// Session variables
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Academic Year and Semester filters (default to active session)
$ay_filter = isset($_GET['ay_id']) ? (int)$_GET['ay_id'] : ($_SESSION['active_ay_id'] ?? 0);
$sem_filter = isset($_GET['semester_id']) ? (int)$_GET['semester_id'] : ($_SESSION['active_sem_id'] ?? 0);
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$from = isset($_GET['from']) ? $_GET['from'] : '';
$to = isset($_GET['to']) ? $_GET['to'] : '';
$gender_filter = isset($_GET['gender']) ? $_GET['gender'] : '';
$show_all = isset($_GET['show_all']) && $_GET['show_all'] == '1';

// validate date helper
function valid_date($d){
  if (!$d) return false;
  $dt = DateTime::createFromFormat('Y-m-d', $d);
  return $dt && $dt->format('Y-m-d') === $d;
}

// Build WHERE clauses
$wheres = [];

if (!$show_all) {
  // Filter by attendance_date
  if (valid_date($from) && valid_date($to)) {
    if ($from > $to) { $tmp = $from; $from = $to; $to = $tmp; }
    $wheres[] = "att.attendance_date BETWEEN '$from' AND '$to'";
  } elseif (valid_date($date)) {
    $wheres[] = "att.attendance_date = '$date'";
  }
}

if ($gender_filter) {
  $g = $conn->real_escape_string($gender_filter);
  $wheres[] = "st.gender = '$g'";
}

if ($sem_filter > 0) {
  $wheres[] = "a.semester_id = $sem_filter";
}
if ($ay_filter > 0) {
  $wheres[] = "a.academic_year_id = $ay_filter";
}

$where_sql = count($wheres) ? 'WHERE ' . implode(' AND ', $wheres) : '';

// Fetch attendance joined with normalized schema (admission, students, section, year_level, subject, schedule, facility)
$sql = "
    SELECT
        att.attendance_id,
        att.attendance_date,
        att.time_in,
        att.time_out,
        att.status,
        st.student_id,
        st.gender,
        COALESCE(
            pa.pc_number,
            (SELECT paa.pc_number FROM pc_assignment paa WHERE paa.student_id = st.student_id ORDER BY paa.date_assigned DESC LIMIT 1)
        ) AS pc_number,
        sec.section_name AS section,
        yl.year_name     AS year_level,
        CONCAT(IFNULL(sub.subject_code,''),
               CASE WHEN sub.subject_code IS NOT NULL AND sub.subject_name IS NOT NULL THEN ' - ' ELSE '' END,
               IFNULL(sub.subject_name,'')) AS subject,
        fac.lab_name     AS lab
    FROM attendance att
    JOIN admissions a   ON att.admission_id = a.admission_id
    JOIN students st   ON a.student_id     = st.student_id
    LEFT JOIN sections sec     ON a.section_id     = sec.section_id
    LEFT JOIN year_levels yl   ON a.year_level_id  = yl.year_id
    LEFT JOIN subjects sub     ON a.subject_id     = sub.subject_id
    LEFT JOIN schedule sc     ON att.schedule_id  = sc.schedule_id
    LEFT JOIN facilities fac    ON sc.lab_id        = fac.lab_id
    LEFT JOIN pc_assignment pa ON pa.student_id   = st.student_id AND pa.lab_id = fac.lab_id
    $where_sql
    ORDER BY att.attendance_date DESC, st.student_id ASC, att.time_in ASC
";
$res = $conn->query($sql);

$rows=[];
if($res){ while($r=$res->fetch_assoc()){ $rows[]=$r; } }

// No need to pair; each row already has time_in/time_out for a class
$all_pairs = $rows;

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? min(30, max(1,intval($_GET['per_page']))) : 25;
$total_rows = count($all_pairs);
$total_pages = max(1, ceil($total_rows/$per_page));
if($page > $total_pages) $page=$total_pages;
$offset=($page-1)*$per_page;
$pairs_page = array_slice($all_pairs,$offset,$per_page);

// For rendering
$all = new class($pairs_page) {
  private $data; private $idx=0; public $num_rows=0;
  public function __construct($d){ $this->data=array_values($d); $this->num_rows=count($this->data); }
  public function fetch_assoc(){ if($this->idx<count($this->data)) return $this->data[$this->idx++]; return false; }
};
?>
<?php if (!$show_all): ?>
  <form method="get" style="display:flex;justify-content:center;align-items:center;margin-bottom:18px;">
    <div style="background:#fff;padding:12px 24px;border-radius:12px;box-shadow:0 2px 12px rgba(99,102,241,0.06);display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
      <label for="from" style="font-weight:600;color:#222;display:flex;align-items:center;gap:6px;">
        <span style="color:#6366f1;"><i class="fas fa-calendar-alt"></i></span> From:
      </label>
      <input type="date" id="from" name="from" value="<?= htmlspecialchars($from) ?>" style="padding:8px 14px;border-radius:6px;border:1px solid #ccc;font-size:1rem;">

      <label for="to" style="font-weight:600;color:#222;display:flex;align-items:center;gap:6px;margin-left:6px;">
        <span style="color:#6366f1;"><i class="fas fa-calendar-alt"></i></span> To:
      </label>
      <input type="date" id="to" name="to" value="<?= htmlspecialchars($to) ?>" style="padding:8px 14px;border-radius:6px;border:1px solid #ccc;font-size:1rem;">

      <label for="gender" style="font-weight:600;color:#222;display:flex;align-items:center;gap:6px;margin-left:6px;">
        <span style="color:#6366f1;"><i class="fas fa-venus-mars"></i></span> Gender:
      </label>
      <select id="gender" name="gender" style="padding:8px 14px;border-radius:6px;border:1px solid #ccc;font-size:1rem;">
        <option value="">All</option>
        <option value="Male" <?= $gender_filter === 'Male' ? 'selected' : '' ?>>Male</option>
        <option value="Female" <?= $gender_filter === 'Female' ? 'selected' : '' ?>>Female</option>
      </select>

      <button type="submit" style="background:#1976d2;color:#fff;font-weight:600;padding:8px 18px;border-radius:6px;border:none;font-size:1rem;cursor:pointer;transition:background 0.2s;margin-left:8px;">Filter</button>
    </div>
  </form>
<?php endif; ?>

<!-- Export and Print Buttons -->
<div style="display:flex;justify-content:center;align-items:center;margin-bottom:18px;gap:12px;">
  <?php
    $params = [];
    if ($gender_filter) $params['gender'] = $gender_filter;
    if (!empty($from)) $params['from'] = $from;
    if (!empty($to)) $params['to'] = $to;
    if (!empty($date) && empty($from) && empty($to)) $params['date'] = $date;
    if ($show_all) $params['show_all'] = '1';
    $query_str = http_build_query($params);
  ?>
  <a href="../includes/export_csv.php?<?= $query_str ?>" style="background:#28a745;color:#fff;font-weight:600;padding:10px 20px;border-radius:8px;text-decoration:none;display:flex;align-items:center;gap:8px;transition:background 0.2s;">
    <i class="fas fa-file-csv"></i> CSV
  </a>
  <a href="../includes/print_report.php?<?= $query_str ?>" target="_blank" style="background:#dc3545;color:#fff;font-weight:600;padding:10px 20px;border-radius:8px;text-decoration:none;display:flex;align-items:center;gap:8px;transition:background 0.2s;">
    <i class="fas fa-print"></i> Print
  </a>
</div>

<!-- Attendance Table -->
<div style="display: flex; justify-content: center; margin-top: 32px;">
  <div style="background: #fff; box-shadow: 0 4px 24px rgba(0,0,0,0.08); border-radius: 18px; padding: 32px; max-width: 1200px; width: 100%;">
    <div style="overflow-x: auto;">
      <table style="width: 100%; border-collapse: collapse; font-size: 1.08rem;">
        <thead>
            <tr style="background: #6366f1; color: #fff;">
            <th style="padding: 12px 10px; font-weight: bold;">Student ID</th>
            <th style="padding: 12px 10px; font-weight: bold;">Section</th>
            <th style="padding: 12px 10px; font-weight: bold;">Year</th>
            <th style="padding: 12px 10px; font-weight: bold;">Gender</th>
            <th style="padding: 12px 10px; font-weight: bold;">PC Number</th>
            <th style="padding: 12px 10px; font-weight: bold;">Subject</th>
            <th style="padding: 12px 10px; font-weight: bold;">Lab</th>
            <th style="padding: 12px 10px; font-weight: bold;">Time In</th>
            <th style="padding: 12px 10px; font-weight: bold;">Time Out</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($all && $all->num_rows > 0): $i = 0; while ($row = $all->fetch_assoc()): ?>
            <tr style="background: <?= $i % 2 === 0 ? '#f3f4f6' : '#fff' ?>; transition: background 0.2s;" onmouseover="this.style.background='#e0e7ff'" onmouseout="this.style.background='<?= $i % 2 === 0 ? '#f3f4f6' : '#fff' ?>'">
              <td style="padding: 10px 8px; border-bottom: 1px solid #e5e7eb; text-align: center;"><?= htmlspecialchars($row['student_id']) ?></td>
              <td style="padding: 10px 8px; border-bottom: 1px solid #e5e7eb; text-align: center;"><?= htmlspecialchars($row['section']) ?></td>
              <td style="padding: 10px 8px; border-bottom: 1px solid #e5e7eb; text-align: center;"><?= $row['year_level'] ?></td>
              <td style="padding: 10px 8px; border-bottom: 1px solid #e5e7eb; text-align: center;"><?= htmlspecialchars($row['gender'] ?? '-') ?></td>
              <td style="padding: 10px 8px; border-bottom: 1px solid #e5e7eb; text-align: center;"><?= htmlspecialchars($row['pc_number'] ?? '-') ?></td>
              <td style="padding: 10px 8px; border-bottom: 1px solid #e5e7eb; text-align: center;"><?= htmlspecialchars($row['subject'] ?? '-') ?></td>
              <td style="padding: 10px 8px; border-bottom: 1px solid #e5e7eb; text-align: center;"><?= htmlspecialchars($row['lab'] ?? '-') ?></td>
              <td style="padding: 10px 8px; border-bottom: 1px solid #e5e7eb; text-align: center;"><?= $row['time_in'] ? date('Y-m-d h:i:s A', strtotime($row['time_in'])) : '-' ?></td>
              <td style="padding: 10px 8px; border-bottom: 1px solid #e5e7eb; text-align: center;"><?= $row['time_out'] ? date('Y-m-d h:i:s A', strtotime($row['time_out'])) : '-' ?></td>
            </tr>
          <?php $i++; endwhile; else: ?>
            <tr><td colspan="9" style="text-align:center; padding: 18px; color: #888;">No attendance records found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="display: flex; justify-content: center; margin-top: 20px; gap: 10px;">
      <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" style="padding: 8px 16px; background: #6366f1; color: white; text-decoration: none; border-radius: 4px;">Previous</a>
      <?php endif; ?>
      <span style="padding: 8px 16px; background: #f3f4f6; border-radius: 4px;">Page <?= $page ?> of <?= $total_pages ?></span>
      <?php if ($page < $total_pages): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" style="padding: 8px 16px; background: #6366f1; color: white; text-decoration: none; border-radius: 4px;">Next</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
