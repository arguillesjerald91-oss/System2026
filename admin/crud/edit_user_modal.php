<div class="modal" id="editUserModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Edit User</h3>
      <span class="close-btn" id="closeEditUserModal">&times;</span>
    </div>

    <form id="editUserForm" class="modal-form">
      <input type="hidden" name="user_id" id="edit_user_id">

      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="fullname" id="edit_fullname" required>
      </div>

      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" id="edit_username" required>
      </div>

      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" id="edit_email" required>
      </div>

      <div class="form-group">
        <label>Role</label>
        <select name="role" id="edit_role">
          <option value="admin">Admin</option>
          <option value="staff">Staff</option>
          <option value="student">Student</option>
        </select>
      </div>

      <div class="form-group">
        <label>
          <input type="checkbox" id="editChangePassword"> Change Password
        </label>
      </div>

      <div class="form-group" id="editPasswordWrap" style="display:none;">
        <label>New Password</label>
        <input type="password" name="password" id="edit_password" placeholder="Enter new password">
      </div>

      <div class="form-actions">
        <button type="submit" class="btn-save">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Success Modal -->
<div class="modal-overlay" id="editUserSuccessModal">
  <div class="success-modal">
    <h3><i class="fas fa-check-circle"></i> Successfully Updated!</h3>
    <p>The user details have been updated successfully.</p>
    <button onclick="closeEditUserSuccess()">OK</button>
  </div>
</div>

<script>
// ===== OPEN EDIT MODAL WITH DATA =====
document.querySelectorAll(".openEditModal").forEach(btn => {
  btn.addEventListener("click", (e) => {
    e.preventDefault();
    const id = btn.dataset.id;
    document.getElementById("edit_user_id").value = id;
    document.getElementById("edit_fullname").value = btn.dataset.name || '';
    document.getElementById("edit_username").value = btn.dataset.username || '';
    document.getElementById("edit_email").value = btn.dataset.email || '';
    document.getElementById("edit_role").value = btn.dataset.role || 'user';
    document.getElementById("editChangePassword").checked = false;
    document.getElementById("edit_password").value = '';
    document.getElementById("editPasswordWrap").style.display = 'none';
    document.getElementById("editUserModal").style.display = "flex";
  });
});

// ===== PASSWORD CHANGE TOGGLE =====
document.getElementById("editChangePassword").addEventListener("change", function(){
  document.getElementById("editPasswordWrap").style.display = this.checked ? '' : 'none';
  if (!this.checked) {
    document.getElementById("edit_password").value = '';
  }
});

document.getElementById("closeEditUserModal").addEventListener("click", () => {
  document.getElementById("editUserModal").style.display = "none";
});

window.addEventListener("click", function(e) {
  let modal = document.getElementById("editUserModal");
  if (e.target == modal) modal.style.display = "none";
});

// ===== SUCCESS MODAL LOGIC =====
const editUserSuccessModal = document.getElementById("editUserSuccessModal");

function showEditUserSuccess() {
  editUserSuccessModal.classList.add("show");
  setTimeout(closeEditUserSuccess, 2000);
}

function closeEditUserSuccess() {
  editUserSuccessModal.classList.remove("show");
  const url = new URL(window.location.href);
  url.searchParams.delete("updated");
  window.history.replaceState({}, '', url);
}

window.addEventListener("click", (e) => {
  if (e.target === editUserSuccessModal) closeEditUserSuccess();
});

<?php if (isset($_GET['updated'])): ?>
  showEditUserSuccess();
<?php endif; ?>
</script>

<style>
/* Modal Styles */
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
.close-btn {
  cursor: pointer;
  font-size: 22px;
  font-weight: bold;
  color: #555;
}
.close-btn:hover { color: red; }

.modal-form {
  display: flex;
  flex-direction: column;
  text-align: left;
}
.form-group {
  display: flex;
  flex-direction: column;
  margin-bottom: 12px;
}
.form-group label {
  font-weight: 600;
  font-size: 14px;
  margin-bottom: 5px;
}
.form-group input, .form-group select {
  padding: 9px;
  border: 1px solid #ccc;
  border-radius: 6px;
  font-size: 14px;
}
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 10px;
}
.btn-save {
  background: #007bff;
  color: #fff;
  padding: 10px 20px;
  border-radius: 50px;
  border: none;
  cursor: pointer;
}
.btn-save:hover { background: #0056b3; }

@keyframes fadeIn {
  from {opacity: 0; transform: translateY(-10px);}
  to {opacity: 1; transform: translateY(0);}
}

/* Success Modal Styles */
.modal-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.3);
  justify-content: center;
  align-items: center;
  z-index: 10000;
}
.modal-overlay.show { display: flex; }
.success-modal {
  background: #fff;
  border-radius: 12px;
  padding: 25px 40px;
  text-align: center;
  box-shadow: 0 8px 16px rgba(0,0,0,0.2);
  font-family: 'Poppins', sans-serif;
}
.success-modal h3 { color: #28a745; font-size: 22px; margin-bottom: 8px; }
.success-modal i { color: #28a745; margin-right: 8px; }
.success-modal p { color: #444; font-size: 15px; margin-bottom: 16px; }
.success-modal button {
  background: #28a745;
  color: #fff;
  border: none;
  padding: 8px 18px;
  border-radius: 8px;
  cursor: pointer;
}
.success-modal button:hover { background: #218838; }
</style>
