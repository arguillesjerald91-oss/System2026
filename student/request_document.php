<?php
/**
 * Student Document Request Form
 */

session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
$userType = $_SESSION['user_type'];
if (!in_array($userType, ['student', 'trainee'])) {
    header("Location: ../login.php");
    exit();
}
$userId = $_SESSION['user_id'];

// Get student
$studentStmt = $conn->prepare("SELECT * FROM student WHERE user_id = ?");
$studentStmt->execute([$userId]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);
if (!$student) die('Student not found');
$studentId = $student['StudID'];

// Check enrollment status and get NC level
$enrollStmt = $conn->prepare("SELECT nc_level FROM student_program_enrollments WHERE student_id = ? AND enrollment_status = 'Active' LIMIT 1");
$enrollStmt->execute([$studentId]);
$ncLevel = $enrollStmt->fetchColumn() ?: 'NC I';
$isEnrolled = !empty($ncLevel);
$studentNcLevel = $ncLevel;

if (!$isEnrolled) {
    header("Location: my_application.php?error=not_enrolled");
    exit();
}

$prefillType = $_GET['type'] ?? '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $docType = $_POST['document_type'];
    $purpose = $_POST['purpose'];
    $copies = $_POST['copies'] ?? 1;
    $urgent = $_POST['urgent'] ?? 0;
    $details = $_POST['details'] ?? '';
    $collection = $_POST['collection_method'] ?? 'Pickup';

    // Determine department and default payment
    $deptMap = [
        'Official Transcript' => 'Registrar',
        'Transcript Copy' => 'Registrar',
        'Certificate' => 'Certification',
        'Diploma' => 'Registrar',
        'Certification' => 'Certification',
        'ID' => 'Admission',
        'Good Moral' => 'Registrar',
        'Honorable Dismissal' => 'Registrar',
        'Other' => 'Registrar'
    ];
    $department = $deptMap[$docType] ?? 'Registrar';

    // Payment logic (placeholder)
    $paymentRequired = 0;
    if ($docType === 'Official Transcript') $paymentRequired = 150;
    if ($docType === 'Certificate') $paymentRequired = 200;
    if ($docType === 'Diploma Replacement') $paymentRequired = 500;
    if ($urgent) $paymentRequired += 100;

    // Generate request number
    $year = date('Y');
    $requestNum = "REQ-$year-" . date('His') . rand(100,999);

    try {
        $conn->beginTransaction();
        $insert = $conn->prepare("
            INSERT INTO document_requests (
                request_number, student_id, document_type, purpose, details,
                copies_requested, urgent, collection_method,
                department, payment_required, status, request_date, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', CURDATE(), NOW())
        ");
        $insert->execute([
            $requestNum, $studentId, $docType, $purpose, $details,
            $copies, $urgent, $collection, $department, $paymentRequired
        ]);
        $conn->commit();
        $success = "Request submitted successfully. Request #: $requestNum";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get my past requests
$myRequests = $conn->prepare("
    SELECT * FROM document_requests 
    WHERE student_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$myRequests->execute([$studentId]);
$requests = $myRequests->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Request Documents";
$currentPage = 'request_document.php';
include 'sidebar_student.php';
?>

<div class="content-header">
    <h2><i class="fas fa-file-import"></i> Request Documents</h2>
    <p class="text-muted">Submit requests for transcripts, certificates, diplomas, or other documents - <strong><?= htmlspecialchars($studentNcLevel) ?></strong></p>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<style>
.doc-type-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: white;
    text-align: center;
}
.doc-type-card:hover {
    border-color: #3b82f6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.doc-type-card.selected {
    border-color: #2563eb;
    background: #eff6ff;
}
.doc-type-card i {
    font-size: 32px;
    margin-bottom: 10px;
    color: #3b82f6;
}
.doc-type-card span {
    font-weight: 600;
    font-size: 14px;
}
.doc-type-card small {
    display: block;
    color: #64748b;
    font-size: 11px;
    margin-top: 5px;
}
.fee-summary {
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    border-radius: 12px;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #bae6fd;
}
.fee-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #e2e8f0;
}
.fee-row:last-child {
    border-bottom: none;
    font-size: 18px;
    font-weight: bold;
    color: #0369a1;
}
.fee-row.total {
    margin-top: 10px;
    padding-top: 15px;
    border-top: 2px solid #3b82f6;
}
.processing-time {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    background: #f0fdf4;
    border-radius: 8px;
    color: #166534;
    font-size: 14px;
}
.processing-time.urgent {
    background: #fef3c7;
    color: #92400e;
}
</style>

<div class="card" style="margin-bottom: 25px;">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-plus-circle"></i> New Document Request</h3>
        <span class="badge badge-primary"><?= htmlspecialchars($studentNcLevel) ?></span>
    </div>
    <div class="card-body">
        <form method="POST" id="requestForm">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 600; margin-bottom: 10px;">Select Document Type *</label>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px;">
                    <?php 
                    $docTypes = [
                        ['value' => 'Official Transcript', 'icon' => 'fa-file-alt', 'fee' => 150, 'time' => '3-5 days', 'desc' => 'Original sealed'],
                        ['value' => 'Transcript Copy', 'icon' => 'fa-copy', 'fee' => 100, 'time' => '2-3 days', 'desc' => 'Plain copy'],
                        ['value' => 'Certificate', 'icon' => 'fa-certificate', 'fee' => 200, 'time' => '5-7 days', 'desc' => 'Competency'],
                        ['value' => 'Diploma', 'icon' => 'fa-graduation-cap', 'fee' => 350, 'time' => '7-10 days', 'desc' => 'Conferment'],
                        ['value' => 'Good Moral', 'icon' => 'fa-heart', 'fee' => 100, 'time' => '2-3 days', 'desc' => 'Character'],
                        ['value' => 'ID', 'icon' => 'fa-id-card', 'fee' => 200, 'time' => '3-5 days', 'desc' => 'School ID'],
                    ];
                    foreach($docTypes as $dt): ?>
                    <div class="doc-type-card" onclick="selectDocType('<?= $dt['value'] ?>')" id="card-<?= str_replace(' ', '-', $dt['value']) ?>">
                        <i class="fas <?= $dt['icon'] ?>"></i>
                        <span><?= $dt['value'] ?></span>
                        <small>₱<?= $dt['fee'] ?> · <?= $dt['time'] ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="document_type" id="docType" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label>Purpose *</label>
                    <select name="purpose" required style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <option value="Employment">For Employment</option>
                        <option value="Studies">For Further Studies</option>
                        <option value="Scholarship">For Scholarship Application</option>
                        <option value="Personal">Personal Use</option>
                        <option value="Agency">For Government Agency</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Number of Copies *</label>
                    <input type="number" name="copies" id="copies" value="1" min="1" max="5" required style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px;" onchange="updatePayment()">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label>Collection Method *</label>
                    <select name="collection_method" required style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <option value="Pickup">📍 Pickup at Campus Registrar</option>
                        <option value="Mail">📬 Mail Delivery (₱50 fee)</option>
                        <option value="Email">📧 Email (Digital PDF)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Priority</label>
                    <label style="display: flex; align-items: center; gap: 10px; padding: 12px; border: 2px solid #fef3c7; border-radius: 8px; background: #fefce8; cursor: pointer;">
                        <input type="checkbox" name="urgent" id="urgent" value="1" style="width: 20px; height: 20px;" onchange="updatePayment()">
                        <span>⚡ Urgent Processing (+₱100)</span>
                    </label>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label>Additional Instructions</label>
                <textarea name="details" rows="3" placeholder="Any specific instructions, notes, or special requirements..." style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit;"></textarea>
            </div>

            <!-- Fee Summary -->
            <div class="fee-summary">
                <h4 style="margin: 0 0 15px 0; color: #0369a1;"><i class="fas fa-calculator"></i> Fee Summary</h4>
                <div class="fee-row">
                    <span>Document Fee</span>
                    <span id="baseFee">₱0.00</span>
                </div>
                <div class="fee-row" id="copiesRow" style="display: none;">
                    <span>Additional Copies</span>
                    <span id="copiesFee">₱0.00</span>
                </div>
                <div class="fee-row" id="urgentRow" style="display: none;">
                    <span>Urgent Processing</span>
                    <span>+₱100.00</span>
                </div>
                <div class="fee-row" id="mailRow" style="display: none;">
                    <span>Mail Delivery</span>
                    <span>+₱50.00</span>
                </div>
                <div class="fee-row total">
                    <span>Total Estimated</span>
                    <span id="totalFee">₱0.00</span>
                </div>
            </div>

            <!-- Processing Time -->
            <div class="processing-time" id="processingTime">
                <i class="fas fa-clock"></i>
                <span>Standard Processing: <strong id="procTime">3-5 business days</strong></span>
            </div>

            <div style="margin-top: 25px;">
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 15px; font-size: 16px; font-weight: 600;">
                    <i class="fas fa-paper-plane"></i> Submit Request
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const docFees = {
    'Official Transcript': { fee: 150, time: '3-5 business days', copies: 50 },
    'Transcript Copy': { fee: 100, time: '2-3 business days', copies: 30 },
    'Certificate': { fee: 200, time: '5-7 business days', copies: 50 },
    'Diploma': { fee: 350, time: '7-10 business days', copies: 100 },
    'Diploma Replacement': { fee: 500, time: '10-14 business days', copies: 150 },
    'Good Moral': { fee: 100, time: '2-3 business days', copies: 30 },
    'Honorable Dismissal': { fee: 150, time: '3-5 business days', copies: 50 },
    'ID': { fee: 200, time: '3-5 business days', copies: 50 },
    'Other': { fee: 100, time: '3-5 business days', copies: 30 }
};

function selectDocType(type) {
    document.querySelectorAll('.doc-type-card').forEach(c => c.classList.remove('selected'));
    document.getElementById('card-' + type.replace(' ', '-')).classList.add('selected');
    document.getElementById('docType').value = type;
    updatePayment();
}

function updatePayment() {
    const type = document.getElementById('docType').value;
    const copies = parseInt(document.getElementById('copies').value) || 1;
    const urgent = document.getElementById('urgent').checked;
    const collection = document.querySelector('select[name="collection_method"]').value;
    
    const data = docFees[type] || { fee: 100, time: '3-5 business days', copies: 30 };
    
    let baseFee = data.fee;
    let copiesFee = (copies > 1) ? (copies - 1) * data.copies : 0;
    let urgentFee = urgent ? 100 : 0;
    let mailFee = (collection === 'Mail') ? 50 : 0;
    let total = baseFee + copiesFee + urgentFee + mailFee;
    
    document.getElementById('baseFee').textContent = '₱' + baseFee.toFixed(2);
    document.getElementById('copiesFee').textContent = '₱' + copiesFee.toFixed(2);
    document.getElementById('copiesRow').style.display = copiesFee > 0 ? 'flex' : 'none';
    document.getElementById('urgentRow').style.display = urgentFee > 0 ? 'flex' : 'none';
    document.getElementById('mailRow').style.display = mailFee > 0 ? 'flex' : 'none';
    document.getElementById('totalFee').textContent = '₱' + total.toFixed(2);
    
    document.getElementById('procTime').textContent = urgent ? '1-2 business days' : data.time;
    
    const procTimeDiv = document.getElementById('processingTime');
    if (urgent) {
        procTimeDiv.className = 'processing-time urgent';
    } else {
        procTimeDiv.className = 'processing-time';
    }
}
</script>

<!-- Past Requests -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">My Past Requests</h3>
    </div>
    <div class="card-body">
        <?php if (empty($requests)): ?>
            <p class="text-muted">No previous requests.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Request #</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $r): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r['request_number']) ?></strong></td>
                        <td><?= htmlspecialchars($r['document_type']) ?></td>
                        <td><?= date('M j, Y', strtotime($r['request_date'])) ?></td>
                        <td>
                            <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $r['status'])) ?>">
                                <?= htmlspecialchars($r['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($r['payment_status'] === 'Paid'): ?>
                                <span class="text-success">Paid</span>
                            <?php else: ?>
                                <?= '₱' . number_format($r['payment_required'], 2) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</main>
</div>
