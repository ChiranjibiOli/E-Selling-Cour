<?php

require_once __DIR__ . '/../../core/Auth.php';

Auth::requireRole('admin');
$currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$panelUser = Auth::user() ?? [];
$panelUserName = trim((string) ($panelUser['full_name'] ?? 'Administrator'));
$panelInitials = implode('', array_map(
    static fn (string $part): string => strtoupper(substr($part, 0, 1)),
    array_slice(preg_split('/\s+/', $panelUserName, -1, PREG_SPLIT_NO_EMPTY) ?: ['A'], 0, 2)
));
?>

<aside class="admin-navbar role-navbar" aria-label="Admin navigation">
    <div class="admin-nav-container role-nav-container">
        <a href="admin-dashboard.php" class="admin-logo role-logo">
            <img class="role-logo-mark" src="assets/images/logo.svg" alt="">
            <span class="role-logo-copy"><strong><?php echo htmlspecialchars(APP_NAME); ?></strong><small>Admin console</small></span>
        </a>
        <ul class="admin-nav-menu role-nav-menu" id="adminNav">
            <li class="role-nav-section">Workspace</li>
            <li><a data-icon="⌂" href="admin-dashboard.php" class="<?php echo $currentPage === 'admin-dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
            <li class="role-nav-section">Review</li>
            <li><a data-icon="◎" href="admin-instructors.php" class="<?php echo $currentPage === 'admin-instructors.php' ? 'active' : ''; ?>">Instructors</a></li>
            <li><a data-icon="▤" href="admin-courses.php" class="<?php echo in_array($currentPage, ['admin-courses.php', 'admin-course-changes.php', 'course-details.php'], true) ? 'active' : ''; ?>">Courses</a></li>
            <li><a data-icon="×" href="admin-course-removal.php" class="<?php echo $currentPage === 'admin-course-removal.php' ? 'active' : ''; ?>">Course cleanup</a></li>
            <li class="role-nav-section">Operations</li>
            <li><a data-icon="○" href="admin-users.php" class="<?php echo $currentPage === 'admin-users.php' ? 'active' : ''; ?>">Users</a></li>
            <li><a data-icon="◫" href="admin-orders.php" class="<?php echo in_array($currentPage, ['admin-orders.php', 'admin-order-details.php'], true) ? 'active' : ''; ?>">Orders</a></li>
            <li><a data-icon="◇" href="admin-withdrawals.php" class="<?php echo $currentPage === 'admin-withdrawals.php' ? 'active' : ''; ?>">Withdrawals</a></li>
            <li class="role-nav-section">Insights</li>
            <li><a data-icon="↗" href="admin-reports.php" class="<?php echo $currentPage === 'admin-reports.php' ? 'active' : ''; ?>">Reports</a></li>
            <li><a data-icon="●" href="admin-notifications.php" class="<?php echo $currentPage === 'admin-notifications.php' ? 'active' : ''; ?>">Notifications</a></li>
            <li class="role-nav-section">Account</li>
            <li><a data-icon="♙" href="admin-profile.php" class="<?php echo $currentPage === 'admin-profile.php' ? 'active' : ''; ?>">Profile</a></li>
            <li><a data-icon="⚙" href="admin-settings.php" class="<?php echo $currentPage === 'admin-settings.php' ? 'active' : ''; ?>">Settings</a></li>
            <li><form action="logout.php" method="POST"><?php echo csrf_field(); ?><button type="submit" class="admin-logout-btn role-logout-btn" data-icon="↗">Log out</button></form></li>
        </ul>
    </div>
</aside>

<header class="panel-topbar">
    <button class="nav-toggle panel-menu-toggle" type="button" data-panel-menu-toggle aria-label="Toggle admin navigation" aria-expanded="false" aria-controls="adminNav">
        <span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span>
    </button>
    <div class="panel-topbar-context">
        <span><?php echo htmlspecialchars(APP_NAME); ?> / Admin</span>
        <strong><?php echo htmlspecialchars((string) ($pageTitle ?? 'Dashboard')); ?></strong>
    </div>
    <div class="panel-topbar-user">
        <span class="panel-user-avatar"><?php echo htmlspecialchars($panelInitials); ?></span>
        <span class="panel-user-copy"><strong><?php echo htmlspecialchars($panelUserName); ?></strong><small>Administrator</small></span>
    </div>
</header>
<button class="panel-sidebar-scrim" type="button" data-panel-sidebar-scrim aria-label="Close navigation"></button>
