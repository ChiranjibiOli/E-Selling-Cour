<?php

require_once __DIR__ . '/../../core/Auth.php';

Auth::start();

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
<link rel="stylesheet" href="assets/css/base/reset.css?v=5">
<link rel="stylesheet" href="assets/css/base/variables.css?v=5">
<link rel="stylesheet" href="assets/css/base/typography.css?v=5">
<link rel="stylesheet" href="assets/css/base/layout.css?v=5">

<link rel="stylesheet" href="assets/css/components/buttons.css?v=5">
<link rel="stylesheet" href="assets/css/components/forms.css?v=5">
<link rel="stylesheet" href="assets/css/components/alerts.css?v=5">
<link rel="stylesheet" href="assets/css/components/cards.css?v=5">
<link rel="stylesheet" href="assets/css/components/modals.css?v=5">
<?php if (Auth::check()): ?>
<link rel="stylesheet" href="assets/css/panel.css?v=5">
<link rel="stylesheet" href="assets/css/panel-modules.css?v=5">
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
        .skip-link:focus {
            transform: translateY(0) !important;
        }
    </style>

    <script src="assets/js/main.js?v=5" defer></script>
    <script src="assets/js/auth.js?v=5" defer></script>
<?php if (Auth::check()): ?>
    <script src="assets/js/panel.js?v=5" defer></script>
<?php endif; ?>
</head>
<body id="main-content" class="<?php echo Auth::check() ? 'authenticated-panel' : 'public-site'; ?>">
<a class="skip-link" href="#main-content">Skip to main content</a>