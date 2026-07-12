<?php

declare(strict_types=1);

require_once __DIR__ . '/../middleware/AdminMiddleware.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/notification_helper.php';

AdminMiddleware::handle();
Security::requirePost();

$instructorId = (int) ($_POST['instructor_id'] ?? 0);
$action = isset($_POST['approve_instructor'])
    ? 'approve'
    : (isset($_POST['block_instructor']) ? 'block' : '');

function admin_instructor_action_fail(string $message, int $status = 400): never
{
    http_response_code($status);
    $safe = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Instructor action failed</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#eee6d9;color:#171511;font-family:Arial,sans-serif}.box{width:min(620px,100%);padding:28px;border:1px solid rgba(72,58,39,.2);border-radius:22px;background:#fffdf8;box-shadow:0 18px 48px rgba(39,31,21,.1)}h1{font-family:Georgia,serif;font-weight:500}p{line-height:1.65;color:#5c5145}a{display:inline-flex;margin-top:12px;padding:11px 16px;border-radius:999px;color:#fff;background:#171511;text-decoration:none}</style></head>';
    echo '<body><main class="box"><h1>Action not completed</h1><p>' . $safe . '</p><a href="admin-instructors.php">Return to instructors</a></main></body></html>';
    exit;
}

if ($instructorId <= 0 || !in_array($action, ['approve', 'block'], true)) {
    admin_instructor_action_fail('Invalid instructor-management request.');
}

$transactionStarted = false;

try {
    $conn->begin_transaction();
    $transactionStarted = true;

    $stmt = $conn->prepare("
        SELECT id, full_name, status, profile_image, identity_document
        FROM users
        WHERE id = ? AND role = 'instructor'
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->bind_param('i', $instructorId);
    $stmt->execute();
    $instructor = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    if (!$instructor) {
        throw new DomainException('Instructor account was not found.');
    }

    $currentStatus = (string) $instructor['status'];

    if ($action === 'approve') {
        if (!in_array($currentStatus, ['inactive', 'pending', 'blocked'], true)) {
            throw new DomainException('This instructor is already active or has an unsupported account state.');
        }

        $profilePath = Security::resolveStoredFile((string) ($instructor['profile_image'] ?? ''), [
            STORAGE_PATH . DIRECTORY_SEPARATOR . 'private_uploads' . DIRECTORY_SEPARATOR . 'profile_photos',
            PUBLIC_PATH . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profile_photos',
            PUBLIC_PATH . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles',
        ]);
        $identityPath = Security::resolveStoredFile((string) ($instructor['identity_document'] ?? ''), [
            STORAGE_PATH . DIRECTORY_SEPARATOR . 'private_uploads' . DIRECTORY_SEPARATOR . 'instructor_documents',
            PUBLIC_PATH . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'instructor_documents',
            PUBLIC_PATH . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'identity_documents',
        ]);

        if ($profilePath === null || !in_array(Security::detectMimeType($profilePath), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new DomainException('A valid instructor profile photo is required before approval.');
        }

        if ($identityPath === null || !in_array(Security::detectMimeType($identityPath), ['image/jpeg', 'image/png', 'application/pdf'], true)) {
            throw new DomainException('A valid identity document is required before approval.');
        }

        $update = $conn->prepare("
            UPDATE users
            SET status = 'active'
            WHERE id = ? AND role = 'instructor' AND status IN ('inactive', 'pending', 'blocked')
        ");
        $update->bind_param('i', $instructorId);
        $update->execute();
        if ($update->affected_rows !== 1) {
            throw new RuntimeException('Instructor state changed during approval.');
        }
        $update->close();

        send_notification(
            $conn,
            $instructorId,
            'Instructor account approved',
            'Your instructor account has been approved. You can now create and submit courses for review.',
            'account'
        );
    } else {
        if ($currentStatus === 'blocked') {
            throw new DomainException('This instructor is already blocked.');
        }

        $update = $conn->prepare("
            UPDATE users
            SET status = 'blocked'
            WHERE id = ? AND role = 'instructor' AND status <> 'blocked'
        ");
        $update->bind_param('i', $instructorId);
        $update->execute();
        if ($update->affected_rows !== 1) {
            throw new RuntimeException('Instructor state changed during blocking.');
        }
        $update->close();

        send_notification(
            $conn,
            $instructorId,
            'Instructor account blocked',
            'Your instructor account has been blocked. Contact the platform administrator if you believe this is an error.',
            'account'
        );
    }

    $conn->commit();
    $transactionStarted = false;
    Auth::redirect('admin-instructors.php?' . ($action === 'approve' ? 'approved=1' : 'blocked=1'));
} catch (DomainException $exception) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    admin_instructor_action_fail($exception->getMessage(), 409);
} catch (Throwable $exception) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    error_log('Admin instructor action failed: ' . $exception->getMessage());
    admin_instructor_action_fail('The instructor account could not be updated. No account state was changed.', 500);
}
