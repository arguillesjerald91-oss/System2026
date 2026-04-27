<div id="deleteModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h2>Confirm Deletion</h2>
    <p>Are you sure you want to delete <strong id="deleteStudentName"></strong>?</p>
    <form id="deleteForm">
      <input type="hidden" name="student_id" id="deleteStudentId">
      <button type="submit" class="btn delete-btn">Yes</button>
      <button type="button" class="btn cancel-btn">Cancel</button>
    </form>
  </div>
</div>

<!-- Success Modal -->
<div class="modal-overlay" id="deleteSuccessModal">
  <div class="success-modal">
    <i class="fas fa-check-circle"></i>
    <h3>Student Deleted</h3>
    <p>Student record has been deleted successfully.</p>
    <button onclick="closeDeleteSuccess()">OK</button>
  </div>
</div>

<style>
/* ===== Delete Modal Styles ===== */
.modal {
  display: none; /* Hidden by default */
  position: fixed;
  z-index: 1000; /* On top */
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  overflow: auto;
  background-color: rgba(0, 0, 0, 0.5); /* Dark overlay */
}

.modal-content {
  background-color: #fff;
  margin: 10% auto; /* Center vertically */
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
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  visibility: hidden;
  transition: all 0.3s ease;
  z-index: 1001;
}

.modal-overlay.show {
  opacity: 1;
  visibility: visible;
}

.success-modal {
  background: #fff;
  border-radius: 10px;
  padding: 30px 40px;
  text-align: center;
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
  animation: popUp 0.4s ease;
  position: relative;
}

@keyframes popUp {
  from {
    transform: scale(0.8);
    opacity: 0;
  }
  to {
    transform: scale(1);
    opacity: 1;
  }
}

.success-modal i {
  color: #28a745;
  font-size: 50px;
  margin-bottom: 15px;
  animation: bounce 1.2s infinite;
}

@keyframes bounce {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-5px);
  }
}

.success-modal h3 {
  color: #333;
  margin-bottom: 10px;
  font-size: 22px;
}

.success-modal p {
  color: #555;
  margin-bottom: 20px;
}

.success-modal button {
  background: linear-gradient(to right, #1976D2, #0D47A1);
  color: white;
  border: none;
  padding: 10px 20px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s ease;
}

.success-modal button:hover {
  background: linear-gradient(to right, #0D47A1, #002171);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const deleteModal = document.getElementById('deleteModal');
  const deleteBtns = document.querySelectorAll('.openDeleteModal');
  const closeDelete = deleteModal.querySelector('.close');
  const cancelBtn = deleteModal.querySelector('.cancel-btn');
  const deleteStudentName = document.getElementById('deleteStudentName');
  const deleteStudentId = document.getElementById('deleteStudentId');

  deleteBtns.forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const name = this.dataset.name;
      const id = this.dataset.id;

      deleteStudentName.textContent = name;
      deleteStudentId.value = id;

      deleteModal.style.display = 'block';
    });
  });

  closeDelete.addEventListener('click', () => deleteModal.style.display = 'none');
  cancelBtn.addEventListener('click', () => deleteModal.style.display = 'none');

  // Close modal when clicking outside
  window.addEventListener('click', (e) => {
    if (e.target == deleteModal) {
      deleteModal.style.display = 'none';
    }
  });

  // ===== SUCCESS MODAL LOGIC =====
  const deleteSuccessModal = document.getElementById('deleteSuccessModal');

  function showDeleteSuccess() {
    deleteSuccessModal.classList.add('show');
    setTimeout(closeDeleteSuccess, 2000);
  }

  function closeDeleteSuccess() {
    deleteSuccessModal.classList.remove('show');
    setTimeout(() => location.reload(), 500);
  }

  window.addEventListener('click', (e) => {
    if (e.target === deleteSuccessModal) closeDeleteSuccess();
  });

  // ===== DELETE FORM AJAX HANDLER =====
  document.getElementById('deleteForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const studentId = document.getElementById('deleteStudentId').value;
    
    if (!studentId) {
      alert('Student ID is missing');
      return;
    }

    fetch('crud/delete_student.php', {
      method: 'POST',
      body: new FormData(this)
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        deleteModal.style.display = 'none';
        showDeleteSuccess();
      } else {
        alert('Error: ' + (data.message || 'Failed to delete student'));
      }
    })
    .catch(err => {
      console.error('Error:', err);
      alert('An error occurred while deleting the student');
    });
  });
});
</script>
