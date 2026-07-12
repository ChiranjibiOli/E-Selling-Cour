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
    <link rel="stylesheet" href="assets/css/base/reset.css?v=10">
    <link rel="stylesheet" href="assets/css/base/variables.css?v=10">
    <link rel="stylesheet" href="assets/css/base/typography.css?v=10">
    <link rel="stylesheet" href="assets/css/base/layout.css?v=10">
    <link rel="stylesheet" href="assets/css/components/buttons.css?v=10">
    <link rel="stylesheet" href="assets/css/components/forms.css?v=10">
    <link rel="stylesheet" href="assets/css/components/alerts.css?v=10">
    <link rel="stylesheet" href="assets/css/components/cards.css?v=10">
    <link rel="stylesheet" href="assets/css/components/modals.css?v=10">

    <?php if (Auth::check()): ?>
        <link rel="stylesheet" href="assets/css/panel.css?v=10">
        <link rel="stylesheet" href="assets/css/panel-modules.css?v=10">
        <?php if ($currentRole === 'student'): ?>
            <link rel="stylesheet" href="assets/css/navbars/student-navbar.css?v=10">
        <?php elseif ($currentRole === 'instructor'): ?>
            <link rel="stylesheet" href="assets/css/navbars/instructor-navbar.css?v=10">
        <?php elseif ($currentRole === 'admin'): ?>
            <link rel="stylesheet" href="assets/css/navbars/admin-navbar.css?v=10">
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
        .role-navbar {
            position: sticky !important;
            top: 10px !important;
            z-index: 99999 !important;
            width: min(1380px, calc(100% - 24px)) !important;
            margin: 10px auto 0 !important;
            border: 1px solid rgba(120,92,46,.16) !important;
            border-radius: 24px !important;
            background: rgba(255,252,246,.84) !important;
            box-shadow: 0 18px 46px rgba(31,25,18,.12) !important;
            backdrop-filter: blur(24px) saturate(145%) !important;
            -webkit-backdrop-filter: blur(24px) saturate(145%) !important;
        }
        .role-nav-container {
            min-height: 70px !important;
            padding: 8px 12px !important;
        }
        .role-nav-menu {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            overflow-x: auto !important;
            scrollbar-width: none !important;
        }
        .role-nav-menu::-webkit-scrollbar { display: none !important; }
        .role-nav-menu a,
        .role-logout-btn,
        .student-logout-item .confirm-logout-btn {
            min-height: 42px !important;
            padding: 0 14px !important;
            border-radius: 999px !important;
            white-space: nowrap !important;
        }
        .role-nav-menu a.active {
            background: #fff !important;
            color: #8a6324 !important;
            box-shadow: 0 8px 24px rgba(31,25,18,.12) !important;
        }

        @media (max-width: 760px) {
            body.authenticated-panel { padding-bottom: 104px !important; }
            .role-navbar {
                position: fixed !important;
                top: auto !important;
                bottom: 12px !important;
                left: 50% !important;
                width: calc(100% - 20px) !important;
                margin: 0 !important;
                transform: translateX(-50%) !important;
                border-radius: 30px !important;
            }
            .role-logo,
            .nav-toggle { display: none !important; }
            .role-nav-container {
                width: 100% !important;
                min-height: 72px !important;
                padding: 8px !important;
            }
            .role-nav-menu {
                position: static !important;
                display: flex !important;
                width: 100% !important;
                flex-direction: row !important;
                align-items: stretch !important;
                justify-content: flex-start !important;
                gap: 4px !important;
                padding: 0 !important;
                border: 0 !important;
                background: transparent !important;
                box-shadow: none !important;
            }
            .role-nav-menu li { flex: 0 0 auto !important; }
            .role-nav-menu a,
            .role-logout-btn,
            .student-logout-item .confirm-logout-btn {
                min-width: 72px !important;
                min-height: 56px !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 4px !important;
                padding: 6px 10px !important;
                border-radius: 20px !important;
                font-size: .68rem !important;
            }
            .role-nav-menu a::before,
            .role-logout-btn::before,
            .student-logout-item .confirm-logout-btn::before {
                content: '•';
                font-size: 1rem;
                line-height: 1;
            }
        }
        <?php endif; ?>
    </style>

    <script src="assets/js/main.js?v=10" defer></script>
    <script src="assets/js/auth.js?v=10" defer></script>
    <?php if (Auth::check()): ?>
        <script src="assets/js/panel.js?v=10" defer></script>
    <?php endif; ?>
</head>
<body id="main-content" class="<?php echo Auth::check() ? 'authenticated-panel' : 'public-site'; ?>">
<a class="skip-link" href="#main-content">Skip to main content</a>