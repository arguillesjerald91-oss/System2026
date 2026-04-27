<?php 
// Include db for database connection
include 'db.php';
$database = new Database();
$conn = $database->getConnection();

session_start();

if (isset($_SESSION['userRole']) && $_SESSION['userRole'] === 'student') {
    $_SESSION['userRole'] = 'trainee';
}

// Set page info for sidebar
$currentPage = 'courses.php';
$pageTitle = 'My Courses';
$pageSubtitle = 'Learning Modules & Curriculum';

include 'sidebar_student.php';
?>
$database = new Database();
$conn = $database->getConnection();

// Redirect if not logged in or not a trainee/student
if (!isset($_SESSION['userId']) || !in_array($_SESSION['userRole'], ['trainee', 'student'])) {
    header("Location: ../login.php");
    exit();
}

// Get student info from session
$userId = $_SESSION['userId'];

// DEBUG: Check what we have in session
error_log("DEBUG: userId from session = " . $userId . ", userRole = " . $_SESSION['userRole']);

// First, try to get StudID from student table using the UserId from login
// Dynamic column detection for student table
$hasFirstName = columnExists($conn, 'student', 'FirstName');
$hasFName = columnExists($conn, 'student', 'FName');
$hasLastName = columnExists($conn, 'student', 'LastName');
$hasLName = columnExists($conn, 'student', 'LName');
$hasYearLvl = columnExists($conn, 'student', 'YearLvl');

$firstNameCol = $hasFirstName ? 'FirstName' : ($hasFName ? 'FName' : 'FName');
$lastNameCol = $hasLastName ? 'LastName' : ($hasLName ? 'LName' : 'LName');
$yearCol = $hasYearLvl ? 'YearLvl' : 'NULL';

$sql = "SELECT StudID as student_id, SchoolID as school_id, $firstNameCol as first_name, $lastNameCol as last_name, Course as course, $yearCol as year_level, Semester as semester FROM student WHERE StudID = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$userId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
error_log("DEBUG: Approach 1 (StudID match) - Found: " . ($student ? "YES" : "NO"));

// Approach 2: If not found, try matching by username from users table
if (!$student && isset($_SESSION['userName'])) {
    // Get the student by username (assuming student username = SchoolID or StudID)
    $userStmt = $conn->prepare("SELECT UserID FROM users WHERE UserID = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && isset($_SESSION['userName'])) {
        // Try to find student by username (which might be SchoolID)
        $sqlStud = "SELECT StudID as student_id, SchoolID as school_id, $firstNameCol as first_name, $lastNameCol as last_name, Course as course, $yearCol as year_level, Semester as semester FROM student WHERE SchoolID = ? OR StudID = ?";
        $stmtStud = $conn->prepare($sqlStud);
        $stmtStud->execute([$_SESSION['userName'], $_SESSION['userName']]);
        $student = $stmtStud->fetch(PDO::FETCH_ASSOC);
        error_log("DEBUG: Approach 2 (username match) - Found: " . ($student ? "YES" : "NO") . " for username: " . $_SESSION['userName']);
    }
}

// Approach 3: Check if session has studentId directly
if (!$student && isset($_SESSION['studentId'])) {
$sql = "SELECT StudID as student_id, SchoolID as school_id, $firstNameCol as first_name, $lastNameCol as last_name, Course as course, $yearCol as year_level, Semester as semester FROM student WHERE StudID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_SESSION['studentId']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("DEBUG: Approach 3 (studentId from session) - Found: " . ($student ? "YES" : "NO"));
}

if (!$student) {
    echo "Student record not found.";
    exit();
}

// Get student's NC level enrollment first
$studentNcLevel = 'Not Assigned';
try {
    $ncLevelSql = "SELECT spe.nc_level, spe.program_id, ap.program_level
                   FROM student_program_enrollments spe
                   JOIN student s ON spe.student_id = s.StudID
                   LEFT JOIN auto_mechanic_programs ap ON spe.program_id = ap.program_id
                   WHERE s.user_id = ? AND spe.enrollment_status = 'Active'
                   ORDER BY spe.enrollment_id DESC LIMIT 1";
    $stmt = $conn->prepare($ncLevelSql);
    $stmt->execute([$userId]);
    $ncEnrollment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($ncEnrollment) {
        $studentNcLevel = $ncEnrollment['nc_level'];
        error_log("DEBUG: Student NC Level: $studentNcLevel");
    }
} catch (Exception $e) {
    error_log("Error fetching NC level: " . $e->getMessage());
}

