<?php 
include 'header.php';
// Set page info for sidebar
$currentPage = 'result.php';
$pageTitle = 'Results';
$pageSubtitle = 'Assessment Results';

include 'sidebar_student.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['userRole']) && $_SESSION['userRole'] === 'student') {
    $_SESSION['userRole'] = 'trainee';
}

if (!isset($_SESSION['userId']) || !in_array($_SESSION['userRole'], ['trainee', 'student'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';
$db = new Database();
$conn = $db->getConnection();

if ($conn === null) {
    die("Database connection unavailable. Please try again later.");
}

// Helper function to check if column exists
function columnExists(PDO $conn, string $table, string $column): bool {
    try {
        $stmt = $conn->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return in_array($column, $columns);
    } catch (Exception $e) {
        return false;
    }
}

/* ===========================
    FETCH STUDENT ID
 =========================== */
// Use the session user ID directly as the student ID (StudID in the student table)
$student_id = $_SESSION['userId'];

// Check enrollment status
$enrollStmt = $conn->prepare("SELECT 1 FROM student_program_enrollments WHERE student_id = (SELECT StudID FROM student WHERE user_id = ? LIMIT 1) AND enrollment_status = 'Active' LIMIT 1");
$enrollStmt->execute([$student_id]);
$isEnrolled = (bool)$enrollStmt->fetchColumn();

if (!$isEnrolled) {
    header("Location: my_application.php?error=not_enrolled");
    exit();
}

/* ===========================
     FETCH POSTED GRADES (Final Grades only)
 =========================== */

// Check if required tables exist
function tableExists(PDO $conn, string $table): bool {
    $stmt = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

$gradesTableExists = tableExists($conn, 'grades');
$allGrades = [];
$gradesBySubject = [];
$displayGrades = [];

if ($gradesTableExists) {




// Fetch all grades for this student for all grading periods
// Use dynamic column check to handle missing YearLvl
$hasYearLvl = columnExists($conn, 'student', 'YearLvl');
$yearSelect = $hasYearLvl ? 's.YearLvl' : 'NULL';

$sql = "SELECT g.*, 
    COALESCE(g.subject_code, sub.SubCode, sub2.SubCode, 'Unknown') as subject_code_real,
    COALESCE(sub.SubName, sub2.SubName, 'Unknown Subject') as title, 
    COALESCE(sub.Unit, sub2.Unit, 0) as units, 
    sch.semester, sch.academic_year,
    $yearSelect as year_level
FROM grades g
LEFT JOIN subject sub ON (g.subject_code = sub.SubCode OR g.subject_code = sub.SubjectID)
LEFT JOIN enrollment e ON g.enrollment_id = e.EnrollID
LEFT JOIN schedules sch ON e.schedule_id = sch.schedule_id
LEFT JOIN subject sub2 ON sch.subject_id = sub2.SubjectID
LEFT JOIN student s ON g.StudID = s.StudID
WHERE g.StudID = ?
ORDER BY sch.academic_year DESC, sch.semester, g.subject_code, g.grade DESC";
$stmt = $conn->prepare($sql);
$stmt->execute([$student_id]);
$allGrades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group grades by year, semester, subject, and grading period
// Group grades by academic year → year → semester → subject
$gradesBySubject = [];
foreach ($allGrades as $g) {
    $ay = $g['academic_year'] ?? 'Unknown AY';
    $year = $g['year_level'] ?? "No Year";
    $sem  = $g['semester'] ?? "No Semester";
    $code = $g['subject_code_real'] ?? ($g['subject_code'] ?? '-');
    $period = $g['grading_period'] ?? '-';
    if (!isset($gradesBySubject[$ay])) $gradesBySubject[$ay] = [];
    if (!isset($gradesBySubject[$ay][$year])) $gradesBySubject[$ay][$year] = [];
    if (!isset($gradesBySubject[$ay][$year][$sem])) $gradesBySubject[$ay][$year][$sem] = [];
    if (!isset($gradesBySubject[$ay][$year][$sem][$code])) {
        $gradesBySubject[$ay][$year][$sem][$code] = [
            'subject_code' => $code,
            'title' => $g['title'] ?? 'Unknown Subject',
            'units' => $g['units'] ?? 0,
            'grades' => []
        ];
    }
    $gradesBySubject[$ay][$year][$sem][$code]['grades'][$period] = [
        'grade_value' => $g['grade_value'],
        'remarks' => $g['remarks']
    ];
}

// For GPA calculation, use the computed literal final grade (average of Prelims, Midterms, Semifinals, Finals) for each subject
$displayGrades = [];
foreach ($gradesBySubject as $ay => $years) {
    foreach ($years as $year => $semesters) {
        foreach ($semesters as $sem => $subjects) {
            foreach ($subjects as $code => $subject) {
                $periods = ['Prelims', 'Midterms', 'Semifinals', 'Finals'];
                $gradesForAvg = [];
                foreach ($periods as $p) {
                    if (isset($subject['grades'][$p]['grade_value']) && $subject['grades'][$p]['grade_value'] !== null && $subject['grades'][$p]['grade_value'] !== '') {
                        $gradesForAvg[] = floatval($subject['grades'][$p]['grade_value']);
                    }
                }
                if (count($gradesForAvg) > 0) {
                    $finalGrade = round(array_sum($gradesForAvg) / count($gradesForAvg), 2);
                    $displayGrades[] = [
                        'grade_value' => $finalGrade,
                        'units' => $subject['units'],
                    ];
                }
            }
        }
    }
}
} // end if ($gradesTableExists)

// DEBUG: Show fetched grades and joined subject info for troubleshooting
if ($gradesTableExists && isset($_GET['debug']) && $_GET['debug'] == '1') {
    echo '<div style="background:#fff3cd; color:#856404; border:1px solid #ffc107; padding:10px; font-size:12px; max-width:900px; margin:20px auto;">';
    echo '<b>DEBUG: Raw grades fetched for StudID: ' . htmlspecialchars($student_id) . '</b><br><pre>';
    print_r($displayGrades);
    echo "</pre>";
    // Show all grades for this student from the grades table
    $allGrades = $conn->prepare("SELECT * FROM grades WHERE StudID = ? ORDER BY date_recorded DESC");
    $allGrades->execute([$student_id]);
    $allGradesArr = $allGrades->fetchAll(PDO::FETCH_ASSOC);
    echo '<b>DEBUG: All grades table rows for this student:</b><br><pre>';
    print_r($allGradesArr);
    echo "</pre>";
    // Show all enrollments for this student
    $allEnroll = $conn->prepare("SELECT * FROM enrollment WHERE StudID = ?");
    $allEnroll->execute([$student_id]);
    $allEnrollArr = $allEnroll->fetchAll(PDO::FETCH_ASSOC);
    echo '<b>DEBUG: All enrollment table rows for this student:</b><br><pre>';
    print_r($allEnrollArr);
    echo "</pre>";
    echo '</div>';
}


// $gradesBySubject is already grouped by year, semester, subject

// GPA calculation and display removed as per request
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Grade - Trainee</title>
<link rel="stylesheet" href="css/grades.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="main-content">
      <div class="header">
        <h1> My Grades</h1>
        <p>View your academic results</p>
    </div>

    
<!-- GPA card removed as per request -->

<?php if (!$gradesTableExists): ?>
    <div class="no-results">
        <i class="fa-solid fa-database"></i>
        <h3>Grades system not configured</h3>
        <p>The grades database table does not exist. Please contact your administrator to set up the grades system.</p>
        
<?php elseif (empty($displayGrades)): ?>
    <div class="no-results">
        <i class="fa-solid fa-folder-open"></i>
        <h3>No grades available yet</h3>
        <p>Your grades will appear here once they are submitted by your instructors.</p>
        
        <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin-top: 20px; border-radius: 5px; max-width: 600px; margin-left: auto; margin-right: auto;">
            <h4 style="color: #856404; margin-top: 0;">
                <i class="fas fa-question-circle"></i> Troubleshooting
            </h4>
            <p>If you believe you should have grades:</p>
            <ol style="text-align: left;">
                <li>Make sure you're enrolled in the current semester</li>
                <li>Check that instructors have submitted grades</li>
                <li>Contact the registrar's office if issues persist</li>
            </ol>
        </div>
    </div>
<?php endif; ?>

<!-- DISPLAY GRADES -->

<?php foreach ($gradesBySubject as $academicYear => $years): ?>
    <h1 style="color: #2563eb; margin: 40px 0 10px 0; padding-bottom: 5px; border-bottom: 4px solid #2563eb; font-size: 28px;">
        <i class="fa-solid fa-calendar-alt"></i> Academic Year: <?= htmlspecialchars($academicYear) ?>
    </h1>
    <?php foreach ($years as $year => $semesters): ?>
        <h2 style="color: #2c3e50; margin: 30px 0 20px 0; padding-bottom: 10px; border-bottom: 3px solid #3498db; font-size: 24px;">
            <i class="fa-solid fa-graduation-cap"></i> <?= htmlspecialchars($year) ?>
        </h2>
        <?php foreach ($semesters as $sem => $subjects): ?>
            <div style="margin-bottom: 30px;">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; border-radius: 10px 10px 0 0; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0; font-size: 20px;">
                        <i class="fa-solid fa-calendar-alt"></i> <?= htmlspecialchars($sem) ?>
                    </h3>
                    <span style="background: rgba(255,255,255,0.2); padding: 5px 15px; border-radius: 20px; font-weight: bold;">
                        <?= count($subjects) ?> subject(s)
                    </span>
                </div>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 0 0 10px 10px;">
                    <table class="grade-table" style="width: 100%; margin: 0;">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Subject</th>
                            <th>Units</th>
                            <th>Prelims</th>
                            <th>Midterms</th>
                            <th>Semifinals</th>
                            <th>Finals</th>
                            <th>Final Grade</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjects as $s): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['subject_code'] ?? '-') ?></strong></td>
                            <td><?= htmlspecialchars($s['title'] ?? 'Subject information not available') ?></td>
                            <td><?= $s['units'] ?? '-' ?></td>
                            <?php 
                            $periods = ['Prelims', 'Midterms', 'Semifinals', 'Finals'];
                            foreach ($periods as $period): ?>
                                <td>
                                    <?= isset($s['grades'][$period]['grade_value']) && $s['grades'][$period]['grade_value'] !== null ? number_format($s['grades'][$period]['grade_value'], 2) : '-' ?>
                                </td>
                            <?php endforeach; ?>
                            <td>
                                <?php 
                                // Compute the literal final grade as the average of Prelims, Midterms, Semifinals, Finals
                                $periods = ['Prelims', 'Midterms', 'Semifinals', 'Finals'];
                                $gradesForAvg = [];
                                foreach ($periods as $p) {
                                    if (isset($s['grades'][$p]['grade_value']) && $s['grades'][$p]['grade_value'] !== null && $s['grades'][$p]['grade_value'] !== '') {
                                        $gradesForAvg[] = floatval($s['grades'][$p]['grade_value']);
                                    }
                                }
                                $finalGrade = null;
                                if (count($gradesForAvg) > 0) {
                                    $finalGrade = round(array_sum($gradesForAvg) / count($gradesForAvg), 2);
                                }
                                ?>
                                <?= $finalGrade !== null ? number_format($finalGrade, 2) : '-' ?>
                            </td>
                            <td>
                                <?php
                                // Status logic: 1.0-3.0 Passed, 3.1-5.0 Failed, based on computed final grade
                                if ($finalGrade !== null && is_numeric($finalGrade)) {
                                    if ($finalGrade >= 1.0 && $finalGrade <= 3.0) {
                                        echo '<span class="badge passed">Passed</span>';
                                    } elseif ($finalGrade > 3.0 && $finalGrade <= 5.0) {
                                        echo '<span class="badge failed">Failed</span>';
                                    } else {
                                        echo '<span class="badge neutral">-</span>';
                                    }
                                } else {
                                    echo '<span class="badge neutral">-</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
<?php endforeach; ?>

</div>

<style>

</style>

</body>
</html>
