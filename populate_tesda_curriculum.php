<?php
/**
 * TESDA Automotive Curriculum Database Population Script
 * Populates all competencies, modules, quizzes, assignments, and assessments
 * for NC I, NC II, NC III, and NC IV
 */

include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== POPULATING TESDA AUTOMOTIVE CURRICULUM ===\n\n";

$count = [
    'modules' => 0,
    'quizzes' => 0,
    'assignments' => 0,
    'contents' => 0,
    'mappings' => 0
];

// ============== NC I ==============
echo "=== Processing NC I ===\n";

// BASIC COMPETENCIES - NC I
$nc1_basic = [
    ['title' => 'Workplace Communication', 'type' => 'Theory', 'duration' => 120, 'competency' => 'Apply/interprete communication skills in the workplace'],
    ['title' => 'Work with Others', 'type' => 'Theory', 'duration' => 120, 'competency' => 'Work effectively with co-workers and supervisors'],
    ['title' => 'Work Values', 'type' => 'Theory', 'duration' => 120, 'competency' => 'Apply positive work values and ethics'],
    ['title' => 'Housekeeping Procedures', 'type' => 'Theory', 'duration' => 120, 'competency' => 'Apply housekeeping procedures']
];

// COMMON COMPETENCIES - NC I
$nc1_common = [
    ['title' => 'Use Tools & Workshop Equipment', 'type' => 'Practical', 'duration' => 280, 'competency' => 'Identify and use basic hand tools'],
    ['title' => 'Read Manuals/Specifications', 'type' => 'Theory', 'duration' => 120, 'competency' => 'Read and interpret simple technical manuals'],
    ['title' => 'Perform Measurements', 'type' => 'Practical', 'duration' => 160, 'competency' => 'Perform basic measurements using appropriate instruments'],
    ['title' => 'Prepare Vehicle for Servicing', 'type' => 'Practical', 'duration' => 120, 'competency' => 'Prepare vehicle for inspection and service'],
    ['title' => 'Shop Maintenance', 'type' => 'Practical', 'duration' => 120, 'competency' => 'Perform basic shop maintenance tasks']
];

// CORE COMPETENCIES - NC I
$nc1_core = [
    ['title' => 'Diesel Engine Tune-Up', 'type' => 'Practical', 'duration' => 300, 'competency' => 'Perform basic diesel engine tune-up procedures'],
    ['title' => 'Gas Engine Tune-Up', 'type' => 'Practical', 'duration' => 260, 'competency' => 'Perform basic gasoline engine tune-up'],
    ['title' => 'Remove/Replace Electrical Components', 'type' => 'Practical', 'duration' => 220, 'competency' => 'Remove and replace simple electrical components'],
    ['title' => 'Remove Engine System Parts', 'type' => 'Practical', 'duration' => 220, 'competency' => 'Remove engine system components for service'],
    ['title' => 'Remove Steering, Suspension, Brake Components', 'type' => 'Practical', 'duration' => 300, 'competency' => 'Remove steering, suspension, and brake components'],
    ['title' => 'Remove Transmission Components', 'type' => 'Practical', 'duration' => 220, 'competency' => 'Remove transmission system components']
];

// ============== NC II ==============
echo "=== Processing NC II ===\n";

// BASIC COMPETENCIES - NC II
$nc2_basic = [
    ['title' => 'Workplace Communication (Advanced)', 'type' => 'Theory', 'duration' => 160, 'competency' => 'Communicate effectively in the workplace'],
    ['title' => 'Teamwork', 'type' => 'Theory', 'duration' => 140, 'competency' => 'Work effectively in a team'],
    ['title' => 'Professionalism', 'type' => 'Theory', 'duration' => 160, 'competency' => 'Demonstrate professional conduct'],
    ['title' => 'Occupational Safety', 'type' => 'Theory', 'duration' => 160, 'competency' => 'Apply occupational safety practices']
];

