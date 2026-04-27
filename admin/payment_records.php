<?php
// Start session only if not already active
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

include_once __DIR__ . '/db.php';
include_once __DIR__ . '/log_activity.php';
$database = new Database();
$conn = $database->getConnection();

/* ======================================
   💰 ADD TUITION FEE (Per Student)
====================================== */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_tuition'])) {
    $student_id = $_POST['student_id'];
    $units = $_POST['units'] ?? 0;
    $rate_unit = $_POST['rate_unit'] ?? 0;
    $total_fee = $_POST['total_fee'] ?? 0;
    $notes = $_POST['notes'] ?? '';

    try {
        // Begin transaction to ensure tuition and billing are created together
        $conn->beginTransaction();

        // Insert/Update tuition fee
        $sql = "INSERT INTO tuition_fees (StudID, units, rate_unit, total_fee, notes)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE units=?, rate_unit=?, total_fee=?, notes=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$student_id, $units, $rate_unit, $total_fee, $notes, $units, $rate_unit, $total_fee, $notes]);
        
        // Create/Update billing record for the student
        // Calculate total paid from actual payment records only
        $paidQuery = $conn->prepare("SELECT SUM(amount_paid) as total_paid FROM payments WHERE StudID = ? AND payment_status = 'Paid'");
        $paidQuery->execute([$student_id]);
        $paidResult = $paidQuery->fetch(PDO::FETCH_ASSOC);
        $actual_paid = (float)($paidResult['total_paid'] ?? 0);
        
        $bSel = $conn->prepare("SELECT billing_id FROM billing WHERE StudID = ? ORDER BY billing_id DESC LIMIT 1");
        $bSel->execute([$student_id]);
        $billing = $bSel->fetch(PDO::FETCH_ASSOC);
        
        $new_balance = max($total_fee - $actual_paid, 0);
        $new_status = $new_balance <= 0 ? 'Paid' : ($actual_paid > 0 ? 'Partial' : 'Unpaid');
        
        if ($billing) {
            // Update existing billing - set paid_amount from actual payments only
            $bUpd = $conn->prepare("UPDATE billing SET total_amount = ?, balance = ?, status = ?, paid_amount = ? WHERE billing_id = ?");
            $bUpd->execute([$total_fee, $new_balance, $new_status, $actual_paid, $billing['billing_id']]);
        } else {
            // Create new billing - unpaid with full balance and zero paid amount (no payments yet)
            $bIns = $conn->prepare("INSERT INTO billing (StudID, total_amount, balance, status, total_fees, paid_amount) VALUES (?, ?, ?, 'Unpaid', ?, 0)");
            $bIns->execute([$student_id, $total_fee, $total_fee, $total_fee]);
        }
        
        $conn->commit();
        
        // Log activity
        logActivity('Tuition Fee Added', "Tuition fee added for Student ID: $student_id - Total Fee: ₱$total_fee (Units: $units)", $conn);
        
        header("Location: payment_records.php?tuition_added=1");
        exit;
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo "Error saving tuition fee: " . $e->getMessage();
    }
}

/* ======================================
   ✏️ UPDATE TUITION FEE (Per Student)
====================================== */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_tuition'])) {
    $id = $_POST['tuition_id'];
    $student_id = $_POST['student_id'];
    $units = $_POST['units'] ?? 0;
    $rate_unit = $_POST['rate_unit'] ?? 0;
    $total_fee = $_POST['total_fee'] ?? 0;
    $notes = $_POST['notes'] ?? '';

    try {
        $conn->beginTransaction();
        
        // Update tuition fee
        $sql = "UPDATE tuition_fees SET StudID=?, units=?, rate_unit=?, total_fee=?, notes=? WHERE fee_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$student_id, $units, $rate_unit, $total_fee, $notes, $id]);
        
        // Update billing to reflect new tuition amount
        $bSel = $conn->prepare("SELECT billing_id, paid_amount FROM billing WHERE StudID = ? ORDER BY billing_id DESC LIMIT 1");
        $bSel->execute([$student_id]);
        $billing = $bSel->fetch(PDO::FETCH_ASSOC);
        
        if ($billing) {
            // Calculate new balance based on what has already been paid
            $paid_amount = (float)$billing['paid_amount'];
            $new_balance = max($total_fee - $paid_amount, 0);
            $new_status = $new_balance <= 0 ? 'Paid' : ($paid_amount > 0 ? 'Partial' : 'Unpaid');
            
            $bUpd = $conn->prepare("UPDATE billing SET total_amount = ?, balance = ?, status = ? WHERE billing_id = ?");
            $bUpd->execute([$total_fee, $new_balance, $new_status, $billing['billing_id']]);
        } else {
            // Create new billing if none exists - unpaid with full balance
            $bIns = $conn->prepare("INSERT INTO billing (StudID, total_amount, balance, status, total_fees, paid_amount) VALUES (?, ?, ?, 'Unpaid', ?, 0)");
            $bIns->execute([$student_id, $total_fee, $total_fee, $total_fee]);
        }
        
        $conn->commit();
        
        // Log activity
        logActivity('Tuition Fee Updated', "Tuition fee updated - ID: $id, Student ID: $student_id - Total Fee: ₱$total_fee (Units: $units)", $conn);
        
        header("Location: payment_records.php?tuition_updated=1");
        exit;
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo "Error updating tuition fee: " . $e->getMessage();
    }
}

/* ======================================
   ✏️ UPDATE PAYMENT RECORD
====================================== */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_payment'])) {
  $id = $_POST['payment_id'];
    $amount_due = $_POST['amount_due'];
    $amount_paid = $_POST['amount_paid'];
    $status = $_POST['status'];

  $sql = "UPDATE payments SET amount_due=?, amount_paid=?, status=? WHERE payment_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$amount_due, $amount_paid, $status, $id]);
    
    // Log activity
    logActivity('Payment Updated', "Payment record updated - ID: $id, Amount Paid: ₱$amount_paid, Status: $status", $conn);
    
    header("Location: payment_records.php?payment_updated=1");
    exit;
}

