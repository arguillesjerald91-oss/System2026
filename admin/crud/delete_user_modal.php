<div id="deleteUserModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h2>Confirm Deletion</h2>
    <p>Are you sure you want to delete <strong id="deleteUserName"></strong>?</p>
    <form id="deleteUserForm">
      <input type="hidden" name="user_id" id="deleteUserId">
      <button type="submit" class="btn delete-btn">Yes</button>
      <button type="button" class="btn cancel-btn">Cancel</button>
    </form>
  </div>
</div>

<!-- Success Modal -->
<div class="modal-overlay" id="deleteUserSuccessModal">
  <div class="success-modal">
    <h3>✅ User Deleted</h3>
    <p>User account has been deleted successfully.</p>
    <button onclick="closeDeleteUserSuccess()">OK</button>
  </div>
</div>

<style>
/* ===== Delete Modal Styles ===== */
.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  overflow: auto;
  background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
  background-color: #fff;
  margin: 10% auto;
  padding: 20px;
  border-radius: 10px;
  width: 400px;
  max-width: 90%;
  box-shadow: 0 5px 15px rgba(0,0,0,0.3);
  text-align: center;
  position: relative;
}

.modal-content h2 {
  margin-bottom: 15px;
}

.modal-content p {
  margin-bottom: 25px;
  font-size: 16px;
}

.modal-content .btn {
  padding: 8px 16px;
  margin: 0 10px;
  border: none;
  border-radius: 5px;
  cursor: pointer;
}

.modal-content .delete-btn {
  background-color: #e74c3c;
  color: #fff;
}

.modal-content .cancel-btn {
  background-color: #95a5a6;
  color: #fff;
}

.modal-content .close {
  position: absolute;
  top: 10px;
  right: 15px;
  font-size: 22px;
  font-weight: bold;
  color: #333;
  cursor: pointer;
}

/* ===== Success Modal ===== */
.modal-overlay {
  display: none;
  position: fixed;
  z-index: 1001;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  align-items: center;
  justify-content: center;
}

.modal-overlay.show {
  display: flex;
}

.success-modal {
  background: white;
  padding: 30px;
  border-radius: 10px;
  text-align: center;
  box-shadow: 0 5px 20px rgba(0,0,0,0.3);
  max-width: 400px;
}

.success-modal h3 {
  color: #28a745;
  font-size: 22px;
  margin-bottom: 8px;
}

.success-modal p {
  color: #666;
  margin-bottom: 20px;
}

.success-modal button {
  background-color: #28a745;
  color: white;
  border: none;
  padding: 10px 20px;
  border-radius: 5px;
  cursor: pointer;
  font-size: 16px;
}

.success-modal button:hover {
  background-color: #218838;
}
</style>

<script>
// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
  const deleteUserModal = document.getElementById('deleteUserModal');
  const deleteUserBtns = document.querySelectorAll('.openDeleteModal');
  const closeDeleteUser = deleteUserModal.querySelector('.close');
  const cancelDeleteUser = deleteUserModal.querySelector('.cancel-btn');
  const deleteUserName = document.getElementById('deleteUserName');
  const deleteUserId = document.getElementById('deleteUserId');

  deleteUserBtns.forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const name = this.dataset.name;
      const id = this.dataset.id;

      deleteUserName.textContent = name;
      deleteUserId.value = id;

      deleteUserModal.style.display = 'block';
    });
  });

  closeDeleteUser.addEventListener('click', () => deleteUserModal.style.display = 'none');
  cancelDeleteUser.addEventListener('click', () => deleteUserModal.style.display = 'none');

  window.addEventListener('click', (e) => {
    if (e.target == deleteUserModal) {
      deleteUserModal.style.display = 'none';
    }
  });

  // ===== SUCCESS MODAL LOGIC =====
  const deleteUserSuccessModal = document.getElementById('deleteUserSuccessModal');

  function showDeleteUserSuccess() {
    deleteUserSuccessModal.classList.add('show');
    setTimeout(closeDeleteUserSuccess, 2000);
  }

  function closeDeleteUserSuccess() {
    deleteUserSuccessModal.classList.remove('show');
    setTimeout(() => location.reload(), 500);
  }

  window.addEventListener('click', (e) => {
    if (e.target === deleteUserSuccessModal) closeDeleteUserSuccess();
  });

  // ===== DELETE FORM AJAX HANDLER =====
  document.getElementById('deleteUserForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const userId = document.getElementById('deleteUserId').value;
    
    if (!userId) {
      alert('User ID is missing');
      return;
    }

    fetch('crud/delete_user.php', {
      method: 'POST',
      body: new FormData(this)
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        deleteUserModal.style.display = 'none';
        showDeleteUserSuccess();
      } else {
        alert('Error: ' + (data.message || 'Failed to delete user'));
      }
    })
    .catch(err => {
      console.error('Error:', err);
      alert('An error occurred while deleting the user');
    });
  });
});
</script>
