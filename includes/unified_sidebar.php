<?php
/**
 * Unified Sidebar Component
 * Adapts navigation based on user role
 */

// Get current page and user information
$currentPage = basename($_SERVER['PHP_SELF']);
$userRole = $_SESSION['userRole'] ?? 'guest';
$userId = $_SESSION['userId'] ?? null;
$userName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
if (empty($userName)) {
    $userName = 'User';
}

// Determine portal type
$portalType = 'unknown';
if (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) {
    $portalType = 'admin';
} elseif (strpos($_SERVER['REQUEST_URI'], '/student/') !== false) {
    $portalType = 'student';
} elseif (strpos($_SERVER['REQUEST_URI'], '/instructor/') !== false) {
    $portalType = 'instructor';
}

// Navigation items based on role
$navigationItems = [
    'admin' => [
        'dashboard' => [
            'title' => 'Dashboard',
            'items' => [
                ['icon' => 'fas fa-home', 'label' => 'Overview', 'href' => 'admin_dashboard.php'],
                ['icon' => 'fas fa-chart-bar', 'label' => 'Reports & Analytics', 'href' => 'reports.php'],
            ]
        ],
        'management' => [
            'title' => 'Management',
            'items' => [
                ['icon' => 'fas fa-user-graduate', 'label' => 'Manage Students', 'href' => 'manage_students.php'],
                ['icon' => 'fas fa-chalkboard-teacher', 'label' => 'Manage Instructors', 'href' => 'manage_instructors.php'],
                ['icon' => 'fas fa-book', 'label' => 'Manage Courses', 'href' => 'courses.php'],
                ['icon' => 'fas fa-calendar-alt', 'label' => 'Manage Schedules', 'href' => 'class_schedule.php'],
                ['icon' => 'fas fa-graduation-cap', 'label' => 'NC Level Subjects', 'href' => 'manage_nc_subjects.php'],
            ]
        ],
        'academic' => [
            'title' => 'Academic Records',
            'items' => [
                ['icon' => 'fas fa-file-alt', 'label' => 'Transcripts (TOR)', 'href' => 'manage_transcripts.php'],
                ['icon' => 'fas fa-certificate', 'label' => 'Certificates', 'href' => 'manage_certificates.php'],
                ['icon' => 'fas fa-award', 'label' => 'Diplomas', 'href' => 'manage_diplomas.php'],
                ['icon' => 'fas fa-folder-open', 'label' => 'Document Repository', 'href' => 'manage_documents.php'],
                ['icon' => 'fas fa-chart-bar', 'label' => 'Reports & Analytics', 'href' => 'reports_documents.php'],
                ['icon' => 'fas fa-users', 'label' => 'User Management', 'href' => 'users.php'],
            ]
        ]
    ],
    'student' => [
        'dashboard' => [
            'title' => 'Dashboard',
            'items' => [
                ['icon' => 'fas fa-home', 'label' => 'Overview', 'href' => 'student_dashboard.php'],
                ['icon' => 'fas fa-chart-line', 'label' => 'My Progress', 'href' => 'my_progress.php'],
            ]
        ],
        'learning' => [
            'title' => 'Learning',
            'items' => [
                ['icon' => 'fas fa-book', 'label' => 'My Modules', 'href' => 'learning_modules.php'],
                ['icon' => 'fas fa-folder', 'label' => 'Materials', 'href' => 'materials.php'],
                ['icon' => 'fas fa-question-circle', 'label' => 'Quizzes', 'href' => 'quizzes.php'],
                ['icon' => 'fas fa-edit', 'label' => 'Assignments', 'href' => 'assignments.php'],
                ['icon' => 'fas fa-clipboard-check', 'label' => 'Assessments', 'href' => 'my_competencies.php'],
                ['icon' => 'fas fa-chart-bar', 'label' => 'Grades', 'href' => 'my_grades.php'],
            ]
        ],
        'personal' => [
            'title' => 'Personal Records',
            'items' => [
                ['icon' => 'fas fa-calendar', 'label' => 'Schedule', 'href' => 'schedule.php'],
                ['icon' => 'fas fa-file-alt', 'label' => 'Transcripts', 'href' => 'transcripts.php'],
                ['icon' => 'fas fa-certificate', 'label' => 'Certificates', 'href' => 'certificates.php'],
                ['icon' => 'fas fa-award', 'label' => 'Diplomas', 'href' => 'diplomas.php'],
                ['icon' => 'fas fa-file-import', 'label' => 'Request Documents', 'href' => 'request_document.php'],
                ['icon' => 'fas fa-bell', 'label' => 'Notices', 'href' => 'notices.php'],
                ['icon' => 'fas fa-user', 'label' => 'Profile', 'href' => 'profile.php'],
            ]
        ]
    ],
    'instructor' => [
        'dashboard' => [
            'title' => 'Dashboard',
            'items' => [
                ['icon' => 'fas fa-home', 'label' => 'Overview', 'href' => 'instructor_dashboard.php'],
                ['icon' => 'fas fa-chart-bar', 'label' => 'Reports & Analytics', 'href' => 'reports.php'],
            ]
        ],
        'teaching' => [
            'title' => 'Teaching',
            'items' => [
                ['icon' => 'fas fa-book', 'label' => 'My Modules', 'href' => 'my_modules.php'],
                ['icon' => 'fas fa-folder', 'label' => 'Materials', 'href' => 'learning_materials.php'],
                ['icon' => 'fas fa-question-circle', 'label' => 'Quizzes', 'href' => 'quizzes.php'],
                ['icon' => 'fas fa-edit', 'label' => 'Assignments', 'href' => 'assignments.php'],
                ['icon' => 'fas fa-clipboard-check', 'label' => 'Assessments', 'href' => 'assessments.php'],
            ]
        ],
        'students' => [
            'title' => 'Students',
            'items' => [
                ['icon' => 'fas fa-users', 'label' => 'My Students', 'href' => 'my_students.php'],
                ['icon' => 'fas fa-chart-line', 'label' => 'Student Progress', 'href' => 'student_progress.php'],
                ['icon' => 'fas fa-graduation-cap', 'label' => 'Grade Management', 'href' => 'grade_management.php'],
            ]
        ]
    ]
];

// Get navigation for current role
$currentNavigation = $navigationItems[$userRole] ?? [];

// Portal titles
$portalTitles = [
    'admin' => 'Admin Portal',
    'student' => 'Trainee Portal', 
    'instructor' => 'Trainer Portal'
];

$portalTitle = $portalTitles[$userRole] ?? 'Portal';
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="fas fa-wrench"></i>
            <span>TESDA</span>
        </div>
        <p class="sidebar-subtitle"><?= htmlspecialchars($portalTitle) ?></p>
    </div>
    
    <nav class="sidebar-nav">
        <?php foreach ($currentNavigation as $section => $data): ?>
            <div class="nav-section">
                <p class="nav-section-title"><?= htmlspecialchars($data['title']) ?></p>
                
                <?php foreach ($data['items'] as $item): ?>
                    <?php 
                    // Check if current page matches this item
                    $isActive = ($currentPage === $item['href']);
                    ?>
                    <a href="<?= htmlspecialchars($item['href']) ?>" 
                       class="nav-item <?= $isActive ? 'active' : '' ?>">
                        <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                        <span><?= htmlspecialchars($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-info">
                <h4><?= htmlspecialchars($userName) ?></h4>
                <p><?= htmlspecialchars(ucfirst($userRole)) ?></p>
            </div>
        </div>
    </div>
</aside>
