<?php
// admin/get_students_integration.php
// Returns JSON array of student records limited to fields used by the application.
header('Content-Type: application/json; charset=utf-8');
// Allow CORS for integration (adjust in production)
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../db.php';

$database = new Database();
$conn = $database->getConnection();

try {
    $stmt = $conn->prepare("SELECT StudID, FirstName, LastName, EmailAddr, PhoneNo, YearLvl, Course, Semester, EnrollmentClass FROM student");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $r) {
        $semesterRaw = $r['Semester'] ?? '';
        $semesterLower = strtolower($semesterRaw);

        // Determine enrollment status and readable semester
        $isEnrolled = preg_match('/1st|first|2nd|second|summer/i', $semesterRaw);
        $status = $isEnrolled ? 'Enrolled' : 'Not Enrolled';

        if (stripos($semesterRaw, '1st') !== false || stripos($semesterRaw, 'first') !== false) {
            $semesterReadable = 'First Semester';
        } elseif (stripos($semesterRaw, '2nd') !== false || stripos($semesterRaw, 'second') !== false) {
            $semesterReadable = 'Second Semester';
        } elseif (stripos($semesterRaw, 'summer') !== false) {
            $semesterReadable = 'Summer';
        } else {
            $semesterReadable = $semesterRaw;
        }

        $out[] = [
            'StudID' => $r['StudID'],
            'first_name' => $r['FirstName'],
            'last_name' => $r['LastName'],
            'email' => $r['EmailAddr'],
            'phone' => $r['PhoneNo'],
            'year_level' => $r['YearLvl'],
            'course' => $r['Course'],
            'semester' => $semesterRaw,
            'semester_readable' => $semesterReadable,
            'enrollment_class' => $r['EnrollmentClass'] ?? null,
            'status' => $status
        ];
    }

    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

?>
