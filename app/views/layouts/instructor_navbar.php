<?php

require_once __DIR__ . '/../../middleware/InstructorMiddleware.php';

InstructorMiddleware::handle();
$currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
?>

<nav class="instructor-navbar role-navbar" aria-label="Instructor navigation">
    <div class="instructor-nav-container role-nav-container">
        <a href="instructor-dashboard.php" class="instructor-logo role-logo"><?php echo htmlspecialchars(APP_NAME); ?> Instructor</a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="instructorNav">
            <span class="sr-only">Toggle instructor navigation</span>
            <span></span><span></span><span></span>
        </button>
        <ul class="instructor-nav-menu role-nav-menu" id="instructorNav">
            <li><a href="instructor-dashboard.php" class="<?php echo $currentPage === 'instructor-dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
            <li><a href="instructor-courses.php" class="<?php echo in_array($currentPage, ['instructor-courses.php', 'instructor-edit-course.php', 'course-details.php'], true) ? 'active' : ''; ?>">My courses</a></li>
            <li><a href="instructor-create-course.php" class="<?php echo $currentPage === 'instructor-create-course.php' ? 'active' : ''; ?>">Create course</a></li>
            <li><a href="instructor-students.php" class="<?php echo $currentPage === 'instructor-students.php' ? 'active' : ''; ?>">Students</a></li>
            <li><a href="instructor-sales.php" class="<?php echo $currentPage === 'instructor-sales.php' ? 'active' : ''; ?>">Sales</a></li>
            <li><a href="instructor-withdrawals.php" class="<?php echo $currentPage === 'instructor-withdrawals.php' ? 'active' : ''; ?>">Withdrawals</a></li>
            <li><a href="instructor-payout-account.php" class="<?php echo $currentPage === 'instructor-payout-account.php' ? 'active' : ''; ?>">Payout account</a></li>
            <li><a href="my-notifications.php">Notifications</a></li>
            <li><a href="instructor-profile.php" class="<?php echo $currentPage === 'instructor-profile.php' ? 'active' : ''; ?>">Profile</a></li>
            <li>
                <form action="logout.php" method="POST">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="instructor-logout-btn role-logout-btn">Log out</button>
                </form>
            </li>
        </ul>
    </div>
</nav>
