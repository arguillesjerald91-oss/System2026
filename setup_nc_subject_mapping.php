<?php
/**
 * Create NC Level to Subject Mapping System
 * This creates a table that maps learning modules to NC levels
 * and allows staff to manage which subjects are available for each NC level
 */

include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== Setting up NC Level Subject Mapping System ===\n\n";

// 1. Create nc_level_subjects table
$conn->exec("DROP TABLE IF EXISTS nc_level_subjects");
$conn->exec("CREATE TABLE nc_level_subjects (
    mapping_id INT AUTO_INCREMENT PRIMARY KEY,
    nc_level VARCHAR(10) NOT NULL COMMENT 'NC I, NC II, NC III, NC IV',
    module_id INT NOT NULL,
    is_required BOOLEAN DEFAULT FALSE COMMENT 'Whether this subject is required for the NC level',
    sort_order INT DEFAULT 0 COMMENT 'Display order for subjects',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
    created_by INT COMMENT 'Staff user who added this mapping',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
    FOREIGN KEY (module_id) REFERENCES learning_modules(module_id) ON DELETE CASCADE,
    UNIQUE KEY unique_nc_module (nc_level, module_id)
) ENGINE=InnoDB");

echo "Created nc_level_subjects table\n";

// 2. Create nc_level_program_subjects table to link programs to their required subjects
$conn->exec("DROP TABLE IF EXISTS nc_level_program_subjects");
$conn->exec("CREATE TABLE nc_level_program_subjects (
    program_subject_id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    nc_level VARCHAR(10) NOT NULL,
    module_id INT NOT NULL,
    is_core_subject BOOLEAN DEFAULT TRUE COMMENT 'Core vs elective subject',
    sequence_order INT DEFAULT 1 COMMENT 'Order in which subjects should be taken',
    prerequisite_module_id INT NULL COMMENT 'Prerequisite module if any',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
    created_by INT,
    FOREIGN KEY (program_id) REFERENCES auto_mechanic_programs(program_id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES learning_modules(module_id) ON DELETE CASCADE,
    FOREIGN KEY (prerequisite_module_id) REFERENCES learning_modules(module_id) ON DELETE SET NULL,
    UNIQUE KEY unique_program_module (program_id, module_id)
) ENGINE=InnoDB");

echo "Created nc_level_program_subjects table\n";

// 3. Get existing learning modules and map them to NC levels
$stmt = $conn->query("SELECT module_id, module_title, nc_level FROM learning_modules WHERE is_active = 1 ORDER BY module_id");
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\nMapping existing modules to NC levels...\n";

foreach ($modules as $module) {
    $moduleId = $module['module_id'];
    $currentNcLevel = $module['nc_level'] ?? 'NC I';
    
    // Add to nc_level_subjects
    $stmt = $conn->prepare("INSERT IGNORE INTO nc_level_subjects (nc_level, module_id, is_required, sort_order) VALUES (?, ?, TRUE, ?)");
    $stmt->execute([$currentNcLevel, $moduleId, $moduleId]);
    
    // Find corresponding program and add to program subjects
    $stmt = $conn->prepare("SELECT program_id FROM auto_mechanic_programs WHERE program_level = ?");
    $stmt->execute([$currentNcLevel]);
    $program = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($program) {
        $programId = $program['program_id'];
        $stmt = $conn->prepare("INSERT IGNORE INTO nc_level_program_subjects (program_id, nc_level, module_id, sequence_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([$programId, $currentNcLevel, $moduleId, $moduleId]);
        echo "  - Mapped module '{$module['module_title']}' to $currentNcLevel (Program ID: $programId)\n";
    }
}

// 4. Create sample subjects for each NC level if no modules exist
if (empty($modules)) {
    echo "\nNo existing modules found. Creating sample subjects for each NC level...\n";
    
    $sampleSubjects = [
        'NC I' => [
            'Basic Automotive Tools and Equipment',
            'Work Safety in Automotive Shop',
            'Basic Engine Components',
            'Preventive Maintenance Procedures',
            'Basic Electrical Systems'
        ],
        'NC II' => [
            'Engine Repair and Overhaul',
            'Fuel System Diagnosis',
            'Ignition Systems',
            'Braking Systems',
            'Suspension and Steering'
        ],
        'NC III' => [
            'Engine Management Systems',
            'Electronic Fuel Injection',
            'Automotive Electronics',
            'Diagnostic Scan Tools',
            'Emission Control Systems'
        ],
        'NC IV' => [
            'Hybrid Vehicle Systems',
            'Advanced Engine Diagnostics',
            'Automotive Computer Systems',
            'Performance Tuning',
            'Master Technician Assessment'
        ]
    ];
    
    foreach ($sampleSubjects as $ncLevel => $subjects) {
        // Get program ID
        $stmt = $conn->prepare("SELECT program_id FROM auto_mechanic_programs WHERE program_level = ?");
        $stmt->execute([$ncLevel]);
        $program = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($program) {
            $programId = $program['program_id'];
            
            foreach ($subjects as $index => $subjectTitle) {
                // Create learning module
                $stmt = $conn->prepare("INSERT INTO learning_modules (module_title, module_description, module_type, duration_mins, is_active, nc_level, sort_order) VALUES (?, ?, 'Theory Subject', 120, 1, ?, ?)");
                $stmt->execute([$subjectTitle, "Core subject for $ncLevel level", $ncLevel, $index + 1]);
                $moduleId = $conn->lastInsertId();
                
                // Map to NC level
                $stmt = $conn->prepare("INSERT INTO nc_level_subjects (nc_level, module_id, is_required, sort_order) VALUES (?, ?, TRUE, ?)");
                $stmt->execute([$ncLevel, $moduleId, $index + 1]);
                
                // Map to program
                $stmt = $conn->prepare("INSERT INTO nc_level_program_subjects (program_id, nc_level, module_id, sequence_order) VALUES (?, ?, ?, ?)");
                $stmt->execute([$programId, $ncLevel, $moduleId, $index + 1]);
                
                echo "  - Created subject: '$subjectTitle' for $ncLevel\n";
            }
        }
    }
}

// 5. Update student_program_enrollments to include NC level properly
$conn->exec("ALTER TABLE student_program_enrollments ADD COLUMN IF NOT EXISTS nc_level VARCHAR(10) DEFAULT 'NC I'");
$conn->exec("ALTER TABLE student_program_enrollments ADD COLUMN IF NOT EXISTS program_id INT DEFAULT NULL");

// Update existing enrollments with proper NC level and program_id
$stmt = $conn->query("SELECT spe.enrollment_id, spe.student_id, s.user_id, pe.nc_level FROM student_program_enrollments spe JOIN student s ON spe.student_id = s.StudID JOIN pre_enrollment_applications pe ON spe.pre_enroll_id = pe.pre_enroll_id");
$enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($enrollments as $enrollment) {
    $ncLevel = $enrollment['nc_level'] ?? 'NC I';
    
    // Get program_id for this NC level
    $stmt = $conn->prepare("SELECT program_id FROM auto_mechanic_programs WHERE program_level = ?");
    $stmt->execute([$ncLevel]);
    $program = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($program) {
        $stmt = $conn->prepare("UPDATE student_program_enrollments SET nc_level = ?, program_id = ? WHERE enrollment_id = ?");
        $stmt->execute([$ncLevel, $program['program_id'], $enrollment['enrollment_id']]);
        echo "  - Updated enrollment {$enrollment['enrollment_id']} to $ncLevel (Program ID: {$program['program_id']})\n";
    }
}

echo "\n=== NC Level Subject Mapping System Setup Complete ===\n";

// Verify the setup
echo "\n=== Verification ===\n";

$stmt = $conn->query("SELECT nc_level, COUNT(*) as subject_count FROM nc_level_subjects GROUP BY nc_level ORDER BY nc_level");
echo "\nSubjects per NC level:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "- {$row['nc_level']}: {$row['subject_count']} subjects\n";
}

$stmt = $conn->query("SELECT ap.program_level, COUNT(spe.enrollment_id) as enrolled_count FROM auto_mechanic_programs ap LEFT JOIN student_program_enrollments spe ON ap.program_id = spe.program_id GROUP BY ap.program_level ORDER BY ap.program_level");
echo "\nEnrolled trainees per NC level:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "- {$row['program_level']}: {$row['enrolled_count']} trainees\n";
}

echo "\nSystem is ready for NC level-based subject access control!\n";
?>
