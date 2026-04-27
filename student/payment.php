<?php 
// Include database first
include_once __DIR__ . '/db.php';
$database = new Database();
$conn = $database->getConnection();

// Start session only if not already active
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (isset($_SESSION['userRole']) && $_SESSION['userRole'] === 'student') {
    $_SESSION['userRole'] = 'trainee';
}

// Set page info for sidebar
$currentPage = 'payment.php';
$pageTitle = 'Payment';
$pageSubtitle = 'Payment History & Transactions';

include 'sidebar_student.php';

if (!isset($_SESSION['userId']) || !in_array($_SESSION['userRole'], ['trainee', 'student'])) {
    header("Location: ../login.php");
    exit;
}

$userId = $_SESSION['userId'];

// Helper functions for column detection
if (!function_exists('columnExists')) {
    function columnExists(PDO $conn, string $table, string $column): bool {
        $stmt = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetchColumn();
    }
}

// Detect actual column names - use simple safe query
$firstNameCol = 'FName';
$lastNameCol = 'LName';
$yearLvlCol = 'YearLvl';

// Check what columns actually exist in student table
try {
    // Try to get columns dynamically - start with known columns from schema
    $firstNameCol = columnExists($conn, 'student', 'FName') ? 'FName' : (columnExists($conn, 'student', 'FirstName') ? 'FirstName' : 'FName');
    $lastNameCol = columnExists($conn, 'student', 'LName') ? 'LName' : (columnExists($conn, 'student', 'LastName') ? 'LastName' : 'LName');
    $yearLvlCol = columnExists($conn, 'student', 'YearLvl') ? 'YearLvl' : null;
} catch (Exception $e) {
    // Use defaults if query fails
}

$sql = "SELECT StudID, $firstNameCol, $lastNameCol, Course" . ($yearLvlCol ? ", $yearLvlCol" : "") . " FROM student WHERE StudID = ?";
$student = $conn->prepare($sql);
$student->execute([$userId]);
$student_data = $student->fetch(PDO::FETCH_ASSOC);

if (!$student_data) {
    echo "Student record not found.";
    exit;
}

$student_id  = $student_data['StudID'];
$course      = $student_data['Course'];
$year_level  = $yearLvlCol ? ($student_data[$yearLvlCol] ?? null) : null;
$semester    = null;

