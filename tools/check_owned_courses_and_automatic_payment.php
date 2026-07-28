<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$files = [
    'rooms' => 'apps/web-platform/src/config/rooms.php',
    'portal' => 'apps/web-platform/src/Shared/Ui/PortalPage.php',
    'enrollment' => 'services/enrollment-service/public/index.php',
    'admin_enrollment_controller' => 'apps/web-platform/src/Admin/Enrollments/Controller.php',
    'admin_enrollment_page' => 'apps/web-platform/src/Admin/Enrollments/Page.php',
    'student_courses' => 'apps/web-platform/src/Student/Courses/Controller.php',
    'public_courses' => 'apps/web-platform/src/Public/Courses/Controller.php',
    'cart' => 'apps/web-platform/src/Student/Cart/Controller.php',
    'commerce' => 'services/commerce-service/public/index.php',
    'payment_controller' => 'apps/web-platform/src/Student/Payment/Controller.php',
    'payment_page' => 'apps/web-platform/src/Student/Payment/Page.php',
    'payment_router' => 'services/payment-service/public/router.php',
    'automatic_payout' => 'services/payment-service/src/AutomaticPayout.php',
    'gateway_settings' => 'services/payment-service/public/gateway-settings.php',
    'admin_settings_controller' => 'apps/web-platform/src/Admin/Settings/Controller.php',
    'admin_settings_page' => 'apps/web-platform/src/Admin/Settings/Page.php',
    'withdrawal_api' => 'services/reporting-service/public/index.php',
    'withdrawal_controller' => 'apps/web-platform/src/Instructor/Withdrawals/Controller.php',
    'withdrawal_page' => 'apps/web-platform/src/Instructor/Withdrawals/Page.php',
    'admin_withdrawal_page' => 'apps/web-platform/src/Admin/Withdrawals/Page.php',
    'instructor_courses_page' => 'apps/web-platform/src/Instructor/MyCourses/Page.php',
    'compose' => 'docker-compose.yml',
    'env' => '.env.example',
    'removal_migration' => 'database/migrations/010_remove_access_removal_requests.sql',
    'permanent_access_migration' => 'database/migrations/011_restore_permanent_paid_enrollments.sql',
    'retry_migration' => 'database/migrations/012_retry_rejected_withdrawals.sql',
    'paid_enrollment_backfill' => 'database/migrations/013_backfill_missing_paid_enrollments.sql',
    'migration_runner' => 'tools/run_migrations.php',
];

$content = [];
foreach ($files as $key => $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        $errors[] = 'Missing workflow file: ' . $relative;
        $content[$key] = '';
        continue;
    }
    $content[$key] = (string) file_get_contents($path);
}

