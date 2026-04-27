 <?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db.php';

$database = new Database();
$conn = $database->getConnection();

if (!isset($_SESSION['userId']) || $_SESSION['userRole'] !== 'student') {
  echo json_encode([]);
  exit;
}

// Resolve actual StudID from session userId
// Session might store UserID, but we need StudID for database queries
$sessionUserId = $_SESSION['userId'];
$studentId = $sessionUserId;  // Default fallback

// Try to find the actual StudID from student table
try {
  $resolve = $conn->prepare("SELECT StudID FROM student WHERE StudID = ? OR user_id = ? LIMIT 1");
  $resolve->execute([$sessionUserId, $sessionUserId]);
  $resolved = $resolve->fetch(PDO::FETCH_ASSOC);
  if ($resolved && !empty($resolved['StudID'])) {
    $studentId = $resolved['StudID'];
  }
} catch (Exception $e) {
  error_log('Error resolving StudID: ' . $e->getMessage());
}

// Helper: check if a column exists in a table
function columnExists(PDO $conn, string $table, string $column): bool {
  $stmt = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
  $stmt->execute([$table, $column]);
  return (bool) $stmt->fetchColumn();
}

// Helpers
function tableExists(PDO $conn, string $table): bool {
  $stmt = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
  $stmt->execute([$table]);
  return (bool) $stmt->fetchColumn();
}
function firstExistingTable(PDO $conn, array $candidates): ?string {
  foreach ($candidates as $t) {
    if (tableExists($conn, $t)) return $t;
  }
  return null;
}

$notifications = [];

// 1) Generic notifications table (announcements, system messages)
try {
  if (tableExists($conn, 'notifications')) {
    $stmt = $conn->prepare("SELECT notification_id, user_id, title, message, is_read, created_at, fname, lname, gmail, dateadded FROM notifications WHERE (user_id = ? OR user_id IS NULL) ORDER BY created_at DESC, notification_id DESC LIMIT 50");
    $stmt->execute([$studentId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
      $notifications[] = [
        'type'       => 'notice',
        'id'         => $row['notification_id'] ?? null,
        'title'      => $row['title'] ?? 'Notification',
        'message'    => $row['message'] ?? '',
        'created_at' => $row['created_at'] ?? ($row['dateadded'] ?? date('Y-m-d H:i:s')),
        'is_read'    => (int)($row['is_read'] ?? 0),
      ];
    }
  }
} catch (Exception $e) {
  error_log('Notification table fetch error: ' . $e->getMessage());
}

// 2) Notices table (with per-student read tracking)
try {
  if (tableExists($conn, 'notices')) {
    // Drop and recreate notice_reads to ensure correct structure
    try {
      $conn->exec("DROP TABLE IF EXISTS notice_reads");
    } catch (Exception $e) { /* ignore */ }
    
    $conn->exec("CREATE TABLE IF NOT EXISTS notice_reads (
      id INT AUTO_INCREMENT PRIMARY KEY,
      notice_id VARCHAR(50),
      StudID VARCHAR(50),
      read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY unique_read (notice_id, StudID)
    )");
    
    // Fetch all notices and track reads per student
    $sql = "
      SELECT n.notice_id, n.title, n.content as message, n.created_at,
             IF(nr.id IS NULL, 0, 1) AS is_read
      FROM notices n
      LEFT JOIN notice_reads nr
        ON n.notice_id = nr.notice_id AND nr.StudID = ?
      ORDER BY n.created_at DESC
      LIMIT 50
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$studentId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
      $notifications[] = [
        'type'       => 'notice',
        'id'         => $row['notice_id'] ?? null,
        'title'      => $row['title'] ?? 'Notice',
        'message'    => $row['message'] ?? '',
        'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
        'is_read'    => (int)($row['is_read'] ?? 0),
      ];
    }
  }
} catch (Exception $e) {
  error_log('Notice fetch error: ' . $e->getMessage());
}

