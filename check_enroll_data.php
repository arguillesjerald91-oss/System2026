<?php
include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== student_program_enrollments columns ===\n";
$stmt = $conn->query("DESCRIBE student_program_enrollments");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $r['Field'] . "\n";
}

echo "\n=== Check NC Level data ===\n";
$stmt = $conn->query("SELECT pre_enroll_id, nc_level, enrollment_status FROM student_program_enrollments");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$r['pre_enroll_id']}, NC: {$r['nc_level']}, Status: {$r['enrollment_status']}\n";
}

echo "\n=== Check nc_level column in pre_enrollment_applications ===\n";
$stmt = $conn->query("SELECT pre_enroll_id, nc_level, application_status FROM pre_enrollment_applications WHERE application_status = 'Enrolled' LIMIT 5");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$r['pre_enroll_id']}, NC: {$r['nc_level']}, Status: {$r['application_status']}\n";
}
?>