// Fetch total tuition fee assigned to this student from tuition_fees table (the setup table)
$total_payable = 0;
$tuition_exists = false;
try {
    $fee = $conn->prepare("
        SELECT total_fee 
        FROM tuition_fees 
        WHERE StudID = ? 
        ORDER BY fee_id DESC
        LIMIT 1
    ");
    $fee->execute([$student_id]);
    $tuition = $fee->fetch(PDO::FETCH_ASSOC);
    if ($tuition) {
        $total_payable = $tuition['total_fee'];
        $tuition_exists = true;
    }
} catch (Exception $e) {
    $total_payable = 0;
}

// Fetch all payments for this student
try {
    $sql = $conn->prepare("
        SELECT p.* FROM payments p 
        WHERE p.StudID = ? AND p.receipt_number LIKE 'INV%'
        ORDER BY p.payment_date ASC
    ");
    $sql->execute([$student_id]);
    $payments = $sql->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $payments = [];
}

// Fetch Student Payment Records from tuition table with payment descriptions
$tuition_records = [];
try {
    $tuition_query = $conn->prepare("
        SELECT 
            t.*,
            s.SchoolID as school_id,
            s.FirstName as first_name,
            s.LastName as last_name,
            s.Course as course,
            GROUP_CONCAT(DISTINCT p.description ORDER BY p.payment_date SEPARATOR ' | ') as payment_descriptions
        FROM tuition t
        JOIN student s ON s.StudID = t.StudID
        LEFT JOIN payments p ON p.StudID = t.StudID
        WHERE t.StudID = ?
        GROUP BY t.tuition_id
        ORDER BY t.generated_at DESC
    ");
    $tuition_query->execute([$student_id]);
    $tuition_records = $tuition_query->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tuition_records = [];
}

// Sync existing tuition records to payments table and mark exams as paid
// This allows us to match student payment history to payment invoices
try {
    foreach ($tuition_records as $record) {
        $tuition_id = $record['tuition_id'];
        $tuition_amount = (float)($record['total_amount'] ?? 0);
        $generated_date = $record['generated_at'];
        
        // Extract exam type from tuition record (check notes or description field)
        $exam_type = null;
        $payment_type = null;
        
        // Check if notes field contains exam info
        $notes = strtolower($record['notes'] ?? '');
        if (strpos($notes, 'prelim') !== false) {
            $exam_type = 'Prelims';
            $payment_type = 'Prelims Tuition';
        } elseif (strpos($notes, 'midterm') !== false) {
            $exam_type = 'Midterm';
            $payment_type = 'Midterm Tuition';
        } elseif (strpos($notes, 'semi') !== false) {
            $exam_type = 'Semis';
            $payment_type = 'Semis Tuition';
        } elseif (strpos($notes, 'final') !== false) {
            $exam_type = 'Finals';
            $payment_type = 'Finals Tuition';
        }
        
        // If exam type was identified, mark it as paid in payments table
        if ($payment_type) {
            // Check if payment record exists for this student and payment type
            $checkPayment = $conn->prepare("
                SELECT payment_id FROM payments 
                WHERE StudID = ? AND payment_type = ? 
                LIMIT 1
            ");
            $checkPayment->execute([$student_id, $payment_type]);
            $existingPayment = $checkPayment->fetch(PDO::FETCH_ASSOC);
            
            if ($existingPayment) {
                // Update existing payment record to mark as paid
                $updatePayment = $conn->prepare("
                    UPDATE payments 
                    SET amount_paid = ?, payment_status = 'Paid', payment_date = ?
                    WHERE payment_id = ?
                ");
                $updatePayment->execute([$tuition_amount / 4, $generated_date, $existingPayment['payment_id']]);
            } else {
                // Create new payment record for this paid exam
                $createPayment = $conn->prepare("
                    INSERT INTO payments (StudID, payment_type, amount_paid, payment_status, payment_date, receipt_number)
                    VALUES (?, ?, ?, 'Paid', ?, ?)
                ");
                $receipt_num = 'TUI-' . $student_id . '-' . strtoupper($exam_type) . '-' . date('Ymd');
                $createPayment->execute([$student_id, $payment_type, $tuition_amount / 4, $generated_date, $receipt_num]);
            }
        }
    }
} catch (Exception $e) {
    // Log error but don't break
}

// Calculate total paid from Student Payment Records table (tuition table)
// This is the source of truth for what the student has paid
$total_paid = 0;
$has_tuition_record = false;
try {
    if (!empty($tuition_records)) {
        // Get the latest tuition record - this represents the total student has paid
        $total_paid = (float)($tuition_records[0]['total_amount'] ?? 0);
        $has_tuition_record = true;
    }
} catch (Exception $e) {
    $total_paid = 0;
}

// Check if fully paid (total paid >= total payable)
$is_fully_paid = $total_paid >= $total_payable && $total_payable > 0;

// Cap displayed amount at total payable (don't show overpayment)
$displayed_total_paid = min($total_paid, $total_payable);

// Calculate outstanding balance
$outstanding = max($total_payable - $total_paid, 0);

$paymentsHaveYear = columnExists($conn, 'payments', 'year_level');
$paymentsHaveSem  = columnExists($conn, 'payments', 'semester');

// Normalize and group payments by year and semester
$formatYear = function($y) {
  if ($y === null || $y === '') return 'Current Year';
  $map = [1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year'];
  if (is_numeric($y)) {
    $n = (int)$y;
    return $map[$n] ?? (string)$y;
  }
  return $y;
};

$formatSem = function($s) {
  if ($s === null || $s === '') return '1st Sem';
  if (is_numeric($s)) {
    return ((int)$s === 1 ? '1st Sem' : ((int)$s === 2 ? '2nd Sem' : 'Semester ' . $s));
  }
  return $s;
};

foreach ($payments as &$p) {
  $yearSrc = $paymentsHaveYear && isset($p['year_level']) ? $p['year_level'] : $year_level;
  $semSrc  = $paymentsHaveSem && isset($p['semester']) ? $p['semester'] : $semester;
  
  $p['year_display'] = $formatYear($yearSrc);
  $p['sem_display']  = $formatSem($semSrc);
}
unset($p);

// Group by year → semester
$groupedPayments = [];
foreach ($payments as $row) {
  $y = $row['year_display'];
  $m = $row['sem_display'];
  if (!isset($groupedPayments[$y])) $groupedPayments[$y] = [];
  if (!isset($groupedPayments[$y][$m])) $groupedPayments[$y][$m] = [];
  $groupedPayments[$y][$m][] = $row;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Trainee Payment Information</title>
   
    <link rel="stylesheet" href="css/payment.css">
    <link rel="stylesheet" href="css/courses.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>


<body>

<div class="main-content">
    <h2>Payment Information</h2>
    <p>Manage your tuition fees and payment history</p>

    <div class="cards">
        <div class="card">
            <h4>Total Payable</h4>
            <h1>₱<?= number_format($total_payable, 2) ?></h1>
            <p><?= $tuition_exists ? 'Tuition fee for this semester' : 'No tuition set yet' ?></p>
        </div>

        <div class="card">
            <h4>Total Paid</h4>
            <h1>₱<?= number_format($displayed_total_paid, 2) ?></h1>
            <p><?php 
                if (!$tuition_exists) {
                    echo 'No tuition fee set';
                } elseif ($is_fully_paid) {
                    echo 'Status: <span style="color: #27ae60; font-weight: bold;">Fully Paid</span>';
                } elseif ($total_paid > 0) {
                    $percentage = ($displayed_total_paid / $total_payable) * 100;
                    echo 'Status: <span style="color: #f39c12; font-weight: bold;">Partially Paid (' . number_format($percentage, 1) . '%)</span>';
                } else {
                    echo 'Status: <span style="color: #e74c3c; font-weight: bold;">Unpaid</span>';
                }
            ?></p>
        </div>

        <div class="card">
            <h4>Outstanding Balance</h4>
            <h1>₱<?= number_format($outstanding, 2) ?></h1>
            <p><?php 
                if ($outstanding > 0) {
                    echo '<span style="color: #e74c3c; font-weight: bold;">Payment Required</span>';
                } else {
                    echo '<span style="color: #27ae60; font-weight: bold;">Fully Settled</span>';
                }
            ?></p>
        </div>
    </div>

    <!-- Amount Due Per Exam Section -->
    <?php if ($tuition_exists): ?>
    <?php 
        $payment_per_exam = $total_payable / 4;
        
        // Create status map
        $status_map = [
            'Prelims Tuition' => 'Unpaid',
            'Midterm Tuition' => 'Unpaid',
            'Semis Tuition' => 'Unpaid',
            'Finals Tuition' => 'Unpaid'
        ];
        
        // If fully paid (total paid >= total payable), mark all exams as paid
        if ($displayed_total_paid >= $total_payable) {
            $status_map['Prelims Tuition'] = 'Paid';
            $status_map['Midterm Tuition'] = 'Paid';
            $status_map['Semis Tuition'] = 'Paid';
            $status_map['Finals Tuition'] = 'Paid';
        } else {
            // Check payment status from payments table
            $exam_status_query = $conn->prepare("
                SELECT payment_type, amount_paid, payment_status 
                FROM payments 
                WHERE StudID = ? AND payment_type IN ('Prelims Tuition', 'Midterm Tuition', 'Semis Tuition', 'Finals Tuition')
            ");
            $exam_status_query->execute([$student_id]);
            $exam_payments = $exam_status_query->fetchAll(PDO::FETCH_ASSOC);
            
            // Check payments table
            foreach ($exam_payments as $payment) {
                if ($payment['payment_status'] == 'Paid' && $payment['amount_paid'] >= $payment_per_exam) {
                    $status_map[$payment['payment_type']] = 'Paid';
                }
            }
            
            // Also check tuition records (Payment Transaction History)
            foreach ($tuition_records as $record) {
                $record_amount = (float)($record['total_amount'] ?? 0);
                $payment_descriptions = strtolower($record['payment_descriptions'] ?? '');
                $notes = strtolower($record['notes'] ?? '');
                
                // Check if amount matches or exceeds payment per exam (with small tolerance for floating point)
                $tolerance = 0.01;
                if ($record_amount >= ($payment_per_exam - $tolerance)) {
                    // Check for Prelims
                    if (strpos($payment_descriptions, 'prelim') !== false || strpos($notes, 'prelim') !== false) {
                        $status_map['Prelims Tuition'] = 'Paid';
                    }
                    // Check for Midterm
                    if (strpos($payment_descriptions, 'midterm') !== false || strpos($notes, 'midterm') !== false) {
                        $status_map['Midterm Tuition'] = 'Paid';
                    }
                    // Check for Semis
                    if (strpos($payment_descriptions, 'semi') !== false || strpos($notes, 'semi') !== false) {
                        $status_map['Semis Tuition'] = 'Paid';
                    }
                    // Check for Finals
                    if (strpos($payment_descriptions, 'final') !== false || strpos($notes, 'final') !== false) {
                        $status_map['Finals Tuition'] = 'Paid';
                    }
                }
            }
        }
    ?>
    <div style="background: white; border-radius: 8px; padding: 20px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h3 style="margin-bottom: 20px; color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;">
            <i class="fa-solid fa-list-check"></i> Amount Due Per Exam
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #2563eb 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <h4 style="margin: 0 0 10px 0; font-size: 14px; opacity: 0.9;">
                    <i class="fa-solid fa-book-open"></i> Prelims Due
                </h4>
                <p style="margin: 0 0 10px 0; font-size: 24px; font-weight: bold;">₱<?= number_format($payment_per_exam, 2) ?></p>
                <span style="background: <?= $status_map['Prelims Tuition'] == 'Paid' ? '#27ae60' : '#e74c3c' ?>; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: bold;">
                    <i class="fa-solid fa-<?= $status_map['Prelims Tuition'] == 'Paid' ? 'check-circle' : 'exclamation-circle' ?>"></i> 
                    <?= $status_map['Prelims Tuition'] ?>
                </span>
            </div>
            <div style="background: linear-gradient(135deg, #f093fb 0%, #2563eb 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <h4 style="margin: 0 0 10px 0; font-size: 14px; opacity: 0.9;">
                    <i class="fa-solid fa-book"></i> Midterm Due
                </h4>
                <p style="margin: 0 0 10px 0; font-size: 24px; font-weight: bold;">₱<?= number_format($payment_per_exam, 2) ?></p>
                <span style="background: <?= $status_map['Midterm Tuition'] == 'Paid' ? '#27ae60' : '#e74c3c' ?>; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: bold;">
                    <i class="fa-solid fa-<?= $status_map['Midterm Tuition'] == 'Paid' ? 'check-circle' : 'exclamation-circle' ?>"></i> 
                    <?= $status_map['Midterm Tuition'] ?>
                </span>
            </div>
            <div style="background: linear-gradient(135deg, #4facfe 0%, #2563eb 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <h4 style="margin: 0 0 10px 0; font-size: 14px; opacity: 0.9;">
                    <i class="fa-solid fa-bookmark"></i> Semis Due
                </h4>
                <p style="margin: 0 0 10px 0; font-size: 24px; font-weight: bold;">₱<?= number_format($payment_per_exam, 2) ?></p>
                <span style="background: <?= $status_map['Semis Tuition'] == 'Paid' ? '#27ae60' : '#e74c3c' ?>; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: bold;">
                    <i class="fa-solid fa-<?= $status_map['Semis Tuition'] == 'Paid' ? 'check-circle' : 'exclamation-circle' ?>"></i> 
                    <?= $status_map['Semis Tuition'] ?>
                </span>
            </div>
            <div style="background: linear-gradient(135deg, #43e97b 0%, #2563eb 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <h4 style="margin: 0 0 10px 0; font-size: 14px; opacity: 0.9;">
                    <i class="fa-solid fa-graduation-cap"></i> Finals Due
                </h4>
                <p style="margin: 0 0 10px 0; font-size: 24px; font-weight: bold;">₱<?= number_format($payment_per_exam, 2) ?></p>
                <span style="background: <?= $status_map['Finals Tuition'] == 'Paid' ? '#27ae60' : '#e74c3c' ?>; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: bold;">
                    <i class="fa-solid fa-<?= $status_map['Finals Tuition'] == 'Paid' ? 'check-circle' : 'exclamation-circle' ?>"></i> 
                    <?= $status_map['Finals Tuition'] ?>
                </span>
            </div>
        </div>
        <p style="margin-top: 15px; color: #7f8c8d; font-size: 14px; text-align: center;">
            <i class="fa-solid fa-info-circle"></i> Required payment amount for each exam period
        </p>
    </div>
    <?php endif; ?>

    <!-- Payment Schedule Section -->
    <?php if ($tuition_exists): ?>
    <?php 
        // Fetch payment invoices from payments table with exam status
        try {
            $payment_schedule_query = $conn->prepare("
                SELECT payment_type, amount_paid, payment_status, payment_date
                FROM payments 
                WHERE StudID = ?
                AND payment_type IN ('Prelims Tuition', 'Midterm Tuition', 'Semis Tuition', 'Finals Tuition')
                ORDER BY FIELD(payment_type, 'Prelims Tuition', 'Midterm Tuition', 'Semis Tuition', 'Finals Tuition')
            ");
            $payment_schedule_query->execute([$student_id]);
            $payment_invoices = $payment_schedule_query->fetchAll(PDO::FETCH_ASSOC);
            
            // If no invoices exist, create them
            if (empty($payment_invoices)) {
                $payment_per_exam = $total_payable / 4;
                $exam_types = [
                    'Prelims' => 'Prelims Tuition',
                    'Midterm' => 'Midterm Tuition',
                    'Semis' => 'Semis Tuition',
                    'Finals' => 'Finals Tuition'
                ];
                
                $paymentInsert = $conn->prepare("
                    INSERT INTO payments (StudID, payment_type, amount_paid, payment_status, receipt_number)
                    VALUES (?, ?, ?, 'Unpaid', ?)
                    ON DUPLICATE KEY UPDATE payment_id=LAST_INSERT_ID(payment_id)
                ");
                
                foreach ($exam_types as $exam => $description) {
                    $receipt_num = 'BILL-' . $student_id . '-' . strtoupper($exam) . '-' . date('Ymd');
                    try {
                        $paymentInsert->execute([$student_id, $description, $payment_per_exam, $receipt_num]);
                    } catch (Exception $e) {
                        // Record may already exist
                    }
                }
                
                // Fetch again after creation
                $payment_schedule_query->execute([$student_id]);
                $payment_invoices = $payment_schedule_query->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Map payment types to display info
            $payment_map = [
                'Prelims Tuition' => ['label' => 'Prelims Exam', 'icon' => 'book-open', 'gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'],
                'Midterm Tuition' => ['label' => 'Midterm Exam', 'icon' => 'book', 'gradient' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)'],
                'Semis Tuition' => ['label' => 'Semis Exam', 'icon' => 'bookmark', 'gradient' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)'],
                'Finals Tuition' => ['label' => 'Finals Exam', 'icon' => 'graduation-cap', 'gradient' => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)']
            ];
        } catch (Exception $e) {
            $payment_invoices = [];
        }
    ?>
    <?php if (!empty($payment_invoices)): ?>
    <div style="background: white; border-radius: 8px; padding: 20px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h3 style="margin-bottom: 20px; color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;">
            <i class="fa-solid fa-calendar-check"></i> Payment Schedule by Exam
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
            <?php foreach ($payment_invoices as $invoice): ?>
                <?php 
                    $type = $invoice['payment_type'];
                    $info = $payment_map[$type] ?? ['label' => $type, 'icon' => 'money-bill', 'gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'];
                    $is_paid = ($invoice['payment_status'] === 'Paid');
                    $amount = (float)($invoice['amount_paid'] ?? 0);
                    $status_badge = $is_paid ? '<span style="font-size: 12px; background: rgba(255,255,255,0.3); padding: 4px 10px; border-radius: 12px; margin-left: 8px; font-weight: bold;">✓ PAID</span>' : '<span style="font-size: 12px; background: rgba(255,255,255,0.3); padding: 4px 10px; border-radius: 12px; margin-left: 8px;">Unpaid</span>';
                ?>
                <div style="background: <?= $info['gradient'] ?>; color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); <?= $is_paid ? 'opacity: 0.75;' : '' ?> transition: all 0.3s ease;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                        <h4 style="margin: 0; font-size: 14px; opacity: 0.95;">
                            <i class="fa-solid fa-<?= $info['icon'] ?>"></i> <?= htmlspecialchars($info['label']) ?>
                        </h4>
                        <?= $status_badge ?>
                    </div>
                    <p style="margin: 0; font-size: 26px; font-weight: bold; margin-bottom: 8px;">
                        ₱<?= number_format($amount, 2) ?>
                    </p>
                    <?php if ($is_paid && $invoice['payment_date']): ?>
                        <p style="margin: 0; font-size: 12px; opacity: 0.85;">
                            Paid on: <?= date('M d, Y', strtotime($invoice['payment_date'])) ?>
                        </p>
                    <?php else: ?>
                        <p style="margin: 0; font-size: 12px; opacity: 0.85; font-style: italic;">
                            Awaiting payment
                        </p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <p style="margin-top: 15px; color: #7f8c8d; font-size: 13px; text-align: center;">
            <i class="fa-solid fa-info-circle"></i> Pay each exam period before the exam date to avoid restrictions
        </p>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <div class="history-header">
        <h3>Payment Transaction History</h3>
        <button class="export-btn" onclick="exportPayment()"><i class="fa-solid fa-download"></i> Export</button>
    </div>

    <!-- Payment History Table -->
    <div class="table-container" id="paymentTable">
      <?php if (!$tuition_exists): ?>
        <div style="text-align: center; padding: 40px; color: #999;">
          <i class="fa-solid fa-inbox" style="font-size: 48px; margin-bottom: 10px;"></i>
          <h3>No Tuition Fee Set</h3>
          <p>Your tuition fee has not been set up yet. Please contact the registrar's office.</p>
        </div>
      <?php elseif (count($tuition_records) == 0): ?>
        <div style="text-align: center; padding: 40px; color: #f39c12;">
          <i class="fa-solid fa-exclamation-circle" style="font-size: 48px; margin-bottom: 10px;"></i>
          <h3>No Payment Records</h3>
          <p>Your tuition fee of ₱<?= number_format($total_payable, 2) ?> has been set.</p>
          <p style="margin-top: 10px; color: #e74c3c; font-weight: bold;">Outstanding Balance: ₱<?= number_format($outstanding, 2) ?></p>
          <p style="margin-top: 10px; color: #666;">Payment records will appear here once processed.</p>
        </div>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>School ID</th>
              <th>Student Name</th>
              <th>Course</th>
              <th>Exam Period</th>
              <th>Total Units</th>
              <th>Total Amount</th>
              <th>Date Generated</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tuition_records as $record): ?>
              <?php 
                // Cap displayed amount at total payable to hide overpayment
                $record_total_amount = $record['total_amount'] ?? 0;
                $displayed_amount = min($record_total_amount, $total_payable);
                
                // Extract exam period from payment descriptions (from payments table) or notes field
                $exam_badges = [];
                $payment_descriptions = $record['payment_descriptions'] ?? '';
                $notes = $record['notes'] ?? '';
                
                // Check if fully paid - if yes, show all exam badges
                if ($displayed_total_paid >= $total_payable && $total_payable > 0) {
                    $exam_badges[] = '<span style="background: #667eea; color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 12px; margin: 2px; display: inline-block;">Prelims</span>';
                    $exam_badges[] = '<span style="background: #f5576c; color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 12px; margin: 2px; display: inline-block;">Midterm</span>';
                    $exam_badges[] = '<span style="background: #00f2fe; color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 12px; margin: 2px; display: inline-block;">Semis</span>';
                    $exam_badges[] = '<span style="background: #38f9d7; color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 12px; margin: 2px; display: inline-block;">Finals</span>';
                } else {
                    // Check payment descriptions first
                    if (!empty($payment_descriptions)) {
                        if (stripos($payment_descriptions, 'Prelim') !== false) {
                            $exam_badges[] = '<span style="background: #667eea; color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 12px; margin: 2px; display: inline-block;">Prelims</span>';
                        }
                        if (stripos($payment_descriptions, 'Midterm') !== false) {
                            $exam_badges[] = '<span style="background: #f5576c; color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 12px; margin: 2px; display: inline-block;">Midterm</span>';
                        }
                        if (stripos($payment_descriptions, 'Semi') !== false || stripos($payment_descriptions, 'Semis') !== false) {
                            $exam_badges[] = '<span style="background: #00f2fe; color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 12px; margin: 2px; display: inline-block;">Semis</span>';
                        }
                        if (stripos($payment_descriptions, 'Final') !== false) {
                            $exam_badges[] = '<span style="background: #38f9d7; color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 12px; margin: 2px; display: inline-block;">Finals</span>';
                        }
                    }
                    
                    // Fallback to notes field if no descriptions found
                    if (empty($exam_badges)) {
                        $notes_lower = strtolower($notes);
                        if (strpos($notes_lower, 'prelim') !== false) {
                            $exam_badges[] = '<span style="background: #667eea; color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 12px;">Prelims</span>';
                        } elseif (strpos($notes_lower, 'midterm') !== false) {
                            $exam_badges[] = '<span style="background: #f5576c; color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 12px;">Midterm</span>';
                        } elseif (strpos($notes_lower, 'semi') !== false) {
                            $exam_badges[] = '<span style="background: #00f2fe; color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 12px;">Semis</span>';
                        } elseif (strpos($notes_lower, 'final') !== false) {
                            $exam_badges[] = '<span style="background: #38f9d7; color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 12px;">Finals</span>';
                        } else {
                            $exam_badges[] = '<span style="background: #95a5a6; color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 12px;">General</span>';
                        }
                    }
                }
                
                $exam_period = implode(' ', $exam_badges);
              ?>
              <tr>
                <td><?= htmlspecialchars($record['school_id'] ?? '-'); ?></td>
                <td><?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                <td><?= htmlspecialchars($record['course']); ?></td>
                <td><?= $exam_period ?></td>
                <td><?= htmlspecialchars($record['total_units'] ?? '0'); ?></td>
                <td><strong>₱<?= number_format($displayed_amount, 2); ?></strong></td>
                <td><?= date('M d, Y h:i A', strtotime($record['generated_at'])); ?></td>
                <td>
                  <button class="view-receipt-btn" onclick="openTuitionReceiptModal(<?= $record['tuition_id']; ?>)">
                    <i class="fa-solid fa-receipt"></i> Receipt
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

</div>

<!-- ============================
      RECEIPT MODAL
============================ -->
<div id="receiptModal" class="receipt-modal">
    <div class="receipt-content">

        <span class="close" onclick="closeReceiptModal()">&times;</span>

        <div class="receipt-header">
            <h2>OFFICIAL PAYMENT RECEIPT</h2>
            <p>TESDA Training Center</p>
            <hr>
        </div>

        <div class="receipt-body">

            <h3>Student Information</h3>
            <p><strong>Name:</strong> <span id="r_name"></span></p>
            <p><strong>Student ID:</strong> <span id="r_sid"></span></p>
            <p><strong>Course:</strong> <span id="r_course"></span></p>
            <p><strong>Year Level:</strong> <span id="r_year"></span></p>
            <p><strong>Semester:</strong> <span id="r_sem"></span></p>

            <hr>

            <h3>Payment Information</h3>
            <p><strong>Receipt No:</strong> <span id="r_receipt"></span></p>
            <p><strong>Description:</strong> <span id="r_desc"></span></p>
            <p><strong>Amount Paid:</strong> ₱ <span id="r_paid"></span></p>
            <p><strong>Status:</strong> <span id="r_status"></span></p>
            <p><strong>Date Paid:</strong> <span id="r_date"></span></p>

            <hr>

            <p><strong>Processed By:</strong> System Administrator</p>
        </div>

        <div class="receipt-footer">
            <button onclick="window.print()">Print Receipt</button>
        </div>

    </div>
</div>

<script>
function openTuitionReceiptModal(tuition_id) {
    // Fetch tuition record data for receipt
    fetch("fetch_tuition_receipt.php?tuition_id=" + tuition_id)
        .then(res => res.json())
        .then(data => {
            document.getElementById("r_name").textContent = data.first_name + " " + data.last_name;
            document.getElementById("r_sid").textContent = data.school_id;
            document.getElementById("r_course").textContent = data.course;
            document.getElementById("r_year").textContent = data.year_level;
            document.getElementById("r_sem").textContent = data.semester;

            document.getElementById("r_receipt").textContent = "TUI-" + data.tuition_id;
            document.getElementById("r_desc").textContent = "Tuition Fee Payment (" + data.total_units + " units)";
            document.getElementById("r_paid").textContent = parseFloat(data.total_amount).toLocaleString();
            document.getElementById("r_status").textContent = "Paid";
            document.getElementById("r_date").textContent = data.generated_at;

            document.getElementById("receiptModal").style.display = "flex";
        })
        .catch(err => {
            console.error("Error fetching receipt:", err);
            alert("Unable to load receipt. Please try again.");
        });
}

function openReceiptModal(receipt_no) {
    fetch("fetch_receipt.php?receipt=" + receipt_no)
        .then(res => res.json())
        .then(data => {

            document.getElementById("r_name").textContent = data.first_name + " " + data.last_name;
            document.getElementById("r_sid").textContent = data.student_id;
            document.getElementById("r_course").textContent = data.course;
            document.getElementById("r_year").textContent = data.year_level;
            document.getElementById("r_sem").textContent = data.semester;

            document.getElementById("r_receipt").textContent = data.receipt_number || data.receipt_no;
            document.getElementById("r_desc").textContent = data.description || data.payment_type || 'Payment';
            document.getElementById("r_paid").textContent = parseFloat(data.amount_paid).toLocaleString();
            document.getElementById("r_status").textContent = data.status || 'Paid';
            document.getElementById("r_date").textContent = data.payment_date;

            document.getElementById("receiptModal").style.display = "flex";
        });
}

function closeReceiptModal() {
    document.getElementById("receiptModal").style.display = "none";
}
</script>

<script>
function exportPayment() {
    // Select the table container
    var element = document.getElementById('paymentTable');

    // PDF options
    var opt = {
        margin:       0.5,
        filename:     'Payment_History.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2 },
        jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
    };

    // Generate PDF
    html2pdf().set(opt).from(element).save();
}
</script>


</body>
</html>
