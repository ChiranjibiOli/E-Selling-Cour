<?php

require_once __DIR__ . '/../../core/Auth.php';

$currentUser = Auth::user();
$currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$isLoggedIn = $currentUser !== null;
$role = $currentUser['role'] ?? '';
?>

<link rel="stylesheet" href="assets/css/navbars/public-navbar.css?v=14">

<?php if ($isLoggedIn): ?>
<div class="logout-modal-overlay" id="logoutModal" role="dialog" aria-modal="true" aria-labelledby="logoutTitle">
    <div class="logout-modal-card">
        <div class="logout-icon" aria-hidden="true">Exit</div>
        <h2 id="logoutTitle">Log out?</h2>
        <p>You will need to sign in again to access your dashboard.</p>
        <div class="logout-modal-actions">
            <button type="button" class="cancel-logout-btn" id="cancelLogout">Cancel</button>
            <form action="logout.php" method="POST">
                <?php echo csrf_field(); ?>
                <button type="submit" class="confirm-logout-btn">Log out</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<header class="site-header">
    <nav class="navbar container" aria-label="Primary navigation">
        <a class="logo" href="index.php" aria-label="<?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?> home">
            <span class="logo-mark" aria-hidden="true">C</span>
            <span><?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></span>
        </a>

        <button class="nav-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" aria-controls="publicNav">
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
        </button>

        <div class="nav-panel" id="publicNav">
            <ul class="nav-links">
                <li><a href="index.php" class="<?php echo $currentPage === 'index.php' ? 'active' : ''; ?>">Home</a></li>
                <li><a href="courses.php" class="<?php echo in_array($currentPage, ['courses.php', 'course-details.php'], true) ? 'active' : ''; ?>">Courses</a></li>
                <?php if ($isLoggedIn && $role === 'student'): ?>
                    <li><a href="cart.php" class="<?php echo in_array($currentPage, ['cart.php', 'checkout.php', 'checkout-success.php'], true) ? 'active' : ''; ?>">Cart</a></li>
                    <li><a href="student-my-courses.php" class="<?php echo in_array($currentPage, ['student-my-courses.php', 'student-course-view.php'], true) ? 'active' : ''; ?>">My courses</a></li>
                <?php endif; ?>
                <li><a href="about.php" class="<?php echo $currentPage === 'about.php' ? 'active' : ''; ?>">About</a></li>
                <li><a href="contact.php" class="<?php echo $currentPage === 'contact.php' ? 'active' : ''; ?>">Contact</a></li>
            </ul>

            <div class="nav-actions">
                <?php if (!$isLoggedIn): ?>
                    <a href="login.php" class="btn btn-outline <?php echo $currentPage === 'login.php' ? 'active' : ''; ?>">Log in</a>
                    <a href="register.php" class="btn btn-primary <?php echo $currentPage === 'register.php' ? 'active' : ''; ?>">Create account</a>
                <?php else: ?>
                    <?php
                    $dashboard = match ($role) {
                        'student' => 'student-dashboard.php',
                        'instructor' => 'instructor-dashboard.php',
                        'admin' => 'admin-dashboard.php',
                        default => 'index.php',
                    };
                    ?>
                    <a href="<?php echo htmlspecialchars($dashboard, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline">Dashboard</a>
                    <button type="button" class="btn btn-primary" id="openLogoutModal">Log out</button>
                <?php endif; ?>
            </div>
        </div>
    </nav>
</header>