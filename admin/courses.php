<?php
session_start();
include 'db.php';
include_once __DIR__ . '/log_activity.php';
$database = new Database();
$conn = $database->getConnection();

// ===== HANDLE ALL REQUESTS BEFORE ANY OUTPUT =====
// Handle Add
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_subject'])) {
    $code        = $_POST['course_code'] ?? '';
    $title       = $_POST['title'] ?? '';
    $units       = $_POST['units'] ?? null;

    $sql = "INSERT INTO subject (SubCode, SubName, Unit) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$code, $title, $units]);
    
    // Log activity
    logActivity('Subject Added', "New subject added - Code: $code, Title: $title, Units: $units", $conn);
    header("Location: courses.php?success=added");
    exit;
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_subject'])) {
    $id          = $_POST['id'];
    $code        = $_POST['course_code'] ?? '';
    $title       = $_POST['title'] ?? '';
    $units       = $_POST['units'] ?? null;

    $sql = "UPDATE subject SET SubCode=?, SubName=?, Unit=? WHERE SubjectID=?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$code, $title, $units, $id]);
    
    // Log activity
    logActivity('Subject Updated', "Subject updated - Code: $code, Title: $title, Units: $units", $conn);
    
    header("Location: courses.php?success=updated");
    exit;
}



// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->prepare("DELETE FROM subject WHERE SubjectID = ?")->execute([$id]);
    header("Location: courses.php");
    exit;
}

// Handle Delete Enrolled Subject (from enrollment table)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_assigned_subject'])) {
    $enrollment_id = $_POST['enrollment_id'] ?? '';
    $school_id = $_POST['delete_assigned_student_id'] ?? '';
    $subject_code = $_POST['delete_assigned_subject'] ?? '';

    if (!empty($enrollment_id)) {
        // Delete from enrollment table using EnrollID
        $deleteSql = "DELETE FROM enrollment WHERE EnrollID = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->execute([$enrollment_id]);
        
        // Log activity
        logActivity('Enrollment Deleted', "Enrollment removed - Student: $school_id, Subject: $subject_code", $conn);
    }

    header("Location: courses.php?success=deleted");
    exit;
}

// ===== NOW SAFE TO INCLUDE HEADER/SIDEBAR =====
?>
<?php include 'header.php'; ?>
<?php include 'sidebar.php'; ?>
<?php

// Fetch all subjects
$subjects = $conn->query("SELECT SubjectID as id, SubCode as course_code, SubName as title, Unit as units, year_level FROM subject ORDER BY SubCode")->fetchAll(PDO::FETCH_ASSOC);

$recordsPerPage = 5;

