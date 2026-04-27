<?php
  $current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
  <h2>TESDA Portal
      <img src="../images/image.png" alt="TESDA Logo" class="sidebar-logo">
  </h2>
  <p class="sub-title">Admin Dashboard</p>
  <link rel="stylesheet" href="css/admin.css">
  <hr class="sidebar-divider">

  <ul>
    <li class="<?= ($current_page == 'admin_dashboard.php') ? 'active' : '' ?>">
      <a href="admin_dashboard.php"><i class="fas fa-home"></i> Overview</a>
    </li>

    <li class="nav-section-title">Academic Records</li>
    <li class="<?= ($current_page == 'manage_transcripts.php') ? 'active' : '' ?>">
      <a href="manage_transcripts.php"><i class="fas fa-file-alt"></i> Transcripts (TOR)</a>
    </li>
    <li class="<?= ($current_page == 'manage_certificates.php') ? 'active' : '' ?>">
      <a href="manage_certificates.php"><i class="fas fa-certificate"></i> Certificates</a>
    </li>
    <li class="<?= ($current_page == 'manage_diplomas.php') ? 'active' : '' ?>">
      <a href="manage_diplomas.php"><i class="fas fa-award"></i> Diplomas</a>
    </li>
    <li class="<?= ($current_page == 'manage_documents.php') ? 'active' : '' ?>">
      <a href="manage_documents.php"><i class="fas fa-folder-open"></i> Document Repository</a>
    </li>
    <li class="<?= ($current_page == 'reports_documents.php') ? 'active' : '' ?>">
      <a href="reports_documents.php"><i class="fas fa-chart-bar"></i> Reports</a>
    </li>

    <li class="nav-section-title">Management</li>
    <li class="<?= ($current_page == 'manage_students.php') ? 'active' : '' ?>">
      <a href="manage_students.php"><i class="fa-solid fa-user-graduate"></i> Manage Students</a>
    </li>
    <li class="<?= ($current_page == 'courses.php') ? 'active' : '' ?>">
      <a href="courses.php"><i class="fas fa-book"></i> Manage Courses</a>
    </li>
    <li class="<?= ($current_page == 'class_schedule.php') ? 'active' : '' ?>">
      <a href="class_schedule.php"><i class="fas fa-calendar-alt"></i> Manage Schedules</a>
    </li>

    <li class="nav-section-title">Operations</li>
    <li class="<?= ($current_page == 'manage_grades.php') ? 'active' : '' ?>">
      <a href="manage_grades.php"><i class="fas fa-chart-bar"></i> Manage Grades</a>
    </li>
    <li class="<?= ($current_page == 'payment_records.php') ? 'active' : '' ?>">
      <a href="payment_records.php"><i class="fas fa-file-invoice-dollar"></i> Payment Records</a>
    </li>
    <li class="<?= ($current_page == 'post_notice.php') ? 'active' : '' ?>">
      <a href="post_notice.php"><i class="fas fa-bullhorn"></i> Post Notice</a>
    </li>

    <li class="nav-section-title">System</li>
    <li class="<?= ($current_page == 'users.php') ? 'active' : '' ?>">
      <a href="users.php"><i class="fas fa-users"></i> User Management</a>
    </li>

    <li>
      <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </li>
  </ul>
</div>
