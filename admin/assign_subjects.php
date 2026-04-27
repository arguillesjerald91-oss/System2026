
<?php
// Start session only if not already active
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Include DB safely with __DIR__
include_once __DIR__ . '/db.php';
include_once __DIR__ . '/log_activity.php';

$database = new Database();
$conn = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_subjects'])) {
    $student_id = $_POST['student_id'] ?? null;
    $schedule_ids = $_POST['schedule_ids'] ?? [];

    if (!empty($student_id) && !empty($schedule_ids)) {
        $successCount = 0;
        $failCount = 0;
        $assignedSubjects = [];

        // Resolve StudID from SchoolID or StudID
        $idLookup = $conn->prepare("SELECT StudID, SchoolID FROM student WHERE SchoolID = ? OR StudID = ? LIMIT 1");
        $idLookup->execute([$student_id, $student_id]);
        $row = $idLookup->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            header("Location: courses.php?error=student_not_found");
            exit;
        }
        
        // Always use StudID for the enrollment
        $studID = $row['StudID'];

        // Insert into enrollment table
        foreach ($schedule_ids as $schedule_id) {
            try {
                // Check if already enrolled in this schedule
                $checkStmt = $conn->prepare("SELECT EnrollID FROM enrollment WHERE StudID = ? AND schedule_id = ?");
                $checkStmt->execute([$studID, $schedule_id]);
                
                if ($checkStmt->rowCount() == 0) {
                    // Insert new enrollment
                    $insertStmt = $conn->prepare("INSERT INTO enrollment (StudID, schedule_id, enrollment_date) VALUES (?, ?, NOW())");
                    $insertStmt->execute([$studID, $schedule_id]);
                    
                    if ($insertStmt->rowCount() > 0) {
                        $successCount++;
                        
                        // Get subject info for logging
                        $subjectStmt = $conn->prepare("SELECT subj.SubCode FROM schedules sch JOIN subject subj ON sch.subject_id = subj.SubjectID WHERE sch.schedule_id = ?");
                        $subjectStmt->execute([$schedule_id]);
                        $subjectInfo = $subjectStmt->fetch(PDO::FETCH_ASSOC);
                        if ($subjectInfo) {
                            $assignedSubjects[] = $subjectInfo['SubCode'];
                        }
                    }
                }
            } catch (PDOException $e) {
                error_log("Error enrolling student $studID in schedule $schedule_id: " . $e->getMessage());
                $failCount++;
            }
        }

        if ($successCount > 0) {
            $subjectList = implode(', ', $assignedSubjects);
            logActivity('Student Enrolled', "Enrolled student {$student_id} (StudID: {$studID}) in {$successCount} subject(s): {$subjectList}", $conn);
            header("Location: courses.php?success=assigned&count=" . $successCount);
        } elseif ($failCount > 0) {
            logActivity('Enrollment Failed', "Failed enrolling student {$student_id} in schedules. Selected IDs: " . implode(', ', $schedule_ids), $conn);
            header("Location: courses.php?error=assignment_failed");
        } else {
            // All duplicates
            logActivity('Enrollment Duplicate', "No new enrollments for student {$student_id} (already enrolled). Selected schedule IDs: " . implode(', ', $schedule_ids), $conn);
            header("Location: courses.php?success=assigned&count=0");
        }
        exit;
    } else {
        header("Location: courses.php?error=1");
        exit;
    }
}

// Redirect back if accessed directly without POST
header("Location: courses.php");
exit;
