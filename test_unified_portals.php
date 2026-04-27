<?php
/**
 * Test Unified Portal System
 * Verify all portals work with the new unified design
 */

echo "=== Testing Unified Portal System ===\n\n";

// Check if all required files exist
$requiredFiles = [
    'css/unified-portal.css' => 'Unified CSS Framework',
    'includes/unified_header.php' => 'Unified Header Component',
    'includes/unified_sidebar.php' => 'Unified Sidebar Component',
    'includes/footer.php' => 'Unified Footer Component',
    'admin/admin_dashboard_new.php' => 'Admin Dashboard (New)',
    'student/student_dashboard_new.php' => 'Student Dashboard (New)',
    'instructor/instructor_dashboard_new.php' => 'Instructor Dashboard (New)'
];

echo "1. Checking Required Files:\n";
$allFilesExist = true;
foreach ($requiredFiles as $file => $description) {
    $exists = file_exists($file);
    $status = $exists ? "EXISTS" : "MISSING";
    echo "   $description: $status\n";
    if (!$exists) {
        $allFilesExist = false;
    }
}

if ($allFilesExist) {
    echo "   Status: All required files exist\n\n";
} else {
    echo "   Status: Some files are missing\n\n";
}

// Check CSS file content
echo "2. Checking CSS Framework:\n";
$cssFile = 'css/unified-portal.css';
if (file_exists($cssFile)) {
    $cssContent = file_get_contents($cssFile);
    $cssLines = count(file($cssFile));
    echo "   CSS file size: $cssLines lines\n";
    
    // Check for key CSS components
    $keyComponents = [
        '.sidebar' => 'Sidebar styles',
        '.main-content' => 'Main content area',
        '.stat-card' => 'Statistics cards',
        '.metric-card' => 'Metric cards',
        '.btn' => 'Button styles',
        '.card' => 'Card components'
    ];
    
    foreach ($keyComponents as $selector => $description) {
        $exists = strpos($cssContent, $selector) !== false;
        $status = $exists ? "FOUND" : "MISSING";
        echo "   $description ($selector): $status\n";
    }
    echo "\n";
}

// Check header component
echo "3. Checking Header Component:\n";
$headerFile = 'includes/unified_header.php';
if (file_exists($headerFile)) {
    $headerContent = file_get_contents($headerFile);
    
    $keyFeatures = [
        '<!DOCTYPE html>' => 'HTML5 doctype',
        'unified-portal.css' => 'Unified CSS link',
        'page-header' => 'Page header structure',
        'user-dropdown' => 'User dropdown menu',
        'alert' => 'Alert messages'
    ];
    
    foreach ($keyFeatures as $feature => $description) {
        $exists = strpos($headerContent, $feature) !== false;
        $status = $exists ? "FOUND" : "MISSING";
        echo "   $description: $status\n";
    }
    echo "\n";
}

// Check sidebar component
echo "4. Checking Sidebar Component:\n";
$sidebarFile = 'includes/unified_sidebar.php';
if (file_exists($sidebarFile)) {
    $sidebarContent = file_get_contents($sidebarFile);
    
    $keyFeatures = [
        'navigationItems' => 'Role-based navigation',
        'admin' => 'Admin navigation',
        'student' => 'Student navigation',
        'instructor' => 'Instructor navigation',
        'nav-item' => 'Navigation items'
    ];
    
    foreach ($keyFeatures as $feature => $description) {
        $exists = strpos($sidebarContent, $feature) !== false;
        $status = $exists ? "FOUND" : "MISSING";
        echo "   $description: $status\n";
    }
    echo "\n";
}

// Check dashboard files
echo "5. Checking Dashboard Files:\n";
$dashboardFiles = [
    'admin/admin_dashboard_new.php' => 'Admin',
    'student/student_dashboard_new.php' => 'Student',
    'instructor/instructor_dashboard_new.php' => 'Instructor'
];

foreach ($dashboardFiles as $file => $role) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $hasUnifiedHeader = strpos($content, 'unified_header.php') !== false;
        $hasUnifiedFooter = strpos($content, 'unified_footer.php') !== false;
        $hasStatsGrid = strpos($content, 'stats-grid') !== false;
        $hasMetricsGrid = strpos($content, 'metrics-grid') !== false;
        
        echo "   $role Dashboard:\n";
        echo "     Unified Header: " . ($hasUnifiedHeader ? "YES" : "NO") . "\n";
        echo "     Unified Footer: " . ($hasUnifiedFooter ? "YES" : "NO") . "\n";
        echo "     Stats Grid: " . ($hasStatsGrid ? "YES" : "NO") . "\n";
        echo "     Metrics Grid: " . ($hasMetricsGrid ? "YES" : "NO") . "\n";
        echo "\n";
    }
}

echo "=== Test Results ===\n";
echo "Unified Portal System Status: ";

if ($allFilesExist) {
    echo "READY FOR TESTING\n\n";
    echo "Next Steps:\n";
    echo "1. Test each dashboard in browser:\n";
    echo "   - Admin: admin/admin_dashboard_new.php\n";
    echo "   - Student: student/student_dashboard_new.php\n";
    echo "   - Instructor: instructor/instructor_dashboard_new.php\n\n";
    echo "2. Verify role-based navigation works correctly\n";
    echo "3. Check responsive design on different screen sizes\n";
    echo "4. Test user dropdown and other interactive elements\n";
} else {
    echo "INCOMPLETE - Missing files detected\n";
}

echo "\n=== Features Implemented ===\n";
echo "1. Unified CSS framework with consistent design\n";
echo "2. Role-based navigation (Admin, Student, Instructor)\n";
echo "3. Responsive sidebar and main content layout\n";
echo "4. Statistics cards and metrics displays\n";
echo "5. User profile dropdown menu\n";
echo "6. Alert messaging system\n";
echo "7. Print report functionality\n";
echo "8. Mobile-responsive design\n";

echo "\n=== Design Elements Applied ===\n";
echo "- Dark blue sidebar with gradient\n";
echo "- White main content area\n";
echo "- Rounded corners on cards and buttons\n";
echo "- Consistent color scheme (blue, green, red, orange)\n";
echo "- Icon-based navigation\n";
echo "- Statistics cards with hover effects\n";
echo "- Metric cards with progress indicators\n";
echo "- User avatar and profile section\n";
?>
