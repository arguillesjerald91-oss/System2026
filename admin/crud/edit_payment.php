<?php
include 'header.php';
include 'sidebar.php';
session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

// Get payment record
if (!isset($_GET['id'])) {
  header("Location: payments_record.php");
  exit;
}

$id = $_GET['id'];
$stmt = $conn->prepare("SELECT * FROM payments WHERE id = ?");
$stmt->execute([$id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
  header("Location: payments_record.php?notfound=1");
  exit;
}

/* === Update Payment === */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_payment'])) {
  $student_id = $_POST['student_id'];
  $course = $_POST['course'];
  $semester = $_POST['semester'];
  $amount_due = $_POST['amount_due'];
  $amount_paid = $_POST['amount_paid'];
  $status = $_POST['status'];

  $update = $conn->prepare("UPDATE payments SET student_id=?, course=?, semester=?, amount_due=?, amount_paid=?, status=? WHERE id=?");
  $update->execute([$student_id, $course, $semester, $amount_due, $amount_paid, $status, $id]);

  header("Location: payments_record.php?payment_updated=1");
  exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Payment</title>
  <link rel="stylesheet" href="css/payments.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
<div class="main-content">
  <div class="header">
    <h2><i class="fa-solid fa-pen"></i> Edit Payment Record</h2>
  </div>

  <div class="table-container">
    <form method="POST">
      <div class="form-group">
        <label>Student ID</label>
        <input type="text" name="student_id" value="<?= htmlspecialchars($payment['student_id']); ?>" required>
      </div>

      <div class="form-group">
        <label>Course</label>
        <select name="course" required>
          <?php
          $courses = ["BSIT", "BSBA", "BEED", "BSED", "BSHM", "BSCRIM"];
          foreach ($courses as $c) {
            $selected = $payment['course'] == $c ? 'selected' : '';
            echo "<option value='$c' $selected>$c</option>";
          }
          ?>
        </select>
      </div>

      <div class="form-group">
        <label>Semester</label>
        <select name="semester" required>
          <?php
          $semesters = ["1st Semester", "2nd Semester", "Summer"];
          foreach ($semesters as $s) {
            $selected = $payment['semester'] == $s ? 'selected' : '';
            echo "<option value='$s' $selected>$s</option>";
          }
          ?>
        </select>
      </div>

      <div class="form-group">
        <label>Amount Due</label>
        <input type="number" step="0.01" name="amount_due" value="<?= htmlspecialchars($payment['amount_due']); ?>" required>
      </div>

      <div class="form-group">
        <label>Amount Paid</label>
        <input type="number" step="0.01" name="amount_paid" value="<?= htmlspecialchars($payment['amount_paid']); ?>" required>
      </div>

      <div class="form-group">
        <label>Status</label>
        <select name="status" required>
          <option value="Unpaid" <?= $payment['status'] == 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
          <option value="Partially Paid" <?= $payment['status'] == 'Partially Paid' ? 'selected' : ''; ?>>Partially Paid</option>
          <option value="Paid" <?= $payment['status'] == 'Paid' ? 'selected' : ''; ?>>Paid</option>
        </select>
      </div>

      <button type="submit" name="update_payment" class="btn-add">Update Payment</button>
      <a href="payments_record.php" class="btn-add" style="background:#aaa;">Cancel</a>
    </form>
  </div>
</div>
</body>
</html>