/* ======================================
   🗑️ DELETE HANDLERS
====================================== */

if (isset($_GET['delete_tuition'])) {
  $id = $_GET['delete_tuition'];
  
  // Get tuition info before deleting for logging
  $getTuitionSql = "SELECT StudID, total_fee FROM tuition_fees WHERE fee_id=?";
  $getTuitionStmt = $conn->prepare($getTuitionSql);
  $getTuitionStmt->execute([$id]);
  $tuitionData = $getTuitionStmt->fetch(PDO::FETCH_ASSOC);
  
  $conn->prepare("DELETE FROM tuition_fees WHERE fee_id=?")->execute([$id]);
  
  // Log activity
  if ($tuitionData) {
    logActivity('Tuition Fee Deleted', "Tuition fee deleted - ID: $id, Student ID: {$tuitionData['StudID']}, Amount: ₱{$tuitionData['total_fee']}", $conn);
  } else {
    logActivity('Tuition Fee Deleted', "Tuition fee deleted - ID: $id", $conn);
  }
  
  header("Location: payment_records.php?tuition_deleted=1");
  exit;
}
if (isset($_GET['delete_student_payment'])) {
  $id = $_GET['delete_student_payment'];
  
  try {
    // Get tuition info before deleting for logging
    $getTuitionSql = "SELECT StudID, total_amount FROM tuition WHERE tuition_id=?";
    $getTuitionStmt = $conn->prepare($getTuitionSql);
    $getTuitionStmt->execute([$id]);
    $tuitionData = $getTuitionStmt->fetch(PDO::FETCH_ASSOC);
    
    // Delete the student payment record
    $conn->prepare("DELETE FROM tuition WHERE tuition_id=?")->execute([$id]);
    
    // Log activity
    if ($tuitionData) {
      logActivity('Student Payment Record Deleted', "Student payment record deleted - ID: $id, Student ID: {$tuitionData['StudID']}, Amount: ₱{$tuitionData['total_amount']}", $conn);
    } else {
      logActivity('Student Payment Record Deleted', "Student payment record deleted - ID: $id", $conn);
    }
    
    header("Location: payment_records.php?student_payment_deleted=1");
    exit;
  } catch (Exception $e) {
    echo "Error deleting student payment record: " . htmlspecialchars($e->getMessage());
  }
}

if (isset($_GET['delete_payment'])) {
    $id = $_GET['delete_payment'];

    try {
      // Wrap in transaction so tuition, billing, and payments stay consistent
      $conn->beginTransaction();

      // Get payment info before deleting for logging and reversal
      $getPaymentSql = "SELECT payment_id, StudID, amount_paid, payment_type, receipt_number FROM payments WHERE payment_id=? FOR UPDATE";
      $getPaymentStmt = $conn->prepare($getPaymentSql);
      $getPaymentStmt->execute([$id]);
      $paymentData = $getPaymentStmt->fetch(PDO::FETCH_ASSOC);

      if ($paymentData) {
        $studId = (int)$paymentData['StudID'];
        $amountPaid = (float)$paymentData['amount_paid'];

        // Revert tuition: add back the deleted payment amount to the latest tuition record
        $tSel = $conn->prepare("SELECT fee_id, total_fee FROM tuition_fees WHERE StudID = ? ORDER BY fee_id DESC LIMIT 1 FOR UPDATE");
        $tSel->execute([$studId]);
        $tuition = $tSel->fetch(PDO::FETCH_ASSOC);
        if ($tuition) {
          $revertedTotal = (float)$tuition['total_fee'] + $amountPaid;
          $tUpd = $conn->prepare("UPDATE tuition_fees SET total_fee = ? WHERE fee_id = ?");
          $tUpd->execute([$revertedTotal, $tuition['fee_id']]);
        }

        // Revert billing snapshot: decrease paid_amount and recompute balance/status
        $bSel = $conn->prepare("SELECT billing_id, total_amount, balance, paid_amount, status FROM billing WHERE StudID = ? ORDER BY billing_id DESC LIMIT 1 FOR UPDATE");
        $bSel->execute([$studId]);
        $billing = $bSel->fetch(PDO::FETCH_ASSOC);
        if ($billing) {
          $newPaidAmount = max(((float)$billing['paid_amount']) - $amountPaid, 0);
          // Recompute balance primarily from total_amount if available; otherwise add back to balance
          if (isset($billing['total_amount'])) {
            $recalcBalance = max(((float)$billing['total_amount']) - $newPaidAmount, 0);
          } else {
            $recalcBalance = max(((float)$billing['balance']) + $amountPaid, 0);
          }
          $newStatus = $recalcBalance <= 0 ? 'Paid' : ($newPaidAmount > 0 ? 'Partial' : 'Unpaid');
          $bUpd = $conn->prepare("UPDATE billing SET paid_amount = ?, balance = ?, status = ? WHERE billing_id = ?");
          $bUpd->execute([$newPaidAmount, $recalcBalance, $newStatus, $billing['billing_id']]);
        }
      }

      // Finally, delete the payment record itself
      $conn->prepare("DELETE FROM payments WHERE payment_id=?")->execute([$id]);

      $conn->commit();

      // Log activity
      if ($paymentData) {
        logActivity('Payment Deleted', "Payment deleted and balances reverted - ID: $id, Student ID: {$paymentData['StudID']}, Amount: ₱{$paymentData['amount_paid']}, Type: {$paymentData['payment_type']}, Receipt: {$paymentData['receipt_number']}", $conn);
      } else {
        logActivity('Payment Deleted', "Payment record deleted - ID: $id", $conn);
      }

      header("Location: payment_records.php?payment_deleted=1");
      exit;
    } catch (Exception $e) {
      if ($conn->inTransaction()) {
        $conn->rollBack();
      }
      echo "Error deleting payment: " . htmlspecialchars($e->getMessage());
    }
}