foreach (['Student/Unsubscribe', '/student/unsubscribe', 'Access requests'] as $forbidden) {
    if (str_contains($content['rooms'] . $content['portal'], $forbidden)) {
        $errors[] = 'Purchased-course removal is still exposed: ' . $forbidden;
    }
}
foreach (['unsubscribe/pending', '/unsubscribe$', 'approve|reject'] as $forbidden) {
    if (str_contains($content['enrollment'], $forbidden)) {
        $errors[] = 'Enrollment service still contains the old decision workflow: ' . $forbidden;
    }
}
if (!str_contains($content['enrollment'], 'Purchased-course removal requests are no longer supported.')) {
    $errors[] = 'Enrollment removal shutdown is missing.';
}
if (!str_contains($content['removal_migration'], 'DROP TABLE IF EXISTS unsubscribe_requests')) {
    $errors[] = 'The obsolete unsubscribe table is not removed by migration.';
}
foreach (['010_remove_access_removal_requests', 'database/migrations/010_remove_access_removal_requests.sql'] as $needle) {
    if (!str_contains($content['migration_runner'], $needle)) {
        $errors[] = 'The removal shutdown migration is not registered: ' . $needle;
    }
}
foreach (["e.status = 'active'", "p.payment_status = 'paid'", "o.order_status = 'paid'", 'e.revoked_by_admin = NULL', 'e.revoked_at = NULL'] as $needle) {
    if (!str_contains($content['permanent_access_migration'], $needle)) {
        $errors[] = 'Permanent paid-enrollment repair is incomplete: ' . $needle;
    }
}
foreach (['011_restore_permanent_paid_enrollments', 'database/migrations/011_restore_permanent_paid_enrollments.sql'] as $needle) {
    if (!str_contains($content['migration_runner'], $needle)) {
        $errors[] = 'The paid-enrollment repair migration is not registered: ' . $needle;
    }
}
foreach (['$repairPaidEnrollments', 'INSERT INTO enrollments', "o.order_status=\\'paid\\'", "p.payment_status=\\'paid\\'", "e.status = \\'active\\'"] as $needle) {
    if (!str_contains($content['enrollment'], $needle)) {
        $errors[] = 'Runtime purchased-course repair is incomplete: ' . $needle;
    }
}
foreach (['INSERT INTO enrollments', 'NOT EXISTS', 'UPDATE enrollments e', "e.status = 'revoked'", "e.status = 'active'"] as $needle) {
    if (!str_contains($content['paid_enrollment_backfill'], $needle)) {
        $errors[] = 'Missing paid-enrollment backfill protection: ' . $needle;
    }
}
foreach (['013_backfill_missing_paid_enrollments', 'database/migrations/013_backfill_missing_paid_enrollments.sql'] as $needle) {
    if (!str_contains($content['migration_runner'], $needle)) {
        $errors[] = 'The missing paid-enrollment backfill is not registered: ' . $needle;
    }
}
foreach (['discount_price', '$hasDiscount', '(was NPR '] as $needle) {
    if (!str_contains($content['instructor_courses_page'], $needle)) {
        $errors[] = 'Instructor course cards do not show the discounted price correctly: ' . $needle;
    }
}
foreach (['request_id', 'decision', '/unsubscribe/'] as $forbidden) {
    if (str_contains($content['admin_enrollment_controller'] . $content['admin_enrollment_page'], $forbidden)) {
        $errors[] = 'Admin Enrollments still exposes access-removal decisions: ' . $forbidden;
    }
}

foreach (['/api/v1/enrollments/mine', 'array_filter'] as $needle) {
    if (!str_contains($content['student_courses'] . $content['public_courses'], $needle)) {
        $errors[] = 'Purchased-course browse filtering is missing: ' . $needle;
    }
}
foreach (['/api/v1/enrollments/mine', "Response::redirect('/student/my-courses')"] as $needle) {
    if (!str_contains($content['cart'], $needle)) {
        $errors[] = 'Cart ownership pre-check is missing: ' . $needle;
    }
}
foreach (['You already have lifetime access to this course.', 'NOT EXISTS (SELECT 1 FROM enrollments'] as $needle) {
    if (!str_contains($content['commerce'], $needle)) {
        $errors[] = 'Commerce ownership enforcement is missing: ' . $needle;
    }
}

foreach (['payments/esewa/initiate', 'payments/khalti/initiate', 'payments/esewa/verify', 'payments/khalti/verify'] as $needle) {
    if (!str_contains($content['payment_controller'] . $content['payment_router'], $needle)) {
        $errors[] = 'Automatic Student gateway flow is missing: ' . $needle;
    }
}
foreach (['Pay the platform merchant and verify automatically', "gatewayButton('esewa'", "gatewayButton('khalti'"] as $needle) {
    if (!str_contains($content['payment_page'], $needle)) {
        $errors[] = 'Student automatic-payment UI is missing: ' . $needle;
    }
}
foreach (['save_gateways', '/api/v1/payments/admin/gateways'] as $needle) {
    if (!str_contains($content['admin_settings_controller'] . $content['admin_settings_page'], $needle)) {
        $errors[] = 'Admin gateway control is missing: ' . $needle;
    }
}