// 3) Grade updates (mark unread if not in grade_reads)
try {
  if (tableExists($conn, 'grades')) {
    // Ensure grade_reads exists
    $conn->exec("CREATE TABLE IF NOT EXISTS grade_reads (
      id INT AUTO_INCREMENT PRIMARY KEY,
      grade_id VARCHAR(50),
      StudID VARCHAR(50),
      subject_code VARCHAR(50),
      read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY unique_read (grade_id, StudID, subject_code)
    )");

    $stmtG = $conn->prepare("SELECT grade_id, subject_code, final_grade, year_level, semester, date_recorded FROM grades WHERE StudID = ? ORDER BY date_recorded DESC LIMIT 50");
    $stmtG->execute([$studentId]);
    $grades = $stmtG->fetchAll(PDO::FETCH_ASSOC);

    foreach ($grades as $g) {
      $gradeId = $g['grade_id'] ?? null;
      $subject = $g['subject_code'] ?? 'Subject';

      $checkRead = $conn->prepare("SELECT id FROM grade_reads WHERE StudID = ? AND subject_code = ? LIMIT 1");
      $checkRead->execute([$studentId, $subject]);
      $isRead = $checkRead->fetch() ? 1 : 0;

      $gradeVal = $g['final_grade'] ?? '—';
      $notifications[] = [
        'type'       => 'grade',
        'id'         => $gradeId,
        'title'      => 'New Grade Posted',
        'message'    => $subject . ' — Grade: ' . $gradeVal . ' (' . ($g['year_level'] ?? '') . ', ' . ($g['semester'] ?? '') . ')',
        'created_at' => $g['date_recorded'] ?? date('Y-m-d H:i:s'),
        'is_read'    => $isRead,
      ];
    }
  }
} catch (Exception $e) {
  error_log('Grade fetch error: ' . $e->getMessage());
}

// 4) Tuition/payment updates (only INV receipts)
try {
  if (tableExists($conn, 'payments')) {
    // Create tracking table for payments
    $conn->exec("CREATE TABLE IF NOT EXISTS payment_reads (
      id INT AUTO_INCREMENT PRIMARY KEY,
      payment_id VARCHAR(50),
      student_id VARCHAR(50),
      read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY unique_read (payment_id, student_id)
    )");
    
    $stmtP = $conn->prepare("SELECT payment_id, amount_paid, payment_date, receipt_number, description, payment_type FROM payments WHERE StudID = ? AND receipt_number LIKE 'INV%' ORDER BY payment_id DESC LIMIT 50");
    $stmtP->execute([$studentId]);
    $rows = $stmtP->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
      $paymentId = $row['payment_id'] ?? null;
      
      // Check if marked as read
      $checkRead = $conn->prepare("SELECT id FROM payment_reads WHERE student_id = ? AND payment_id = ? LIMIT 1");
      $checkRead->execute([$studentId, $paymentId]);
      $isRead = $checkRead->fetch() ? 1 : 0;
      
      $notifications[] = [
        'type'       => 'payment',
        'id'         => $paymentId,
        'title'      => 'Payment Recorded',
        'message'    => ($row['description'] ?? 'Payment') . ' — ₱' . number_format((float)($row['amount_paid'] ?? 0), 2) . ' (Receipt: ' . ($row['receipt_number'] ?? 'N/A') . ')',
        'created_at' => $row['payment_date'] ?? date('Y-m-d H:i:s'),
        'is_read'    => $isRead,
      ];
    }
  }
} catch (Exception $e) {
  error_log('Payment fetch error: ' . $e->getMessage());
}