// COMMON COMPETENCIES - NC II
$nc2_common = [
    ['title' => 'Use Lubricants/Coolants', 'type' => 'Practical', 'duration' => 220, 'competency' => 'Select and apply lubricants and coolants'],
    ['title' => 'Read Manuals & Technical Drawings', 'type' => 'Theory', 'duration' => 220, 'competency' => 'Interpret technical manuals and drawings'],
    ['title' => 'Perform Job Estimates', 'type' => 'Theory', 'duration' => 200, 'competency' => 'Estimate repair time and costs'],
    ['title' => 'Maintain Quality Systems', 'type' => 'Theory', 'duration' => 180, 'competency' => 'Apply quality management in repairs'],
    ['title' => 'Identify Automotive Parts', 'type' => 'Theory', 'duration' => 200, 'competency' => 'Identify correct automotive parts and specifications']
];

// CORE COMPETENCIES - NC II
$nc2_core = [
    ['title' => 'Battery Servicing', 'type' => 'Practical', 'duration' => 300, 'competency' => 'Service automotive batteries'],
    ['title' => 'Ignition System', 'type' => 'Practical', 'duration' => 320, 'competency' => 'Diagnose and repair ignition systems'],
    ['title' => 'Wiring & Lighting Repair', 'type' => 'Practical', 'duration' => 300, 'competency' => 'Repair vehicle wiring and lighting systems'],
    ['title' => 'Starting & Charging System', 'type' => 'Practical', 'duration' => 320, 'competency' => 'Service starting and charging systems'],
    ['title' => 'Engine Mechanical System', 'type' => 'Practical', 'duration' => 300, 'competency' => 'Perform engine mechanical repairs'],
    ['title' => 'Clutch System', 'type' => 'Practical', 'duration' => 260, 'competency' => 'Service clutch systems'],
    ['title' => 'Differential & Axle', 'type' => 'Practical', 'duration' => 280, 'competency' => 'Service differential and axle assemblies'],
    ['title' => 'Steering System', 'type' => 'Practical', 'duration' => 320, 'competency' => 'Service steering systems'],
    ['title' => 'Brake System', 'type' => 'Practical', 'duration' => 320, 'competency' => 'Service brake systems'],
    ['title' => 'Suspension System', 'type' => 'Practical', 'duration' => 300, 'competency' => 'Service suspension systems'],
    ['title' => 'Underchassis Preventive Maintenance', 'type' => 'Practical', 'duration' => 280, 'competency' => 'Perform preventive maintenance on underchassis'],
    ['title' => 'Manual Transmission Overhaul', 'type' => 'Practical', 'duration' => 320, 'competency' => 'Overhaul manual transmissions']
];

// ============== NC III ==============
echo "=== Processing NC III ===\n";

// BASIC COMPETENCIES - NC III
$nc3_basic = [
    ['title' => 'Lead Workplace Communication', 'type' => 'Theory', 'duration' => 160, 'competency' => 'Lead communication in the workplace'],
    ['title' => 'Lead Teams', 'type' => 'Theory', 'duration' => 180, 'competency' => 'Lead and supervise teams'],
    ['title' => 'Problem-Solving', 'type' => 'Theory', 'duration' => 160, 'competency' => 'Apply advanced problem-solving techniques'],
    ['title' => 'Use Math & Technology', 'type' => 'Theory', 'duration' => 160, 'competency' => 'Apply mathematical and technical knowledge']
];

// COMMON COMPETENCIES - NC III
$nc3_common = [
    ['title' => 'Advanced Interpretation of Manuals', 'type' => 'Theory', 'duration' => 160, 'competency' => 'Interpret complex technical manuals'],
    ['title' => 'Measurements and Technical Work', 'type' => 'Practical', 'duration' => 240, 'competency' => 'Perform precise technical measurements']
];

