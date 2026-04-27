<?php
session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

// Check if user is admin or staff
if (!isset($_SESSION['user_id']) && !isset($_SESSION['userId'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['user_id'] ?? $_SESSION['userId'] ?? null;
$userType = $_SESSION['user_type'] ?? $_SESSION['userRole'] ?? '';

if ($userType !== 'admin' && $userType !== 'staff') {
    header("Location: ../login.php");
    exit();
}

// Get application ID
$applicationId = $_GET['id'] ?? null;
if (!$applicationId) {
    header("Location: manage_applicants.php");
    exit();
}

// Get application details with all related information
$stmt = $conn->prepare("
    SELECT 
        sa.*,
        pea.*,
        sp.*,
        processor.first_name as processor_first_name,
        processor.last_name as processor_last_name,
        reviewer.first_name as reviewer_first_name,
        reviewer.last_name as reviewer_last_name
    FROM scholarship_applications sa
    JOIN pre_enrollment_applications pea ON sa.pre_enroll_id = pea.pre_enroll_id
    JOIN scholarship_programs sp ON sa.program_id = sp.program_id
    LEFT JOIN users processor ON sa.processed_by = processor.user_id
    LEFT JOIN users reviewer ON sa.reviewed_by = reviewer.user_id
    WHERE sa.scholarship_app_id = ?
");
$stmt->execute([$applicationId]);
$application = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$application) {
    header("Location: manage_applicants.php");
    exit();
}

// Get scholarship requirements for this application
$reqStmt = $conn->prepare("
    SELECT * FROM scholarship_requirements 
    WHERE scholarship_app_id = ? 
    ORDER BY requirement_name
");
$reqStmt->execute([$applicationId]);
$requirements = $reqStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $notes = $_POST['notes'] ?? '';
    
    try {
        $status = '';
        $subject = '';
        $message = '';
        
        switch ($action) {
            case 'approve':
                $status = 'Approved';
                $subject = 'Scholarship Application Approved - TESDA Auto Mechanic Training Centre';
                $message = "Dear {$application['first_name']} {$application['last_name']},\n\n";
                $message .= "Congratulations! Your scholarship application for the {$application['program_name']} program has been APPROVED.\n\n";
                $message .= "You are qualified to proceed to the next steps of the application process. Our admissions office will contact you soon with further instructions.\n\n";
                $message .= "Application Number: {$application['application_number']}\n";
                $message .= "Program: {$application['program_name']} ({$application['program_type']})\n\n";
                $message .= "If you have any questions, please contact our admissions office.\n\n";
                $message .= "Best regards,\n";
                $message .= "TESDA Auto Mechanic Training Centre";
                break;
                
            case 'reject':
                $status = 'Rejected';
                $subject = 'Scholarship Application Status - TESDA Auto Mechanic Training Centre';
                $message = "Dear {$application['first_name']} {$application['last_name']},\n\n";
                $message .= "We regret to inform you that your scholarship application for the {$application['program_name']} program has been carefully reviewed and is not eligible to proceed for the scholarship application at this time.\n\n";
                $message .= "Application Number: {$application['application_number']}\n";
                $message .= "Program: {$application['program_name']} ({$application['program_type']})\n\n";
                if ($notes) {
                    $message .= "Reason: " . htmlspecialchars($notes) . "\n\n";
                }
                $message .= "We encourage you to consider other financial assistance options or reapply in the future. Thank you for your interest in our training programs.\n\n";
                $message .= "Best regards,\n";
                $message .= "TESDA Auto Mechanic Training Centre";
                break;
                
            case 'review':
                $status = 'Under Review';
                break;
                
            case 'interview':
                $status = 'For Interview';
                break;
        }
        
        // Update database
        $updateFields = [
            'application_status' => $status,
            'processed_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($action == 'approve') {
            $updateFields['decision_date'] = date('Y-m-d H:i:s');
            $updateFields['approval_notes'] = $notes;
        } elseif ($action == 'reject') {
            $updateFields['decision_date'] = date('Y-m-d H:i:s');
            $updateFields['rejection_reason'] = $notes;
        } elseif ($action == 'review') {
            $updateFields['review_date'] = date('Y-m-d H:i:s');
            $updateFields['reviewed_by'] = $userId;
        } elseif ($action == 'interview') {
            $updateFields['interview_date'] = date('Y-m-d H:i:s');
        }
        
        $setClause = [];
        $values = [];
        foreach ($updateFields as $field => $value) {
            $setClause[] = "$field = ?";
            $values[] = $value;
        }
        $values[] = $applicationId;
        
        $sql = "UPDATE scholarship_applications SET " . implode(', ', $setClause) . " WHERE scholarship_app_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute($values);
        
        // Send email notification for approve/reject
        if (($action == 'approve' || $action == 'reject') && $application['email_address']) {
            $headers = "From: admissions@tesda-automotive.edu.ph\r\n";
            $headers .= "Reply-To: admissions@tesda-automotive.edu.ph\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            
            mail($application['email_address'], $subject, $message, $headers);
        }
        
        $_SESSION['success'] = "Application status updated successfully.";
        
        // Refresh application data
        $stmt = $conn->prepare("
            SELECT 
                sa.*,
                pea.*,
                sp.*,
                processor.first_name as processor_first_name,
                processor.last_name as processor_last_name,
                reviewer.first_name as reviewer_first_name,
                reviewer.last_name as reviewer_last_name
            FROM scholarship_applications sa
            JOIN pre_enrollment_applications pea ON sa.pre_enroll_id = pea.pre_enroll_id
            JOIN scholarship_programs sp ON sa.program_id = sp.program_id
            LEFT JOIN users processor ON sa.processed_by = processor.user_id
            LEFT JOIN users reviewer ON sa.reviewed_by = reviewer.user_id
            WHERE sa.scholarship_app_id = ?
        ");
        $stmt->execute([$applicationId]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating application: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Applicant Details - <?= htmlspecialchars($application['application_number']) ?></title>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f8f9fc;
    color: #2d2d2d;
    line-height: 1.6;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.header {
    background: white;
    padding: 20px 0;
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 30px;
}

.header-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header h1 {
    color: #1f2937;
    font-size: 24px;
}

.back-btn {
    background: #6b7280;
    color: white;
    padding: 10px 20px;
    text-decoration: none;
    border-radius: 6px;
    transition: background 0.3s;
}

.back-btn:hover {
    background: #4b5563;
}

.grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
    margin-bottom: 30px;
}

.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.card-header {
    background: #f9fafb;
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.card-header h3 {
    color: #1f2937;
    font-size: 18px;
    margin: 0;
}

.card-body {
    padding: 20px;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-submitted { background: #dbeafe; color: #1e40af; }
.status-under-review { background: #fef3c7; color: #92400e; }
.status-for-interview { background: #ede9fe; color: #6b21a8; }
.status-approved { background: #d1fae5; color: #065f46; }
.status-rejected { background: #fee2e2; color: #991b1b; }
.status-waitlisted { background: #fed7aa; color: #9a3412; }

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.info-item {
    padding: 15px;
    background: #f9fafb;
    border-radius: 8px;
}

.info-label {
    font-weight: 600;
    color: #6b7280;
    font-size: 12px;
    text-transform: uppercase;
    margin-bottom: 5px;
}

.info-value {
    color: #1f2937;
    font-size: 14px;
}

.requirement-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    margin-bottom: 10px;
}

.requirement-name {
    font-weight: 500;
}

.requirement-status {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.status-submitted-req { background: #dbeafe; color: #1e40af; }
.status-pending-req { background: #fef3c7; color: #92400e; }
.status-verified { background: #d1fae5; color: #065f46; }

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 20px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-block;
}

.btn-primary { background: #2563eb; color: white; }
.btn-success { background: #10b981; color: white; }
.btn-danger { background: #ef4444; color: white; }
.btn-warning { background: #f59e0b; color: white; }
.btn-secondary { background: #6b7280; color: white; }

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.alert {
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert-danger {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
}

.modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 30px;
    border-radius: 10px;
    width: 90%;
    max-width: 500px;
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

.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    resize: vertical;
}

.modal-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

@media (max-width: 768px) {
    .grid {
        grid-template-columns: 1fr;
    }
    
    .header-content {
        flex-direction: column;
        gap: 15px;
    }
    
    .action-buttons {
        flex-direction: column;
    }
}
</style>
</head>
<body>

<div class="header">
    <div class="header-content">
        <h1>Application Details: <?= htmlspecialchars($application['application_number']) ?></h1>
        <a href="manage_applicants.php" class="back-btn">← Back to Applicants</a>
    </div>
</div>

<div class="container">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <div class="grid">
        <!-- Main Content -->
        <div>
            <!-- Application Status -->
            <div class="card" style="margin-bottom: 30px;">
                <div class="card-header">
                    <h3>Application Status</h3>
                </div>
                <div class="card-body">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <span class="status-badge status-<?= str_replace(' ', '-', strtolower($application['application_status'])) ?>">
                                <?= htmlspecialchars($application['application_status']) ?>
                            </span>
                        </div>
                        <div style="text-align: right;">
                            <small style="color: #6b7280;">Application Number</small><br>
                            <strong><?= htmlspecialchars($application['application_number']) ?></strong>
                        </div>
                    </div>
                    
                    <?php if ($application['application_status'] == 'Approved' && $application['approval_notes']): ?>
                        <div style="margin-top: 15px; padding: 15px; background: #f0fdf4; border-radius: 6px;">
                            <strong>Approval Notes:</strong><br>
                            <?= nl2br(htmlspecialchars($application['approval_notes'])) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($application['application_status'] == 'Rejected' && $application['rejection_reason']): ?>
                        <div style="margin-top: 15px; padding: 15px; background: #fef2f2; border-radius: 6px;">
                            <strong>Rejection Reason:</strong><br>
                            <?= nl2br(htmlspecialchars($application['rejection_reason'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Personal Information -->
            <div class="card" style="margin-bottom: 30px;">
                <div class="card-header">
                    <h3>Personal Information</h3>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Full Name</div>
                            <div class="info-value"><?= htmlspecialchars($application['first_name'] . ' ' . $application['last_name']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Email Address</div>
                            <div class="info-value"><?= htmlspecialchars($application['email_address']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Phone Number</div>
                            <div class="info-value"><?= htmlspecialchars($application['contact_number']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Date of Birth</div>
                            <div class="info-value"><?= date('M d, Y', strtotime($application['birth_date'])) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Address</div>
                            <div class="info-value"><?= htmlspecialchars($application['complete_address']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Civil Status</div>
                            <div class="info-value"><?= htmlspecialchars($application['civil_status']) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Program Information -->
            <div class="card" style="margin-bottom: 30px;">
                <div class="card-header">
                    <h3>Program Information</h3>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Program Name</div>
                            <div class="info-value"><?= htmlspecialchars($application['program_name']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Program Type</div>
                            <div class="info-value"><?= htmlspecialchars($application['program_type']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Duration</div>
                            <div class="info-value"><?= htmlspecialchars($application['duration']) ?> months</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Training Cost</div>
                            <div class="info-value">₱<?= number_format($application['training_cost'], 2) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Financial Information -->
            <div class="card" style="margin-bottom: 30px;">
                <div class="card-header">
                    <h3>Financial Information</h3>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Monthly Household Income</div>
                            <div class="info-value">₱<?= number_format($application['household_income'], 2) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Total Household Members</div>
                            <div class="info-value"><?= htmlspecialchars($application['household_members']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Family Head's Occupation</div>
                            <div class="info-value"><?= htmlspecialchars($application['family_head_occupation'] ?: 'Not specified') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Family Head's Monthly Income</div>
                            <div class="info-value">₱<?= number_format($application['family_head_monthly_income'] ?: 0, 2) ?></div>
                        </div>
                    </div>
                    
                    <?php if ($application['special_circumstances']): ?>
                        <div style="margin-top: 20px;">
                            <div class="info-label">Special Circumstances</div>
                            <div style="padding: 15px; background: #f9fafb; border-radius: 6px; margin-top: 5px;">
                                <?= nl2br(htmlspecialchars($application['special_circumstances'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Requirements -->
            <div class="card">
                <div class="card-header">
                    <h3>Submitted Requirements</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($requirements)): ?>
                        <p style="color: #6b7280; text-align: center; padding: 20px;">No requirements submitted yet.</p>
                    <?php else: ?>
                        <?php foreach ($requirements as $req): ?>
                            <div class="requirement-item">
                                <div>
                                    <div class="requirement-name"><?= htmlspecialchars($req['requirement_name']) ?></div>
                                    <small style="color: #6b7280;">Submitted: <?= date('M d, Y H:i', strtotime($req['submission_date'])) ?></small>
                                </div>
                                <span class="requirement-status status-<?= str_replace(' ', '-', strtolower($req['verification_status'])) ?>">
                                    <?= htmlspecialchars($req['verification_status']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div>
            <!-- Scores -->
            <div class="card" style="margin-bottom: 30px;">
                <div class="card-header">
                    <h3>Evaluation Scores</h3>
                </div>
                <div class="card-body">
                    <div class="info-item" style="margin-bottom: 15px;">
                        <div class="info-label">Financial Need Score</div>
                        <div class="info-value"><?= $application['financial_need_score'] ? number_format($application['financial_need_score'], 1) : 'Not evaluated' ?></div>
                    </div>
                    <div class="info-item" style="margin-bottom: 15px;">
                        <div class="info-label">Academic Score</div>
                        <div class="info-value"><?= $application['academic_score'] ? number_format($application['academic_score'], 1) : 'Not evaluated' ?></div>
                    </div>
                    <div class="info-item" style="margin-bottom: 15px;">
                        <div class="info-label">Interview Score</div>
                        <div class="info-value"><?= $application['interview_score'] ? number_format($application['interview_score'], 1) : 'Not evaluated' ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Total Score</div>
                        <div class="info-value" style="font-size: 18px; font-weight: bold; color: #2563eb;">
                            <?= $application['total_score'] ? number_format($application['total_score'], 1) : 'Not calculated' ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Timeline -->
            <div class="card" style="margin-bottom: 30px;">
                <div class="card-header">
                    <h3>Application Timeline</h3>
                </div>
                <div class="card-body">
                    <div class="info-item" style="margin-bottom: 15px;">
                        <div class="info-label">Submitted</div>
                        <div class="info-value"><?= date('M d, Y H:i', strtotime($application['submission_date'])) ?></div>
                    </div>
                    <?php if ($application['review_date']): ?>
                        <div class="info-item" style="margin-bottom: 15px;">
                            <div class="info-label">Under Review</div>
                            <div class="info-value"><?= date('M d, Y H:i', strtotime($application['review_date'])) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($application['interview_date']): ?>
                        <div class="info-item" style="margin-bottom: 15px;">
                            <div class="info-label">Interview Date</div>
                            <div class="info-value"><?= date('M d, Y H:i', strtotime($application['interview_date'])) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($application['decision_date']): ?>
                        <div class="info-item">
                            <div class="info-label">Decision Date</div>
                            <div class="info-value"><?= date('M d, Y H:i', strtotime($application['decision_date'])) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Processed By -->
            <?php if ($application['processor_first_name']): ?>
                <div class="card" style="margin-bottom: 30px;">
                    <div class="card-header">
                        <h3>Processed By</h3>
                    </div>
                    <div class="card-body">
                        <div class="info-item">
                            <div class="info-value"><?= htmlspecialchars($application['processor_first_name'] . ' ' . $application['processor_last_name']) ?></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <?php if ($application['application_status'] != 'Approved' && $application['application_status'] != 'Rejected'): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>Actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="action-buttons">
                            <?php if ($application['application_status'] == 'Submitted'): ?>
                                <button onclick="updateStatus('review')" class="btn btn-warning">Mark for Review</button>
                            <?php endif; ?>
                            
                            <?php if ($application['application_status'] == 'Under Review'): ?>
                                <button onclick="updateStatus('interview')" class="btn btn-primary">Schedule Interview</button>
                            <?php endif; ?>
                            
                            <button onclick="updateStatus('approve')" class="btn btn-success">Approve</button>
                            <button onclick="updateStatus('reject')" class="btn btn-danger">Reject</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal for Status Update -->
<div id="statusModal" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle" style="margin-top: 0;">Update Application Status</h3>
        <form id="statusForm" method="POST">
            <input type="hidden" name="action" id="modalAction">
            
            <div class="form-group">
                <label for="modalNotes">Notes/Reason</label>
                <textarea name="notes" id="modalNotes" rows="4" placeholder="Enter notes or reason..."></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateStatus(action) {
    const titles = {
        'review': 'Mark for Review',
        'interview': 'Schedule Interview',
        'approve': 'Approve Application',
        'reject': 'Reject Application'
    };
    
    const placeholders = {
        'review': 'Enter review notes...',
        'interview': 'Enter interview details...',
        'approve': 'Enter approval notes...',
        'reject': 'Enter rejection reason...'
    };
    
    document.getElementById('modalTitle').textContent = titles[action];
    document.getElementById('modalAction').value = action;
    document.getElementById('modalNotes').placeholder = placeholders[action];
    document.getElementById('statusModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('statusModal').style.display = 'none';
    document.getElementById('statusForm').reset();
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('statusModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>

</body>
</html>