// Fetch subjects from NC level mapping instead of traditional enrollment
$assignedSubjects = [];
try {
    if ($studentNcLevel !== 'Not Assigned') {
        $enrollmentSql = "SELECT DISTINCT 
                            lm.module_id as id,
                            lm.module_title as title,
                            lm.module_type as course_code,
                            lm.duration_mins as units,
                            lm.nc_level as year_level,
                            'Current Semester' as semester,
                            '2025-2026' as academic_year,
                            'Online' as day_of_week,
                            'Flexible' as start_time,
                            'Self-paced' as end_time,
                            'Virtual' as room,
                            nls.is_required,
                            nls.sort_order
                         FROM learning_modules lm
                         JOIN nc_level_subjects nls ON lm.module_id = nls.module_id
                         WHERE lm.is_active = 1 AND nls.nc_level = ?
                         ORDER BY nls.sort_order ASC, lm.module_id ASC";
        
        $stmt = $conn->prepare($enrollmentSql);
        $stmt->execute([$studentNcLevel]);
        $assignedSubjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("DEBUG: Found " . count($assignedSubjects) . " NC level subjects for $studentNcLevel");
    }
    
} catch (Exception $e) {
    error_log("Error fetching NC level subjects: " . $e->getMessage());
    $assignedSubjects = [];
}

// Calculate statistics
$activeCourses = count($assignedSubjects);
$totalUnits = array_sum(array_column($assignedSubjects, 'units'));

