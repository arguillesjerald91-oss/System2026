<?php
session_start();
include __DIR__ . '/db.php';
$database = new Database();
$conn = $database->getConnection();

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $old = $_POST;
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid form submission. Please try again.";
    } else {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $birth_date = $_POST['birth_date'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $contact_number = trim($_POST['contact_number'] ?? '');
        $email_address = trim($_POST['email_address'] ?? '');
        $complete_address = trim($_POST['complete_address'] ?? '');
        $barangay = trim($_POST['barangay'] ?? '');
        $city_municipality = trim($_POST['city_municipality'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $civil_status = $_POST['civil_status'] ?? '';
        $citizenship = trim($_POST['citizenship'] ?? '') ?: 'Filipino';
        $highest_education = $_POST['highest_educational_attainment'] ?? '';
        $school_last_attended = trim($_POST['school_last_attended'] ?? '');
        $year_graduated = !empty($_POST['year_graduated']) ? (int)$_POST['year_graduated'] : null;
        $employment_status = $_POST['employment_status'] ?? '';
        $monthly_income = !empty($_POST['monthly_income']) ? (float)$_POST['monthly_income'] : null;
        $preferred_schedule = $_POST['preferred_training_schedule'] ?? '';
        $preferred_start_date = $_POST['preferred_start_date'] ?? null;
        $has_previous = isset($_POST['has_previous_tesda_training']) ? 1 : 0;
        $previous_course = trim($_POST['previous_tesa_course'] ?? '');
        $reason = trim($_POST['reason_for_applying'] ?? '');
        $emergency_name = trim($_POST['emergency_contact_name'] ?? '');
        $emergency_relationship = trim($_POST['emergency_contact_relationship'] ?? '');
        $emergency_number = trim($_POST['emergency_contact_number'] ?? '');
        
        if (empty($first_name)) $errors[] = "First name is required";
        if (empty($last_name)) $errors[] = "Last name is required";
        if (empty($birth_date)) $errors[] = "Birth date is required";
        if (empty($gender)) $errors[] = "Gender is required";
        if (empty($contact_number)) $errors[] = "Contact number is required";
        if (empty($email_address)) $errors[] = "Email address is required";
        if (!empty($email_address) && !filter_var($email_address, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email address format";
        }
        if (empty($complete_address)) $errors[] = "Complete address is required";
        if (empty($barangay)) $errors[] = "Barangay is required";
        if (empty($city_municipality)) $errors[] = "City/Municipality is required";
        if (empty($province)) $errors[] = "Province is required";
        if (empty($civil_status)) $errors[] = "Civil status is required";
        if (empty($highest_education)) $errors[] = "Educational attainment is required";
        if (empty($employment_status)) $errors[] = "Employment status is required";
        if (empty($preferred_schedule)) $errors[] = "Training schedule preference is required";
        if (empty($reason)) $errors[] = "Reason for applying is required";
        if (empty($emergency_name)) $errors[] = "Emergency contact name is required";
        if (empty($emergency_relationship)) $errors[] = "Emergency contact relationship is required";
        if (empty($emergency_number)) $errors[] = "Emergency contact number is required";
        
        if (!empty($birth_date)) {
            $birthDateObj = new DateTime($birth_date);
            $today = new DateTime();
            $age = $today->diff($birthDateObj)->y;
            if ($age < 16) {
                $errors[] = "Applicant must be at least 16 years old";
            }
            if ($birthDateObj > $today) {
                $errors[] = "Birth date cannot be in the future";
            }
        }
        
        if (!empty($year_graduated)) {
            $currentYear = (int)date('Y');
            if ($year_graduated < 1950 || $year_graduated > $currentYear) {
                $errors[] = "Invalid year graduated";
            }
        }
        
        if (!empty($email_address)) {
            $stmt = $conn->prepare("SELECT pre_enroll_id FROM pre_enrollment_applications WHERE email_address = ? AND application_status NOT IN ('Rejected', 'Not Qualified')");
            $stmt->execute([$email_address]);
            if ($stmt->fetch()) {
                $errors[] = "An application with this email already exists";
            }
        }
        
        if (empty($errors)) {
            $application_number = 'APP-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            try {
// Build dynamic INSERT based on actual table columns
$columnsStmt = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_enrollment_applications' AND COLUMN_NAME NOT IN ('pre_enroll_id', 'created_at', 'updated_at') ORDER BY ORDINAL_POSITION");
$tableColumns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);

// Map values to columns
$values = [];
foreach ($tableColumns as $col) {
    switch($col) {
        case 'application_number':
            $values[] = $application_number;
            break;
        case 'first_name':
            $values[] = $first_name;
            break;
        case 'last_name':
            $values[] = $last_name;
            break;
        case 'middle_name':
            $values[] = $middle_name;
            break;
        case 'birth_date':
            $values[] = $birth_date;
            break;
        case 'gender':
            $values[] = $gender;
            break;
        case 'contact_number':
            $values[] = $contact_number;
            break;
        case 'email_address':
            $values[] = $email_address;
            break;
        case 'complete_address':
            $values[] = $complete_address;
            break;
        case 'barangay':
            $values[] = $barangay;
            break;
        case 'city_municipality':
            $values[] = $city_municipality;
            break;
        case 'province':
            $values[] = $province;
            break;
        case 'postal_code':
            $values[] = $postal_code;
            break;
        case 'civil_status':
            $values[] = $civil_status;
            break;
        case 'citizenship':
            $values[] = $citizenship;
            break;
        case 'highest_educational_attainment':
            $values[] = $highest_education;
            break;
        case 'school_last_attended':
            $values[] = $school_last_attended;
            break;
        case 'year_graduated':
            $values[] = $year_graduated;
            break;
        case 'employment_status':
            $values[] = $employment_status;
            break;
        case 'monthly_income':
            $values[] = $monthly_income;
            break;
        case 'preferred_training_schedule':
            $values[] = $preferred_schedule;
            break;
        case 'preferred_start_date':
            $values[] = $preferred_start_date;
            break;
        case 'has_previous_tesda_training':
            $values[] = $has_previous;
            break;
        case 'previous_tesa_course':
            $values[] = $previous_course;
            break;
        case 'reason_for_applying':
            $values[] = $reason;
            break;
        case 'emergency_contact_name':
            $values[] = $emergency_name;
            break;
        case 'emergency_contact_relationship':
            $values[] = $emergency_relationship;
            break;
        case 'emergency_contact_number':
            $values[] = $emergency_number;
            break;
        case 'application_status':
            $values[] = 'Pending';
            break;
        case 'submission_date':
            $values[] = date('Y-m-d H:i:s');
            break;
        case 'nc_level':
            $values[] = $_POST['nc_level'] ?? 'NC I';
            break;
        case 'program_id':
            $values[] = $_POST['program_id'] ?? 0;
            break;
        default:
            $values[] = null;
    }
}

$sql = "INSERT INTO pre_enrollment_applications (" . implode(', ', $tableColumns) . ") VALUES (" . implode(', ', array_fill(0, count($values), '?')) . ")";
$stmt = $conn->prepare($sql);
$stmt->execute($values);
                
                $_SESSION['application_success'] = true;
                $_SESSION['application_number'] = $application_number;
                header('Location: pre_enrollment_success.php');
                exit;
            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pre-Enrollment - TESDA Auto Mechanic Training Centre</title>
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
    border-radius: 12px;
    display: flex;
    justify-content: center;
    align-items: center;
    background: linear-gradient(135deg, #2563eb, #1e40af);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.logo-box:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(37, 99, 235, 0.35);
}

.logo-box img {
    width: 35px;
    height: 35px;
    object-fit: contain;
    filter: brightness(1.1) contrast(1.1);
    transition: all 0.3s ease;
}

.logo-box:hover img {
    filter: brightness(1.2) contrast(1.2);
    transform: rotate(5deg);
}

.logo-box::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
    transform: rotate(45deg);
    transition: all 0.5s ease;
    opacity: 0;
}

.logo-box:hover::before {
    opacity: 1;
    animation: shimmer 0.5s ease-in-out;
}

@keyframes shimmer {
    0% { transform: translateX(-100%) rotate(45deg); }
    100% { transform: translateX(100%) rotate(45deg); }
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
.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
}
.error {
    background: #fee2e2;
    color: #991b1b;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #dc2626;
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
            <h2>Pre-Enrollment Application</h2>
            <p>TESDA Auto Mechanic Training Centre</p>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="error">
            <strong>Please correct the following errors:</strong>
            <ul style="margin: 10px 0 0 20px;">
                <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            
            <div class="form-section">
                <h3>Personal Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" required value="<?= htmlspecialchars($old['first_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" required value="<?= htmlspecialchars($old['last_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name" value="<?= htmlspecialchars($old['middle_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Birth Date *</label>
                        <input type="date" name="birth_date" required value="<?= htmlspecialchars($old['birth_date'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Gender *</label>
                        <select name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male" <?= ($old['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= ($old['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                            <option value="Other" <?= ($old['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Civil Status *</label>
                        <select name="civil_status" required>
                            <option value="">Select Status</option>
                            <option value="Single" <?= ($old['civil_status'] ?? '') === 'Single' ? 'selected' : '' ?>>Single</option>
                            <option value="Married" <?= ($old['civil_status'] ?? '') === 'Married' ? 'selected' : '' ?>>Married</option>
                            <option value="Widowed" <?= ($old['civil_status'] ?? '') === 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                            <option value="Separated" <?= ($old['civil_status'] ?? '') === 'Separated' ? 'selected' : '' ?>>Separated</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Contact Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Contact Number *</label>
                        <input type="tel" name="contact_number" required value="<?= htmlspecialchars($old['contact_number'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Email Address *</label>
                        <input type="email" name="email_address" required value="<?= htmlspecialchars($old['email_address'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Complete Address *</label>
                        <input type="text" name="complete_address" required value="<?= htmlspecialchars($old['complete_address'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Barangay *</label>
                        <input type="text" name="barangay" required value="<?= htmlspecialchars($old['barangay'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>City/Municipality *</label>
                        <input type="text" name="city_municipality" required value="<?= htmlspecialchars($old['city_municipality'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Province *</label>
                        <input type="text" name="province" required value="<?= htmlspecialchars($old['province'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Postal Code</label>
                        <input type="text" name="postal_code" value="<?= htmlspecialchars($old['postal_code'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Citizenship</label>
                        <input type="text" name="citizenship" value="<?= htmlspecialchars($old['citizenship'] ?? 'Filipino') ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Educational Background</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Highest Educational Attainment *</label>
                        <select name="highest_educational_attainment" required>
                            <option value="">Select Level</option>
                            <option value="Elementary" <?= ($old['highest_educational_attainment'] ?? '') === 'Elementary' ? 'selected' : '' ?>>Elementary</option>
                            <option value="High School" <?= ($old['highest_educational_attainment'] ?? '') === 'High School' ? 'selected' : '' ?>>High School</option>
                            <option value="College Undergraduate" <?= ($old['highest_educational_attainment'] ?? '') === 'College Undergraduate' ? 'selected' : '' ?>>College Undergraduate</option>
                            <option value="College Graduate" <?= ($old['highest_educational_attainment'] ?? '') === 'College Graduate' ? 'selected' : '' ?>>College Graduate</option>
                            <option value="Vocational" <?= ($old['highest_educational_attainment'] ?? '') === 'Vocational' ? 'selected' : '' ?>>Vocational</option>
                            <option value="Post Graduate" <?= ($old['highest_educational_attainment'] ?? '') === 'Post Graduate' ? 'selected' : '' ?>>Post Graduate</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>School Last Attended</label>
                        <input type="text" name="school_last_attended" value="<?= htmlspecialchars($old['school_last_attended'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Year Graduated</label>
                        <input type="number" name="year_graduated" min="1950" max="<?= date('Y') ?>" value="<?= htmlspecialchars($old['year_graduated'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Training Preferences</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Preferred Training Schedule *</label>
                        <select name="preferred_training_schedule" required>
                            <option value="">Select Schedule</option>
                            <option value="Morning" <?= ($old['preferred_training_schedule'] ?? '') === 'Morning' ? 'selected' : '' ?>>Morning</option>
                            <option value="Afternoon" <?= ($old['preferred_training_schedule'] ?? '') === 'Afternoon' ? 'selected' : '' ?>>Afternoon</option>
                            <option value="Evening" <?= ($old['preferred_training_schedule'] ?? '') === 'Evening' ? 'selected' : '' ?>>Evening</option>
                            <option value="Weekend" <?= ($old['preferred_training_schedule'] ?? '') === 'Weekend' ? 'selected' : '' ?>>Weekend</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Preferred Start Date</label>
                        <input type="date" name="preferred_start_date" value="<?= htmlspecialchars($old['preferred_start_date'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Employment Status *</label>
                        <select name="employment_status" required>
                            <option value="">Select Status</option>
                            <option value="Employed" <?= ($old['employment_status'] ?? '') === 'Employed' ? 'selected' : '' ?>>Employed</option>
                            <option value="Unemployed" <?= ($old['employment_status'] ?? '') === 'Unemployed' ? 'selected' : '' ?>>Unemployed</option>
                            <option value="Self-Employed" <?= ($old['employment_status'] ?? '') === 'Self-Employed' ? 'selected' : '' ?>>Self-Employed</option>
                            <option value="Student" <?= ($old['employment_status'] ?? '') === 'Student' ? 'selected' : '' ?>>Student</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Monthly Income (PHP)</label>
                        <input type="number" name="monthly_income" step="0.01" min="0" placeholder="e.g. 15000.00" value="<?= htmlspecialchars($old['monthly_income'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="has_previous_tesda_training" id="prev_training" <?= isset($old['has_previous_tesda_training']) ? 'checked' : '' ?>>
                            <label for="prev_training">Previous TESDA Training</label>
                        </div>
                    </div>
                    <div class="form-group" id="previous_course_group" style="<?= isset($old['has_previous_tesda_training']) ? '' : 'display:none;' ?>">
                        <label>Previous TESDA Course</label>
                        <input type="text" name="previous_tesa_course" value="<?= htmlspecialchars($old['previous_tesa_course'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Reason for Applying *</label>
                    <textarea name="reason_for_applying" rows="4" required placeholder="Tell us why you want to enroll in this program..."><?= htmlspecialchars($old['reason_for_applying'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-section">
                <h3>Emergency Contact</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Emergency Contact Name *</label>
                        <input type="text" name="emergency_contact_name" required value="<?= htmlspecialchars($old['emergency_contact_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Relationship *</label>
                        <input type="text" name="emergency_contact_relationship" required value="<?= htmlspecialchars($old['emergency_contact_relationship'] ?? '') ?>" placeholder="e.g. Parent, Spouse, Sibling">
                    </div>
                    <div class="form-group">
                        <label>Contact Number *</label>
                        <input type="tel" name="emergency_contact_number" required value="<?= htmlspecialchars($old['emergency_contact_number'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-submit">Submit Application</button>
        </form>
    </div>
</div>

<script>
document.getElementById('prev_training').addEventListener('change', function() {
    const courseGroup = document.getElementById('previous_course_group');
    courseGroup.style.display = this.checked ? '' : 'none';
});

document.querySelector('form').addEventListener('submit', function(e) {
    const required = this.querySelectorAll('[required]');
    let valid = true;
    let firstError = null;
    
    required.forEach(field => {
        if (!field.value.trim()) {
            field.style.borderColor = '#dc2626';
            valid = false;
            if (!firstError) firstError = field;
        } else {
            field.style.borderColor = '#e5e7eb';
        }
    });
    
    const emailField = this.querySelector('input[type="email"]');
    if (emailField && emailField.value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(emailField.value)) {
            emailField.style.borderColor = '#dc2626';
            valid = false;
            if (!firstError) firstError = emailField;
        }
    }
    
    if (!valid) {
        e.preventDefault();
        if (firstError) firstError.focus();
        alert('Please fill in all required fields correctly.');
    }
});

document.querySelectorAll('input, select, textarea').forEach(field => {
    field.addEventListener('input', function() {
        if (this.value.trim()) {
            this.style.borderColor = '#e5e7eb';
        }
    });
});
</script>
</body>
</html>
