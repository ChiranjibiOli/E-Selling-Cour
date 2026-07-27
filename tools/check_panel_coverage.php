<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$rooms = require $root . '/apps/web-platform/src/config/rooms.php';
$expected = ['Student' => 14, 'Instructor' => 20, 'Admin' => 21];
$counts = array_fill_keys(array_keys($expected), 0);
$authenticated = 0;
$errors = [];

foreach ($rooms as $key => $metadata) {
    [$floor, $room] = explode('/', $key, 2);
    if (!isset($counts[$floor])) {
        continue;
    }
    $counts[$floor]++;
    if (in_array((string) ($metadata['role'] ?? ''), ['student', 'instructor', 'admin'], true)) {
        $authenticated++;
    }
    $directory = $root . '/apps/web-platform/src/' . $floor . '/' . $room;
    if (!is_file($directory . '/Controller.php') && !isset($metadata['controller_file'])) {
        $errors[] = 'Panel controller missing: ' . $key;
    }
    if ($floor === 'Admin' && $room !== 'Login' && (string) ($metadata['status'] ?? '') !== 'implemented') {
        $errors[] = 'Admin navigation room must be implemented: ' . $key;
    }
}

foreach ($expected as $floor => $count) {
    if ($counts[$floor] !== $count) {
        $errors[] = sprintf('%s panel count changed: expected %d, found %d.', $floor, $count, $counts[$floor]);
    }
}
if ($authenticated !== 50) {
    $errors[] = 'Authenticated panel count must remain 50; found ' . $authenticated . '.';
}

$requiredFiles = [
    'apps/web-platform/src/Shared/Http/Request.php',
    'apps/web-platform/src/Shared/Security/FormInput.php',
    'apps/web-platform/src/Shared/Ui/PortalPage.php',
    'apps/web-platform/src/Shared/Ui/AccountProfilePage.php',
    'apps/web-platform/src/Shared/Profile/AccountProfileManager.php',
    'apps/web-platform/src/Shared/Session/AuthSession.php',
    'apps/web-platform/src/Shared/Session/SessionGuard.php',
    'apps/web-platform/src/Instructor/CreateCourse/Controller.php',
    'apps/web-platform/src/Instructor/CreateCourse/Page.php',
    'apps/web-platform/src/Instructor/Profile/Controller.php',
    'apps/web-platform/src/Instructor/Profile/Page.php',
    'apps/web-platform/src/Admin/Profile/Controller.php',
    'apps/web-platform/src/Student/Profile/Controller.php',
    'apps/web-platform/public/assets/css/admin-console.css',
    'apps/web-platform/public/assets/css/profile-links.css',
    'apps/web-platform/public/assets/css/profile-dialog.css',
    'apps/web-platform/public/assets/css/instructor-console.css',
    'apps/web-platform/public/assets/js/app.js',
    'services/identity-service/public/account-profile.php',
    'services/identity-service/public/instructor-profile.php',
    'services/identity-service/public/router.php',
    'services/identity-service/src/Features/Login/LoginHandler.php',
    'services/identity-service/src/Features/Session/SessionHandler.php',
    'services/reporting-service/public/admin-console.php',
    'services/reporting-service/public/router.php',
    'tools/check_form_security.php',
];
foreach ($requiredFiles as $file) {
    if (!is_file($root . '/' . $file) || filesize($root . '/' . $file) < 100) {
        $errors[] = 'Shared panel asset is missing or empty: ' . $file;
    }
}