// Fetch all enrolled subjects per student from enrollment table (via schedule_id -> schedules -> subject)
$assignedSubjects = [];
try {
  $sql = "SELECT 
            s.SchoolID as school_id, 
            s.FirstName as first_name, 
            s.LastName as last_name, 
            s.Course AS student_course,
            s.YearLvl as year_level,
            subj.SubCode as course_code, 
            subj.SubName as title, 
            subj.Unit as units,
            sch.semester,
            sch.academic_year,
            e.EnrollID as enrollment_id,
            e.enrollment_date as assigned_at
          FROM enrollment e
          JOIN student s ON e.StudID = s.StudID
          JOIN schedules sch ON e.schedule_id = sch.schedule_id
          JOIN subject subj ON sch.subject_id = subj.SubjectID
          ORDER BY s.Course, s.YearLvl, s.LastName, s.FirstName, subj.SubCode";
  
  $assignedSubjects = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  // If anything goes wrong, fall back to empty list and avoid crashing the page
  $assignedSubjects = [];
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Subjects - Admin Dashboard</title>
  <link rel="stylesheet" href="css/course.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
 
</head>
<body>

<div class="main-content">
  <div class="header">
    <h1><i class="fa-solid fa-book"></i> Subjects</h1>

    <!-- Button Group - Assign only -->
    <div class="button-group">
      <button id="openAssignModal" class="btn-add assign-btn">
        <i class="fa-solid fa-user-plus"></i> Assign Subjects
      </button>
    </div>
  </div>

  <!-- 🔍 Filters -->
  <div class="filter-container">
    <input type="text" id="searchInput" placeholder="Search by code or title...">
    
    <select id="yearLevelFilter">
      <option value="">All Year Levels</option>
      <option value="1">1st Year</option>
      <option value="2">2nd Year</option>
      <option value="3">3rd Year</option>
      <option value="4">4th Year</option>
    </select>
  </div>

  <!-- 📋 Tables by Year Level -->
  <?php
  // Group subjects by year level
  $subjectsByYear = [];
  foreach ($subjects as $s) {
    $year = $s['year_level'] ?? 'N/A';
    if (!isset($subjectsByYear[$year])) {
      $subjectsByYear[$year] = [];
    }
    $subjectsByYear[$year][] = $s;
  }
  
  // Sort year levels
  ksort($subjectsByYear);
  
  // Helper function for ordinal numbers
  function ordinal($number) {
    if ($number === 'N/A') return $number;
    $ends = array('th','st','nd','rd','th','th','th','th','th','th');
    if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
      return $number . 'th';
    }
    return $number . $ends[$number % 10];
  }
  
  $groupIndex = 0;
  foreach ($subjectsByYear as $yearLevel => $yearSubjects):
    $groupIndex++;
    $groupPageKey = "page_subject_" . $groupIndex;
    $groupCurrentPage = intval($_GET[$groupPageKey] ?? 1);
    
    $totalRecords = count($yearSubjects);
    $totalPages = ceil($totalRecords / $recordsPerPage);
    if ($totalPages < 1) $totalPages = 1;
    $groupCurrentPage = max(1, min($groupCurrentPage, $totalPages));
    
    $startIndex = ($groupCurrentPage - 1) * $recordsPerPage;
    $paginatedSubjects = array_slice($yearSubjects, $startIndex, $recordsPerPage);
  ?>
  <div class="table-container" style="margin-bottom: 30px;">
    <h3 style="margin-bottom: 15px; color: #2c3e50;">
      <i class="fa-solid fa-graduation-cap"></i> 
      <?php 
        if ($yearLevel === 'N/A') {
          echo 'Unassigned Year Level';
        } else {
          echo ordinal($yearLevel) . ' Year Subjects';
        }
      ?>
      <span style="font-size: 0.85em; color: #7f8c8d; margin-left: 10px;">
        (<?php echo $totalRecords; ?> subjects, Page <?php echo $groupCurrentPage; ?>/<?php echo $totalPages; ?>)
      </span>
    </h3>
    <table id="subjectsTable<?php echo $yearLevel; ?>">
      <thead>
        <tr>
          <th>Code</th>
          <th>Title</th>
          <th>Units</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($paginatedSubjects as $s): ?>
        <tr data-year="<?php echo htmlspecialchars($yearLevel); ?>">
          <td><?php echo htmlspecialchars($s['course_code']); ?></td>
          <td><?php echo htmlspecialchars($s['title']); ?></td>
          <td><?php echo htmlspecialchars($s['units']); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    
    <!-- Pagination Controls -->
    <div class="pagination-controls">
      <?php if ($groupCurrentPage > 1): ?>
        <a href="?<?php echo $groupPageKey; ?>=1" 
           class="btn">« First</a>
        <a href="?<?php echo $groupPageKey; ?>=<?php echo $groupCurrentPage - 1; ?>" 
           class="btn">‹ Prev</a>
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
          <a href="?<?php echo $groupPageKey; ?>=<?php echo $p; ?>" 
             class="btn">
            <?php echo $p; ?>
          </a>
        <?php endif; ?>
      <?php endfor; ?>

      <?php if ($endPage < $totalPages): ?>
        <span class="ellipsis">...</span>
      <?php endif; ?>

      <?php if ($groupCurrentPage < $totalPages): ?>
        <a href="?<?php echo $groupPageKey; ?>=<?php echo $groupCurrentPage + 1; ?>" 
           class="btn">Next ›</a>
        <a href="?<?php echo $groupPageKey; ?>=<?php echo $totalPages; ?>" 
           class="btn">Last »</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ==========================================================
     📘 NEXT CHAPTER: Enrolled Subjects per Student
