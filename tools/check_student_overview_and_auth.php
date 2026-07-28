<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$contracts = [
    'apps/web-platform/src/Student/Dashboard/Controller.php' => [
        '/api/v1/enrollments/mine',
        '/api/v1/progress/mine',
        '/api/v1/cart',
        '/api/v1/notifications?limit=5',
        '/api/v1/courses?limit=6',
    ],
    'apps/web-platform/src/Student/Dashboard/Page.php' => [
        'student-overview-hero',
        'CONTINUE LEARNING',
        'student-overview-cart',
        'RECENT UPDATES',
        'DISCOVER NEXT',
    ],
    'apps/web-platform/src/Shared/Ui/PortalPage.php' => [
        "'Purchases' => ['/student/cart' => 'My cart', '/student/payment-history' => 'Payment history']",
        "in_array(\$currentPath, ['/student/checkout', '/student/payment'], true)",
        "return '/student/cart';",
        'student-experience.css',
    ],
    'apps/web-platform/src/Student/Cart/Page.php' => [
        'student-purchase-flow',
        'Review cart',
        'Confirm order',
        'Complete payment',
    ],
    'apps/web-platform/src/Student/Checkout/Page.php' => [
        'student-purchase-flow',
        'My cart · Confirm order',
    ],
    'apps/web-platform/src/Student/Payment/Page.php' => [
        'student-purchase-flow',
        'My cart · Payment',
    ],
    'apps/web-platform/src/Student/Login/StudentLoginScreen.php' => [
        'student-login-card-mark',
        'YOUR LEARNING SPACE',
        'page.css?v=20260728-4',
    ],
    'apps/web-platform/src/Instructor/Login/StudioAccessScreen.php' => [
        'studio-form-brand',
        'APPROVED INSTRUCTOR ACCESS',
        'page.css?v=20260728-4',
    ],
    'apps/web-platform/src/Admin/Login/ControlRoomScreen.php' => [
        'control-terminal-badge',
        'PRIVATE CONTROL ENTRY',
        'page.css?v=20260728-4',
    ],
    'apps/web-platform/src/Shared/Ui/RegistrationPage.php' => [
        'instructor-registration.css',
        'instructor-application-shell',
        'Profile photo changeable anytime',
        'You can change the approved profile photo later at any time.',
    ],
];

$errors = [];
foreach ($contracts as $relativePath => $needles) {
    $path = $root . '/' . $relativePath;
    $content = is_file($path) ? (string) file_get_contents($path) : '';
    if ($content === '') {
        $errors[] = 'Missing experience file: ' . $relativePath;
        continue;
    }
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $errors[] = 'Missing experience contract in ' . $relativePath . ': ' . $needle;
        }
    }
}

$portal = (string) file_get_contents($root . '/apps/web-platform/src/Shared/Ui/PortalPage.php');
foreach (["'/student/checkout' => 'Checkout'", "'/student/payment' => 'Payment'"] as $removedSidebarItem) {
    if (str_contains($portal, $removedSidebarItem)) {
        $errors[] = 'Checkout and Payment must not return as permanent Student sidebar items: ' . $removedSidebarItem;
    }
}

$registration = (string) file_get_contents($root . '/apps/web-platform/src/Shared/Ui/RegistrationPage.php');
foreach (['25-day', '25 days', 'only once every'] as $removedPhotoCooldownCopy) {
    if (str_contains($registration, $removedPhotoCooldownCopy)) {
        $errors[] = 'Instructor registration restored obsolete photo-cooldown copy: ' . $removedPhotoCooldownCopy;
    }
}

foreach ([
    'apps/web-platform/public/assets/css/student-experience.css',
    'apps/web-platform/public/assets/css/instructor-registration.css',
    'apps/web-platform/src/Student/Login/Assets/page.css',
    'apps/web-platform/src/Instructor/Login/Assets/page.css',
    'apps/web-platform/src/Admin/Login/Assets/page.css',
] as $stylePath) {
    $fullPath = $root . '/' . $stylePath;
    if (!is_file($fullPath) || filesize($fullPath) < 1000) {
        $errors[] = 'Missing or incomplete redesigned stylesheet: ' . $stylePath;
    }
}

if ($errors !== []) {
    echo "STUDENT OVERVIEW AND AUTH CHECK: FAIL\n";
    foreach ($errors as $error) {
        echo '- ' . $error . PHP_EOL;
    }
    exit(1);
}

echo "STUDENT OVERVIEW AND AUTH CHECK: PASS\n";
echo "Student overview: live courses, progress, cart, notifications and catalogue\n";
echo "Student purchase navigation: My cart owns checkout and payment steps\n";
echo "Auth design: Student, Instructor, Instructor application and Admin refreshed\n";
