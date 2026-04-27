<?php 
// Include db first
include __DIR__ . '/../db.php';
$database = new Database();
$conn = $database->getConnection();

session_start();

if (isset($_SESSION['userRole']) && $_SESSION['userRole'] === 'student') {
    $_SESSION['userRole'] = 'trainee';
}

// Set page info for sidebar
$currentPage = 'notices.php';
$pageTitle = 'Notices';
$pageSubtitle = 'Announcements & Updates';

// Check login after session_start
if (!isset($_SESSION['userId']) || !in_array($_SESSION['userRole'], ['trainee', 'student'])) {
    header("Location: ../login.php");
    exit;
}

$userId = $_SESSION['userId'];
$dbError = false;

if ($conn === null) {
    $dbError = true;
}

$studentNcLevel = 'NC I';

if ($conn) {
    try {
        $ncStmt = $conn->prepare("
            SELECT nc_level FROM student_program_enrollments 
            WHERE student_id = (SELECT StudID FROM student WHERE user_id = ? LIMIT 1) 
            AND enrollment_status = 'Active' 
            LIMIT 1
        ");
        $ncStmt->execute([$userId]);
        $ncLevel = $ncStmt->fetchColumn();
        if ($ncLevel) {
            $isEnrolled = true;
            $studentNcLevel = $ncLevel;
        }
    } catch (Exception $e) {
        $studentNcLevel = 'NC I';
    }
}

// Include the consistent sidebar
include 'sidebar_student.php';

if ($conn) {
  try {
    $stmt = $conn->prepare("
        SELECT * FROM notices 
        WHERE (nc_level = ? OR nc_level IS NULL OR nc_level = '' OR nc_level = '')
        ORDER BY created_at DESC
    ");
    $stmt->execute([$studentNcLevel]);
    $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($notices)) {
        $stmt = $conn->prepare("SELECT * FROM notices ORDER BY created_at DESC");
        $stmt->execute();
        $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
  } catch (Exception $e) {
    $notices = [];
    $dbError = true;
  }
} else {
  $notices = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Notices - Trainee</title>
  <link rel="stylesheet" href="css/notices.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="main-content">
  <div class="header">
    <h2> Notices & Announcements</h2>
    <p>Stay updated with the latest announcements - <strong><?= htmlspecialchars($studentNcLevel) ?></strong></p>
  </div>

  <?php if (!$isEnrolled): ?>
  <div style="padding: 15px; margin-bottom: 20px; background: #fef3c7; border-radius: 8px; color: #d97706;">
    <i class="fas fa-exclamation-triangle"></i> <strong>Not Enrolled</strong> - You are not currently enrolled. Please contact the admin/staff to enroll you.
  </div>
  <?php endif; ?>

  <!-- Search & Filters -->
  <div class="notice-controls">
    <div class="search-box">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="text" id="searchInput" placeholder="Search notices...">
    </div>
    <button id="filterDate" class="filter-btn">
      <i class="fa-regular fa-calendar"></i> Filter by Date
    </button>
  </div>

  <div class="notice-filters">
    <button class="filter-btn active" data-type="all">All Notices</button>
  </div>

  <!-- Notices Section -->
  <div class="notice-section">
    <?php if ($notices): ?>
      <?php foreach ($notices as $n): ?>
        <div class="notice-card-student">
          <div class="notice-icon-student">
            <i class="fa-regular fa-bell"></i>
          </div>
          <div class="notice-content-student">
            <div class="notice-header-student">
              <h3 class="notice-title-student"><?= htmlspecialchars($n['title']) ?></h3>
            </div>
            <div class="notice-message-student">
              <?= nl2br(htmlspecialchars($n['content'])) ?>
            </div>
            <div class="notice-footer-student">
              <span class="notice-date-student">
                <i class="fa-regular fa-calendar"></i> 
                <?= date("F j, Y - g:i A", strtotime($n['created_at'])) ?>
              </span>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="no-notices-student">
        <i class="fa-regular fa-face-frown"></i>
        <p>No notices available at the moment.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
// Search functionality
const searchInput = document.getElementById('searchInput');
const filterBtns = document.querySelectorAll('.filter-btn');
const noticeCards = document.querySelectorAll('.notice-card-student');

searchInput.addEventListener('input', function() {
  const query = this.value.toLowerCase();
  noticeCards.forEach(card => {
    const title = card.querySelector('.notice-title-student').textContent.toLowerCase();
    const message = card.querySelector('.notice-message-student').textContent.toLowerCase();
    card.style.display = (title.includes(query) || message.includes(query)) ? 'block' : 'none';
  });
});

// Filter functionality
filterBtns.forEach(btn => {
  btn.addEventListener('click', () => {
    // Remove active class from all buttons
    filterBtns.forEach(b => b.classList.remove('active'));
    // Add active class to clicked button
    btn.classList.add('active');
    
    const filterType = btn.dataset.type;
    
    noticeCards.forEach(card => {
      if (filterType === 'all') {
        card.style.display = 'block';
      } else {
        card.style.display = card.dataset.type === filterType ? 'block' : 'none';
      }
    });
  });
});

// Simple date filter (you can enhance this with a date picker)
document.getElementById('filterDate').addEventListener('click', function() {
  const selectedDate = prompt('Enter date (YYYY-MM-DD):');
  if (selectedDate) {
    noticeCards.forEach(card => {
      const dateElement = card.querySelector('.notice-date-student');
      const cardDate = dateElement.textContent.includes(selectedDate);
      card.style.display = cardDate ? 'block' : 'none';
    });
  }
});
</script>

<!-- Page-specific content ends where sidebar started the page-content div -->
<!-- No closing tags needed - sidebar_student.php provides them -->