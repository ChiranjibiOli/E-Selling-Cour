<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';

function db_health_identifier(string $value): string
{
    return preg_replace('/[^A-Za-z0-9_]/', '', $value) ?: '';
}

function db_health_table_exists(mysqli $conn, string $table): bool
{
    $safeTable = db_health_identifier($table);
    if ($safeTable === '') {
        return false;
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->bind_param('s', $safeTable);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['total'] ?? 0) === 1;
}

function db_health_column_exists(mysqli $conn, string $table, string $column): bool
{
    $safeTable = db_health_identifier($table);
    $safeColumn = db_health_identifier($column);
    if ($safeTable === '' || $safeColumn === '') {
        return false;
    }

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?"
    );
    $stmt->bind_param('ss', $safeTable, $safeColumn);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['total'] ?? 0) === 1;
}

$server = $conn->query('SELECT DATABASE() AS database_name, @@port AS server_port, VERSION() AS server_version')->fetch_assoc();
$databaseName = (string) ($server['database_name'] ?? 'unknown');
$serverPort = (int) ($server['server_port'] ?? 0);
$serverVersion = (string) ($server['server_version'] ?? 'unknown');

$requiredSchema = [
    'users' => ['id', 'full_name', 'email', 'password', 'phone', 'bio', 'profile_image', 'identity_document', 'role', 'status', 'last_login_at'],
    'categories' => ['id', 'name', 'slug', 'status', 'is_active'],
    'courses' => ['id', 'instructor_id', 'category_id', 'title', 'slug', 'short_description', 'full_description', 'thumbnail', 'price', 'level', 'language', 'duration', 'status', 'submitted_at', 'reviewed_at', 'reviewed_by', 'review_note'],
    'course_sections' => ['id', 'course_id', 'title', 'sort_order'],
    'course_lessons' => ['id', 'section_id', 'title', 'content_type', 'content_url', 'content_text', 'duration_minutes', 'is_preview', 'sort_order'],
    'course_change_logs' => ['id', 'course_id', 'instructor_id', 'change_type', 'after_snapshot', 'previous_status', 'new_status'],
    'cart' => ['id', 'student_id', 'course_id'],
    'orders' => ['id', 'student_id', 'original_amount', 'discount_amount', 'final_amount', 'order_status'],
    'order_items' => ['id', 'order_id', 'course_id', 'instructor_id', 'course_price', 'discount_amount', 'final_price'],
    'payments' => ['id', 'order_id', 'student_id', 'payment_method', 'payment_type', 'transaction_id', 'paid_amount', 'payment_status'],
    'payment_proofs' => ['id', 'payment_id', 'proof_image', 'note'],
    'enrollments' => ['id', 'student_id', 'course_id', 'order_id', 'payment_id', 'access_type', 'status'],
    'reviews' => ['id', 'course_id', 'student_id', 'rating', 'review_text', 'status'],
    'notifications' => ['id', 'user_id', 'title', 'message', 'notification_type', 'is_read', 'read_at'],
    'site_settings' => ['id', 'setting_key', 'setting_value'],
    'instructor_bank_details' => ['id', 'instructor_id', 'bank_name', 'account_name', 'account_number', 'esewa_number', 'khalti_number', 'qr_image'],
    'instructor_earnings' => ['id', 'instructor_id', 'course_id', 'student_id', 'order_id', 'order_item_id', 'payment_id', 'gross_amount', 'commission_rate', 'commission_amount', 'instructor_amount', 'earning_status'],
    'withdrawal_requests' => ['id', 'instructor_id', 'requested_amount', 'payment_method', 'request_status', 'processed_by', 'processed_at'],
    'withdrawal_request_earnings' => ['id', 'withdrawal_request_id', 'earning_id'],
    'payouts' => ['id', 'payout_source', 'instructor_id', 'paid_amount', 'payment_method', 'transaction_reference', 'payout_status', 'paid_by'],
];

$missingTables = [];
$missingColumns = [];

foreach ($requiredSchema as $table => $columns) {
    if (!db_health_table_exists($conn, $table)) {
        $missingTables[] = $table;
        continue;
    }

    foreach ($columns as $column) {
        if (!db_health_column_exists($conn, $table, $column)) {
            $missingColumns[] = $table . '.' . $column;
        }
    }
}

$healthy = $serverPort === 3307 && $missingTables === [] && $missingColumns === [];

echo PHP_EOL;
echo 'CourseHub database health' . PHP_EOL;
echo str_repeat('=', 28) . PHP_EOL;
echo 'Host: ' . (defined('DB_HOST_NAME') ? DB_HOST_NAME : 'unknown') . PHP_EOL;
echo 'Port: ' . $serverPort . ($serverPort === 3307 ? ' [OK]' : ' [EXPECTED 3307]') . PHP_EOL;
echo 'Database: ' . $databaseName . PHP_EOL;
echo 'Server: ' . $serverVersion . PHP_EOL;
echo PHP_EOL;

if ($missingTables !== []) {
    echo 'Missing tables:' . PHP_EOL;
    foreach ($missingTables as $table) {
        echo '  - ' . $table . PHP_EOL;
    }
    echo PHP_EOL;
}

if ($missingColumns !== []) {
    echo 'Missing columns:' . PHP_EOL;
    foreach ($missingColumns as $column) {
        echo '  - ' . $column . PHP_EOL;
    }
    echo PHP_EOL;
}

if ($healthy) {
    echo 'RESULT: PASS - connection and required schema are ready.' . PHP_EOL;
    exit(0);
}

echo 'RESULT: FAIL - apply database/migrations/20260712_course_selling_compatibility.sql and run this command again.' . PHP_EOL;
exit(1);
