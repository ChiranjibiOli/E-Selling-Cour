<?php

require_once __DIR__ . '/../../middleware/InstructorMiddleware.php';

InstructorMiddleware::handle();
$currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$panelUser = Auth::user() ?? [];
$panelUserName = trim((string) ($panelUser['full_name'] ?? 'Instructor'));
$panelInitials = implode('', array_map(
    static fn (string $part): string => strtoupper(substr($part, 0, 1)),
    array_slice(preg_split('/\s+/', $panelUserName, -1, PREG_SPLIT_NO_EMPTY) ?: ['I'], 0, 2)
));
?>

<aside class="instructor-navbar role-navbar" aria-label="Instructor navigation">
    <div class="instructor-nav-container role-nav-container">
        <a href="instructor-dashboard.php" class="instructor-logo role-logo">
            <img class="role-logo-mark" src="assets/images/logo.svg" alt="">
            <span class="role-logo-copy"><strong><?php echo htmlspecialchars(APP_NAME); ?></strong><small>Instructor studio</small></span>
        </a>
        <ul class="instructor-nav-menu role-nav-menu" id="instructorNav">
            <li class="role-nav-section">Workspace</li>
            <li><a data-icon="⌂" href="instructor-dashboard.php" class="<?php echo $currentPage === 'instructor-dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
            <li class="role-nav-section">Courses</li>
            <li><a data-icon="▤" href="instructor-courses.php" class="<?php echo in_array($currentPage, ['instructor-courses.php', 'instructor-edit-course.php', 'course-details.php'], true) ? 'active' : ''; ?>">My courses</a></li>
            <li><a data-icon="＋" href="instructor-create-course.php" class="<?php echo $currentPage === 'instructor-create-course.php' ? 'active' : ''; ?>">Create course</a></li>
            <li class="role-nav-section">Business</li>
            <li><a data-icon="◎" href="instructor-students.php" class="<?php echo $currentPage === 'instructor-students.php' ? 'active' : ''; ?>">Students</a></li>
            <li><a data-icon="↗" href="instructor-sales.php" class="<?php echo $currentPage === 'instructor-sales.php' ? 'active' : ''; ?>">Sales</a></li>
            <li><a data-icon="◫" href="instructor-withdrawals.php" class="<?php echo $currentPage === 'instructor-withdrawals.php' ? 'active' : ''; ?>">Withdrawals</a></li>
            <li><a data-icon="◇" href="instructor-payout-account.php" class="<?php echo $currentPage === 'instructor-payout-account.php' ? 'active' : ''; ?>">Payout</a></li>
            <li class="role-nav-section">Account</li>
            <li><a data-icon="●" href="my-notifications.php">Notifications</a></li>
            <li><a data-icon="○" href="instructor-profile.php" class="<?php echo $currentPage === 'instructor-profile.php' ? 'active' : ''; ?>">Profile</a></li>
            <li><form action="logout.php" method="POST"><?php echo csrf_field(); ?><button type="submit" class="instructor-logout-btn role-logout-btn" data-icon="↗">Log out</button></form></li>
        </ul>
    </div>
</aside>

<header class="panel-topbar">
    <button class="nav-toggle panel-menu-toggle" type="button" data-panel-menu-toggle aria-label="Toggle instructor navigation" aria-expanded="false" aria-controls="instructorNav">
        <span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span>
    </button>
    <div class="panel-topbar-context">
        <span><?php echo htmlspecialchars(APP_NAME); ?> / Instructor</span>
        <strong><?php echo htmlspecialchars((string) ($pageTitle ?? 'Dashboard')); ?></strong>
    </div>
    <div class="panel-topbar-user">
        <span class="panel-user-avatar"><?php echo htmlspecialchars($panelInitials); ?></span>
        <span class="panel-user-copy"><strong><?php echo htmlspecialchars($panelUserName); ?></strong><small>Instructor</small></span>
    </div>
</header>
<button class="panel-sidebar-scrim" type="button" data-panel-sidebar-scrim aria-label="Close navigation"></button>