/* ======================================
   📊 FETCH RECORDS
====================================== */
?>
<?php include 'header.php'; ?>
<?php include 'sidebar.php'; ?>
<?php
$sql = "SELECT t.*, 
        s.FirstName as first_name, 
        s.LastName as last_name,
        s.Course as course,
        s.YearLvl as year_level,
        s.Semester as semester,
        s.SchoolID as school_id,
        t.StudID as student_study_id,
        GROUP_CONCAT(DISTINCT p.description ORDER BY p.payment_date SEPARATOR ' | ') as payment_descriptions,
        GROUP_CONCAT(DISTINCT p.payment_type ORDER BY p.payment_date SEPARATOR ' | ') as payment_types,
        COUNT(p.payment_id) as payment_count,
        (SELECT total_fee FROM tuition_fees WHERE StudID = t.StudID ORDER BY fee_id DESC LIMIT 1) as total_payable
        FROM tuition t 
        JOIN student s ON s.StudID = t.StudID
        LEFT JOIN payments p ON p.StudID = t.StudID
        GROUP BY t.tuition_id
        ORDER BY t.generated_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$t_stmt = $conn->prepare("SELECT t.*, 
        CONCAT(s.FirstName, ' ', s.LastName) AS student_name,
        s.Course as course,
        s.YearLvl as year_level,
        s.Semester as semester,
        s.SchoolID as school_id
        FROM tuition_fees t 
        LEFT JOIN student s ON t.StudID = s.StudID 
        ORDER BY s.FirstName, s.LastName");
$t_stmt->execute();
$tuition_fees = $t_stmt->fetchAll(PDO::FETCH_ASSOC);

$recordsPerPage = 5;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment Records - Admin Dashboard</title>
  <link rel="stylesheet" href="css/payments.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
<div class="main-content">
  <div class="header">
    <h2><i class="fa-solid fa-receipt"></i> Payment & Tuition Records</h2>
    <div class="btn-group">
      <button id="openTuitionModal" class="btn-add"><i class="fas fa-coins"></i> Set Tuition Fee</button>
    </div>
  </div>

  <!-- 🔍 FILTER BAR for Tuition Fees -->
  <div class="filter-bar">
    <input type="text" id="searchTuition" placeholder="Search student name or ID...">
  </div>

  <!-- Tuition Fee Table -->
  <div class="table-container">
    <h3>Tuition Fee Setup</h3>

    <?php
    // Group tuition fees by year level
    $tuitionByYear = [];
    foreach ($tuition_fees as $t) {
      $yearLevel = $t['year_level'] ?? 'N/A';
      if (!isset($tuitionByYear[$yearLevel])) {
        $tuitionByYear[$yearLevel] = [];
      }
      $tuitionByYear[$yearLevel][] = $t;
    }
    
    // Sort year levels
    uksort($tuitionByYear, function($a, $b) {
      $aNum = intval($a);
      $bNum = intval($b);
      return $aNum - $bNum;
    });
    
    // Helper function for ordinal numbers
    function ordinal_payment($number) {
      if ($number === 'N/A') return $number;
      $number = intval($number);
      $ends = array('th','st','nd','rd','th','th','th','th','th','th');
      if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
        return $number . 'th';
      }
      return $number . $ends[$number % 10];
    }
    
    // Display each year level group
    $groupIndex = 0;
    foreach ($tuitionByYear as $yearLevel => $yearTuitions):
      $groupIndex++;
      $groupPageKey = "page_tuition_" . $groupIndex;
      $groupCurrentPage = intval($_GET[$groupPageKey] ?? 1);
      
      $totalRecords = count($yearTuitions);
      $totalPages = ceil($totalRecords / $recordsPerPage);
      if ($totalPages < 1) $totalPages = 1;
      $groupCurrentPage = max(1, min($groupCurrentPage, $totalPages));
      
      $startIndex = ($groupCurrentPage - 1) * $recordsPerPage;
      $paginatedTuitions = array_slice($yearTuitions, $startIndex, $recordsPerPage);
    ?>
    
    <div style="margin-bottom: 40px;">
      <h4 style="margin-bottom: 15px; color: #2c3e50; border-left: 4px solid #3498db; padding-left: 10px;">
        <i class="fa-solid fa-graduation-cap"></i> 
        <?php echo ordinal_payment($yearLevel); ?> Year Tuition Fees
        <span style="font-size: 0.85em; color: #7f8c8d; margin-left: 10px;">
          (<?php echo $totalRecords; ?> records, Page <?php echo $groupCurrentPage; ?>/<?php echo $totalPages; ?>)
        </span>
      </h4>
      
      <table class="tuition-table">
        <thead>
          <tr>
            <th>School ID</th>
            <th>Student Name</th>
            <th>Units</th>
            <th>Rate/Unit</th>
            <th>Total Fee</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($paginatedTuitions as $t): ?>
            <tr>
              <td><?= htmlspecialchars($t['school_id'] ?? '-'); ?></td>
              <td><?= htmlspecialchars($t['student_name'] ?? '-'); ?></td>
              <td><?= $t['units'] ?? 0; ?></td>
              <td>₱<?= number_format($t['rate_unit'] ?? 0, 2); ?></td>
              <td><strong>₱<?= number_format($t['total_fee'] ?? 0, 2); ?></strong></td>
              <td>
                <button class="action-btn edit edit-tuition-btn"
                  data-id="<?= htmlspecialchars($t['fee_id'] ?? ''); ?>"
                  data-student-id="<?= htmlspecialchars($t['StudID'] ?? ''); ?>"
                  data-units="<?= htmlspecialchars($t['units'] ?? 0); ?>"
                  data-rate-unit="<?= htmlspecialchars($t['rate_unit'] ?? 0); ?>"
                  data-total-fee="<?= htmlspecialchars($t['total_fee'] ?? 0); ?>"
                  data-notes="<?= htmlspecialchars($t['notes'] ?? ''); ?>">
                  <i class="fas fa-edit"></i>
                </button>
                <a href="#"
                  class="action-btn delete openDeleteModal"
                  data-type="tuition"
                  data-id="<?= htmlspecialchars($t['fee_id'] ?? ''); ?>"
                  data-name="<?= htmlspecialchars($t['student_name'] ?? $t['StudID'] ?? ''); ?>">
                  <i class="fa-solid fa-trash"></i>
                </a>
              </td>
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
            <span class="current-page"><?php echo $p; ?></span>
          <?php else: ?>
            <a href="?<?php echo $groupPageKey; ?>=<?php echo $p; ?>" class="btn"><?php echo $p; ?></a>
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
  </div>

  <!-- 🔍 FILTER BAR for Payments -->
  <div class="filter-bar">
    <input type="text" id="searchPayment" placeholder="Search student or receipt no...">

    <select id="filterCoursePayment">
      <option value="">All Courses</option>
      <option value="BSIT">BSIT</option>
      <option value="BSCS">BSCS</option>
      <option value="BSBA">BSBA</option>
      <option value="BEED">BEED</option>
      <option value="BSED">BSED</option>
      <option value="BSHM">BSHM</option>
      <option value="BSCRIM">BSCRIM</option>
    </select>

    <select id="filterYearPayment">
      <option value="">All Year Levels</option>
      <option value="1st Year">1st Year</option>
      <option value="2nd Year">2nd Year</option>
      <option value="3rd Year">3rd Year</option>
      <option value="4th Year">4th Year</option>
    </select>

    <select id="filterSemPayment">
      <option value="">All Semesters</option>
      <option value="1st Semester">1st Semester</option>
      <option value="2nd Semester">2nd Semester</option>
      <option value="Summer">Summer</option>
    </select>
  </div>

  

  <!-- Payment Table -->
  <div class="table-container">
    <h3>Student Payment Records</h3>
    
    <?php
    // Group tuition by year level and semester
    $paymentsByGroup = [];
    foreach ($payments as $p) {
      $yearLevel = $p['year_level'] ?? 'N/A';
      $semester = $p['semester'] ?? 'N/A';
      $key = $semester . '|' . $yearLevel;
      
      if (!isset($paymentsByGroup[$key])) {
        $paymentsByGroup[$key] = [
          'semester' => $semester,
          'year_level' => $yearLevel,
          'payments' => []
        ];
      }
      $paymentsByGroup[$key]['payments'][] = $p;
    }
    
    // Display each group with pagination
    $paymentGroupIndex = 0;
    foreach ($paymentsByGroup as $groupKey => $groupData):
      $paymentGroupIndex++;
      $paymentGroupPageKey = "page_payment_" . $paymentGroupIndex;
      $paymentGroupCurrentPage = intval($_GET[$paymentGroupPageKey] ?? 1);
      
      $paymentTotalRecords = count($groupData['payments']);
      $paymentTotalPages = ceil($paymentTotalRecords / $recordsPerPage);
      if ($paymentTotalPages < 1) $paymentTotalPages = 1;
      $paymentGroupCurrentPage = max(1, min($paymentGroupCurrentPage, $paymentTotalPages));
      
      $paymentStartIndex = ($paymentGroupCurrentPage - 1) * $recordsPerPage;
      $paginatedPayments = array_slice($groupData['payments'], $paymentStartIndex, $recordsPerPage);
    ?>
    
    <div style="margin-bottom: 40px;">
      <h4 style="margin-bottom: 15px; color: #2c3e50; border-left: 4px solid #3498db; padding-left: 10px;">
        <i class="fa-solid fa-receipt"></i> 
        <?php echo htmlspecialchars($groupData['semester']); ?> - 
        <?php echo ordinal_payment($groupData['year_level']); ?> Year
        <span style="font-size: 0.85em; color: #7f8c8d; margin-left: 10px;">
          (<?php echo $paymentTotalRecords; ?> payments, Page <?php echo $paymentGroupCurrentPage; ?>/<?php echo $paymentTotalPages; ?>)
        </span>
      </h4>
      
      <table class="payment-table">
        <thead>
          <tr>
            <th>School ID</th>
            <th>Student Name</th>
            <th>Course</th>
            <th>Description / Exam Paid</th>
            <th>Total Units</th>
            <th>Total Amount</th>
            <th>Date Generated</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($paginatedPayments as $p): ?>
            <?php 
              // Extract and display payment descriptions with color-coded badges
              $descriptions = [];
              $payment_descriptions = $p['payment_descriptions'] ?? '';
              $total_amount = (float)($p['total_amount'] ?? 0);
              $total_payable = (float)($p['total_payable'] ?? 0);
              
              // Check if fully paid - if yes, show all exam badges
              $is_fully_paid = ($total_payable > 0 && $total_amount >= $total_payable);
              
              if ($is_fully_paid) {
                // Show all exam badges when fully paid
                $descriptions[] = '<span style="background: #667eea; color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 11px; margin: 2px; display: inline-block;">Prelims</span>';
                $descriptions[] = '<span style="background: #f5576c; color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 11px; margin: 2px; display: inline-block;">Midterm</span>';
                $descriptions[] = '<span style="background: #00f2fe; color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 11px; margin: 2px; display: inline-block;">Semis</span>';
                $descriptions[] = '<span style="background: #38f9d7; color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 11px; margin: 2px; display: inline-block;">Finals</span>';
              } elseif (!empty($payment_descriptions)) {
                // Otherwise, check payment descriptions for specific exams
                if (stripos($payment_descriptions, 'Prelim') !== false) {
                  $descriptions[] = '<span style="background: #667eea; color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 11px; margin: 2px; display: inline-block;">Prelims</span>';
                }
                if (stripos($payment_descriptions, 'Midterm') !== false) {
                  $descriptions[] = '<span style="background: #f5576c; color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 11px; margin: 2px; display: inline-block;">Midterm</span>';
                }
                if (stripos($payment_descriptions, 'Semi') !== false || stripos($payment_descriptions, 'Semis') !== false) {
                  $descriptions[] = '<span style="background: #00f2fe; color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 11px; margin: 2px; display: inline-block;">Semis</span>';
                }
                if (stripos($payment_descriptions, 'Final') !== false) {
                  $descriptions[] = '<span style="background: #38f9d7; color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 11px; margin: 2px; display: inline-block;">Finals</span>';
                }
              }
              
              $display_desc = !empty($descriptions) ? implode(' ', $descriptions) : '<span style="color: #999;">No payments yet</span>';
            ?>
            <tr>
              <td><?= htmlspecialchars($p['school_id'] ?? '-'); ?></td>
              <td><?= $p['first_name'] . ' ' . $p['last_name']; ?></td>
              <td><?= $p['course']; ?></td>
              <td><?= $display_desc; ?></td>
              <td><?= $p['total_units'] ?? 0; ?></td>
              <td><strong>₱<?= number_format($p['total_amount'] ?? 0, 2); ?></strong></td>
              <td><?= $p['generated_at'] ?? '-'; ?></td>
              <td>
                <a href="#" 
                  class="action-btn delete openDeleteModal"
                  data-type="student_payment"
                  data-id="<?= $p['tuition_id']; ?>"
                  data-name="<?= $p['first_name'] . ' ' . $p['last_name']; ?>">
                  <i class="fa-solid fa-trash"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      
      <!-- Pagination Controls -->
      <div class="pagination-controls">
        <?php if ($paymentGroupCurrentPage > 1): ?>
          <a href="?<?php echo $paymentGroupPageKey; ?>=1" class="btn">« First</a>
          <a href="?<?php echo $paymentGroupPageKey; ?>=<?php echo $paymentGroupCurrentPage - 1; ?>" class="btn">‹ Prev</a>
        <?php endif; ?>

        <?php 
        $paymentStartPage = max(1, $paymentGroupCurrentPage - 2);
        $paymentEndPage = min($paymentTotalPages, $paymentGroupCurrentPage + 2);
        
        if ($paymentStartPage > 1): ?>
          <span class="ellipsis">...</span>
        <?php endif; ?>

        <?php for ($p = $paymentStartPage; $p <= $paymentEndPage; $p++): ?>
          <?php if ($p == $paymentGroupCurrentPage): ?>
            <span class="current-page"><?php echo $p; ?></span>
          <?php else: ?>
            <a href="?<?php echo $paymentGroupPageKey; ?>=<?php echo $p; ?>" class="btn"><?php echo $p; ?></a>
          <?php endif; ?>
        <?php endfor; ?>

        <?php if ($paymentEndPage < $paymentTotalPages): ?>
          <span class="ellipsis">...</span>
        <?php endif; ?>

        <?php if ($paymentGroupCurrentPage < $paymentTotalPages): ?>
          <a href="?<?php echo $paymentGroupPageKey; ?>=<?php echo $paymentGroupCurrentPage + 1; ?>" class="btn">Next ›</a>
          <a href="?<?php echo $paymentGroupPageKey; ?>=<?php echo $paymentTotalPages; ?>" class="btn">Last »</a>
        <?php endif; ?>
      </div>
    </div>
    
    <?php endforeach; ?>
  </div>
