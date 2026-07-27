<?php

declare(strict_types=1);

use CourseHub\Identity\Infrastructure\EmailDeliveryException;
use CourseHub\Identity\Infrastructure\SmtpMailer;
use CourseHub\Services\Shared\Database;
use CourseHub\Services\Shared\ServiceAuth;
use CourseHub\Services\Shared\ServiceAuthenticationException;
use CourseHub\Services\Shared\ServiceAuthorizationException;

require_once dirname(__DIR__, 2) . '/_shared/Database.php';
require_once dirname(__DIR__, 2) . '/_shared/ServiceAuth.php';
require_once dirname(__DIR__, 2) . '/identity-service/src/Infrastructure/SmtpMailer.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');

$respond = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
};

$jsonInput = static function (): array {
    $raw = (string) file_get_contents('php://input');
    if ($raw === '') return [];
    $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) throw new InvalidArgumentException('Request body must be a JSON object.');
    return $decoded;
};

$cleanScalar = static function (array $input, string $key, string $label, int $max, bool $required = true): string {
    $value = $input[$key] ?? '';
    if (!is_scalar($value) && $value !== null) throw new InvalidArgumentException($label . ' must be a single text value.');
    $text = trim((string) $value);
    if ($required && $text === '') throw new InvalidArgumentException($label . ' is required.');
    if (mb_strlen($text) > $max || str_contains($text, "\0")) throw new InvalidArgumentException($label . ' is too long or contains invalid characters.');
    return $text;
};

