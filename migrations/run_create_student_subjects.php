<?php
// Migration runner: creates a canonical `student_subjects` table if none of the common variants exist.
// Usage (from project root):
// php migrations\run_create_student_subjects.php

require_once __DIR__ . '/../db.php';
$database = new Database();
$conn = $database->getConnection();

$possibleTables = ['student_subjects', 'students_subjects', 'student_subject', 'students_subject'];
$found = null;

try {
    $stmt = $conn->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    foreach ($possibleTables as $tbl) {
        $stmt->execute([$tbl]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res && !empty($res['TABLE_NAME'])) { $found = $res['TABLE_NAME']; break; }
    }

    if ($found) {
        echo "Found existing student-subjects table: {$found}\n";
        echo "No migration performed. If you want to migrate data to the canonical 'student_subjects' table, please run a custom migration script.\n";
        exit(0);
    }

    // No table found — create canonical `student_subjects` using the SQL file if present, otherwise inline SQL
    $sqlFile = __DIR__ . '/001_create_student_subjects.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            throw new Exception('Failed to read SQL file: ' . $sqlFile);
        }
    } else {
        // fallback SQL (same as in the SQL file)
        $sql = "CREATE TABLE IF NOT EXISTS `student_subjects` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `student_id` VARCHAR(100) NOT NULL,
          `subject_id` INT NOT NULL,
          `assigned_at` DATETIME DEFAULT NULL,
          `created_by` INT DEFAULT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `student_subject_unique` (`student_id`, `subject_id`),
          KEY `idx_subject_id` (`subject_id`),
          KEY `idx_student_id` (`student_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    }

    // Execute SQL (may contain multiple statements)
    $conn->beginTransaction();
    $conn->exec($sql);
    $conn->commit();

    echo "Created 'student_subjects' table successfully.\n";
    echo "If you have existing alternate tables (e.g. 'students_subjects'), consider migrating rows into this new table and then updating code to use the canonical name.\n";
    exit(0);
} catch (Exception $e) {
    if ($conn && $conn->inTransaction()) $conn->rollBack();
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
