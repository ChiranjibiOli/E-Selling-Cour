<?php

require_once __DIR__ . '/../../core/Auth.php';

Auth::requireRole('student');
$currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$panelUser = Auth::user() ?? [];
$panelUserName = trim((string) ($panelUser['full_name'] ?? 'Student'));
$panelInitials = implode('', array_map(
    static fn (string $part): string => strtoupper(substr($part, 0, 1)),
    array_slice(preg_split('/\s+/', $panelUserName, -1, PREG_SPLIT_NO_EMPTY) ?: ['S'], 0, 2)
));
?>

<div class="logout-modal-overlay" id="logoutModal" role="dialog" aria-modal="true" aria-labelledby="studentLogoutTitle">
    <div class="logout-modal-card">
        <div class="logout-icon" aria-hidden="true">Exit</div>
        <h2 id="studentLogoutTitle">Log out?</h2>
        <p>You will need to sign in again to continue learning.</p>
        <div class="logout-modal-actions">
            <button type="button" class="cancel-logout-btn" id="cancelLogout">Cancel</button>
            <form action="logout.php" method="POST">
                <?php echo csrf_field(); ?>
                <button type="submit" class="confirm-logout-btn">Log out</button>
            </form>
        </div>
    </div>
</div>

<aside class="student-navbar role-navbar" aria-label="Student navigation">
    <div class="student-nav-container role-nav-container">
        <a href="student-dashboard.php" class="student-logo role-logo">
            <img class="role-logo-mark" src="assets/images/logo.svg" alt="">
            <span class="role-logo-copy"><strong><?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></strong><small>Student learning</small></span>
        </a>

        <ul class="student-nav-menu role-nav-menu" id="studentNav">
            <li class="role-nav-section">Learning</li>
            <li><a data-icon="⌂" href="student-dashboard.php" class="<?php echo $currentPage === 'student-dashboard.php' ? 'active' : ''; ?>"><span>Dashboard</span></a></li>
            <li><a data-icon="⌕" href="student-browse-courses.php" class="<?php echo in_array($currentPage, ['student-browse-courses.php', 'course-details.php'], true) ? 'active' : ''; ?>"><span>Browse</span></a></li>
            <li><a data-icon="◫" href="cart.php" class="<?php echo in_array($currentPage, ['cart.php', 'checkout.php'], true) ? 'active' : ''; ?>"><span>Cart</span></a></li>
            <li><a data-icon="▤" href="student-my-courses.php" class="<?php echo in_array($currentPage, ['student-my-courses.php', 'student-course-view.php'], true) ? 'active' : ''; ?>"><span>My courses</span></a></li>
            <li class="role-nav-section">Account</li>
            <li><a data-icon="●" href="student-notifications.php" class="<?php echo $currentPage === 'student-notifications.php' ? 'active' : ''; ?>"><span>Notifications</span></a></li>
            <li><a data-icon="○" href="student-profile.php" class="<?php echo $currentPage === 'student-profile.php' ? 'active' : ''; ?>"><span>Profile</span></a></li>
            <li class="student-logout-item"><button type="button" class="confirm-logout-btn" id="openLogoutModal" data-icon="↗">Log out</button></li>
        </ul>
    </div>
</aside>

<header class="panel-topbar">
    <button class="nav-toggle panel-menu-toggle" type="button" data-panel-menu-toggle aria-label="Toggle student navigation" aria-expanded="false" aria-controls="studentNav">
        <span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span>
    </button>
    <div class="panel-topbar-context">
        <span><?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?> / Student</span>
        <strong><?php echo htmlspecialchars((string) ($pageTitle ?? 'Dashboard'), ENT_QUOTES, 'UTF-8'); ?></strong>
    </div>
    <div class="panel-topbar-user">
        <span class="panel-user-avatar"><?php echo htmlspecialchars($panelInitials, ENT_QUOTES, 'UTF-8'); ?></span>
        <span class="panel-user-copy"><strong><?php echo htmlspecialchars($panelUserName, ENT_QUOTES, 'UTF-8'); ?></strong><small>Student</small></span>
    </div>
</header>
<button class="panel-sidebar-scrim" type="button" data-panel-sidebar-scrim aria-label="Close navigation"></button>