</div>

<!-- ✅ ADD TUITION MODAL (Student-Specific with Lec/Lab/Units Rates) -->
<div id="tuitionModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h3>Set Student Tuition Fee</h3>
    <form method="POST" id="addTuitionForm" data-form-type="tuition">
      <div class="form-group">
        <label>Student</label>
        <select name="student_id" id="add_student_id" data-field="student_id" required>
          <option value="">Select Student</option>
          <?php 
            try {
              $studentsQuery = "SELECT s.StudID as student_id, s.SchoolID as school_id, s.FirstName as first_name, s.LastName as last_name, s.Course as course, s.YearLvl as year_level FROM student s ORDER BY s.FirstName, s.LastName";
              $stmt = $database->getConnection()->prepare($studentsQuery);
              $stmt->execute();
              $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
              
              if (count($students) == 0) {
                echo '<option value="">No students found in the system</option>';
              } else {
                foreach ($students as $student):
                  echo '<option value="' . htmlspecialchars($student['student_id']) . '">' . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . ' (' . htmlspecialchars($student['school_id']) . ') - ' . htmlspecialchars($student['course']) . '</option>';
                endforeach;
              }
            } catch (Exception $e) {
              echo '<option value="">Error loading students: ' . htmlspecialchars($e->getMessage()) . '</option>';
            }
          ?>
        </select>
      </div>

      <div style="border-top: 1px solid #ddd; margin: 15px 0; padding-top: 15px;">
        <h4 style="margin: 0 0 10px 0;">Units</h4>
        <div class="form-group">
            <label>Units</label>
            <input type="number" min="0" step="0.5" name="units" class="tuition-calc" id="add_units" data-field="units" value="0" readonly style="background:#f0f0f0;">
        </div>
        <div class="form-group">
            <label>Rate per Unit (₱)</label>
            <input type="number" step="0.01" name="rate_unit" class="tuition-calc" id="add_rate_unit" data-field="rate_unit" value="0" readonly style="background:#f0f0f0;">
        </div>
      </div>

      <div style="background-color: #f9f9f9; border: 2px solid #007bff; border-radius: 5px; padding: 15px; margin-top: 15px;">
        <label style="font-weight: bold; font-size: 16px;">Total Tuition Fee (₱)</label>
        <input type="number" step="0.01" name="total_fee" id="add_total_fee" data-field="total_fee" value="0" readonly style="background-color: #f0f0f0; font-weight: bold; font-size: 18px; width: 100%;">
        <small style="color: #666; display: block; margin-top: 5px;">Calculated as: (Units × Rate Unit)</small>
        <div id="add_tuition_warning" style="color:#e74c3c; font-weight:bold; margin-top:10px; display:none;">This student is not enrolled in any subjects. Please enroll them first.</div>
      </div>

      <div class="form-group" style="margin-top: 15px;">
        <label>Notes (Optional)</label>
        <textarea name="notes" id="add_notes" data-field="notes" placeholder="e.g., Irregular student, Payment plan, etc." rows="3"></textarea>
      </div>

      <div class="form-actions">
        <button type="submit" name="save_tuition" class="btn-add">Save Tuition Fee</button>
      </div>
    </form>
  </div>
