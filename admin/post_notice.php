

<?php 
session_start();
include 'db.php';
include_once __DIR__ . '/log_activity.php';
$database = new Database();
$conn = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_notice'])) {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);

    if (!empty($title) && !empty($message)) {
        $stmt = $conn->prepare("INSERT INTO notices (title, content) VALUES (?, ?)");
        $stmt->execute([$title, $message]);
        
        // Log activity
        logActivity('Notice Posted', "New notice posted - Title: $title", $conn);
        
        header("Location: ".$_SERVER['PHP_SELF']."?success=added");
        exit;
    } 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notice'])) {
    $notice_id = $_POST['notice_id'];
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $stmt = $conn->prepare("UPDATE notices SET title=?, content=? WHERE notice_id=?");
    $stmt->execute([$title, $message, $notice_id]);
    
    // Log activity
    logActivity('Notice Updated', "Notice updated - Title: $title", $conn);
    
    header("Location: ".$_SERVER['PHP_SELF']."?success=updated");
    exit;
}



// --- DELETE NOTICE ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM notices WHERE notice_id = ?");
    $stmt->execute([$id]);
    $success = "Notice deleted successfully!";
}

include 'header.php';
include 'sidebar.php';

// --- FETCH ALL NOTICES ---
$stmt = $conn->prepare("SELECT * FROM notices ORDER BY created_at DESC");
$stmt->execute();
$notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Post Notice</title>
  <link rel="stylesheet" href="css/notice.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    
<div class="main-content">
  <div class="header">
    <h1 class="page-title"><i class="fa-solid fa-bullhorn"></i> Post Notices</h1>



   <!-- Post Notice Form -->
<form method="POST" class="notice-form">
  <div class="form-group">
    <label>Title</label>
    <input type="text" name="title" id="title" placeholder="Enter notice title" required>
  </div>

  <div class="form-group">
    <label>Preset Notice (Optional)</label>
    <select id="preset" onchange="fillPreset()">
      <option value="">-- Select Preset Notice --</option>
      <option value="Prelims Exam">Prelims Exam</option>
      <option value="Midterm Exam">Midterm Exam</option>
      <option value="Semi-Finals Exam">Semi-Finals Exam</option>
      <option value="Finals Exam">Finals Exam</option>
    </select>
  </div>

  
  <div class="form-group">
    <label>Date (Optional)</label>
    <input type="date" id="preset_date" onchange="updatePresetMessage()">
  </div>

  <div class="form-group">
    <label>Message</label>
    <label>Message</label>
    <textarea name="message" id="message" rows="4" placeholder="Write your notice..." required></textarea>
  </div>

  <button type="submit" name="add_notice" class="btn btn-primary">
    <i class="fa-solid fa-paper-plane"></i> Post Notice
  </button>
</form>

    <!-- Recent Notices -->
    <div class="recent-section">
      <h3><i class="fa-regular fa-clock"></i> Recent Notices</h3>
      <div class="notice-list">
        <?php if ($notices): ?>
          <?php foreach ($notices as $n): ?>
            <div class="notice-card">
              <div class="notice-icon">
                <i class="fa-regular fa-bell"></i>
              </div>
              <div class="notice-content">
                <div class="notice-title">
                  <?php echo htmlspecialchars($n['title']); ?>
                </div>
                <div class="notice-message"><?php echo nl2br(htmlspecialchars($n['content'])); ?></div>
                <div class="notice-date"><i class="fa-regular fa-calendar"></i> <?php echo date("Y-m-d", strtotime($n['created_at'])); ?></div>
              </div>
              <div class="notice-actions">
                <button onclick="openEditModal(<?php echo $n['notice_id']; ?>, '<?php echo htmlspecialchars($n['title']); ?>', '<?php echo htmlspecialchars(addslashes($n['content'])); ?>')">
                  <i class="fa-solid fa-pen-to-square"></i>
                </button>
     <a href="#" class="delete-icon" onclick="openDeleteModal('<?php echo htmlspecialchars($n['title']); ?>', <?php echo $n['notice_id']; ?>)">
  <i class="fa-solid fa-trash"></i>
</a>


              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="no-data"><i class="fa-regular fa-face-frown"></i> No notices yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Edit Notice Modal -->
<div id="editModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal()">&times;</span>
    <h3><i class="fa-solid fa-pen"></i> Edit Notice</h3>
    <form method="POST">
      <input type="hidden" name="notice_id" id="edit_id">

      <div class="form-group">
        <label>Title</label>
        <input type="text" name="title" id="edit_title" required>
      </div>

      <div class="form-group">
        <label>Message</label>
        <textarea name="message" id="edit_message" rows="4" required></textarea>
      </div>

     <div class="form-actions">
    <button type="submit" name="update_notice" class="btn-add">Update</button>
  </div>
