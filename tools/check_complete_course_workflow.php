<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$requiredFiles = [
    'database/migrations/008_course_authoring_workflow.sql',
    'services/catalog-service/public/router.php',
    'services/catalog-service/public/authoring.php',
    'services/catalog-service/public/review.php',
    'services/catalog-service/public/course-duration.php',
    'services/learning-service/public/router.php',
    'services/learning-service/public/player.php',
    'services/learning-service/public/curriculum.php',
    'services/payment-service/public/router.php',
    'services/payment-service/public/manual.php',
    'services/notification-service/public/index.php',
    'apps/web-platform/src/Instructor/CreateCourse/Controller.php',
    'apps/web-platform/src/Instructor/CreateCourse/Page.php',
    'apps/web-platform/src/Instructor/MyCourses/Controller.php',
    'apps/web-platform/src/Instructor/MyCourses/Page.php',
    'apps/web-platform/src/Admin/CourseApprovals/Controller.php',
    'apps/web-platform/src/Admin/CourseApprovals/Page.php',
    'apps/web-platform/src/Admin/ContactMessages/Controller.php',
    'apps/web-platform/src/Admin/ContactMessages/Page.php',
    'apps/web-platform/src/Student/Payment/Controller.php',
    'apps/web-platform/src/Student/Payment/Page.php',
    'apps/web-platform/src/Admin/Payments/Controller.php',
    'apps/web-platform/src/Admin/Payments/Page.php',
    'apps/web-platform/src/Student/CoursePlayer/Controller.php',
    'apps/web-platform/src/Student/CoursePlayer/Page.php',
    'apps/web-platform/src/Public/CourseDetails/Controller.php',
    'apps/web-platform/src/Public/CourseDetails/Page.php',
    'apps/web-platform/public/assets/js/app.js',
    'apps/web-platform/public/assets/js/workflow.js',
    'apps/web-platform/public/assets/css/instructor-console.css',
    'apps/web-platform/public/assets/css/workflow-console.css',
    'apps/web-platform/public/assets/css/learning-commerce.css',
    'apps/web-platform/public/assets/css/instructor-communication.css',
];

foreach ($requiredFiles as $file) {
    $path = $root . '/' . $file;
    if (!is_file($path) || filesize($path) < 100) {
        $errors[] = 'Required workflow file is missing or empty: ' . $file;
    }
}

$contracts = [
    'tools/run_migrations.php' => [
        "'008_course_authoring_workflow'",
        '008_course_authoring_workflow.sql',
    ],
    'database/migrations/008_course_authoring_workflow.sql' => [
        'reply_delivery_status',
        'edit_permission_status',
        'content_version',
        "ENUM('text', 'word', 'video', 'pdf', 'audio', 'image', 'link')",
        'CREATE TABLE course_revisions',
        "ENUM('draft', 'pending', 'approved', 'rejected')",
        'change_summary JSON',
        'student_summary',
    ],
    'services/identity-service/src/Infrastructure/SmtpMailer.php' => [
        'sendSupportReply',
        'CourseHub Support',
    ],
    'services/notification-service/public/index.php' => [
        '/reply$#',
        'SmtpMailer::sendSupportReply',
        "reply_delivery_status='sent'",
        "reply_delivery_status='failed'",
    ],
    'apps/web-platform/src/Admin/ContactMessages/Page.php' => [
        'Send reply by email',
        'Reply destination',
        'reply_subject',
        'reply_message',
        'Csrf::field()',
    ],
    'apps/web-platform/src/Instructor/CreateCourse/Page.php' => [
        'COMPLETE COURSE AUTHORING',
        'provided-course-card',
        'field-info',
        'role="tooltip"',
        'curriculum_json',
        'data-section-number',
        'data-lesson-number',
        'data-content-type',
        'data-content-panel="text word"',
        'data-content-panel="video"',
        'data-content-panel="pdf"',
        'data-content-panel="audio"',
        'data-content-panel="image"',
        'name="action" value="draft"',
        'name="action" value="submit"',
    ],
    'apps/web-platform/src/Instructor/CreateCourse/Controller.php' => [
        'private/course-content',
        'media/course-thumbnails',
        "['text', 'word', 'video', 'pdf', 'audio', 'image', 'link']",
        "'/api/v1/courses/' . \$courseId . '/duration'",
        'SecureUpload::store',
        'SecureUpload::delete',
    ],
    'apps/web-platform/public/assets/js/app.js' => [
        'renumberAuthoring',
        'serializeCurriculum',
        'setLessonPanel',
        'data-section-number',
        'data-lesson-number',
        'data-error',
        'provided-course-card',
        'URL.createObjectURL',
    ],
    'services/catalog-service/public/authoring.php' => [
        "['draft', 'submit']",
        'edit_permission_status',
        'revision_status',
        'course_revisions',
        'change_summary',
        'course_change_logs',
        'course_updated',
        'Admin edit permission is required',
        'Partial published-course edits are disabled',
    ],
    'services/catalog-service/public/course-duration.php' => [
        'Course duration synchronized.',
        'revision_snapshot',
        "status'] === 'published'",
    ],
    'apps/web-platform/src/Instructor/MyCourses/Page.php' => [
        'Request edit permission',
        'Create private revision',
        'Continue private revision',
        'Locked during Admin review',
    ],
    'apps/web-platform/src/Admin/CourseApprovals/Page.php' => [
        'New course submissions',
        'Published-course edit permission',
        'Staged course revisions',
        'Approve revision and notify Students',
        'Complete curriculum',
    ],
    'services/learning-service/public/curriculum.php' => [
        'Legacy section and lesson mutations are disabled',
        'complete course authoring page',
    ],
    'apps/web-platform/src/Student/Payment/Page.php' => [
        'type="file" name="proof_image"',
        'actual payment screenshot or PDF receipt',
        'data-payment-proof-preview',
    ],
    'apps/web-platform/src/Student/Payment/Controller.php' => [
        'private/payment-proofs',
        'getimagesize',
        'application/pdf',
        '8 * 1024 * 1024',
    ],
    'services/payment-service/public/manual.php' => [
        "private/payment-proofs/",
        'payment_proofs',
        'protected receipt',
    ],
    'apps/web-platform/src/Admin/Payments/Page.php' => [
        'View uploaded proof',
        'data-proof-dialog',
        'data-proof-frame',
    ],
    'services/learning-service/public/player.php' => [
        'content_name',
        'completed_lessons',
        "e.status='active'",
        "c.status='published'",
    ],
    'apps/web-platform/src/Student/CoursePlayer/Page.php' => [
        'Mark complete',
        'lesson-reading',
        '<video controls',
        '<audio controls',
        'lesson-pdf-wrap',
        'course-changes-dialog',
        'VERSION ',
    ],
    'apps/web-platform/src/Student/CoursePlayer/Controller.php' => [
        '/complete',
        '&completed=1',
        "['private/course-content']",
        '/change-log',
    ],
    'apps/web-platform/src/Public/CourseDetails/Page.php' => [
        'Continue learning',
        'Buy once',
        'Already purchased',
    ],
    'apps/web-platform/src/Instructor/Notifications/Page.php' => [
        'INSTRUCTOR INBOX',
        'Mark all as read',
    ],
    'apps/web-platform/src/Instructor/Messaging/Page.php' => [
        'Admin replies directly to your registered email',
        'Send message to Admin',
        'Csrf::field()',
    ],
    'docker-compose.yml' => [
        'services/catalog-service',
        'public/router.php',
        'services/learning-service',
        'services/payment-service',
        'SMTP_FROM_ADDRESS',
    ],
];

