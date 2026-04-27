<?php
include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== CHECKING QUIZZES FOR PATRICIA (NC IV) ===\n\n";

$stmt = $conn->prepare("
    SELECT q.quiz_id, q.title, q.nc_level, q.passing_score, q.time_limit, 
           (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.quiz_id) as question_count,
           (SELECT MAX(score) FROM quiz_attempts WHERE quiz_id = q.quiz_id AND user_id = 53) as best_score,
           (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.quiz_id AND user_id = 53) as attempt_count
    FROM quizzes q
    WHERE q.nc_level = 'NC IV' AND q.is_active = 1
    ORDER BY q.quiz_id
");
$stmt->execute();
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "NC IV Quizzes:\n";
foreach ($quizzes as $q) {
    $status = $q['best_score'] >= ($q['passing_score'] ?? 70) ? "✓ PASSED" : ($q['attempt_count'] > 0 ? "✗ Failed" : "○ Not taken");
    echo "- Quiz ID {$q['quiz_id']}: {$q['title']}\n";
    echo "  Questions: {$q['question_count']}, Best Score: {$q['best_score']}%, Attempts: {$q['attempt_count']}, Status: $status\n";
    
    if ($q['question_count'] == 0) {
        // Check questions table
        $stmt2 = $conn->prepare("SELECT question_id, question_text, question_type, options, correct_answer FROM quiz_questions WHERE quiz_id = ?");
        $stmt2->execute([$q['quiz_id']]);
        $questions = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        echo "  Questions in DB: " . count($questions) . "\n";
        if (count($questions) > 0) {
            foreach ($questions as $qq) {
                echo "    - Q{$qq['question_id']}: {$qq['question_text']}\n";
                echo "      Options: {$qq['options']}\n";
                echo "      Answer: {$qq['correct_answer']}\n";
            }
        }
    }
}

echo "\n=== quiz_attempts table ===\n";
$stmt = $conn->query("DESCRIBE quiz_attempts");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$r['Field']} | {$r['Type']}\n";
}
?>