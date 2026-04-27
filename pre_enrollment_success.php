<?php
session_start();
if (!isset($_SESSION['application_success']) || !$_SESSION['application_success']) {
    header('Location: pre_enrollment.php');
    exit;
}

$application_number = $_SESSION['application_number'] ?? '';
unset($_SESSION['application_success']);
unset($_SESSION['application_number']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Application Submitted - TESDA Auto Mechanic Training Centre</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
.success-container { background: white; border-radius: 20px; padding: 60px 40px; text-align: center; max-width: 600px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
.success-icon { width: 100px; height: 100px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 30px; font-size: 48px; color: white; }
h1 { color: #1f2937; font-size: 32px; margin-bottom: 20px; }
.application-number { background: #f3f4f6; padding: 15px 25px; border-radius: 10px; font-size: 18px; font-weight: bold; color: #1e40af; margin: 20px 0; display: inline-block; }
.message { color: #6b7280; font-size: 16px; line-height: 1.6; margin-bottom: 30px; }
.btn { background: #1e40af; color: white; padding: 12px 30px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; margin: 10px; transition: all 0.3s ease; }
.btn:hover { background: #1e3a8a; transform: translateY(-2px); }
.btn-secondary { background: transparent; color: #1e40af; border: 2px solid #1e40af; }
.btn-secondary:hover { background: #1e40af; color: white; }
.next-steps { background: #f9fafb; padding: 30px; border-radius: 10px; margin-top: 30px; text-align: left; }
.next-steps h3 { color: #1e40af; margin-bottom: 15px; }
.next-steps ul { list-style: none; padding: 0; }
.next-steps li { padding: 10px 0; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; }
.next-steps li:last-child { border-bottom: none; }
.next-steps li::before { content: '✓'; color: #10b981; font-weight: bold; margin-right: 15px; font-size: 18px; }
</style>
</head>
<body>
<div class="success-container">
    <div class="success-icon">✓</div>
    <h1>Application Submitted Successfully!</h1>
    
    <div class="application-number">
        Application Number: <?= htmlspecialchars($application_number) ?>
    </div>
    
    <p class="message">
        Thank you for applying to the TESDA Auto Mechanic Training Centre. Your pre-enrollment application has been received and is now being processed.
    </p>
    
    <div class="next-steps">
        <h3>What Happens Next?</h3>
        <ul>
            <li>Application review by our admissions team</li>
            <li>Initial assessment and screening</li>
            <li>Schedule for entrance examination</li>
            <li>Interview with program coordinator</li>
            <li>Final admission decision</li>
        </ul>
    </div>
    
    <p class="message">
        You will receive updates via email and SMS regarding your application status. Please keep your application number for reference.
    </p>
    
    <div style="margin-top: 40px;">
        <a href="index.php" class="btn">Return to Home</a>
        <a href="check_application_status.php" class="btn btn-secondary">Check Status</a>
        <a href="scholarship_application.php" class="btn btn-secondary">Apply for Scholarship</a>
    </div>
</div>

<script>
setTimeout(() => {
    const proceed = confirm('Would you like to apply for a scholarship now?');
    if (proceed) {
        window.location.href = 'scholarship_application.php';
    } else {
        window.location.href = 'index.php';
    }
}, 10000);
</script>
</body>
</html>
