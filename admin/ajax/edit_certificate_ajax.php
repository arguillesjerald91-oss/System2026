<?php
/**
 * AJAX: Edit Certificate Form
 */

session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

$certId = $_GET['id'] ?? null;
if (!$certId) die('ID required');

$stmt = $conn->prepare("SELECT * FROM certificates WHERE certificate_id = ?");
$stmt->execute([$certId]);
$c = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$c) die('Certificate not found');

$students = $conn->query("SELECT StudID, FirstName, LastName, SchoolID FROM student ORDER BY FirstName")->fetchAll();
$programs = $conn->query("SELECT program_id, program_code, program_title FROM auto_mechanic_programs")->fetchAll();
$certTypes = ['Certificate of Completion','Competency Certificate','NC Certificate','Skill Certificate','Completion','Achievement','Custom'];
$ncLevels = ['NC I','NC II','NC III','NC IV','Diploma','Special'];

// Get assigned modules
$assignedMods = $conn->prepare("SELECT module_id FROM certificate_competencies WHERE certificate_id = ?");
$assignedMods->execute([$certId]);
$assigned = $assignedMods->fetchAll(PDO::FETCH_COLUMN);

$allModules = $conn->query("SELECT module_id, module_code, module_title FROM training_modules ORDER BY module_code")->fetchAll();
?>

<form id="editCertificateForm" onsubmit="updateCertificate(event, <?= $c['certificate_id'] ?>)">
    <div class="form-row">
        <div class="form-group">
            <label>Student</label>
            <select name="student_id" required>
                <?php foreach ($students as $s): ?>
                    <option value="<?= $s['StudID'] ?>" <?= $s['StudID'] == $c['student_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['FirstName'] . ' ' . $s['LastName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Enrollment ID</label>
            <input type="number" name="enrollment_id" value="<?= htmlspecialchars($c['enrollment_id']) ?>" required>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Certificate Type</label>
            <select name="certificate_type" id="certTypeSelect" required>
                <?php foreach ($certTypes as $ct): ?>
                    <option value="<?= $ct ?>" <?= $ct == $c['certificate_type'] ? 'selected' : '' ?>><?= $ct ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>NC Level</label>
            <select name="nc_level" id="ncLevelSelect">
                <option value="">None</option>
                <?php foreach ($ncLevels as $nl): ?>
                    <option value="<?= $nl ?>" <?= $nl == $c['nc_level'] ? 'selected' : '' ?>><?= $nl ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Program</label>
            <select name="program_id">
                <?php foreach ($programs as $p): ?>
                    <option value="<?= $p['program_id'] ?>" <?= $p['program_id'] == $c['program_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['program_code']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Issue Date</label>
            <input type="date" name="issue_date" value="<?= htmlspecialchars($c['issue_date']) ?>" required>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Valid Until</label>
            <input type="date" name="valid_until" value="<?= htmlspecialchars($c['valid_until'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Honors</label>
            <input type="text" name="honors" value="<?= htmlspecialchars($c['honors'] ?? '') ?>">
        </div>
    </div>

    <div class="form-group">
        <label>Title</label>
        <input type="text" name="title" value="<?= htmlspecialchars($c['title'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label>Description</label>
        <textarea name="description" rows="3"><?= htmlspecialchars($c['description'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
        <label>Included Modules</label>
        <div style="max-height: 150px; overflow-y: auto; border: 1px solid #e5e7eb; padding: 10px; border-radius: 4px;">
            <?php foreach ($allModules as $m): ?>
                <label style="display: block; margin-bottom: 5px;">
                    <input type="checkbox" name="modules[]" value="<?= $m['module_id'] ?>" <?= in_array($m['module_id'], $assigned) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($m['module_code'] . ' - ' . $m['module_title']) ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div style="text-align: right; margin-top: 1rem;">
        <button type="submit" class="btn btn-primary">Save Changes</button>
    </div>
</form>

<script>
function updateCertificate(event, id) {
    event.preventDefault();
    alert('Certificate update placeholder. Implement save logic.');
}
</script>
