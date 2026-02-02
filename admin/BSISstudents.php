<?php
session_start();
include '../includes/db.php';

/**
 * 1. AUTHENTICATION & SESSION CHECK
 * We perform this BEFORE any HTML output to allow header() redirects.
 */
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}

/**
 * 2. DATABASE PREPARATION
 * Fetch BSIS course ids (code or name match)
 */
$courseIds = [];
$courseRes = $conn->query("SELECT course_id FROM course WHERE course_code = 'BSIS' OR course_name LIKE 'Bachelor of Science in Information System%'");
if ($courseRes) {
    while ($c = $courseRes->fetch_assoc()) {
        $courseIds[] = (int)$c['course_id'];
    }
}
if (empty($courseIds)) {
    $courseIds = [-1]; // force empty result if no BSIS course defined
}
$idList = implode(',', $courseIds);

/**
 * 3. FORM PROCESSING (DELETE)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_year'])) {
    $yearToDelete = trim($_POST['delete_year']);
    if ($yearToDelete !== '') {
        $delSql = "DELETE a FROM admission a\n"
               . "JOIN year_level yl ON a.year_level_id = yl.year_id\n"
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

/**
 * 4. DATA FETCHING & GROUPING
 * Group BSIS admissions by year level and section for the ACTIVE session.
 */
$ay_id  = (int)($_SESSION['active_ay_id'] ?? 0);
$sem_id = (int)($_SESSION['active_sem_id'] ?? 0);

$sql = "SELECT DISTINCT st.student_id, yl.year_name, sct.section_name,
               st.first_name, st.middle_name, st.last_name, st.suffix, st.gender
        FROM admission a
        JOIN students st    ON a.student_id     = st.student_id
        JOIN year_level yl  ON a.year_level_id  = yl.year_id
        JOIN section sct    ON a.section_id     = sct.section_id
        JOIN course c       ON a.course_id      = c.course_id
        WHERE a.course_id IN ($idList)
          AND a.academic_year_id = $ay_id
          AND a.semester_id = $sem_id
        ORDER BY yl.level, sct.section_name, st.last_name, st.first_name, st.student_id";

$result = $conn->query($sql);
$grouped = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $yearKey = trim($row['year_name']);
        $sectionKey = $row['section_name'] ?: 'No Section';
        if (!isset($grouped[$yearKey])) {
            $grouped[$yearKey] = [];
        }
        if (!isset($grouped[$yearKey][$sectionKey])) {
            $grouped[$yearKey][$sectionKey] = [];
        }
        $grouped[$yearKey][$sectionKey][] = $row;
    }
}

// Prepare year tabs/filters
$availableYears = array_values(array_keys($grouped));
$totalPages = count($availableYears);
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

/**
 * 5. OUTPUT STARTS HERE
 */
include '../includes/header.php'; 
?>

