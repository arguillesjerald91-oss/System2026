<?php
session_start();
include 'db.php';
include_once __DIR__ . '/log_activity.php';
$database = new Database();
$conn = $database->getConnection();

// Fetch schedules with related data
$schedules = [];
try {
  $sql = "SELECT 
            s.schedule_id,
            s.subject_id,
            s.instructor_id,
            s.section_id,
            s.room_id,
            subj.SubName AS subject,
            subj.SubCode AS subject_code,
            subj.year_level,
            inst.FName AS instructor_first_name,
            inst.LName AS instructor_last_name,
            sec.name AS section,
            r.room_number AS room,
            s.day_of_week,
            s.start_time,
            s.end_time,
            s.semester,
            s.academic_year
          FROM schedules s
          LEFT JOIN subject subj ON s.subject_id = subj.SubjectID
          LEFT JOIN instructor inst ON s.instructor_id = inst.InsID
          LEFT JOIN sections sec ON s.section_id = sec.section_id
          LEFT JOIN rooms r ON s.room_id = r.room_id
          ORDER BY s.semester ASC, subj.year_level ASC, s.schedule_id ASC";
  $stmt = $conn->prepare($sql);
  $stmt->execute();
  $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $schedules = [];
}

// Fetch students from database
$students = [];
try {
  $sql2 = "SELECT StudID AS student_id, FirstName AS first_name, LastName AS last_name, Course AS course, YearLvl AS year_level, Semester AS semester FROM student";
  $stmt2 = $conn->prepare($sql2);
  $stmt2->execute();
  $students = $stmt2->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $students = [];
}

// Handle assigning schedule to student (insert into enrollment table)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_schedule'])) {
    $student_id = $_POST['student_id'];
    $schedule_ids = $_POST['schedule_ids'] ?? [];

    if (empty($schedule_ids)) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=no_schedule");
        exit;
    }

    $count = 0;
    foreach ($schedule_ids as $schedule_id) {
        // Get schedule and student info for logging
        $getSql = "SELECT subj.SubName AS subject, st.FirstName, st.LastName 
                   FROM schedules s
                   LEFT JOIN subject subj ON s.subject_id = subj.SubjectID
                   CROSS JOIN student st 
                   WHERE s.schedule_id = ? AND st.StudID = ?";
        $getStmt = $conn->prepare($getSql);
        $getStmt->execute([$schedule_id, $student_id]);
        $details = $getStmt->fetch(PDO::FETCH_ASSOC);

        // Check if already enrolled in this schedule
        $checkSql = "SELECT EnrollID FROM enrollment WHERE StudID = ? AND schedule_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([$student_id, $schedule_id]);
        
        if ($checkStmt->rowCount() == 0) {
            // Insert into enrollment table
            $sql = "INSERT INTO enrollment (StudID, schedule_id, enrollment_date) VALUES (?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$student_id, $schedule_id]);

            if ($stmt->rowCount() > 0) {
                $count++;
                // Log activity
                if ($details) {
                    logActivity('Student Enrolled', "Student enrolled in schedule - Student ID: $student_id ({$details['FirstName']} {$details['LastName']}), Subject: {$details['subject']}", $conn);
                } else {
                    logActivity('Student Enrolled', "Student enrolled in schedule - Student ID: $student_id, Schedule ID: $schedule_id", $conn);
                }
            }
        }
    }

    // Safe redirect to same file with success flag
    header("Location: " . $_SERVER['PHP_SELF'] . "?assigned=$count");
    exit;
}
// Handle schedule update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_schedule'])) {
    $id = $_POST['edit_id'];
    $subject_id = $_POST['edit_subject_id'];
    $instructor_id = $_POST['edit_instructor_id'];
    $section_id = $_POST['edit_section_id'];
    $room_id = $_POST['edit_room_id'];
    $day_of_week = $_POST['edit_day_of_week'];
    $start_time = $_POST['edit_start_time'];
    $end_time = $_POST['edit_end_time'];
    $semester = $_POST['edit_semester'];
    $academic_year = $_POST['edit_academic_year'];

    $sql = "UPDATE schedules SET subject_id=?, instructor_id=?, section_id=?, room_id=?, day_of_week=?, start_time=?, end_time=?, semester=?, academic_year=? WHERE schedule_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$subject_id, $instructor_id, $section_id, $room_id, $day_of_week, $start_time, $end_time, $semester, $academic_year, $id]);

    // Log activity
    logActivity('Schedule Updated', "Schedule updated - ID: $id, Semester: $semester, Academic Year: $academic_year", $conn);

    header("Location: " . $_SERVER['PHP_SELF'] . "?updated=1");
    exit;
}

