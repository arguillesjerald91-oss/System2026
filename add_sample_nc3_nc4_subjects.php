<?php
/**
 * Add sample subjects for NC III and NC IV levels
 * Complete the NC level system with sample content
 */

include 'db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== Adding Sample Subjects for NC III and NC IV ===\n\n";

// Sample subjects for NC III
$nc3Subjects = [
    ['Engine Management Systems', 'Advanced electronic engine control systems and diagnostics', 180],
    ['Electronic Fuel Injection', 'Modern fuel injection systems and troubleshooting', 160],
    ['Automotive Electronics', 'Advanced electronic components and circuitry', 200],
    ['Diagnostic Scan Tools', 'Professional diagnostic equipment usage', 120],
    ['Emission Control Systems', 'Environmental control systems and regulations', 140]
];

// Sample subjects for NC IV
$nc4Subjects = [
    ['Hybrid Vehicle Systems', 'Hybrid and electric vehicle technology', 240],
    ['Advanced Engine Diagnostics', 'Complex engine problem diagnosis', 200],
    ['Automotive Computer Systems', 'Vehicle computer networks and programming', 180],
    ['Performance Tuning', 'Engine performance optimization', 160],
    ['Master Technician Assessment', 'Final assessment and certification preparation', 120]
];

// Add NC III subjects
echo "Adding NC III subjects:\n";
$stmt = $conn->prepare("SELECT program_id FROM auto_mechanic_programs WHERE program_level = 'NC III'");
$stmt->execute();
$nc3Program = $stmt->fetch(PDO::FETCH_ASSOC);

if ($nc3Program) {
    foreach ($nc3Subjects as $index => $subject) {
        // Create learning module
        $stmt = $conn->prepare("INSERT INTO learning_modules (module_title, module_description, module_type, duration_mins, is_active, nc_level, sort_order) VALUES (?, ?, 'Theory Subject', ?, 1, 'NC III', ?)");
        $stmt->execute([$subject[0], $subject[1], $subject[2], $index + 1]);
        $moduleId = $conn->lastInsertId();
        
        // Map to NC level
        $stmt = $conn->prepare("INSERT INTO nc_level_subjects (nc_level, module_id, is_required, sort_order) VALUES ('NC III', ?, TRUE, ?)");
        $stmt->execute([$moduleId, $index + 1]);
        
        // Map to program
        $stmt = $conn->prepare("INSERT INTO nc_level_program_subjects (program_id, nc_level, module_id, sequence_order) VALUES (?, 'NC III', ?, ?)");
        $stmt->execute([$nc3Program['program_id'], $moduleId, $index + 1]);
        
        echo "  - Created: '{$subject[0]}' for NC III\n";
    }
}

// Add NC IV subjects
echo "\nAdding NC IV subjects:\n";
$stmt = $conn->prepare("SELECT program_id FROM auto_mechanic_programs WHERE program_level = 'NC IV'");
$stmt->execute();
$nc4Program = $stmt->fetch(PDO::FETCH_ASSOC);

if ($nc4Program) {
    foreach ($nc4Subjects as $index => $subject) {
        // Create learning module
        $stmt = $conn->prepare("INSERT INTO learning_modules (module_title, module_description, module_type, duration_mins, is_active, nc_level, sort_order) VALUES (?, ?, 'Theory Subject', ?, 1, 'NC IV', ?)");
        $stmt->execute([$subject[0], $subject[1], $subject[2], $index + 1]);
        $moduleId = $conn->lastInsertId();
        
        // Map to NC level
        $stmt = $conn->prepare("INSERT INTO nc_level_subjects (nc_level, module_id, is_required, sort_order) VALUES ('NC IV', ?, TRUE, ?)");
        $stmt->execute([$moduleId, $index + 1]);
        
        // Map to program
        $stmt = $conn->prepare("INSERT INTO nc_level_program_subjects (program_id, nc_level, module_id, sequence_order) VALUES (?, 'NC IV', ?, ?)");
        $stmt->execute([$nc4Program['program_id'], $moduleId, $index + 1]);
        
        echo "  - Created: '{$subject[0]}' for NC IV\n";
    }
}

echo "\n=== Sample Subjects Added Successfully ===\n";

// Verify the results
echo "\nUpdated subjects per NC level:\n";
$ncLevels = ['NC I', 'NC II', 'NC III', 'NC IV'];

foreach ($ncLevels as $ncLevel) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM nc_level_subjects
        WHERE nc_level = ?
    ");
    $stmt->execute([$ncLevel]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "  $ncLevel: $count subjects\n";
}

echo "\nThe NC level system is now complete with sample subjects for all levels!\n";
?>
