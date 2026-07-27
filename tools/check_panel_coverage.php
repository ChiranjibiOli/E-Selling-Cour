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
    'apps/web-platform/src/Shared/Ui/PortalPage.php',
    'apps/web-platform/src/Shared/Ui/PanelFactory.php',
    'apps/web-platform/src/Shared/Ui/AdminConsole.php',
    'apps/web-platform/src/Shared/Ui/AccountProfilePage.php',
    'apps/web-platform/src/Shared/Profile/AccountProfileManager.php',
    'apps/web-platform/src/Shared/Room/RoomRuntime.php',
    'apps/web-platform/src/Shared/Routing/HouseRouter.php',
    'apps/web-platform/src/Shared/Session/AuthSession.php',
    'apps/web-platform/src/Shared/Session/SessionGuard.php',
    'apps/web-platform/src/Admin/Profile/Middleware.php',
    'apps/web-platform/src/Admin/Profile/Controller.php',
    'apps/web-platform/src/Admin/Profile/Page.php',
    'apps/web-platform/src/Student/Profile/Controller.php',
    'apps/web-platform/src/Student/Profile/Page.php',
    'apps/web-platform/public/assets/css/app.css',
    'apps/web-platform/public/assets/css/admin-console.css',
    'apps/web-platform/public/assets/css/profile-links.css',
    'apps/web-platform/public/assets/js/app.js',
    'services/identity-service/public/account-profile.php',
    'services/identity-service/public/router.php',
    'services/identity-service/src/Features/Login/LoginHandler.php',
    'services/identity-service/src/Features/Session/SessionHandler.php',
    'services/reporting-service/public/admin-console.php',
    'services/reporting-service/public/router.php',
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
    ],
    'apps/web-platform/src/Shared/Ui/PortalPage.php' => [
        'data-portal-nav',
        'data-logout-dialog',
        'Yes, log out',
        '$role === \'admin\'',
        "'admin' => '/admin/profile'",
        "'instructor' => '/instructor/profile'",
        "default => '/student/profile'",
        'portal-profile-link',
        'portal-top-profile',
        "profileImage !== ''",
        '?photo=1&amp;v=',
    ],
    'apps/web-platform/src/Shared/Ui/AccountProfilePage.php' => [
        'View photo',
        'Change photo',
        'Remove photo',
        'data-profile-photo-remove',
        'profile_photo',
        'Csrf::field()',
    ],
    'apps/web-platform/src/Shared/Profile/AccountProfileManager.php' => [
        "'/api/v1/users/account-profile'",
        "'private/profile-photos'",
        "'change_photo'",
        "'remove_photo'",
        'SecureUpload::delete',
        'AuthSession::synchronizeUser',
    ],
    'apps/web-platform/src/Instructor/Profile/Page.php' => [
        'View photo',
        'cannot be removed completely',
        '25-day lock',
    ],
    'apps/web-platform/public/assets/css/profile-links.css' => [
        '.portal-profile-link',
        '.portal-top-profile',
        '.portal-avatar img',
        '.account-profile-panel',
        '.account-profile-upload',
    ],
    'apps/web-platform/public/assets/js/app.js' => [
        'navigationScrollKey',
        'sessionStorage.setItem',
        'data-logout-confirm',
        'showModal',
        "fetch('/session-status'",
        'window.setInterval(checkSession, 12000)',
        'window.location.replace',
        'data-profile-photo-remove',
        'Remove this profile photo',
    ],
    'apps/web-platform/src/Shared/Routing/HouseRouter.php' => [
        "'/session-status'",
        'SessionGuard::verify($requiredRole)',
        'SessionEndedException',
        "Response::redirect($exception->loginPath() . '?session=ended')",
    ],
    'apps/web-platform/src/Shared/Session/AuthSession.php' => [
        'synchronizeUser',
        "'profile_image'",
    ],
    'apps/web-platform/src/Shared/Session/SessionGuard.php' => [
        "get('/api/v1/auth/session')",
        'AuthSession::clear()',
        'AuthSession::synchronizeUser',
        "'/learn/sign-in'",
        "'/teach/studio-access'",
        'ADMIN_LOGIN_PATH',
        'SessionValidationUnavailableException',
    ],
    'services/identity-service/public/account-profile.php' => [
        "['student', 'admin']",
        "'change_photo', 'remove_photo'",
        'private/profile-photos',
        'profile_image=NULL',
        'old_profile_image',
    ],
    'services/identity-service/public/router.php' => [
        "'/api/v1/users/account-profile'",
        "require __DIR__ . '/account-profile.php'",
    ],
    'services/identity-service/src/Features/Login/LoginHandler.php' => [
        'profile_image FROM users',
        "'profile_image' =>",
    ],
    'services/identity-service/src/Features/Session/SessionHandler.php' => [
        'u.profile_image',
        "'profile_image' =>",
    ],
    'apps/web-platform/src/Shared/Ui/AdminConsole.php' => [
        "'Notifications' => 'notifications'",
        "'Students' => 'students'",
        "'Instructors' => 'instructors'",
        "'Users' => 'users'",
        "'Categories' => 'categories'",
        "'Refunds' => 'refunds'",
        "'Coupons' => 'coupons'",
        "'Reports' => 'reports'",
        "'AuditLogs' => 'audit-logs'",
        "'Security' => 'security'",
        "'Settings' => 'settings'",
        'Csrf::field()',
    ],
    'services/reporting-service/public/admin-console.php' => [
        'ServiceAuth::requireUser($database, $authorization, \'admin\')',
        "'notifications', 'students', 'instructors', 'users', 'categories', 'refunds'",
        "'coupons', 'reports', 'audit-logs', 'security', 'settings'",
        "payment_status='refunded'",
        'UPDATE identity_sessions SET revoked_at',
        'ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
    ],
    'docker-compose.yml' => [
        '/repo/services/reporting-service',
        'public/router.php',
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
echo "Admin navigation rooms: implemented\n";
echo "Portal avatars: linked to role profiles\n";
echo "Profile photos: view, change and role-safe removal enabled\n";
echo "Revoked sessions: automatic portal logout enabled\n";