// Handle schedule deletion
if (isset($_GET['delete_schedule'])) {
    $deleteId = $_GET['delete_schedule'];

    // Get schedule info before deleting for logging
    $getSql = "SELECT subj.SubName AS subject, s.day_of_week FROM schedules s LEFT JOIN subject subj ON s.subject_id = subj.SubjectID WHERE s.schedule_id = ?";
    $getStmt = $conn->prepare($getSql);
    $getStmt->execute([$deleteId]);
    $scheduleData = $getStmt->fetch(PDO::FETCH_ASSOC);

    $sql = "DELETE FROM schedules WHERE schedule_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$deleteId]);

    // Log activity
    if ($scheduleData) {
        logActivity('Schedule Deleted', "Schedule deleted - ID: $deleteId, Subject: {$scheduleData['subject']}, Day: {$scheduleData['day_of_week']}", $conn);
    } else {
        logActivity('Schedule Deleted', "Schedule deleted - ID: $deleteId", $conn);
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "?deleted=1");
    exit;
}

// Handle enrollment deletion (from enrollment table)
if (isset($_GET['delete_assignment'])) {
    $enrollmentId = $_GET['delete_assignment'];

    // Get enrollment info before deleting for logging
        $getSql = "SELECT st.StudID, st.FirstName, st.LastName, subj.SubName 
                   FROM enrollment e
                   JOIN student st ON e.StudID = st.StudID
                   JOIN schedules s ON e.schedule_id = s.schedule_id
                   LEFT JOIN subject subj ON s.subject_id = subj.SubjectID
                   WHERE e.EnrollID = ?";
    $getStmt = $conn->prepare($getSql);
    $getStmt->execute([$enrollmentId]);
    $assignmentData = $getStmt->fetch(PDO::FETCH_ASSOC);

    $sql = "DELETE FROM enrollment WHERE EnrollID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$enrollmentId]);

    // Log activity
    if ($assignmentData) {
        logActivity('Enrollment Deleted', "Enrollment removed - Student: {$assignmentData['FirstName']} {$assignmentData['LastName']} (ID: {$assignmentData['StudID']}), Subject: {$assignmentData['SubName']}", $conn);
    } else {
        logActivity('Enrollment Deleted', "Enrollment removed - Enrollment ID: $enrollmentId", $conn);
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "?assignment_deleted=1");
    exit;
}


