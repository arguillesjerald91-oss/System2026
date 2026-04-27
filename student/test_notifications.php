<?php
session_start();
$_SESSION['userId'] = '1000010'; // Test with a student ID
$_SESSION['userRole'] = 'student';

include 'db.php';

$database = new Database();
$conn = $database->getConnection();

echo "<h2>Testing fetch_notifications.php</h2>";
echo "<h3>Session Info:</h3>";
echo "User ID: " . $_SESSION['userId'] . "<br>";
echo "User Role: " . $_SESSION['userRole'] . "<br><br>";

// Test if notices table exists
echo "<h3>Notices Table Check:</h3>";
$stmt = $conn->query("SELECT COUNT(*) as cnt FROM notices");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total notices in database: " . $result['cnt'] . "<br><br>";

// Test the actual fetch
echo "<h3>Fetching notifications via include:</h3>";
ob_start();
include 'fetch_notifications.php';
$output = ob_get_clean();

echo "<pre>";
$data = json_decode($output, true);
if ($data === null) {
    echo "JSON Error: " . json_last_error_msg() . "\n";
    echo "Raw output:\n" . htmlspecialchars($output);
} else {
    echo "Decoded data:\n";
    print_r($data);
    echo "\n\nTotal notifications: " . count($data);
}
echo "</pre>";
?>