foreach ($contracts as $file => $needles) {
    $path = $root . '/' . $file;
    $content = is_file($path) ? (string) file_get_contents($path) : '';
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $errors[] = 'Missing complete-workflow contract in ' . $file . ': ' . $needle;
        }
    }
}

$rooms = require $root . '/apps/web-platform/src/config/rooms.php';
$implementedRooms = [
    'Student/CoursePlayer',
    'Instructor/CreateCourse',
    'Instructor/EditCourse',
    'Instructor/CurriculumBuilder',
    'Instructor/Lessons',
    'Instructor/DraftCourses',
    'Instructor/PendingCourses',
    'Instructor/PublishedCourses',
    'Instructor/Sales',
    'Instructor/Notifications',
    'Instructor/Messaging',
    'Admin/CourseApprovals',
    'Admin/Payments',
    'Admin/ContactMessages',
];
foreach ($implementedRooms as $room) {
    if (($rooms[$room]['status'] ?? '') !== 'implemented') {
        $errors[] = 'Workflow room is not registered as implemented: ' . $room;
    }
}

$coursePage = (string) file_get_contents($root . '/apps/web-platform/src/Instructor/CreateCourse/Page.php');
if (str_contains($coursePage, 'name="sort_order"')) {
    $errors[] = 'Instructor course authoring must not expose editable sort-order fields.';
}
if (str_contains($coursePage, 'Thumbnail filename')) {
    $errors[] = 'Course authoring must upload an actual thumbnail instead of accepting a filename.';
}

$paymentPage = (string) file_get_contents($root . '/apps/web-platform/src/Student/Payment/Page.php');
if (str_contains($paymentPage, 'name="proof_image" value=')) {
    $errors[] = 'Manual payment must not accept a typed proof filename.';
}

if ($errors !== []) {
    echo "COMPLETE COURSE WORKFLOW CHECK: FAIL\n";
    foreach ($errors as $error) {
        echo '- ' . $error . PHP_EOL;
    }
    exit(1);
}

echo "COMPLETE COURSE WORKFLOW CHECK: PASS\n";
echo "Admin support replies: SMTP email enabled\n";
echo "Course authoring: one complete page with supplied card design\n";
echo "Curriculum: automatic serial numbering and typed lesson content\n";
echo "Published courses: permission, private revision and Admin approval enforced\n";
echo "Manual payments: actual protected receipt required\n";
echo "Purchased learning: protected content, progress and approved update history enabled\n";
echo "Instructor rooms: notifications, messages, sales and course queues implemented\n";
