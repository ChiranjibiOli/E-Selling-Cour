<?php

declare(strict_types=1);

require_once __DIR__ . '/../middleware/AdminMiddleware.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/notification_helper.php';
require_once __DIR__ . '/../helpers/security_helper.php';

/** @var mysqli $conn */
$conn = database_connection();

AdminMiddleware::handle();
Security::requirePost();

$admin = Auth::user();
$adminId = (int) ($admin['id'] ?? 0);
$orderId = (int) ($_POST['order_id'] ?? 0);
$paymentId = (int) ($_POST['payment_id'] ?? 0);

function admin_order_action_fail(string $message, int $orderId, int $status = 400): never
{
    http_response_code($status);
    $safeMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeBack = 'admin-order-details.php?order_id=' . max(0, $orderId);

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Order action could not be completed</title>';
    echo '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#eee6d9;color:#171511;font-family:Arial,sans-serif}.box{width:min(620px,100%);padding:28px;border:1px solid rgba(72,58,39,.2);border-radius:22px;background:#fffdf8;box-shadow:0 18px 48px rgba(39,31,21,.1)}h1{margin:0 0 10px;font-family:Georgia,serif;font-weight:500}p{line-height:1.65;color:#5c5145}a{display:inline-flex;margin-top:12px;padding:11px 16px;border-radius:999px;color:#fff;text-decoration:none;background:#171511}</style>';
    echo '</head><body><main class="box"><h1>Action not completed</h1><p>' . $safeMessage . '</p>';
    echo '<a href="' . htmlspecialchars($safeBack, ENT_QUOTES, 'UTF-8') . '">Return to order</a></main></body></html>';
    exit;
}

function admin_order_action_redirect(int $orderId, string $flag): never
{
    Auth::redirect('admin-order-details.php?order_id=' . $orderId . '&' . rawurlencode($flag) . '=1');
}

