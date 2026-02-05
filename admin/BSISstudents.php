<?php
session_start();
include '../includes/db.php';

// Authentication check before any output
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}

// Fetch BSIS course ids (code or name match)
$courseIds = [];
$courseRes = $conn->query("SELECT course_id FROM courses WHERE course_code = 'BSIS' OR course_name LIKE 'Bachelor of Science in Information System%'");
if ($courseRes) {
    while ($c = $courseRes->fetch_assoc()) {
        $courseIds[] = (int)$c['course_id'];
    }
}
if (empty($courseIds)) {
    $courseIds = [-1]; // force empty result if no BSIS course defined
}
$idList = implode(',', $courseIds);

// Handle delete by year (deletes admissions for BSIS courses for the given year level)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_year'])) {
    $yearToDelete = trim($_POST['delete_year']);
    if ($yearToDelete !== '') {
        $delSql = "DELETE a FROM admissions a\n"
               . "JOIN year_levels yl ON a.year_level_id = yl.year_id\n"
               . "WHERE a.course_id IN ($idList) AND yl.year_name = ?";
        if ($stmtDel = $conn->prepare($delSql)) {
            $stmtDel->bind_param('s', $yearToDelete);
            $stmtDel->execute();
            $stmtDel->close();
        }
    }
    header('Location: BSISstudents.php');
    exit();
}

// Group BSIS admissions by year level and section in CURRENT session
$ay_id  = (int)($_SESSION['active_ay_id'] ?? 0);
$sem_id = (int)($_SESSION['active_sem_id'] ?? 0);

$sql = "SELECT DISTINCT st.student_id, yl.year_name, sct.section_name,
               st.first_name, st.middle_name, st.last_name, st.suffix, st.gender
        FROM admissions a
        JOIN students st    ON a.student_id     = st.student_id
        JOIN year_levels yl  ON a.year_level_id  = yl.year_id
        JOIN sections sct    ON a.section_id     = sct.section_id
        JOIN courses c       ON a.course_id      = c.course_id
        WHERE a.course_id IN ($idList)
          AND a.academic_year_id = $ay_id
          AND a.semester_id = $sem_id
        ORDER BY yl.level, sct.section_name, st.last_name, st.first_name, st.student_id";

$result = $conn->query($sql);
$grouped = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $yearKey = trim($row['year_name']);
        $sectionKey = $row['section_name'];
        if (!isset($grouped[$yearKey])) {
            $grouped[$yearKey] = [];
        }
        if (!isset($grouped[$yearKey][$sectionKey])) {
            $grouped[$yearKey][$sectionKey] = [];
        }
        $grouped[$yearKey][$sectionKey][] = $row;
    }
}

$availableYears = array_values(array_keys($grouped));
$totalPages = count($availableYears);
// Determine selected year: prefer explicit 'year' param; fallback to 'page' index for backward compatibility
$selectedYear = '';
if (isset($_GET['year']) && $_GET['year'] !== '') {
    $selectedYear = (string)$_GET['year'];
} elseif (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $idx = max(1, (int)$_GET['page']) - 1;
    if (isset($availableYears[$idx])) {
        $selectedYear = (string)$availableYears[$idx];
    }
}
if ($selectedYear === '' && $totalPages > 0) {
    $selectedYear = (string)$availableYears[0];
}

// Output headers after all logic/redirects
include '../includes/header.php';
?>

