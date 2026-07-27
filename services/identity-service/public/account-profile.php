<?php

declare(strict_types=1);

use CourseHub\Identity\Features\Session\SessionAuthenticationException;
use CourseHub\Identity\Features\Session\SessionHandler;
use CourseHub\Identity\Infrastructure\Database;

require_once dirname(__DIR__) . '/src/Infrastructure/Database.php';
require_once dirname(__DIR__) . '/src/Features/Session/SessionHandler.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$requestId = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
if ($requestId === '' || preg_match('/^[A-Za-z0-9._-]{8,100}$/', $requestId) !== 1) {
    $requestId = bin2hex(random_bytes(16));
}
header('X-Request-ID: ' . $requestId);

$respond = static function (array $payload, int $status = 200) use ($requestId): never {
    http_response_code($status);
    echo json_encode($payload + ['request_id' => $requestId], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
};

$jsonInput = static function (): array {
    $raw = (string) file_get_contents('php://input');
    $decoded = json_decode($raw !== '' ? $raw : '{}', true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Request body must be a JSON object.');
    }
    return $decoded;
};

$profile = static function (PDO $database, int $userId, string $role): array {
    $statement = $database->prepare(
        'SELECT id,full_name,email,phone,role,status,profile_image,profile_image_changed_at,created_at,last_login_at '
        . 'FROM users WHERE id=:id AND role=:role AND status=\'active\' LIMIT 1'
    );
    $statement->execute(['id' => $userId, 'role' => $role]);
    $record = $statement->fetch();
    if (!is_array($record)) {
        throw new SessionAuthenticationException('The account profile is unavailable.');
    }
    return $record;
};

$database = null;
try {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'POST'], true)) {
        $respond(['error' => 'Method not allowed.'], 405);
    }

    $database = Database::connect();
    $authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $session = (new SessionHandler($database))->verify($authorization);
    $userId = (int) ($session['user']['id'] ?? 0);
    $role = (string) ($session['user']['role'] ?? '');

    if ($userId < 1 || !in_array($role, ['student', 'admin'], true)) {
        throw new SessionAuthenticationException('Student or Administrator profile access is required.');
    }

    if ($method === 'GET') {
        $respond(['data' => $profile($database, $userId, $role)]);
    }

    $input = $jsonInput();
    $action = strtolower(trim((string) ($input['action'] ?? '')));
    if (!in_array($action, ['change_photo', 'remove_photo'], true)) {
        throw new InvalidArgumentException('Choose a valid profile-photo action.');
    }

    $database->beginTransaction();
    $currentStatement = $database->prepare(
        'SELECT profile_image FROM users WHERE id=:id AND role=:role AND status=\'active\' LIMIT 1 FOR UPDATE'
    );
    $currentStatement->execute(['id' => $userId, 'role' => $role]);
    $current = $currentStatement->fetch();
    if (!is_array($current)) {
        $database->rollBack();
        throw new SessionAuthenticationException('The account profile is unavailable.');
    }

    $oldProfileImage = trim((string) ($current['profile_image'] ?? ''));
    if ($action === 'change_photo') {
        $newProfileImage = trim((string) ($input['profile_image'] ?? ''));
        if (preg_match('#^private/profile-photos/[a-f0-9]{40}\.(?:jpg|png|webp)$#', $newProfileImage) !== 1) {
            $database->rollBack();
            throw new InvalidArgumentException('A valid JPG, PNG, or WebP profile photo is required.');
        }
        $update = $database->prepare(
            'UPDATE users SET profile_image=:profile_image,profile_image_changed_at=NOW() '
            . 'WHERE id=:id AND role=:role AND status=\'active\''
        );
        $update->execute(['profile_image' => $newProfileImage, 'id' => $userId, 'role' => $role]);
        $message = 'Profile photo changed.';
    } else {
        $update = $database->prepare(
            'UPDATE users SET profile_image=NULL,profile_image_changed_at=NOW() '
            . 'WHERE id=:id AND role=:role AND status=\'active\''
        );
        $update->execute(['id' => $userId, 'role' => $role]);
        $message = $oldProfileImage !== '' ? 'Profile photo removed.' : 'No profile photo was stored.';
    }

    $database->commit();
    $respond([
        'message' => $message,
        'old_profile_image' => $oldProfileImage,
        'data' => $profile($database, $userId, $role),
    ]);
} catch (SessionAuthenticationException $exception) {
    if ($database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    $respond(['error' => $exception->getMessage()], 401);
} catch (InvalidArgumentException $exception) {
    if ($database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    $respond(['error' => $exception->getMessage()], 422);
} catch (JsonException) {
    if ($database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    $respond(['error' => 'Malformed JSON request.'], 400);
} catch (Throwable $exception) {
    if ($database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Account profile failure [' . $requestId . ']: ' . $exception->getMessage());
    $respond(['error' => 'The account profile service is unavailable.'], 503);
}
