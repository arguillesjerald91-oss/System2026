<?php 

// Start session only if not already active
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

include_once __DIR__ . '/db.php';
include_once __DIR__ . '/log_activity.php';
include 'header.php';
include 'sidebar.php';

$database = new Database();
$conn = $database->getConnection();

/* ========================
   🧾 SAVE / UPDATE GRADES
======================== */
$gradeSaved = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_grade'])) {
    $enrollment_id = intval($_POST['enrollment_id']);
    $student_id = intval($_POST['student_id']);
    $grading_period = $_POST['grading_period'];
    $grade_value = floatval($_POST['grade_value']);
    
    // Determine remarks based on grade value (1.0-3.0 = Passed, 3.1-5.0 = Failed)
    if ($grade_value >= 1.0 && $grade_value <= 3.0) {
        $remarks = 'Passed';
    } else {
        $remarks = 'Failed';
    }

    // Check if grade already exists for this student and grading period
    $check = $conn->prepare("SELECT grade_id FROM grades WHERE StudID = ? AND grading_period = ?");
    $check->execute([$student_id, $grading_period]);

    if ($check->rowCount() > 0) {
        $sql = "UPDATE grades SET grade_value = ?, remarks = ?, enrollment_id = ?, date_recorded = NOW() 
                WHERE StudID = ? AND grading_period = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$grade_value, $remarks, $enrollment_id, $student_id, $grading_period]);
        
        logActivity('Grade Updated', "Grade updated - Student ID: $student_id, Period: $grading_period, Grade: $grade_value", $conn);
    } else {
        $sql = "INSERT INTO grades (enrollment_id, StudID, grading_period, grade_value, remarks, date_recorded)
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$enrollment_id, $student_id, $grading_period, $grade_value, $remarks]);
        
        logActivity('Grade Added', "New grade added - Student ID: $student_id, Period: $grading_period, Grade: $grade_value", $conn);
    }

    $gradeSaved = true;
}


/* ========================
   � POST FINAL GRADE
======================== */
$gradePosted = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_grade'])) {
    $enrollment_id = intval($_POST['enrollment_id']);
    $student_id = intval($_POST['student_id']);
    $final_grade = floatval($_POST['final_grade']);
    $subject_id = intval($_POST['subject_id']);

    // Get subject code from subject_id (always fetch for accuracy)
    $subCodeQuery = $conn->prepare("SELECT SubCode FROM subject WHERE SubjectID = ?");
    $subCodeQuery->execute([$subject_id]);
    $subCodeResult = $subCodeQuery->fetch(PDO::FETCH_ASSOC);
    $subject_code = $subCodeResult['SubCode'] ?? '';

    // Determine status based on final grade
    $status = ($final_grade >= 1.0 && $final_grade <= 3.0) ? 'Passed' : 'Failed';

    // Always tie the posted grade to the enrollment_id, student, and subject_code
    $checkPosted = $conn->prepare("SELECT grade_id FROM grades WHERE enrollment_id = ? AND StudID = ? AND subject_code = ? AND grading_period = 'Posted'");
    $checkPosted->execute([$enrollment_id, $student_id, $subject_code]);

    if ($checkPosted->rowCount() > 0) {
        // Update existing posted grade
        $sql = "UPDATE grades SET grade_value = ?, remarks = ?, date_recorded = NOW() 
                WHERE enrollment_id = ? AND StudID = ? AND subject_code = ? AND grading_period = 'Posted'";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$final_grade, $status, $enrollment_id, $student_id, $subject_code]);
    } else {
        // Insert new posted grade
        $sql = "INSERT INTO grades (enrollment_id, StudID, subject_code, grading_period, grade_value, remarks, date_recorded)
                VALUES (?, ?, ?, 'Posted', ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$enrollment_id, $student_id, $subject_code, $final_grade, $status]);
    }

    logActivity('Grade Posted', "Final grade posted - Student ID: $student_id, Subject: $subject_code, Grade: $final_grade, Status: $status", $conn);
    $gradePosted = true;
}

