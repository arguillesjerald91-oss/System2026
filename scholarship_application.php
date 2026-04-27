<?php
session_start();
include __DIR__ . '/db.php';
$database = new Database();
$conn = $database->getConnection();

// Get available scholarship programs
$programs = $conn->query("SELECT * FROM scholarship_programs WHERE program_status = 'Active' AND application_deadline >= CURDATE() ORDER BY program_name")->fetchAll(PDO::FETCH_ASSOC);

// Get pre-enrollment applications for selection (if user is logged in)
$pre_enrollments = [];
if (isset($_SESSION['userId'])) {
    $stmt = $conn->prepare("SELECT pre_enroll_id, application_number, first_name, last_name FROM pre_enrollment_applications WHERE application_status = 'Qualified' ORDER BY submission_date DESC");
    $stmt->execute();
    $pre_enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $application_number = 'SCH-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    $stmt = $conn->prepare("INSERT INTO scholarship_applications (
        pre_enroll_id, program_id, application_number, household_income, household_members,
        family_head_occupation, family_head_monthly_income, special_circumstances,
        financial_need_score, academic_score, interview_score, total_score,
        application_status, submission_date
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $stmt->execute([
        $_POST['pre_enroll_id'],
        $_POST['program_id'],
        $application_number,
        $_POST['household_income'],
        $_POST['household_members'],
        $_POST['family_head_occupation'] ?? null,
        $_POST['family_head_monthly_income'] ?? null,
        $_POST['special_circumstances'] ?? null,
        0, // Will be calculated
        0, // Will be calculated
        0, // Will be calculated
        0, // Will be calculated
        'Submitted'
    ]);
    
    $_SESSION['scholarship_success'] = true;
    $_SESSION['scholarship_number'] = $application_number;
    header('Location: scholarship_success.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Scholarship Application - TESDA Auto Mechanic Training Centre</title>
<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f8f9fc;
    color: #2d2d2d;
}
:root {
  --background: #f8f9fc;
  --foreground: #2d2d2d;
  --card: #ffffff;
  --card-foreground: #2d2d2d;
  --primary: #2563eb;
  --muted-foreground: #6b7280;
  --radius: 14px;
  --shadow-soft: 0 4px 15px rgba(0,0,0,0.08);
  --shadow-card: 0 8px 25px rgba(0,0,0,0.12);
}
header {
    position: sticky;
    top: 0;
    z-index: 100;
    background: rgba(255,255,255,0.92);
    backdrop-filter: blur(6px);
    border-bottom: 1px solid #ddd;
    padding: 20px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.logo {
    display: flex;
    align-items: center;
    gap: 12px;
}
.logo-box {
    width: 50px;
    height: 50px;
    border-radius: 50px;
    display: flex;
    justify-content: center;
    align-items: center;
}
header a {
    padding: 10px 18px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
}
.btn-primary {
    background: #2563eb;
    color: white;
}
.btn-primary:hover {
    background: #1e4dcc;
}
.container {
    max-width: 800px;
    margin: 40px auto;
    padding: 0 20px;
}
.form-container {
    background: white;
    border-radius: 15px;
    padding: 40px;
    box-shadow: var(--shadow-card);
}
.form-header {
    text-align: center;
    margin-bottom: 40px;
}
.form-header h2 {
    font-size: 28px;
    color: #1f2937;
    margin-bottom: 10px;
}
.form-header p {
    color: #6b7280;
}
.form-section {
    margin-bottom: 40px;
}
.form-section h3 {
    color: #2563eb;
    margin-bottom: 20px;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 10px;
}
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #374151;
}
.form-group input, .form-group select, .form-group textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.3s ease;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}
.btn {
    padding: 12px 30px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}
