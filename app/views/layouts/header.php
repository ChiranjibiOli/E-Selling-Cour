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
    <meta name="theme-color" content="#181612">
    <meta name="description" content="Learn practical skills from verified instructors and manage your courses in one place.">
    <title><?php echo htmlspecialchars($documentTitle, ENT_QUOTES, 'UTF-8'); ?></title>

    <link rel="icon" href="assets/images/logo.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/css/base/reset.css?v=1">
    <link rel="stylesheet" href="assets/css/base/variables.css?v=1">
    <link rel="stylesheet" href="assets/css/base/typography.css?v=1">
    <link rel="stylesheet" href="assets/css/base/layout.css?v=1">

    <link rel="stylesheet" href="assets/css/components/buttons.css?v=1">
    <link rel="stylesheet" href="assets/css/components/forms.css?v=1">
    <link rel="stylesheet" href="assets/css/components/alerts.css?v=1">
    <link rel="stylesheet" href="assets/css/components/cards.css?v=1">
    <link rel="stylesheet" href="assets/css/components/modals.css?v=1">
<?php if (Auth::check()): ?>
    <link rel="stylesheet" href="assets/css/panel.css?v=2">
    <link rel="stylesheet" href="assets/css/panel-modules.css?v=2">
<?php endif; ?>
    <link rel="stylesheet" href="assets/css/theme-luxury.css?v=1">

    <script src="assets/js/main.js" defer></script>
    <script src="assets/js/auth.js" defer></script>
<?php if (Auth::check()): ?>
    <script src="assets/js/panel.js?v=2" defer></script>
<?php endif; ?>
    <script src="assets/js/ui-enhancements.js?v=1" defer></script>
</head>
<body id="main-content" class="<?php echo Auth::check() ? 'authenticated-panel' : 'public-site'; ?>">
<a class="skip-link" href="#main-content">Skip to main content</a>
