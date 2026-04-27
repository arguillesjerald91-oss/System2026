<?php
include 'header.php';
include 'sidebar.php';
session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

// Get the tuition fee record
if (!isset($_GET['id'])) {
  header("Location: payments_record.php");
  exit;
}

$id = $_GET['id'];
$stmt = $conn->prepare("SELECT * FROM tuition_fees WHERE id = ?");
$stmt->execute([$id]);
$tuition = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tuition) {
  header("Location: payments_record.php?notfound=1");
  exit;
}

/* === Update tuition === */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_tuition'])) {
  $course = $_POST['course'];
  $semester = $_POST['semester'];
  $year_level = $_POST['year_level'];
  $total_fee = $_POST['total_fee'];

  $update = $conn->prepare("UPDATE tuition_fees SET course=?, semester=?, year_level=?, total_fee=? WHERE id=?");
  $update->execute([$course, $semester, $year_level, $total_fee, $id]);

  header("Location: payments_record.php?tuition_updated=1");
  exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Tuition Fee</title>
  <link rel="stylesheet" href="css/payments.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
<div class="main-content">
  <div class="header">
    <h2><i class="fa-solid fa-pen"></i> Edit Tuition Fee</h2>
  </div>

  <div class="table-container">
    <form method="POST">
      <div class="form-group">
        <label>Course</label>
        <select name="course" required>
          <?php
          $courses = ["BSIT", "BSBA", "BEED", "BSED", "BSHM", "BSCRIM"];
          foreach ($courses as $c) {
            $selected = $tuition['course'] == $c ? 'selected' : '';
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
            $selected = $tuition['semester'] == $s ? 'selected' : '';
            echo "<option value='$s' $selected>$s</option>";
          }
          ?>
        </select>
      </div>

      <div class="form-group">
        <label>Year Level</label>
        <select name="year_level" required>
          <?php
          $years = ["1st Year", "2nd Year", "3rd Year", "4th Year"];
          foreach ($years as $y) {
            $selected = $tuition['year_level'] == $y ? 'selected' : '';
            echo "<option value='$y' $selected>$y</option>";
          }
          ?>
        </select>
      </div>

      <div class="form-group">
        <label>Total Tuition Fee</label>
        <input type="number" step="0.01" name="total_fee" value="<?= htmlspecialchars($tuition['total_fee']); ?>" required>
      </div>

      <button type="submit" name="update_tuition" class="btn-add">Update Tuition</button>
      <a href="payments_record.php" class="btn-add" style="background:#aaa;">Cancel</a>
    </form>
  </div>
</div>
</body>
</html>
