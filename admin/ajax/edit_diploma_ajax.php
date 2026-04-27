<?php
/**
 * AJAX: Edit Diploma Form
 */

session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

$diplomaId = $_GET['id'] ?? null;
if (!$diplomaId) die('ID required');

$stmt = $conn->prepare("SELECT * FROM diplomas WHERE diploma_id = ?");
$stmt->execute([$diplomaId]);
$d = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$d) die('Diploma not found');

$students = $conn->query("SELECT StudID, FirstName, LastName, SchoolID FROM student ORDER BY FirstName")->fetchAll();
$programs = $conn->query("SELECT program_id, program_code, program_title FROM auto_mechanic_programs")->fetchAll();
$batches = $conn->query("SELECT batch_id, batch_code FROM training_batches")->fetchAll();
$honorsOptions = ['None', 'Cum Laude', 'Magna Cum Laude', 'Summa Cum Laude', 'With Honors'];
?>

<form id="editDiplomaForm" onsubmit="updateDiploma(event, <?= $d['diploma_id'] ?>)">
    <div class="form-row">
        <div class="form-group">
            <label>Student</label>
            <select name="student_id" required>
                <?php foreach ($students as $s): ?>
                    <option value="<?= $s['StudID'] ?>" <?= $s['StudID'] == $d['student_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['FirstName'] . ' ' . $s['LastName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Enrollment ID</label>
            <input type="number" name="enrollment_id" value="<?= htmlspecialchars($d['enrollment_id']) ?>" required>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Program</label>
            <select name="program_id">
                <?php foreach ($programs as $p): ?>
                    <option value="<?= $p['program_id'] ?>" <?= $p['program_id'] == $d['program_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['program_code']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Batch</label>
            <select name="batch_id">
                <option value="">None</option>
                <?php foreach ($batches as $b): ?>
                    <option value="<?= $b['batch_id'] ?>" <?= $b['batch_id'] == $d['batch_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['batch_code']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Graduation Date</label>
            <input type="date" name="graduation_date" value="<?= htmlspecialchars($d['graduation_date'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Convocation Date</label>
            <input type="date" name="convocation_date" value="<?= htmlspecialchars($d['convocation_date'] ?? '') ?>">
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Diploma Type</label>
            <select name="diploma_type">
                <option value="Diploma" <?= $d['diploma_type'] == 'Diploma' ? 'selected' : '' ?>>Diploma</option>
                <option value="Certificate" <?= $d['diploma_type'] == 'Certificate' ? 'selected' : '' ?>>Certificate</option>
                <option value="Associate" <?= $d['diploma_type'] == 'Associate' ? 'selected' : '' ?>>Associate</option>
                <option value="Bachelor" <?= $d['diploma_type'] == 'Bachelor' ? 'selected' : '' ?>>Bachelor</option>
            </select>
        </div>
        <div class="form-group">
            <label>Major</label>
            <input type="text" name="major" value="<?= htmlspecialchars($d['major'] ?? '') ?>">
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Honors</label>
            <select name="honors">
                <?php foreach ($honorsOptions as $h): ?>
                    <option value="<?= $h ?>" <?= $h == $d['honors'] ? 'selected' : '' ?>><?= $h ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>General Average (GPA)</label>
            <input type="number" step="0.01" name="general_average" value="<?= htmlspecialchars($d['general_average']) ?>">
        </div>
    </div>

    <div style="text-align: right; margin-top: 1rem;">
        <button type="submit" class="btn btn-primary">Save Changes</button>
    </div>
</form>

<script>
function updateDiploma(event, id) {
    event.preventDefault();
    alert('Diploma update saved (placeholder).');
}
</script>