========================================================== -->
<div class="main-content" style="margin-top: 60px;">
  <div class="header">
    <h2><i class="fa-solid fa-list"></i> Assign Subjects per Student</h2>
  </div>

  <!-- DEBUG INFO -->
  <?php if (isset($_GET['debug_assign']) && $_GET['debug_assign'] == '1'): ?>
  <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 5px; font-family: monospace; font-size: 11px; max-height: 500px; overflow-y: auto;">
    <strong>DEBUG INFO:</strong><br>
    <?php
    try {
      // Check student_subjects table
      $checkTableSql = "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE '%student%subject%'";
      $checkStmt = $conn->prepare($checkTableSql);
      $checkStmt->execute();
      $tables = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
      echo "<strong>Found tables:</strong> " . (count($tables) > 0 ? implode(', ', $tables) : "NONE") . "<br><br>";
      
      if (!empty($tables)) {
        $tbl = $tables[0];
        echo "<strong>Using table: {$tbl}</strong><br>";
        
        // Show columns
        $colSql = "SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
        $colStmt = $conn->prepare($colSql);
        $colStmt->execute([$tbl]);
        $columns = $colStmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<strong>Columns:</strong> ";
        foreach ($columns as $col) {
          echo "{$col['COLUMN_NAME']} ({$col['COLUMN_TYPE']}), ";
        }
        echo "<br><br>";
        
        // Show ALL records
        echo "<strong>ALL records in {$tbl}:</strong><br>";
        $allSql = "SELECT * FROM {$tbl}";
        $allStmt = $conn->prepare($allSql);
        $allStmt->execute();
        $allRecords = $allStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($allRecords)) {
          echo "<pre style='background:#f5f5f5; padding:10px;'>" . print_r($allRecords, true) . "</pre>";
        } else {
          echo "❌ <strong>TABLE IS EMPTY!</strong><br>";
        }
      }
    } catch (Exception $e) {
      echo "Error: " . htmlspecialchars($e->getMessage());
    }
    ?>
    <br><a href="?debug_assign=0" style="color: #dc3545;">Hide Debug</a>
  </div>
  <?php elseif (count($assignedSubjects) == 0): ?>
  <div style="background: #f0f0f0; border: 1px solid #ccc; padding: 10px; margin-bottom: 20px; border-radius: 5px; text-align: center;">
    ⚠️ No assigned subjects found. <a href="?debug_assign=1" style="color: #007bff;">Show Debug Info</a> to check database.
  </div>
  <?php endif; ?>

  <!-- 🔍 Filters for Assigned Subjects -->
  <div class="filter-container">
    <input type="text" id="assignedSearchInput" placeholder="Search student or subject...">

    <select id="assignedCourseFilter">
      <option value="">All Courses</option>
      <option value="BSIT">BSIT</option>
      <option value="BSBA">BSBA</option>
      <option value="BEED">BEED</option>
      <option value="BSED">BSED</option>
      <option value="BSHM">BSHM</option>
      <option value="BSCRIM">BSCRIM</option>
    </select>
  </div>

  <!-- 📋 Enrolled Subjects Table -->
  <div class="table-container">
    <table id="assignedSubjectsTable">
      <thead>
        <tr>
          <th>School ID</th>
          <th>Student Name</th>
          <th>Course</th>
          <th>Year Level</th>
          <th>Subject Code</th>
          <th>Title</th>
          <th>Units</th>
          <th>Semester</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($assignedSubjects) > 0): ?>
          <?php foreach ($assignedSubjects as $a): ?>
            <tr>
              <td><?php echo htmlspecialchars($a['school_id'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($a['last_name'] . ', ' . $a['first_name']); ?></td>
              <td><?php echo htmlspecialchars($a['student_course']); ?></td>
              <td><?php echo htmlspecialchars($a['year_level']); ?></td>
              <td><?php echo htmlspecialchars($a['course_code']); ?></td>
              <td><?php echo htmlspecialchars($a['title']); ?></td>
              <td><?php echo htmlspecialchars($a['units']); ?></td>
              <td><?php echo htmlspecialchars($a['semester'] ?? ''); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="8" style="text-align:center; color:#888;">No enrolled subjects yet.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

 

<!-- Edit Modal -->
<div id="editModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h3>Edit Subject</h3>
    <form method="POST">
      <input type="hidden" name="id" id="edit_id">
      <div class="form-group"><label>Course Code</label><input type="text" name="course_code" id="edit_code" required></div>
      <div class="form-group"><label>Subject Title</label><input type="text" name="title" id="edit_title" required></div>
      <div class="form-group"><label>Units</label><input type="number" step="0.1" name="units" id="edit_units" required></div>
     <div class="form-actions">
    <button type="submit" name="update_subject" class="btn-edit">Update</button>
  </div>
</form>
  </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h2>Confirm Delete</h2>
    <p>Are you sure you want to delete <strong id="deleteSubjectName"></strong>?</p>
    <form method="GET" style="margin-top:20px;">
      <input type="hidden" name="delete" id="deleteSubjectId">
      <button type="submit" class="btn delete-btn">Delete</button>
      <button type="button" class="btn cancel-btn">Cancel</button>
    </form>
  </div>
</div>

<!-- Assign Subjects Modal -->
<div id="assignModal" class="modal">
  <div class="modal-content" style="max-width: 600px;">
    <span class="close">&times;</span>
    <h3>Assign Subjects to Student</h3>

    <form method="POST" action="assign_subjects.php">
      <!-- Select Student -->
      <div class="form-group">
        <label>Select Student</label>
        <select id="studentSelect" name="student_id" required>
          <option value="">-- Select Student --</option>
          <?php
            // Ensure we're getting only registered students from the student table
            $students = $conn->query("SELECT StudID as student_id, SchoolID as school_id, FirstName as first_name, LastName as last_name, Course as course, YearLvl as year_level, Semester as semester 
                                     FROM student 
                                     ORDER BY Course, YearLvl, LastName, FirstName")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($students as $st) {
                    echo "<option value='" . htmlspecialchars($st['school_id'] ?? $st['student_id']) . "' 
                      data-course='{$st['course']}' 
                      data-year='{$st['year_level']}'
                      data-semester='{$st['semester']}'>
                      ".htmlspecialchars($st['last_name'].', '.$st['first_name'])." - ".htmlspecialchars($st['school_id'] ?? $st['student_id'])." ({$st['course']} - {$st['year_level']})
                    </option>";
            }
          ?>
        </select>
      </div>

      <!-- Subjects List -->
      <div class="form-group" id="subjectsContainer" style="display:none;">
        <label>Available Subjects for <span id="selectedStudentInfo" style="font-weight:bold;"></span></label>
        <div id="subjectList" class="checkbox-container" style="max-height:230px; overflow-y:auto; border:1px solid #ccc; padding:10px; border-radius:8px;">
          <p style="text-align:center; color:#888;">Select a student first...</p>
        </div>
      </div>

        <div class="form-actions">
    <button type="submit" name="assign_subjects" id="assignButton" class="btn-add" style="display:none;">
      Assign
    </button>
  </div>
</form>
  </div>
</div>

<!-- Delete Enrolled Subject Modal -->
<div id="deleteAssignedModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h2>Confirm Delete</h2>
    <p>Are you sure you want to remove <strong id="deleteSubjectInfo"></strong> from <strong id="deleteStudentInfo"></strong>?</p>
    <form method="POST" style="margin-top:20px;">
      <input type="hidden" name="delete_assigned_subject" value="1">
      <input type="hidden" name="enrollment_id" id="deleteEnrollmentId">
      <input type="hidden" name="delete_assigned_student_id" id="deleteAssignedStudentId">
      <input type="hidden" name="delete_assigned_subject_code" id="deleteAssignedSubjectId">
      <button type="submit" class="btn delete-btn">Delete</button>
      <button type="button" class="btn cancel-btn">Cancel</button>
    </form>
  </div>
</div>

      <!-- ✅ SUCCESS MODAL -->
<div class="modal-overlay" id="courseSuccessModal">
  <div class="success-modal">
    <h3><i class="fa-solid fa-circle-check"></i> <span id="courseSuccessTitle">Success!</span></h3>
    <p id="courseSuccessMessage"></p>
    <button onclick="closeCourseModal()">OK</button>
  </div>
</div>

<script>
// Success modal elements
const courseModal = document.getElementById('courseSuccessModal');
const courseTitle = document.getElementById('courseSuccessTitle');
const courseMessage = document.getElementById('courseSuccessMessage');

function showCourseModal(title, message) {
  courseTitle.textContent = title;
  courseMessage.textContent = message;
  courseModal.classList.add('show');
  courseModal.style.display = 'flex'; // fallback in case CSS show class is overridden
}

function closeCourseModal() {
  courseModal.classList.remove('show');
  courseModal.style.display = 'none';
}

// Initialize all functionality when DOM is ready
document.addEventListener("DOMContentLoaded", () => {
  // Show modal if success query exists
  const params = new URLSearchParams(window.location.search);
  const successType = params.get('success');

    if (successType) {
    let title = "Success!";
    let message = "";

    if (successType === "added") {
      title = "Subject Added!";
      message = "The subject has been successfully added.";
    } else if (successType === "updated") {
      title = "Subject Updated!";
      message = "The subject has been updated successfully.";
    } else if (successType === "assigned") {
        const assignedCount = Number(params.get('count') || 0);
        title = "Subjects Assigned!";
        message = assignedCount > 0
          ? `${assignedCount} subject(s) have been assigned to the student successfully.`
          : "Subjects were already assigned to this student.";
    } else if (successType === "deleted") {
      title = "Subject Removed!";
      message = "The assigned subject has been successfully removed from the student.";
    }

    showCourseModal(title, message);

    // Remove query string after showing modal
    const url = new URL(window.location);
    url.searchParams.delete('success');
    window.history.replaceState({}, document.title, url.toString());
  }

  // ===========================
  // MODAL FUNCTIONALITY
  // ===========================

  // (Removed Add Subject modal trigger)

  // Open Assign Subjects Modal
  document.getElementById('openAssignModal').addEventListener('click', () => {
    document.getElementById('assignModal').style.display = 'block';
  });

  // open Edit Modal
  document.querySelectorAll('.openEditModal').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      document.getElementById('edit_id').value = btn.dataset.id;
      document.getElementById('edit_code').value = btn.dataset.code;
      document.getElementById('edit_title').value = btn.dataset.title;
      document.getElementById('edit_units').value = btn.dataset.units;
      document.getElementById('editModal').style.display = 'block';
    });
  });

  // Delete Modal Logic
  const deleteModal = document.getElementById('deleteModal');
  const deleteBtns = document.querySelectorAll('.openDeleteModal');
  const deleteSubjectName = document.getElementById('deleteSubjectName');
  const deleteSubjectId = document.getElementById('deleteSubjectId');

  deleteBtns.forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      deleteSubjectName.textContent = this.dataset.title;
      deleteSubjectId.value = this.dataset.id;
      deleteModal.style.display = 'block';
    });
  });

  // Delete Enrolled Subject Modal Logic
  const deleteAssignedModal = document.getElementById('deleteAssignedModal');
  const deleteAssignedBtns = document.querySelectorAll('.deleteAssignedSubject');

  deleteAssignedBtns.forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const enrollmentId = this.dataset.enrollmentId;
      const schoolId = this.dataset.schoolId;
      const studentName = this.dataset.studentName;
      const subjectCode = this.dataset.subjectCode;
      const subjectTitle = this.dataset.subjectTitle;

      document.getElementById('deleteSubjectInfo').textContent = subjectCode + ' - ' + subjectTitle;
      document.getElementById('deleteStudentInfo').textContent = studentName;
      document.getElementById('deleteEnrollmentId').value = enrollmentId;
      document.getElementById('deleteAssignedSubjectId').value = subjectCode;
      document.getElementById('deleteAssignedStudentId').value = schoolId;
      
      deleteAssignedModal.style.display = 'block';
    });
  });

  // Close buttons (X icon)
  document.querySelectorAll('.modal .close').forEach(el => {
    el.addEventListener('click', () => {
      el.closest('.modal').style.display = 'none';
    });
  });

  // Cancel buttons
  document.querySelectorAll('.cancel-btn').forEach(el => {
    el.addEventListener('click', (e) => {
      e.preventDefault();
      el.closest('.modal').style.display = 'none';
    });
  });

  // Close modal when clicking outside of it
  window.addEventListener('click', (event) => {
    const editModal = document.getElementById('editModal');
    const deleteModal = document.getElementById('deleteModal');
    const deleteAssignedModal = document.getElementById('deleteAssignedModal');
    const assignModal = document.getElementById('assignModal');

    if (event.target === editModal) {
      editModal.style.display = 'none';
    }
    if (event.target === deleteModal) {
      deleteModal.style.display = 'none';
    }
    if (event.target === deleteAssignedModal) {
      deleteAssignedModal.style.display = 'none';
    }
    if (event.target === assignModal) {
      assignModal.style.display = 'none';
    }
  });

  // ===========================
  // ASSIGN SUBJECTS FUNCTIONALITY
  // ===========================

  // Dynamic subject filtering based on selected student
  document.getElementById('studentSelect').addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const studentId = this.value;
    const studentName = selected.textContent;

    // Update student info display
    document.getElementById('selectedStudentInfo').textContent = studentName;

    if (!studentId) {
      document.getElementById('subjectsContainer').style.display = 'none';
      document.getElementById('assignButton').style.display = 'none';
      return;
    }

    // Fetch enrolled and available subjects for this student
    fetch(`fetch_student_subjects.php?student_id=${encodeURIComponent(studentId)}`)
      .then(response => response.json())
      .then(data => {
        const container = document.getElementById('subjectList');
        const wrapper = document.getElementById('subjectsContainer');
        const assignBtn = document.getElementById('assignButton');

        if (!data.enrolled && !data.available) {
          container.innerHTML = "<p style='color:red; text-align:center;'>" + (data.error || "Error loading subjects") + "</p>";
          wrapper.style.display = 'block';
          assignBtn.style.display = 'none';
          return;
        }

        let html = '';

        // Display already enrolled subjects
        if (data.enrolled && data.enrolled.length > 0) {
          html += '<div style="margin-bottom: 20px;">';
          html += '<h4 style="color: #27ae60; margin-bottom: 10px;"><i class="fa-solid fa-check-circle"></i> Already Enrolled (' + data.enrolled.length + ')</h4>';
          html += '<ul class="subject-list" style="background: #f0f9f4; padding: 10px; border-radius: 5px;">';
          data.enrolled.forEach(subj => {
            html += `
              <li class="subject-item" style="opacity: 0.7;">
                <label>
                  <input type="checkbox" disabled checked style="cursor: not-allowed;">
                  <span class="subject-title">
                    <strong>${subj.course_code}</strong> - ${subj.title}<br>
                    <small>Units: ${subj.units} | Semester: ${subj.semester || 'N/A'} | A.Y.: ${subj.academic_year || 'N/A'}</small>
                  </span>
                </label>
              </li>
            `;
          });
          html += '</ul>';
          html += '</div>';
        }

        // Display available subjects to assign
        if (data.available && data.available.length > 0) {
          html += '<div>';
          html += '<h4 style="color: #2980b9; margin-bottom: 10px;"><i class="fa-solid fa-plus-circle"></i> Available to Assign (' + data.available.length + ')</h4>';
          html += '<ul class="subject-list" style="background: #f0f7ff; padding: 10px; border-radius: 5px;">';
          data.available.forEach(subj => {
            html += `
              <li class="subject-item">
                <label>
                  <input type="checkbox" name="schedule_ids[]" value="${subj.schedule_id}">
                  <span class="subject-title">
                    <strong>${subj.course_code}</strong> - ${subj.title}<br>
                    <small>Units: ${subj.units} | Semester: ${subj.schedule_semester || 'N/A'} | A.Y.: ${subj.academic_year || 'N/A'}</small>
                  </span>
                </label>
              </li>
            `;
          });
          html += '</ul>';
          html += '</div>';
        } else {
          if (!data.enrolled || data.enrolled.length === 0) {
            html += "<p style='color:#888; text-align:center;'>No subjects found for this student's year level.</p>";
          } else {
            html += "<p style='color:#888; text-align:center; margin-top: 10px;'>All subjects for this year level are already enrolled.</p>";
          }
        }

        container.innerHTML = html;
        wrapper.style.display = 'block';
        
        // Only show assign button if there are available subjects to assign
        if (data.available && data.available.length > 0) {
          assignBtn.style.display = 'inline-block';
        } else {
          assignBtn.style.display = 'none';
        }
      })
      .catch(err => {
        console.error('Error fetching subjects:', err);
        document.getElementById('subjectList').innerHTML = "<p style='color:red; text-align:center;'>Error loading subjects.</p>";
      });
  });

  // ===========================
  // SEARCH & FILTER FUNCTIONALITY
  // ===========================

  // Filter Subjects Table (now handling multiple tables and containers)
  const searchInput = document.getElementById('searchInput');
  const yearLevelFilter = document.getElementById('yearLevelFilter');
  const tableContainers = document.querySelectorAll('.table-container');

  function filterTable() {
    const searchValue = searchInput.value.toLowerCase();
    const yearValue = yearLevelFilter.value;

    tableContainers.forEach(container => {
      // Skip the assigned subjects table
      if (container.closest('.main-content').querySelector('h2')) return;
      
      const rows = container.querySelectorAll('tbody tr');
      let hasVisibleRows = false;

      rows.forEach(row => {
        const code = row.cells[0].textContent.toLowerCase();
        const title = row.cells[1].textContent.toLowerCase();
        const yearLevel = row.getAttribute('data-year');

        const matchesSearch = code.includes(searchValue) || title.includes(searchValue);
        const matchesYear = !yearValue || yearLevel === yearValue;

        if (matchesSearch && matchesYear) {
          row.style.display = '';
          hasVisibleRows = true;
        } else {
          row.style.display = 'none';
        }
      });

      // Hide/show entire section if no rows match
      container.style.display = hasVisibleRows ? '' : 'none';
    });
  }

  searchInput.addEventListener('keyup', filterTable);
  yearLevelFilter.addEventListener('change', filterTable);

  // Filter Assigned Subjects Table
  const assignedSearchInput = document.getElementById('assignedSearchInput');
  const assignedCourseFilter = document.getElementById('assignedCourseFilter');
  const assignedRows = document.querySelectorAll('#assignedSubjectsTable tbody tr');

  function filterAssignedTable() {
    const searchValue = assignedSearchInput.value.toLowerCase();
    const courseValue = assignedCourseFilter.value.toLowerCase();

    assignedRows.forEach(row => {
      const studentName = row.cells[1].textContent.toLowerCase();
      const studentCourse = row.cells[2].textContent.toLowerCase();
      const subjectCode = row.cells[3].textContent.toLowerCase();
      const title = row.cells[4].textContent.toLowerCase();

      const matchesSearch =
        studentName.includes(searchValue) ||
        subjectCode.includes(searchValue) ||
        title.includes(searchValue);

      const matchesCourse = !courseValue || studentCourse === courseValue;

      row.style.display = (matchesSearch && matchesCourse) ? '' : 'none';
    });
  }

  assignedSearchInput.addEventListener('keyup', filterAssignedTable);
  assignedCourseFilter.addEventListener('change', filterAssignedTable);

});
</script>


</body>
</html>
