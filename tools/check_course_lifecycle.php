<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'services/_shared/ServiceAuth.php' => ['ServiceAuth', 'identity_sessions'],
    'services/identity-service/public/index.php' => [
        '/api/v1/auth/register/',
        'instructor-applications',
        'password_hash',
        '/api/v1/auth/verify-student-email',
        '/api/v1/auth/resend-student-verification',
        '/api/v1/auth/reset-password-code',
        'student_registration',
        'student_password_reset',
        '@gmail.com',
    ],
    'services/identity-service/public/router.php' => [
        'instructor-registration.php',
        'instructor-decision.php',
        'instructor-profile.php',
        '/api/v1/auth/register/instructor',
        '/api/v1/users/instructor-profile',
    ],
    'services/identity-service/public/instructor-profile.php' => [
        "['save_profile', 'remove_photo']",
        'photo_change_allowed',
        'profile_image_changed_at=NOW()',
        'profile_image=NULL',
        'FOR UPDATE',
        'old_profile_image',
    ],
    'services/identity-service/public/instructor-registration.php' => [
        '$applicationStatus !== \'rejected\'',
        '$status !== \'blocked\'',
        'old_profile_image',
        'old_identity_document',
        'reapplication',
        'same email',
    ],
    'services/identity-service/public/instructor-decision.php' => [
        'sendInstructorRejection',
        'email_sent',
        'reapply with the same email',
        'application_status',
    ],
    'services/identity-service/src/Infrastructure/SmtpMailer.php' => [
        'SMTP_HOST',
        'STARTTLS',
        'AUTH LOGIN',
        'sendCode',
        'sendInstructorRejection',
        'same email address',
    ],
    'docker-compose.yml' => ['identity-service:', 'public/router.php', 'SMTP_PASSWORD'],
    'services/catalog-service/public/index.php' => ['/api/v1/courses/mine', '/submit', '/(approve|reject)', 'ServiceAuth::requireUser'],
    'database/schema.sql' => ['CREATE TABLE instructor_applications', "status ENUM('draft', 'pending', 'published', 'rejected', 'archived')"],
    'database/migrations/006_instructor_profile_photo_cooldown.sql' => ['profile_image_changed_at', 'instructor_applications'],
    'database/migrations/007_student_email_verification.sql' => ['email_verified_at', 'email_verification_codes', 'student_password_reset'],
    'apps/web-platform/src/Public/InstructorRegistration/Controller.php' => [
        'getimagesize',
        'private/instructor-profiles',
        'identity_document',
        'old_profile_image',
        'old_identity_document',
    ],
    'apps/web-platform/src/Shared/Ui/RegistrationPage.php' => ['Reapplication rule:', 'same email', 'Pending and approved accounts'],
    'apps/web-platform/src/Public/StudentRegistration/Controller.php' => ['/verify-otp', 'development_code'],
    'apps/web-platform/src/Public/VerifyOtp/Controller.php' => ['verify-student-email', 'reset-password-code', 'resend-student-verification'],
    'apps/web-platform/src/Public/ForgotPassword/Page.php' => ['STUDENT ACCOUNT RECOVERY', 'Student sign in', 'Send six-digit code'],
    'apps/web-platform/src/Instructor/Profile/Controller.php' => ['/api/v1/users/instructor-profile', 'PrivateMedia', 'SecureUpload'],
    'apps/web-platform/src/Instructor/Profile/Page.php' => ['at any time', 'Remove photo', 'profile_photo'],
    'apps/web-platform/src/Admin/InstructorApprovals/Controller.php' => ['PrivateMedia', 'instructor-applications'],
    'apps/web-platform/src/Admin/InstructorApprovals/Page.php' => ['instructor-review-summary', 'Review application', 'instructor-review-body'],
    'apps/web-platform/public/assets/css/portal-fixes.css' => ['overflow-wrap: anywhere', 'instructor-approval-list', 'instructor-review-card[open]'],
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

$instructorProfileService = (string) file_get_contents($root . '/services/identity-service/public/instructor-profile.php');
foreach (['INTERVAL 25 DAY', '$photoLocked', 'photo cooldown', 'changed again on'] as $removedCooldownContract) {
    if (str_contains($instructorProfileService, $removedCooldownContract)) {
        $errors[] = 'Instructor profile photos must remain changeable at any time: ' . $removedCooldownContract;
    }
}

$landingPath = $root . '/apps/web-platform/src/Public/Landing/Page.php';
$landingContent = is_file($landingPath) ? (string) file_get_contents($landingPath) : '';
foreach (['/teach/studio-access', '/register/instructor'] as $forbiddenInstructorLink) {
    if (str_contains($landingContent, $forbiddenInstructorLink)) {
        $errors[] = 'Instructor access must remain separate from the public landing page: ' . $forbiddenInstructorLink;
    }
}

foreach ([
    'apps/web-platform/src/Public/ForgotPassword/Page.php',
    'apps/web-platform/src/Public/ResetPassword/Page.php',
    'apps/web-platform/src/Public/VerifyOtp/Page.php',
] as $studentRecoveryPage) {
    $content = (string) file_get_contents($root . '/' . $studentRecoveryPage);
    if (str_contains($content, '/teach/studio-access') || str_contains($content, '>Instructor<')) {
        $errors[] = 'Student recovery pages must not show Instructor access: ' . $studentRecoveryPage;
    }
}

$approvalPage = (string) file_get_contents($root . '/apps/web-platform/src/Admin/InstructorApprovals/Page.php');
if (str_contains($approvalPage, '<details open')) {
    $errors[] = 'Instructor approval applications must start collapsed.';
}

if ($errors !== []) {
    echo "COURSE LIFECYCLE CHECK: FAIL\n";
    foreach ($errors as $error) {
        echo '- ' . $error . PHP_EOL;
    }
    exit(1);
}

echo "COURSE LIFECYCLE CHECK: PASS\n";
