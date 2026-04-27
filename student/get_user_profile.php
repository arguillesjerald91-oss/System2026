<?php
session_start();
include __DIR__ . '/../db.php';

$database = new Database();
$conn = $database->getConnection();

if (!isset($_SESSION['userId'])) {
    echo json_encode(['success' => false, 'message' => 'not_logged_in']);
    exit;
}

$userId = $_SESSION['userId'];

try {
    // Resolve users row: UserID -> Username=StudID -> Email -> Names
    $stmt = $conn->prepare("SELECT Username, Email FROM users WHERE UserID = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Load student data
        $stmtS = $conn->prepare("SELECT FirstName, LastName, EmailAddr FROM student WHERE StudID = ?");
        $stmtS->execute([$userId]);
        $stud = $stmtS->fetch(PDO::FETCH_ASSOC);

        // Username=StudID
        if (!$user) {
            $stmt2 = $conn->prepare("SELECT Username, Email FROM users WHERE Role = 'student' AND Username = ?");
            $stmt2->execute([(string)$userId]);
            $user = $stmt2->fetch(PDO::FETCH_ASSOC);
        }

        // Email match
        if (!$user && $stud && !empty($stud['EmailAddr'])) {
            $stmt3 = $conn->prepare("SELECT Username, Email FROM users WHERE Email = ? ORDER BY UserID DESC LIMIT 1");
            $stmt3->execute([$stud['EmailAddr']]);
            $user = $stmt3->fetch(PDO::FETCH_ASSOC);
        }

        // Names match
        if (!$user && $stud) {
            $full = trim(($stud['FirstName'] ?? '') . ' ' . ($stud['LastName'] ?? ''));
            $stmt4 = $conn->prepare("SELECT Username, Email FROM users WHERE (Fname = ? AND Lname = ?) OR (TRIM(IFNULL(FullName, '')) = ?) ORDER BY UserID DESC LIMIT 1");
            $stmt4->execute([$stud['FirstName'] ?? '', $stud['LastName'] ?? '', $full]);
            $user = $stmt4->fetch(PDO::FETCH_ASSOC);
        }
    }

    if ($user) {
        echo json_encode([
            'success' => true,
            'username' => $user['Username'],
            'email' => $user['Email']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