// Get options for dropdowns
$subjects = [];
$instructors = [];
$sections = [];
$rooms = [];
$semesters = ['1st Semester', '2nd Semester', 'Summer'];
$daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// History of enrolled schedules (from enrollment table)
$assignmentHistory = [];
try {
  $histSql = "SELECT 
      e.EnrollID AS enrollment_id,
      e.enrollment_date AS assigned_at,
      st.SchoolID AS school_id,
      st.FirstName AS student_first_name,
      st.LastName AS student_last_name,
      subj.SubCode AS subject_code,
      subj.SubName AS subject_name,
      s.day_of_week,
      s.start_time,
      s.end_time,
      s.semester,
      s.academic_year
    FROM enrollment e
    JOIN student st ON e.StudID = st.StudID
    JOIN schedules s ON e.schedule_id = s.schedule_id
    LEFT JOIN subject subj ON s.subject_id = subj.SubjectID
    ORDER BY e.enrollment_date DESC";
  $histStmt = $conn->prepare($histSql);
  $histStmt->execute();
  $assignmentHistory = $histStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

try {
  $stmt = $conn->prepare("SELECT SubjectID as subject_id, SubName as name FROM subject");
  $stmt->execute();
  $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

try {
  $stmt = $conn->prepare("SELECT InsID as instructor_id, CONCAT(FName, ' ', LName) as name FROM instructor");
  $stmt->execute();
  $instructors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

try {
  $stmt = $conn->prepare("SELECT section_id, name FROM sections");
  $stmt->execute();
  $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

try {
  $stmt = $conn->prepare("SELECT room_id, name FROM rooms");
  $stmt->execute();
  $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$recordsPerPage = 5;
?>

<?php include 'header.php'; ?>
<?php include 'sidebar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Class Schedules - Admin Dashboard</title>
  <link rel="stylesheet" href="css/schedules.css">
</head>
<body>

  <div class="main-content">
    <div class="header">
       <h2><i class="fa-solid fa-clock"></i> Assign Schedules</h2>
    </div>

    <div class="form-container" id="assignSection">
  <h2>Assign Schedule to Student</h2>
  <form method="POST">
    <!-- Student selection -->
    <select name="student_id" id="studentSelect" required style="max-width: 260px; min-width: 200px; width: 260px; display: inline-block;">
      <option value="">-- Select Student --</option>
      <?php
        $filteredStudents = $students;
        if(isset($_GET['filter_course'])) {
            $filteredStudents = array_filter($filteredStudents, fn($s) => $s['course']==$_GET['filter_course']);
        }
        if(isset($_GET['filter_year'])) {
            $filteredStudents = array_filter($filteredStudents, fn($s) => $s['year_level']==$_GET['filter_year']);
        }
        foreach($filteredStudents as $stu): ?>
          <option value="<?php echo $stu['student_id'] ?? $stu['StudID']; ?>" data-semester="<?php echo htmlspecialchars($stu['semester'] ?? ''); ?>">
            <?php echo ($stu['first_name'] ?? $stu['FirstName']).' '.($stu['last_name'] ?? $stu['LastName']); ?>
          </option>
      <?php endforeach; ?>
    </select>

    <!-- Schedule selection with checkboxes -->
    <div style="margin-top: 10px;">
      <label style="display: block; margin-bottom: 8px; font-weight: 500;">Select Schedules:</label>
      <div id="scheduleCheckboxContainer" style="max-width: 600px; min-width: 300px; max-height: 250px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 10px; background: #fff;">
        <?php foreach($schedules as $sch): ?>
          <label class="schedule-checkbox-item" data-semester="<?php echo htmlspecialchars($sch['semester']); ?>" data-subject-id="<?php echo $sch['subject_id'] ?? 0; ?>" style="display: none; padding: 8px; margin-bottom: 5px; border-radius: 4px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#f0f0f0'" onmouseout="this.style.background='transparent'">
            <input type="checkbox" name="schedule_ids[]" value="<?php echo $sch['schedule_id']; ?>" style="margin-right: 8px;">
            <span style="font-size: 14px;">
              <?php
                echo ($sch['subject_code'] ?? 'N/A') . ' | ' .
                     ($sch['subject'] ?? 'N/A') . ' | ' .
                     (($sch['instructor_first_name'] ?? '') . ' ' . ($sch['instructor_last_name'] ?? '')) . ' | ' .
                     ($sch['room'] ?? 'N/A') . ' | ' .
                     ($sch['day_of_week'] ?? 'Day') . ' ' . ($sch['start_time'] ?? '') . '-' . ($sch['end_time'] ?? '') . ' | ' .
                     ($sch['semester'] ?? '') . ' | ' .
                     ($sch['academic_year'] ?? '');
              ?>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <button type="submit" name="assign_schedule" class="btn-primary" style="padding: 8px 20px; margin-top: 10px; width: 100px !important; min-width: 100px !important; max-width: 100px !important; display: inline-block; flex-grow: 0; flex-shrink: 0;">Assign</button>
  </form>
</div>


    <!-- Schedule List -->
    <div class="table-container">
      <h2>All Schedules</h2>


      <?php
      // Group schedules by academic year, then semester, then year level
      $schedulesByYear = [];
      foreach ($schedules as $schedule) {
        $academicYear = $schedule['academic_year'] ?? 'Unknown';
        $semester = $schedule['semester'] ?? 'Unknown';
        $yearLevel = $schedule['year_level'] ?? 'N/A';
        $key = $academicYear . '|' . $semester . '|' . $yearLevel;
        if (!isset($schedulesByYear[$academicYear])) {
          $schedulesByYear[$academicYear] = [];
        }
        if (!isset($schedulesByYear[$academicYear][$semester])) {
          $schedulesByYear[$academicYear][$semester] = [];
        }
        if (!isset($schedulesByYear[$academicYear][$semester][$yearLevel])) {
          $schedulesByYear[$academicYear][$semester][$yearLevel] = [];
        }
        $schedulesByYear[$academicYear][$semester][$yearLevel][] = $schedule;
      }

      // Helper function for ordinal numbers
      function ordinal($number) {
        if ($number === 'N/A') return $number;
        $ends = array('th','st','nd','rd','th','th','th','th','th','th');
        if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
          return $number . 'th';
        }
        return $number . $ends[$number % 10];
      }

      // Display each academic year group
      $groupIndex = 0;
      foreach ($schedulesByYear as $academicYear => $semesters):
        foreach ($semesters as $semester => $yearLevels):
          foreach ($yearLevels as $yearLevel => $groupSchedules):
            $groupIndex++;
            $groupPageKey = "page_schedule_" . $groupIndex;
            $groupCurrentPage = intval($_GET[$groupPageKey] ?? 1);
            $totalRecords = count($groupSchedules);
            $totalPages = ceil($totalRecords / $recordsPerPage);
            if ($totalPages < 1) $totalPages = 1;
            $groupCurrentPage = max(1, min($groupCurrentPage, $totalPages));
            $startIndex = ($groupCurrentPage - 1) * $recordsPerPage;
            $paginatedSchedules = array_slice($groupSchedules, $startIndex, $recordsPerPage);
      ?>
      <div class="schedule-group" style="margin-bottom: 40px;">
        <h3 style="margin-bottom: 15px; color: #2c3e50; border-left: 4px solid #3498db; padding-left: 10px;">
          <i class="fa-solid fa-calendar"></i>
          <?php echo htmlspecialchars($academicYear); ?> -
          <?php echo htmlspecialchars($semester); ?> -
          <?php echo ordinal($yearLevel); ?> Year
          <span style="font-size: 0.85em; color: #7f8c8d; margin-left: 10px;">
            (<?php echo $totalRecords; ?> schedules, Page <?php echo $groupCurrentPage; ?>/<?php echo $totalPages; ?>)
          </span>
        </h3>
        <table id="scheduleTable">
          <thead>
            <tr>
              <th>Subject Code</th>
              <th>Subject</th>
              <th>Instructor</th>
              <th>Section</th>
              <th>Room</th>
              <th>Day</th>
              <th>Time</th>
              <th>Academic Year</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($paginatedSchedules as $row): ?>
              <tr>
                <td data-label="Subject Code"><?php echo $row['subject_code'] ?? 'N/A'; ?></td>
                <td data-label="Subject"><?php echo $row['subject'] ?? 'N/A'; ?></td>
                <td data-label="Instructor"><?php echo (($row['instructor_first_name'] ?? '') . ' ' . ($row['instructor_last_name'] ?? '')); ?></td>
                <td data-label="Section"><?php echo $row['section'] ?? 'N/A'; ?></td>
                <td data-label="Room"><?php echo $row['room'] ?? 'N/A'; ?></td>
                <td data-label="Day"><?php echo $row['day_of_week']; ?></td>
                <td data-label="Time"><?php echo $row['start_time'] . ' - ' . $row['end_time']; ?></td>
                <td data-label="Academic Year"><?php echo $row['academic_year']; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <!-- Pagination Controls -->
        <div class="pagination-controls">
          <?php if ($groupCurrentPage > 1): ?>
            <a href="?<?php echo $groupPageKey; ?>=1" class="btn">« First</a>
            <a href="?<?php echo $groupPageKey; ?>=<?php echo $groupCurrentPage - 1; ?>" class="btn">‹ Prev</a>
          <?php endif; ?>
          <?php 
          $startPage = max(1, $groupCurrentPage - 2);
          $endPage = min($totalPages, $groupCurrentPage + 2);
          if ($startPage > 1): ?>
            <span class="ellipsis">...</span>
          <?php endif; ?>
          <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
            <?php if ($p == $groupCurrentPage): ?>
              <span class="current-page">
                <?php echo $p; ?>
              </span>
            <?php else: ?>
              <a href="?<?php echo $groupPageKey; ?>=<?php echo $p; ?>" class="btn">
                <?php echo $p; ?>
              </a>
            <?php endif; ?>
          <?php endfor; ?>
          <?php if ($endPage < $totalPages): ?>
            <span class="ellipsis">...</span>
          <?php endif; ?>
          <?php if ($groupCurrentPage < $totalPages): ?>
            <a href="?<?php echo $groupPageKey; ?>=<?php echo $groupCurrentPage + 1; ?>" class="btn">Next ›</a>
            <a href="?<?php echo $groupPageKey; ?>=<?php echo $totalPages; ?>" class="btn">Last »</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endforeach; ?>
      <?php endforeach; ?>

  <h2 style="margin-top:30px;">Assigned Schedules</h2>
  <table>
    <thead>
      <tr>
        <th>Enrolled At</th>
        <th>Student</th>
        <th>Subject Code</th>
        <th>Subject</th>
        <th>Day</th>
        <th>Time</th>
        <th>Semester</th>
        <th>Academic Year</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($assignmentHistory)): ?>
        <tr><td colspan="8" style="text-align:center;">No enrollments yet</td></tr>
      <?php else: ?>
        <?php foreach($assignmentHistory as $hist): ?>
          <tr>
            <td data-label="Enrolled At"><?php echo $hist['assigned_at']; ?></td>
            <td data-label="Student"><?php echo ($hist['student_first_name'] ?? '').' '.($hist['student_last_name'] ?? '').' ('.($hist['school_id'] ?? 'N/A').')'; ?></td>
            <td data-label="Subject Code"><?php echo $hist['subject_code'] ?? 'N/A'; ?></td>
            <td data-label="Subject"><?php echo $hist['subject_name'] ?? 'N/A'; ?></td>
            <td data-label="Day"><?php echo $hist['day_of_week']; ?></td>
            <td data-label="Time"><?php echo $hist['start_time'].' - '.$hist['end_time']; ?></td>
            <td data-label="Semester"><?php echo $hist['semester']; ?></td>
            <td data-label="Academic Year"><?php echo $hist['academic_year']; ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

<!-- Edit Schedule Modal -->
<div id="editModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeEditModal()">&times;</span>
    <h2>Edit Schedule</h2>
    <form method="POST" id="editForm" style="margin-top:15px;">
      <input type="hidden" name="edit_id" id="editScheduleId">

      <select name="edit_subject_id" id="editSubjectId" required>
        <option value="">-- Select Subject --</option>
        <?php foreach($subjects as $s): ?>
          <option value="<?php echo $s['subject_id']; ?>"><?php echo $s['name']; ?></option>
        <?php endforeach; ?>
      </select>

      <select name="edit_instructor_id" id="editInstructorId" required>
        <option value="">-- Select Instructor --</option>
        <?php foreach($instructors as $i): ?>
          <option value="<?php echo $i['instructor_id']; ?>"><?php echo $i['name']; ?></option>
        <?php endforeach; ?>
      </select>

      <select name="edit_section_id" id="editSectionId" required>
        <option value="">-- Select Section --</option>
        <?php foreach($sections as $s): ?>
          <option value="<?php echo $s['section_id']; ?>"><?php echo $s['name']; ?></option>
        <?php endforeach; ?>
      </select>

      <select name="edit_room_id" id="editRoomId" required>
        <option value="">-- Select Room --</option>
        <?php foreach($rooms as $r): ?>
          <option value="<?php echo $r['room_id']; ?>"><?php echo $r['name']; ?></option>
        <?php endforeach; ?>
      </select>

      <select name="edit_day_of_week" id="editDayOfWeek" required>
        <option value="">-- Select Day --</option>
        <?php foreach($daysOfWeek as $d): ?>
          <option value="<?php echo $d; ?>"><?php echo $d; ?></option>
        <?php endforeach; ?>
      </select>

      <input type="time" name="edit_start_time" id="editStartTime" required>
      <input type="time" name="edit_end_time" id="editEndTime" required>

      <select name="edit_semester" id="editSemester" required>
        <option value="">-- Select Semester --</option>
        <?php foreach($semesters as $s): ?>
          <option value="<?php echo $s; ?>"><?php echo $s; ?></option>
        <?php endforeach; ?>
      </select>

      <input type="text" name="edit_academic_year" id="editAcademicYear" placeholder="Academic Year (e.g., 2025-2026)" required>

      <button type="submit" name="update_schedule" class="btn-primary">Update</button>
      <button type="button" class="btn cancel-btn" onclick="closeEditModal()">Cancel</button>
    </form>
  </div>
</div>


<!-- Delete Schedule Modal -->
<div id="deleteModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeDeleteModal()">&times;</span>
    <h2>Confirm Delete</h2>
    <p>Are you sure you want to delete this schedule?</p>
    <form method="GET" style="margin-top:20px;">
      <input type="hidden" name="delete_schedule" id="deleteScheduleId">
      <button type="submit" class="btn delete-btn">Delete</button>
      <button type="button" class="btn cancel-btn" onclick="closeDeleteModal()">Cancel</button>
    </form>
  </div>
</div>

<!-- Delete Assigned Schedule Modal -->
<div id="deleteAssignmentModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeDeleteAssignmentModal()">&times;</span>
    <h2>Confirm Delete Assignment</h2>
    <p id="deleteAssignmentMessage">Are you sure you want to remove this schedule assignment?</p>
    <form method="GET" style="margin-top:20px;">
      <input type="hidden" name="delete_assignment" id="deleteAssignmentId">
      <button type="submit" class="btn delete-btn">Delete</button>
      <button type="button" class="btn cancel-btn" onclick="closeDeleteAssignmentModal()">Cancel</button>
    </form>
  </div>
</div>


 <div class="modal-overlay" id="courseSuccessModal">
  <div class="success-modal">
    <h3><i class="fa-solid fa-circle-check"></i> <span id="courseSuccessTitle">Success!</span></h3>
    <p id="courseSuccessMessage"></p>
    <button onclick="closeCourseModal()">OK</button>
  </div>
</div>


  <script>
    function showCourseModal(title, message) {
  const modal = document.getElementById('courseSuccessModal');
  const modalTitle = document.getElementById('courseSuccessTitle');
  const modalMessage = document.getElementById('courseSuccessMessage');

  modalTitle.textContent = title;
  modalMessage.textContent = message;

  modal.classList.add('show');

  // auto-hide after 3 seconds
  setTimeout(() => {
    modal.classList.remove('show');
  }, 3000);
}

// Filter schedules based on selected student's semester
document.getElementById('studentSelect').addEventListener('change', function() {
  const studentId = this.value;
  const selectedOption = this.options[this.selectedIndex];
  const studentSemester = selectedOption.getAttribute('data-semester');
  const scheduleItems = document.querySelectorAll('.schedule-checkbox-item');

  // Uncheck all checkboxes and reset styles
  document.querySelectorAll('input[name="schedule_ids[]"]').forEach(cb => {
    cb.checked = false;
    cb.disabled = false;
  });
  scheduleItems.forEach(item => {
    item.style.opacity = '1';
    item.style.background = 'transparent';
  });

  // Map student semester format to schedule semester format
  let mappedSemester = '';
  if (studentSemester) {
    if (studentSemester.includes('1st')) {
      mappedSemester = '1st Semester';
    } else if (studentSemester.includes('2nd')) {
      mappedSemester = '2nd Semester';
    } else if (studentSemester.toLowerCase().includes('summer')) {
      mappedSemester = 'Summer';
    }
  }

  // If no student selected, hide all schedules
  if (!studentId) {
    scheduleItems.forEach(item => {
      item.style.display = 'none';
    });
    return;
  }

  // Fetch already enrolled schedules for the student
  fetch('fetch_student_enrolled_subjects.php?student_id=' + encodeURIComponent(studentId))
    .then(response => response.json())
    .then(data => {
      const enrolledSchedules = data.enrolled_schedules || [];
      const enrolledScheduleSet = new Set(enrolledSchedules.map(s => parseInt(s)));

      // Show all schedules for the semester, disable already assigned ones
      scheduleItems.forEach(item => {
        const scheduleSemester = item.getAttribute('data-semester');
        const checkbox = item.querySelector('input[name="schedule_ids[]"]');
        const scheduleId = parseInt(checkbox.value);

        // Show if semester matches (or show all if no semester set)
        if (!mappedSemester || scheduleSemester === mappedSemester) {
          item.style.display = 'block';
          
          // Check if already enrolled - disable and style it
          if (enrolledScheduleSet.has(scheduleId)) {
            checkbox.checked = true;
            checkbox.disabled = true;
            item.style.opacity = '0.6';
            item.style.background = '#e8f5e9';
            item.title = 'Already enrolled';
          }
        } else {
          item.style.display = 'none';
        }
      });
    })
    .catch(err => {
      console.error('Error fetching enrolled schedules:', err);
      // On error, show all schedules for the semester
      scheduleItems.forEach(item => {
        const scheduleSemester = item.getAttribute('data-semester');
        if (!mappedSemester || scheduleSemester === mappedSemester) {
          item.style.display = 'block';
        } else {
          item.style.display = 'none';
        }
      });
    });
});


function closeCourseModal() {
  const modal = document.getElementById('courseSuccessModal');
  modal.classList.remove('show');

  // remove query params from URL
  const url = new URL(window.location.href);
  url.searchParams.delete('new');
  url.searchParams.delete('assigned');
  url.searchParams.delete('updated');
  url.searchParams.delete('deleted');
  url.searchParams.delete('assignment_deleted');
  window.history.replaceState({}, '', url);
}

// Show modal based on URL params
<?php if(isset($_GET['new'])): ?>
  showCourseModal("Schedule Added", "A new class schedule has been successfully created.");
<?php elseif(isset($_GET['assigned'])): ?>
  <?php $count = intval($_GET['assigned']); ?>
  showCourseModal("Student Enrolled", "<?php echo $count; ?> schedule(s) have been successfully enrolled to the student.");
<?php elseif(isset($_GET['updated'])): ?>
  showCourseModal("Schedule Updated", "The schedule has been successfully updated.");
<?php elseif(isset($_GET['deleted'])): ?>
  showCourseModal("Schedule Deleted", "The schedule has been successfully deleted.");
<?php elseif(isset($_GET['assignment_deleted'])): ?>
  showCourseModal("Enrollment Removed", "The enrollment has been successfully removed.");
<?php endif; ?>

// Close modal when clicking outside
window.addEventListener('click', (e) => {
  const modal = document.getElementById('courseSuccessModal');
  if (e.target === modal) closeCourseModal();
});

  </script>
</body>
</html>


  <script>
    <?php if(isset($_GET['new'])): ?>
      document.addEventListener("DOMContentLoaded", () => {
        document.getElementById("assignSection").scrollIntoView({behavior: "smooth"});
      });
    <?php endif; ?>
  </script>

  <script>
function confirmDelete(scheduleId) {
  document.getElementById('deleteScheduleId').value = scheduleId;
  document.getElementById('deleteModal').style.display = 'block';
}

function confirmDeleteAssignment(assignmentId, subjectName, studentName) {
  document.getElementById('deleteAssignmentId').value = assignmentId;
  document.getElementById('deleteAssignmentMessage').innerHTML = 
    'Are you sure you want to remove <strong>' + subjectName + '</strong> from <strong>' + studentName + '</strong>?';
  document.getElementById('deleteAssignmentModal').style.display = 'block';
}

function openEditModal(schedule) {
  document.getElementById('editScheduleId').value = schedule.schedule_id;
  document.getElementById('editSubjectId').value = schedule.subject_id || '';
  document.getElementById('editInstructorId').value = schedule.instructor_id || '';
  document.getElementById('editSectionId').value = schedule.section_id || '';
  document.getElementById('editRoomId').value = schedule.room_id || '';
  document.getElementById('editDayOfWeek').value = schedule.day_of_week || '';
  document.getElementById('editStartTime').value = schedule.start_time || '';
  document.getElementById('editEndTime').value = schedule.end_time || '';
  document.getElementById('editSemester').value = schedule.semester || '';
  document.getElementById('editAcademicYear').value = schedule.academic_year || '';

  document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() { document.getElementById('editModal').style.display = 'none'; }
function closeDeleteModal() { document.getElementById('deleteModal').style.display = 'none'; }
function closeDeleteAssignmentModal() { document.getElementById('deleteAssignmentModal').style.display = 'none'; }

// Global click listener for modals
window.addEventListener('click', (e) => {
  if(e.target.id === 'editModal') closeEditModal();
  if(e.target.id === 'deleteModal') closeDeleteModal();
  if(e.target.id === 'deleteAssignmentModal') closeDeleteAssignmentModal();
  if(e.target.id === 'courseSuccessModal') closeCourseModal();
});


</script>

</body>
</html>
