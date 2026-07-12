<?php

require_once __DIR__ . '/../../core/Auth.php';

Auth::start();
$currentUser = Auth::user();
$currentRole = $currentUser['role'] ?? '';

$documentTitle = isset($pageTitle) && trim((string) $pageTitle) !== ''
    ? trim((string) $pageTitle) . ' | ' . APP_NAME
    : APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f3eadc">
    <meta name="description" content="Learn practical skills from verified instructors and manage your courses in one place.">
    <title><?php echo htmlspecialchars($documentTitle, ENT_QUOTES, 'UTF-8'); ?></title>

    <link rel="icon" href="assets/images/logo.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/css/base/reset.css?v=12">
    <link rel="stylesheet" href="assets/css/base/variables.css?v=12">
    <link rel="stylesheet" href="assets/css/base/typography.css?v=12">
    <link rel="stylesheet" href="assets/css/base/layout.css?v=12">
    <link rel="stylesheet" href="assets/css/components/buttons.css?v=12">
    <link rel="stylesheet" href="assets/css/components/forms.css?v=12">
    <link rel="stylesheet" href="assets/css/components/alerts.css?v=12">
    <link rel="stylesheet" href="assets/css/components/cards.css?v=12">
    <link rel="stylesheet" href="assets/css/components/modals.css?v=12">

    <?php if (Auth::check()): ?>
        <link rel="stylesheet" href="assets/css/panel.css?v=12">
        <link rel="stylesheet" href="assets/css/panel-modules.css?v=12">
        <?php if ($currentRole === 'student'): ?>
            <link rel="stylesheet" href="assets/css/navbars/student-navbar.css?v=12">
        <?php elseif ($currentRole === 'instructor'): ?>
            <link rel="stylesheet" href="assets/css/navbars/instructor-navbar.css?v=12">
        <?php elseif ($currentRole === 'admin'): ?>
            <link rel="stylesheet" href="assets/css/navbars/admin-navbar.css?v=12">
        <?php endif; ?>
    <?php endif; ?>

    <style>
        .sr-only {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            padding: 0 !important;
            margin: -1px !important;
            overflow: hidden !important;
            clip: rect(0, 0, 0, 0) !important;
            clip-path: inset(50%) !important;
            white-space: nowrap !important;
            border: 0 !important;
        }
        .skip-link {
            position: fixed !important;
            top: 8px !important;
            left: 8px !important;
            z-index: 100000 !important;
            transform: translateY(-220%) !important;
        }
        .skip-link:focus { transform: translateY(0) !important; }

        <?php if (Auth::check()): ?>
        body.authenticated-panel {
            padding-top: 96px !important;
        }

        .authenticated-panel .role-navbar {
            position: fixed !important;
            top: 14px !important;
            left: 50% !important;
            z-index: 99999 !important;
            width: min(1420px, calc(100% - 28px)) !important;
            height: 70px !important;
            margin: 0 !important;
            transform: translateX(-50%) !important;
            border: 1px solid rgba(118, 87, 35, .18) !important;
            border-radius: 26px !important;
            background:
                linear-gradient(135deg, rgba(255, 253, 248, .96), rgba(239, 226, 203, .90)) !important;
            box-shadow:
                0 18px 48px rgba(35, 27, 18, .14),
                inset 0 1px 0 rgba(255, 255, 255, .92) !important;
            backdrop-filter: blur(24px) saturate(150%) !important;
            -webkit-backdrop-filter: blur(24px) saturate(150%) !important;
        }

        .authenticated-panel .role-nav-container {
            width: 100% !important;
            height: 100% !important;
            min-height: 70px !important;
            display: flex !important;
            align-items: center !important;
            gap: 16px !important;
            padding: 8px 12px 8px 16px !important;
        }

        .authenticated-panel .role-logo {
            flex: 0 0 auto !important;
            display: inline-flex !important;
            align-items: center !important;
            min-width: max-content !important;
            color: #171511 !important;
            font-family: var(--font-display) !important;
            font-size: .94rem !important;
            font-weight: 800 !important;
            text-decoration: none !important;
            letter-spacing: -.01em !important;
        }

        .authenticated-panel .role-logo::before {
            width: 38px !important;
            height: 38px !important;
            margin-right: 10px !important;
            display: grid !important;
            place-items: center !important;
            border-radius: 50% !important;
            background: #171511 !important;
            color: #d7a54d !important;
            box-shadow: 0 9px 20px rgba(23, 21, 17, .22) !important;
            font-family: var(--font-display) !important;
            font-size: .94rem !important;
            font-weight: 900 !important;
        }

        .authenticated-panel .student-logo::before { content: 'S' !important; }
        .authenticated-panel .instructor-logo::before { content: 'I' !important; }
        .authenticated-panel .admin-logo::before { content: 'A' !important; }

        .authenticated-panel .role-nav-menu {
            flex: 1 1 auto !important;
            min-width: 0 !important;
            height: 52px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            gap: 5px !important;
            margin: 0 !important;
            padding: 6px !important;
            overflow-x: auto !important;
            overflow-y: hidden !important;
            border: 1px solid rgba(118, 87, 35, .12) !important;
            border-radius: 999px !important;
            background: rgba(255, 255, 255, .52) !important;
            list-style: none !important;
            scrollbar-width: none !important;
        }

        .authenticated-panel .role-nav-menu::-webkit-scrollbar { display: none !important; }
        .authenticated-panel .role-nav-menu li,
        .authenticated-panel .role-nav-menu form { flex: 0 0 auto !important; margin: 0 !important; }
        .authenticated-panel .role-nav-menu li:last-child { margin-left: auto !important; }

        .authenticated-panel .role-nav-menu a,
        .authenticated-panel .role-logout-btn,
        .authenticated-panel .student-logout-item .confirm-logout-btn {
            min-height: 40px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 7px !important;
            padding: 0 14px !important;
            border: 0 !important;
            border-radius: 999px !important;
            color: #5f574e !important;
            background: transparent !important;
            box-shadow: none !important;
            text-decoration: none !important;
            font-size: .75rem !important;
            font-weight: 850 !important;
            white-space: nowrap !important;
            cursor: pointer !important;
            transition: transform .18s ease, background .18s ease, color .18s ease, box-shadow .18s ease !important;
        }

        .authenticated-panel .role-nav-menu a::before,
        .authenticated-panel .role-logout-btn::before,
        .authenticated-panel .student-logout-item .confirm-logout-btn::before {
            content: attr(data-icon) !important;
            display: inline-block !important;
            color: #a9792e !important;
            font-size: .93rem !important;
            line-height: 1 !important;
        }

        .authenticated-panel .role-nav-menu a:hover,
        .authenticated-panel .role-nav-menu a.active {
            transform: translateY(-1px) !important;
            background: #fff !important;
            color: #8a6324 !important;
            box-shadow: 0 8px 22px rgba(35, 27, 18, .10) !important;
        }

        .authenticated-panel .role-nav-menu a.active::before { color: #8a6324 !important; }

        .authenticated-panel .role-logout-btn,
        .authenticated-panel .student-logout-item .confirm-logout-btn {
            color: #a7443d !important;
            background: rgba(167, 68, 61, .08) !important;
        }

        .authenticated-panel .role-logout-btn::before,
        .authenticated-panel .student-logout-item .confirm-logout-btn::before {
            color: #a7443d !important;
        }

        .authenticated-panel .nav-toggle { display: none !important; }

        @media (max-width: 980px) and (min-width: 761px) {
            .authenticated-panel .role-logo { font-size: 0 !important; }
            .authenticated-panel .role-logo::before { margin-right: 0 !important; }
            .authenticated-panel .role-nav-menu a,
            .authenticated-panel .role-logout-btn,
            .authenticated-panel .student-logout-item .confirm-logout-btn {
                padding: 0 12px !important;
                font-size: .71rem !important;
            }
        }

        @media (max-width: 760px) {
            body.authenticated-panel {
                padding-top: 0 !important;
                padding-bottom: 104px !important;
            }

            .authenticated-panel .role-navbar {
                top: auto !important;
                bottom: 10px !important;
                width: calc(100% - 16px) !important;
                height: 78px !important;
                border-radius: 30px !important;
                background: rgba(255, 252, 246, .96) !important;
                box-shadow: 0 18px 48px rgba(24, 18, 12, .24), inset 0 1px rgba(255,255,255,.92) !important;
            }

            .authenticated-panel .role-logo,
            .authenticated-panel .nav-toggle { display: none !important; }

            .authenticated-panel .role-nav-container {
                min-height: 78px !important;
                padding: 8px 9px !important;
            }

            .authenticated-panel .role-nav-menu {
                width: 100% !important;
                height: 62px !important;
                display: flex !important;
                align-items: stretch !important;
                justify-content: flex-start !important;
                gap: 4px !important;
                padding: 0 !important;
                border: 0 !important;
                background: transparent !important;
            }

            .authenticated-panel .role-nav-menu li,
            .authenticated-panel .role-nav-menu li:last-child {
                min-width: 74px !important;
                height: 100% !important;
                margin-left: 0 !important;
            }

            .authenticated-panel .role-nav-menu form { width: 100% !important; height: 100% !important; }

            .authenticated-panel .role-nav-menu a,
            .authenticated-panel .role-logout-btn,
            .authenticated-panel .student-logout-item .confirm-logout-btn {
                width: 100% !important;
                height: 100% !important;
                min-height: 0 !important;
                flex-direction: column !important;
                gap: 5px !important;
                padding: 7px 7px !important;
                border-radius: 20px !important;
                font-size: .62rem !important;
                line-height: 1.05 !important;
            }

            .authenticated-panel .role-nav-menu a::before,
            .authenticated-panel .role-logout-btn::before,
            .authenticated-panel .student-logout-item .confirm-logout-btn::before {
                font-size: 1.02rem !important;
            }

            .authenticated-panel .role-nav-menu a.active {
                transform: none !important;
                background: #171511 !important;
                color: #fff4df !important;
                box-shadow: none !important;
            }

            .authenticated-panel .role-nav-menu a.active::before { color: #d7a54d !important; }
            .authenticated-panel .role-logout-btn,
            .authenticated-panel .student-logout-item .confirm-logout-btn {
                background: transparent !important;
                color: #a7443d !important;
            }
        }
        <?php endif; ?>
    </style>

    <script src="assets/js/main.js?v=12" defer></script>
    <script src="assets/js/auth.js?v=12" defer></script>
    <?php if (Auth::check()): ?>
        <script src="assets/js/panel.js?v=12" defer></script>
    <?php endif; ?>
</head>
<body id="main-content" class="<?php echo Auth::check() ? 'authenticated-panel' : 'public-site'; ?>">
<a class="skip-link" href="#main-content">Skip to main content</a>