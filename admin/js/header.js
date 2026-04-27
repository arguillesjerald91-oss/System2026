document.addEventListener('DOMContentLoaded', () => {
  const avatarBtn = document.getElementById('avatarBtn');
  const dropdownMenu = document.getElementById('dropdownMenu');
  const editProfileBtn = document.getElementById('editProfileBtn');
  const changePasswordBtn = document.getElementById('changePasswordBtn');
  const editProfileModal = document.getElementById('editProfileModal');
  const changePasswordModal = document.getElementById('changePasswordModal');
  const successModal = document.getElementById('successModal');
  const closeSuccess = document.getElementById('closeSuccess');
  const closeButtons = document.querySelectorAll('.closeModal');
  const avatarInput = document.getElementById('avatarInput');
  const avatarPreview = document.getElementById('avatarPreview');

  // Show dropdown
  avatarBtn.addEventListener('click', () => {
    dropdownMenu.style.display = dropdownMenu.style.display === 'flex' ? 'none' : 'flex';
  });

  // Open modals
  editProfileBtn.addEventListener('click', (e) => {
    e.preventDefault();
    dropdownMenu.style.display = 'none';
    
    // Fetch current user data
    fetch('get_user_profile.php')
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          document.getElementById('editProfileForm').username.value = data.username || '';
          document.getElementById('editProfileForm').email.value = data.email || '';
        }
      });
    
    editProfileModal.classList.add('show');
  });

  changePasswordBtn.addEventListener('click', (e) => {
    e.preventDefault();
    dropdownMenu.style.display = 'none';
    document.getElementById('changePasswordForm').reset();
    changePasswordModal.classList.add('show');
  });

  // Close modals
  closeButtons.forEach(btn =>
    btn.addEventListener('click', () => {
      editProfileModal.classList.remove('show');
      changePasswordModal.classList.remove('show');
    })
  );

  // Avatar preview before upload
  avatarInput.addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = e => avatarPreview.src = e.target.result;
      reader.readAsDataURL(file);
    }
  });

  // AJAX — Edit Profile (with avatar)
  document.getElementById('editProfileForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('edit_profile.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.text())
    .then(data => {
      if (data.trim() === "success") {
        editProfileModal.classList.remove('show');
        successModal.classList.add('show');
        // Update topbar avatar instantly
        document.getElementById('avatarBtn').src = avatarPreview.src;
      } else {
        alert("Error: " + data);
      }
    });
  });

  

  // AJAX — Change Password
  document.getElementById('changePasswordForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('change_password.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.text())
    .then(data => {
      if (data.trim() === "success") {
        changePasswordModal.classList.remove('show');
        successModal.classList.add('show');
      } else {
        alert("Error: " + data);
      }
    });
  });

  closeSuccess.addEventListener('click', () => {
    successModal.classList.remove('show');
  });

  window.addEventListener('click', (e) => {
    if (e.target === editProfileModal || e.target === changePasswordModal) {
      e.target.classList.remove('show');
    }
  });
});
