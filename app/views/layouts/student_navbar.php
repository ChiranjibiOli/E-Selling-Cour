<?php

require_once __DIR__ . '/../../core/Auth.php';

Auth::requireRole('student');
$currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
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

<nav class="student-navbar role-navbar" aria-label="Student navigation">
    <div class="student-nav-container role-nav-container">
        <a href="student-dashboard.php" class="student-logo role-logo"><?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?> Student</a>

        <button class="nav-toggle" type="button" aria-label="Toggle student navigation" aria-expanded="false" aria-controls="studentNav">
            <span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span>
        </button>

        <ul class="student-nav-menu role-nav-menu" id="studentNav">
            <li><a data-icon="⌂" href="student-dashboard.php" class="<?php echo $currentPage === 'student-dashboard.php' ? 'active' : ''; ?>"><span>Dashboard</span></a></li>
            <li><a data-icon="⌕" href="courses.php" class="<?php echo in_array($currentPage, ['courses.php', 'course-details.php'], true) ? 'active' : ''; ?>"><span>Browse</span></a></li>
            <li><a data-icon="◫" href="cart.php" class="<?php echo in_array($currentPage, ['cart.php', 'checkout.php'], true) ? 'active' : ''; ?>"><span>Cart</span></a></li>
            <li><a data-icon="▤" href="student-my-courses.php" class="<?php echo $currentPage === 'student-my-courses.php' ? 'active' : ''; ?>"><span>My courses</span></a></li>
            <li><a data-icon="●" href="student-notifications.php" class="<?php echo $currentPage === 'student-notifications.php' ? 'active' : ''; ?>"><span>Notifications</span></a></li>
            <li><a data-icon="○" href="student-profile.php" class="<?php echo $currentPage === 'student-profile.php' ? 'active' : ''; ?>"><span>Profile</span></a></li>
            <li class="student-logout-item"><button type="button" class="confirm-logout-btn" id="openLogoutModal" data-icon="↗">Log out</button></li>
        </ul>
    </div>
</nav>