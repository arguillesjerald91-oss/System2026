<?php
/**
 * Calculate tuition based on enrolled subjects for a student
 * Fetches subjects from subjectenrollment table and calculates total units and cost
 */
session_start();
header('Content-Type: application/json');
include 'db.php';

$database = new Database();
$conn = $database->getConnection();

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if ($student_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid student ID']);
    exit;
}

try {
    // Use enrollment table joined to schedules and subject
    $sql = "SELECT 
                subj.SubjectID,
                subj.SubCode,
                subj.SubName,
                subj.Unit,
                subj.Cost,
                subj.type
            FROM enrollment e
            INNER JOIN schedules sch ON e.schedule_id = sch.schedule_id
            INNER JOIN subject subj ON sch.subject_id = subj.SubjectID
            WHERE e.StudID = ?
            ORDER BY subj.SubCode ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$student_id]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    $totalUnits = 0;
    $totalCost = 0;
    $subjectCount = count($subjects);
    
    // For each subject, calculate cost as: Units × Cost (rate per unit)
    foreach ($subjects as $subject) {
        $units = floatval($subject['Unit']);
        $ratePerUnit = floatval($subject['Cost']); // Cost field is the rate per unit
        $subjectTotalCost = $units * $ratePerUnit; // Total cost for this subject
        
        $totalUnits += $units;
        $totalCost += $subjectTotalCost;
    }
    
    // Calculate average rate per unit (weighted average across all subjects)
    $ratePerUnit = $totalUnits > 0 ? ($totalCost / $totalUnits) : 0;
    
    echo json_encode([
        'status' => 'success',
        'subject_count' => $subjectCount,
        'total_units' => $totalUnits,
        'total_cost' => $totalCost,
        'rate_per_unit' => round($ratePerUnit, 2),
        'subjects' => $subjects
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching student tuition calculation: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database error',
        'total_units' => 0,
        'total_cost' => 0,
        'rate_per_unit' => 0
    ]);
}
exit;
?>
