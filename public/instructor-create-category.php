<?php

declare(strict_types=1);

require_once '../app/middleware/InstructorMiddleware.php';
require_once '../app/config/database.php';

InstructorMiddleware::handle();
Security::requirePost();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function category_json(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function category_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function category_clean_name(mixed $value): string
{
    if (!is_scalar($value) && $value !== null) {
        return '';
    }

    $name = strip_tags((string) $value);
    $name = str_replace("\0", '', $name);
    $name = (string) preg_replace('/[\x{0000}-\x{001F}\x{007F}]/u', '', $name);
    return trim((string) preg_replace('/\s+/u', ' ', $name));
}

function category_slug_base(string $name): string
{
    $candidate = $name;

    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        if (is_string($converted) && $converted !== '') {
            $candidate = $converted;
        }
    }

    $candidate = strtolower($candidate);
    $candidate = (string) preg_replace('/[^a-z0-9]+/', '-', $candidate);
    $candidate = trim($candidate, '-');

    return $candidate !== '' ? $candidate : 'category-' . substr(hash('sha256', $name), 0, 10);
}

function category_unique_slug(mysqli $conn, string $name): string
{
    $base = category_slug_base($name);
    $slug = $base;
    $counter = 2;
    $check = $conn->prepare('SELECT id FROM categories WHERE slug = ? LIMIT 1');

    while (true) {
        $check->bind_param('s', $slug);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;

        if (!$exists) {
            $check->close();
            return $slug;
        }

        $slug = $base . '-' . $counter;
        $counter++;
    }
}

$name = category_clean_name($_POST['name'] ?? '');

if ($name === '' || category_length($name) < 3 || category_length($name) > 100) {
    category_json(422, [
        'ok' => false,
        'message' => 'Category names must contain between 3 and 100 characters.',
    ]);
}

if (!preg_match("/^[\p{L}\p{N}][\p{L}\p{N}\s&+\/'’.,()\-]*$/u", $name)) {
    category_json(422, [
        'ok' => false,
        'message' => 'Use letters, numbers, spaces, and ordinary category punctuation only.',
    ]);
}

$creationTimes = is_array($_SESSION['instructor_category_creation_times'] ?? null)
    ? $_SESSION['instructor_category_creation_times']
    : [];
$cutoff = time() - 3600;
$creationTimes = array_values(array_filter(
    $creationTimes,
    static fn (mixed $timestamp): bool => is_int($timestamp) && $timestamp >= $cutoff
));

if (count($creationTimes) >= 10) {
    category_json(429, [
        'ok' => false,
        'message' => 'Too many new categories were created recently. Reuse an existing category or try again later.',
    ]);
}

$existing = $conn->prepare('SELECT id, name, status FROM categories WHERE name = ? LIMIT 1');
$existing->bind_param('s', $name);
$existing->execute();
$existingCategory = $existing->get_result()->fetch_assoc() ?: null;
$existing->close();

if ($existingCategory) {
    if (($existingCategory['status'] ?? '') !== 'active') {
        category_json(409, [
            'ok' => false,
            'message' => 'That category already exists but is currently inactive. Ask an administrator to reactivate it.',
        ]);
    }

    category_json(200, [
        'ok' => true,
        'created' => false,
        'category' => [
            'id' => (int) $existingCategory['id'],
            'name' => (string) $existingCategory['name'],
        ],
        'message' => 'That category already exists, so it was selected instead of duplicated.',
    ]);
}

try {
    $conn->begin_transaction();
    $slug = category_unique_slug($conn, $name);
    $insert = $conn->prepare("INSERT INTO categories (name, slug, description, status) VALUES (?, ?, NULL, 'active')");
    $insert->bind_param('ss', $name, $slug);
    $insert->execute();
    $categoryId = (int) $conn->insert_id;
    $insert->close();
    $conn->commit();

    $creationTimes[] = time();
    $_SESSION['instructor_category_creation_times'] = $creationTimes;

    category_json(201, [
        'ok' => true,
        'created' => true,
        'category' => [
            'id' => $categoryId,
            'name' => $name,
        ],
        'message' => 'Category created and selected. It is now available in the landing-page category collection.',
    ]);
} catch (Throwable $exception) {
    $conn->rollback();
    error_log('Instructor category creation failed: ' . $exception->getMessage());

    $duplicate = $conn->prepare('SELECT id, name, status FROM categories WHERE name = ? LIMIT 1');
    $duplicate->bind_param('s', $name);
    $duplicate->execute();
    $duplicateCategory = $duplicate->get_result()->fetch_assoc() ?: null;
    $duplicate->close();

    if ($duplicateCategory && ($duplicateCategory['status'] ?? '') === 'active') {
        category_json(200, [
            'ok' => true,
            'created' => false,
            'category' => [
                'id' => (int) $duplicateCategory['id'],
                'name' => (string) $duplicateCategory['name'],
            ],
            'message' => 'The category already existed and has been selected.',
        ]);
    }

    category_json(500, [
        'ok' => false,
        'message' => 'The category could not be created right now.',
    ]);
}