function admin_order_locked_header(mysqli $conn, int $orderId, int $paymentId): ?array
{
    $stmt = $conn->prepare("
        SELECT o.id AS order_id, o.student_id, o.final_amount, o.order_status,
               p.id AS payment_id, p.paid_amount, p.payment_status
        FROM orders o
        INNER JOIN payments p ON p.order_id = o.id
        WHERE o.id = ? AND p.id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->bind_param('ii', $orderId, $paymentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function admin_order_commission_rate(mysqli $conn): float
{
    $rate = 20.0;
    $stmt = $conn->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'admin_commission_rate' LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        if ($row !== null && is_numeric((string) $row['setting_value'])) {
            $rate = (float) $row['setting_value'];
        }
    }

    if (!is_finite($rate) || $rate < 0 || $rate > 100) {
        throw new DomainException('The configured commission rate is invalid. Correct it before approving this payment.');
    }

    return round($rate, 2);
}

function admin_order_verify_payment(
    mysqli $conn,
    int $adminId,
    int $orderId,
    int $paymentId
): void {
    $header = admin_order_locked_header($conn, $orderId, $paymentId);
    if (!$header) {
        throw new DomainException('Order or payment was not found.');
    }

    if ($header['order_status'] !== 'pending' || $header['payment_status'] !== 'pending') {
        throw new DomainException('This order or payment has already been processed.');
    }

    $orderCents = (int) round((float) $header['final_amount'] * 100);
    $paidCents = (int) round((float) $header['paid_amount'] * 100);
    if ($orderCents < 0 || $paidCents !== $orderCents) {
        throw new DomainException('Payment amount does not match the locked order total.');
    }

    $proofStmt = $conn->prepare('SELECT proof_image FROM payment_proofs WHERE payment_id = ? LIMIT 1 FOR UPDATE');
    $proofStmt->bind_param('i', $paymentId);
    $proofStmt->execute();
    $proof = $proofStmt->get_result()->fetch_assoc() ?: null;
    $proofStmt->close();

    $proofPath = $proof
        ? Security::resolveStoredFile((string) $proof['proof_image'], [
            STORAGE_PATH . DIRECTORY_SEPARATOR . 'private_uploads' . DIRECTORY_SEPARATOR . 'payment_proofs',
            PUBLIC_PATH . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'payment_proofs',
        ])
        : null;

    if ($proofPath === null || !in_array(Security::detectMimeType($proofPath), ['image/jpeg', 'image/png', 'application/pdf'], true)) {
        throw new DomainException('A valid payment proof is required before approval.');
    }

    $itemStmt = $conn->prepare("
        SELECT oi.id AS order_item_id, oi.course_id, oi.instructor_id, oi.final_price,
               c.instructor_id AS course_owner_id, c.status AS course_status,
               instructor.role AS instructor_role, instructor.status AS instructor_status
        FROM order_items oi
        INNER JOIN courses c ON c.id = oi.course_id
        INNER JOIN users instructor ON instructor.id = oi.instructor_id
        WHERE oi.order_id = ?
        ORDER BY oi.id
        FOR UPDATE
    ");
    $itemStmt->bind_param('i', $orderId);
    $itemStmt->execute();
    $result = $itemStmt->get_result();
    $items = [];
    $itemTotalCents = 0;

    while ($result && $item = $result->fetch_assoc()) {
        if ((int) $item['instructor_id'] !== (int) $item['course_owner_id']) {
            throw new DomainException('An order item no longer matches the course owner.');
        }
        if ($item['instructor_role'] !== 'instructor' || $item['instructor_status'] !== 'active') {
            throw new DomainException('An order item belongs to an inactive instructor.');
        }
        if ($item['course_status'] !== 'published') {
            throw new DomainException('An order item is no longer published. Resolve it before approving payment.');
        }

        $priceCents = (int) round((float) $item['final_price'] * 100);
        if ($priceCents < 0) {
            throw new DomainException('An order item has an invalid price.');
        }
        $itemTotalCents += $priceCents;
        $items[] = $item;
    }
    $itemStmt->close();

    if ($items === [] || $itemTotalCents !== $orderCents) {
        throw new DomainException('Order items do not reconcile to the order total.');
    }

    $commissionRate = admin_order_commission_rate($conn);

    $paymentUpdate = $conn->prepare("
        UPDATE payments
        SET payment_status = 'paid', verified_by = ?, verified_at = NOW()
        WHERE id = ? AND order_id = ? AND payment_status = 'pending'
    ");
    $paymentUpdate->bind_param('iii', $adminId, $paymentId, $orderId);
    $paymentUpdate->execute();
    if ($paymentUpdate->affected_rows !== 1) {
        throw new RuntimeException('Payment state changed while approval was processing.');
    }
    $paymentUpdate->close();

    $orderUpdate = $conn->prepare("UPDATE orders SET order_status = 'paid' WHERE id = ? AND order_status = 'pending'");
    $orderUpdate->bind_param('i', $orderId);
    $orderUpdate->execute();
    if ($orderUpdate->affected_rows !== 1) {
        throw new RuntimeException('Order state changed while approval was processing.');
    }
    $orderUpdate->close();

    $enrollmentLookup = $conn->prepare("
        SELECT id, status, order_id
        FROM enrollments
        WHERE student_id = ? AND course_id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $enrollmentInsert = $conn->prepare("
        INSERT INTO enrollments (
            student_id, course_id, order_id, payment_id,
            access_type, status, granted_by, granted_at
        ) VALUES (?, ?, ?, ?, 'lifetime', 'active', ?, NOW())
    ");
    $enrollmentUpdate = $conn->prepare("
        UPDATE enrollments
        SET order_id = ?, payment_id = ?, access_type = 'lifetime', status = 'active',
            granted_by = ?, granted_at = NOW(), revoked_by_admin = NULL, revoked_at = NULL
        WHERE id = ? AND status <> 'active'
    ");
    $earningLookup = $conn->prepare('SELECT id FROM instructor_earnings WHERE order_item_id = ? LIMIT 1 FOR UPDATE');
    $earningInsert = $conn->prepare("
        INSERT INTO instructor_earnings (
            instructor_id, course_id, student_id, order_id, order_item_id, payment_id,
            gross_amount, commission_rate, commission_amount, instructor_amount, earning_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available')
    ");

    $studentId = (int) $header['student_id'];
    $notifiedInstructors = [];

    foreach ($items as $item) {
        $courseId = (int) $item['course_id'];
        $instructorId = (int) $item['instructor_id'];
        $orderItemId = (int) $item['order_item_id'];
        $grossAmount = round((float) $item['final_price'], 2);

        $enrollmentLookup->bind_param('ii', $studentId, $courseId);
        $enrollmentLookup->execute();
        $existingEnrollment = $enrollmentLookup->get_result()->fetch_assoc() ?: null;

        if ($existingEnrollment && $existingEnrollment['status'] === 'active') {
            throw new DomainException('The student is already actively enrolled in a course from this order.');
        }

        if ($existingEnrollment) {
            $enrollmentId = (int) $existingEnrollment['id'];
            $enrollmentUpdate->bind_param('iiii', $orderId, $paymentId, $adminId, $enrollmentId);
            $enrollmentUpdate->execute();
            if ($enrollmentUpdate->affected_rows !== 1) {
                throw new RuntimeException('An enrollment could not be reactivated.');
            }
        } else {
            $enrollmentInsert->bind_param('iiiii', $studentId, $courseId, $orderId, $paymentId, $adminId);
            $enrollmentInsert->execute();
        }

        if ($grossAmount > 0) {
            $earningLookup->bind_param('i', $orderItemId);
            $earningLookup->execute();
            if ($earningLookup->get_result()->num_rows > 0) {
                throw new DomainException('An instructor earning already exists for an item in this pending payment.');
            }

            $commissionAmount = round($grossAmount * $commissionRate / 100, 2);
            $instructorAmount = round($grossAmount - $commissionAmount, 2);
            $earningInsert->bind_param(
                'iiiiiidddd',
                $instructorId,
                $courseId,
                $studentId,
                $orderId,
                $orderItemId,
                $paymentId,
                $grossAmount,
                $commissionRate,
                $commissionAmount,
                $instructorAmount
            );
            $earningInsert->execute();
        }

        $notifiedInstructors[$instructorId] = true;
    }

    $enrollmentLookup->close();
    $enrollmentInsert->close();
    $enrollmentUpdate->close();
    $earningLookup->close();
    $earningInsert->close();

    send_notification(
        $conn,
        $studentId,
        'Payment approved',
        'Your payment has been approved. Your purchased courses are now available in My Courses.',
        'payment'
    );

    foreach (array_keys($notifiedInstructors) as $instructorId) {
        send_notification(
            $conn,
            (int) $instructorId,
            'New course sale',
            'A student payment was approved for one or more of your courses. The recorded earning is available for payout.',
            'payout'
        );
    }
}

function admin_order_reject_payment(mysqli $conn, int $adminId, int $orderId, int $paymentId): void
{
    $header = admin_order_locked_header($conn, $orderId, $paymentId);
    if (!$header) {
        throw new DomainException('Order or payment was not found.');
    }
    if ($header['order_status'] !== 'pending' || $header['payment_status'] !== 'pending') {
        throw new DomainException('This order or payment has already been processed.');
    }

    $paymentUpdate = $conn->prepare("
        UPDATE payments
        SET payment_status = 'rejected', verified_by = ?, verified_at = NOW()
        WHERE id = ? AND order_id = ? AND payment_status = 'pending'
    ");
    $paymentUpdate->bind_param('iii', $adminId, $paymentId, $orderId);
    $paymentUpdate->execute();
    if ($paymentUpdate->affected_rows !== 1) {
        throw new RuntimeException('Payment state changed during rejection.');
    }
    $paymentUpdate->close();

    $orderUpdate = $conn->prepare("UPDATE orders SET order_status = 'failed' WHERE id = ? AND order_status = 'pending'");
    $orderUpdate->bind_param('i', $orderId);
    $orderUpdate->execute();
    if ($orderUpdate->affected_rows !== 1) {
        throw new RuntimeException('Order state changed during rejection.');
    }
    $orderUpdate->close();

    send_notification(
        $conn,
        (int) $header['student_id'],
        'Payment rejected',
        'Your submitted payment could not be verified. Review the payment details and contact support before submitting another order.',
        'payment'
    );
}

function admin_order_direct_payout(
    mysqli $conn,
    int $adminId,
    int $orderId,
    int $paymentId,
    int $instructorId,
    string $paymentMethod,
    string $transactionReference,
    string $adminNote
): void {
    $header = admin_order_locked_header($conn, $orderId, $paymentId);
    if (!$header || $header['order_status'] !== 'paid' || $header['payment_status'] !== 'paid') {
        throw new DomainException('Student payment must be verified before an instructor payout.');
    }

    $instructorStmt = $conn->prepare("
        SELECT u.id, u.status, ibd.bank_name, ibd.account_name, ibd.account_number,
               ibd.esewa_number, ibd.khalti_number, ibd.qr_image
        FROM users u
        LEFT JOIN instructor_bank_details ibd ON ibd.instructor_id = u.id
        WHERE u.id = ? AND u.role = 'instructor'
        LIMIT 1
        FOR UPDATE
    ");
    $instructorStmt->bind_param('i', $instructorId);
    $instructorStmt->execute();
    $receiving = $instructorStmt->get_result()->fetch_assoc() ?: null;
    $instructorStmt->close();

    if (!$receiving || $receiving['status'] !== 'active') {
        throw new DomainException('The instructor account is not active.');
    }

    $methodReady = match ($paymentMethod) {
        'bank' => !empty($receiving['bank_name']) && !empty($receiving['account_name']) && !empty($receiving['account_number']),
        'esewa' => preg_match('/^\d{10,15}$/', (string) ($receiving['esewa_number'] ?? '')) === 1,
        'khalti' => preg_match('/^\d{10,15}$/', (string) ($receiving['khalti_number'] ?? '')) === 1,
        default => false,
    };

    if (!$methodReady) {
        throw new DomainException('The instructor has not completed the selected receiving method.');
    }

    $earningStmt = $conn->prepare("
        SELECT id, instructor_amount, earning_status
        FROM instructor_earnings
        WHERE order_id = ? AND payment_id = ? AND instructor_id = ?
        ORDER BY id
        FOR UPDATE
    ");
    $earningStmt->bind_param('iii', $orderId, $paymentId, $instructorId);
    $earningStmt->execute();
    $earningResult = $earningStmt->get_result();
    $earningIds = [];
    $payoutCents = 0;

    while ($earningResult && $earning = $earningResult->fetch_assoc()) {
        if ($earning['earning_status'] !== 'available') {
            throw new DomainException('One or more earnings are already paid or locked in a withdrawal request.');
        }
        $earningIds[] = (int) $earning['id'];
        $payoutCents += (int) round((float) $earning['instructor_amount'] * 100);
    }
    $earningStmt->close();

    if ($earningIds === [] || $payoutCents <= 0) {
        throw new DomainException('No available recorded instructor earning exists for this order.');
    }

    $duplicateStmt = $conn->prepare("
        SELECT id FROM payouts
        WHERE order_id = ? AND payment_id = ? AND instructor_id = ? AND payout_source = 'direct_order'
        LIMIT 1 FOR UPDATE
    ");
    $duplicateStmt->bind_param('iii', $orderId, $paymentId, $instructorId);
    $duplicateStmt->execute();
    $duplicate = $duplicateStmt->get_result()->num_rows > 0;
    $duplicateStmt->close();
    if ($duplicate) {
        throw new DomainException('A direct payout already exists for this instructor and order.');
    }

    $updateStmt = $conn->prepare("
        UPDATE instructor_earnings
        SET earning_status = 'paid', paid_at = NOW()
        WHERE order_id = ? AND payment_id = ? AND instructor_id = ? AND earning_status = 'available'
    ");
    $updateStmt->bind_param('iii', $orderId, $paymentId, $instructorId);
    $updateStmt->execute();
    if ($updateStmt->affected_rows !== count($earningIds)) {
        throw new RuntimeException('Not every recorded earning transitioned to paid.');
    }
    $updateStmt->close();

    $paidAmount = $payoutCents / 100;
    $payoutStmt = $conn->prepare("
        INSERT INTO payouts (
            withdrawal_request_id, order_id, payment_id, payout_source,
            instructor_id, paid_amount, payment_method,
            transaction_reference, payout_status, paid_by, admin_note
        ) VALUES (NULL, ?, ?, 'direct_order', ?, ?, ?, ?, 'paid', ?, ?)
    ");
    $payoutStmt->bind_param(
        'iiidssis',
        $orderId,
        $paymentId,
        $instructorId,
        $paidAmount,
        $paymentMethod,
        $transactionReference,
        $adminId,
        $adminNote
    );
    $payoutStmt->execute();
    $payoutStmt->close();

    send_notification(
        $conn,
        $instructorId,
        'Instructor payout sent',
        'A direct payout of Rs. ' . number_format($paidAmount, 2) . ' was recorded for order #' . $orderId . '.',
        'payout'
    );
}

if ($orderId <= 0 || $paymentId <= 0 || $adminId <= 0) {
    admin_order_action_fail('Invalid order action request.', $orderId);
}

$action = isset($_POST['verify_payment'])
    ? 'verify'
    : (isset($_POST['reject_payment'])
        ? 'reject'
        : (isset($_POST['send_direct_instructor_payout']) ? 'payout' : ''));

if ($action === '') {
    admin_order_action_fail('Unknown order action.', $orderId);
}

$transactionStarted = false;

try {
    $conn->begin_transaction();
    $transactionStarted = true;

    if ($action === 'verify') {
        admin_order_verify_payment($conn, $adminId, $orderId, $paymentId);
    } elseif ($action === 'reject') {
        admin_order_reject_payment($conn, $adminId, $orderId, $paymentId);
    } else {
        $instructorId = (int) ($_POST['instructor_id'] ?? 0);
        $paymentMethod = (string) ($_POST['payment_method'] ?? '');
        $transactionReference = security_clean_text($_POST['transaction_reference'] ?? '', 150);
        $adminNote = security_clean_text($_POST['admin_note'] ?? '', 1000, true);

        if (
            $instructorId <= 0
            || !in_array($paymentMethod, ['bank', 'esewa', 'khalti'], true)
            || $transactionReference === ''
        ) {
            throw new DomainException('Provide a valid instructor, receiving method, and transaction reference.');
        }

        admin_order_direct_payout(
            $conn,
            $adminId,
            $orderId,
            $paymentId,
            $instructorId,
            $paymentMethod,
            $transactionReference,
            $adminNote
        );
    }

    $conn->commit();
    $transactionStarted = false;

    admin_order_action_redirect($orderId, match ($action) {
        'verify' => 'verified',
        'reject' => 'rejected',
        default => 'payout_sent',
    });
} catch (DomainException $exception) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    admin_order_action_fail($exception->getMessage(), $orderId, 409);
} catch (Throwable $exception) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    error_log('Admin order action failed: ' . $exception->getMessage());
    admin_order_action_fail('The order action could not be completed. No financial state was changed.', $orderId, 500);
}
