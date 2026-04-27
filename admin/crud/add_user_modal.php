<!-- crud/add_user_modal.php -->
<button id="openAddUser" class="btn-add">
  <i class="fas fa-user-plus"></i> Add User
</button>

<!-- Modal -->
<div class="modal" id="addUserModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="modal-title">Add User</h3>
      <span class="close-btn" id="closeAddUserModal">&times;</span>
    </div>

    <form id="addUserForm" class="modal-form">
      <div class="form-group">
        <label>Create From</label>
        <select id="createFrom" name="create_from">
          <option value="manual">Manual</option>
          <option value="student">Existing Student</option>
        </select>
      </div>

      <div class="form-group" id="studentSelectWrap" style="display:none;">
        <label>Select Student</label>
        <select id="studentSelect" name="studentSelect">
          <option value="">-- Choose Student --</option>
          <?php foreach ($students as $s): ?>
            <option value="<?= htmlspecialchars($s['StudID']) ?>" data-schoolid="<?= htmlspecialchars($s['SchoolID'] ?: $s['StudID']) ?>" data-first="<?= htmlspecialchars($s['FirstName']) ?>" data-last="<?= htmlspecialchars($s['LastName']) ?>" data-email="<?= htmlspecialchars($s['EmailAddr']) ?>"><?= htmlspecialchars($s['FirstName'] . ' ' . $s['LastName'] . ' (' . ($s['SchoolID'] ?: $s['StudID']) . ')') ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="fullname" id="add_fullname" required>
      </div>
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" id="add_username" required>
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" id="add_email" required>
      </div>
      <div class="form-group">
        <label>Role</label>
        <select name="role" id="add_role">
          <option value="admin">Admin</option>
          <option value="staff">Staff</option>
          <option value="student">Student</option>
        </select>
      </div>

      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" id="add_password" required placeholder="Enter password for the user">
      </div>

      <div class="form-actions">
        <button type="submit" class="btn-save">Create User</button>
      </div>
    </form>
  </div>
</div>

<!-- Success Modal -->
<div class="modal-overlay" id="addUserSuccessModal">
  <div class="success-modal">
    <h3><i class="fas fa-check-circle"></i> Successfully Added!</h3>
    <p>The user has been added successfully.</p>
    <button onclick="closeAddUserSuccess()">OK</button>
  </div>
</div>

<script>
// ===== Modal Controls =====
const addUserModal = document.getElementById("addUserModal");
document.getElementById("openAddUser").onclick = () => addUserModal.style.display = "flex";
document.getElementById("closeAddUserModal").onclick = () => addUserModal.style.display = "none";
window.onclick = e => { if (e.target == addUserModal) addUserModal.style.display = "none"; };

// ===== Create From Selection =====
const createFrom = document.getElementById('createFrom');
const studentSelectWrap = document.getElementById('studentSelectWrap');
const studentSelect = document.getElementById('studentSelect');
createFrom.addEventListener('change', () => {
  if (createFrom.value === 'student') {
    studentSelectWrap.style.display = '';
  } else {
    studentSelectWrap.style.display = 'none';
  }
});

studentSelect.addEventListener('change', function(){
  const opt = this.selectedOptions[0];
  if (!opt) return;
  document.getElementById('add_fullname').value = opt.dataset.first + ' ' + opt.dataset.last;
  document.getElementById('add_email').value = opt.dataset.email;
  // Auto-fill username with SchoolID
  document.getElementById('add_username').value = opt.dataset.schoolid;
});

document.getElementById('addUserForm').addEventListener('submit', function(e) {
  e.preventDefault();
  fetch('crud/add_user.php', {method: 'POST', body: new FormData(this)})
    .then(r => r.json())
    .then(d => {
      if (d.status === 'success') {
        addUserModal.style.display = 'none';
        showAddUserSuccess();
        document.getElementById('addUserForm').reset();
        setTimeout(() => location.reload(), 2000);
      } else {
        alert('Error: ' + (d.message || 'Unknown'));
      }
    })
    .catch(e => alert('Error: ' + e.message));
});

