<?php

declare(strict_types=1);

use CourseHub\Identity\Features\Session\SessionAuthenticationException;
use CourseHub\Identity\Features\Session\SessionHandler;
use CourseHub\Identity\Infrastructure\Database;
use CourseHub\Identity\Infrastructure\EmailDeliveryException;
use CourseHub\Identity\Infrastructure\SmtpMailer;

require_once dirname(__DIR__) . '/src/Infrastructure/Database.php';
require_once dirname(__DIR__) . '/src/Infrastructure/SmtpMailer.php';
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

$rollback = static function (?PDO $database): void {
    if ($database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
};

$database = null;
try {
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    if (preg_match('#^/api/v1/users/instructor-applications/(\d+)/(approve|reject)$#', $path, $matches) !== 1) {
        $respond(['error' => 'Instructor decision route not found.'], 404);
    }

    $raw = (string) file_get_contents('php://input');
    $input = json_decode($raw !== '' ? $raw : '{}', true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($input)) {
        throw new InvalidArgumentException('Request body must be a JSON object.');
    }

    $instructorId = (int) $matches[1];
    $action = (string) $matches[2];
    $note = trim((string) ($input['note'] ?? ''));
    if (mb_strlen($note) > 1000) {
        throw new InvalidArgumentException('Decision notes must be 1000 characters or fewer.');
    }
    if ($action === 'reject' && $note === '') {
        throw new InvalidArgumentException('A rejection reason is required and will be sent to the Instructor by email.');
    }

    $database = Database::connect();
    $session = (new SessionHandler($database))->verify((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (($session['user']['role'] ?? '') !== 'admin') {
        throw new SessionAuthenticationException('Administrator access is required.');
    }

    $database->beginTransaction();
    $applicantStatement = $database->prepare(
        'SELECT u.id, u.full_name, u.email, u.status, a.application_status '
        . 'FROM users u INNER JOIN instructor_applications a ON a.instructor_id=u.id '
        . 'WHERE u.id=:id AND u.role=\'instructor\' LIMIT 1 FOR UPDATE'
    );
    $applicantStatement->execute(['id' => $instructorId]);
    $applicant = $applicantStatement->fetch();
    if (!is_array($applicant)
        || (string) ($applicant['status'] ?? '') !== 'inactive'
        || (string) ($applicant['application_status'] ?? '') !== 'pending'
    ) {
        throw new DomainException('The Instructor application is no longer awaiting review.');
    }

    $application = $database->prepare(
        'UPDATE instructor_applications SET application_status=:application_status, review_note=:review_note, '
        . 'reviewed_by=:reviewed_by, reviewed_at=NOW() '
        . 'WHERE instructor_id=:id AND application_status=\'pending\''
    );
    $application->execute([
        'application_status' => $action === 'approve' ? 'approved' : 'rejected',
        'review_note' => $note !== '' ? $note : null,
        'reviewed_by' => (int) ($session['user']['id'] ?? 0),
        'id' => $instructorId,
    ]);
    if ($application->rowCount() !== 1) {
        throw new DomainException('The Instructor application changed while the decision was being saved.');
    }

    $userUpdate = $database->prepare(
        'UPDATE users SET status=:status, '
        . 'profile_image_changed_at=CASE WHEN :approved=1 THEN COALESCE(profile_image_changed_at, NOW()) ELSE profile_image_changed_at END '
        . 'WHERE id=:id AND role=\'instructor\' AND status=\'inactive\''
    );
    $userUpdate->execute([
        'status' => $action === 'approve' ? 'active' : 'blocked',
        'approved' => $action === 'approve' ? 1 : 0,
        'id' => $instructorId,
    ]);
    if ($userUpdate->rowCount() !== 1) {
        throw new DomainException('The Instructor account changed while the decision was being saved.');
    }

    $notification = $database->prepare(
        'INSERT INTO notifications (user_id, title, message, notification_type) '
        . 'VALUES (:user_id, :title, :message, \'instructor_application\')'
    );
    $notification->execute([
        'user_id' => $instructorId,
        'title' => $action === 'approve' ? 'Instructor application approved' : 'Instructor application rejected',
        'message' => $action === 'approve'
            ? 'Your Instructor account is active. The accepted passport-size photo is now your profile photo and can be changed after 25 days.'
            : 'Your Instructor application was not approved. Reason: ' . $note . ' You may correct it and reapply using the same email address.',
    ]);

    $database->commit();

    if ($action === 'approve') {
        $respond([
            'message' => 'Instructor approved with the submitted profile photo.',
            'email_sent' => false,
        ]);
    }

    $email = (string) ($applicant['email'] ?? '');
    $name = (string) ($applicant['full_name'] ?? 'Instructor applicant');
    $emailSent = false;
    $warning = '';
    try {
        SmtpMailer::sendInstructorRejection($email, $name, $note);
        $emailSent = true;
    } catch (EmailDeliveryException $exception) {
        $warning = 'The rejection was saved, but the email could not be sent. Check SMTP settings and the identity-service logs.';
        error_log('Instructor rejection email failure [' . $requestId . ']: ' . $exception->getMessage());
    }

    $respond([
        'message' => $emailSent
            ? 'Instructor application rejected. The reason was emailed to ' . $email . '. The Instructor may reapply with the same email.'
            : 'Instructor application rejected. ' . $warning,
        'email_sent' => $emailSent,
        'warning' => $warning,
    ]);
} catch (InvalidArgumentException $exception) {
    $rollback($database);
    $respond(['error' => $exception->getMessage()], 422);
} catch (DomainException $exception) {
    $rollback($database);
    $respond(['error' => $exception->getMessage()], 409);
} catch (SessionAuthenticationException $exception) {
    $rollback($database);
    $respond(['error' => $exception->getMessage()], 401);
} catch (JsonException) {
    $rollback($database);
    $respond(['error' => 'Malformed JSON request.'], 400);
} catch (PDOException $exception) {
    $rollback($database);
    error_log('Instructor decision database failure [' . $requestId . ']: ' . $exception->getMessage());
    $respond(['error' => 'The Instructor decision could not be saved.'], 409);
} catch (Throwable $exception) {
    $rollback($database);
    error_log('Instructor decision failure [' . $requestId . ']: ' . $exception->getMessage());
    $respond(['error' => 'The Instructor decision service is unavailable.'], 503);
}
