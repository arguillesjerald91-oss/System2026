<?php
/**
 * Add NC Level columns to existing tables
 */

include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== Adding NC Level Columns ===\n\n";

// Check table structure
$stmt = $conn->query("DESCRIBE auto_mechanic_programs");
echo "Current auto_mechanic_programs structure:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  - " . $row['Field'] . ": " . $row['Type'] . "\n";
}

// Add missing columns
try { $conn->exec("ALTER TABLE auto_mechanic_programs ADD COLUMN duration_hours INT DEFAULT 280"); } catch (Exception $e) {}
try { $conn->exec("ALTER TABLE auto_mechanic_programs ADD COLUMN program_description TEXT"); } catch (Exception $e) {}

// Insert NC Level programs data
$programs = [
    ['AUTO-MECH-NC1', 'Automotive Servicing NC I', 'NC I', 'Entry-level automotive servicing and maintenance', 280],
    ['AUTO-MECH-NC2', 'Automotive Servicing NC II', 'NC II', 'Intermediate automotive repair and diagnostics', 640],
    ['AUTO-MECH-NC3', 'Automotive Servicing NC III', 'NC III', 'Advanced automotive systems repair', 640],
    ['AUTO-MECH-NC4', 'Automotive Servicing NC IV', 'NC IV', 'Master automotive technician certification', 480]
];

$stmt = $conn->prepare("INSERT IGNORE INTO auto_mechanic_programs (program_code, program_title, program_level, program_description, duration_hours) VALUES (?, ?, ?, ?, ?)");
foreach ($programs as $p) {
    try {
        $stmt->execute($p);
        echo "Inserted: " . $p[2] . "\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

// Add NC Level columns to other tables
$alterations = [
    "ALTER TABLE learning_modules ADD COLUMN nc_level VARCHAR(10) DEFAULT 'NC I'",
    "ALTER TABLE module_contents ADD COLUMN nc_level VARCHAR(10) DEFAULT 'NC I'",
    "ALTER TABLE quizzes ADD COLUMN nc_level VARCHAR(10) DEFAULT 'NC I'",
    "ALTER TABLE assignments ADD COLUMN nc_level VARCHAR(10) DEFAULT 'NC I'",
    "ALTER TABLE learning_materials ADD COLUMN nc_level VARCHAR(10) DEFAULT 'NC I'",
    "ALTER TABLE pre_enrollment_applications ADD COLUMN nc_level VARCHAR(10) DEFAULT 'NC I'"
];

foreach ($alterations as $sql) {
    try {
        $conn->exec($sql);
        preg_match('/ADD COLUMN (\w+)/', $sql, $m);
        echo "Added column: " . ($m[1] ?? 'unknown') . "\n";
    } catch (Exception $e) {
        // Column might already exist
    }
}

// Update existing modules with NC Levels
$conn->exec("UPDATE learning_modules SET nc_level = 'NC I' WHERE module_id IN (1,2,3)");
$conn->exec("UPDATE learning_modules SET nc_level = 'NC II' WHERE module_id IN (4,5)");

echo "\n=== Complete ===\n";
$stmt = $conn->query("SELECT program_id, program_level, program_title FROM auto_mechanic_programs");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['program_level'] . " - " . $row['program_title'] . "\n";
}
?>