try {
    $database = Database::connect();

    if ($path === '/health' && $method === 'GET') {
        $database->query('SELECT 1');
        $respond(['status' => 'ok', 'service' => 'notification-service']);
    }

    if ($path === '/api/v1/notifications' && $method === 'GET') {
        $user = ServiceAuth::requireUser($database, $authorization);
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 50)));
        $statement = $database->prepare('SELECT id, title, message, notification_type, is_read, created_at, read_at FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit');
        $statement->bindValue('user_id', $user['id'], PDO::PARAM_INT);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll();
        $unread = count(array_filter($rows, static fn (array $row): bool => (int) $row['is_read'] === 0));
        $respond(['data' => $rows, 'meta' => ['unread' => $unread]]);
    }

    if (preg_match('#^/api/v1/notifications/(\d+)/read$#', $path, $matches) === 1 && $method === 'POST') {
        $user = ServiceAuth::requireUser($database, $authorization);
        $statement = $database->prepare('UPDATE notifications SET is_read = 1, read_at = COALESCE(read_at, NOW()) WHERE id = :id AND user_id = :user_id');
        $statement->execute(['id' => (int) $matches[1], 'user_id' => $user['id']]);
        $respond(['message' => 'Notification marked as read.']);
    }

    if ($path === '/api/v1/notifications/read-all' && $method === 'POST') {
        $user = ServiceAuth::requireUser($database, $authorization);
        $statement = $database->prepare('UPDATE notifications SET is_read = 1, read_at = COALESCE(read_at, NOW()) WHERE user_id = :user_id AND is_read = 0');
        $statement->execute(['user_id' => $user['id']]);
        $respond(['message' => 'All notifications marked as read.', 'updated' => $statement->rowCount()]);
    }

    if (preg_match('#^/api/v1/notifications/(\d+)/delete$#', $path, $matches) === 1 && $method === 'POST') {
        $user = ServiceAuth::requireUser($database, $authorization);
        $statement = $database->prepare('DELETE FROM notifications WHERE id = :id AND user_id = :user_id');
        $statement->execute(['id' => (int) $matches[1], 'user_id' => $user['id']]);
        $respond(['message' => $statement->rowCount() > 0 ? 'Notification deleted.' : 'Notification was already removed.']);
    }

    if ($path === '/api/v1/notifications/delete-all' && $method === 'POST') {
        $user = ServiceAuth::requireUser($database, $authorization);
        $statement = $database->prepare('DELETE FROM notifications WHERE user_id = :user_id');
        $statement->execute(['user_id' => $user['id']]);
        $respond(['message' => 'All notifications deleted.', 'deleted' => $statement->rowCount()]);
    }

    if ($path === '/api/v1/notifications/contact' && $method === 'POST') {
        $input = $jsonInput();
        $name = $cleanScalar($input, 'name', 'Name', 100);
        $email = strtolower($cleanScalar($input, 'email', 'Email address', 150));
        $subject = $cleanScalar($input, 'subject', 'Subject', 200, false);
        $message = $cleanScalar($input, 'message', 'Message', 10_000);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) throw new InvalidArgumentException('Enter a valid email address.');
        $statement = $database->prepare('INSERT INTO contact_messages (name, email, subject, message, status) VALUES (:name, :email, :subject, :message, \'new\')');
        $statement->execute(['name' => $name, 'email' => $email, 'subject' => $subject !== '' ? $subject : null, 'message' => $message]);
        $respond(['message' => 'Your message has been sent to CourseHub support.'], 201);
    }

    if ($path === '/api/v1/notifications/contact' && $method === 'GET') {
        ServiceAuth::requireUser($database, $authorization, 'admin');
        $statement = $database->query('SELECT id, name, email, subject, message, status, reply_subject, reply_message, replied_by, replied_at, reply_delivery_status, created_at FROM contact_messages ORDER BY created_at DESC LIMIT 300');
        $respond(['data' => $statement->fetchAll()]);
    }

    if (preg_match('#^/api/v1/notifications/contact/(\d+)/reply$#', $path, $matches) === 1 && $method === 'POST') {
        $admin = ServiceAuth::requireUser($database, $authorization, 'admin');
        $input = $jsonInput();
        $replySubject = $cleanScalar($input, 'reply_subject', 'Reply subject', 200);
        $replyMessage = $cleanScalar($input, 'reply_message', 'Reply message', 10_000);
        $statement = $database->prepare('SELECT id, name, email, subject FROM contact_messages WHERE id=:id LIMIT 1');
        $statement->execute(['id' => (int) $matches[1]]);
        $contact = $statement->fetch();
        if (!is_array($contact)) $respond(['error' => 'The contact message was not found.'], 404);

        try {
            SmtpMailer::sendSupportReply((string) $contact['email'], (string) $contact['name'], (string) ($contact['subject'] ?? 'Support request'), $replySubject, $replyMessage);
            $deliveryStatus = 'sent';
        } catch (EmailDeliveryException $exception) {
            $deliveryStatus = 'failed';
            $saveFailed = $database->prepare('UPDATE contact_messages SET status=\'read\',reply_subject=:reply_subject,reply_message=:reply_message,replied_by=:replied_by,replied_at=NOW(),reply_delivery_status=\'failed\' WHERE id=:id');
            $saveFailed->execute(['reply_subject' => $replySubject, 'reply_message' => $replyMessage, 'replied_by' => (int) $admin['id'], 'id' => (int) $contact['id']]);
            $respond(['error' => 'The reply was saved, but email delivery failed. Check the SMTP configuration and send it again.', 'delivery_status' => $deliveryStatus], 503);
        }

        $update = $database->prepare('UPDATE contact_messages SET status=\'replied\',reply_subject=:reply_subject,reply_message=:reply_message,replied_by=:replied_by,replied_at=NOW(),reply_delivery_status=\'sent\' WHERE id=:id');
        $update->execute(['reply_subject' => $replySubject, 'reply_message' => $replyMessage, 'replied_by' => (int) $admin['id'], 'id' => (int) $contact['id']]);
        $respond(['message' => 'Reply emailed to ' . (string) $contact['email'] . '.', 'delivery_status' => 'sent']);
    }

    if (preg_match('#^/api/v1/notifications/contact/(\d+)$#', $path, $matches) === 1 && $method === 'POST') {
        ServiceAuth::requireUser($database, $authorization, 'admin');
        $input = $jsonInput();
        $status = strtolower($cleanScalar($input, 'status', 'Message status', 20));
        if (!in_array($status, ['new', 'read', 'replied'], true)) throw new InvalidArgumentException('Choose a valid contact-message status.');
        $statement = $database->prepare('UPDATE contact_messages SET status = :status WHERE id = :id');
        $statement->execute(['status' => $status, 'id' => (int) $matches[1]]);
        $respond(['message' => 'Support message updated.']);
    }

    $respond(['error' => 'Notification route not found.'], 404);
} catch (ServiceAuthenticationException $exception) {
    $respond(['error' => $exception->getMessage()], 401);
} catch (ServiceAuthorizationException $exception) {
    $respond(['error' => $exception->getMessage()], 403);
} catch (InvalidArgumentException $exception) {
    $respond(['error' => $exception->getMessage()], 422);
} catch (JsonException) {
    $respond(['error' => 'Malformed JSON request.'], 400);
} catch (PDOException $exception) {
    error_log('Notification database failure: ' . $exception->getMessage());
    $respond(['error' => 'Notification request could not be completed.'], 409);
} catch (Throwable $exception) {
    error_log('Notification service failure: ' . $exception->getMessage());
    $respond(['error' => 'Notification service is unavailable.'], 503);
}