</form>
  </div>
</div>


<!-- Success Modal -->
<div class="modal-overlay" id="noticeSuccessModal">
  <div class="success-modal">
    <h3><i class="fa-solid fa-circle-check"></i> <span id="successTitle">Success!</span></h3>
    <p id="successMessage"></p>
    <button onclick="closeNoticeModal()">OK</button>
  </div>
</div>


<!-- Delete Notice Modal -->
<div id="deleteModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h2>Confirm Delete</h2>
    <p>Are you sure you want to delete <strong id="deleteNoticeTitle"></strong>?</p>
    <form method="GET" style="margin-top:20px;">
      <input type="hidden" name="delete" id="deleteNoticeId">
      <button type="submit" class="btn delete-btn">Delete</button>
      <button type="button" class="btn cancel-btn">Cancel</button>
    </form>
  </div>
</div>


<script>
const noticeModal = document.getElementById('noticeSuccessModal');
const successTitle = document.getElementById('successTitle');
const successMessage = document.getElementById('successMessage');

function showNoticeModal(title, message) {
  successTitle.textContent = title;
  successMessage.textContent = message;
  noticeModal.classList.add('show');
}

// Close modal function
function closeNoticeModal() {
  noticeModal.classList.remove('show');
}

// Show modal on page load if success query exists
document.addEventListener("DOMContentLoaded", () => {
  const params = new URLSearchParams(window.location.search);
  const successType = params.get('success');

  if (successType) {
    let title = "Success!";
    let message = "";

    if (successType === "added") {
      title = "Notice Posted!";
      message = "Your notice has been successfully posted.";
    } else if (successType === "updated") {
      title = "Notice Updated!";
      message = "Your notice has been updated successfully.";
    }

    showNoticeModal(title, message);

    // Remove query string after showing modal
    const url = new URL(window.location);
    url.searchParams.delete('success');
    window.history.replaceState({}, document.title, url.toString());
  }
});
</script>




<script>
function openEditModal(id, title, message) {
  document.getElementById('edit_id').value = id;
  document.getElementById('edit_title').value = title;
  document.getElementById('edit_message').value = message;
  document.getElementById('editModal').style.display = 'flex';
}
function closeModal() {
  document.getElementById('editModal').style.display = 'none';
}
window.onclick = function(e) {
  if (e.target == document.getElementById('editModal')) closeModal();
};
</script>



<script>
  // Get modal element
  const deleteModal = document.getElementById('deleteModal');
  const deleteNoticeTitle = document.getElementById('deleteNoticeTitle');
  const deleteNoticeId = document.getElementById('deleteNoticeId');
  const closeBtn = deleteModal.querySelector('.close');
  const cancelBtn = deleteModal.querySelector('.cancel-btn');

  // Function to open modal and set dynamic data
  function openDeleteModal(title, id) {
    deleteNoticeTitle.textContent = title;
    deleteNoticeId.value = id;
    deleteModal.style.display = 'block';
  }

  // Function to close modal
  function closeDeleteModal() {
    deleteModal.style.display = 'none';
  }

  // Close modal when clicking the X
  closeBtn.addEventListener('click', closeDeleteModal);

  // Close modal when clicking Cancel button
  cancelBtn.addEventListener('click', closeDeleteModal);

  // Close modal when clicking outside the modal content
  window.addEventListener('click', function(event) {
    if (event.target === deleteModal) {
      closeDeleteModal();
    }
  });

  // Example usage: attach to a delete link/button
  // <a href="#" onclick="openDeleteModal('Notice Title', 123)">Delete</a>
</script>



<script>
function fillPreset() {
  const preset = document.getElementById('preset').value;
  const titleInput = document.getElementById('title');
  const messageInput = document.getElementById('message');
  const dateInput = document.getElementById('preset_date').value;

  if (preset !== "") {
    titleInput.value = preset;
    let msg = `${preset} will be held on ${dateInput || 'TBA'}. Please prepare and review your lessons.`;
    messageInput.value = msg;
  } else {
    titleInput.value = "";
    messageInput.value = "";
  }
}

function updatePresetMessage() {
  const preset = document.getElementById('preset').value;
  const date = document.getElementById('preset_date').value;
  const messageInput = document.getElementById('message');

  if (preset !== "") {
    messageInput.value = `${preset} will be held on ${date || 'TBA'}. Please prepare and review your lessons.`;
  }
}
</script>

</body>
</html>
