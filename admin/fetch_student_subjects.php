<?php
// This file handles fetching both enrolled and available subjects for a student

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/db.php';

$database = new Database();
$conn = $database->getConnection();

$student_id = $_GET['student_id'] ?? null;

if (!$student_id) {
    echo json_encode(['error' => 'No student selected', 'enrolled' => [], 'available' => []]);
    exit;
}

try {
    // First, resolve StudID from school_id or StudID
    $studentLookup = $conn->prepare("SELECT StudID, SchoolID, Course, YearLvl, Semester FROM student WHERE SchoolID = ? OR StudID = ? LIMIT 1");
    $studentLookup->execute([$student_id, $student_id]);
    $studentInfo = $studentLookup->fetch(PDO::FETCH_ASSOC);
    
    if (!$studentInfo) {
        echo json_encode(['error' => 'Student not found', 'enrolled' => [], 'available' => []]);
        exit;
    }
    
    $studID = $studentInfo['StudID'];
    $course = $studentInfo['Course'];
    $yearLevel = $studentInfo['YearLvl'];
    $semester = $studentInfo['Semester'];
    
    // Fetch ENROLLED subjects from enrollment table (via schedule_id -> schedules -> subject)
    $enrolledQuery = $conn->prepare("
        SELECT DISTINCT 
            subj.SubjectID as id, 
            subj.SubCode as course_code, 
            subj.SubName as title, 
            subj.Unit as units,
            sch.semester,
            sch.academic_year,
            e.EnrollID as enrollment_id
        FROM enrollment e
        JOIN schedules sch ON e.schedule_id = sch.schedule_id
        JOIN subject subj ON sch.subject_id = subj.SubjectID
        WHERE e.StudID = ?
        ORDER BY subj.SubCode
    ");
    $enrolledQuery->execute([$studID]);
    $enrolled = $enrolledQuery->fetchAll(PDO::FETCH_ASSOC);
    
    // Get already enrolled subject IDs for filtering
    $enrolledIds = array_column($enrolled, 'id');
    
    // Fetch AVAILABLE subjects (schedules that the student can enroll in)
    // Filter by year level and semester if available
    $availableQuery = $conn->prepare("
        SELECT DISTINCT
            subj.SubjectID as id, 
            subj.SubCode as course_code, 
            subj.SubName as title, 
            subj.Unit as units,
            subj.year_level,
            sch.schedule_id,
            sch.semester as schedule_semester,
            sch.academic_year
        FROM schedules sch
        JOIN subject subj ON sch.subject_id = subj.SubjectID
        WHERE subj.year_level = ?
        ORDER BY subj.SubCode
    ");
    $availableQuery->execute([$yearLevel]);
    $allAvailable = $availableQuery->fetchAll(PDO::FETCH_ASSOC);
    
    // Filter out already enrolled subjects
    $available = array_filter($allAvailable, function($subj) use ($enrolledIds) {
        return !in_array($subj['id'], $enrolledIds);
    });
    
    // Re-index array after filtering
    $available = array_values($available);
    
    echo json_encode([
        'enrolled' => $enrolled,
        'available' => $available,
        'student' => [
            'StudID' => $studID,
            'Course' => $course,
            'YearLevel' => $yearLevel,
            'Semester' => $semester
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching student subjects: " . $e->getMessage());
    echo json_encode(['error' => 'Database error', 'enrolled' => [], 'available' => []]);
}
?>
