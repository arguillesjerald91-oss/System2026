<div class="modal" id="editModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Edit Student</h3>
      <span class="close-btn" id="closeEditModal">&times;</span>
    </div>

    <form method="POST" action="crud/edit.php" class="modal-form">
      <input type="hidden" name="student_id" id="edit_id">

      <div class="form-group">
        <label>School ID</label>
        <input type="text" name="school_id" id="edit_schoolid" readonly>
      </div>

      <div class="form-group">
        <label>First Name</label>
        <input type="text" name="first_name" id="edit_first" required>
      </div>

      <div class="form-group">
        <label>Last Name</label>
        <input type="text" name="last_name" id="edit_last" required>
      </div>

      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" id="edit_email" required>
      </div>

      <div class="form-group">
        <label>Phone</label>
        <input type="text" name="phone" id="edit_phone">
      </div>

      <div class="form-group">
        <label>Course</label>
        <select name="course" id="edit_course">
          <option value="BSIT">BSIT - Bachelor of Science in Information Technology</option>
          <option value="BSBA">BSBA - Bachelor of Science in Business Administration</option>
          <option value="BEED">BEED - Bachelor of Elementary Education</option>
          <option value="BSED">BSED - Bachelor of Secondary Education</option>
          <option value="BSHM">BSHM - Bachelor of Science in Hospitality Management</option>
          <option value="BSCRIM">BSCRIM - Bachelor of Science in Criminology</option>
        </select>
      </div>

      <div class="form-group">
        <label>Year Level</label>
        <select name="year_level" id="edit_year">
          <option value="1st Year">1st Year</option>
          <option value="2nd Year">2nd Year</option>
          <option value="3rd Year">3rd Year</option>
          <option value="4th Year">4th Year</option>
        </select>
      </div>

      <div class="form-group">
        <label>Enrollment Semester</label>
        <select name="semester" id="edit_semester">
          <option value="1st Semester">1st Semester</option>
          <option value="2nd Semester">2nd Semester</option>
          <option value="Summer">Summer</option>
          <option value="Not Enrolled">Not Enrolled</option>
        </select>
      </div>

      <div class="form-group">
        <label>Status</label>
        <select name="status" id="edit_status">
          <option value="Active">Enrolled</option>
          <option value="Inactive">Not Enrolled</option>
        </select>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn-save">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Success Modal -->
<div class="modal-overlay" id="editSuccessModal">
  <div class="success-modal">
    <h3><i class="fas fa-check-circle"></i> Successfully Updated!</h3>
    <p>The student details have been updated successfully.</p>
    <button onclick="closeEditSuccessModal()">OK</button>
  </div>
</div>

<script>
// ===== OPEN EDIT MODAL WITH DATA =====
    // Helper: sync status based on semester for Edit modal
    function updateEditStatus() {
      const sem = document.getElementById('edit_semester');
      const status = document.getElementById('edit_status');
      if (!sem || !status) return;
      const v = (sem.value || '').toLowerCase();
      if (v.includes('1st') || v.includes('2nd') || v.includes('first') || v.includes('second') || v.includes('summer')) {
        status.value = 'Active';
      } else {
        status.value = 'Inactive';
      }
    }

    document.getElementById('edit_semester').addEventListener('change', updateEditStatus);

    document.querySelectorAll(".openEditModal").forEach(btn => {
      btn.addEventListener("click", () => {
        document.getElementById("edit_id").value = btn.dataset.id;
        document.getElementById("edit_schoolid").value = btn.dataset.schoolid || 'N/A';
        document.getElementById("edit_first").value = btn.dataset.first;
        document.getElementById("edit_last").value = btn.dataset.last;
        document.getElementById("edit_email").value = btn.dataset.email;
        document.getElementById("edit_phone").value = btn.dataset.phone;
        document.getElementById("edit_course").value = btn.dataset.course;
        document.getElementById("edit_year").value = btn.dataset.year;
        document.getElementById("edit_semester").value = btn.dataset.semester;
        // prefer dataset.status when provided, otherwise determine from semester
        if (btn.dataset.status) {
          document.getElementById("edit_status").value = btn.dataset.status;
        } else {
          updateEditStatus();
        }

        document.getElementById("editModal").style.display = "flex";
      });
    });

document.getElementById("closeEditModal").addEventListener("click", () => {
  document.getElementById("editModal").style.display = "none";
});

window.addEventListener("click", function(e) {
  let modal = document.getElementById("editModal");
  if (e.target == modal) modal.style.display = "none";
});

// ===== SUCCESS MODAL LOGIC =====
const editSuccessModal = document.getElementById("editSuccessModal");

function showEditSuccessModal() {
  editSuccessModal.classList.add("show");
  setTimeout(closeEditSuccessModal, 2000); // auto-close
}

function closeEditSuccessModal() {
  editSuccessModal.classList.remove("show");
  const url = new URL(window.location.href);
  url.searchParams.delete("updated");
  window.history.replaceState({}, '', url);
}

window.addEventListener("click", (e) => {
  if (e.target === editSuccessModal) closeEditSuccessModal();
});

// Auto-show modal if redirected with ?updated=1
<?php if (isset($_GET['updated'])): ?>
  showEditSuccessModal();
<?php endif; ?>
</script>

<style>
/* Modal Styles same as Add modal */
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
  max-width: 100px;
  box-shadow: 0 5px 15px rgba(0,0,0,0.2);
  animation: fadeIn 0.3s ease-in-out;
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