// 4b) Tuition fee setups/updates per student (with read tracking)
try {
  if (tableExists($conn, 'tuition_fees')) {
    // Create tracking table for tuition fees
    $conn->exec("CREATE TABLE IF NOT EXISTS tuition_reads (
      id INT AUTO_INCREMENT PRIMARY KEY,
      fee_id VARCHAR(50),
      student_id VARCHAR(50),
      read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY unique_read (fee_id, student_id)
    )");
    
    $stmtT = $conn->prepare("SELECT fee_id, total_fee, units, rate_unit, created_at FROM tuition_fees WHERE StudID = ? ORDER BY fee_id DESC LIMIT 20");
    $stmtT->execute([$studentId]);
    $rows = $stmtT->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
      $feeId = $row['fee_id'] ?? null;
      
      // Check if marked as read
      $checkRead = $conn->prepare("SELECT id FROM tuition_reads WHERE student_id = ? AND fee_id = ? LIMIT 1");
      $checkRead->execute([$studentId, $feeId]);
      $isRead = $checkRead->fetch() ? 1 : 0;
      
      $notifications[] = [
        'type'       => 'tuition_fee',
        'id'         => $feeId,
        'title'      => 'Tuition Fee Set',
        'message'    => 'Total: ₱' . number_format((float)($row['total_fee'] ?? 0), 2) . ' | ' . ($row['units'] ?? 0) . ' units @ ₱' . number_format((float)($row['rate_unit'] ?? 0), 2) . '/unit',
        'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
        'is_read'    => $isRead,
      ];
    }
  }
} catch (Exception $e) {
  error_log('Tuition setup fetch error: ' . $e->getMessage());
}

// 5) Schedule updates from ENROLLMENT table with read tracking
try {
  if (tableExists($conn, 'enrollment') && tableExists($conn, 'schedules')) {
    // Read tracking table for schedules
    $conn->exec("CREATE TABLE IF NOT EXISTS schedule_reads (
      id INT AUTO_INCREMENT PRIMARY KEY,
      schedule_id VARCHAR(50),
      student_id VARCHAR(50),
      read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY unique_read (schedule_id, student_id)
    )");

    // Column/tables detection
    $hasCreatedAt   = columnExists($conn, 'schedules', 'created_at');
    $hasDayOfWeek   = columnExists($conn, 'schedules', 'day_of_week');
    $hasStartTime   = columnExists($conn, 'schedules', 'start_time');
    $hasEndTime     = columnExists($conn, 'schedules', 'end_time');
    $hasSchedText   = columnExists($conn, 'schedules', 'schedule');
    $hasSubjectRel  = tableExists($conn, 'subject')    && columnExists($conn, 'schedules', 'subject_id');
    $hasInstructor  = tableExists($conn, 'instructor') && columnExists($conn, 'schedules', 'instructor_id');
    $hasRooms       = tableExists($conn, 'rooms')      && columnExists($conn, 'schedules', 'room_id');
    $hasEnrollDate  = columnExists($conn, 'enrollment', 'enrollment_date');

    $select = ['s.schedule_id', 'e.EnrollID'];
    if ($hasEnrollDate) $select[] = 'e.enrollment_date';
    if ($hasCreatedAt)   $select[] = 's.created_at';
    if ($hasDayOfWeek)   $select[] = 's.day_of_week';
    if ($hasStartTime)   $select[] = 's.start_time';
    if ($hasEndTime)     $select[] = 's.end_time';
    if ($hasSchedText)   $select[] = 's.schedule';
    if ($hasSubjectRel)  $select[] = 'sub.SubCode AS code, sub.SubName AS subject, sub.Unit AS unit';
    if ($hasRooms)       $select[] = 'r.room_number AS room';
    if ($hasInstructor)  $select[] = "CONCAT(i.FName, ' ', i.LName) AS instructor";

    $sql  = 'SELECT ' . implode(', ', $select) . ' FROM enrollment e JOIN schedules s ON e.schedule_id = s.schedule_id';
    if ($hasSubjectRel)  $sql .= ' LEFT JOIN subject sub ON s.subject_id = sub.SubjectID';
    if ($hasInstructor)  $sql .= ' LEFT JOIN instructor i ON s.instructor_id = i.InsID';
    if ($hasRooms)       $sql .= ' LEFT JOIN rooms r ON s.room_id = r.room_id';
    $sql .= ' WHERE e.StudID = ?';
    $orderBy = $hasEnrollDate ? ' ORDER BY e.enrollment_date DESC, s.schedule_id DESC' : ' ORDER BY s.schedule_id DESC';
    $sql .= $orderBy . ' LIMIT 20';

    $stmtS = $conn->prepare($sql);
    $stmtS->execute([$studentId]);
    $rows = $stmtS->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
      // Determine schedule string
      $schedStr = '';
      if ($hasDayOfWeek && $hasStartTime && $hasEndTime && isset($row['day_of_week'], $row['start_time'], $row['end_time'])) {
        // Format times to 12-hour with AM/PM
        $start = date('h:i A', strtotime($row['start_time']));
        $end   = date('h:i A', strtotime($row['end_time']));
        $schedStr = $row['day_of_week'] . ' ' . $start . '-' . $end;
      } elseif ($hasSchedText && isset($row['schedule'])) {
        $schedStr = $row['schedule'];
      }

      // Check read state
      $chk = $conn->prepare('SELECT id FROM schedule_reads WHERE student_id = ? AND schedule_id = ? LIMIT 1');
      $chk->execute([$studentId, $row['schedule_id']]);
      $isRead = $chk->fetch() ? 1 : 0;

      // Use enrollment_date as the notification timestamp
      $notifDate = $row['enrollment_date'] ?? ($row['created_at'] ?? date('Y-m-d H:i:s'));

      $notifications[] = [
        'type'       => 'schedule',
        'id'         => $row['schedule_id'] ?? null,
        'title'      => 'Class Schedule Assigned',
        'message'    => ($row['subject'] ?? ($row['code'] ?? 'Subject')) . ' — ' . $schedStr . (isset($row['room']) ? (' @ ' . $row['room']) : ''),
        'created_at' => $notifDate,
        'is_read'    => $isRead,
      ];
    }
  }
} catch (Exception $e) {
  error_log('Schedule fetch error: ' . $e->getMessage());
}

