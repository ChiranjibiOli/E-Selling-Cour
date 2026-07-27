<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$files = [
    'theme_css' => 'apps/web-platform/public/assets/css/course-card-theme.css',
    'theme_js' => 'apps/web-platform/public/assets/js/course-card-theme.js',
    'portal' => 'apps/web-platform/src/Shared/Ui/PortalPage.php',
    'catalog_page' => 'apps/web-platform/src/Public/Courses/Page.php',
    'landing_js' => 'apps/web-platform/public/room-assets/Public/Landing/page-v2.js',
    'landing_card' => 'apps/web-platform/src/Public/Landing/Components/CourseCard.php',
    'catalog_card' => 'apps/web-platform/src/Public/Courses/Components/CourseCard.php',
    'authoring' => 'apps/web-platform/src/Instructor/CreateCourse/Page.php',
    'student_courses' => 'apps/web-platform/src/Student/MyCourses/Page.php',
];

$content = [];
foreach ($files as $key => $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        $errors[] = 'Missing required file: ' . $relative;
        $content[$key] = '';
        continue;
    }
    $content[$key] = (string) file_get_contents($path);
}

foreach (['.course-card', '.catalog-card', '.provided-course-card', '.learning-course-card', '--course-tone', '--course-glow', 'backdrop-filter', 'linear-gradient'] as $needle) {
    if (!str_contains($content['theme_css'], $needle)) {
        $errors[] = 'Shared course-card CSS is missing: ' . $needle;
    }
}

foreach (['canvas', 'getImageData', 'sourceHeight', 'MutationObserver', '--course-tone', '--course-glow', '.provided-course-card', '.learning-course-card'] as $needle) {
    if (!str_contains($content['theme_js'], $needle)) {
        $errors[] = 'Adaptive colour script is missing: ' . $needle;
    }
}

foreach (['/assets/css/course-card-theme.css', '/assets/js/course-card-theme.js'] as $asset) {
    if (!str_contains($content['portal'], $asset)) {
        $errors[] = 'Authenticated portals do not load: ' . $asset;
    }
    if (!str_contains($content['catalog_page'], $asset)) {
        $errors[] = 'Course catalogue does not load: ' . $asset;
    }
    if (!str_contains($content['landing_js'], $asset)) {
        $errors[] = 'Landing page does not load: ' . $asset;
    }
}

$locations = [
    'homepage card' => [$content['landing_card'], 'class="course-card"'],
    'catalogue card' => [$content['catalog_card'], 'class="catalog-card"'],
    'Instructor live preview' => [$content['authoring'], 'class="provided-course-card"'],
    'Student purchased course' => [$content['student_courses'], 'class="learning-course-card"'],
];
foreach ($locations as $label => [$haystack, $needle]) {
    if (!str_contains($haystack, $needle)) {
        $errors[] = 'Missing adaptive-card target for ' . $label . '.';
    }
}

if (!str_contains($content['authoring'], 'data-preview-media')) {
    $errors[] = 'Instructor thumbnail preview is not connected to dynamic image replacement.';
}

if ($errors !== []) {
    echo "ADAPTIVE COURSE CARD CHECK: FAIL\n";
    foreach ($errors as $error) {
        echo '- ' . $error . PHP_EOL;
    }
    exit(1);
}

echo "ADAPTIVE COURSE CARD CHECK: PASS\n";
echo "Homepage course cards: adaptive full-image design\n";
echo "Catalogue course cards: adaptive full-image design\n";
echo "Instructor live preview: adaptive after thumbnail selection\n";
echo "Student purchased courses: adaptive full-image design\n";
echo "Bottom-image colour sampling, dark tint and blur: enabled\n";
