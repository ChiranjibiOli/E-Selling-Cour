<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$loginPage = $root . '/apps/web-platform/src/Public/Login/Page.php';
$loginController = $root . '/apps/web-platform/src/Public/Login/Controller.php';
$roomRuntime = $root . '/apps/web-platform/src/Shared/Room/RoomRuntime.php';
$instructorLogin = $root . '/apps/web-platform/src/Instructor/Login/StudioAccessScreen.php';

foreach ([$loginPage, $loginController, $roomRuntime, $instructorLogin] as $required) {
    if (!is_file($required)) {
        $errors[] = 'Missing required portal-entry file: ' . str_replace($root . '/', '', $required);
    }
}

$loginContent = is_file($loginPage) ? (string) file_get_contents($loginPage) : '';
$controllerContent = is_file($loginController) ? (string) file_get_contents($loginController) : '';
$runtimeContent = is_file($roomRuntime) ? (string) file_get_contents($roomRuntime) : '';
$instructorContent = is_file($instructorLogin) ? (string) file_get_contents($instructorLogin) : '';

foreach (['/learn/sign-in', '/teach/studio-access', 'access-portals', 'access-shortcuts'] as $needle) {
    if (!str_contains($loginContent, $needle)) {
        $errors[] = 'Dedicated portal chooser is missing: ' . $needle;
    }
}

if (!str_contains($controllerContent, 'LoginPage::render')) {
    $errors[] = 'The /login controller still uses the generic room renderer.';
}

if (str_contains($loginContent, 'control-room') || str_contains($loginContent, 'ADMIN_LOGIN_PATH')) {
    $errors[] = 'The private Admin entrance must not appear on the public chooser.';
}

if (str_contains($runtimeContent, '/teach/studio-access') || str_contains($runtimeContent, '/register/instructor')) {
    $errors[] = 'Generic public room headers must not advertise Instructor access.';
}

if (!str_contains($runtimeContent, '<a href="/login">Sign in</a>')) {
    $errors[] = 'Generic public room headers should use the neutral /login entry.';
}

if (!str_contains($instructorContent, '/register/instructor')) {
    $errors[] = 'Instructor registration must remain inside the Instructor login portal.';
}

$publicDirectory = $root . '/apps/web-platform/src/Public';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($publicDirectory, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    $relative = str_replace($root . '/', '', $path);
    $content = (string) file_get_contents($path);

    if (str_contains($content, 'control-room/entry') || str_contains($content, 'ADMIN_LOGIN_PATH')) {
        $errors[] = 'Public page exposes Admin access: ' . $relative;
    }
    if ($relative !== 'apps/web-platform/src/Public/Login/Page.php'
        && (str_contains($content, 'href="/teach/studio-access"') || str_contains($content, "href='/teach/studio-access'"))) {
        $errors[] = 'Instructor login appears outside the dedicated chooser: ' . $relative;
    }
    if (str_contains($content, 'href="/register/instructor"') || str_contains($content, "href='/register/instructor'")) {
        $errors[] = 'Instructor registration appears on a random public page: ' . $relative;
    }
}

if ($errors !== []) {
    echo "PORTAL ENTRY SEPARATION CHECK: FAIL\n";
    foreach ($errors as $error) {
        echo '- ' . $error . PHP_EOL;
    }
    exit(1);
}

echo "PORTAL ENTRY SEPARATION CHECK: PASS\n";
echo "Student portal: dedicated\n";
echo "Instructor portal and registration: dedicated\n";
echo "Admin entrance: private and unadvertised\n";
echo "Random public role links: removed\n";