/* ========================
   �🗑 DELETE GRADE
======================== */
if (isset($_POST['delete_grade'])) {
    $enrollment_id = intval($_POST['enrollment_id']);
    $grading_period = $_POST['grading_period'] ?? '';

    if ($grading_period) {
        // Delete specific grading period
        $del = $conn->prepare("DELETE FROM grades WHERE enrollment_id = ? AND grading_period = ?");
        $del->execute([$enrollment_id, $grading_period]);
    } else {
        // Delete all grades for this enrollment
        $del = $conn->prepare("DELETE FROM grades WHERE enrollment_id = ?");
        $del->execute([$enrollment_id]);
    }
}

/* ========================
   📚 Fetch Subjects from Schedules
======================== */
$subjectQuery = "SELECT DISTINCT sch.subject_id, sub.SubCode, sub.SubName, sch.semester, sch.academic_year
                 FROM schedules sch
                 JOIN subject sub ON sch.subject_id = sub.SubjectID
                 WHERE sch.subject_id IS NOT NULL
                 ORDER BY sub.SubName";
$subjectStmt = $conn->prepare($subjectQuery);
$subjectStmt->execute();
$subjects = $subjectStmt->fetchAll(PDO::FETCH_ASSOC);

/* ========================
   📚 Fetch Students by Selected Subject
======================== */
$selectedSubject = $_GET['subject_id'] ?? '';
$selectedSemester = $_GET['semester'] ?? '';
$currentPage = intval($_GET['page'] ?? 1);
$recordsPerPage = 10;

$records = [];
$subjectInfo = null;

if ($selectedSubject != '') {
    // Get subject info
    $subInfoQuery = "SELECT sub.SubCode, sub.SubName, sub.Unit, sch.semester, sch.academic_year
                     FROM schedules sch
                     JOIN subject sub ON sch.subject_id = sub.SubjectID
                     WHERE sch.subject_id = ?
                     LIMIT 1";
    $subInfoStmt = $conn->prepare($subInfoQuery);
    $subInfoStmt->execute([$selectedSubject]);
    $subjectInfo = $subInfoStmt->fetch(PDO::FETCH_ASSOC);

    // Query #1: Get all students enrolled in the selected subject
    $query = "SELECT 
                e.EnrollID AS enrollment_id,
                s.StudID AS student_id,
                s.SchoolID AS student_number,
                s.FirstName AS first_name,
                s.LastName AS last_name,
                s.Course AS course,
                s.YearLvl AS year_level
              FROM enrollment e
              JOIN schedules sch ON e.schedule_id = sch.schedule_id
              JOIN student s ON e.StudID = s.StudID
              WHERE sch.subject_id = ?";
    
    if ($selectedSemester != '') {
        $query .= " AND sch.semester = ?";
    }
    
    $query .= " ORDER BY s.LastName, s.FirstName";
    
    $stmt = $conn->prepare($query);
    if ($selectedSemester != '') {
        $stmt->execute([$selectedSubject, $selectedSemester]);
    } else {
        $stmt->execute([$selectedSubject]);
    }
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get subject code for the selected subject (do this once, not per student)
    $subjectCodeQuery = $conn->prepare("SELECT SubCode FROM subject WHERE SubjectID = ?");
    $subjectCodeQuery->execute([$selectedSubject]);
    $subjectCode = $subjectCodeQuery->fetchColumn();

    // Query #2: For each student, get their grades by StudID from grades table
    foreach ($students as &$student) {
        $gradeQuery = "SELECT grading_period, grade_value, remarks 
                       FROM grades 
                       WHERE StudID = ? AND subject_code = ?
                       ORDER BY FIELD(grading_period, 'Prelims', 'Midterms', 'Semifinals', 'Finals')";
        $gradeStmt = $conn->prepare($gradeQuery);
        $gradeStmt->execute([$student['student_id'], $subjectCode]);
        $grades = $gradeStmt->fetchAll(PDO::FETCH_ASSOC);

        // Initialize all grading periods (Prelims, Midterms, Semifinals, Finals)
        $student['grades'] = [
            'Prelims' => ['grade_value' => '', 'remarks' => ''],
            'Midterms' => ['grade_value' => '', 'remarks' => ''],
            'Semifinals' => ['grade_value' => '', 'remarks' => ''],
            'Finals' => ['grade_value' => '', 'remarks' => '']
        ];

        // Fill in actual grades from the grades table
        foreach ($grades as $g) {
            $period = $g['grading_period'];
            if (isset($student['grades'][$period])) {
                $student['grades'][$period] = [
                    'grade_value' => $g['grade_value'],
                    'remarks' => $g['remarks']
                ];
            }
        }
    }
    unset($student);
    $records = $students;
}

