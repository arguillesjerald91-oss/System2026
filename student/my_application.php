<?php
session_start();
include __DIR__ . '/../db.php';
$database = new Database();
$conn = $database->getConnection();

if (!isset($_SESSION['userId'])) {
    header('Location: ../login.php');
    exit();
}

$userId = $_SESSION['userId'];
$userRole = $_SESSION['userRole'] ?? 'trainee';

if (!in_array($userRole, ['trainee', 'student'])) {
    header("Location: ../login.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

$application = null;
$enrollment = null;
$studentRecord = null;

$stmt = $conn->prepare("SELECT StudID FROM student WHERE user_id = ?");
$stmt->execute([$userId]);
$studentRecord = $stmt->fetch(PDO::FETCH_ASSOC);

if ($studentRecord) {
    $studId = $studentRecord['StudID'];
    
    $stmt = $conn->prepare("
        SELECT spe.*, pea.application_number, pea.application_status, pea.submission_date,
               pea.nc_level as applied_nc_level, pea.first_name, pea.last_name, pea.email_address
        FROM student_program_enrollments spe
        LEFT JOIN pre_enrollment_applications pea ON spe.pre_enroll_id = pea.pre_enroll_id
        WHERE spe.student_id = ?
        ORDER BY spe.enrollment_id DESC LIMIT 1
    ");
    $stmt->execute([$studId]);
    $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($enrollment && $enrollment['pre_enroll_id']) {
        $application = [
            'application_number' => $enrollment['application_number'],
            'application_status' => $enrollment['application_status'],
            'submission_date' => $enrollment['submission_date'],
            'nc_level' => $enrollment['applied_nc_level']
        ];
    } else {
        $stmt = $conn->prepare("
            SELECT application_number, application_status, submission_date, nc_level
            FROM pre_enrollment_applications 
            WHERE email_address = ?
            ORDER BY pre_enroll_id DESC LIMIT 1
        ");
        $stmt->execute([$user['email']]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} else {
    $stmt = $conn->prepare("
        SELECT application_number, application_status, submission_date, nc_level
        FROM pre_enrollment_applications 
        WHERE email_address = ?
        ORDER BY pre_enroll_id DESC LIMIT 1
    ");
    $stmt->execute([$user['email']]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
}

$currentPage = 'my_application.php';
$pageTitle = "My Application";
$pageSubtitle = "Track Your Enrollment Status";
include 'sidebar_student.php';
?>

<div class="page-content">
    
    <!-- Application Status Card -->
    <div class="card" style="max-width: 800px;">
        <div class="card-header">
            <h3 class="card-title">Application Status</h3>
            <?php if ($application && $application['application_number']): ?>
            <span style="font-size: 14px; color: #64748b;"><?= htmlspecialchars($application['application_number']) ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (!$application): ?>
                <div style="text-align: center; padding: 40px; color: #64748b;">
                    <div style="font-size: 48px; margin-bottom: 16px;">📋</div>
                    <h3 style="margin: 0 0 8px 0;">No Application Found</h3>
                    <p>You haven't submitted a pre-enrollment application yet.</p>
                    <a href="../pre_enrollment.php" class="btn" style="padding: 12px 24px; background: #2563eb; color: white; border-radius: 8px; text-decoration: none; display: inline-block; margin-top: 16px;">
                        Submit Application
                    </a>
                </div>
            <?php else: ?>
                <?php 
                $statusColors = [
                    'Pending' => ['bg' => '#fef3c7', 'text' => '#d97706'],
                    'Under Review' => ['bg' => '#dbeafe', 'text' => '#2563eb'],
                    'Qualified' => ['bg' => '#d1fae5', 'text' => '#059669'],
                    'Not Qualified' => ['bg' => '#fee2e2', 'text' => '#dc2626'],
                    'Enrolled' => ['bg' => '#d1fae5', 'text' => '#059669'],
                    'Rejected' => ['bg' => '#fee2e2', 'text' => '#dc2626']
                ];
                $status = $application['application_status'] ?? 'Pending';
                $colors = $statusColors[$status] ?? ['bg' => '#f1f5f9', 'text' => '#64748b'];
                ?>
                
                <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 30px;">
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: <?= $colors['bg'] ?>; display: flex; align-items: center; justify-content: center;">
                        <?php if ($status === 'Enrolled'): ?>
                        <span style="font-size: 36px;">✓</span>
                        <?php elseif (in_array($status, ['Pending', 'Under Review'])): ?>
                        <span style="font-size: 36px;">⏳</span>
                        <?php else: ?>
                        <span style="font-size: 36px;">📋</span>
                        <?php endif; ?>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Current Status</div>
                        <div style="font-size: 24px; font-weight: 700; color: <?= $colors['text'] ?>;">
                            <?= htmlspecialchars($status) ?>
                        </div>
                        <div style="font-size: 14px; color: #64748b; margin-top: 4px;">
                            Submitted: <?= date('M d, Y', strtotime($application['submission_date'] ?? 'now')) ?>
                        </div>
                    </div>
                </div>
                
                <!-- Status Timeline -->
                <div style="position: relative; padding-left: 30px;">
                    <div style="position: absolute; left: 15px; top: 0; bottom: 0; width: 2px; background: #e2e8f0;"></div>
                    
                    <div style="position: relative; margin-bottom: 24px;">
                        <div style="position: absolute; left: -30px; width: 32px; height: 32px; border-radius: 50%; background: #10b981; color: white; display: flex; align-items: center; justify-content: center; font-size: 14px;">1</div>
                        <div style="font-weight: 600;">Application Submitted</div>
                        <div style="font-size: 12px; color: #64748b;">Your application was submitted and is pending review</div>
                    </div>
                    
                    <?php if (in_array($status, ['Under Review', 'Qualified', 'Enrolled'])): ?>
                    <div style="position: relative; margin-bottom: 24px;">
                        <div style="position: absolute; left: -30px; width: 32px; height: 32px; border-radius: 50%; background: #2563eb; color: white; display: flex; align-items: center; justify-content: center; font-size: 14px;">2</div>
                        <div style="font-weight: 600;">Under Review</div>
                        <div style="font-size: 12px; color: #64748b;">Your application is being reviewed by our team</div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (in_array($status, ['Qualified', 'Enrolled'])): ?>
                    <div style="position: relative; margin-bottom: 24px;">
                        <div style="position: absolute; left: -30px; width: 32px; height: 32px; border-radius: 50%; background: #10b981; color: white; display: flex; align-items: center; justify-content: center; font-size: 14px;">✓</div>
                        <div style="font-weight: 600;">Qualified</div>
                        <div style="font-size: 12px; color: #64748b;">Congratulations! You've been qualified for enrollment</div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($status === 'Enrolled' && $enrollment): ?>
                    <div style="position: relative; margin-bottom: 24px;">
                        <div style="position: absolute; left: -30px; width: 32px; height: 32px; border-radius: 50%; background: #10b981; color: white; display: flex; align-items: center; justify-content: center; font-size: 14px;">✓</div>
                        <div style="font-weight: 600;">Enrolled</div>
                        <div style="font-size: 12px; color: #64748b;">You are officially enrolled in the program</div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Enrollment Details (if enrolled) -->
                <?php if ($enrollment): ?>
                <div style="margin-top: 30px; padding: 20px; background: #f8fafc; border-radius: 12px;">
                    <h4 style="margin: 0 0 16px 0; font-size: 16px;">Enrollment Details</h4>
                    <div class="grid-2">
                        <div>
                            <div style="font-size: 12px; color: #64748b;">NC Level</div>
                            <div style="font-weight: 600;"><?= htmlspecialchars($enrollment['nc_level'] ?? 'NC I') ?></div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: #64748b;">Enrollment Status</div>
                            <div style="font-weight: 600; color: #10b981;"><?= htmlspecialchars($enrollment['enrollment_status'] ?? 'Active') ?></div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: #64748b;">Enrollment Date</div>
                            <div style="font-weight: 600;"><?= date('M d, Y', strtotime($enrollment['enrollment_date'] ?? 'now')) ?></div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: #64748b;">Student ID</div>
                            <div style="font-weight: 600;">TESDA-<?= str_pad($studId, 4, '0', STR_PAD_LEFT) ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($status === 'Not Qualified' || $status === 'Rejected'): ?>
                <div style="margin-top: 20px; padding: 16px; background: #fee2e2; border-radius: 8px; color: #dc2626;">
                    <strong>Note:</strong> Your application was not qualified. Please contact support for more information.
                </div>
                <?php endif; ?>
                
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Help Card -->
    <div class="card" style="max-width: 800px; margin-top: 20px;">
        <div class="card-header">
            <h3 class="card-title">Need Help?</h3>
        </div>
        <div class="card-body">
            <p style="color: #64748b; margin-bottom: 16px;">
                If you have questions about your application status or need assistance, please contact our support team.
            </p>
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <a href="notices.php" class="btn" style="padding: 10px 20px; background: #2563eb; color: white; border-radius: 8px; text-decoration: none;">
                    View Notices
                </a>
                <a href="mailto:support@tesda.edu.ph" class="btn" style="padding: 10px 20px; background: white; border: 1px solid #e2e8f0; color: #374151; border-radius: 8px; text-decoration: none;">
                    Contact Support
                </a>
            </div>
        </div>
    </div>
    
</div>

</main>
</div>