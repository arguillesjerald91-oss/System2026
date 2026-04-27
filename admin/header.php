
<?php
include 'db.php';

$database = new Database();
$conn = $database->getConnection();

$userId = $_SESSION['userId'] ?? null;

// Set default avatar
$avatarPath = "../images/image.png";
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>TESDA Admin Portal</title>
  <link rel="stylesheet" href="admin.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="container">

  <!-- ======= TOPBAR ======= -->
  <div class="topbar">
    <div class="topbar-left">
      <h2>Admin Dashboard</h2>
      <p>Welcome back, Administrator</p>
    </div>
    <div class="topbar-right">
     <img src="<?= $avatarPath ?>" alt="Admin Avatar" class="avatar" id="avatarBtn">

      <div class="dropdown-menu" id="dropdownMenu">
        <a href="#" id="editProfileBtn"><i class="fa-solid fa-user-pen"></i> Edit Profile</a>
        <a href="#" id="changePasswordBtn"><i class="fa-solid fa-key"></i> Change Password</a>
      </div>
    </div>
  </div>

 <!-- ======= EDIT PROFILE MODAL ======= -->
<div class="modal" id="editProfileModal">
  <div class="modal-content">
    <h3><i class="fa-solid fa-user-pen"></i> Edit Profile</h3>
    <form id="editProfileForm" enctype="multipart/form-data">
      <div class="avatar-preview">
        <img id="avatarPreview" src="../images/admin.png" alt="Avatar Preview">
      </div>
      <input type="file" name="avatar" id="avatarInput" accept="image/*">
      <input type="text" name="username" placeholder="Username" required>
      <input type="email" name="email" placeholder="Email" required>
      <div class="modal-actions">
        <button type="submit" class="btn-success">Save</button>
        <button type="button" class="btn-secondary closeModal">Cancel</button>
      </div>
    </form>
  </div>
</div>


  <!-- ======= CHANGE PASSWORD MODAL ======= -->
  <div class="modal" id="changePasswordModal">
    <div class="modal-content">
      <h3><i class="fa-solid fa-key"></i> Change Password</h3>
      <form id="changePasswordForm">
        <input type="password" name="current_password" placeholder="Current Password" required>
        <input type="password" name="new_password" placeholder="New Password" required>
        <input type="password" name="confirm_password" placeholder="Confirm Password" required>
        <div class="modal-actions">
          <button type="submit" class="btn-success">Update</button>
          <button type="button" class="btn-secondary closeModal">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ======= SUCCESS MODAL ======= -->
  <div class="modal-overlay" id="successModal">
    <div class="success-modal">
      <h3>✅ Success!</h3>
      <p>Your changes have been saved successfully.</p>
      <button id="closeSuccess">OK</button>
    </div>
  </div>

</div>

<script src="js/header.js"></script>
</body>
</html>
