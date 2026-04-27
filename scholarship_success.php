<?php
session_start();
if (!isset($_SESSION['scholarship_success']) || !$_SESSION['scholarship_success']) {
    header('Location: scholarship_application.php');
    exit;
}

$scholarship_number = $_SESSION['scholarship_number'] ?? '';
unset($_SESSION['scholarship_success']);
unset($_SESSION['scholarship_number']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Scholarship Application Submitted - TESDA Auto Mechanic Training Centre</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #10b981 0%, #059669 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
.success-container { background: white; border-radius: 20px; padding: 60px 40px; text-align: center; max-width: 600px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
.success-icon { width: 100px; height: 100px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 30px; font-size: 48px; color: white; }
h1 { color: #1f2937; font-size: 32px; margin-bottom: 20px; }
.application-number { background: #f0fdf4; padding: 15px 25px; border-radius: 10px; font-size: 18px; font-weight: bold; color: #10b981; margin: 20px 0; display: inline-block; border: 2px solid #10b981; }
.message { color: #6b7280; font-size: 16px; line-height: 1.6; margin-bottom: 30px; }
.btn { background: #10b981; color: white; padding: 12px 30px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; margin: 10px; transition: all 0.3s ease; }
.btn:hover { background: #059669; transform: translateY(-2px); }
.btn-secondary { background: transparent; color: #10b981; border: 2px solid #10b981; }
.btn-secondary:hover { background: #10b981; color: white; }
.next-steps { background: #f9fafb; padding: 30px; border-radius: 10px; margin-top: 30px; text-align: left; }
.next-steps h3 { color: #10b981; margin-bottom: 15px; }
.next-steps ul { list-style: none; padding: 0; }
.next-steps li { padding: 10px 0; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; }
.next-steps li:last-child { border-bottom: none; }
.next-steps li::before { content: '📋'; margin-right: 15px; font-size: 18px; }
.document-list { background: #fef3c7; padding: 20px; border-radius: 10px; margin-top: 20px; border-left: 4px solid #f59e0b; }
.document-list h4 { color: #92400e; margin-bottom: 15px; }
.document-list ul { list-style: none; padding: 0; }
.document-list li { padding: 8px 0; color: #78350f; }
.document-list li::before { content: '📄'; margin-right: 10px; }
</style>
</head>
<body>
<div class="success-container">
    <div class="success-icon">🎓</div>
    <h1>Scholarship Application Submitted!</h1>
    
    <div class="application-number">
        Scholarship Application Number: <?= htmlspecialchars($scholarship_number) ?>
    </div>
    
    <p class="message">
        Your scholarship application has been successfully submitted to the TESDA Auto Mechanic Training Centre. Our scholarship committee will review your application based on financial need and academic merit.
    </p>
    
    <div class="next-steps">
        <h3>Application Process Timeline</h3>
        <ul>
            <li>Initial screening and document verification</li>
            <li>Financial need assessment</li>
            <li>Scholarship committee review</li>
            <li>Interview (if required)</li>
            <li>Final decision notification</li>
        </ul>
    </div>
    
    <div class="document-list">
        <h4>📋 Required Documents to Submit</h4>
        <ul>
            <li>Income Tax Return or Certificate of Tax Exemption</li>
            <li>Barangay Certificate of Indigency</li>
            <li>Latest School Card or Transcript of Records</li>
            <li>Character Reference</li>
            <li>Birth Certificate (NSO)</li>
        </ul>
        <p style="margin-top: 15px; color: #92400e; font-weight: 600;">
            Please prepare these documents for submission. You will receive instructions via email on how to upload them.
        </p>
    </div>
    
    <p class="message">
        You can track your application status using your scholarship application number. Results will be announced within 2-3 weeks.
    </p>
    
    <div style="margin-top: 40px;">
        <a href="index_tesda.php" class="btn">Return to Home</a>
        <a href="login.php" class="btn btn-secondary">Track Application</a>
    </div>
</div>

<script>
// Auto-redirect after 15 seconds
setTimeout(() => {
    if (confirm('Would you like to track your application status?')) {
        window.location.href = 'login.php';
    } else {
        window.location.href = 'index_tesda.php';
    }
}, 15000);
</script>
</body>
</html>
