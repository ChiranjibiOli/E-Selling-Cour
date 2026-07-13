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

<link rel="stylesheet" href="assets/css/navbars/public-navbar.css?v=18">
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
        transform: scale(var(--nav-logo-scale, 1));
        transform-origin: 50% 68%;
        transition: filter .22s ease;
        will-change: transform;
    }

    .coursehub-mascot-logo:hover .coursehub-mascot {
        transform: translateY(-2px) rotate(-5deg) scale(var(--nav-logo-hover-scale, 1.03));
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
        font-size: var(--nav-wordmark-size, 18px);
        font-style: italic;
        font-weight: 900;
        line-height: 1;
        letter-spacing: -.075em;
        transform: skewX(-4deg);
        will-change: font-size;
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

    /*
     * Scroll interpolation values are updated by the spring animation below.
     * Both compression and release use the same motion curve, avoiding the
     * abrupt class switch that previously made the navbar snap.
     */
    .site-header,
    .site-header.is-scrolled {
        top: var(--nav-top, 12px);
        height: var(--nav-height, 72px);
        transition: none;
        will-change: top, height;
    }

    .site-header .navbar,
    .site-header.is-scrolled .navbar {
        max-width: var(--nav-max-width, 1360px);
        height: var(--nav-height, 72px);
        padding-right: var(--nav-padding-inline, 18px);
        padding-left: var(--nav-padding-inline, 18px);
        border-radius: var(--nav-radius, 22px);
        transform: translateZ(0);
        transition:
            border-color .48s cubic-bezier(.22, 1, .36, 1),
            background .48s cubic-bezier(.22, 1, .36, 1),
            box-shadow .48s cubic-bezier(.22, 1, .36, 1),
            backdrop-filter .48s cubic-bezier(.22, 1, .36, 1),
            -webkit-backdrop-filter .48s cubic-bezier(.22, 1, .36, 1);
        will-change: max-width, height, padding, border-radius;
    }

    .site-header .nav-links,
    .site-header.is-scrolled .nav-links {
        gap: var(--nav-link-gap, 8px);
    }

    .site-header .nav-links a,
    .site-header.is-scrolled .nav-links a {
        min-height: var(--nav-link-height, 42px);
        padding: var(--nav-link-pad-top, 8px) var(--nav-link-pad-x, 14px) var(--nav-link-pad-bottom, 9px);
        font-size: var(--nav-link-font-size, 14.1px);
    }

    .site-header .nav-actions,
    .site-header.is-scrolled .nav-actions {
        gap: var(--nav-action-gap, 10px);
    }

    .site-header .nav-actions .btn,
    .site-header.is-scrolled .nav-actions .btn {
        min-height: var(--nav-button-height, 42px);
        padding: var(--nav-button-pad-y, 8px) var(--nav-button-pad-x, 18px);
        font-size: var(--nav-button-font-size, 14.4px);
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
        .site-header,
        .site-header.is-scrolled {
            top: 8px;
            height: 62px;
        }

        .site-header .navbar,
        .site-header.is-scrolled .navbar {
            width: calc(100% - 16px);
            max-width: none;
            height: 62px;
            padding: 0 11px 0 13px;
            border-radius: 18px;
        }

        .site-header .nav-links,
        .site-header.is-scrolled .nav-links {
            gap: 6px;
        }

        .site-header .nav-links a,
        .site-header.is-scrolled .nav-links a {
            min-height: 42px;
            padding: 0 13px;
            font-size: 13.8px;
        }

        .site-header .nav-actions,
        .site-header.is-scrolled .nav-actions {
            gap: 8px;
        }

        .site-header .nav-actions .btn,
        .site-header.is-scrolled .nav-actions .btn {
            min-height: 40px;
            padding-inline: 10px;
            font-size: 13.1px;
        }

        .coursehub-mascot {
            width: 36px;
            height: 36px;
            flex-basis: 36px;
            transform: none;
        }

        .coursehub-mascot-logo:hover .coursehub-mascot {
            transform: translateY(-2px) rotate(-5deg) scale(1.03);
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

        .site-header .navbar,
        .site-header.is-scrolled .navbar {
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

<header class="site-header" id="publicSiteHeader">
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

<script>
(function () {
    const header = document.getElementById('publicSiteHeader');
    if (!header) {
        return;
    }

    const desktopQuery = window.matchMedia('(min-width: 901px)');
    const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    const compactDistance = 120;
    const values = {
        top: [12, 8],
        height: [72, 64],
        maxWidth: [1360, 1120],
        paddingInline: [18, 14],
        radius: [22, 18],
        linkGap: [8, 5],
        linkHeight: [42, 38],
        linkPadTop: [8, 6],
        linkPadX: [14, 11],
        linkPadBottom: [9, 7],
        linkFont: [14.1, 13],
        actionGap: [10, 7],
        buttonHeight: [42, 38],
        buttonPadY: [8, 6],
        buttonPadX: [18, 14],
        buttonFont: [14.4, 13],
        logoScale: [1, .9],
        logoHoverScale: [1.03, .93],
        wordmarkSize: [18, 17]
    };

    let targetProgress = 0;
    let currentProgress = 0;
    let velocity = 0;
    let animationFrame = 0;
    let previousTime = performance.now();

    function clamp(value, minimum, maximum) {
        return Math.min(maximum, Math.max(minimum, value));
    }

    function interpolate(range, progress) {
        return range[0] + ((range[1] - range[0]) * progress);
    }

    function setPixelProperty(name, range, progress, precision) {
        header.style.setProperty(name, `${interpolate(range, progress).toFixed(precision ?? 3)}px`);
    }

    function applyProgress(progress) {
        const safeProgress = clamp(progress, 0, 1);

        setPixelProperty('--nav-top', values.top, safeProgress);
        setPixelProperty('--nav-height', values.height, safeProgress);
        setPixelProperty('--nav-max-width', values.maxWidth, safeProgress);
        setPixelProperty('--nav-padding-inline', values.paddingInline, safeProgress);
        setPixelProperty('--nav-radius', values.radius, safeProgress);
        setPixelProperty('--nav-link-gap', values.linkGap, safeProgress);
        setPixelProperty('--nav-link-height', values.linkHeight, safeProgress);
        setPixelProperty('--nav-link-pad-top', values.linkPadTop, safeProgress);
        setPixelProperty('--nav-link-pad-x', values.linkPadX, safeProgress);
        setPixelProperty('--nav-link-pad-bottom', values.linkPadBottom, safeProgress);
        setPixelProperty('--nav-link-font-size', values.linkFont, safeProgress, 2);
        setPixelProperty('--nav-action-gap', values.actionGap, safeProgress);
        setPixelProperty('--nav-button-height', values.buttonHeight, safeProgress);
        setPixelProperty('--nav-button-pad-y', values.buttonPadY, safeProgress);
        setPixelProperty('--nav-button-pad-x', values.buttonPadX, safeProgress);
        setPixelProperty('--nav-button-font-size', values.buttonFont, safeProgress, 2);
        setPixelProperty('--nav-wordmark-size', values.wordmarkSize, safeProgress, 2);
        header.style.setProperty('--nav-logo-scale', interpolate(values.logoScale, safeProgress).toFixed(4));
        header.style.setProperty('--nav-logo-hover-scale', interpolate(values.logoHoverScale, safeProgress).toFixed(4));

        if (safeProgress >= .62) {
            header.classList.add('is-scrolled');
        } else if (safeProgress <= .38) {
            header.classList.remove('is-scrolled');
        }
    }

    function readTargetProgress() {
        if (!desktopQuery.matches) {
            return 0;
        }

        return clamp(window.scrollY / compactDistance, 0, 1);
    }

    function stopAnimation() {
        if (animationFrame) {
            window.cancelAnimationFrame(animationFrame);
            animationFrame = 0;
        }
    }

    function animate(now) {
        const deltaTime = Math.min((now - previousTime) / 1000, .034);
        previousTime = now;

        const stiffness = 175;
        const damping = 27;
        const displacement = targetProgress - currentProgress;

        velocity += displacement * stiffness * deltaTime;
        velocity *= Math.exp(-damping * deltaTime);
        currentProgress += velocity * deltaTime;
        currentProgress = clamp(currentProgress, -.03, 1.03);

        applyProgress(currentProgress);

        const settled = Math.abs(targetProgress - currentProgress) < .0007 && Math.abs(velocity) < .0007;
        if (settled) {
            currentProgress = targetProgress;
            velocity = 0;
            applyProgress(currentProgress);
            animationFrame = 0;
            return;
        }

        animationFrame = window.requestAnimationFrame(animate);
    }

    function startAnimation() {
        targetProgress = readTargetProgress();

        if (reducedMotionQuery.matches || !desktopQuery.matches) {
            stopAnimation();
            currentProgress = targetProgress;
            velocity = 0;
            applyProgress(currentProgress);
            return;
        }

        if (!animationFrame) {
            previousTime = performance.now();
            animationFrame = window.requestAnimationFrame(animate);
        }
    }

    function initialize() {
        targetProgress = readTargetProgress();
        currentProgress = targetProgress;
        applyProgress(currentProgress);
    }

    initialize();
    window.addEventListener('scroll', startAnimation, { passive: true });
    window.addEventListener('resize', startAnimation, { passive: true });
    desktopQuery.addEventListener('change', startAnimation);
    reducedMotionQuery.addEventListener('change', startAnimation);
})();
</script>
