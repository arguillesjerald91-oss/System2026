<?php
/**
 * Setup NC Level System
 * 1. Add NC Level columns to tables
 * 2. Create auto_mechanic_programs table with NC Levels
 * 3. Update learning_modules with NC Level
 * 4. Create student_enrollments table
 */

include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== Setting up NC Level System ===\n\n";

// 1. Create auto_mechanic_programs table if not exists
try { $conn->exec("DROP TABLE IF EXISTS auto_mechanic_programs"); } catch (Exception $e) { }
try { $conn->exec("DROP TABLE IF EXISTS student_program_enrollments"); } catch (Exception $e) { }
$conn->exec("CREATE TABLE auto_mechanic_programs (
    program_id INT AUTO_INCREMENT PRIMARY KEY,
    program_code VARCHAR(20) NOT NULL UNIQUE,
    program_title VARCHAR(255) NOT NULL,
    program_level VARCHAR(10) NOT NULL COMMENT 'NC I, NC II, NC III, NC IV',
    program_description TEXT,
    duration_hours INT DEFAULT 0,
    program_status ENUM('Active','Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP()
) ENGINE=InnoDB");

echo "Created auto_mechanic_programs table\n";

// Insert NC Level programs
$programs = [
    ['AUTO-MECH-NC1', 'Automotive Servicing NC I', 'NC I', 'Entry-level automotive servicing and maintenance', 280],
    ['AUTO-MECH-NC2', 'Automotive Servicing NC II', 'NC II', 'Intermediate automotive repair and diagnostics', 640],
    ['AUTO-MECH-NC3', 'Automotive Servicing NC III', 'NC III', 'Advanced automotive systems repair', 640],
    ['AUTO-MECH-NC4', 'Automotive Servicing NC IV', 'NC IV', 'Master automotive technician certification', 480]
];

$stmt = $conn->prepare("INSERT INTO auto_mechanic_programs (program_code, program_title, program_level, program_description, duration_hours) VALUES (?, ?, ?, ?, ?)");
foreach ($programs as $p) {
    $stmt->execute($p);
}
echo "Inserted 4 NC Level programs\n";

// 2. Add nc_level column to learning_modules
$conn->exec("ALTER TABLE learning_modules ADD COLUMN IF NOT EXISTS nc_level VARCHAR(10) DEFAULT 'NC I'");
echo "Added nc_level column to learning_modules\n";

// 3. Update existing modules with NC Levels
$conn->exec("UPDATE learning_modules SET nc_level = 'NC I' WHERE module_id IN (1,2,3)");
$conn->exec("UPDATE learning_modules SET nc_level = 'NC II' WHERE module_id IN (4,5)");
echo "Updated module NC Levels\n";

// 4. Add nc_level to module_contents
$conn->exec("ALTER TABLE module_contents ADD COLUMN IF NOT EXISTS nc_level VARCHAR(10) DEFAULT 'NC I'");
echo "Added nc_level column to module_contents\n";

// 5. Add nc_level to quizzes
$conn->exec("ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS nc_level VARCHAR(10) DEFAULT 'NC I'");
echo "Added nc_level column to quizzes\n";

// 6. Add nc_level to assignments
$conn->exec("ALTER TABLE assignments ADD COLUMN IF NOT EXISTS nc_level VARCHAR(10) DEFAULT 'NC I'");
echo "Added nc_level column to assignments\n";

// 7. Add nc_level to learning_materials
$conn->exec("ALTER TABLE learning_materials ADD COLUMN IF NOT EXISTS nc_level VARCHAR(10) DEFAULT 'NC I'");
echo "Added nc_level column to learning_materials\n";

// 8. Create student_program_enrollments table
$conn->exec("DROP TABLE IF EXISTS student_program_enrollments");
$conn->exec("CREATE TABLE student_program_enrollments (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    program_id INT NOT NULL,
    nc_level VARCHAR(10) NOT NULL,
    enrollment_date DATE NOT NULL,
    status ENUM('Active','Completed','Dropped','Suspended') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
    created_by INT,
    FOREIGN KEY (student_id) REFERENCES users(user_id),
    FOREIGN KEY (program_id) REFERENCES auto_mechanic_programs(program_id)
) ENGINE=InnoDB");
echo "Created student_program_enrollments table\n";

// 9. Add nc_level to pre_enrollment_applications
$conn->exec("ALTER TABLE pre_enrollment_applications ADD COLUMN IF NOT EXISTS nc_level VARCHAR(10) DEFAULT 'NC I'");
echo "Added nc_level column to pre_enrollment_applications\n";

echo "\n=== NC Level System Setup Complete ===\n";

// Verify
$stmt = $conn->query("SELECT * FROM auto_mechanic_programs");
echo "\nPrograms:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "- " . $row['program_level'] . ": " . $row['program_title'] . "\n";
}
?>