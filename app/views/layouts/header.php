<?php

require_once __DIR__ . '/../../core/Auth.php';

Auth::start();
$currentUser = Auth::user();
$currentRole = $currentUser['role'] ?? '';
$currentScript = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$publicLayoutScripts = ['index.php', 'about.php', 'contact.php', 'how-it-works.php'];
$usesPanelLayout = Auth::check() && !in_array($currentScript, $publicLayoutScripts, true);

$documentTitle = isset($pageTitle) && trim((string) $pageTitle) !== ''
    ? trim((string) $pageTitle) . ' | ' . APP_NAME
    : APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#eee6d9">
    <meta name="description" content="Learn practical skills from verified instructors and manage your courses in one place.">
    <title><?php echo htmlspecialchars($documentTitle, ENT_QUOTES, 'UTF-8'); ?></title>

    <link rel="icon" href="assets/images/logo.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/css/base/reset.css?v=12">
    <link rel="stylesheet" href="assets/css/base/variables.css?v=12">
    <link rel="stylesheet" href="assets/css/base/typography.css?v=12">
    <link rel="stylesheet" href="assets/css/base/layout.css?v=12">
    <link rel="stylesheet" href="assets/css/base/page-gutters.css?v=3">
    <link rel="stylesheet" href="assets/css/components/buttons.css?v=12">
    <link rel="stylesheet" href="assets/css/components/forms.css?v=12">
    <link rel="stylesheet" href="assets/css/components/alerts.css?v=12">
    <link rel="stylesheet" href="assets/css/components/cards.css?v=12">
    <link rel="stylesheet" href="assets/css/components/modals.css?v=12">

    <?php if ($usesPanelLayout): ?>
        <link rel="stylesheet" href="assets/css/panel.css?v=12">
        <link rel="stylesheet" href="assets/css/panel-modules.css?v=12">

        <?php if ($currentRole === 'student'): ?>
            <link rel="stylesheet" href="assets/css/navbars/student-navbar.css?v=12">
        <?php elseif ($currentRole === 'instructor'): ?>
            <link rel="stylesheet" href="assets/css/navbars/instructor-navbar.css?v=12">
        <?php elseif ($currentRole === 'admin'): ?>
            <link rel="stylesheet" href="assets/css/navbars/admin-navbar.css?v=12">
        <?php endif; ?>

        <link rel="stylesheet" href="assets/css/panel-editorial.css?v=4" data-panel-style="panel-editorial">
        <link rel="stylesheet" href="assets/css/panel-navigation.css?v=3" data-panel-style="panel-navigation">
        <link rel="stylesheet" href="assets/css/panel-sections.css?v=3" data-panel-style="panel-sections">
        <link rel="stylesheet" href="assets/css/panel-final.css?v=2" data-panel-style="panel-final">
    <?php endif; ?>

    <link rel="stylesheet" href="assets/css/components/course-card-theme.css?v=3">
    <link rel="stylesheet" href="assets/css/components/course-card-uniform.css?v=3">
    <link rel="stylesheet" href="assets/css/components/course-card-dimensions.css?v=2">
    <link rel="stylesheet" href="assets/css/components/course-reviews.css?v=1">
    <link rel="stylesheet" href="assets/css/components/purchase-flow.css?v=1">

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
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            padding: 0 14px;
            border-radius: 999px;
            color: #fffaf0;
            background: #171511;
            transform: translateY(-220%) !important;
        }

        .skip-link:focus {
            transform: translateY(0) !important;
        }
    </style>

    <script src="assets/js/main.js?v=12" defer></script>
    <script src="assets/js/auth.js?v=12" defer></script>
    <script src="assets/js/course-reviews.js?v=1" defer></script>
    <script src="assets/js/purchase-flow.js?v=2" defer></script>
    <?php if ($usesPanelLayout): ?>
        <script src="assets/js/panel.js?v=15" defer></script>
    <?php endif; ?>
</head>
<body id="main-content" class="<?php echo $usesPanelLayout ? 'authenticated-panel' : 'public-site'; ?>">
<a class="skip-link" href="#main-content">Skip to main content</a>