?>



<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Grades</title>
<link rel="stylesheet" href="css/grades.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.subject-selector {
    background: linear-gradient(135deg, #2563eb, #2563eb);
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    color: white;
}
.subject-selector label {
    font-weight: 600;
    margin-right: 10px;
}
.subject-selector select {
    padding: 10px 15px;
    border-radius: 5px;
    border: none;
    min-width: 300px;
    font-size: 14px;
}
.subject-info {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #2563eb;
}
.subject-info h4 {
    margin: 0 0 10px 0;
    color: #2c3e50;
}
.subject-info span {
    margin-right: 20px;
    color: #555;
}
.grade-input {
    width: 60px;
    padding: 5px;
    text-align: center;
    border: 1px solid #ddd;
    border-radius: 4px;
}
.grade-input:focus {
    border-color: #2563eb;
    outline: none;
}
.period-header {
    background: linear-gradient(135deg, #2563eb, #2563eb);
    color: white;
    padding: 8px;
    text-align: center;
    font-weight: 600;
}
.remarks-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}
.remarks-badge.passed { background: #27ae60; color: white; }
.remarks-badge.failed { background: #e74c3c; color: white; }
.no-selection {
    text-align: center;
    padding: 60px;
    background: #f8f9fa;
    border-radius: 10px;
    color: #666;
}
.no-selection i {
    font-size: 48px;
    color: #2563eb;
    margin-bottom: 15px;
}
.save-all-btn {
    background: linear-gradient(135deg, #27ae60, #2ecc71);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    font-weight: 600;
    margin-bottom: 15px;
}
.save-all-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
}
.action-btn.post {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    border: none;
    padding: 6px 10px;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.3s ease;
}
.action-btn.post:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(52, 152, 219, 0.4);
}
.action-btn.post.disabled {
    background: #bdc3c7;
    cursor: not-allowed;
    opacity: 0.6;
}
.action-btn.post.disabled:hover {
    transform: none;
    box-shadow: none;
}
</style>
</head>
<body>

<div class="main-content">
<h2><i class="fa-solid fa-file-pen"></i> Manage Grades</h2>

<!-- SUBJECT SELECTOR -->
<div class="subject-selector">
    <form method="GET" style="display:flex; gap: 15px; align-items: center; flex-wrap: wrap;">
        <div>
            <label><i class="fas fa-book"></i> Select Subject:</label>
            <select name="subject_id" onchange="this.form.submit()">
                <option value="">-- Choose a Subject --</option>
                <?php foreach ($subjects as $sub): ?>
                    <option value="<?= $sub['subject_id'] ?>" <?= $selectedSubject == $sub['subject_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sub['SubCode'] . ' - ' . $sub['SubName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div>
            <label><i class="fas fa-calendar"></i> Semester:</label>
            <select name="semester" onchange="this.form.submit()">
                <option value="">All Semesters</option>
                <option value="1st Semester" <?= $selectedSemester == '1st Semester' ? 'selected' : '' ?>>1st Semester</option>
                <option value="2nd Semester" <?= $selectedSemester == '2nd Semester' ? 'selected' : '' ?>>2nd Semester</option>
                <option value="Summer" <?= $selectedSemester == 'Summer' ? 'selected' : '' ?>>Summer</option>
            </select>
        </div>
    </form>
</div>

<?php if ($selectedSubject && $subjectInfo): ?>
<!-- SUBJECT INFO -->
<div class="subject-info">
    <h4><i class="fas fa-info-circle"></i> Subject Information</h4>
    <span><strong>Code:</strong> <?= htmlspecialchars($subjectInfo['SubCode']) ?></span>
    <span><strong>Title:</strong> <?= htmlspecialchars($subjectInfo['SubName']) ?></span>
    <span><strong>Units:</strong> <?= $subjectInfo['Unit'] ?></span>
    <span><strong>Semester:</strong> <?= htmlspecialchars($subjectInfo['semester']) ?></span>
    <span><strong>A.Y.:</strong> <?= htmlspecialchars($subjectInfo['academic_year']) ?></span>
    <span><strong>Enrolled Students:</strong> <?= count($records) ?></span>
</div>

<!-- FILTERS -->
<div class="filter-container">
    <input type="text" id="searchInput" placeholder="Search student name...">
    
    <select id="courseFilter">
        <option value="">All Courses</option>
        <option value="BSIT">BSIT</option>
        <option value="BSBA">BSBA</option>
        <option value="BEED">BEED</option>
        <option value="BSED">BSED</option>
        <option value="BSHM">BSHM</option>
        <option value="BSCRIM">BSCRIM</option>
    </select>
    
    <select id="yearFilter">
        <option value="">All Years</option>
        <option value="1">1st Year</option>
        <option value="2">2nd Year</option>
        <option value="3">3rd Year</option>
        <option value="4">4th Year</option>
    </select>
</div>

<!-- GRADES TABLE -->
<?php if (count($records) > 0): ?>
<div class="table-container">
    <table id="gradesTable">
        <thead>
            <tr>
                <th rowspan="2">School ID</th>
                <th rowspan="2">Student Name</th>
                <th rowspan="2">Course</th>
                <th rowspan="2">Year</th>
                <th colspan="2" class="period-header" style="background: linear-gradient(135deg, #2563eb, #2563eb);">Prelims</th>
                <th colspan="2" class="period-header" style="background: linear-gradient(135deg, #2563eb, #2563eb);">Midterms</th>
                <th colspan="2" class="period-header" style="background: linear-gradient(135deg, #2563eb, #2563eb);">Semi Finals</th>
                <th colspan="2" class="period-header" style="background: linear-gradient(135deg, #2563eb, #2563eb);">Finals</th>
                <th colspan="2" class="period-header" style="background: linear-gradient(135deg, #2563eb, #2563eb);">Final Grade</th>
                <th rowspan="2">Action</th>
            </tr>
            <tr>
                <th>Grade</th>
                <th>Remarks</th>
                <th>Grade</th>
                <th>Remarks</th>
                <th>Grade</th>
                <th>Remarks</th>
                <th>Grade</th>
                <th>Remarks</th>
                <th>Average</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            // Pagination
            $totalRecords = count($records);
            $totalPages = ceil($totalRecords / $recordsPerPage);
            $currentPage = max(1, min($currentPage, $totalPages));
            $startIndex = ($currentPage - 1) * $recordsPerPage;
            $paginatedRecords = array_slice($records, $startIndex, $recordsPerPage);
            
            foreach ($paginatedRecords as $r): 
            ?>
            <tr data-course="<?= htmlspecialchars($r['course']) ?>" data-year="<?= $r['year_level'] ?>">
                <td><?= $r['student_number'] ?: $r['student_id'] ?></td>
                <td><?= htmlspecialchars($r['last_name'] . ', ' . $r['first_name']) ?></td>
                <td><?= htmlspecialchars($r['course']) ?></td>
                <td><?= $r['year_level'] ?></td>
                
                <?php 
                $periods = ['Prelims', 'Midterms', 'Semifinals', 'Finals'];
                foreach ($periods as $period): 
                    $gradeData = $r['grades'][$period];
                    $gradeValue = $gradeData['grade_value'];
                    
                    // Calculate remarks based on grade value (not from database)
                    $remarks = '';
                    $remarksClass = '';
                    if ($gradeValue !== '' && $gradeValue !== null) {
                        $gradeFloat = floatval($gradeValue);
                        if ($gradeFloat >= 1.0 && $gradeFloat <= 3.0) {
                            $remarks = 'Passed';
                            $remarksClass = 'passed';
                        } else {
                            $remarks = 'Failed';
                            $remarksClass = 'failed';
                        }
                    }
                ?>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="enrollment_id" value="<?= $r['enrollment_id'] ?>">
                        <input type="hidden" name="student_id" value="<?= $r['student_id'] ?>">
                        <input type="hidden" name="grading_period" value="<?= $period ?>">
                        <input type="number" name="grade_value" class="grade-input" 
                               value="<?= htmlspecialchars($gradeValue) ?>" min="1" max="5" step="0.25"
                               onchange="this.form.submit()"
                               placeholder="-">
                        <input type="hidden" name="save_grade" value="1">
                    </form>
                </td>
                <td>
                    <?php if ($remarks): ?>
                        <span class="remarks-badge <?= $remarksClass ?>"><?= $remarks ?></span>
                    <?php else: ?>
                        <span style="color:#999;">-</span>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
                
                <?php
                // Calculate Final Grade (average of all 4 periods)
                $gradesForAvg = [];
                foreach ($periods as $p) {
                    $gv = $r['grades'][$p]['grade_value'];
                    if ($gv !== '' && $gv !== null) {
                        $gradesForAvg[] = floatval($gv);
                    }
                }
                
                $finalGrade = '';
                $finalRemarks = '';
                $finalRemarksClass = '';
                
                if (count($gradesForAvg) > 0) {
                    $finalGrade = round(array_sum($gradesForAvg) / count($gradesForAvg), 2);
                    if ($finalGrade >= 1.0 && $finalGrade <= 3.0) {
                        $finalRemarks = 'Passed';
                        $finalRemarksClass = 'passed';
                    } else {
                        $finalRemarks = 'Failed';
                        $finalRemarksClass = 'failed';
                    }
                }
                ?>
                <td>
                    <?php if ($finalGrade !== ''): ?>
                        <strong style="font-size: 14px; color: #2c3e50;"><?= $finalGrade ?></strong>
                    <?php else: ?>
                        <span style="color:#999;">-</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($finalRemarks): ?>
                        <span class="remarks-badge <?= $finalRemarksClass ?>"><?= $finalRemarks ?></span>
                    <?php else: ?>
                        <span style="color:#999;">-</span>
                    <?php endif; ?>
                </td>
                
                <td style="display: flex; gap: 5px;">
                    <?php if ($finalGrade !== ''): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="enrollment_id" value="<?= $r['enrollment_id'] ?>">
                        <input type="hidden" name="student_id" value="<?= $r['student_id'] ?>">
                        <input type="hidden" name="subject_id" value="<?= $selectedSubject ?>">
                        <input type="hidden" name="final_grade" value="<?= $finalGrade ?>">
                        <input type="hidden" name="post_grade" value="1">
                        <button type="submit" class="action-btn post" title="Post grade to student">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                    <?php else: ?>
                    <button type="button" class="action-btn post disabled" disabled title="Enter grades first">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination-controls">
    <?php if ($currentPage > 1): ?>
        <a href="?subject_id=<?= $selectedSubject ?>&semester=<?= urlencode($selectedSemester) ?>&page=1" class="btn">« First</a>
        <a href="?subject_id=<?= $selectedSubject ?>&semester=<?= urlencode($selectedSemester) ?>&page=<?= $currentPage - 1 ?>" class="btn">‹ Prev</a>
    <?php endif; ?>

    <?php 
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    if ($startPage > 1): ?>
        <span class="ellipsis">...</span>
    <?php endif; ?>

    <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
        <?php if ($p == $currentPage): ?>
            <span class="current-page"><?= $p ?></span>
        <?php else: ?>
            <a href="?subject_id=<?= $selectedSubject ?>&semester=<?= urlencode($selectedSemester) ?>&page=<?= $p ?>" class="btn"><?= $p ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($endPage < $totalPages): ?>
        <span class="ellipsis">...</span>
    <?php endif; ?>

    <?php if ($currentPage < $totalPages): ?>
        <a href="?subject_id=<?= $selectedSubject ?>&semester=<?= urlencode($selectedSemester) ?>&page=<?= $currentPage + 1 ?>" class="btn">Next ›</a>
        <a href="?subject_id=<?= $selectedSubject ?>&semester=<?= urlencode($selectedSemester) ?>&page=<?= $totalPages ?>" class="btn">Last »</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php else: ?>
<div class="no-selection">
    <i class="fas fa-users-slash"></i>
    <h3>No Students Enrolled</h3>
    <p>No students are currently enrolled in this subject.</p>
</div>
<?php endif; ?>

<?php else: ?>
<!-- NO SUBJECT SELECTED -->
<div class="no-selection">
    <i class="fas fa-hand-pointer"></i>
    <h3>Select a Subject</h3>
    <p>Please select a subject from the dropdown above to view and manage student grades.</p>
</div>
<?php endif; ?>

</div>

<!-- Success Modal -->
<div class="modal-overlay" id="gradeSuccessModal">
  <div class="success-modal">
    <h3><i class="fa-solid fa-circle-check"></i> Success!</h3>
    <p>Grade has been saved successfully.</p>
    <button onclick="closeGradeModal()">OK</button>
  </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h2>Confirm Delete</h2>
    <p>Are you sure you want to delete all grades for <strong id="deleteStudentName"></strong>?</p>

    <form method="POST">
      <input type="hidden" name="delete_grade" value="1">
      <input type="hidden" name="enrollment_id" id="del_enrollment">

      <button type="submit" class="btn delete-btn">Delete All Grades</button>
      <button type="button" class="btn cancel-btn" onclick="document.getElementById('deleteModal').style.display='none'">Cancel</button>
    </form>
  </div>
</div>

<script>
// Search and filter functionality
const searchInput = document.getElementById('searchInput');
const courseFilter = document.getElementById('courseFilter');
const yearFilter = document.getElementById('yearFilter');

function filterTable() {
    const s = searchInput ? searchInput.value.toLowerCase() : '';
    const c = courseFilter ? courseFilter.value.toLowerCase() : '';
    const y = yearFilter ? yearFilter.value : '';
    
    const tableRows = document.querySelectorAll('#gradesTable tbody tr');
    
    tableRows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const course = row.getAttribute('data-course') || '';
        const year = row.getAttribute('data-year') || '';

        const matchesSearch = s === '' || text.includes(s);
        const matchesCourse = c === '' || course.toLowerCase().includes(c);
        const matchesYear = y === '' || year == y;

        row.style.display = (matchesSearch && matchesCourse && matchesYear) ? '' : 'none';
    });
}

