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

  // Handle password warning banner link click
  const changePasswordLink = document.getElementById('changePasswordLink');
  if (changePasswordLink) {
    changePasswordLink.addEventListener('click', (e) => {
      e.preventDefault();
      document.getElementById('changePasswordForm').reset();
      changePasswordModal.classList.add('show');
    });
  }

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
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        editProfileModal.classList.remove('show');
        successModal.classList.add('show');
        // Update topbar avatar with server path if provided
        if (data.avatarPath) {
          document.getElementById('avatarBtn').src = data.avatarPath;
        }
      } else {
        alert("Error: " + (data.message || 'Unknown error'));
      }
    })
    .catch(err => alert('Error: ' + err.message));
  });

  // AJAX — Change Password
  document.getElementById('changePasswordForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('change_password.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        changePasswordModal.classList.remove('show');
        successModal.classList.add('show');
      } else {
        alert("Error: " + (data.message || 'Unknown error'));
      }
    })
    .catch(err => alert('Error: ' + err.message));
  });

  closeSuccess.addEventListener('click', () => {
    successModal.classList.remove('show');
    setTimeout(() => location.reload(), 500);
  });

  // Auto-close and reload success modal after 2 seconds
  const successModalObserver = new MutationObserver(() => {
    if (successModal.classList.contains('show')) {
      setTimeout(() => {
        successModal.classList.remove('show');
        setTimeout(() => location.reload(), 500);
      }, 2000);
    }
  });
  successModalObserver.observe(successModal, { attributes: true, attributeFilter: ['class'] });

  window.addEventListener('click', (e) => {
    if (e.target === editProfileModal || e.target === changePasswordModal) {
      e.target.classList.remove('show');
    }
  });


  // === NOTIFICATIONS ===
  const notificationBtn = document.getElementById('notificationBtn');
  const notifDropdown = document.getElementById('notifDropdown');
  const notifCount = document.getElementById('notifCount');
  const markAllBtn = document.getElementById('markAllRead');
  const notifList = notifDropdown ? notifDropdown.querySelector('.notif-list') : null;

  if (notificationBtn && notifDropdown && notifCount && markAllBtn && notifList) {
    // Toggle dropdown
    notificationBtn.addEventListener('click', () => {
      notifDropdown.classList.toggle('show');
    });

    // Close if clicked outside
    document.addEventListener('click', (e) => {
      if (!notificationBtn.contains(e.target) && !notifDropdown.contains(e.target)) {
        notifDropdown.classList.remove('show');
      }
    });

    // Load notifications from server
    function loadNotifications() {
      fetch('fetch_notifications.php')
        .then((res) => res.json())
        .then((data) => {
          console.log('Notifications received:', data); // Debug log
          notifList.innerHTML = '';
          let unread = 0;

          if (!Array.isArray(data) || data.length === 0) {
            notifList.innerHTML = '<div class="notif-item">No notices yet.</div>';
            notifCount.textContent = '';
            return;
          }

          data.forEach((n) => {
            const item = document.createElement('div');
            item.classList.add('notif-item');
            if (String(n.is_read) === '0') {
              item.classList.add('unread');
              unread++;
            }

            item.dataset.id = n.id || n.notice_id || '';
            
            // Set icon based on type
            let icon = 'fa-bullhorn';
            if (n.type === 'grade') icon = 'fa-graduation-cap';
            if (n.type === 'enrollment') icon = 'fa-user-check';
            if (n.type === 'notice') icon = 'fa-bell';
            if (n.type === 'payment') icon = 'fa-money-bill';
            if (n.type === 'tuition_fee') icon = 'fa-dollar-sign';
            if (n.type === 'schedule') icon = 'fa-calendar';
            if (n.type === 'course') icon = 'fa-book';
            
            item.innerHTML = `
              <i class="fa-solid ${icon}"></i>
              <div class="notif-content">
                <strong>${n.title || 'Notification'}</strong>
                <small>${n.message || ''}</small>
              </div>
              <span class="notif-date">${new Date(n.created_at).toLocaleString()}</span>
            `;

            item.addEventListener('click', () => {
              if (item.classList.contains('unread')) {
                const formData = new FormData();
                formData.append('type', n.type || 'notice');
                formData.append('id', n.id || '');
                if (n.type === 'grade' && n.message) {
                  // Extract subject code from message (format: "SUBCODE — Grade: ...")
                  const match = n.message.match(/^([A-Z0-9\-]+)\s+—/);
                  if (match) formData.append('subject_code', match[1]);
                }
                
                fetch('mark_read.php', {
                  method: 'POST',
                  body: formData
                }).then(() => loadNotifications());
              }
            });

            notifList.appendChild(item);
          });

          notifCount.textContent = unread > 0 ? unread : '';
        })
        .catch(() => {
          notifList.innerHTML = '<div class="notif-item">Unable to load notifications.</div>';
          notifCount.textContent = '';
        });
    }

    // Mark all as read
    markAllBtn.addEventListener('click', () => {
      // Optimistic UI: remove all unread badges immediately
      notifList.querySelectorAll('.notif-item.unread').forEach((el) => el.classList.remove('unread'));
      notifCount.textContent = '';
      notifCount.style.display = 'none'; // Hide the badge immediately

      fetch('mark_all_read.php', { method: 'POST' })
        .then(res => res.text())
        .then(result => {
          if (result.trim() === 'success') {
            // Reload after 300ms to ensure database is updated and sync UI
            setTimeout(() => loadNotifications(), 300);
          }
        })
        .catch(() => {
          // On error, reload to sync state
          setTimeout(() => loadNotifications(), 500);
        });
    });

    // Initial load + poll every 30s
    loadNotifications();
    setInterval(loadNotifications, 30000);
  }

});