</div>



<!-- ✅ EDIT TUITION MODAL (Student-Specific with Lec/Lab/Units Rates) -->
<div id="editTuitionModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h3>Edit Student Tuition Fee</h3>
    <form method="POST" id="editTuitionForm" data-form-type="tuition">
      <input type="hidden" name="tuition_id" id="edit_tuition_id" data-field="id">

      <div class="form-group">
        <label>Student</label>
        <select name="student_id" id="edit_student_id" data-field="student_id" required>
          <option value="">Select Student</option>
          <?php 
            try {
              $studentsQuery = "SELECT s.StudID as student_id, s.SchoolID as school_id, s.FirstName as first_name, s.LastName as last_name, s.Course as course, s.YearLvl as year_level FROM student s ORDER BY s.FirstName, s.LastName";
              $stmt = $database->getConnection()->prepare($studentsQuery);
              $stmt->execute();
              $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
              
              if (count($students) == 0) {
                echo '<option value="">No students found in the system</option>';
              } else {
                foreach ($students as $student):
                  echo '<option value="' . htmlspecialchars($student['student_id']) . '">' . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . ' (' . htmlspecialchars($student['school_id']) . ') - ' . htmlspecialchars($student['course']) . '</option>';
                endforeach;
              }
            } catch (Exception $e) {
              echo '<option value="">Error loading students: ' . htmlspecialchars($e->getMessage()) . '</option>';
            }
          ?>
        </select>
      </div>

      <div style="border-top: 1px solid #ddd; margin: 15px 0; padding-top: 15px;">
        <h4 style="margin: 0 0 10px 0;">Units</h4>
        <div class="form-group">
          <label>Units</label>
          <input type="number" min="0" step="0.5" name="units" class="edit-tuition-calc" id="edit_units" data-field="units" value="0">
        </div>
        <div class="form-group">
          <label>Rate per Unit (₱)</label>
          <input type="number" step="0.01" name="rate_unit" class="edit-tuition-calc" id="edit_rate_unit" data-field="rate_unit" value="1000">
        </div>
      </div>

      <div style="background-color: #f9f9f9; border: 2px solid #007bff; border-radius: 5px; padding: 15px; margin-top: 15px;">
        <label style="font-weight: bold; font-size: 16px;">Total Tuition Fee (₱)</label>
        <input type="number" step="0.01" name="total_fee" id="edit_total_fee" data-field="total_fee" readonly style="background-color: #f0f0f0; font-weight: bold; font-size: 18px; width: 100%;">
        <small style="color: #666; display: block; margin-top: 5px;">Calculated as: (Units × Rate Unit)</small>
      </div>

      <div class="form-group" style="margin-top: 15px;">
        <label>Notes (Optional)</label>
        <textarea name="notes" id="edit_notes" data-field="notes" placeholder="e.g., Irregular student, Payment plan, etc." rows="3"></textarea>
      </div>

      <button type="submit" name="update_tuition" class="btn-add">Update Tuition Fee</button>
    </form>
  </div>
