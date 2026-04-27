<?php
/**
 * Migration Runner: Add rate_per_unit to tuition_fees table
 * 
 * This script safely applies the migration to add the rate_per_unit column
 * to support flexible tuition for regular and irregular students.
 * 
 * Usage: Visit this file in browser or run: php migrations/run_add_rate_per_unit.php
 */

include_once __DIR__ . '/../db.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    die("Database connection failed.");
}

try {
    // Check if rate_per_unit column already exists
    $dbName = $conn->query('SELECT DATABASE()')->fetchColumn();
    $colStmt = $conn->prepare("
        SELECT COUNT(*) as cnt FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'tuition_fees' AND COLUMN_NAME = 'rate_per_unit'
    ");
    $colStmt->execute([$dbName]);
    $result = $colStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['cnt'] > 0) {
        echo "✓ rate_per_unit column already exists in tuition_fees table. No migration needed.\n";
        exit(0);
    }

    // Column doesn't exist, apply the migration
    echo "Applying migration: Adding rate_per_unit column...\n";
    
    // Read and execute the migration SQL
    $migrationFile = __DIR__ . '/002_add_rate_per_unit.sql';
    if (!file_exists($migrationFile)) {
        die("Migration file not found: $migrationFile\n");
    }

    $migrationSQL = file_get_contents($migrationFile);
    
    // Execute the migration (MySQL allows multiple statements with semicolon separation)
    $conn->exec($migrationSQL);
    
    echo "✓ Migration applied successfully!\n";
    echo "✓ rate_per_unit column added to tuition_fees table.\n";
    echo "✓ Existing total_fee values have been converted to rate_per_unit (divided by 18).\n";

} catch (PDOException $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

?>
