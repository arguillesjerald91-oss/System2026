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

// Get admin/staff info
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
$fullName = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''));

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $applicationId = $_POST['application_id'] ?? null;
    $action = $_POST['action'];
    $notes = $_POST['notes'] ?? '';
    
    if ($applicationId) {
        try {
            // Get application details for email notification
            $stmt = $conn->prepare("
                SELECT sa.*, pea.first_name, pea.last_name, pea.email_address, pea.contact_number,
                       sp.program_name, sp.program_type
                FROM scholarship_applications sa
                JOIN pre_enrollment_applications pea ON sa.pre_enroll_id = pea.pre_enroll_id
                JOIN scholarship_programs sp ON sa.program_id = sp.program_id
                WHERE sa.scholarship_app_id = ?
            ");
            $stmt->execute([$applicationId]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($application) {
                // Update application status
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
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating application: " . $e->getMessage();
        }
    }
    
    header("Location: manage_applicants.php");
    exit();
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? '';
$searchFilter = $_GET['search'] ?? '';

// Build query
$whereConditions = [];
$params = [];

if ($statusFilter) {
    $whereConditions[] = "sa.application_status = ?";
    $params[] = $statusFilter;
}

if ($searchFilter) {
    $whereConditions[] = "(sa.application_number LIKE ? OR pea.first_name LIKE ? OR pea.last_name LIKE ? OR sp.program_name LIKE ?)";
    $searchParam = "%$searchFilter%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get applications
$sql = "
    SELECT 
        sa.scholarship_app_id,
        sa.application_number,
        sa.application_status,
        sa.submission_date,
        sa.total_score,
        sa.household_income,
        sa.household_members,
        pea.first_name,
        pea.last_name,
        pea.email_address,
        pea.contact_number,
        sp.program_name,
        sp.program_type,
        processor.first_name as processor_first_name,
        processor.last_name as processor_last_name
    FROM scholarship_applications sa
    JOIN pre_enrollment_applications pea ON sa.pre_enroll_id = pea.pre_enroll_id
    JOIN scholarship_programs sp ON sa.program_id = sp.program_id
    LEFT JOIN users processor ON sa.processed_by = processor.user_id
    $whereClause
    ORDER BY sa.submission_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$statsSql = "
    SELECT 
        application_status,
        COUNT(*) as count
    FROM scholarship_applications
    GROUP BY application_status
";
$stmt = $conn->query($statsSql);
$stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
$statsArray = [];
foreach ($stats as $stat) {
    $statsArray[$stat['application_status']] = $stat['count'];
}

$pageTitle = "Manage Applicants";
$pageSubtitle = "Scholarship Application Management";
include 'sidebar_new.php';
?>

<!-- Applicant Management Content -->
<div class="applicant-management">
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Applications</div>
            <div class="stat-value"><?= array_sum($statsArray) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Submitted</div>
            <div class="stat-value"><?= $statsArray['Submitted'] ?? 0 ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Under Review</div>
            <div class="stat-value"><?= $statsArray['Under Review'] ?? 0 ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Approved</div>
            <div class="stat-value"><?= $statsArray['Approved'] ?? 0 ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Rejected</div>
            <div class="stat-value"><?= $statsArray['Rejected'] ?? 0 ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-header">
            <h3 class="card-title">Filters</h3>
        </div>
        <div class="card-body">
            <form method="GET" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Status</label>
                    <select name="status" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;">
                        <option value="">All Status</option>
                        <option value="Submitted" <?= $statusFilter == 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                        <option value="Under Review" <?= $statusFilter == 'Under Review' ? 'selected' : '' ?>>Under Review</option>
                        <option value="For Interview" <?= $statusFilter == 'For Interview' ? 'selected' : '' ?>>For Interview</option>
                        <option value="Approved" <?= $statusFilter == 'Approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="Rejected" <?= $statusFilter == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="Waitlisted" <?= $statusFilter == 'Waitlisted' ? 'selected' : '' ?>>Waitlisted</option>
                    </select>
                </div>
                <div style="flex: 2; min-width: 300px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Search</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($searchFilter) ?>" 
                           placeholder="Search by name, application number, or program..." 
                           style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;">
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="manage_applicants.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Applications Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Scholarship Applications</h3>
        </div>
        <div class="card-body">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success" style="background: #d1fae5; color: #065f46; padding: 12px; border-radius: 6px; margin-bottom: 15px;">
                    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger" style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 15px;">
                    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($applications)): ?>
                <p style="text-align: center; color: #6b7280; padding: 40px;">No applications found.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; min-width: 1200px;">
                        <thead>
                            <tr style="background: #f9fafb; border-bottom: 2px solid #e5e7eb;">
                                <th style="padding: 12px; text-align: left;">Application #</th>
                                <th style="padding: 12px; text-align: left;">Applicant</th>
                                <th style="padding: 12px; text-align: left;">Program</th>
                                <th style="padding: 12px; text-align: left;">Status</th>
                                <th style="padding: 12px; text-align: left;">Score</th>
                                <th style="padding: 12px; text-align: left;">Submitted</th>
                                <th style="padding: 12px; text-align: left;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr style="border-bottom: 1px solid #e5e7eb;">
                                    <td style="padding: 12px;">
                                        <strong><?= htmlspecialchars($app['application_number']) ?></strong>
                                    </td>
                                    <td style="padding: 12px;">
                                        <div>
                                            <strong><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></strong><br>
                                            <small style="color: #6b7280;"><?= htmlspecialchars($app['email_address']) ?></small>
                                        </div>
                                    </td>
                                    <td style="padding: 12px;">
                                        <div>
                                            <?= htmlspecialchars($app['program_name']) ?><br>
                                            <small style="color: #6b7280;"><?= htmlspecialchars($app['program_type']) ?></small>
                                        </div>
                                    </td>
                                    <td style="padding: 12px;">
                                        <?php
                                        $statusColors = [
                                            'Submitted' => 'badge-blue',
                                            'Under Review' => 'badge-yellow',
                                            'For Interview' => 'badge-purple',
                                            'Approved' => 'badge-green',
                                            'Rejected' => 'badge-red',
                                            'Waitlisted' => 'badge-orange'
                                        ];
                                        $badgeClass = $statusColors[$app['application_status']] ?? 'badge-gray';
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($app['application_status']) ?></span>
                                    </td>
                                    <td style="padding: 12px;">
                                        <?= $app['total_score'] ? number_format($app['total_score'], 1) : 'N/A' ?>
                                    </td>
                                    <td style="padding: 12px;">
                                        <small><?= date('M d, Y', strtotime($app['submission_date'])) ?></small>
                                    </td>
                                    <td style="padding: 12px;">
                                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                            <button onclick="reviewApplication(<?= $app['scholarship_app_id'] ?>)" 
                                                    class="btn btn-sm btn-info" style="padding: 4px 8px; font-size: 12px;">
                                                Review
                                            </button>
                                            
                                            <?php if ($app['application_status'] != 'Approved' && $app['application_status'] != 'Rejected'): ?>
                                                <button onclick="updateStatus(<?= $app['scholarship_app_id'] ?>, 'approve')" 
                                                        class="btn btn-sm btn-success" style="padding: 4px 8px; font-size: 12px;">
                                                    Approve
                                                </button>
                                                <button onclick="updateStatus(<?= $app['scholarship_app_id'] ?>, 'reject')" 
                                                        class="btn btn-sm btn-danger" style="padding: 4px 8px; font-size: 12px;">
                                                    Reject
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal for Status Update -->
<div id="statusModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 10px; width: 90%; max-width: 500px;">
        <h3 id="modalTitle" style="margin-top: 0;">Update Application Status</h3>
        <form id="statusForm" method="POST">
            <input type="hidden" name="application_id" id="modalApplicationId">
            <input type="hidden" name="action" id="modalAction">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Notes/Reason</label>
                <textarea name="notes" id="modalNotes" rows="4" 
                          style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;"
                          placeholder="Enter approval notes or rejection reason..."></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
function reviewApplication(appId) {
    window.open('applicant_details.php?id=' + appId, '_blank', 'width=1000,height=800,scrollbars=yes');
}

function updateStatus(appId, action) {
    const titles = {
        'approve': 'Approve Application',
        'reject': 'Reject Application'
    };
    
    const placeholders = {
        'approve': 'Enter approval notes...',
        'reject': 'Enter rejection reason...'
    };
    
    document.getElementById('modalTitle').textContent = titles[action];
    document.getElementById('modalApplicationId').value = appId;
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

</div><!-- End page-content -->
</main>
</div><!-- End main-wrapper -->
</body>
</html>