$contracts = [
    'apps/web-platform/src/config/rooms.php' => [
        "'Admin/Profile' => ['title'=>'Profile','path'=>'/admin/profile','methods'=>'GET|POST'",
        "'Student/Profile' => ['title'=>'Profile','path'=>'/student/profile','methods'=>'GET|POST'",
        "'Instructor/Profile' => ['title'=>'Profile','path'=>'/instructor/profile','methods'=>'GET|POST'",
    ],
    'apps/web-platform/src/Shared/Http/Request.php' => [
        'MAX_BODY_FIELDS',
        'MAX_DEPTH',
        'validatePayload',
        'null byte',
        'invalid text encoding',
    ],
    'apps/web-platform/src/Shared/Security/FormInput.php' => [
        'function text(',
        'function multiline(',
        'function integer(',
        'function decimal(',
        'function httpsUrl(',
        'function listText(',
        'unsupported control characters',
    ],
    'apps/web-platform/src/Shared/Ui/PortalPage.php' => [
        'data-portal-nav',
        'data-logout-dialog',
        'Yes, log out',
        '$role === \'student\'',
        'portal-crumb-simple',
        "'admin' => '/admin/profile'",
        "'instructor' => '/instructor/profile'",
        "default => '/student/profile'",
        'profile-dialog.css',
        'instructor-console.css',
    ],
    'apps/web-platform/src/Shared/Ui/AccountProfilePage.php' => [
        'View photo',
        'Change photo',
        'Remove photo',
        'data-photo-dialog',
        'data-profile-photo-remove',
        'data-photo-remove-dialog',
        'Csrf::field()',
    ],
    'apps/web-platform/src/Instructor/Profile/Page.php' => [
        'View photo',
        'Remove photo',
        'data-photo-dialog',
        'data-photo-zoom-in',
        'data-photo-remove-dialog',
        'name="action" value="save_profile"',
        'type="tel"',
        'type="url"',
        '25-day',
    ],
    'apps/web-platform/src/Instructor/Profile/Controller.php' => [
        'FormInput::text',
        'FormInput::multiline',
        'FormInput::httpsUrl',
        "'remove_photo'",
        'SecureUpload::store',
        'SecureUpload::delete',
        'AuthSession::synchronizeUser',
    ],
    'services/identity-service/public/instructor-profile.php' => [
        "['save_profile', 'remove_photo']",
        'profile_image=NULL',
        'profile_image_changed_at=NULL',
        'FOR UPDATE',
        'old_profile_image',
        'normal HTTPS address',
    ],
    'apps/web-platform/src/Instructor/CreateCourse/Page.php' => [
        'data-course-authoring',
        'course-live-card',
        'data-preview-title',
        'data-preview-description',
        'data-preview-price',
        'type="number"',
        'type="url"',
        'type="file"',
        'Csrf::field()',
    ],
    'apps/web-platform/src/Instructor/CreateCourse/Controller.php' => [
        'FormInput::decimal',
        'FormInput::integer',
        'FormInput::httpsUrl',
        'media/course-thumbnails',
        'getimagesize',
        'SecureUpload::delete',
    ],
    'apps/web-platform/public/assets/js/app.js' => [
        'data-photo-open',
        'data-photo-zoom-in',
        'data-photo-remove-confirm',
        'data-course-authoring',
        'updateCoursePreview',
        'URL.createObjectURL',
        "input[type=\"number\"]",
        "fetch('/session-status'",
        'window.setInterval(checkSession, 12000)',
    ],
    'apps/web-platform/public/assets/css/profile-dialog.css' => [
        '.profile-photo-dialog',
        '.profile-photo-stage',
        '.instructor-profile-surface',
        '.instructor-profile-hero',
    ],
    'apps/web-platform/public/assets/css/instructor-console.css' => [
        '.course-authoring-layout',
        '.course-authoring-surface',
        '.course-live-preview',
        '.course-live-card',
        'overflow-wrap: anywhere',
    ],
    'services/identity-service/public/router.php' => [
        "'/api/v1/users/account-profile'",
        "'/api/v1/users/instructor-profile'",
        "require __DIR__ . '/instructor-profile.php'",
    ],
    'services/identity-service/src/Features/Login/LoginHandler.php' => [
        'profile_image FROM users',
        "'profile_image' =>",
    ],
    'services/identity-service/src/Features/Session/SessionHandler.php' => [
        'u.profile_image',
        "'profile_image' =>",
    ],
    'services/reporting-service/public/admin-console.php' => [
        'ServiceAuth::requireUser($database, $authorization, \'admin\')',
        'UPDATE identity_sessions SET revoked_at',
        'ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
    ],
    'tools/check_form_security.php' => [
        'FORM SECURITY CHECK',
        'POST form has no visible CSRF token contract',
        'Semantic input warnings',
    ],
];
foreach ($contracts as $file => $needles) {
    $content = is_file($root . '/' . $file) ? (string) file_get_contents($root . '/' . $file) : '';
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $errors[] = 'Missing panel contract in ' . $file . ': ' . $needle;
        }
    }
}

$portal = (string) file_get_contents($root . '/apps/web-platform/src/Shared/Ui/PortalPage.php');
if (str_contains($portal, "'admin' => 'Platform control'")) {
    $errors[] = 'The removed Admin Platform control workspace label returned.';
}
if (str_contains($portal, '<div class="portal-workspace"><span>Instructor')) {
    $errors[] = 'The removed Instructor workspace card returned.';
}

$accountProfile = (string) file_get_contents($root . '/apps/web-platform/src/Shared/Ui/AccountProfilePage.php');
$instructorProfile = (string) file_get_contents($root . '/apps/web-platform/src/Instructor/Profile/Page.php');
if (str_contains($accountProfile, 'target="_blank"') || str_contains($instructorProfile, 'target="_blank"')) {
    $errors[] = 'Profile photo viewing must stay in the same page dialog.';
}

if ($errors !== []) {
    echo "PANEL COVERAGE CHECK: FAIL\n";
    foreach ($errors as $error) {
        echo '- ' . $error . PHP_EOL;
    }
    exit(1);
}

echo "PANEL COVERAGE CHECK: PASS\n";
echo "Panel routes: 55\n";
echo "Authenticated panels: 50\n";
echo "Instructor chrome: simplified\n";
echo "Course authoring: live public-card preview enabled\n";
echo "Profile photos: in-page view, zoom, change and removal enabled\n";
echo "Forms: central request hardening and typed validation enabled\n";
echo "Revoked sessions: automatic portal logout enabled\n";
