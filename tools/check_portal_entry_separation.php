<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$loginPage = $root . '/apps/web-platform/src/Public/Login/Page.php';
$loginController = $root . '/apps/web-platform/src/Public/Login/Controller.php';
$roomRuntime = $root . '/apps/web-platform/src/Shared/Room/RoomRuntime.php';
$instructorLogin = $root . '/apps/web-platform/src/Instructor/Login/StudioAccessScreen.php';
$instructorsController = $root . '/apps/web-platform/src/Public/Instructors/Controller.php';
$publicInformationPage = $root . '/apps/web-platform/src/Shared/Ui/PublicInformationPage.php';

foreach ([$loginPage, $loginController, $roomRuntime, $instructorLogin, $instructorsController, $publicInformationPage] as $required) {
    if (!is_file($required)) {
        $errors[] = 'Missing required portal-entry file: ' . str_replace($root . '/', '', $required);
    }
}

$loginContent = is_file($loginPage) ? (string) file_get_contents($loginPage) : '';
$controllerContent = is_file($loginController) ? (string) file_get_contents($loginController) : '';
$runtimeContent = is_file($roomRuntime) ? (string) file_get_contents($roomRuntime) : '';
$instructorContent = is_file($instructorLogin) ? (string) file_get_contents($instructorLogin) : '';
$instructorsControllerContent = is_file($instructorsController) ? (string) file_get_contents($instructorsController) : '';
$publicInformationContent = is_file($publicInformationPage) ? (string) file_get_contents($publicInformationPage) : '';

if (!str_contains($controllerContent, "'/learn/sign-in'")) {
    $errors[] = 'The public /login route must redirect guests to Student sign in.';
}

if (!str_contains($loginContent, '/learn/sign-in')) {
    $errors[] = 'The public login fallback must remain Student-focused.';
}

foreach (['/teach/studio-access', '/register/instructor', 'control-room', 'ADMIN_LOGIN_PATH'] as $forbidden) {
    if (str_contains($loginContent, $forbidden)) {
        $errors[] = 'The public login page exposes a staff entrance: ' . $forbidden;
    }
}

if (!str_contains($instructorsControllerContent, "Response::redirect('/courses')")) {
    $errors[] = 'The retired public Instructor directory must redirect to the course catalogue.';
}

if (str_contains($publicInformationContent, 'href="/instructors"') || str_contains($publicInformationContent, "href='/instructors'")) {
    $errors[] = 'Public information navigation still advertises the Instructor directory.';
}

if (str_contains($runtimeContent, '/teach/studio-access') || str_contains($runtimeContent, '/register/instructor')) {
    $errors[] = 'Generic public room headers must not advertise Instructor access.';
}

if (!str_contains($runtimeContent, '<a href="/login">Sign in</a>')) {
    $errors[] = 'Generic public room headers should use the Student-focused /login entry.';
}

if (!str_contains($instructorContent, '/register/instructor')) {
    $errors[] = 'Instructor registration must remain inside the dedicated Instructor login portal.';
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

    foreach (['/teach/studio-access', '/register/instructor', '/instructors'] as $staffPath) {
        if (str_contains($content, 'href="' . $staffPath . '"') || str_contains($content, "href='" . $staffPath . "'")) {
            $errors[] = 'Public page advertises a staff-only destination (' . $staffPath . '): ' . $relative;
        }
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
echo "Public login: Student-only\n";
echo "Instructor portal and registration: dedicated and unadvertised\n";
echo "Admin entrance: private and unadvertised\n";
echo "Public Instructor directory: retired\n";
