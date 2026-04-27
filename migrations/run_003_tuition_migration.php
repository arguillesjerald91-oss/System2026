<?php
/**
 * Migration Runner for Student-Specific Tuition Fees
 * Ensures tuition_fees table has correct schema for per-student assignments
 */

include_once __DIR__ . '/../db.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    die("❌ Database connection failed");
}

try {
    // Read and execute the migration SQL
    $sql = file_get_contents(__DIR__ . '/003_tuition_fees_student_specific.sql');
    
    // Split by semicolon and execute each statement
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($s) => !empty($s) && !str_starts_with($s, '--')
    );
    
    foreach ($statements as $statement) {
        $conn->exec($statement);
        echo "✅ Executed: " . substr($statement, 0, 60) . "...\n";
    }
    
    echo "\n✅ Migration 003_tuition_fees_student_specific completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
