<?php

declare(strict_types=1);

use CourseHub\Identity\Features\Login\LoginAuthenticationException;
use CourseHub\Identity\Features\Login\LoginHandler;
use CourseHub\Identity\Features\Login\LoginRateLimitException;
use CourseHub\Identity\Features\Login\LoginRateLimiter;
use CourseHub\Identity\Features\Login\LoginValidationException;
use CourseHub\Identity\Features\Session\SessionAuthenticationException;
use CourseHub\Identity\Features\Session\SessionHandler;
use CourseHub\Identity\Infrastructure\Database;

require_once dirname(__DIR__) . '/src/Infrastructure/Database.php';
require_once dirname(__DIR__) . '/src/Features/Login/LoginRateLimiter.php';
require_once dirname(__DIR__) . '/src/Features/Login/LoginHandler.php';
require_once dirname(__DIR__) . '/src/Features/Session/SessionHandler.php';

header('Content-Type: application/json; charset=utf-8');
$requestId = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
if ($requestId === '' || preg_match('/^[A-Za-z0-9._-]{8,100}$/', $requestId) !== 1) {
    $requestId = bin2hex(random_bytes(16));
}
header('X-Request-ID: ' . $requestId);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

$respond = static function (array $payload, int $status = 200) use ($requestId): never {
    http_response_code($status);
    echo json_encode($payload + ['request_id' => $requestId], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
};

$jsonInput = static function (): array {
    $decoded = json_decode((string) file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new LoginValidationException('Request body must be a JSON object.');
    }
    return $decoded;
};

try {
    $database = Database::connect();

    if ($path === '/health' && $method === 'GET') {
        $database->query('SELECT 1');
        $respond(['status' => 'ok', 'service' => 'identity-service']);
    }

    if (preg_match('#^/api/v1/auth/register/(student|instructor)$#', $path, $matches) === 1 && $method === 'POST') {
        $input = $jsonInput();
        $role = $matches[1];
        $fullName = trim((string) ($input['full_name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $phone = trim((string) ($input['phone'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $passwordConfirmation = (string) ($input['password_confirmation'] ?? '');
        $bio = trim((string) ($input['bio'] ?? ''));

        if ($fullName === '' || mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
            throw new LoginValidationException('Enter your full name.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            throw new LoginValidationException('Enter a valid email address.');
        }
        if ($phone !== '' && (preg_match('/^[0-9+() -]{7,20}$/', $phone) !== 1)) {
            throw new LoginValidationException('Enter a valid phone number.');
        }
        if (strlen($password) < 8 || strlen($password) > 200 || !hash_equals($password, $passwordConfirmation)) {
            throw new LoginValidationException('Use a matching password with at least 8 characters.');
        }
        if (mb_strlen($bio) > 3000 || ($role === 'instructor' && mb_strlen($bio) < 40)) {
            throw new LoginValidationException($role === 'instructor' ? 'Instructor biography must contain at least 40 characters.' : 'Biography is too long.');
        }

        $status = $role === 'student' ? 'active' : 'inactive';
        $statement = $database->prepare('INSERT INTO users (full_name, email, password, phone, role, bio, status) VALUES (:full_name, :email, :password, :phone, :role, :bio, :status)');
        try {
            $database->beginTransaction();
            $statement->execute([
                'full_name' => $fullName,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'phone' => $phone !== '' ? $phone : null,
                'role' => $role,
                'bio' => $bio !== '' ? $bio : null,
                'status' => $status,
            ]);
            $userId = (int) $database->lastInsertId();
            if ($role === 'instructor') {
                $application = $database->prepare('INSERT INTO instructor_applications (instructor_id, application_note) VALUES (:instructor_id, :application_note)');
                $application->execute(['instructor_id' => $userId, 'application_note' => $bio]);
            }
            $database->commit();
        } catch (PDOException $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            if ($exception->getCode() === '23000') {
                throw new LoginValidationException('An account with that email already exists.');
            }
            throw $exception;
        }

        $respond([
            'message' => $role === 'student'
                ? 'Student account created. You can sign in now.'
                : 'Instructor application submitted for administrator approval.',
            'status' => $status,
        ], 201);
    }

    if ($path === '/api/v1/auth/login' && $method === 'POST') {
        $rawBody = file_get_contents('php://input');
        $input = json_decode($rawBody ?: '{}', true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($input)) {
            throw new LoginValidationException('Request body must be a JSON object.');
        }

        $handler = new LoginHandler($database, new LoginRateLimiter($database));
        $result = $handler->handle(
            $input,
            (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 500)
        );

        http_response_code(200);
        echo json_encode($result + ['request_id' => $requestId], JSON_THROW_ON_ERROR);
        exit;
    }

    $authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $sessionHandler = new SessionHandler($database);

    if ($path === '/api/v1/users/instructor-applications' && $method === 'GET') {
        $session = $sessionHandler->verify($authorization);
        if (($session['user']['role'] ?? '') !== 'admin') {
            throw new SessionAuthenticationException('Administrator access is required.');
        }
        $statement = $database->query(
            'SELECT u.id, u.full_name, u.email, u.phone, u.bio, u.profile_image, u.identity_document, a.created_at '
            . 'FROM instructor_applications a INNER JOIN users u ON u.id=a.instructor_id '
            . 'WHERE a.application_status=\'pending\' AND u.role=\'instructor\' AND u.status=\'inactive\' ORDER BY a.created_at ASC'
        );
        $respond(['data' => $statement->fetchAll()]);
    }

    if (preg_match('#^/api/v1/users/instructor-applications/(\d+)/(approve|reject)$#', $path, $matches) === 1 && $method === 'POST') {
        $session = $sessionHandler->verify($authorization);
        if (($session['user']['role'] ?? '') !== 'admin') {
            throw new SessionAuthenticationException('Administrator access is required.');
        }
        $input = $jsonInput();
        $action = $matches[2];
        $note = trim((string) ($input['note'] ?? ''));
        if (mb_strlen($note) > 1000) {
            throw new LoginValidationException('Decision notes must be 1000 characters or fewer.');
        }
        if ($action === 'reject' && $note === '') {
            throw new LoginValidationException('A rejection reason is required.');
        }

        $database->beginTransaction();
        $application = $database->prepare(
            'UPDATE instructor_applications SET application_status=:application_status, review_note=:review_note, reviewed_by=:reviewed_by, reviewed_at=NOW() '
            . 'WHERE instructor_id=:id AND application_status=\'pending\''
        );
        $application->execute([
            'application_status' => $action === 'approve' ? 'approved' : 'rejected',
            'review_note' => $note !== '' ? $note : null,
            'reviewed_by' => (int) ($session['user']['id'] ?? 0),
            'id' => (int) $matches[1],
        ]);
        if ($application->rowCount() !== 1) {
            $database->rollBack();
            throw new LoginValidationException('The instructor application is no longer pending.');
        }
        $statement = $database->prepare('UPDATE users SET status=:status WHERE id=:id AND role=\'instructor\' AND status=\'inactive\'');
        $statement->execute(['status' => $action === 'approve' ? 'active' : 'blocked', 'id' => (int) $matches[1]]);
        if ($statement->rowCount() !== 1) {
            $database->rollBack();
            throw new LoginValidationException('The instructor account state changed during review.');
        }
        $notification = $database->prepare('INSERT INTO notifications (user_id, title, message, notification_type) VALUES (:user_id, :title, :message, \'instructor_application\')');
        $notification->execute([
            'user_id' => (int) $matches[1],
            'title' => $action === 'approve' ? 'Instructor application approved' : 'Instructor application reviewed',
            'message' => $action === 'approve'
                ? 'Your instructor studio is active. You can sign in and begin creating courses.'
                : 'Your instructor application was not approved. Review note: ' . $note,
        ]);
        $database->commit();
        $respond(['message' => $action === 'approve' ? 'Instructor approved.' : 'Instructor application rejected.']);
    }

    if ($path === '/api/v1/auth/session' && $method === 'GET') {
        $result = $sessionHandler->verify($authorization);
        http_response_code(200);
        echo json_encode($result + ['request_id' => $requestId], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($path === '/api/v1/auth/logout' && $method === 'POST') {
        $sessionHandler->logout($authorization);
        http_response_code(200);
        echo json_encode(['message' => 'Logged out.', 'request_id' => $requestId], JSON_THROW_ON_ERROR);
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Identity route not found.', 'request_id' => $requestId], JSON_THROW_ON_ERROR);
} catch (LoginRateLimitException $exception) {
    http_response_code(429);
    header('Retry-After: 900');
    echo json_encode(['error' => $exception->getMessage(), 'request_id' => $requestId], JSON_THROW_ON_ERROR);
} catch (LoginValidationException $exception) {
    http_response_code(422);
    echo json_encode(['error' => $exception->getMessage(), 'request_id' => $requestId], JSON_THROW_ON_ERROR);
} catch (LoginAuthenticationException|SessionAuthenticationException $exception) {
    http_response_code(401);
    echo json_encode(['error' => $exception->getMessage(), 'request_id' => $requestId], JSON_THROW_ON_ERROR);
} catch (JsonException) {
    http_response_code(400);
    echo json_encode(['error' => 'Malformed JSON request.', 'request_id' => $requestId], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Identity service failure [' . $requestId . ']: ' . $exception->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'Identity service is unavailable.', 'request_id' => $requestId], JSON_THROW_ON_ERROR);
}
