<?php
include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== ADDING DEFAULT OPTIONS TO QUIZ QUESTIONS ===\n\n";

$defaults = [
    'NC I' => 'Yes|No|Not sure|None of the above',
    'NC II' => 'Correct|Incorrect|Not sure|Need more info',
    'NC III' => 'True|False|Not applicable|None',
    'NC IV' => 'Option A|Option B|Option C|Option D'
];

$updated = 0;
$quizzes = $conn->query("SELECT quiz_id, title, nc_level FROM quizzes WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);

foreach ($quizzes as $q) {
    $quizId = $quiz['quiz_id'];
    $ncLevel = $quiz['nc_level'];
    $defaultOpts = $defaults[$ncLevel] ?? $defaults['NC I'];
    
    // Update questions that have no options
    $stmt = $conn->prepare("UPDATE quiz_questions SET options = ? WHERE quiz_id = ? AND (options IS NULL OR options = '')");
    $stmt->execute([$defaultOpts, $quizId]);
    
    $count = $stmt->rowCount();
    if ($count > 0) {
        echo "Quiz $quizId ({$q['title']}): $count questions updated\n";
        $updated += $count;
    }
}

echo "\nTotal: $updated questions updated with options\n";

// Now verify
$stmt = $conn->query("SELECT q.nc_level, COUNT(*) as total, SUM(CASE WHEN q.options IS NOT NULL AND q.options != '' THEN 1 ELSE 0 END) as with_options FROM quiz_questions q GROUP BY q.nc_level");
echo "\nVerification:\n";
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$r['nc_level']}: {$r['with_options']}/{$r['total']} with options\n";
}
?>