</div>

<!-- ✅ EDIT PAYMENT MODAL -->
<div id="editPaymentModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h3>Edit Payment</h3>
    <form method="POST">
      <input type="hidden" name="payment_id" id="edit_payment_id">
      <div class="form-group"><label>Amount Due</label><input type="number" step="0.01" name="amount_due" id="edit_due" required></div>
      <div class="form-group"><label>Amount Paid</label><input type="number" step="0.01" name="amount_paid" id="edit_paid" required></div>
      <div class="form-group"><label>Status</label>
        <select name="status" id="edit_status">
          <option value="Unpaid">Unpaid</option>
          <option value="Partially Paid">Partially Paid</option>
          <option value="Paid">Paid</option>
        </select>
      </div>
      <button type="submit" name="update_payment" class="btn-add">Update Payment</button>
    </form>
  </div>
</div>

<!-- 🗑️ DELETE CONFIRMATION MODAL -->
<div id="deleteModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h2>Confirm Deletion</h2>
    <p>Are you sure you want to delete <strong id="deleteItemName"></strong>?</p>
    <form id="deleteForm" method="GET">
      <input type="hidden" name="delete_tuition" id="deleteTuitionId">
      <input type="hidden" name="delete_payment" id="deletePaymentId">
      <input type="hidden" name="delete_student_payment" id="deleteStudentPaymentId">
      <button type="submit" class="btn delete-btn">Yes</button>
      <button type="button" class="btn cancel-btn">Cancel</button>
    </form>
  </div>
</div>

