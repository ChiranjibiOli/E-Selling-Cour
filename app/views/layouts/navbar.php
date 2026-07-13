<?php

require_once __DIR__ . '/../../core/Auth.php';

$currentUser = Auth::user();
$currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$isLoggedIn = $currentUser !== null;
$role = $currentUser['role'] ?? '';
$brandName = trim((string) APP_NAME);
$brandHasHubSuffix = str_ends_with($brandName, 'Hub');
$brandMain = $brandHasHubSuffix ? substr($brandName, 0, -3) : $brandName;
$brandAccent = $brandHasHubSuffix ? 'Hub' : '';
?>

<link rel="stylesheet" href="assets/css/navbars/public-navbar.css?v=17">
<link rel="stylesheet" href="assets/css/pages/public/public-courses.css?v=15">

<style>
    .logo.coursehub-mascot-logo {
        gap: 8px;
        overflow: visible;
        color: #1d1735;
    }

    .coursehub-mascot {
        width: 42px;
        height: 42px;
        display: block;
        flex: 0 0 42px;
        overflow: visible;
        filter: drop-shadow(0 5px 8px rgba(31, 23, 58, .18));
        transform-origin: 50% 68%;
        transition: transform .22s cubic-bezier(.22, 1, .36, 1), filter .22s ease;
    }

    .coursehub-mascot-logo:hover .coursehub-mascot {
        transform: translateY(-2px) rotate(-5deg) scale(1.03);
        filter: drop-shadow(0 8px 11px rgba(31, 23, 58, .24));
    }

    .coursehub-antenna {
        transform-origin: 42px 15px;
        animation: coursehubAntenna 3.6s ease-in-out infinite;
    }

    .coursehub-eye {
        transform-box: fill-box;
        transform-origin: center;
        animation: coursehubBlink 5.2s steps(1, end) infinite;
    }

    .coursehub-wordmark {
        position: relative;
        display: inline-flex;
        align-items: baseline;
        white-space: nowrap;
        font-family: "Arial Rounded MT Bold", "Trebuchet MS", Arial, sans-serif;
        font-size: 18px;
        font-style: italic;
        font-weight: 900;
        line-height: 1;
        letter-spacing: -.075em;
        transform: skewX(-4deg);
    }

    .coursehub-wordmark-main {
        color: #1d1735;
    }

    .coursehub-wordmark-hub {
        position: relative;
        margin-left: 2px;
        color: #6e46db;
    }

    .coursehub-wordmark-hub::after {
        content: "";
        position: absolute;
        left: 5px;
        right: -1px;
        bottom: -5px;
        height: 3px;
        border-radius: 999px;
        background: linear-gradient(90deg, #b88939, #efbe55);
        transform: scaleX(.58);
        transform-origin: left;
        transition: transform .22s ease;
    }

    .coursehub-mascot-logo:hover .coursehub-wordmark-hub::after {
        transform: scaleX(1);
    }

    @keyframes coursehubAntenna {
        0%, 74%, 100% { transform: rotate(0deg); }
        80% { transform: rotate(8deg); }
        87% { transform: rotate(-6deg); }
        93% { transform: rotate(3deg); }
    }

    @keyframes coursehubBlink {
        0%, 43%, 47%, 100% { transform: scaleY(1); }
        45% { transform: scaleY(.14); }
    }

    @media (max-width: 900px) {
        .coursehub-mascot {
            width: 36px;
            height: 36px;
            flex-basis: 36px;
        }

        .coursehub-wordmark {
            font-size: 16px;
        }
    }

    @media (max-width: 430px) {
        .coursehub-wordmark {
            max-width: 112px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .coursehub-antenna,
        .coursehub-eye {
            animation: none;
        }

        .coursehub-mascot,
        .coursehub-wordmark-hub::after {
            transition: none;
        }
    }
</style>

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
        <a class="logo coursehub-mascot-logo" href="index.php" aria-label="<?php echo htmlspecialchars(APP_NAME); ?> home">
            <svg class="coursehub-mascot" viewBox="0 0 64 64" aria-hidden="true" focusable="false">
                <g class="coursehub-antenna">
                    <path d="M39.5 16.5C40.4 10.6 44.1 7.1 49.4 6.4" fill="none" stroke="#1d1735" stroke-width="3.2" stroke-linecap="round"/>
                    <circle cx="51.3" cy="6.1" r="4.6" fill="#b88939"/>
                </g>
                <path d="M10.6 31.4C10.6 19.4 18.2 12.9 31.9 12.9C45.9 12.9 53.7 19.4 53.7 31.4C53.7 43.3 45.8 49.8 31.9 49.8C18.1 49.8 10.6 43.3 10.6 31.4Z" fill="#1d1735"/>
                <path d="M17.2 25.3C22.5 21.8 27.4 22 31.9 25.4C36.5 22 41.6 21.8 47 25.3L45.6 38C40.4 36.2 35.9 36.9 31.9 40.4C27.9 36.9 23.4 36.2 18.4 38L17.2 25.3Z" fill="#fffaf0"/>
                <path d="M31.9 25.4V40.4" stroke="#d9c99e" stroke-width="1.7" stroke-linecap="round"/>
                <ellipse class="coursehub-eye" cx="25.1" cy="31.4" rx="2.8" ry="4.4" fill="#1d1735"/>
                <ellipse class="coursehub-eye" cx="38.9" cy="31.4" rx="2.8" ry="4.4" fill="#1d1735"/>
                <path d="M23.6 49C26.1 52.1 28.8 53.6 31.9 53.6C35.1 53.6 37.9 52.1 40.4 49" fill="none" stroke="#b88939" stroke-width="3.2" stroke-linecap="round"/>
            </svg>
            <span class="coursehub-wordmark" aria-hidden="true">
                <span class="coursehub-wordmark-main"><?php echo htmlspecialchars($brandMain, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php if ($brandAccent !== ''): ?>
                    <span class="coursehub-wordmark-hub"><?php echo htmlspecialchars($brandAccent, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </span>
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