// ===== Success Modal =====
const addUserSuccessModal = document.getElementById("addUserSuccessModal");

function showAddUserSuccess() {
  addUserSuccessModal.classList.add("show");
  setTimeout(closeAddUserSuccess, 2000);
}

function closeAddUserSuccess() {
  addUserSuccessModal.classList.remove("show");
  const url = new URL(window.location.href);
  url.searchParams.delete("added");
  window.history.replaceState({}, '', url);
}

window.addEventListener("click", (e) => {
  if (e.target === addUserSuccessModal) closeAddUserSuccess();
});

<?php if (isset($_GET['added'])): ?>
  showAddUserSuccess();
<?php endif; ?>
</script>

<style>
/* ===== Modal Base ===== */
.modal {
  display: none;
  position: fixed;
  z-index: 9999;
  left: 0; top: 0;
  width: 80%; height: 80%;
  background: rgba(0,0,0,0.5);
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.modal-content {
  background: #fff;
  padding: 20px;
  border-radius: 10px;
  width: 80%;
  max-width: 520px;
  box-shadow: 0 5px 15px rgba(0,0,0,0.2);
  animation: fadeIn 0.3s ease-in-out;
}
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}
.modal-title {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  text-align: left;
  color: #333;
}
.close-btn {
  cursor: pointer;
  font-size: 22px;
  font-weight: bold;
  color: #555;
}
.close-btn:hover { color: red; }

/* ===== Form ===== */
.modal-form {
  display: flex;
  flex-direction: column;
  text-align: left;
}
.form-group {
  display: flex;
  flex-direction: column;
  text-align: left;
  margin-bottom: 10px;
}
.form-group label {
  margin-bottom: 5px;
  font-weight: 600;
  font-size: 14px;
  color: #333;
  text-align: left;
}
.form-group input,
.form-group select {
  width: 90%;
  max-width: 380px;
  padding: 8px 10px;
  border: 1px solid #ccc;
  border-radius: 6px;
  font-size: 14px;
  text-align: left;
  background: #fff;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

/* ===== Buttons ===== */
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 10px;
}
.btn-save {
  padding: 9px 18px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  background: #007bff;
  color: #fff;
}
.btn-save:hover { background: #0056b3; }

.btn-add {
  background: #007bff;
  color: #fff;
  border: none;
  padding: 8px 15px;
  border-radius: 100px;
  cursor: pointer;
  font-size: 14px;
}
.btn-add i { margin-right: 5px; }
.btn-add:hover { background: #0552a5; }

@keyframes fadeIn {
  from {opacity: 0; transform: translateY(-10px);}
  to {opacity: 1; transform: translateY(0);}
}

/* ===== Success Modal ===== */
.modal-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.3);
  justify-content: center;
  align-items: center;
  z-index: 10000;
}

.modal-overlay.show {
  display: flex;
  animation: fadeIn 0.3s ease;
}

.success-modal {
  background: #ffffff;
  border-radius: 12px;
  padding: 25px 40px;
  text-align: center;
  box-shadow: 0 8px 16px rgba(0,0,0,0.2);
  font-family: 'Poppins', sans-serif;
  animation: popIn 0.3s ease;
}

.success-modal h3 {
  color: #28a745;
  font-size: 22px;
  margin-bottom: 8px;
}

.success-modal i {
  color: #28a745;
  margin-right: 8px;
}

.success-modal p {
  color: #444;
  font-size: 15px;
  margin-bottom: 16px;
}

.success-modal button {
  background: #28a745;
  color: white;
  border: none;
  padding: 8px 18px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  transition: background 0.3s;
}

.success-modal button:hover {
  background: #218838;
}

@keyframes popIn {
  from { transform: scale(0.9); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}
</style>
