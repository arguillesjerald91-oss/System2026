<?php
include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== CHECKING QUIZ QUESTIONS ===\n\n";

// Check available quizzes and their question counts
$stmt = $conn->query("
    SELECT q.quiz_id, q.title, q.nc_level, 
           (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.quiz_id) as question_count
    FROM quizzes q
    WHERE q.is_active = 1
    ORDER BY q.nc_level, q.title
");

$total = 0;
echo "--- ALL QUIZZES ---\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $status = $row['question_count'] > 0 ? "✓ Has {$row['question_count']} questions" : "✗ NO QUESTIONS";
    echo "[{$row['nc_level']}] {$row['title']}: $status\n";
    $total++;
}

echo "\nTotal quizzes: $total\n";

// Check for NC IV quizzes specifically
echo "\n=== NC IV QUIZZES STATUS ===\n";
$stmt = $conn->prepare("
    SELECT q.quiz_id, q.title, 
           (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.quiz_id) as question_count
    FROM quizzes q
    WHERE q.nc_level = 'NC IV' AND q.is_active = 1
");
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $status = $row['question_count'] > 0 ? "✓" : "✗";
    echo "$status {$row['title']}: {$row['question_count']} questions\n";
}

// Count quizzes with questions vs without
$stmt = $conn->query("
    SELECT q.nc_level, 
           SUM(CASE WHEN (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.quiz_id) > 0 THEN 1 ELSE 0 END) as with_questions,
           SUM(CASE WHEN (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.quiz_id) = 0 THEN 1 ELSE 0 END) as without_questions
    FROM quizzes q
    WHERE q.is_active = 1
    GROUP BY q.nc_level
");
echo "\n=== SUMMARY BY NC LEVEL ===\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['nc_level']}: {$row['with_questions']} with questions, {$row['without_questions']} without\n";
}
?>