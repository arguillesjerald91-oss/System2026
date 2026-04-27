<?php
include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== ADDING QUESTIONS TO ALL QUIZZES ===\n\n";

// Clear existing questions first
$conn->exec("DELETE FROM quiz_questions");

$added = 0;

// Get all quizzes
$stmt = $conn->query("SELECT quiz_id, title, nc_level FROM quizzes WHERE is_active = 1 ORDER BY nc_level, quiz_id");
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Default questions for each NC level
$defaults = [
    'NC I' => [
        ['q' => 'What is the primary purpose of workplace communication?', 'a' => 'To exchange information effectively'],
        ['q' => 'Teamwork is important because it:', 'a' => 'Improves productivity and morale'],
        ['q' => 'Punctuality shows:', 'a' => 'Professionalism and respect']
    ],
    'NC II' => [
        ['q' => 'Battery measures voltage in:', 'a' => 'Volts'],
        ['q' => 'Ignition timing affects:', 'a' => 'Engine performance'],
        ['q' => 'Brake system uses:', 'a' => 'Friction to stop']
    ],
    'NC III' => [
        ['q' => 'A toolbox meeting is used to:', 'a' => 'Brief the team'],
        ['q' => 'ECU controls:', 'a' => 'Engine functions electronically'],
        ['q' => 'CAN bus allows:', 'a' => 'Modules to communicate']
    ],
    'NC IV' => [
        ['q' => 'Workshop manager must:', 'a' => 'All of the above'],
        ['q' => 'Electric vehicles use:', 'a' => 'High voltage batteries'],
        ['q' => 'AI diagnostics uses:', 'a' => 'Data analysis and patterns']
    ]
];

foreach ($quizzes as $quiz) {
    $quizId = $quiz['quiz_id'];
    $ncLevel = $quiz['nc_level'];
    
    $questions = $defaults[$ncLevel] ?? $defaults['NC I'];
    
    foreach ($questions as $q) {
        $stmt2 = $conn->prepare("INSERT INTO quiz_questions (quiz_id, question_text, question_type, correct_answer, points_value) VALUES (?, ?, 'Multiple Choice', ?, 1)");
        $stmt2->execute([$quizId, $q['q'], $q['a']]);
        $added++;
    }
    
    echo "Quiz $quizId ({$quiz['title']}): " . count($questions) . " questions\n";
}

echo "\nTotal: $added questions added\n";

// Verify
echo "\n=== VERIFICATION ===\n";
$stmt = $conn->query("
    SELECT nc_level, COUNT(*) as quizzes, 
           (SELECT COUNT(*) FROM quiz_questions qq JOIN quizzes q ON qq.quiz_id = q.quiz_id WHERE q.nc_level = nc_level GROUP BY q.nc_level) as questions
    FROM quizzes WHERE is_active = 1 GROUP BY nc_level
");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['nc_level']}: {$row['quizzes']} quizzes, {$row['questions']} questions\n";
}

echo "\nDone! Now Patricia can take quizzes.\n";
?>