foreach (['final class AutomaticPayout', 'Idempotency-Key', 'PAYOUT_API_URL', 'PAYOUT_API_TOKEN', 'withdrawal_request_earnings', "earning_status='paid'", 'transaction_reference'] as $needle) {
    if (!str_contains($content['automatic_payout'], $needle)) {
        $errors[] = 'Automatic Instructor payout adapter is missing: ' . $needle;
    }
}
foreach (['AutomaticPayout::settleForPayment', 'register_shutdown_function'] as $needle) {
    if (!str_contains($content['payment_router'], $needle)) {
        $errors[] = 'Verified payment does not dispatch payout: ' . $needle;
    }
}
foreach (['AUTO_PAYOUT_ENABLED', 'PAYOUT_API_URL', 'PAYOUT_API_TOKEN', 'PAYOUT_HMAC_SECRET', 'PAYOUT_METHOD_PRIORITY'] as $needle) {
    if (!str_contains($content['compose'], $needle) || !str_contains($content['env'], $needle)) {
        $errors[] = 'Payout configuration is missing: ' . $needle;
    }
}
foreach (['esewa', 'khalti', 'available'] as $needle) {
    if (!str_contains($content['gateway_settings'], $needle)) {
        $errors[] = 'Gateway availability configuration is missing: ' . $needle;
    }
}

foreach (['ADD KEY withdrawal_earning_lookup_index', 'DROP INDEX withdrawal_earning_single_request_unique'] as $needle) {
    if (!str_contains($content['retry_migration'], $needle)) {
        $errors[] = 'Rejected-withdrawal retry migration is incomplete: ' . $needle;
    }
}
foreach (['012_retry_rejected_withdrawals', 'database/migrations/012_retry_rejected_withdrawals.sql'] as $needle) {
    if (!str_contains($content['migration_runner'], $needle)) {
        $errors[] = 'Rejected-withdrawal retry migration is not registered: ' . $needle;
    }
}
foreach (['retry_request_id', "request_status'] !== 'rejected'", "earning_status='available'", "request_status IN ('pending','approved','paid')"] as $needle) {
    if (!str_contains($content['withdrawal_api'], $needle)) {
        $errors[] = 'Retry-safe Instructor withdrawal API is missing: ' . $needle;
    }
}
foreach (["action === 'retry'", 'retry_request_id'] as $needle) {
    if (!str_contains($content['withdrawal_controller'], $needle)) {
        $errors[] = 'Instructor withdrawal retry controller is missing: ' . $needle;
    }
}
foreach (['Request again', 'Waiting for transfer or Admin settlement'] as $needle) {
    if (!str_contains($content['withdrawal_page'], $needle)) {
        $errors[] = 'Instructor retry or payout-ready UI is missing: ' . $needle;
    }
}
foreach (['Automatic payout queue', 'Rejecting releases the money back to the Instructor'] as $needle) {
    if (!str_contains($content['admin_withdrawal_page'], $needle)) {
        $errors[] = 'Admin payout queue explanation is missing: ' . $needle;
    }
}

if ($errors !== []) {
    echo "PERMANENT ACCESS AND AUTOMATIC PAYMENT CHECK: FAIL\n";
    foreach ($errors as $error) {
        echo '- ' . $error . PHP_EOL;
    }
    exit(1);
}

echo "PERMANENT ACCESS AND AUTOMATIC PAYMENT CHECK: PASS\n";
echo "Purchased courses: missing or old paid enrollments are restored to active lifetime access\n";
echo "Owned courses: hidden from browse and blocked from repeat cart purchase\n";
echo "Instructor pricing: regular and discounted prices are both visible in My courses\n";
echo "Student checkout: eSewa/Khalti automatic verification with manual fallback\n";
echo "Commission: calculated per verified order item\n";
echo "Instructor payout: idempotent automatic queue plus Admin settlement fallback\n";
echo "Rejected withdrawal: earnings return to available balance and can be requested again\n";
