<?php
/**
 * Create/Verify Scholarship Tables
 */

include __DIR__ . '/db.php';

$db = new Database();
$conn = $db->getConnection();

echo "<h2>Setting up Scholarship Tables</h2>";

// Insert sample scholarship programs
try {
    $stmt = $conn->query("SELECT COUNT(*) FROM scholarship_programs");
    $count = $stmt->fetchColumn();
    if ($count == 0) {
        $conn->exec("INSERT INTO scholarship_programs (program_name, program_code, description, scholarship_amount, slots_available, application_deadline) VALUES
        ('TESDA Free Tuition Scholarship', 'TESDA-FREE', 'Full tuition coverage for qualified automotive mechanic students', 15000, 50, '2026-12-31'),
        ('Automotive Excellence Grant', 'AEG', 'Merit-based scholarship for top performing students', 10000, 25, '2026-12-31'),
        ('Single Parent Scholarship', 'SP-SCH', 'Special scholarship for single parents in automotive training', 8000, 15, '2026-12-31')");
        echo "<p style='color: green;'>✓ Sample programs added</p>";
    } else {
        echo "<p style='color: gray;'>Programs already exist</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: orange;'>Note: " . $e->getMessage() . "</p>";
}

echo "<h3 style='color: green;'>Done!</h3>";
echo "<p><a href='admin/scholarship_qualification.php'>Go to Scholarship Qualification</a></p>";