// CORE COMPETENCIES - NC III
$nc3_core = [
    ['title' => 'Automatic Transmission Systems', 'type' => 'Practical', 'duration' => 440, 'competency' => 'Diagnose and repair automatic transmissions'],
    ['title' => 'Engine Management Systems (ECU)', 'type' => 'Practical', 'duration' => 400, 'competency' => 'Service engine management systems'],
    ['title' => 'Steering & Suspension Advanced Repair', 'type' => 'Practical', 'duration' => 360, 'competency' => 'Perform advanced steering and suspension repairs'],
    ['title' => 'LPG System Installation/Service', 'type' => 'Practical', 'duration' => 340, 'competency' => 'Install and service LPG systems'],
    ['title' => 'Engine Replacement (Repowering)', 'type' => 'Practical', 'duration' => 340, 'competency' => 'Perform engine replacement and repowering'],
    ['title' => 'Electronic Control Systems & Security Systems', 'type' => 'Practical', 'duration' => 360, 'competency' => 'Service electronic control and security systems'],
    ['title' => 'Advanced Diagnostics and Troubleshooting', 'type' => 'Practical', 'duration' => 400, 'competency' => 'Perform advanced diagnostics and troubleshooting']
];

// ============== NC IV ==============
echo "=== Processing NC IV ===\n";

// BASIC COMPETENCIES - NC IV
$nc4_basic = [
    ['title' => 'Workshop Leadership', 'type' => 'Theory', 'duration' => 220, 'competency' => 'Lead workshop operations'],
    ['title' => 'Technical Training', 'type' => 'Theory', 'duration' => 160, 'competency' => 'Train junior technicians']
];

// COMMON COMPETENCIES - NC IV
$nc4_common = [
    ['title' => 'Quality Control & Inspection Systems', 'type' => 'Theory', 'duration' => 200, 'competency' => 'Implement quality control systems'],
    ['title' => 'Business Operations', 'type' => 'Theory', 'duration' => 180, 'competency' => 'Manage business operations']
];

// CORE COMPETENCIES - NC IV
$nc4_core = [
    ['title' => 'Hybrid/Electric Vehicle Systems', 'type' => 'Practical', 'duration' => 500, 'competency' => 'Service hybrid and electric vehicle systems'],
    ['title' => 'AI-Based Diagnostics', 'type' => 'Practical', 'duration' => 300, 'competency' => 'Apply AI-based diagnostic systems'],
    ['title' => 'CAN Bus Systems', 'type' => 'Practical', 'duration' => 320, 'competency' => 'Diagnose and repair CAN bus systems'],
    ['title' => 'Advanced ECU Programming', 'type' => 'Practical', 'duration' => 320, 'competency' => 'Perform advanced ECU programming'],
    ['title' => 'Shop Management & Supervision', 'type' => 'Practical', 'duration' => 280, 'competency' => 'Manage and supervise automotive shop']
];

