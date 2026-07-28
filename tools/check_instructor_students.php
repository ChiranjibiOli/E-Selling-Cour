<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'services/reporting-service/public/router.php' => [
        '/api/v1/reports/instructor-students',
        'instructor-students.php',
    ],
    'services/reporting-service/public/instructor-students.php' => [
        "requireUser($database, $authorization, 'instructor')",
        'c.instructor_id=:instructor_id',
        "WHERE e.status='active'",
        'lesson_progress',
        'progress_percent',
        'active_enrollments',
    ],
    'apps/web-platform/src/Instructor/Students/Controller.php' => [
        '/api/v1/reports/instructor-students',
        'InstructorStudentsPage::render',
    ],
    'apps/web-platform/src/Instructor/Students/Page.php' => [
        'Related students',
        'Active enrollments',
        'Learning started',
        'Students enrolled in courses you own',
        'No enrolled students yet',
    ],
];

$errors = [];
foreach ($checks as $relativePath => $needles) {
    $path = $root . '/' . $relativePath;
    $content = is_file($path) ? (string) file_get_contents($path) : '';
    if ($content === '') {
        $errors[] = 'Missing Instructor student roster file: ' . $relativePath;
        continue;
    }
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $errors[] = 'Missing Instructor student roster contract in ' . $relativePath . ': ' . $needle;
        }
    }
}

$controller = (string) file_get_contents($root . '/apps/web-platform/src/Instructor/Students/Controller.php');
if (str_contains($controller, 'RoomRuntime::load')) {
    $errors[] = 'Instructor Students must not use the generic placeholder loader.';
}

if ($errors !== []) {
    echo "INSTRUCTOR STUDENTS CHECK: FAIL\n";
    foreach ($errors as $error) {
        echo '- ' . $error . PHP_EOL;
    }
    exit(1);
}

echo "INSTRUCTOR STUDENTS CHECK: PASS\n";
echo "Ownership: only active enrollments for courses owned by the logged-in Instructor\n";
echo "Roster: Student, course, enrollment date, progress, access and status\n";
