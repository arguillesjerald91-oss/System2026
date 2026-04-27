<?php
session_start();
include 'db.php';
$database = new Database();
$conn = $database->getConnection();

$appNumber = $_GET['app_no'] ?? $_POST['app_no'] ?? '';
$application = null;
$error = '';
$searched = false;

if (!empty($appNumber)) {
    $searched = true;
    $stmt = $conn->prepare("SELECT application_number, first_name, last_name, application_status, nc_level, submission_date, reviewed_at FROM pre_enrollment_applications WHERE application_number = ?");
    $stmt->execute([$appNumber]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        $error = "Application not found. Please check your application number and try again.";
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
    <title>Check Application Status - TESDA Auto Mechanic Training Centre</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f3f4f6; }
        .header { background: linear-gradient(135deg, #1e40af, #3b82f6); color: white; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 600px; margin: 30px auto; padding: 0 20px; }
        .card { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { margin-bottom: 20px; color: #374151; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #374151; }
        .form-group input { width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 16px; transition: border-color 0.2s; }
        .form-group input:focus { outline: none; border-color: #2563eb; }
        .btn { display: block; width: 100%; padding: 14px; background: linear-gradient(135deg, #1e40af, #3b82f6); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); }
        .btn-secondary { display: inline-block; padding: 10px 20px; background: white; color: #374151; border: 2px solid #e5e7eb; border-radius: 8px; text-decoration: none; font-weight: 600; }
        .btn-secondary:hover { background: #f9fafb; }
        .error { background: #fee2e2; color: #dc2626; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-pending { background: #fef3c7; color: #d97706; }
        .badge-review { background: #dbeafe; color: #2563eb; }
        .badge-qualified { background: #d1fae5; color: #065f46; }
        .badge-enrolled { background: #d1fae5; color: #065f46; }
        .badge-rejected { background: #fee2e2; color: #dc2626; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; color: #6b7280; font-size: 12px; text-transform: uppercase; }
        .status-cell { display: flex; align-items: center; gap: 8px; }
        .link-cell { color: #6b7280; font-size: 14px; }
        .link-cell a { color: #2563eb; text-decoration: none; }
        .link-cell a:hover { text-decoration: underline; }
        .empty-state { text-align: center; padding: 40px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Check Application Status</h1>
        <a href="pre_enrollment.php" style="color: white;">Apply Now</a>
    </div>

    <div class="container">
        <div class="card">
            <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <h2><?= $application ? 'Application Details' : 'Enter Application Number' ?></h2>
            
            <?php if (!$application): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="form-group">
                    <label for="app_no">Application Number</label>
                    <input type="text" id="app_no" name="app_no" placeholder="e.g., APP-2026-0001" value="<?= htmlspecialchars($appNumber) ?>" required>
                </div>
                <button type="submit" class="btn">Check Status</button>
            </form>
            <?php else: 
            $status = $application['application_status'];
            $statusClass = [
                'Pending' => 'badge-pending',
                'Under Review' => 'badge-review',
                'Qualified' => 'badge-qualified',
                'Enrolled' => 'badge-enrolled',
                'Not Qualified' => 'badge-rejected',
                'Rejected' => 'badge-rejected'
            ][$status] ?? 'badge-pending';
            ?>
            <table>
                <thead>
                    <tr>
                        <th>Application No.</th>
                        <th>Applicant Name</th>
                        <th>NC Level</th>
                        <th>Submission Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= htmlspecialchars($application['application_number']) ?></td>
                        <td><?= htmlspecialchars($application['first_name'] . ' ' . $application['last_name']) ?></td>
                        <td><?= htmlspecialchars($application['nc_level'] ?? '-') ?></td>
                        <td><?= date('M d, Y', strtotime($application['submission_date'])) ?></td>
                        <td><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($status) ?></span></td>
                    </tr>
                </tbody>
            </table>
            
            <?php if ($application['reviewed_at']): ?>
            <p style="margin-top: 16px; font-size: 14px; color: #6b7280;">
                Reviewed: <?= date('M d, Y', strtotime($application['reviewed_at'])) ?>
            </p>
            <?php endif; ?>
            
            <div style="margin-top: 20px;">
                <a href="check_application_status.php" class="btn-secondary">Check Another</a>
            </div>
            <?php endif; ?>
        </div>
        
        <div style="text-align: center; margin-top: 24px; font-size: 13px; color: #6b7280;">
            Need help? <a href="mailto:support@tesda.edu.ph" style="color: #2563eb;">Contact Support</a>
        </div>
    </div>
</body>
</html>