<!-- ✅ DYNAMIC SUCCESS MODAL -->
<div class="modal-overlay" id="dynamicSuccessModal">
  <div class="success-modal">
    <h3><i class="fa-solid fa-circle-check"></i> <span id="dynamicSuccessTitle">Success!</span></h3>
    <p id="dynamicSuccessMessage"></p>
    <button onclick="closeDynamicModal()">OK</button>
  </div>
</div>

<script>
// === DYNAMIC SUCCESS MODAL ===
const dynamicModal = document.getElementById('dynamicSuccessModal');
const dynamicTitle = document.getElementById('dynamicSuccessTitle');
const dynamicMessage = document.getElementById('dynamicSuccessMessage');

function showDynamicModal(title, message) {
  dynamicTitle.textContent = title;
  dynamicMessage.textContent = message;
  dynamicModal.classList.add('show');
}

function closeDynamicModal() {
  dynamicModal.classList.remove('show');
}

// Auto-show modal based on URL params
document.addEventListener("DOMContentLoaded", () => {
  const params = new URLSearchParams(window.location.search);
  let title = '';
  let message = '';

  if (params.has('added')) {
    title = "Payment Added!";
    message = "You have successfully added a payment record.";
  } else if (params.has('payment_updated')) {
    title = "Payment Updated!";
    message = "You have successfully updated a payment record.";
  } else if (params.has('tuition_added')) {
    title = "Tuition Fee Added!";
    message = "A new tuition fee record has been added successfully.";
  } else if (params.has('tuition_updated')) {
    title = "Tuition Fee Updated!";
    message = "The tuition fee record has been updated successfully.";
  } else if (params.has('tuition_deleted')) {
    title = "Tuition Fee Deleted!";
    message = "The tuition fee record has been deleted.";
  } else if (params.has('student_payment_deleted')) {
    title = "Student Payment Deleted!";
    message = "The student payment record has been deleted.";
  } else if (params.has('payment_deleted')) {
    title = "Payment Deleted!";
    message = "The payment record has been deleted.";
  }

  if (title && message) {
    showDynamicModal(title, message);

    // Auto close after 3 seconds
    setTimeout(closeDynamicModal, 3000);

    // Clean URL params
    const url = new URL(window.location);
    ['added','payment_updated','tuition_added','tuition_updated','tuition_deleted','student_payment_deleted','payment_deleted'].forEach(p => url.searchParams.delete(p));
    window.history.replaceState({}, document.title, url.toString());
  }
});
</script>


<script>
// === MODAL CONTROLS ===
const openTuitionModal = document.getElementById('openTuitionModal');
const tuitionModal = document.getElementById('tuitionModal');

openTuitionModal.onclick = () => tuitionModal.style.display = 'block';

// === AUTO-CALCULATE TOTAL FEE (Add Modal) ===
function calculateAddTuitionFee() {
  const units = parseFloat(document.getElementById('add_units').value) || 0;
  const rateUnit = parseFloat(document.getElementById('add_rate_unit').value) || 0;
  
  const total = units * rateUnit;
  document.getElementById('add_total_fee').value = total.toFixed(2);
}

// Auto-fill tuition based on enrolled subjects when student is selected
document.getElementById('add_student_id').addEventListener('change', function() {
  const studentId = this.value;
  const unitsField = document.getElementById('add_units');
  const rateField = document.getElementById('add_rate_unit');
  const totalField = document.getElementById('add_total_fee');
  const warn = document.getElementById('add_tuition_warning');
  
  if (!studentId) {
    unitsField.value = '0';
    rateField.value = '3144.07';
    totalField.value = '0';
    warn.style.display = 'none';
    return;
  }
  fetch('fetch_student_tuition_calc.php?student_id=' + encodeURIComponent(studentId))
    .then(response => response.json())
    .then(data => {
      if (data.status === 'success') {
        unitsField.value = data.total_units.toFixed(1);
        rateField.value = data.rate_per_unit.toFixed(2);
        totalField.value = data.total_cost.toFixed(2);
        if (data.subject_count === 0) {
          warn.style.display = 'block';
          unitsField.value = '0';
          rateField.value = '3144.07';
          totalField.value = '0';
        } else {
          warn.style.display = 'none';
        }
      } else {
        warn.style.display = 'block';
        unitsField.value = '0';
        rateField.value = '3144.07';
        totalField.value = '0';
      }
    })
    .catch(err => {
      warn.style.display = 'block';
      unitsField.value = '0';
      rateField.value = '3144.07';
      totalField.value = '0';
    });
});


// === AUTO-CALCULATE TOTAL FEE (Edit Modal) ===
function calculateEditTuitionFee() {
  const units = parseFloat(document.getElementById('edit_units').value) || 0;
  const rateUnit = parseFloat(document.getElementById('edit_rate_unit').value) || 0;
  
  const total = units * rateUnit;
  document.getElementById('edit_total_fee').value = total.toFixed(2);
}

// Auto-fill tuition based on enrolled subjects when student is changed in edit modal
document.getElementById('edit_student_id').addEventListener('change', function() {
  const studentId = this.value;
  
  if (!studentId) {
    return;
  }
  
  // Fetch enrolled subjects and calculate tuition
  fetch('fetch_student_tuition_calc.php?student_id=' + encodeURIComponent(studentId))
    .then(response => response.json())
    .then(data => {
      if (data.status === 'success' && data.subject_count > 0) {
        // Auto-fill the fields with calculated values
        document.getElementById('edit_units').value = data.total_units.toFixed(1);
        document.getElementById('edit_rate_unit').value = data.rate_per_unit.toFixed(2);
        document.getElementById('edit_total_fee').value = data.total_cost.toFixed(2);
      }
    })
    .catch(err => {
      console.error('Error:', err);
    });
});


// Attach event listeners for Add Modal
['add_units', 'add_rate_unit'].forEach(id => {
  const el = document.getElementById(id);
  if (el) {
    el.addEventListener('input', calculateAddTuitionFee);
  }
});

