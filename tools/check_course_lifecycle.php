<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'services/_shared/ServiceAuth.php' => ['ServiceAuth', 'identity_sessions'],
    'services/identity-service/public/index.php' => ['/api/v1/auth/register/', 'instructor-applications', 'password_hash'],
    'services/catalog-service/public/index.php' => ['/api/v1/courses/mine', '/submit', '/(approve|reject)', 'ServiceAuth::requireUser'],
    'database/schema.sql' => ['CREATE TABLE instructor_applications', "status ENUM('draft', 'pending', 'published', 'rejected', 'archived')"],
    'apps/web-platform/src/Instructor/CreateCourse/Controller.php' => ['/api/v1/courses', 'Csrf::assertValid'],
    'apps/web-platform/src/Admin/CourseApprovals/Controller.php' => ['/api/v1/courses/pending', 'Csrf::assertValid'],
];

$errors = [];
foreach ($checks as $relative => $needles) {
    $path = $root . '/' . $relative;
    $content = is_file($path) ? (string) file_get_contents($path) : '';
    if ($content === '') {
        $errors[] = 'Missing lifecycle file: ' . $relative;
        continue;
    }
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $errors[] = 'Missing lifecycle contract in ' . $relative . ': ' . $needle;
        }
    }
}

if ($errors !== []) {
    echo "COURSE LIFECYCLE CHECK: FAIL\n";
    foreach ($errors as $error) {
        echo '- ' . $error . PHP_EOL;
    }
    exit(1);
}

echo "COURSE LIFECYCLE CHECK: PASS\n";
