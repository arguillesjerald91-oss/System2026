
<?php
// Include db.php first for database connection
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

if ($conn === null) {
    die("Database connection unavailable. Please try again later.");
}

// Redirect if not logged in
if (!isset($_SESSION['userId']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Normalize role for trainee terminology
if (isset($_SESSION['userRole']) && $_SESSION['userRole'] === 'student') {
    $_SESSION['userRole'] = 'trainee';
}

// Use the session key set at login
$userId = $_SESSION['userId'] ?? $_SESSION['user_id'] ?? null;

// Set page info for sidebar
$currentPage = 'schedule.php';
$pageTitle = 'Schedule';
$pageSubtitle = 'Training Schedule & Calendar';

// Include sidebar (which includes all HTML structure)
include 'sidebar_student.php';

// Continue with page content below sidebar
?>
function columnExists(PDO $conn, string $table, string $column): bool {
  $stmt = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
  $stmt->execute([$table, $column]);
  return (bool)$stmt->fetchColumn();
}
function tableExists(PDO $conn, string $table): bool {
  $stmt = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
  $stmt->execute([$table]);
  return (bool)$stmt->fetchColumn();
}

// Fetch student info - detect actual column names dynamically
$hasFName = columnExists($conn, 'student', 'FName');
$hasFirstName = columnExists($conn, 'student', 'FirstName');
$hasLName = columnExists($conn, 'student', 'LName');
$hasLastName = columnExists($conn, 'student', 'LastName');

$firstNameCol = $hasFirstName ? 'FirstName' : ($hasFName ? 'FName' : 'FName');
$lastNameCol = $hasLastName ? 'LastName' : ($hasLName ? 'LName' : 'LName');

// Check what columns exist
$hasYearLvl = columnExists($conn, 'student', 'YearLvl');
$yearCol = $hasYearLvl ? 'YearLvl' : 'NULL';

// Get student data with available columns only
$sql = "SELECT StudID, $firstNameCol as FName, $lastNameCol as LName, Course" . ($hasYearLvl ? ', YearLvl' : '') . " FROM student WHERE StudID = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$userId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
  echo "Student record not found.";
  exit();
}

// Fetch assigned schedules for this student
// Join student_schedules with schedules, subject, instructor, and room tables
// Build a resilient query based on available columns/tables

$hasDayOfWeek  = columnExists($conn, 'schedules', 'day_of_week');
$hasStartTime  = columnExists($conn, 'schedules', 'start_time');
$hasEndTime    = columnExists($conn, 'schedules', 'end_time');
$hasSchedText  = columnExists($conn, 'schedules', 'schedule');
$hasSubjectRel = tableExists($conn, 'subject')    && columnExists($conn, 'schedules', 'subject_id');
$hasInstructor = tableExists($conn, 'instructor') && columnExists($conn, 'schedules', 'instructor_id');
$hasRooms      = tableExists($conn, 'rooms')      && columnExists($conn, 'schedules', 'room_id');
// Year/Semester detection
$hasSchedYear  = columnExists($conn, 'schedules', 'year_level');
$hasSchedSem   = columnExists($conn, 'schedules', 'semester');
$hasSubjYear   = tableExists($conn, 'subject') && columnExists($conn, 'subject', 'year_level');
$hasAcademicYr = columnExists($conn, 'schedules', 'academic_year');

$selectParts = [
  's.schedule_id'
];
// Subject/course info
if ($hasSubjectRel) {
  $selectParts[] = 'sub.SubCode AS code';
  $selectParts[] = 'sub.SubName AS subject';
  $selectParts[] = 'sub.Unit AS unit';
  // Subject year fallback
  if ($hasSubjYear) {
    $selectParts[] = 'sub.year_level AS subj_year_level';
  } else {
    $selectParts[] = 'NULL AS subj_year_level';
  }
} else {
  $selectParts[] = 'NULL AS code';
  $selectParts[] = 'NULL AS subject';
  $selectParts[] = '0 AS unit';
  $selectParts[] = 'NULL AS subj_year_level';
}
// Room
if ($hasRooms) {
  $selectParts[] = 'r.room_number AS room';
} else {
  $selectParts[] = 'NULL AS room';
}
// Instructor
if ($hasInstructor) {
  $selectParts[] = "CONCAT(i.FName, ' ', i.LName) AS instructor";
} else {
  $selectParts[] = 'NULL AS instructor';
}
// Schedule display string
if ($hasDayOfWeek && $hasStartTime && $hasEndTime) {
  // Format with AM/PM
  $selectParts[] = "CONCAT(s.day_of_week, ' ', DATE_FORMAT(s.start_time, '%h:%i %p'), '-', DATE_FORMAT(s.end_time, '%h:%i %p')) AS schedule";
} elseif ($hasSchedText) {
  $selectParts[] = 's.schedule AS schedule';
} else {
  $selectParts[] = 'NULL AS schedule';
}
// Year/Semester fields from schedules if available
if ($hasSchedYear) {
  $selectParts[] = 's.year_level AS year_level';
} else {
  $selectParts[] = 'NULL AS year_level';
}
if ($hasSchedSem) {
  $selectParts[] = 's.semester AS semester';
} else {
  $selectParts[] = 'NULL AS semester';
}
if ($hasAcademicYr) {
  $selectParts[] = 's.academic_year AS academic_year';
} else {
  $selectParts[] = 'NULL AS academic_year';
}

// Use enrollment table instead of student_schedules
$sql2  = 'SELECT ' . implode(', ', $selectParts) . ' FROM enrollment e JOIN schedules s ON e.schedule_id = s.schedule_id';
if ($hasSubjectRel)  $sql2 .= ' LEFT JOIN subject sub ON s.subject_id = sub.SubjectID';
if ($hasInstructor)  $sql2 .= ' LEFT JOIN instructor i ON s.instructor_id = i.InsID';
if ($hasRooms)       $sql2 .= ' LEFT JOIN rooms r ON s.room_id = r.room_id';
$sql2 .= ' WHERE e.StudID = ?';
// Order by day/time if available, else by schedule_id
if ($hasDayOfWeek && $hasStartTime) {
  $sql2 .= " ORDER BY FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), s.start_time";
} else {
  $sql2 .= ' ORDER BY s.schedule_id DESC';
}

$stmt2 = $conn->prepare($sql2);
$stmt2->execute([$student['StudID']]);
$schedules = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Include header/sidebar after $student is available

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Class Schedule</title>
  <link rel="stylesheet" href="css/schedule.css">
  <link rel="stylesheet" href="css/courses.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>


<div class="main-content">
  <div class="schedule-header">
 <h2> Class Schedule</h2>
    <p>Your weekly class schedule</p>
  </div>

  <!-- Summary section -->
  <div class="schedule-summary">
    <div class="summary-card">
      <h3><?php echo count($schedules); ?></h3>
      <p>Courses</p>
    </div>
    <div class="summary-card">
      <h3>
        <?php 
          $totalUnits = array_sum(array_column($schedules, 'unit'));
          echo $totalUnits;
        ?>
      </h3>
      <p>Weekly Hours</p>
    </div>
   <div class="summary-card">
  <h3>
    <?php 
      if (empty($schedules)) {
        echo 0;
      } else {
        // Extract days (first word of each schedule string)
        $days = array_map(fn($s) => strtok($s['schedule'], ' '), $schedules);
        $uniqueDays = count(array_unique($days));
        echo $uniqueDays;
      }
    ?>
  </h3>
  <p>Days/Week</p>
</div>
    <div class="summary-card">
      <h3><?php echo count($schedules); ?></h3>
      <p>Sessions</p>
    </div>
  </div>

  <!-- Schedule grouped by Year and Semester (styled like courses) -->
  <div class="schedule-container">
    <?php
      // Helper to format year level display
      $formatYear = function($y) {
        if ($y === null || $y === '') return 'Unknown Year';
        $map = [1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year'];
        if (is_numeric($y)) {
          $n = (int)$y;
          return $map[$n] ?? (string)$y;
        }
        return $y; // already text like '1st Year'
      };

      // Normalize year/semester on each schedule row
      foreach ($schedules as &$s) {
        $yearSrc = $s['year_level'] ?? null;
        if (!$yearSrc) $yearSrc = $s['subj_year_level'] ?? null;
        $s['year_display'] = $formatYear($yearSrc);

        $sem = $s['semester'] ?? '';
        if ($sem === '' || $sem === null) $sem = '1st Sem';
        elseif (is_numeric($sem)) $sem = ((int)$sem === 1 ? '1st Sem' : ((int)$sem === 2 ? '2nd Sem' : 'Semester ' . $sem));
        $s['sem_display'] = $sem;
      }
      unset($s);

      // Group by year → semester
      // Group by academic year → year → semester
      $grouped = [];
      foreach ($schedules as $row) {
        $ay = $row['academic_year'] ?? 'Unknown AY';
        $y = $row['year_display'];
        $m = $row['sem_display'];
        if (!isset($grouped[$ay])) $grouped[$ay] = [];
        if (!isset($grouped[$ay][$y])) $grouped[$ay][$y] = [];
        if (!isset($grouped[$ay][$y][$m])) $grouped[$ay][$y][$m] = [];
        $grouped[$ay][$y][$m][] = $row;
      }

      // Render groups by academic year, then year, then semester
      foreach ($grouped as $academicYear => $years): ?>
        <h1 style="color: #2563eb; margin: 40px 0 10px 0; padding-bottom: 5px; border-bottom: 4px solid #2563eb; font-size: 28px;">
          <i class="fa-solid fa-calendar-alt"></i> Academic Year: <?php echo htmlspecialchars($academicYear); ?>
        </h1>
        <?php foreach ($years as $year => $semesters): ?>
          <h2 style="color: #2c3e50; margin: 30px 0 20px 0; padding-bottom: 10px; border-bottom: 3px solid #3498db; font-size: 24px;">
            <i class="fa-solid fa-graduation-cap"></i> <?php echo htmlspecialchars($year); ?>
          </h2>
          <?php foreach ($semesters as $semester => $classes): ?>
            <div style="margin-bottom: 30px;">
              <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; border-radius: 10px 10px 0 0; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 20px;">
                  <i class="fa-solid fa-calendar-alt"></i> <?php echo htmlspecialchars($semester); ?>
                </h3>
                <span style="background: rgba(255,255,255,0.2); padding: 5px 15px; border-radius: 20px; font-weight: bold;">
                  <?php echo count($classes); ?> Class(es)
                </span>
              </div>

              <div class="course-cards" style="margin-top: 0; background: #f8f9fa; padding: 20px; border-radius: 0 0 10px 10px;">
                <?php foreach ($classes as $c): ?>
                  <div class="course-card">
                    <div class="course-header">
                      <div>
                        <h2 class="course-title"><?php echo htmlspecialchars($c['subject']); ?></h2>
                        <span class="course-code"><?php echo htmlspecialchars($c['code']); ?></span>
                      </div>
                    </div>

                    <div class="course-info">
                      <div class="course-info-item">
                        <i class="fa-regular fa-clock"></i>
                        <span><?php echo htmlspecialchars($c['schedule']); ?></span>
                      </div>
                      <div class="course-info-item">
                        <i class="fa-solid fa-location-dot"></i>
                        <span><?php echo htmlspecialchars($c['room']); ?></span>
                      </div>
                      <div class="course-info-item">
                        <i class="fa-solid fa-user-tie"></i>
                        <span><?php echo htmlspecialchars($c['instructor']); ?></span>
                      </div>
                      <div class="course-info-item">
                        <i class="fa-solid fa-calendar-week"></i>
                        <span><?php echo htmlspecialchars($c['semester'] ?? ''); ?></span>
                      </div>
                      <div class="course-info-item">
                        <i class="fa-solid fa-calendar-alt"></i>
                        <span><?php echo htmlspecialchars($c['academic_year'] ?? ''); ?></span>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endforeach; ?>
      <?php endforeach; ?>
  </div>
</div>
</body>
</html>