<style>
    .app-inner-content {
        padding: 1.25rem;
        max-width: 1400px;
        margin: 0 auto;
    }

    .app-container {
        background: #ffffff;
        border-radius: 0.75rem;
        padding: 1.25rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid #e2e8f0;
    }

    .page-title {
        text-align: left;
        margin-bottom: 0.25rem;
        font-size: 1.5rem;
        font-weight: 800;
        color: #1e293b;
        letter-spacing: -0.025em;
    }

    .page-subtitle {
        text-align: left;
        margin-bottom: 1.5rem;
        font-size: 0.85rem;
        color: #64748b;
    }

    .filter-row {
        display: flex;
        justify-content: flex-start;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        padding: 0.5rem;
        background: #f8fafc;
        border-radius: 0.5rem;
        border: 1px solid #f1f5f9;
    }

    .year-tab {
        padding: 0.4rem 1rem;
        border-radius: 0.4rem;
        font-size: 0.8rem;
        font-weight: 600;
        color: #475569;
        background: #fff;
        border: 1px solid #e2e8f0;
        transition: all 0.2s;
    }

    .year-tab:hover {
        background: #f1f5f9;
        color: #0f52d8;
    }

    .year-tab.active {
        background: #0f52d8;
        color: #ffffff;
        border-color: #0f52d8;
    }

    .year-wrapper {
        margin-bottom: 1.5rem;
    }

    .year-card-header {
        background: #0f52d8 !important;
        color: #fff;
        padding: 0.75rem 1.25rem;
        border-radius: 0.5rem 0.5rem 0 0 !important;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .section-block {
        margin-bottom: 1.5rem;
        padding: 1rem;
        background: #fff;
        border: 1px solid #f1f5f9;
        border-radius: 0.5rem;
    }

    .section-label-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #f1f5f9;
    }

    .section-name-text {
        font-size: 1rem;
        font-weight: 700;
        color: #1e293b;
    }

    .students-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 0.75rem;
    }

    .student-card {
        background: #fff;
        border: 1px solid #f1f5f9;
        border-radius: 0.5rem;
        padding: 0.875rem;
        transition: all 0.2s;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    }

    .student-card:hover {
        border-color: #cbd5e1;
        background: #f8fafc;
    }

    .student-name {
        font-size: 0.9rem;
        font-weight: 600;
        color: #0f172a;
        margin-bottom: 0.125rem;
        line-height: 1.2;
    }

    .student-id-text {
        color: #94a3b8;
        font-size: 0.75rem;
        margin-bottom: 0.5rem;
    }

    .gender-pill {
        display: inline-block;
        padding: 0.125rem 0.5rem;
        border-radius: 4px;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .gender-male { background: #e0f2fe; color: #0369a1; }
    .gender-female { background: #fce7f3; color: #9d174d; }

    .btn-delete-year {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        padding: 0.3rem 0.6rem;
        border-radius: 0.25rem;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .btn-delete-year:hover {
        background: #ef4444;
    }

    .total-badge-white {
        background: #fff;
        color: #0f52d8;
        padding: 0.15rem 0.5rem;
        border-radius: 4px;
        font-weight: 700;
        font-size: 0.7rem;
    }
</style>

<div class="app-inner-content">
    <div class="app-container">
        <h1 class="page-title">BSIS Masterlist</h1>
        <p class="page-subtitle">
            <?php 
                $active_ay = $_SESSION['active_ay_name'] ?? 'N/A';
                $active_sem = $_SESSION['active_sem_now'] ?? 'N/A';
                echo "Viewing students for Academic Year <strong>$active_ay</strong>, <strong>$active_sem</strong>";
            ?>
        </p>

        <?php if ($totalPages > 1): ?>
            <div class="filter-row">
                <?php $baseUrl = strtok($_SERVER['REQUEST_URI'], '?'); ?>
                <?php foreach ($availableYears as $y): ?>
                    <a href="<?= htmlspecialchars($baseUrl . '?year=' . urlencode($y)) ?>"
                       class="year-tab <?= ($selectedYear === (string)$y) ? 'active' : '' ?>">
                       <?= htmlspecialchars($y) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($grouped)): ?>
            <div style="text-align: center; padding: 5rem 2rem;">
                <div style="font-size: 4rem; color: #cbd5e1; margin-bottom: 1.5rem;">
                    <i class="fas fa-folder-open"></i>
                </div>
                <h3 style="color: #64748b; font-weight: 600;">No students enrolled for this session yet.</h3>
                <p style="color: #94a3b8; font-size: 0.9rem;">Go to Master List or Enroll Page to add students to this department.</p>
            </div>
        <?php else: ?>
            <?php foreach ($grouped as $year => $sections): ?>
                <?php if ($selectedYear !== '' && (string)$selectedYear !== (string)$year) continue; ?>
                <?php 
                    $yearTotal = 0; 
                    foreach ($sections as $s) { $yearTotal += count($s); } 
                ?>
                <div class="year-wrapper">
                    <div class="card overflow-hidden" style="border-radius: 1rem; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                        <div class="year-card-header">
                            <div>
                                <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; opacity: 0.8;">Year Level</span>
                                <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0; line-height: 1;"><?= htmlspecialchars($year) ?></h2>
                            </div>
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <span class="total-badge-white">Total: <?= $yearTotal ?></span>
                                <form method="post" onsubmit="return confirm('Delete BSIS records for <?= htmlspecialchars($year) ?>?');">
                                    <input type="hidden" name="delete_year" value="<?= htmlspecialchars($year) ?>">
                                    <button type="submit" class="btn-delete-year">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="card-body" style="padding: 2rem;">
                            <?php foreach ($sections as $section => $students): ?>
                                <?php $gridId = 'grid_' . md5($year . '_' . $section); ?>
                                <div class="section-block">
                                    <div class="section-label-header">
                                        <div class="section-name-text">Section: <?= htmlspecialchars($section) ?></div>
                                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                                            <button class="year-tab" style="padding: 0.3rem 0.75rem; font-size: 0.75rem;" onclick="sortByGender('<?= $gridId ?>','Male')">Show Boys 1st</button>
                                            <button class="year-tab" style="padding: 0.3rem 0.75rem; font-size: 0.75rem;" onclick="sortByGender('<?= $gridId ?>','Female')">Show Girls 1st</button>
                                            <span style="font-weight: 700; color: #3b82f6; font-size: 0.85rem; margin-left: 0.5rem;"><?= count($students) ?> Students</span>
                                        </div>
                                    </div>
                                    <div class="students-grid" id="<?= $gridId ?>">
                                        <?php foreach ($students as $student): 
                                            $gender_type = (isset($student['gender']) && strcasecmp($student['gender'], 'male') === 0) ? 'Male' : 'Female';
                                            $gender_class = ($gender_type === 'Male') ? 'gender-male' : 'gender-female';
                                        ?>
                                            <div class="student-card" data-gender="<?= $gender_type ?>">
                                                <div class="student-name">
                                                    <?= htmlspecialchars($student['last_name']) ?>, 
                                                    <?= htmlspecialchars($student['first_name']) ?>
                                                    <?= !empty($student['middle_name']) ? htmlspecialchars(substr($student['middle_name'], 0, 1) . '.') : '' ?>
                                                    <?= !empty($student['suffix']) ? htmlspecialchars($student['suffix']) : '' ?>
                                                </div>
                                                <div class="student-id-text">ID: <?= htmlspecialchars($student['student_id']) ?></div>
                                                <span class="gender-pill <?= $gender_class ?>"><?= $gender_type ?></span>
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

<script>
/**
 * Advanced sorting to prevent breakage and ensure smooth transitions
 */
function sortByGender(gridId, priorityGender) {
    const grid = document.getElementById(gridId);
    if (!grid) return;
    
    // Convert children to array
    const sortedCards = Array.from(grid.children).sort((a, b) => {
        const genA = (a.getAttribute('data-gender') || '').toLowerCase();
        const genB = (b.getAttribute('data-gender') || '').toLowerCase();
        const pri = priorityGender.toLowerCase();
        
        // Priority Sort
        const rankA = (genA === pri) ? 0 : 1;
        const rankB = (genB === pri) ? 0 : 1;
        
        if (rankA !== rankB) return rankA - rankB;
        
        // Secondary Alphabetical Sort
        const nameA = a.querySelector('.student-name').innerText.trim().toLowerCase();
        const nameB = b.querySelector('.student-name').innerText.trim().toLowerCase();
        return nameA.localeCompare(nameB);
    });
    
    // Clear and re-append
    grid.innerHTML = '';
    sortedCards.forEach(card => grid.appendChild(card));
}
</script>

<?php include '../includes/footer.php'; ?>
