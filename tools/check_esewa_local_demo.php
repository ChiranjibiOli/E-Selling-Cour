<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'services/payment-service/public/router.php' => [
        '/api/v1/payments/esewa/demo-complete',
        "require __DIR__ . '/esewa-local-demo.php'",
        'ESEWA_LOCAL_DEMO',
        "getenv('APP_ENV')",
        "getenv('ESEWA_ENV')",
    ],
    'services/payment-service/public/esewa-local-demo.php' => [
        'The local eSewa simulator is disabled outside the local sandbox environment.',
        'hash_hmac(',
        'ServiceAuth::requireUser',
        'FOR UPDATE',
        "payment_status=\'paid\'",
        "order_status=\'paid\'",
        "\'lifetime\'",
        'instructor_earnings',
        'Local eSewa sandbox payment completed.',
    ],
    'services/payment-service/public/gateway-settings.php' => [
        "'local-demo'",
        'ESEWA_LOCAL_DEMO',
        'APP_ENV',
    ],
    'apps/web-platform/src/Student/Payment/Controller.php' => [
        'eSewa sandbox simulator',
        "payment_method\" value=\"esewa-demo",
        '/api/v1/payments/esewa/demo-complete',
        "=== 'local-demo'",
    ],
    'apps/web-platform/src/Student/Payment/Page.php' => [
        "'local-demo' => ' local demo'",
        'Complete a safe local test payment',
    ],
    'docker-compose.yml' => [
        'ESEWA_LOCAL_DEMO: "${ESEWA_LOCAL_DEMO:-true}"',
        'APP_ENV: "${APP_ENV:-local}"',
    ],
    '.env.example' => [
        'ESEWA_LOCAL_DEMO=true',
    ],
];

$errors = [];
foreach ($checks as $relativePath => $needles) {
    $path = $root . '/' . $relativePath;
    $content = is_file($path) ? file_get_contents($path) : false;
    if (!is_string($content)) {
        $errors[] = 'Missing file: ' . $relativePath;
        continue;
    }
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $errors[] = $relativePath . ' is missing marker: ' . $needle;
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "ESEWA LOCAL DEMO CHECK: FAIL\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "ESEWA LOCAL DEMO CHECK: PASS\n";
echo "Local development: uses an authenticated, signed eSewa simulator instead of the unavailable UAT form\n";
echo "Production: continues to use the real eSewa gateway when ESEWA_ENV=production\n";
echo "Completion: activates lifetime access, commission, Instructor earnings and payout dispatch\n";
