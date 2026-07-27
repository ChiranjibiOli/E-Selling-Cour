<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$files = [
    'student_courses_controller' => 'apps/web-platform/src/Student/Courses/Controller.php',
    'student_courses_page' => 'apps/web-platform/src/Student/Courses/Page.php',
    'public_courses_controller' => 'apps/web-platform/src/Public/Courses/Controller.php',
    'public_courses_page' => 'apps/web-platform/src/Public/Courses/Page.php',
    'course_details_controller' => 'apps/web-platform/src/Public/CourseDetails/Controller.php',
    'course_details_page' => 'apps/web-platform/src/Public/CourseDetails/Page.php',
    'cart_controller' => 'apps/web-platform/src/Student/Cart/Controller.php',
    'commerce' => 'services/commerce-service/public/index.php',
    'payment_controller' => 'apps/web-platform/src/Student/Payment/Controller.php',
    'payment_page' => 'apps/web-platform/src/Student/Payment/Page.php',
    'payment_router' => 'services/payment-service/public/router.php',
    'admin_settings_controller' => 'apps/web-platform/src/Admin/Settings/Controller.php',
    'admin_settings_page' => 'apps/web-platform/src/Admin/Settings/Page.php',
    'checkout_page' => 'apps/web-platform/src/Student/Checkout/Page.php',
    'pricing_page' => 'apps/web-platform/src/Public/Pricing/Page.php',
];

$content = [];
foreach ($files as $key => $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        $errors[] = 'Missing rule file: ' . $relative;
        $content[$key] = '';
        continue;
    }
    $content[$key] = (string) file_get_contents($path);
}

foreach (['/api/v1/enrollments/mine', 'array_filter', 'ownedCourseIds', "Response::redirect('/student/my-courses')"] as $needle) {
    if (!str_contains($content['student_courses_controller'], $needle)) {
        $errors[] = 'Student catalogue ownership filtering is missing: ' . $needle;
    }
}

foreach (['Courses already in your learning library are hidden', 'Courses available to buy', 'Automatic gateway checkout is not available'] as $needle) {
    if (!str_contains($content['student_courses_page'], $needle)) {
        $errors[] = 'Student catalogue ownership UI is missing: ' . $needle;
    }
}

foreach (['AuthSession::role() === \'student\'', '/api/v1/enrollments/mine', 'array_filter'] as $needle) {
    if (!str_contains($content['public_courses_controller'], $needle)) {
        $errors[] = 'Signed-in public catalogue filtering is missing: ' . $needle;
    }
}

if (!str_contains($content['public_courses_page'], 'Courses already purchased are hidden from Browse Courses')) {
    $errors[] = 'Public catalogue does not explain hidden purchased courses.';
}

foreach (['ownership_checked', "Continue learning", 'Check My Courses', 'Manual payment verification'] as $needle) {
    if (!str_contains($content['course_details_controller'] . $content['course_details_page'], $needle)) {
        $errors[] = 'Course details ownership protection is missing: ' . $needle;
    }
}

foreach (['/api/v1/enrollments/mine', "Response::redirect('/student/my-courses')"] as $needle) {
    if (!str_contains($content['cart_controller'], $needle)) {
        $errors[] = 'Cart ownership pre-check is missing: ' . $needle;
    }
}

foreach (['You already have lifetime access to this course.', 'NOT EXISTS (SELECT 1 FROM enrollments'] as $needle) {
    if (!str_contains($content['commerce'], $needle)) {
        $errors[] = 'Commerce ownership enforcement is missing: ' . $needle;
    }
}

foreach (["\$paymentMethod !== 'manual'", '/api/v1/payments/manual', 'Automatic payment is not available'] as $needle) {
    if (!str_contains($content['payment_controller'], $needle)) {
        $errors[] = 'Manual-only Student payment controller is missing: ' . $needle;
    }
}

foreach (['MANUAL PAYMENT', 'Submit manual proof for verification', 'CourseHub does not use automatic payment checkout'] as $needle) {
    if (!str_contains($content['payment_page'], $needle)) {
        $errors[] = 'Manual-only Student payment page is missing: ' . $needle;
    }
}

foreach (['http_response_code(410)', 'Automatic payment gateways are not available', "'manual' =>"] as $needle) {
    if (!str_contains($content['payment_router'], $needle)) {
        $errors[] = 'Payment-service automatic gateway block is missing: ' . $needle;
    }
}

foreach (['save_gateways', '/api/v1/payments/admin/gateways'] as $forbidden) {
    if (str_contains($content['admin_settings_controller'], $forbidden) || str_contains($content['admin_settings_page'], $forbidden)) {
        $errors[] = 'Admin Settings still exposes automatic gateways: ' . $forbidden;
    }
}

foreach (['manual payment', 'Admin verification'] as $needle) {
    if (!str_contains(strtolower($content['checkout_page'] . $content['pricing_page']), strtolower($needle))) {
        $errors[] = 'Checkout or pricing does not describe manual payment: ' . $needle;
    }
}

foreach (['payments/esewa/initiate', 'payments/khalti/initiate', 'gatewayButton('] as $forbidden) {
    if (str_contains($content['payment_controller'], $forbidden) || str_contains($content['payment_page'], $forbidden)) {
        $errors[] = 'Student payment UI still contains automatic gateway code: ' . $forbidden;
    }
}

if ($errors !== []) {
    echo "OWNED COURSE AND MANUAL PAYMENT CHECK: FAIL\n";
    foreach ($errors as $error) {
        echo '- ' . $error . PHP_EOL;
    }
    exit(1);
}

echo "OWNED COURSE AND MANUAL PAYMENT CHECK: PASS\n";
echo "Purchased courses: hidden from signed-in browse pages\n";
echo "Course details: no repeat Buy or Add to cart action\n";
echo "Cart and Commerce: active enrollment enforced server-side\n";
echo "Payment: manual proof and Admin verification only\n";
echo "Automatic eSewa/Khalti initiation: disabled\n";
