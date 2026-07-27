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
    'apps/web-platform/src/Shared/Room/RoomRuntime.php',
    'apps/web-platform/src/Shared/Routing/HouseRouter.php',
    'apps/web-platform/src/Shared/Session/SessionGuard.php',
    'apps/web-platform/src/Admin/Profile/Middleware.php',
    'apps/web-platform/src/Admin/Profile/Controller.php',
    'apps/web-platform/src/Admin/Profile/Page.php',
    'apps/web-platform/public/assets/css/app.css',
    'apps/web-platform/public/assets/css/admin-console.css',
    'apps/web-platform/public/assets/css/profile-links.css',
    'apps/web-platform/public/assets/js/app.js',
    'services/reporting-service/public/admin-console.php',
    'services/reporting-service/public/router.php',
];
foreach ($requiredFiles as $file) {
    if (!is_file($root . '/' . $file) || filesize($root . '/' . $file) < 100) {
        $errors[] = 'Shared panel asset is missing or empty: ' . $file;
    }
}

$contracts = [
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
    ],
    'apps/web-platform/src/Admin/Profile/Page.php' => [
        "PortalPage::render('admin', 'Profile'",
        'admin-profile-panel',
        'Open security',
        'Open settings',
    ],
    'apps/web-platform/public/assets/css/profile-links.css' => [
        '.portal-profile-link',
        '.portal-top-profile',
        '.admin-profile-panel',
    ],
    'apps/web-platform/public/assets/js/app.js' => [
        'navigationScrollKey',
        'sessionStorage.setItem',
        'data-logout-confirm',
        'showModal',
        "fetch('/session-status'",
        'window.setInterval(checkSession, 12000)',
        'window.location.replace',
    ],
    'apps/web-platform/src/Shared/Routing/HouseRouter.php' => [
        "'/session-status'",
        'SessionGuard::verify($requiredRole)',
        'SessionEndedException',
        "Response::redirect($exception->loginPath() . '?session=ended')",
    ],
    'apps/web-platform/src/Shared/Session/SessionGuard.php' => [
        "get('/api/v1/auth/session')",
        'AuthSession::clear()',
        "'/learn/sign-in'",
        "'/teach/studio-access'",
        'ADMIN_LOGIN_PATH',
        'SessionValidationUnavailableException',
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
            $errors[] = 'Missing Admin panel contract in ' . $file . ': ' . $needle;
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
echo "Revoked sessions: automatic portal logout enabled\n";