// Group subjects by year level and semester
// Group subjects by academic year → year level → semester
$groupedSubjects = [];
foreach ($assignedSubjects as $subject) {
    $academicYear = $subject['academic_year'] ?? 'Unknown AY';
    $year = isset($subject['year_level']) ? $subject['year_level'] . 'st Year' : 'No Year';
    if ($subject['year_level'] == 2) $year = '2nd Year';
    if ($subject['year_level'] == 3) $year = '3rd Year';
    if ($subject['year_level'] == 4) $year = '4th Year';
    $semester = $subject['semester'] ?? $student['semester'] ?? '1st Semester';
    $groupedSubjects[$academicYear][$year][$semester][] = $subject;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Courses - Trainee Dashboard</title>
    <link rel="stylesheet" href="css/courses.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="main-content">
    <div class="header">
        <h1> My Courses - <?= htmlspecialchars($studentNcLevel) ?></h1>
        <p>Training modules for your <?= htmlspecialchars($studentNcLevel) ?> level</p>
    </div>

    <!-- DEBUG INFO (Remove after fixing) -->
    <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
    <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 5px; font-family: monospace; font-size: 11px; max-height: 600px; overflow-y: auto;">
        <strong>DEBUG INFO:</strong><br>
        UserId: <?php echo htmlspecialchars($userId); ?><br>
        Student ID: <?php echo htmlspecialchars($student['student_id'] ?? 'NOT FOUND'); ?><br>
        School ID: <?php echo htmlspecialchars($student['school_id'] ?? 'N/A'); ?><br>
        Student Name: <?php echo htmlspecialchars($student['first_name'] ?? '') . ' ' . htmlspecialchars($student['last_name'] ?? ''); ?><br>
        Assigned Subjects Count: <?php echo count($assignedSubjects); ?><br>
        <br>
        
        <!-- Database structure debugging -->
        <?php
        try {
            // Check student_subjects table structure
            $checkTableSql = "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE '%student%subject%'";
            $checkStmt = $conn->prepare($checkTableSql);
            $checkStmt->execute();
            $tables = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
            echo "<strong>Found tables:</strong> " . implode(', ', $tables) . "<br><br>";
            
            if (!empty($tables)) {
                $tbl = $tables[0];
                echo "<strong>Using table: {$tbl}</strong><br>";
                
                // Show columns
                $colSql = "SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
                $colStmt = $conn->prepare($colSql);
                $colStmt->execute([$tbl]);
                $columns = $colStmt->fetchAll(PDO::FETCH_ASSOC);
                echo "<strong>Table columns:</strong><br>";
                foreach ($columns as $col) {
                    echo "  - {$col['COLUMN_NAME']} ({$col['COLUMN_TYPE']})<br>";
                }
                
                // Show sample data from the table
                echo "<br><strong>Sample data from {$tbl}:</strong><br>";
                $sampleSql = "SELECT * FROM {$tbl} LIMIT 10";
                $sampleStmt = $conn->prepare($sampleSql);
                $sampleStmt->execute();
                $samples = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($samples)) {
                    echo "<pre>" . print_r($samples, true) . "</pre>";
                } else {
                    echo "No records found in table!<br>";
                }
                
                // Try to find records for this student
                echo "<br><strong>Searching for Student 23-123452 in {$tbl}:</strong><br>";
                $searchSql = "SELECT * FROM {$tbl} WHERE SchoolID = ? OR SchoolID LIKE ? OR StudID = ? OR StudID LIKE ?";
                $searchStmt = $conn->prepare($searchSql);
                $searchStmt->execute(['23-123452', '%23-123452%', '1000010', '%1000010%']);
                $searchResults = $searchStmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($searchResults)) {
                    echo "<pre>" . print_r($searchResults, true) . "</pre>";
                } else {
                    echo "No matching records found for this student!<br>";
                }
            }
        } catch (Exception $e) {
            echo "Error: " . htmlspecialchars($e->getMessage());
        }
        ?>
        <br>
        <a href="?debug=0" style="color: #dc3545;">Hide Debug</a>
    </div>
    <?php elseif (count($assignedSubjects) == 0): ?>
    <div style="background: #f0f0f0; border: 1px solid #ccc; padding: 10px; margin-bottom: 20px; border-radius: 5px; text-align: center;">
        ⚠️ No subjects found. <a href="?debug=1" style="color: #007bff;">Show Debug Info</a> to troubleshoot.
    </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-container">
        <div class="stat-card">
            <h3><?php echo $activeCourses; ?></h3>
            <p>Active Courses</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $totalUnits; ?></h3>
            <p>Units</p>
        </div>
    </div>

    <!-- Course Cards Grouped by Academic Year, Year, and Semester -->
    <?php if (count($assignedSubjects) > 0): ?>
        <?php foreach ($groupedSubjects as $academicYear => $years): ?>
            <h1 style="color: #2563eb; margin: 40px 0 10px 0; padding-bottom: 5px; border-bottom: 4px solid #2563eb; font-size: 28px;">
                <i class="fa-solid fa-calendar-alt"></i> Academic Year: <?php echo htmlspecialchars($academicYear); ?>
            </h1>
            <?php foreach ($years as $year => $semesters): ?>
                <h2 style="color: #2c3e50; margin: 30px 0 20px 0; padding-bottom: 10px; border-bottom: 3px solid #3498db; font-size: 24px;">
                    <i class="fa-solid fa-graduation-cap"></i> <?php echo htmlspecialchars($year); ?>
                </h2>
                <?php foreach ($semesters as $semester => $courses): ?>
                    <div style="margin-bottom: 30px;">
                        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; border-radius: 10px 10px 0 0; display: flex; justify-content: space-between; align-items: center;">
                            <h3 style="margin: 0; font-size: 20px;">
                                <i class="fa-solid fa-calendar-alt"></i> <?php echo htmlspecialchars($semester); ?>
                            </h3>
                            <span style="background: rgba(255,255,255,0.2); padding: 5px 15px; border-radius: 20px; font-weight: bold;">
                                <?php echo count($courses); ?> Subject(s)
                            </span>
                        </div>
                        <div class="course-cards" style="margin-top: 0; background: #f8f9fa; padding: 20px; border-radius: 0 0 10px 10px;">
                            <?php foreach ($courses as $course): ?>
                                <div class="course-card">
                                    <div class="course-header">
                                        <div>
                                            <h2 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h2>
                                            <span class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></span>
                                        </div>
                                    </div>
                                    <div class="course-info">
                                        <div class="course-info-item">
                                            <i class="fa-solid fa-weight-scale"></i>
                                            <span>Units: <?php echo htmlspecialchars($course['units']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="no-courses">
            <i class="fa-solid fa-book"></i>
            <?php if ($studentNcLevel !== 'Not Assigned'): ?>
                <h3>No Courses for <?= htmlspecialchars($studentNcLevel) ?> Level</h3>
                <p>No training modules have been assigned to your <?= htmlspecialchars($studentNcLevel) ?> level yet. Please contact your administrator to assign subjects to your NC level.</p>
            <?php else: ?>
                <h3>No NC Level Enrollment</h3>
                <p>You haven't been enrolled in an NC level program yet. Please contact your administrator to complete your enrollment.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