// 6) Enrolled Subjects from ENROLLMENT table (with read tracking)
try {
  if (tableExists($conn, 'enrollment') && tableExists($conn, 'schedules') && tableExists($conn, 'subject')) {
    // Create subject_reads tracking table
    $conn->exec("CREATE TABLE IF NOT EXISTS subject_reads (
      id INT AUTO_INCREMENT PRIMARY KEY,
      subject_id VARCHAR(50),
      student_id VARCHAR(50),
      read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY unique_read (subject_id, student_id)
    )");
    
    $hasEnrollDate = columnExists($conn, 'enrollment', 'enrollment_date');
    
    $sql = "SELECT DISTINCT 
              sub.SubjectID, 
              sub.SubCode, 
              sub.SubName, 
              sub.Unit,
              s.semester,
              s.academic_year" . 
              ($hasEnrollDate ? ", e.enrollment_date" : "") . "
            FROM enrollment e 
            JOIN schedules s ON e.schedule_id = s.schedule_id
            JOIN subject sub ON s.subject_id = sub.SubjectID
            WHERE e.StudID = ?
            ORDER BY " . ($hasEnrollDate ? "e.enrollment_date DESC, " : "") . "sub.SubjectID DESC
            LIMIT 20";
    
    $stmtC = $conn->prepare($sql);
    $stmtC->execute([$studentId]);
    $rows = $stmtC->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($rows as $row) {
      $subjectId = $row['SubjectID'] ?? null;
      
      // Check if marked as read
      $checkRead = $conn->prepare("SELECT id FROM subject_reads WHERE student_id = ? AND subject_id = ? LIMIT 1");
      $checkRead->execute([$studentId, $subjectId]);
      $isRead = $checkRead->fetch() ? 1 : 0;
      
      $semInfo = '';
      if (!empty($row['semester'])) {
        $semInfo = ' (' . $row['semester'] . ($row['academic_year'] ? ', ' . $row['academic_year'] : '') . ')';
      }
      
      $notifDate = $row['enrollment_date'] ?? date('Y-m-d H:i:s');
      
      $notifications[] = [
        'type'       => 'course',
        'id'         => $subjectId,
        'title'      => 'Subject Enrolled',
        'message'    => ($row['SubCode'] ?? 'Subject') . ' — ' . ($row['SubName'] ?? '') . $semInfo,
        'created_at' => $notifDate,
        'is_read'    => $isRead,
      ];
    }
  }
} catch (Exception $e) {
  error_log('Enrollment course fetch error: ' . $e->getMessage());
}

// Sort by newest first
usort($notifications, function($a, $b) {
  return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
});

echo json_encode($notifications);
?>