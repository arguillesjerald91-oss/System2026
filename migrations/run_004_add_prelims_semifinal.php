<?php
// Database connection
include_once __DIR__ . '/../admin/db.php';

$database = new Database();
$conn = $database->getConnection();

try {
    // Read the SQL file
    $sql = file_get_contents(__DIR__ . '/004_add_prelims_semifinal.sql');
    
    // Execute the migration
    $conn->exec($sql);
    
    echo "<h2 style='color: green;'>✓ Migration Successful!</h2>";
    echo "<p>Columns 'prelims' and 'semi_finals' have been added to the 'grades' table.</p>";
    echo "<a href='javascript:history.back()'><button>Go Back</button></a>";
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>✗ Migration Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<a href='javascript:history.back()'><button>Go Back</button></a>";
}
?>
