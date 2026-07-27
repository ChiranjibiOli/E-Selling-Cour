<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$files = [
    'navbar' => 'apps/web-platform/src/Shared/Ui/PublicNavbar.php',
    'navbar_style' => 'apps/web-platform/public/assets/css/public-navbar.css',
    'navbar_script' => 'apps/web-platform/public/assets/js/public-site.js',
    'landing' => 'apps/web-platform/src/Public/Landing/Page.php',
    'courses' => 'apps/web-platform/src/Public/Courses/Page.php',
    'information' => 'apps/web-platform/src/Shared/Ui/PublicInformationPage.php',
    'student_login' => 'apps/web-platform/src/Student/Login/StudentLoginScreen.php',
    'registration' => 'apps/web-platform/src/Shared/Ui/RegistrationPage.php',
    'contact' => 'apps/web-platform/src/Public/Contact/Page.php',
    'course_detail' => 'apps/web-platform/src/Public/CourseDetails/Page.php',
    'forgot' => 'apps/web-platform/src/Public/ForgotPassword/Page.php',
    'reset' => 'apps/web-platform/src/Public/ResetPassword/Page.php',
    'verify' => 'apps/web-platform/src/Public/VerifyOtp/Page.php',
    'pricing' => 'apps/web-platform/src/Public/Pricing/Page.php',
    'about' => 'apps/web-platform/src/Public/About/Page.php',
    'public_login' => 'apps/web-platform/src/Public/Login/Page.php',
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

foreach (['Home', 'Courses', 'Categories', 'About', 'Contact', 'Log in', 'Create account'] as $label) {
    if (!str_contains($content['navbar'], '>' . $label . '<')) {
        $errors[] = 'Shared public navbar is missing: ' . $label;
    }
}

foreach (['/#top', '/courses', '/#categories', '/#promise', '/contact', '/learn/sign-in', '/register/student'] as $destination) {
    if (!str_contains($content['navbar'], 'href="' . $destination . '"')) {
        $errors[] = 'Shared public navbar has the wrong or missing destination: ' . $destination;
    }
}

if (str_contains($content['navbar'], 'href="/about"')) {
    $errors[] = 'Shared navbar must not link to the retired duplicate /about page.';
}

foreach (['.coursehub-public-nav', '.coursehub-public-links a.active', '#171611', 'is-compact', 'menu-open'] as $needle) {
    if (!str_contains($content['navbar_style'], $needle)) {
        $errors[] = 'Shared navbar style is missing: ' . $needle;
    }
}

foreach (['data-public-section', 'destinationForPage', 'is-compact', 'nav-active-shift', "About: '/#promise'"] as $needle) {
    if (!str_contains($content['navbar_script'], $needle)) {
        $errors[] = 'Shared navbar behaviour is missing: ' . $needle;
    }
}

foreach (['landing', 'courses', 'information', 'student_login', 'contact', 'course_detail', 'forgot', 'reset', 'verify', 'pricing'] as $key) {
    foreach (['PublicNavbar::render', 'PublicNavbar::styles', 'PublicNavbar::script'] as $needle) {
        if (!str_contains($content[$key], $needle)) {
            $errors[] = $files[$key] . ' does not use the shared navbar contract: ' . $needle;
        }
    }
}

foreach (['/teach/studio-access', '/register/instructor', 'control-room/entry'] as $staffPath) {
    foreach (['landing', 'courses', 'information', 'student_login', 'contact', 'course_detail', 'forgot', 'reset', 'verify', 'pricing'] as $key) {
        if (str_contains($content[$key], 'href="' . $staffPath . '"') || str_contains($content[$key], "href='" . $staffPath . "'")) {
            $errors[] = $files[$key] . ' advertises a staff-only destination: ' . $staffPath;
        }
    }
}

if (!str_contains($content['about'], "Response::redirect('/#promise')")) {
    $errors[] = 'The duplicate /about page must redirect to the landing About section.';
}

if (!str_contains($content['public_login'], "Response::redirect('/learn/sign-in'")) {
    $errors[] = 'The duplicate public login page must redirect to Student sign in.';
}

if (!str_contains($content['registration'], 'public-form-body') || !str_contains($content['registration'], 'noindex,nofollow,noarchive')) {
    $errors[] = 'Registration renderer must keep Student public styling and Instructor no-index separation.';
}

if (!str_contains($content['search'], 'Response::redirect($destination)')) {
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
echo "One shared navbar: landing and every public panel\n";
echo "About: one landing section, no duplicate page\n";
echo "Active dark pill and compact scroll motion: enabled\n";
echo "Student login, registration and recovery: one visual system\n";
echo "Instructor and Admin entrances: unadvertised publicly\n";