function insertModule($conn, $ncLevel, $category, $title, $type, $duration, $competency, &$count) {
    $moduleTitle = "$title ($category)";
    $moduleDesc = "Competency: $competency. This module covers the required knowledge and skills for TESDA $ncLevel automotive servicing.";
    
    $stmt = $conn->prepare("INSERT INTO learning_modules (module_title, module_description, module_type, duration_mins, nc_level, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
    $stmt->execute([$moduleTitle, $moduleDesc, $type, $duration, $ncLevel]);
    
    $moduleId = $conn->lastInsertId();
    $count['modules']++;
    
    // Add module content (basic content for each module)
    $contentTitle = "$title - Lesson 1: Introduction";
    $contentStmt = $conn->prepare("INSERT INTO module_contents (module_id, title, description, content_type, duration_mins, is_published, nc_level, created_at) VALUES (?, ?, ?, 'Theory', ?, 1, ?, NOW())");
    $contentStmt->execute([$moduleId, $contentTitle, "Introduction to $title", $duration, $ncLevel]);
    $count['contents']++;
    
    // Add quiz for this module
    $quizTitle = "Quiz: $title";
    $quizDesc = "Assessment quiz for $title";
    $quizStmt = $conn->prepare("INSERT INTO quizzes (module_id, title, description, time_limit, passing_score, nc_level, is_active, created_at) VALUES (?, ?, ?, 30, 70, ?, 1, NOW())");
    $quizStmt->execute([$moduleId, $quizTitle, $quizDesc, $ncLevel]);
    $count['quizzes']++;
    
    // Add assignment for this module
    $assignTitle = "Assignment: $title";
    $assignDesc = "Practical assignment for $title";
    $assignStmt = $conn->prepare("INSERT INTO assignments (module_id, title, description, due_date, max_score, nc_level, created_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), 100, ?, NOW())");
    $assignStmt->execute([$moduleId, $assignTitle, $assignDesc, $ncLevel]);
    $count['assignments']++;
    
    // Map to NC level in nc_level_subjects
    $mappingStmt = $conn->prepare("INSERT INTO nc_level_subjects (nc_level, module_id, is_required, sort_order, created_by) VALUES (?, ?, 1, ?, 1)");
    $mappingStmt->execute([$ncLevel, $moduleId, $count['modules']]);
    $count['mappings']++;
    
    return $moduleId;
}

echo "Inserting modules...\n";

// Process NC I
$order = 1;
foreach ($nc1_basic as $mod) {
    insertModule($conn, 'NC I', 'Basic', $mod['title'], $mod['type'], $mod['duration'], $mod['competency'], $count);
}
foreach ($nc1_common as $mod) {
    insertModule($conn, 'NC I', 'Common', $mod['title'], $mod['type'], $mod['duration'], $mod['competency'], $count);
}
foreach ($nc1_core as $mod) {
    insertModule($conn, 'NC I', 'Core', $mod['title'], $mod['type'], $mod['duration'], $mod['competency'], $count);
}

// Process NC II
foreach ($nc2_basic as $mod) {
    insertModule($conn, 'NC II', 'Basic', $mod['title'], $mod['type'], $mod['duration'], $mod['competency'], $count);
}
foreach ($nc2_common as $mod) {
    insertModule($conn, 'NC II', 'Common', $mod['title'], $mod['type'], $mod['duration'], $mod['competency'], $count);
}
foreach ($nc2_core as $mod) {
    insertModule($conn, 'NC II', 'Core', $mod['title'], $mod['type'], $mod['duration'], $mod['competency'], $count);
}

// Process NC III
foreach ($nc3_basic as $mod) {
    insertModule($conn, 'NC III', 'Basic', $mod['title'], $mod['type'], $mod['duration'], $mod['competency'], $count);
}
foreach ($nc3_common as $mod) {
    insertModule($conn, 'NC III', 'Common', $mod['title'], $mod['type'], $mod['duration'], $mod['competency'], $count);
}
foreach ($nc3_core as $mod) {
    insertModule($conn, 'NC III', 'Core', $mod['title'], $mod['type'], $mod['duration'], $mod['competency'], $count);
}

// Process NC IV
foreach ($nc4_basic as $mod) {
    insertModule($conn, 'NC IV', 'Basic', $mod['title'], $mod['type'], $mod['duration'], $mod['competency'], $count);
}
foreach ($nc4_common as $mod) {
    insertModule($conn, 'NC IV', 'Common', $mod['title'], $mod['type'], $mod['duration'], $mod['competency'], $count);
}
foreach ($nc4_core as $mod) {
    insertModule($conn, 'NC IV', 'Core', $mod['title'], $mod['type'], $mod['duration'], $mod['competency'], $count);
}

echo "\n=== SUMMARY ===\n";
echo "Modules created: {$count['modules']}\n";
echo "Module contents created: {$count['contents']}\n";
echo "Quizzes created: {$count['quizzes']}\n";
echo "Assignments created: {$count['assignments']}\n";
echo "NC Level mappings created: {$count['mappings']}\n";

// Verify counts by NC level
echo "\n=== VERIFICATION BY NC LEVEL ===\n";
foreach (['NC I', 'NC II', 'NC III', 'NC IV'] as $level) {
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM nc_level_subjects WHERE nc_level = ?");
    $stmt->execute([$level]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "$level: {$row['cnt']} subjects\n";
}

echo "\n=== DATABASE POPULATION COMPLETE ===\n";
?>