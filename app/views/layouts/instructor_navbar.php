<?php

require_once __DIR__ . '/../../middleware/InstructorMiddleware.php';

InstructorMiddleware::handle();
$currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
?>

<nav class="instructor-navbar role-navbar" aria-label="Instructor navigation">
    <div class="instructor-nav-container role-nav-container">
        <a href="instructor-dashboard.php" class="instructor-logo role-logo"><?php echo htmlspecialchars(APP_NAME); ?> Instructor</a>
        <button class="nav-toggle" type="button" aria-label="Toggle instructor navigation" aria-expanded="false" aria-controls="instructorNav">
            <span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span>
        </button>
        <ul class="instructor-nav-menu role-nav-menu" id="instructorNav">
            <li><a data-icon="⌂" href="instructor-dashboard.php" class="<?php echo $currentPage === 'instructor-dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
            <li><a data-icon="▤" href="instructor-courses.php" class="<?php echo in_array($currentPage, ['instructor-courses.php', 'instructor-edit-course.php', 'course-details.php'], true) ? 'active' : ''; ?>">My courses</a></li>
            <li><a data-icon="＋" href="instructor-create-course.php" class="<?php echo $currentPage === 'instructor-create-course.php' ? 'active' : ''; ?>">Create course</a></li>
            <li><a data-icon="◎" href="instructor-students.php" class="<?php echo $currentPage === 'instructor-students.php' ? 'active' : ''; ?>">Students</a></li>
            <li><a data-icon="↗" href="instructor-sales.php" class="<?php echo $currentPage === 'instructor-sales.php' ? 'active' : ''; ?>">Sales</a></li>
            <li><a data-icon="◫" href="instructor-withdrawals.php" class="<?php echo $currentPage === 'instructor-withdrawals.php' ? 'active' : ''; ?>">Withdrawals</a></li>
            <li><a data-icon="◇" href="instructor-payout-account.php" class="<?php echo $currentPage === 'instructor-payout-account.php' ? 'active' : ''; ?>">Payout</a></li>
            <li><a data-icon="●" href="my-notifications.php">Notifications</a></li>
            <li><a data-icon="○" href="instructor-profile.php" class="<?php echo $currentPage === 'instructor-profile.php' ? 'active' : ''; ?>">Profile</a></li>
            <li><form action="logout.php" method="POST"><?php echo csrf_field(); ?><button type="submit" class="instructor-logout-btn role-logout-btn" data-icon="↗">Log out</button></form></li>
        </ul>
    </div>
</nav>