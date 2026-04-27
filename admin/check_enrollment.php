<?php
include 'db.php';
$database = new Database();
$conn = $database->getConnection();

echo "<h2>Enrollment Table Contents:</h2>";
$stmt = $conn->query("SELECT * FROM enrollment LIMIT 10");
$enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($enrollments);
echo "</pre>";

echo "<h2>Enrollment Table Columns:</h2>";
$cols = $conn->query("DESCRIBE enrollment")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($cols);
echo "</pre>";

// Check if schedule_id values exist in schedules table
echo "<h2>Schedule ID Validation:</h2>";
if (!empty($enrollments)) {
    foreach ($enrollments as $e) {
        $schedId = $e['schedule_id'] ?? 'N/A';
        $checkSql = "SELECT schedule_id, subject_id FROM schedules WHERE schedule_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([$schedId]);
        $schedRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($schedRow) {
            echo "✅ Schedule ID {$schedId} EXISTS (subject_id: {$schedRow['subject_id']})<br>";
            
            // Check if subject exists
            $subCheck = $conn->prepare("SELECT SubjectID, SubCode, SubName FROM subject WHERE SubjectID = ?");
            $subCheck->execute([$schedRow['subject_id']]);
            $subRow = $subCheck->fetch(PDO::FETCH_ASSOC);
            if ($subRow) {
                echo "&nbsp;&nbsp;&nbsp;↳ Subject: {$subRow['SubCode']} - {$subRow['SubName']}<br>";
            } else {
                echo "&nbsp;&nbsp;&nbsp;❌ Subject ID {$schedRow['subject_id']} NOT FOUND<br>";
            }
        } else {
            echo "❌ Schedule ID {$schedId} NOT FOUND in schedules table<br>";
        }
    }
}

// Check if StudID values exist in student table
echo "<h2>Student ID Validation:</h2>";
if (!empty($enrollments)) {
    foreach ($enrollments as $e) {
        $studId = $e['StudID'] ?? 'N/A';
        $checkSql = "SELECT StudID, FirstName, LastName FROM student WHERE StudID = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([$studId]);
        $studRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($studRow) {
            echo "✅ StudID {$studId} EXISTS ({$studRow['FirstName']} {$studRow['LastName']})<br>";
        } else {
            echo "❌ StudID {$studId} NOT FOUND in student table<br>";
        }
    }
}

echo "<h2>JOIN Query Test (courses.php query):</h2>";
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

try {
    $result = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    echo "<p><strong>Total Records: " . count($result) . "</strong></p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<h2>Sample Schedules:</h2>";
$schedSample = $conn->query("SELECT schedule_id, subject_id, semester, academic_year FROM schedules LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($schedSample);
echo "</pre>";
?>