<style>
    .app-content {
        margin-left: 20rem;
        padding: 2rem 1.5rem 2rem 1.5rem;
        min-height: 100vh;
        box-sizing: border-box;
    }

    .app-container {
        width: 100%;
        background: #ffffff;
        border-radius: 1rem;
        padding: 1.5rem 1.5rem 2rem 1.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    .page-title {
        text-align: center;
        margin-bottom: 0.75rem;
        font-size: 2.25rem;
        font-weight: 800;
        letter-spacing: -0.03em;
    }

    .page-subtitle {
        text-align: center;
        margin-bottom: 2.5rem;
        font-size: 0.95rem;
        color: #4b5563;
        max-width: 700px;
        margin-left: auto;
        margin-right: auto;
    }

    .year-wrapper {
        margin-bottom: 2.5rem;
        animation: fadeInUp 0.4s ease;
        background: #f8fafc;
        border-radius: 0.75rem;
        padding: 1.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .year-header-extra {
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #e5e7eb;
    }

    .section-header-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }

    .section-title {
        font-weight: 600;
        font-size: 1.1rem;
        color: #1e293b;
        margin: 0;
    }

    .section-total {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        opacity: 0.9;
    }

    .no-data-card {
        max-width: 700px;
        margin: 0 auto;
    }

    .filter-row {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.75rem;
        margin: 0 auto 2.5rem;
        flex-wrap: wrap;
        max-width: 900px;
        padding: 0.75rem 1rem;
        background: #f1f5f9;
        border-radius: 0.75rem;
    }

    .year-tab {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1.25rem;
        border-radius: 0.5rem;
        font-size: 0.85rem;
        font-weight: 600;
        text-decoration: none;
        border: 1px solid #e2e8f0;
        color: #475569;
        background: #ffffff;
        transition: all 0.2s ease;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .year-tab:hover {
        background: #f1f5f9;
        color: #0f52d8;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .year-tab.active {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: #ffffff;
        border-color: transparent;
        box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.3);
    }

    /* Student cards grid */
    .students-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1rem;
        margin-top: 1.25rem;
    }

    .student-card {
        background: white;
        border-radius: 0.75rem;
        padding: 1.25rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        transition: all 0.2s ease;
        border: 1px solid #e2e8f0;
    }

    .student-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        border-color: #bfdbfe;
    }

    .student-name {
        font-weight: 600;
        color: #1e293b;
        margin: 0 0 0.25rem 0;
        font-size: 0.95rem;
    }

    .student-id {
        color: #64748b;
        font-size: 0.8rem;
        margin: 0 0 0.5rem 0;
    }

    .student-gender {
        display: inline-block;
        padding: 0.2rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .gender-male {
        background-color: #dbeafe;
        color: #1e40af;
    }

    .gender-female {
        background-color: #f3e8ff;
        color: #6b21a8;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #e2e8f0;
    }

    .section-total-badge {
        background: #e0f2fe;
        color: #0369a1;
        padding: 0.3rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    @media (max-width: 900px) {
        .app-content {
            margin-left: 0;
            padding: 1.25rem 0.75rem 1.75rem 0.75rem;
        }

        .app-container {
            padding: 1.5rem 1.25rem;
            border-radius: 1rem;
        }

        .page-title {
            font-size: 1.6rem;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }

        .students-grid {
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 0.875rem;
        }

        .year-wrapper {
            padding: 1.25rem;
        }
    }

    @media (max-width: 640px) {
        .students-grid {
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }

        .filter-row {
            gap: 0.5rem;
            padding: 0.5rem;
        }

        .year-tab {
            padding: 0.4rem 0.9rem;
            font-size: 0.8rem;
        }
    }

    /* Scoped override for this page's year cards */
    .year-wrapper .card .card-header {
        background: #0f52d8 !important;
        background-image: none !important;
        color: #ffffff;
        border-bottom-color: #0f52d8;
    }
</style>

<div class="app-content">
    <div class="app-container card">
        <div class="card-body">
            <h1 class="page-title text-gradient">
                 BSIS Students 
            </h1>
            <p class="page-subtitle">
                <?php 
                $ay_name = $_SESSION['active_ay_name'] ?? 'N/A';
                $sem_name = $_SESSION['active_sem_now'] ?? 'N/A';
                echo "Managing students for <strong>$ay_name</strong> - <strong>$sem_name</strong>";
                ?>
            </p>

            <?php if ($totalPages > 1): ?>
                <div class="filter-row">
                    <?php $baseUrl = strtok($_SERVER['REQUEST_URI'], '?'); ?>
                    <?php foreach ($availableYears as $y): ?>
                        <a href="<?= htmlspecialchars($baseUrl . '?year=' . urlencode($y)) ?>"
                           class="year-tab <?= ($selectedYear === (string)$y) ? 'active' : '' ?>"><?= htmlspecialchars($y) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($grouped)): ?>
                <div class="card no-data-card">
                    <div class="card-body" style="text-align:center;">
                        <p style="font-size:0.95rem;color:#4b5563;">
                            No BSIS admissions found. Please enroll students via the Enroll Student page.
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($grouped as $year => $sections): ?>
                    <?php if ($selectedYear !== '' && (string)$selectedYear !== (string)$year) continue; ?>
                    <?php $yearTotal = 0; foreach ($sections as $s) { $yearTotal += count($s); } ?>
                    <div class="year-wrapper">
                        <div class="card">
                            <div class="card-header">
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;">
                                    <div>
                                        <div class="year-header-extra">Year Level</div>
                                        <div style="font-size:1.2rem;font-weight:700;">
                                            <?= htmlspecialchars($year) ?>
                                        </div>
                                    </div>
                                    <div class="badge badge-info">
                                        Total: <?= $yearTotal ?> student(s)
                                    </div>
                                    <form method="post" onsubmit="return confirm('Delete all BSIS admissions for year: <?= htmlspecialchars($year) ?>? This cannot be undone.');" style="margin-left:auto;">
                                        <input type="hidden" name="delete_year" value="<?= htmlspecialchars($year) ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete Year</button>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body" style="padding-top:1.25rem;">
                                <?php foreach ($sections as $section => $students): ?>
                                    <?php $gridId = 'grid_' . md5($year . '_' . $section); ?>
                                    <div class="mb-8">
                                        <div class="section-header">
                                            <h3 class="section-title">Section <?= htmlspecialchars($section) ?></h3>
                                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                                <button type="button" class="year-tab" onclick="sortByGender('<?= $gridId ?>','Male')">Male</button>
                                                <button type="button" class="year-tab" onclick="sortByGender('<?= $gridId ?>','Female')">Female</button>
                                                <span class="section-total-badge"><?= count($students) ?> student(s)</span>
                                            </div>
                                        </div>
                                        <div class="students-grid" id="<?= $gridId ?>">
                                            <?php foreach ($students as $student): 
                                                $genderClass = strtolower($student['gender']) === 'male' ? 'gender-male' : 'gender-female';
                                                $genderValue = (strcasecmp(trim($student['gender'] ?? ''), 'male') === 0) ? 'Male' : 'Female';
                                            ?>
                                                <div class="student-card" data-gender="<?= $genderValue ?>">
                                                    <h4 class="student-name">
                                                        <?= htmlspecialchars($student['last_name']) ?>, 
                                                        <?= htmlspecialchars($student['first_name']) ?>
                                                        <?= !empty($student['middle_name']) ? htmlspecialchars(substr($student['middle_name'], 0, 1) . '.') : '' ?>
                                                        <?= !empty($student['suffix']) ? htmlspecialchars($student['suffix']) : '' ?>
                                                    </h4>
                                                    <p class="student-id">ID: <?= htmlspecialchars($student['student_id']) ?></p>
                                                    <span class="student-gender <?= $genderClass ?>">
                                                        <?= htmlspecialchars($student['gender']) ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function sortByGender(gridId, priorityGender) {
    var grid = document.getElementById(gridId);
    if (!grid) return;
    var cards = Array.prototype.slice.call(grid.children);
    cards.sort(function(a, b) {
        var ga = (a.getAttribute('data-gender') || '').toLowerCase();
        var gb = (b.getAttribute('data-gender') || '').toLowerCase();
        var pri = (priorityGender || '').toLowerCase();
        var ra = ga === pri ? 0 : 1;
        var rb = gb === pri ? 0 : 1;
        if (ra !== rb) return ra - rb;
        var na = (a.querySelector('.student-name')?.textContent || '').toLowerCase();
        var nb = (b.querySelector('.student-name')?.textContent || '').toLowerCase();
        if (na < nb) return -1;
        if (na > nb) return 1;
        return 0;
    });
    cards.forEach(function(card){ grid.appendChild(card); });
}
</script>