// Attach event listeners for Edit Modal
['edit_units', 'edit_rate_unit'].forEach(id => {
  const el = document.getElementById(id);
  if (el) {
    el.addEventListener('input', calculateEditTuitionFee);
  }
});

// Edit Tuition
document.querySelectorAll('.edit-tuition-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('edit_tuition_id').value = btn.dataset.id;
    document.getElementById('edit_student_id').value = btn.dataset.studentId;
    document.getElementById('edit_units').value = btn.dataset.units;
    document.getElementById('edit_rate_unit').value = btn.dataset.rateUnit;
    document.getElementById('edit_total_fee').value = btn.dataset.totalFee;
    document.getElementById('edit_notes').value = btn.dataset.notes;
    document.getElementById('editTuitionModal').style.display = 'block';
  });
});

// Edit Payment
document.querySelectorAll('.edit-payment-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('edit_payment_id').value = btn.dataset.id;
    document.getElementById('edit_due').value = btn.dataset.due;
    document.getElementById('edit_paid').value = btn.dataset.paid;
    document.getElementById('edit_status').value = btn.dataset.status;
    document.getElementById('editPaymentModal').style.display = 'block';
  });
});

// Close any modal
document.querySelectorAll('.close').forEach(btn => {
  btn.addEventListener('click', () => {
    btn.closest('.modal').style.display = 'none';
  });
});

// === 🔎 FILTER TUITION FEES ===
const tuitionSearch = document.getElementById('searchTuition');
const allTuitionRows = document.querySelectorAll('.tuition-table tbody tr');

function filterTuition() {
  const searchText = tuitionSearch.value.toLowerCase();

  allTuitionRows.forEach(row => {
    const studentId = row.cells[0].textContent;
    const studentName = row.cells[1].textContent;
    const fee = row.cells[2].textContent;
    const notes = row.cells[3].textContent;
    
    const rowText = (studentId + ' ' + studentName + ' ' + fee + ' ' + notes).toLowerCase();

    const matchSearch = rowText.includes(searchText);

    row.style.display = matchSearch ? '' : 'none';
  });
}

if (tuitionSearch) {
  tuitionSearch.addEventListener('input', filterTuition);
  tuitionSearch.addEventListener('change', filterTuition);
}

// === 🔎 FILTER PAYMENT RECORDS ===
const paySearch = document.getElementById('searchPayment');
const payCourse = document.getElementById('filterCoursePayment');
const payYear = document.getElementById('filterYearPayment');
const paySem = document.getElementById('filterSemPayment');
const allPaymentRows = document.querySelectorAll('.payment-table tbody tr');

function filterPayments() {
  const searchText = paySearch.value.toLowerCase();
  const course = payCourse.value;
  const year = payYear.value;
  const sem = paySem.value;

  allPaymentRows.forEach(row => {
    const receiptCell = row.cells[0].textContent;
    const studentCell = row.cells[1].textContent;
    const courseCell = row.cells[2].textContent;
    const examTypeCell = row.cells[3].textContent;
    const dueCell = row.cells[4].textContent;
    const paidCell = row.cells[5].textContent;
    const statusCell = row.cells[6].textContent;
    const dateCell = row.cells[7].textContent;
    
    const rowText = (receiptCell + ' ' + studentCell + ' ' + courseCell + ' ' + examTypeCell + ' ' + dueCell + ' ' + paidCell + ' ' + statusCell + ' ' + dateCell).toLowerCase();

    const matchSearch = rowText.includes(searchText);
    const matchCourse = !course || courseCell === course;
    const matchYear = !year || yearCell === year;
    const matchSem = !sem || semCell === sem;

    row.style.display = (matchSearch && matchCourse && matchYear && matchSem) ? '' : 'none';
  });
}

if (paySearch) paySearch.addEventListener('input', filterPayments);
if (payCourse) payCourse.addEventListener('change', filterPayments);
if (payYear) payYear.addEventListener('change', filterPayments);
if (paySem) paySem.addEventListener('change', filterPayments);

// === DELETE CONFIRMATION MODAL ===
const deleteModal = document.getElementById('deleteModal');
const deleteBtns = document.querySelectorAll('.openDeleteModal');
const closeDelete = deleteModal ? deleteModal.querySelector('.close') : null;
const cancelBtn = deleteModal ? deleteModal.querySelector('.cancel-btn') : null;
const deleteItemName = document.getElementById('deleteItemName');
const deleteTuitionId = document.getElementById('deleteTuitionId');
const deletePaymentId = document.getElementById('deletePaymentId');
const deleteStudentPaymentId = document.getElementById('deleteStudentPaymentId');
const deleteForm = document.getElementById('deleteForm');

if (deleteModal) {
  deleteBtns.forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const name = this.dataset.name;
      const id = this.dataset.id;
      const type = this.dataset.type; // tuition, payment, or student_payment

      deleteItemName.textContent = name;

      // Reset fields first
      deleteTuitionId.removeAttribute('name');
      deletePaymentId.removeAttribute('name');
      deleteStudentPaymentId.removeAttribute('name');

      // Assign correct hidden field
      if (type === 'tuition') {
        deleteTuitionId.name = 'delete_tuition';
        deleteTuitionId.value = id;
      } else if (type === 'payment') {
        deletePaymentId.name = 'delete_payment';
        deletePaymentId.value = id;
      } else if (type === 'student_payment') {
        deleteStudentPaymentId.name = 'delete_student_payment';
        deleteStudentPaymentId.value = id;
      }

      deleteModal.style.display = 'block';
    });
  });

  closeDelete.addEventListener('click', () => deleteModal.style.display = 'none');
  cancelBtn.addEventListener('click', () => deleteModal.style.display = 'none');
  window.addEventListener('click', (e) => {
    if (e.target == deleteModal) deleteModal.style.display = 'none';
  });
}

</script>

</body>
</html>