.btn-submit {
    background: #2563eb;
    color: white;
    width: 100%;
    font-size: 16px;
}
.btn-submit:hover {
    background: #1e4dcc;
    transform: translateY(-2px);
}
.program-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.program-card {
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
}
.program-card:hover {
    border-color: #2563eb;
    box-shadow: 0 5px 15px rgba(37, 99, 235, 0.2);
}
.program-card.selected {
    border-color: #2563eb;
    background: #f0f9ff;
}
.program-title {
    font-weight: bold;
    color: #1f2937;
    margin-bottom: 10px;
}
.program-type {
    color: #2563eb;
    font-size: 14px;
    margin-bottom: 10px;
}
.program-description {
    color: #6b7280;
    font-size: 14px;
    line-height: 1.5;
}
.eligibility-info {
    background: #f9fafb;
    padding: 15px;
    border-radius: 8px;
    margin-top: 15px;
}
.eligibility-info h4 {
    color: #dc2626;
    margin-bottom: 10px;
}
.eligibility-info ul {
    margin-left: 20px;
    color: #6b7280;
}
@media (max-width: 768px) {
    .container {
        margin: 20px auto;
        padding: 0 15px;
    }
    .form-container {
        padding: 25px;
    }
    header {
        padding: 15px 20px;
        flex-direction: column;
        gap: 15px;
    }
    .program-cards {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>
<header>
    <div class="logo">
        <div class="logo-box">
            <img src="images/image.png" width="35" height="35" alt="Logo">
        </div>
        <strong>TESDA Auto Mechanic Training Centre</strong>
    </div>
    <div>
        <a href="student/student_dashboard.php" class="btn-primary">Back to Dashboard</a>
    </div>
</header>

<div class="container">
    <div class="form-container">
        <div class="form-header">
            <h2>Scholarship Application</h2>
            <p>Apply for financial assistance for your training</p>
        </div>

        <form method="POST">
            <div class="form-section">
                <h3>Select Scholarship Program</h3>
                <div class="program-cards">
                    <?php foreach ($programs as $program): ?>
                    <div class="program-card" onclick="selectProgram(<?= $program['program_id'] ?>)">
                        <div class="program-title"><?= htmlspecialchars($program['program_name']) ?></div>
                        <div class="program-type"><?= htmlspecialchars($program['program_type']) ?></div>
                        <div class="program-description"><?= htmlspecialchars(substr($program['description'], 0, 150)) ?>...</div>
                        <div class="eligibility-info">
                            <h4>Eligibility:</h4>
                            <ul>
                                <li>Max Income: <?= number_format($program['income_requirement_max'], 2) ?></li>
                                <li>Available Slots: <?= $program['max_slots'] - $program['current_slots_taken'] ?>/<?= $program['max_slots'] ?></li>
                                <li>Deadline: <?= date('M d, Y', strtotime($program['application_deadline'])) ?></li>
                            </ul>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="program_id" id="selected_program" required>
            </div>

            <div class="form-section">
                <h3>Applicant Information</h3>
                <?php if (!empty($pre_enrollments)): ?>
                <div class="form-group">
                    <label>Select Your Pre-Enrollment Application *</label>
                    <select name="pre_enroll_id" required>
                        <option value="">Select Application</option>
                        <?php foreach ($pre_enrollments as $enrollment): ?>
                        <option value="<?= $enrollment['pre_enroll_id'] ?>">
                            <?= htmlspecialchars($enrollment['application_number']) ?> - 
                            <?= htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <div class="form-group">
                    <label>Pre-Enrollment Application ID *</label>
                    <input type="text" name="pre_enroll_id" required placeholder="Enter your pre-enrollment application ID">
                    <small>You must have a qualified pre-enrollment application to apply for scholarship.</small>
                </div>
                <?php endif; ?>
            </div>

            <div class="form-section">
                <h3>Financial Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Monthly Household Income *</label>
                        <input type="number" name="household_income" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Total Household Members *</label>
                        <input type="number" name="household_members" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Family Head's Occupation</label>
                        <input type="text" name="family_head_occupation">
                    </div>
                    <div class="form-group">
                        <label>Family Head's Monthly Income</label>
                        <input type="number" name="family_head_monthly_income" step="0.01" min="0">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Additional Information</h3>
                <div class="form-group">
                    <label>Special Circumstances</label>
                    <textarea name="special_circumstances" rows="4" placeholder="Please describe any special circumstances that may affect your eligibility..."></textarea>
                </div>
                
                <div class="eligibility-info">
                    <h4>Required Documents:</h4>
                    <ul>
                        <li>Income Tax Return (ITR) or Certificate of Tax Exemption</li>
                        <li>Certificate of Indigency from Barangay</li>
                        <li>Latest School Card or Transcript of Records</li>
                        <li>Character Reference from School/Community</li>
                        <li>Birth Certificate (NSO)</li>
                        <li>Parent's Marriage Certificate (if applicable)</li>
                    </ul>
                    <p><strong>Note:</strong> You will be required to upload these documents after submitting this application.</p>
                </div>
            </div>

            <button type="submit" class="btn btn-submit">Submit Scholarship Application</button>
        </form>
    </div>
</div>

<script>
function selectProgram(programId) {
    // Remove previous selection
    document.querySelectorAll('.program-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // Add selection to clicked card
    event.currentTarget.classList.add('selected');
    
    // Set hidden input value
    document.getElementById('selected_program').value = programId;
}

document.querySelector('form').addEventListener('submit', function(e) {
    const required = this.querySelectorAll('[required]');
    let valid = true;
    
    required.forEach(field => {
        if (!field.value.trim()) {
            field.style.borderColor = '#dc2626';
            valid = false;
        } else {
            field.style.borderColor = '#e5e7eb';
        }
    });
    
    if (!valid) {
        e.preventDefault();
        alert('Please fill in all required fields.');
    }
});

document.querySelector('input[name="household_members"]').addEventListener('input', function() {
    if (this.value < 1) {
        this.value = 1;
    }
});
</script>
</body>
</html>
