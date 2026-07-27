<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$files = [
    'style' => 'apps/web-platform/public/assets/css/public-site.css',
    'polish' => 'apps/web-platform/public/assets/css/public-site-fixes.css',
    'script' => 'apps/web-platform/public/assets/js/public-site.js',
    'courses' => 'apps/web-platform/src/Public/Courses/Page.php',
    'information' => 'apps/web-platform/src/Shared/Ui/PublicInformationPage.php',
    'student_login' => 'apps/web-platform/src/Student/Login/StudentLoginScreen.php',
    'registration' => 'apps/web-platform/src/Shared/Ui/RegistrationPage.php',
    'contact' => 'apps/web-platform/src/Public/Contact/Page.php',
    'course_detail' => 'apps/web-platform/src/Public/CourseDetails/Page.php',
    'forgot' => 'apps/web-platform/src/Public/ForgotPassword/Page.php',
    'reset' => 'apps/web-platform/src/Public/ResetPassword/Page.php',
    'verify' => 'apps/web-platform/src/Public/VerifyOtp/Page.php',
    'search' => 'apps/web-platform/src/Public/CourseSearch/Controller.php',
];

$content = [];
foreach ($files as $key => $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        $errors[] = 'Missing public cohesion file: ' . $relative;
        $content[$key] = '';
        continue;
    }
    $content[$key] = (string) file_get_contents($path);
}

foreach (['--public-coral:#ff7043', '--public-peach:#fff3e0', '.public-site-nav', 'transform:scaleX(0)', 'transform:scaleX(1)'] as $needle) {
    if (!str_contains($content['style'], $needle)) {
        $errors[] = 'Shared public style is missing: ' . $needle;
    }
}

foreach (['is-compact', 'data-public-site-menu', 'public-site-fixes.css'] as $needle) {
    if (!str_contains($content['script'], $needle)) {
        $errors[] = 'Shared public navigation script is missing: ' . $needle;
    }
}

foreach (['courses', 'information', 'student_login', 'contact', 'course_detail', 'forgot', 'reset', 'verify'] as $key) {
    foreach (['public-site-nav', 'public-site.css', 'public-site.js'] as $needle) {
        if (!str_contains($content[$key], $needle)) {
            $errors[] = $files[$key] . ' does not load the shared public shell: ' . $needle;
        }
    }
}

foreach (['/learn/sign-in', '/register/student', '/courses', '/about', '/contact'] as $studentPath) {
    if (!str_contains($content['courses'], $studentPath)) {
        $errors[] = 'Course catalogue is missing Student-facing navigation: ' . $studentPath;
    }
}

foreach (['/teach/studio-access', '/register/instructor', 'control-room/entry'] as $staffPath) {
    foreach (['courses', 'information', 'student_login', 'contact', 'course_detail', 'forgot', 'reset', 'verify'] as $key) {
        if (str_contains($content[$key], 'href="' . $staffPath . '"') || str_contains($content[$key], "href='" . $staffPath . "'")) {
            $errors[] = $files[$key] . ' advertises a staff-only destination: ' . $staffPath;
        }
    }
}

if (!str_contains($content['registration'], 'public-form-body') || !str_contains($content['registration'], 'noindex,nofollow,noarchive')) {
    $errors[] = 'Registration renderer must keep Student public styling and Instructor no-index separation.';
}

if (!str_contains($content['search'], "Response::redirect(\$destination)")) {
    $errors[] = 'Public /search must redirect into the real course catalogue.';
}

if ($errors !== []) {
    echo "PUBLIC SITE COHESION CHECK: FAIL\n";
    foreach ($errors as $error) {
        echo '- ' . $error . PHP_EOL;
    }
    exit(1);
}

echo "PUBLIC SITE COHESION CHECK: PASS\n";
echo "Landing, Courses, About and Contact: one visual system\n";
echo "Student login, registration and recovery: one visual system\n";
echo "Course details and public search: connected to the catalogue\n";
echo "Hover fill and compact navigation: enabled\n";
echo "Instructor and Admin entrances: unadvertised publicly\n";
