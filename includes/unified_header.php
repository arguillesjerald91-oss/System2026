<?php
/**
 * Unified Header Component
 * Used across all portals (Admin, Student, Instructor)
 */

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get user information
$userId = $_SESSION['userId'] ?? null;
$userRole = $_SESSION['userRole'] ?? 'guest';
$userName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
if (empty($userName)) {
    $userName = ucfirst($userRole);
}

// Portal type based on current directory or session
$portalType = 'unknown';
$currentDir = basename(dirname(__FILE__));
if (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) {
    $portalType = 'admin';
} elseif (strpos($_SERVER['REQUEST_URI'], '/student/') !== false) {
    $portalType = 'student';
} elseif (strpos($_SERVER['REQUEST_URI'], '/instructor/') !== false) {
    $portalType = 'instructor';
}

// Portal titles
$portalTitles = [
    'admin' => 'Admin Portal',
    'student' => 'Trainee Portal',
    'instructor' => 'Trainer Portal'
];

$portalTitle = $portalTitles[$portalType] ?? 'Portal';

// Default avatar
$avatarPath = "../images/image.png";
if (!empty($_SESSION['avatar'])) {
    $avatarPath = "../" . ltrim($_SESSION['avatar'], '/');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'TESDA ' . $portalTitle) ?></title>
    <link rel="stylesheet" href="../css/unified-portal.css">
    <?php
    // Load advanced document management CSS for related pages
    $currentPage = basename($_SERVER['PHP_SELF']);
    $docPages = [
        'manage_transcripts.php', 'manage_certificates.php', 'manage_diplomas.php',
        'manage_documents.php', 'reports_documents.php', 'staff_document_requests.php',
        'transcripts.php', 'certificates.php', 'diplomas.php', 'request_document.php',
        'documents.php'
    ];
    if (in_array($currentPage, $docPages)) {
        echo '<link rel="stylesheet" href="../css/advanced_document_styles.css">' . PHP_EOL;
    }
    ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php if (file_exists('../css/custom.css')): ?>
        <link rel="stylesheet" href="../css/custom.css">
    <?php endif; ?>
</head>
<body>
<div class="portal-container">
    
    <!-- Sidebar -->
    <?php include 'unified_sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title-section">
                <h1><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
                <p><?= htmlspecialchars($pageSubtitle ?? $portalTitle . ' Overview') ?></p>
            </div>
            <div class="header-actions">
                <?php if (isset($showPrintButton) && $showPrintButton): ?>
                    <button class="btn btn-outline" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                <?php endif; ?>
                
                <div class="user-menu">
                    <img src="<?= htmlspecialchars($avatarPath) ?>" alt="User Avatar" class="user-avatar" id="userAvatarBtn">
                    
                    <!-- User Dropdown -->
                    <div class="user-dropdown" id="userDropdown">
                        <div class="user-dropdown-header">
                            <img src="<?= htmlspecialchars($avatarPath) ?>" alt="User Avatar">
                            <div>
                                <div class="user-name"><?= htmlspecialchars($userName) ?></div>
                                <div class="user-role"><?= htmlspecialchars(ucfirst($userRole)) ?></div>
                            </div>
                        </div>
                        <div class="user-dropdown-divider"></div>
                        <a href="#" class="user-dropdown-item" id="editProfileBtn">
                            <i class="fas fa-user-pen"></i> Edit Profile
                        </a>
                        <a href="#" class="user-dropdown-item" id="changePasswordBtn">
                            <i class="fas fa-key"></i> Change Password
                        </a>
                        <div class="user-dropdown-divider"></div>
                        <a href="../logout.php" class="user-dropdown-item logout">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Page Content -->
        <div class="page-content">
            
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($_SESSION['success']) ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

<style>
/* User Menu Styles */
.user-menu {
    position: relative;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-full);
    cursor: pointer;
    border: 2px solid var(--white);
    box-shadow: var(--shadow-sm);
    transition: all 0.2s ease;
}

.user-avatar:hover {
    transform: scale(1.05);
    box-shadow: var(--shadow-md);
}

.user-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    background: var(--white);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-xl);
    min-width: 250px;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.2s ease;
    margin-top: var(--spacing-2);
}

.user-dropdown.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.user-dropdown-header {
    display: flex;
    align-items: center;
    gap: var(--spacing-3);
    padding: var(--spacing-4);
    border-bottom: 1px solid #e5e7eb;
}

.user-dropdown-header img {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-full);
}

.user-name {
    font-weight: 600;
    font-size: var(--font-size-sm);
    color: var(--black);
}

.user-role {
    font-size: var(--font-size-xs);
    color: var(--neutral-gray);
}

.user-dropdown-divider {
    height: 1px;
    background: #e5e7eb;
}

.user-dropdown-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-3);
    padding: var(--spacing-3) var(--spacing-4);
    color: var(--black);
    text-decoration: none;
    font-size: var(--font-size-sm);
    transition: background-color 0.2s ease;
}

.user-dropdown-item:hover {
    background: var(--light-gray);
}

.user-dropdown-item.logout {
    color: var(--danger-red);
}

.user-dropdown-item.logout:hover {
    background: var(--danger-light);
}

/* Alert Styles */
.alert {
    display: flex;
    align-items: center;
    gap: var(--spacing-3);
    padding: var(--spacing-4);
    border-radius: var(--radius-lg);
    margin-bottom: var(--spacing-6);
    font-size: var(--font-size-sm);
}

.alert-success {
    background: var(--success-light);
    color: var(--success-green);
    border: 1px solid var(--success-green);
}

.alert-danger {
    background: var(--danger-light);
    color: var(--danger-red);
    border: 1px solid var(--danger-red);
}

.alert-warning {
    background: #fef3c7;
    color: var(--warning-orange);
    border: 1px solid var(--warning-orange);
}

.alert-info {
    background: var(--light-blue);
    color: var(--secondary-blue);
    border: 1px solid var(--secondary-blue);
}
</style>

<script>
// User Dropdown Toggle
document.getElementById('userAvatarBtn').addEventListener('click', function(e) {
    e.stopPropagation();
    const dropdown = document.getElementById('userDropdown');
    dropdown.classList.toggle('show');
});

// Close dropdown when clicking outside
document.addEventListener('click', function() {
    const dropdown = document.getElementById('userDropdown');
    dropdown.classList.remove('show');
});

// Prevent dropdown from closing when clicking inside it
document.getElementById('userDropdown').addEventListener('click', function(e) {
    e.stopPropagation();
});

// Edit Profile Modal (placeholder)
document.getElementById('editProfileBtn').addEventListener('click', function(e) {
    e.preventDefault();
    alert('Edit Profile functionality would be implemented here');
});

// Change Password Modal (placeholder)
document.getElementById('changePasswordBtn').addEventListener('click', function(e) {
    e.preventDefault();
    alert('Change Password functionality would be implemented here');
});
</script>
