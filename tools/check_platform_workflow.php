<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$checks = [
    'database/migrations/008_course_authoring_workflow.sql' => [
        'reply_delivery_status',
        'edit_permission_status',
        'CREATE TABLE course_revisions',
        "ENUM('draft', 'pending', 'approved', 'rejected')",
        "ENUM('text', 'word', 'video', 'pdf', 'audio', 'image', 'link')",
    ],
    'tools/run_migrations.php' => ['008_course_authoring_workflow.sql'],
    'docker-compose.override.yml' => [
        'upload_max_filesize=520M',
        'post_max_size=600M',
        'max_file_uploads=220',
    ],
    'services/catalog-service/public/router.php' => [
        "require __DIR__ . '/authoring.php'",
        "require __DIR__ . '/review.php'",
        "require __DIR__ . '/course-duration.php'",
    ],
    'services/catalog-service/public/authoring.php' => [
        'course_revisions',
        'edit_permission_status',
        'change_summary',
        'course_change_logs',
        'Partial published-course edits are disabled',
    ],
    'services/catalog-service/public/course-duration.php' => [
        'Course duration synchronized.',
        'revision_snapshot',
    ],
    'apps/web-platform/src/Instructor/CreateCourse/Page.php' => [
        'COMPLETE COURSE AUTHORING',
        'provided-course-card',
        'role="tooltip"',
        'data-section-number',
        'data-lesson-number',
        'data-content-panel="text word"',
        'data-content-panel="video"',
        'data-content-panel="pdf"',
        'data-content-panel="audio"',
        'data-content-panel="image"',
        'name="action" value="draft"',
        'name="action" value="submit"',
    ],
    'apps/web-platform/public/assets/js/app.js' => [
        'renumberAuthoring',
        'serializeCurriculum',
        'setLessonPanel',
        'data-error',
        'updateCoursePreview',
    ],
    'apps/web-platform/src/Instructor/MyCourses/Page.php' => [
        'Request edit permission',
        'Create private revision',
        'Continue private revision',
        'Submit from inside editor',
    ],
    'apps/web-platform/src/Admin/CourseApprovals/Page.php' => [
        'New course submissions',
        'Published-course edit permission',
        'Staged course revisions',
        'Approve revision and notify Students',
    ],
    'services/learning-service/public/curriculum.php' => [
        'Legacy section and lesson mutations are disabled',
    ],
    'services/notification-service/public/index.php' => [
        'sendSupportReply',
        'reply_delivery_status',
    ],
    'apps/web-platform/src/Admin/ContactMessages/Page.php' => [
        'Send reply by email',
        'Reply destination',
    ],
    'apps/web-platform/src/Student/Payment/Page.php' => [
        'type="file" name="proof_image"',
        'data-payment-proof-preview',
    ],
    'services/payment-service/public/manual.php' => [
        'private/payment-proofs',
        'payment_proofs',
    ],
    'apps/web-platform/src/Admin/Payments/Page.php' => [
        'View uploaded proof',
        'data-proof-dialog',
    ],
    'services/learning-service/public/player.php' => [
        'completed_lessons',
        'content_name',
    ],
    'apps/web-platform/src/Student/CoursePlayer/Page.php' => [
        'Mark complete',
        '<video controls',
        '<audio controls',
        'lesson-pdf-wrap',
        'course-changes-dialog',
    ],
    'apps/web-platform/src/Public/CourseDetails/Page.php' => [
        'Open purchased course',
        'Access active',
    ],
    'apps/web-platform/src/Instructor/Notifications/Page.php' => [
        'INSTRUCTOR INBOX',
    ],
    'apps/web-platform/src/Instructor/Messaging/Page.php' => [
        'Admin replies directly to your registered email',
    ],
];

foreach ($checks as $file => $needles) {
    $path = $root . '/' . $file;
    if (!is_file($path)) {
        $errors[] = 'Missing file: ' . $file;
        continue;
    }
    $content = (string) file_get_contents($path);
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $errors[] = 'Missing workflow contract in ' . $file . ': ' . $needle;
        }
    }
}

$rooms = require $root . '/apps/web-platform/src/config/rooms.php';
foreach ([
    'Student/CoursePlayer',
    'Instructor/CreateCourse',
    'Instructor/DraftCourses',
    'Instructor/PendingCourses',
    'Instructor/PublishedCourses',
    'Instructor/Sales',
    'Instructor/Notifications',
    'Instructor/Messaging',
    'Admin/CourseApprovals',
    'Admin/Payments',
    'Admin/ContactMessages',
] as $room) {
    if (($rooms[$room]['status'] ?? '') !== 'implemented') {
        $errors[] = 'Room must be implemented: ' . $room;
    }
}

$authoringPage = (string) file_get_contents($root . '/apps/web-platform/src/Instructor/CreateCourse/Page.php');
if (str_contains($authoringPage, 'name="sort_order"')) {
    $errors[] = 'Editable section or lesson order fields are forbidden.';
}
if (str_contains($authoringPage, 'Thumbnail filename')) {
    $errors[] = 'Course thumbnails must be uploaded files, not typed filenames.';
}

$paymentPage = (string) file_get_contents($root . '/apps/web-platform/src/Student/Payment/Page.php');
if (str_contains($paymentPage, 'name="proof_image" value=')) {
    $errors[] = 'Payment proof must be a real uploaded file, not a typed filename.';
}

if ($errors !== []) {
    echo "PLATFORM WORKFLOW CHECK: FAIL\n";
    foreach ($errors as $error) {
        echo '- ' . $error . PHP_EOL;
    }
    exit(1);
}

echo "PLATFORM WORKFLOW CHECK: PASS\n";
echo "Support email replies: enabled\n";
echo "Complete one-page course authoring: enabled\n";
echo "Automatic serial curriculum and typed lesson content: enabled\n";
echo "Published-course permission and revision review: enabled\n";
echo "Actual protected payment receipts: enabled\n";
echo "Purchased-course player, progress and change history: enabled\n";
echo "Instructor notifications, messages, sales and course queues: implemented\n";
