<?php

require_once __DIR__ . '/../../core/Auth.php';

Auth::requireRole('admin');
$currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
?>

<nav class="admin-navbar role-navbar" aria-label="Admin navigation">
    <div class="admin-nav-container role-nav-container">
        <a href="admin-dashboard.php" class="admin-logo role-logo"><?php echo htmlspecialchars(APP_NAME); ?> Admin</a>
        <button class="nav-toggle" type="button" aria-label="Toggle admin navigation" aria-expanded="false" aria-controls="adminNav">
            <span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span>
        </button>
        <ul class="admin-nav-menu role-nav-menu" id="adminNav">
            <li><a data-icon="⌂" href="admin-dashboard.php" class="<?php echo $currentPage === 'admin-dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
            <li><a data-icon="◎" href="admin-instructors.php" class="<?php echo $currentPage === 'admin-instructors.php' ? 'active' : ''; ?>">Instructors</a></li>
            <li><a data-icon="▤" href="admin-courses.php" class="<?php echo in_array($currentPage, ['admin-courses.php', 'admin-course-changes.php', 'course-details.php'], true) ? 'active' : ''; ?>">Courses</a></li>
            <li><a data-icon="○" href="admin-users.php" class="<?php echo $currentPage === 'admin-users.php' ? 'active' : ''; ?>">Users</a></li>
            <li><a data-icon="◫" href="admin-orders.php" class="<?php echo in_array($currentPage, ['admin-orders.php', 'admin-order-details.php'], true) ? 'active' : ''; ?>">Orders</a></li>
            <li><a data-icon="◇" href="admin-withdrawals.php" class="<?php echo $currentPage === 'admin-withdrawals.php' ? 'active' : ''; ?>">Withdrawals</a></li>
            <li><a data-icon="↗" href="admin-reports.php" class="<?php echo $currentPage === 'admin-reports.php' ? 'active' : ''; ?>">Reports</a></li>
            <li><a data-icon="●" href="admin-notifications.php" class="<?php echo $currentPage === 'admin-notifications.php' ? 'active' : ''; ?>">Notifications</a></li>
            <li><a data-icon="⚙" href="admin-settings.php" class="<?php echo $currentPage === 'admin-settings.php' ? 'active' : ''; ?>">Settings</a></li>
            <li><form action="logout.php" method="POST"><?php echo csrf_field(); ?><button type="submit" class="admin-logout-btn role-logout-btn" data-icon="↗">Log out</button></form></li>
        </ul>
    </div>
</nav>