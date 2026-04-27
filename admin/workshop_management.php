<?php
session_start();
include __DIR__ . '/../db.php';
$database = new Database();
$conn = $database->getConnection();

// Check if user is logged in and is admin
if (!isset($_SESSION['userId']) || $_SESSION['userRole'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Get workshop equipment
$equipment = $conn->query("
    SELECT we.*, 
           (SELECT COUNT(*) FROM equipment_reservations er 
            WHERE er.equipment_id = we.equipment_id 
            AND er.reservation_date >= CURDATE() 
            AND er.reservation_status = 'Approved') as active_reservations
    FROM workshop_equipment we 
    ORDER BY we.equipment_category, we.equipment_name
")->fetchAll(PDO::FETCH_ASSOC);

// Get equipment categories
$categories = $conn->query("SELECT DISTINCT equipment_category FROM workshop_equipment ORDER BY equipment_category")->fetchAll(PDO::FETCH_COLUMN);

// Handle equipment management actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    switch ($_POST['action']) {
        case 'add_equipment':
            $stmt = $conn->prepare("
                INSERT INTO workshop_equipment 
                (equipment_code, equipment_name, equipment_category, equipment_description, 
                 quantity, manufacturer, model, serial_number, purchase_date, purchase_cost, 
                 maintenance_schedule, equipment_status, location)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['equipment_code'],
                $_POST['equipment_name'],
                $_POST['equipment_category'],
                $_POST['equipment_description'] ?? null,
                $_POST['quantity'],
                $_POST['manufacturer'] ?? null,
                $_POST['model'] ?? null,
                $_POST['serial_number'] ?? null,
                !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null,
                !empty($_POST['purchase_cost']) ? $_POST['purchase_cost'] : null,
                $_POST['maintenance_schedule'] ?? null,
                $_POST['equipment_status'],
                $_POST['location'] ?? null
            ]);
            break;
            
        case 'update_equipment':
            $stmt = $conn->prepare("
                UPDATE workshop_equipment 
                SET equipment_name = ?, equipment_category = ?, equipment_description = ?,
                    quantity = ?, manufacturer = ?, model = ?, serial_number = ?,
                    purchase_date = ?, purchase_cost = ?, maintenance_schedule = ?,
                    equipment_status = ?, location = ?, last_maintenance_date = ?,
                    next_maintenance_date = ?
                WHERE equipment_id = ?
            ");
            $stmt->execute([
                $_POST['equipment_name'],
                $_POST['equipment_category'],
                $_POST['equipment_description'] ?? null,
                $_POST['quantity'],
                $_POST['manufacturer'] ?? null,
                $_POST['model'] ?? null,
                $_POST['serial_number'] ?? null,
                !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null,
                !empty($_POST['purchase_cost']) ? $_POST['purchase_cost'] : null,
                $_POST['maintenance_schedule'] ?? null,
                $_POST['equipment_status'],
                $_POST['location'] ?? null,
                !empty($_POST['last_maintenance_date']) ? $_POST['last_maintenance_date'] : null,
                !empty($_POST['next_maintenance_date']) ? $_POST['next_maintenance_date'] : null,
                $_POST['equipment_id']
            ]);
            break;
            
        case 'delete_equipment':
            $stmt = $conn->prepare("DELETE FROM workshop_equipment WHERE equipment_id = ?");
            $stmt->execute([$_POST['equipment_id']]);
            break;
    }
    
    header('Location: workshop_management.php');
    exit;
}

// Get equipment reservations
$reservations = $conn->query("
    SELECT er.*, we.equipment_name, we.equipment_category,
           CASE 
               WHEN er.user_type = 'student' THEN CONCAT(s.FirstName, ' ', s.LastName)
               WHEN er.user_type = 'admin' THEN CONCAT(a.Fname, ' ', a.Lname)
               ELSE 'Unknown User'
           END as user_name,
           tb.batch_name
    FROM equipment_reservations er
    JOIN workshop_equipment we ON er.equipment_id = we.equipment_id
    LEFT JOIN student s ON er.user_id = s.StudID AND er.user_type = 'student'
    LEFT JOIN admins a ON er.user_id = a.admin_id AND er.user_type = 'admin'
    LEFT JOIN training_batches tb ON er.batch_id = tb.batch_id
    ORDER BY er.reservation_date DESC, er.start_time
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Workshop Management - TESDA Auto Mechanic Training Centre</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; }
.container { max-width: 1400px; margin: 0 auto; padding: 20px; }
.header { background: linear-gradient(135deg, #ea580c, #f97316); color: white; padding: 40px 20px; border-radius: 12px; margin-bottom: 30px; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
.stat-card { background: white; padding: 25px; border-radius: 12px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
.stat-number { font-size: 36px; font-weight: bold; color: #ea580c; margin-bottom: 10px; }
.stat-label { color: #6b7280; font-size: 14px; }
.tabs { display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #e5e7eb; }
.tab { padding: 12px 24px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-weight: 600; color: #6b7280; transition: all 0.3s ease; }
.tab.active { color: #ea580c; border-bottom-color: #ea580c; }
.tab:hover { color: #ea580c; }
.tab-content { display: none; }
.tab-content.active { display: block; }
.form-container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 30px; }
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #374151; }
.form-group input, .form-group select, .form-group textarea {
    width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px;
    transition: border-color 0.3s ease;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    outline: none; border-color: #ea580c; box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.1);
}
.btn { padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-block; }
.btn-primary { background: #ea580c; color: white; }
.btn-primary:hover { background: #c2410c; transform: translateY(-2px); }
.btn-success { background: #10b981; color: white; }
.btn-success:hover { background: #059669; }
.btn-danger { background: #dc2626; color: white; }
.btn-danger:hover { background: #b91c1c; }
.btn-secondary { background: #6b7280; color: white; }
.btn-secondary:hover { background: #4b5563; }
.equipment-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
.equipment-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: all 0.3s ease; }
.equipment-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
.equipment-header { background: linear-gradient(135deg, #f97316, #ea580c); color: white; padding: 20px; }
.equipment-name { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
.equipment-code { font-size: 14px; opacity: 0.9; }
.equipment-content { padding: 20px; }
.equipment-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
.category-badge { background: #fed7aa; color: #c2410c; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
.status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
.status-available { background: #d1fae5; color: #065f46; }
.status-in-use { background: #fef3c7; color: #92400e; }
.status-maintenance { background: #fee2e2; color: #991b1b; }
.equipment-info { color: #6b7280; font-size: 14px; line-height: 1.5; margin-bottom: 15px; }
.equipment-actions { display: flex; gap: 10px; }
.table-container { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
table { width: 100%; border-collapse: collapse; }
th { background: #f8fafc; padding: 15px; text-align: left; font-weight: 600; color: #374151; border-bottom: 2px solid #e5e7eb; }
td { padding: 15px; border-bottom: 1px solid #e5e7eb; }
tr:hover { background: #f8fafc; }
.search-box { margin-bottom: 20px; }
.search-box input { width: 100%; max-width: 400px; padding: 10px 15px; border: 2px solid #e5e7eb; border-radius: 8px; }
.modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
.modal-content { background: white; margin: 50px auto; padding: 30px; border-radius: 12px; max-width: 600px; max-height: 80vh; overflow-y: auto; position: relative; }
.close { position: absolute; top: 15px; right: 20px; font-size: 28px; cursor: pointer; color: #6b7280; }
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🔧 Workshop Management</h1>
        <p>Manage equipment, tools, and facilities for Auto Mechanic Training</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?= count($equipment) ?></div>
            <div class="stat-label">Total Equipment</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= count(array_filter($equipment, fn($e) => $e['equipment_status'] === 'Available')) ?></div>
            <div class="stat-label">Available</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= count(array_filter($equipment, fn($e) => $e['equipment_status'] === 'In Use')) ?></div>
            <div class="stat-label">In Use</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= count(array_filter($reservations, fn($r) => $r['reservation_status'] === 'Approved')) ?></div>
            <div class="stat-label">Active Reservations</div>
        </div>
    </div>

    <div class="tabs">
        <button class="tab active" onclick="showTab('equipment')">Equipment</button>
        <button class="tab" onclick="showTab('reservations')">Reservations</button>
        <button class="tab" onclick="showTab('maintenance')">Maintenance</button>
        <button class="tab" onclick="showTab('add')">Add Equipment</button>
    </div>

    <!-- Equipment Tab -->
    <div id="equipment" class="tab-content active">
        <div class="search-box">
            <input type="text" placeholder="Search equipment..." onkeyup="filterEquipment()">
        </div>
        
        <div class="equipment-grid" id="equipmentGrid">
            <?php foreach ($equipment as $item): ?>
            <div class="equipment-card" data-name="<?= strtolower($item['equipment_name']) ?>" data-category="<?= strtolower($item['equipment_category']) ?>">
                <div class="equipment-header">
                    <div class="equipment-name"><?= htmlspecialchars($item['equipment_name']) ?></div>
                    <div class="equipment-code"><?= htmlspecialchars($item['equipment_code']) ?></div>
                </div>
                <div class="equipment-content">
                    <div class="equipment-meta">
                        <span class="category-badge"><?= htmlspecialchars($item['equipment_category']) ?></span>
                        <span class="status-badge status-<?= str_replace(' ', '-', strtolower($item['equipment_status'])) ?>">
                            <?= $item['equipment_status'] ?>
                        </span>
                    </div>
                    
                    <div class="equipment-info">
                        <strong>Quantity:</strong> <?= $item['available_quantity'] ?>/<?= $item['quantity'] ?><br>
                        <?php if ($item['manufacturer']): ?>
                        <strong>Manufacturer:</strong> <?= htmlspecialchars($item['manufacturer']) ?><br>
                        <?php endif; ?>
                        <?php if ($item['location']): ?>
                        <strong>Location:</strong> <?= htmlspecialchars($item['location']) ?><br>
                        <?php endif; ?>
                        <?php if ($item['active_reservations'] > 0): ?>
                        <strong>Active Reservations:</strong> <?= $item['active_reservations'] ?><br>
                        <?php endif; ?>
                    </div>
                    
                    <div class="equipment-actions">
                        <button class="btn btn-secondary" onclick="editEquipment(<?= htmlspecialchars(json_encode($item)) ?>)">
                            Edit
                        </button>
                        <button class="btn btn-primary" onclick="reserveEquipment(<?= $item['equipment_id'] ?>)">
                            Reserve
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Reservations Tab -->
    <div id="reservations" class="tab-content">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Equipment</th>
                        <th>User</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $reservation): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($reservation['equipment_name']) ?></strong><br>
                            <small><?= htmlspecialchars($reservation['equipment_category']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($reservation['user_name']) ?></td>
                        <td><?= date('M d, Y', strtotime($reservation['reservation_date'])) ?></td>
                        <td><?= date('H:i', strtotime($reservation['start_time'])) ?> - <?= date('H:i', strtotime($reservation['end_time'])) ?></td>
                        <td><?= htmlspecialchars($reservation['purpose']) ?></td>
                        <td>
                            <span class="status-badge status-<?= str_replace(' ', '-', strtolower($reservation['reservation_status'])) ?>">
                                <?= $reservation['reservation_status'] ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($reservation['reservation_status'] === 'Pending'): ?>
                                <button class="btn btn-success" onclick="approveReservation(<?= $reservation['reservation_id'] ?>)">
                                    Approve
                                </button>
                                <button class="btn btn-danger" onclick="rejectReservation(<?= $reservation['reservation_id'] ?>)">
                                    Reject
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Maintenance Tab -->
    <div id="maintenance" class="tab-content">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Equipment</th>
                        <th>Last Maintenance</th>
                        <th>Next Maintenance</th>
                        <th>Schedule</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($equipment as $item): ?>
                    <?php if ($item['maintenance_schedule']): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($item['equipment_name']) ?></strong><br>
                            <small><?= htmlspecialchars($item['equipment_code']) ?></small>
                        </td>
                        <td><?= $item['last_maintenance_date'] ? date('M d, Y', strtotime($item['last_maintenance_date'])) : 'Never' ?></td>
                        <td><?= $item['next_maintenance_date'] ? date('M d, Y', strtotime($item['next_maintenance_date'])) : 'Not Scheduled' ?></td>
                        <td><?= htmlspecialchars($item['maintenance_schedule']) ?></td>
                        <td>
                            <?php 
                            $status = 'Up to Date';
                            if ($item['next_maintenance_date'] && strtotime($item['next_maintenance_date']) < strtotime('+30 days')) {
                                $status = 'Due Soon';
                            }
                            if ($item['next_maintenance_date'] && strtotime($item['next_maintenance_date']) < strtotime('today')) {
                                $status = 'Overdue';
                            }
                            ?>
                            <span class="status-badge status-<?= str_replace(' ', '-', strtolower($status)) ?>">
                                <?= $status ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-secondary" onclick="scheduleMaintenance(<?= $item['equipment_id'] ?>)">
                                Schedule Maintenance
                            </button>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Equipment Tab -->
    <div id="add" class="tab-content">
        <div class="form-container">
            <h3>Add New Equipment</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_equipment">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Equipment Code *</label>
                        <input type="text" name="equipment_code" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Equipment Name *</label>
                        <input type="text" name="equipment_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Category *</label>
                        <select name="equipment_category" required>
                            <option value="">Select Category</option>
                            <option value="Hand Tools">Hand Tools</option>
                            <option value="Power Tools">Power Tools</option>
                            <option value="Diagnostic Equipment">Diagnostic Equipment</option>
                            <option value="Lifting Equipment">Lifting Equipment</option>
                            <option value="Safety Equipment">Safety Equipment</option>
                            <option value="Specialized Tools">Specialized Tools</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Quantity *</label>
                        <input type="number" name="quantity" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Manufacturer</label>
                        <input type="text" name="manufacturer">
                    </div>
                    
                    <div class="form-group">
                        <label>Model</label>
                        <input type="text" name="model">
                    </div>
                    
                    <div class="form-group">
                        <label>Serial Number</label>
                        <input type="text" name="serial_number">
                    </div>
                    
                    <div class="form-group">
                        <label>Purchase Date</label>
                        <input type="date" name="purchase_date">
                    </div>
                    
                    <div class="form-group">
                        <label>Purchase Cost</label>
                        <input type="number" name="purchase_cost" step="0.01" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label>Maintenance Schedule</label>
                        <input type="text" name="maintenance_schedule" placeholder="e.g., Every 6 months">
                    </div>
                    
                    <div class="form-group">
                        <label>Status *</label>
                        <select name="equipment_status" required>
                            <option value="Available">Available</option>
                            <option value="In Use">In Use</option>
                            <option value="Under Maintenance">Under Maintenance</option>
                            <option value="Damaged">Damaged</option>
                            <option value="Lost">Lost</option>
                            <option value="Retired">Retired</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="equipment_description" rows="3" placeholder="Equipment description and specifications..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Add Equipment</button>
            </form>
        </div>
    </div>
</div>

<!-- Edit Equipment Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h3>Edit Equipment</h3>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="update_equipment">
            <input type="hidden" name="equipment_id" id="edit_equipment_id">
            
            <div class="form-grid">
                <div class="form-group">
                    <label>Equipment Name *</label>
                    <input type="text" name="equipment_name" id="edit_name" required>
                </div>
                
                <div class="form-group">
                    <label>Category *</label>
                    <select name="equipment_category" id="edit_category" required>
                        <option value="Hand Tools">Hand Tools</option>
                        <option value="Power Tools">Power Tools</option>
                        <option value="Diagnostic Equipment">Diagnostic Equipment</option>
                        <option value="Lifting Equipment">Lifting Equipment</option>
                        <option value="Safety Equipment">Safety Equipment</option>
                        <option value="Specialized Tools">Specialized Tools</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Quantity *</label>
                    <input type="number" name="quantity" id="edit_quantity" min="1" required>
                </div>
                
                <div class="form-group">
                    <label>Status *</label>
                    <select name="equipment_status" id="edit_status" required>
                        <option value="Available">Available</option>
                        <option value="In Use">In Use</option>
                        <option value="Under Maintenance">Under Maintenance</option>
                        <option value="Damaged">Damaged</option>
                        <option value="Lost">Lost</option>
                        <option value="Retired">Retired</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Last Maintenance Date</label>
                    <input type="date" name="last_maintenance_date" id="edit_last_maintenance">
                </div>
                
                <div class="form-group">
                    <label>Next Maintenance Date</label>
                    <input type="date" name="next_maintenance_date" id="edit_next_maintenance">
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">Update Equipment</button>
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
        </form>
    </div>
</div>

<script>
function showTab(tabName) {
    const tabs = document.querySelectorAll('.tab-content');
    tabs.forEach(tab => tab.classList.remove('active'));
    
    const tabButtons = document.querySelectorAll('.tab');
    tabButtons.forEach(btn => btn.classList.remove('active'));
    
    document.getElementById(tabName).classList.add('active');
    event.target.classList.add('active');
}

function filterEquipment() {
    const input = event.target.value.toLowerCase();
    const cards = document.querySelectorAll('.equipment-card');
    
    cards.forEach(card => {
        const name = card.dataset.name;
        const category = card.dataset.category;
        
        if (name.includes(input) || category.includes(input)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}

function editEquipment(equipment) {
    document.getElementById('edit_equipment_id').value = equipment.equipment_id;
    document.getElementById('edit_name').value = equipment.equipment_name;
    document.getElementById('edit_category').value = equipment.equipment_category;
    document.getElementById('edit_quantity').value = equipment.quantity;
    document.getElementById('edit_status').value = equipment.equipment_status;
    document.getElementById('edit_last_maintenance').value = equipment.last_maintenance_date || '';
    document.getElementById('edit_next_maintenance').value = equipment.next_maintenance_date || '';
    
    document.getElementById('editModal').style.display = 'block';
}

function reserveEquipment(equipmentId) {
    // This would open a reservation modal
    alert('Reservation form would open here for equipment ID: ' + equipmentId);
}

function approveReservation(reservationId) {
    if (confirm('Approve this reservation?')) {
        // Implement approval logic
        location.reload();
    }
}

function rejectReservation(reservationId) {
    if (confirm('Reject this reservation?')) {
        // Implement rejection logic
        location.reload();
    }
}

function scheduleMaintenance(equipmentId) {
    // This would open a maintenance scheduling modal
    alert('Maintenance scheduling would open here for equipment ID: ' + equipmentId);
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>
</body>
</html>
