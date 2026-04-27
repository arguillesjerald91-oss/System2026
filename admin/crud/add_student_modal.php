
<!-- Modal -->
<div class="modal" id="addModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="modal-title">Add Student</h3>
      <span class="close-btn" id="closeModal">&times;</span>
    </div>

    <form method="POST" action="crud/add.php" enctype="multipart/form-data" class="modal-form" id="addStudentForm">
      <!-- ===== Student Information ===== -->
      <div class="form-page" id="page1">
        <div class="form-group">
          <label>School ID <small style="color: #888;">(will be used as login credentials)</small></label>
          <input type="text" name="school_id" placeholder="e.g., 22-123456" required>
        </div>

        <div class="form-group">
          <label>First Name</label>
          <input type="text" name="first_name" required>
        </div>

        <div class="form-group">
          <label>Last Name</label>
          <input type="text" name="last_name" required>
        </div>

        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" required>
        </div>

        <div class="form-group">
          <label>Phone</label>
          <input type="text" name="phone">
        </div>

        <div class="form-group">
          <label>Course</label>
          <select name="course" required>
            <option value="">-- Select Course --</option>
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
          <select name="year_level" required>
            <option value="">-- Select Year Level --</option>
            <option value="1st Year">1st Year</option>
            <option value="2nd Year">2nd Year</option>
            <option value="3rd Year">3rd Year</option>
            <option value="4th Year">4th Year</option>
          </select>
        </div>
        
        <div class="form-group">
          <label>Semester</label>
          <select name="semester" id="add_semester" required>
            <option value="">-- Select Semester --</option>
            <option value="1st Semester">1st Semester</option>
            <option value="2nd Semester">2nd Semester</option>
            <option value="Summer">Summer</option>
            <option value="Not Enrolled">Not Enrolled</option>
          </select>
        </div>

        <div class="alert-info" style="background: #e3f2fd; border-left: 4px solid #2196F3; padding: 12px; margin: 15px 0; border-radius: 4px; font-size: 13px;">
          <i class="fas fa-info-circle" style="color: #2196F3;"></i>
          <strong>Note:</strong> A user account will be automatically created with:<br>
          • Username: <strong>School ID</strong><br>
          • Password: <strong>School ID</strong> (student should change after first login)
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-save">Save Student</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Success Modal -->
<div class="modal-overlay" id="successModal">
  <div class="success-modal">
    <h3><i class="fas fa-check-circle"></i> Successfully Added!</h3>
    <p>The student has been added successfully.</p>
    <button onclick="closeModal()">OK</button>
  </div>
</div>



<script>
// ===== Modal Controls =====
const modal = document.getElementById("addModal");
document.getElementById("openAddModal").onclick = () => {
  modal.style.display = "flex";
  // Reset form when opening
  document.getElementById('addStudentForm').reset();
};
document.getElementById("closeModal").onclick = () => modal.style.display = "none";
window.onclick = e => { if (e.target == modal) modal.style.display = "none"; };

// ===== Edit Modal Logic =====
  
  console.log("Validation Check:", {schoolId, firstName, lastName, email, course, yearLevel, semester});
  
  // Check each field individually for better error messaging
  if (!schoolId) {
    alert("Please enter School ID");
    return;
  }
  if (!firstName) {
    alert("Please enter First Name");
    return;
  }
  if (!lastName) {
    alert("Please enter Last Name");
    return;
  }
  if (!email) {
    alert("Please enter Email");
    return;
// ===== Edit Modal Logic =====
      if (v.includes('1st') || v.includes('2nd') || v.includes('first') || v.includes('second') || v.includes('summer')) {
        status.value = 'Active';
      } else {
        status.value = 'Inactive';
      }
    }

    sem.addEventListener('change', updateStatus);
    // initial state
    updateStatus();
  })();
</script>

<script>
  // ===== Success Modal =====
  const successModal = document.getElementById("successModal");

  function showModal() {
    successModal.classList.add("show");
  }

  function closeModal() {
    successModal.classList.remove("show");
    // Reload the page to show the newly added student
    window.location.href = 'manage_students.php';
  }

  window.addEventListener("click", (e) => {
    if (e.target === successModal) closeModal();
  });

  // Show success modal if ?added=1 is in URL
  <?php if (isset($_GET['added'])): ?>
    showModal();
    setTimeout(closeModal, 2000);
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
  width: 90%;              /* not too wide — fits nicely within modal */
  max-width: 380px;        /* limit for large screens */
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
.btn-save, .btn-next, .btn-back {
  padding: 9px 18px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
}
.btn-save { background: #007bff; color: #fff; }
.btn-next { background: #28a745; color: #fff; }
.btn-back { background: #6c757d; color: #fff; }
.btn-save:hover { background: #0056b3; }
.btn-next:hover { background: #1f7a31; }
.btn-back:hover { background: #565e64; }

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
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  visibility: hidden;
  transition: all 0.3s ease;
  z-index: 10000;
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
