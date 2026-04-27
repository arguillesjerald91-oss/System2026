<?php
include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== ADDING SAMPLE QUIZ QUESTIONS ===\n\n";

$addedCount = 0;

$defaultQuestions = [
    'NC I' => [
        ['What is the primary purpose of workplace communication?', 'To exchange information effectively', 'To exchange information effectively|To create documents|To attend meetings|To make phone calls'],
        ['Which is NOT a form of workplace communication?', 'Sleeping', 'Email|Phone call|Sleeping|Face-to-face conversation'],
        ['Teamwork is important because it:', 'Improves productivity and morale', 'Is required by law|Improves productivity and morale|Means less work for you|Is only for managers'],
        ['Punctuality shows:', 'Professionalism and respect', 'That you have no life|Professionalism and respect|That you are desperate|That you are weak'],
        ['5S includes:', 'Sort, Set in order, Shine, Standardize, Sustain', 'Sell, Ship, Shop| Sort, Set, Shine, Standardize, Sustain'],
        ['Which tool tightens nuts?', 'Wrench', 'Hammer|Wrench|Screwdriver|Pliers'],
        ['Before using power tools:', 'Read manual', 'Use immediately|Read manual and check safety'],
        ['Micrometer measures:', 'Small dimensions precisely', 'Weight|Temperature|Small dimensions precisely'],
        ['Before servicing vehicle:', 'Apply parking brake', 'Apply parking brake|Disconnect battery|Both'],
        ['Diesel engines differ in:', 'Ignition method', 'Number of cylinders|Ignition method|Fuel type'],
        ['Spark plugs create:', 'Spark to ignite mixture', 'Fuel|Spark to ignite mixture|Cooling'],
        ['Glow plugs help with:', 'Cold weather starting', 'Cooling|Cold weather starting|Increase fuel economy']
    ],
    'NC II' => [
        ['Battery measures in:', 'Volts', 'Amps|Volts|Ohms|Watts'],
        ['Load testing checks:', 'Battery capacity', 'Battery color|Battery capacity|Battery age'],
        ['Ignition timing affects:', 'Engine performance', 'Radio reception|Engine performance|Tire pressure'],
        ['Electronic ignition:', 'More efficient', 'Uses points|More efficient|Need no power'],
        ['The starter motor:', 'Cranks the engine', 'Charges battery|Cranks the engine|Controls lights'],
        ['Alternator:', 'Charges battery', 'Charges battery|Powers accessories'],
        ['Brake system uses:', 'Friction', 'Magnets|Friction|Electricity'],
        ['Disc brakes use:', 'Caliper and pad', 'Drums only|Caliper and pad|Shoes'],
        ['Suspension includes:', 'Springs and shocks', 'Springs|Shocks|Springs and shocks'],
        ['Clutch connects:', 'Engine to transmission', 'Wheels to road|Engine to transmission']
    ],
    'NC III' => [
        ['Toolbox meeting is for:', 'Brief the team', 'Fire employees|Brief the team on daily tasks|None'],
        ['ECU controls:', 'Engine functions', 'Radio|Engine functions|None'],
        ['O2 sensors monitor:', 'Exhaust oxygen content', 'Oil pressure|Exhaust oxygen content|Fuel level'],
        ['Four-wheel alignment:', 'All alignment angles', 'Only front wheel|All angles|Only rear'],
        ['LPG is:', 'Liquefied Petroleum Gas', 'Liquid Petrol Gas|LPG|Low Pressure Gas'],
        ['CAN bus allows:', 'Modules to communicate', 'Driving|CAN bus|Modules to communicate']
    ],
    'NC IV' => [
        ['Electric vehicles use:', 'High voltage batteries', 'Gasoline engines|High voltage batteries'],
        ['EV batteries are:', 'Lithium-ion', 'Lead-acid mostly|Lithium-ion'],
        ['High voltage safety:', 'Specialized training', 'No precautions|Specialized training'],
        ['AI diagnostics uses:', 'Data analysis', 'Crystal ball|Data analysis|Random selection'],
        ['Predictive maintenance:', 'Prevent failures', 'Fix broken parts|Prevent failures'],
        ['ECU flashing:', 'Updating software', 'Physical replacement|Updating software']
    ]
];

// Get all quizzes
$stmt = $conn->query("SELECT quiz_id, title, nc_level FROM quizzes WHERE is_active = 1 ORDER BY nc_level, title");
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($quizzes as $quiz) {
    $quizId = $quiz['quiz_id'];
    $ncLevel = $quiz['nc_level'];
    $title = $quiz['title'];
    
    // Get questions for this NC level
    $questions = $defaultQuestions[$ncLevel] ?? $defaultQuestions['NC I'];
    $qNum = array_rand($questions);
    $q = $questions[$qNum];
    
    $opt = !empty($q[2]) ? $q[2] : 'Option A|Option B';
    
    $stmt2 = $conn->prepare("INSERT INTO quiz_questions (quiz_id, question_text, question_type, options, correct_answer, points_value) VALUES (?, ?, 'Multiple Choice', ?, ?, 1)");
    try {
        $stmt2->execute([$quizId, $q[0], $opt, $q[1]]);
        $addedCount++;
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . " for $title\n";
    }
}

echo "Added questions to " . count($quizzes) . " quizzes\n";
echo "Total questions: $addedCount\n";

echo "\n=== VERIFICATION ===\n";
$stmt = $conn->query("SELECT q.nc_level, COUNT(DISTINCT q.quiz_id) as quizzes, (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.quiz_id) as questions FROM quizzes q GROUP BY q.nc_level");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['nc_level']}: {$row['questions']} questions\n";
}
?>