if (searchInput) searchInput.addEventListener('keyup', filterTable);
if (courseFilter) courseFilter.addEventListener('change', filterTable);
if (yearFilter) yearFilter.addEventListener('change', filterTable);

// Delete modal
document.querySelectorAll('.openDeleteModal').forEach(button => {
    button.addEventListener('click', function() {
        const modal = document.getElementById('deleteModal');
        document.getElementById('del_enrollment').value = this.dataset.enrollment;
        document.getElementById('deleteStudentName').textContent = this.dataset.name;
        modal.style.display = 'block';
    });
});

// Close modal
document.querySelectorAll('.close').forEach(el => {
    el.addEventListener('click', () => {
        document.getElementById('deleteModal').style.display = 'none';
    });
});

// Close when clicking outside modal
window.onclick = function(event) {
    const modal = document.getElementById('deleteModal');
    if (event.target == modal) modal.style.display = 'none';
};

// Success modal
function closeGradeModal() {
    document.getElementById('gradeSuccessModal').classList.remove('show');
    // Reload page to show updated data
    window.location.href = window.location.href;
}

<?php if(isset($gradeSaved) && $gradeSaved): ?>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('gradeSuccessModal');
    modal.classList.add('show');
});
<?php endif; ?>

<?php if(isset($gradePosted) && $gradePosted): ?>
document.addEventListener('DOMContentLoaded', function() {
    alert('Grade has been posted successfully! Students can now view their final grade.');
});
<?php endif; ?>
</script>

</body>
</html>
