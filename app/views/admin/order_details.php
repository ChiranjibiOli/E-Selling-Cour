<?php

require_once __DIR__ . '/../../middleware/AdminMiddleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/notification_helper.php';

AdminMiddleware::handle();

$admin = Auth::user();
$currentAdminId = (int) ($admin['id'] ?? 0);

$orderId = (int) ($_GET['order_id'] ?? $_POST['order_id'] ?? 0);

$message = '';
$messageType = '';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function display_value($value)
{
    $value = trim((string) $value);
    return $value !== '' ? $value : 'Not provided';
}

function status_label($status)
{
    if ($status === 'pending') {
        return 'Pending';
    }

    if ($status === 'paid') {
        return 'Paid';
    }

    if ($status === 'failed') {
        return 'Failed';
    }

    if ($status === 'cancelled') {
        return 'Cancelled';
    }

    if ($status === 'rejected') {
        return 'Rejected';
    }

    return ucfirst((string) $status);
}

function status_class($status)
{
    if ($status === 'paid') {
        return 'status-paid';
    }

    if ($status === 'pending') {
        return 'status-pending';
    }

    return 'status-failed';
}

if ($orderId <= 0) {
    header("Location: admin-orders.php");
    exit;
}



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment'])) {
    $paymentId = (int) ($_POST['payment_id'] ?? 0);

    if ($paymentId > 0 && $orderId > 0) {
        try {
            $conn->begin_transaction();

            $paymentSql = "
                UPDATE payments
                SET payment_status = 'paid',
                    verified_by = ?,
                    verified_at = NOW()
                WHERE id = ?
                  AND order_id = ?
                  AND payment_status = 'pending'
            ";

            $paymentStmt = $conn->prepare($paymentSql);

            if (!$paymentStmt) {
                throw new Exception('Failed to prepare payment update.');
            }

            $paymentStmt->bind_param("iii", $currentAdminId, $paymentId, $orderId);

            if (!$paymentStmt->execute()) {
                throw new Exception('Failed to update payment.');
            }

            if ($paymentStmt->affected_rows !== 1) {
                throw new Exception('Payment is not pending or does not belong to this order.');
            }

            $paymentStmt->close();

            $orderSql = "
                UPDATE orders
                SET order_status = 'paid'
                WHERE id = ?
                  AND order_status = 'pending'
            ";

            $orderStmt = $conn->prepare($orderSql);

            if (!$orderStmt) {
                throw new Exception('Failed to prepare order update.');
            }

            $orderStmt->bind_param("i", $orderId);

            if (!$orderStmt->execute()) {
                throw new Exception('Failed to update order.');
            }

            if ($orderStmt->affected_rows !== 1) {
                throw new Exception('Order is not pending.');
            }

            $orderStmt->close();

            $itemSql = "
                SELECT 
                    oi.id AS order_item_id,
                    oi.course_id,
                    oi.instructor_id,
                    oi.final_price,
                    o.student_id
                FROM order_items oi
                INNER JOIN orders o ON oi.order_id = o.id
                WHERE oi.order_id = ?
            ";
            $itemStmt = $conn->prepare($itemSql);

            if (!$itemStmt) {
                throw new Exception('Failed to prepare order items.');
            }

            $itemStmt->bind_param("i", $orderId);
            $itemStmt->execute();

            $itemsResult = $itemStmt->get_result();

            if ($itemsResult->num_rows === 0) {
                throw new Exception('Order has no items.');
            }

            $enrollSql = "
                INSERT INTO enrollments (
                    student_id,
                    course_id,
                    order_id,
                    payment_id,
                    access_type,
                    status,
                    granted_by,
                    granted_at
                ) VALUES (?, ?, ?, ?, 'lifetime', 'active', ?, NOW())
                ON DUPLICATE KEY UPDATE
                    order_id = VALUES(order_id),
                    payment_id = VALUES(payment_id),
                    access_type = 'lifetime',
                    status = 'active',
                    granted_by = VALUES(granted_by),
                    granted_at = NOW(),
                    revoked_by_admin = NULL,
                    revoked_at = NULL
            ";

            
            $enrollStmt = $conn->prepare($enrollSql);

            if (!$enrollStmt) {
                throw new Exception('Failed to prepare enrollment insert.');
            }

            $commissionRate = 20.00;

            $settingSql = "
                SELECT setting_value
                FROM site_settings
                WHERE setting_key = 'admin_commission_rate'
                LIMIT 1
            ";

            $settingResult = $conn->query($settingSql);

            if ($settingResult && $settingResult->num_rows === 1) {
                $settingRow = $settingResult->fetch_assoc();
                $commissionRate = (float) ($settingRow['setting_value'] ?? 20);
            }

            $earningSql = "
                INSERT INTO instructor_earnings (
                    instructor_id,
                    course_id,
                    student_id,
                    order_id,
                    order_item_id,
                    payment_id,
                    gross_amount,
                    commission_rate,
                    commission_amount,
                    instructor_amount,
                    earning_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available')
            ";

            $earningStmt = $conn->prepare($earningSql);

            if (!$earningStmt) {
                throw new Exception('Failed to prepare instructor earning insert.');
            }

            while ($item = $itemsResult->fetch_assoc()) {
                $studentId = (int) ($item['student_id'] ?? 0);
                $courseId = (int) ($item['course_id'] ?? 0);
                $instructorId = (int) ($item['instructor_id'] ?? 0);
                $orderItemId = (int) ($item['order_item_id'] ?? 0);

                if ($studentId <= 0 || $courseId <= 0 || $instructorId <= 0 || $orderItemId <= 0) {
                    throw new Exception('Invalid order item data. Student, course, instructor, or order item is missing.');
                }

                $grossAmount = (float) ($item['final_price'] ?? 0);

                if ($grossAmount <= 0) {
                    throw new Exception('Invalid course price found in this order.');
                }
                $commissionAmount = ($grossAmount * $commissionRate) / 100;
                $instructorAmount = $grossAmount - $commissionAmount;

                $enrollStmt->bind_param(
                    "iiiii",
                    $studentId,
                    $courseId,
                    $orderId,
                    $paymentId,
                    $currentAdminId
                );

                if (!$enrollStmt->execute()) {
                    throw new Exception('Failed to enroll student.');
                }

                $earningStmt->bind_param(
                    "iiiiiidddd",
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

                if (!$earningStmt->execute()) {
                    throw new Exception('Failed to create instructor earning.');
                }
                send_notification(
    $conn,
    $studentId,
    'Payment approved',
    'Your payment has been approved. Your course is now available in My Courses.',
    'payment'
);

send_notification(
    $conn,
    $instructorId,
    'New course sale',
    'A student purchased your course. Your earning is now pending admin payout.',
    'payout'
);
            }

            $earningStmt->close();
            $enrollStmt->close();
            $itemStmt->close();

            $conn->commit();

            header("Location: admin-order-details.php?order_id=" . $orderId . "&verified=1");
            exit;

        } catch (Exception $e) {
            $conn->rollback();

            $message = 'Failed to verify payment: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_payment'])) {
    $paymentId = (int) ($_POST['payment_id'] ?? 0);

    if ($paymentId > 0 && $orderId > 0) {
        try {
            $conn->begin_transaction();

            $paymentSql = "
                UPDATE payments
                SET payment_status = 'rejected',
                    verified_by = ?,
                    verified_at = NOW()
                WHERE id = ?
                  AND order_id = ?
                  AND payment_status = 'pending'
            ";

            $paymentStmt = $conn->prepare($paymentSql);

            if (!$paymentStmt) {
                throw new Exception('Failed to prepare payment reject.');
            }

            $paymentStmt->bind_param("iii", $currentAdminId, $paymentId, $orderId);

            if (!$paymentStmt->execute()) {
                throw new Exception('Failed to reject payment.');
            }

            if ($paymentStmt->affected_rows !== 1) {
                throw new Exception('Payment is not pending or does not belong to this order.');
            }

            $paymentStmt->close();

            $orderSql = "
                UPDATE orders
                SET order_status = 'failed'
                WHERE id = ?
                  AND order_status = 'pending'
            ";

            $orderStmt = $conn->prepare($orderSql);

            if (!$orderStmt) {
                throw new Exception('Failed to prepare order reject.');
            }

            $orderStmt->bind_param("i", $orderId);

            if (!$orderStmt->execute()) {
                throw new Exception('Failed to reject order.');
            }

            if ($orderStmt->affected_rows !== 1) {
                throw new Exception('Order is not pending.');
            }

            $orderStmt->close();

            $conn->commit();

            header("Location: admin-order-details.php?order_id=" . $orderId . "&rejected=1");
            exit;

        } catch (Exception $e) {
            $conn->rollback();

            $message = 'Failed to reject payment: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

if (isset($_GET['verified'])) {
    $message = 'Payment verified successfully. Student course is active and instructor payout is pending.';
    $messageType = 'success';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_direct_instructor_payout'])) {
    $paymentId = (int) ($_POST['payment_id'] ?? 0);
    $instructorId = (int) ($_POST['instructor_id'] ?? 0);

    $commissionRate = 20.0;
    $commissionSettingResult = $conn->query("
        SELECT setting_value
        FROM site_settings
        WHERE setting_key = 'admin_commission_rate'
        LIMIT 1
    ");

    if ($commissionSettingResult && $commissionSettingResult->num_rows === 1) {
        $commissionRate = (float) ($commissionSettingResult->fetch_assoc()['setting_value'] ?? 20);
    }
    $paymentMethod = trim($_POST['payment_method'] ?? 'bank');
    $transactionReference = trim($_POST['transaction_reference'] ?? '');
    $adminNote = trim($_POST['admin_note'] ?? '');

    if ($commissionRate < 0) {
        $commissionRate = 0;
    }

    if ($commissionRate > 100) {
        $commissionRate = 100;
    }

    if (
        $orderId <= 0 ||
        $paymentId <= 0 ||
        $instructorId <= 0 ||
        !in_array($paymentMethod, ['bank', 'esewa', 'khalti'], true) ||
        $transactionReference === ''
    ) {
        $message = 'Please provide valid instructor payout details.';
        $messageType = 'error';
    } else {
        try {
            $conn->begin_transaction();

            $checkSql = "
                SELECT 
                    o.id AS order_id,
                    o.order_status,
                    p.id AS payment_id,
                    p.payment_status
                FROM orders o
                INNER JOIN payments p ON p.order_id = o.id
                WHERE o.id = ?
                  AND p.id = ?
                LIMIT 1
                FOR UPDATE
            ";

            $checkStmt = $conn->prepare($checkSql);

            if (!$checkStmt) {
                throw new Exception('Failed to prepare order/payment check.');
            }

            $checkStmt->bind_param("ii", $orderId, $paymentId);
            $checkStmt->execute();

            $checkResult = $checkStmt->get_result();

            if (!$checkResult || $checkResult->num_rows !== 1) {
                throw new Exception('Order or payment not found.');
            }

            $checkRow = $checkResult->fetch_assoc();
            $checkStmt->close();

            if ($checkRow['order_status'] !== 'paid' || $checkRow['payment_status'] !== 'paid') {
                throw new Exception('Student payment must be verified before sending instructor money.');
            }

            $methodCheckSql = "
                SELECT *
                FROM instructor_bank_details
                WHERE instructor_id = ?
                LIMIT 1
            ";

            $methodCheckStmt = $conn->prepare($methodCheckSql);

            if (!$methodCheckStmt) {
                throw new Exception('Failed to check instructor receiving method.');
            }

            $methodCheckStmt->bind_param("i", $instructorId);
            $methodCheckStmt->execute();

            $methodResult = $methodCheckStmt->get_result();

            if (!$methodResult || $methodResult->num_rows !== 1) {
                throw new Exception('Instructor has not added receiving method yet.');
            }

            $methodDetails = $methodResult->fetch_assoc();
            $methodCheckStmt->close();

            if (
                empty($methodDetails['bank_name']) &&
                empty($methodDetails['account_number']) &&
                empty($methodDetails['esewa_number']) &&
                empty($methodDetails['khalti_number']) &&
                empty($methodDetails['qr_image'])
            ) {
                throw new Exception('Instructor receiving method is empty.');
            }

            $earningSql = "
                SELECT 
                    id,
                    gross_amount
                FROM instructor_earnings
                WHERE order_id = ?
                  AND payment_id = ?
                  AND instructor_id = ?
                  AND earning_status = 'available'
                FOR UPDATE
            ";

            $earningStmt = $conn->prepare($earningSql);

            if (!$earningStmt) {
                throw new Exception('Failed to prepare instructor earning check.');
            }

            $earningStmt->bind_param("iii", $orderId, $paymentId, $instructorId);
            $earningStmt->execute();

            $earningResult = $earningStmt->get_result();

            $grossTotal = 0;

            if ($earningResult) {
                while ($earning = $earningResult->fetch_assoc()) {
                    $grossTotal += (float) $earning['gross_amount'];
                }
            }

            $earningStmt->close();

            if ($grossTotal <= 0) {
                throw new Exception('No pending instructor payout found. It may already be paid.');
            }

            $commissionAmount = ($grossTotal * $commissionRate) / 100;
            $instructorPaidAmount = $grossTotal - $commissionAmount;

            $updateEarningSql = "
                UPDATE instructor_earnings
                SET 
                    commission_rate = ?,
                    commission_amount = gross_amount * (? / 100),
                    instructor_amount = gross_amount - (gross_amount * (? / 100)),
                    earning_status = 'paid',
                    paid_at = NOW()
                WHERE order_id = ?
                  AND payment_id = ?
                  AND instructor_id = ?
                  AND earning_status = 'available'
            ";

            $updateEarningStmt = $conn->prepare($updateEarningSql);

            if (!$updateEarningStmt) {
                throw new Exception('Failed to prepare instructor earning update.');
            }

            $updateEarningStmt->bind_param(
                "dddiii",
                $commissionRate,
                $commissionRate,
                $commissionRate,
                $orderId,
                $paymentId,
                $instructorId
            );

            if (!$updateEarningStmt->execute()) {
                throw new Exception('Failed to update instructor earning.');
            }

            $updateEarningStmt->close();

            $payoutSql = "
                INSERT INTO payouts (
                    withdrawal_request_id,
                    order_id,
                    payment_id,
                    payout_source,
                    instructor_id,
                    paid_amount,
                    payment_method,
                    transaction_reference,
                    payout_status,
                    paid_by,
                    admin_note
                ) VALUES (
                    NULL,
                    ?,
                    ?,
                    'direct_order',
                    ?,
                    ?,
                    ?,
                    ?,
                    'paid',
                    ?,
                    ?
                )
            ";

            $payoutStmt = $conn->prepare($payoutSql);

            if (!$payoutStmt) {
                throw new Exception('Failed to prepare payout record.');
            }

            $payoutStmt->bind_param(
                "iiidssis",
                $orderId,
                $paymentId,
                $instructorId,
                $instructorPaidAmount,
                $paymentMethod,
                $transactionReference,
                $currentAdminId,
                $adminNote
            );

            if (!$payoutStmt->execute()) {
                throw new Exception('Failed to save payout record.');
            }

            $payoutStmt->close();

            $conn->commit();

            header("Location: admin-order-details.php?order_id=" . $orderId . "&payout_sent=1");
            exit;

        } catch (Exception $e) {
            $conn->rollback();

            $message = 'Instructor payout failed: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

if (isset($_GET['rejected'])) {
    $message = 'Payment rejected successfully.';
    $messageType = 'success';
}
if (isset($_GET['payout_sent'])) {
    $message = 'Instructor money marked as sent successfully. Instructor panel is updated to Paid to Instructor.';
    $messageType = 'success';
}
/*
|--------------------------------------------------------------------------
| Load order
|--------------------------------------------------------------------------
*/

$order = null;

$orderSql = "
    SELECT 
        o.id AS order_id,
        o.student_id,
        o.original_amount,
        o.discount_amount,
        o.final_amount,
        o.order_status,
        o.created_at AS order_date,

        student.full_name AS student_name,
        student.email AS student_email,
        student.phone AS student_phone,

        p.id AS payment_id,
        p.payment_method,
        p.payment_type,
        p.transaction_id,
        p.paid_amount,
        p.payment_status,
        p.verified_at,

        pp.id AS proof_id,
        pp.proof_image,
        pp.note AS proof_note
    FROM orders o
    INNER JOIN users student ON o.student_id = student.id
    LEFT JOIN payments p ON p.order_id = o.id
    LEFT JOIN payment_proofs pp ON pp.payment_id = p.id
    WHERE o.id = ?
    LIMIT 1
";

$orderStmt = $conn->prepare($orderSql);

if (!$orderStmt) {
    error_log('Admin order query preparation failed: ' . $conn->error);
    http_response_code(500);
    exit('Unable to load this order right now.');
}

$orderStmt->bind_param("i", $orderId);
$orderStmt->execute();

$orderResult = $orderStmt->get_result();

if ($orderResult && $orderResult->num_rows === 1) {
    $order = $orderResult->fetch_assoc();
}

$orderStmt->close();

if (!$order) {
    
    require_once __DIR__ . '/../layouts/header.php';
    require_once __DIR__ . '/../layouts/admin_navbar.php';
    ?>


    <main class="admin-order-details-page">
        <section class="admin-order-details-wrapper">
            <div class="empty-order-box">
                <h1>Order not found</h1>
                <p>This order does not exist.</p>
                <a href="admin-orders.php">Back to Orders</a>
            </div>
        </section>
    </main>

    </body>
    </html>

    <?php
    exit;
}

/*
|--------------------------------------------------------------------------
| Load items
|--------------------------------------------------------------------------
*/

$items = [];

$itemSql = "
    SELECT 
        oi.id AS order_item_id,
        oi.course_id,
        oi.instructor_id,
        oi.course_price,
        oi.discount_amount,
        oi.final_price,

        c.title AS course_title,
        c.thumbnail AS course_thumbnail,

        instructor.full_name AS instructor_name,
        instructor.email AS instructor_email,

        ie.gross_amount,
        ie.commission_rate,
        ie.commission_amount,
        ie.instructor_amount,
        ie.earning_status
    FROM order_items oi
    INNER JOIN courses c ON oi.course_id = c.id
    INNER JOIN users instructor ON oi.instructor_id = instructor.id
    LEFT JOIN instructor_earnings ie ON ie.order_item_id = oi.id
    WHERE oi.order_id = ?
    ORDER BY oi.id ASC
";

$itemStmt = $conn->prepare($itemSql);

if ($itemStmt) {
    $itemStmt->bind_param("i", $orderId);
    $itemStmt->execute();

    $itemResult = $itemStmt->get_result();

    if ($itemResult) {
        while ($row = $itemResult->fetch_assoc()) {
            $items[] = $row;
        }
    }

    $itemStmt->close();
}

$proofPath = !empty($order['proof_id'])
    ? 'admin-view-payment-proof.php?proof_id=' . (int) $order['proof_id']
    : '';

    if (!function_exists('get_order_instructor_payouts')) {
    function get_order_instructor_payouts(mysqli $conn, int $orderId, int $paymentId): array
    {
        $rows = [];

        $sql = "
            SELECT
                ie.instructor_id,
                instructor.full_name AS instructor_name,
                instructor.email AS instructor_email,

                COUNT(ie.id) AS earning_count,
                COALESCE(SUM(ie.gross_amount), 0) AS gross_amount,
                COALESCE(MAX(ie.commission_rate), 20) AS commission_rate,
                COALESCE(SUM(ie.commission_amount), 0) AS commission_amount,
                COALESCE(SUM(ie.instructor_amount), 0) AS instructor_amount,

                SUM(CASE WHEN ie.earning_status = 'paid' THEN 1 ELSE 0 END) AS paid_count,

                GROUP_CONCAT(DISTINCT p.transaction_reference SEPARATOR ', ') AS payout_references
            FROM instructor_earnings ie
            INNER JOIN users instructor ON ie.instructor_id = instructor.id
            LEFT JOIN payouts p 
                ON p.order_id = ie.order_id
               AND p.payment_id = ie.payment_id
               AND p.instructor_id = ie.instructor_id
               AND p.payout_source = 'direct_order'
            WHERE ie.order_id = ?
              AND ie.payment_id = ?
            GROUP BY 
                ie.instructor_id,
                instructor.full_name,
                instructor.email
            ORDER BY instructor.full_name ASC
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param("ii", $orderId, $paymentId);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }

        $stmt->close();

        return $rows;
    }
}

if (!function_exists('get_instructor_receiving_method')) {
    function get_instructor_receiving_method(mysqli $conn, int $instructorId): ?array
    {
        $sql = "
            SELECT *
            FROM instructor_bank_details
            WHERE instructor_id = ?
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("i", $instructorId);
        $stmt->execute();

        $result = $stmt->get_result();

        $details = null;

        if ($result && $result->num_rows === 1) {
            $details = $result->fetch_assoc();
        }

        $stmt->close();

        return $details;
    }
}

if (!function_exists('instructor_has_receiving_method')) {
    function instructor_has_receiving_method(?array $details): bool
    {
        if (!$details) {
            return false;
        }

        return !empty($details['bank_name']) ||
               !empty($details['account_number']) ||
               !empty($details['esewa_number']) ||
               !empty($details['khalti_number']) ||
               !empty($details['qr_image']);
    }
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/admin_navbar.php';


?>



<main class="admin-order-details-page">
    <section class="admin-order-details-wrapper">

        <div class="order-details-header">
            <div>
                <p class="page-label">Admin Panel</p>
                <h1>Order #<?php echo (int) $order['order_id']; ?></h1>
                <p>Review pending student payment and approve or reject the order.</p>
            </div>

            <a href="admin-orders.php" class="back-btn">Back to Orders</a>
        </div>

        <?php if ($message !== ''): ?>
            <div class="admin-alert <?php echo h($messageType); ?>">
                <?php echo h($message); ?>
            </div>
        <?php endif; ?>

        <div class="status-banner <?php echo status_class($order['order_status']); ?>">
            <strong>Order Status:</strong>
            <?php echo h(status_label($order['order_status'])); ?>

            <span>|</span>

            <strong>Payment Status:</strong>
            <?php echo h(status_label($order['payment_status'] ?? 'pending')); ?>
        </div>

        <div class="details-layout">

            <div class="details-main">

                <div class="details-card">
                    <h2>Student Details</h2>

                    <div class="details-grid">
                        <div>
                            <span>Name</span>
                            <strong><?php echo h($order['student_name']); ?></strong>
                        </div>

                        <div>
                            <span>Email</span>
                            <strong><?php echo h($order['student_email']); ?></strong>
                        </div>

                        <div>
                            <span>Phone</span>
                            <strong><?php echo h(display_value($order['student_phone'])); ?></strong>
                        </div>

                        <div>
                            <span>Order Date</span>
                            <strong><?php echo h(date('M d, Y h:i A', strtotime($order['order_date']))); ?></strong>
                        </div>
                    </div>
                </div>

                <div class="details-card">
                    <h2>Purchased Courses</h2>

                    <?php if (empty($items)): ?>

                        <div class="empty-small-box">No course item found.</div>

                    <?php else: ?>

                        <div class="items-list">
                            <?php foreach ($items as $item): ?>
                                <?php
                                    $thumbnail = $item['course_thumbnail'] ?? '';
                                    $thumbnailPath = $thumbnail !== ''
                                        ? 'assets/uploads/course_thumbnails/' . $thumbnail
                                        : 'assets/images/course-placeholder.svg';
                                ?>

                                <div class="item-row">
                                    <img src="<?php echo h($thumbnailPath); ?>" alt="<?php echo h($item['course_title']); ?>">

                                    <div>
                                        <h3><?php echo h($item['course_title']); ?></h3>
                                        <p>Instructor: <?php echo h($item['instructor_name']); ?></p>
                                        <p><?php echo h($item['instructor_email']); ?></p>
                                    </div>

                                    <strong>Rs. <?php echo number_format((float) $item['final_price'], 2); ?></strong>
                                    <div class="course-commission-box">
    <div>
        <span>Gross Amount</span>
        <strong>Rs. <?php echo number_format((float) ($item['gross_amount'] ?? $item['final_price']), 2); ?></strong>
    </div>

    <div>
        <span>Admin Commission</span>
        <strong>
            Rs. <?php echo number_format((float) ($item['commission_amount'] ?? 0), 2); ?>
            <?php if (isset($item['commission_rate'])): ?>
                <small>(<?php echo number_format((float) $item['commission_rate'], 2); ?>%)</small>
            <?php endif; ?>
        </strong>
    </div>

    <div>
        <span>Instructor Gets</span>
        <strong>Rs. <?php echo number_format((float) ($item['instructor_amount'] ?? 0), 2); ?></strong>
    </div>

    <div>
        <span>Payout Status</span>
        <strong>
            <?php
                $earningStatus = $item['earning_status'] ?? 'not_created';

                if ($earningStatus === 'available') {
                    echo 'Pending Admin Payout';
                } elseif ($earningStatus === 'paid') {
                    echo 'Paid to Instructor';
                } elseif ($earningStatus === 'withdraw_requested') {
                    echo 'Withdrawal Requested';
                } else {
                    echo 'Not Created Yet';
                }
            ?>
        </strong>
    </div>
</div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    <?php endif; ?>
                </div>

                <div class="details-card">
                    <h2>Payment Proof</h2>

                    <?php if ($proofPath !== ''): ?>

                        <div class="proof-box">
                            <img src="<?php echo h($proofPath); ?>" alt="Payment Proof">
                        </div>

                        <a href="<?php echo h($proofPath); ?>" target="_blank" class="open-proof-btn">
                            Open Proof Full Size
                        </a>

                        <?php if (!empty($order['proof_note'])): ?>
                            <div class="proof-note">
                                <strong>Student Note:</strong>
                                <?php echo h($order['proof_note']); ?>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>

                        <div class="empty-small-box">
                            No payment proof uploaded.
                        </div>

                    <?php endif; ?>
                </div>

            </div>

            <aside class="details-side">

                <div class="details-card">
                    <h2>Amount Details</h2>

                    <div class="amount-list">
                        <div>
                            <span>Original Amount</span>
                            <strong>Rs. <?php echo number_format((float) $order['original_amount'], 2); ?></strong>
                        </div>

                        <div>
                            <span>Discount</span>
                            <strong>Rs. <?php echo number_format((float) $order['discount_amount'], 2); ?></strong>
                        </div>

                        <div>
                            <span>Final Amount</span>
                            <strong>Rs. <?php echo number_format((float) $order['final_amount'], 2); ?></strong>
                        </div>

                        <div>
                            <span>Paid Amount</span>
                            <strong>Rs. <?php echo number_format((float) ($order['paid_amount'] ?? 0), 2); ?></strong>
                        </div>
                    </div>
                </div>

                <div class="details-card">
                    <h2>Payment Info</h2>

                    <div class="amount-list">
                        <div>
                            <span>Method</span>
                            <strong><?php echo h(display_value($order['payment_method'])); ?></strong>
                        </div>

                        <div>
                            <span>Type</span>
                            <strong><?php echo h(display_value($order['payment_type'])); ?></strong>
                        </div>

                        <div>
                            <span>Transaction ID</span>
                            <strong><?php echo h(display_value($order['transaction_id'])); ?></strong>
                        </div>
                    </div>
                </div>

                <div class="details-card action-card">
                    <h2>Admin Action</h2>

                    <?php if (($order['payment_status'] ?? '') !== 'paid' && (int) ($order['payment_id'] ?? 0) > 0): ?>

                        <form method="POST">
                              <?php echo csrf_field(); ?>
                            <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                            <input type="hidden" name="payment_id" value="<?php echo (int) $order['payment_id']; ?>">

                            <button type="submit" name="verify_payment" class="verify-btn">
                                Verify Payment & Open Course
                            </button>
                        </form>

                        <form method="POST">
                              <?php echo csrf_field(); ?>
                            <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                            <input type="hidden" name="payment_id" value="<?php echo (int) $order['payment_id']; ?>">

                            <button type="submit" name="reject_payment" class="reject-btn">
                                Reject Payment
                            </button>
                        </form>

                    <?php else: ?>

                        <div class="already-box">
                            Payment already verified or processed.
                        </div>

                    <?php endif; ?>
                </div>
<?php if (($order['order_status'] ?? '') === 'paid' && ($order['payment_status'] ?? '') === 'paid'): ?>
    <?php
        $paymentIdForPayout = (int) ($order['payment_id'] ?? 0);
        $instructorPayouts = get_order_instructor_payouts($conn, $orderId, $paymentIdForPayout);
    ?>

    <div class="details-card payout-side-card">
        <h2>Send Money to Instructor</h2>
        <p class="side-help-text">
            Student course is already opened. Now send instructor earning after commission cut.
        </p>

        <?php if (empty($instructorPayouts)): ?>

            <div class="empty-small-box">
                No instructor earning found. Verify payment first.
            </div>

        <?php else: ?>

            <?php foreach ($instructorPayouts as $payout): ?>
                <?php
                    $instructorId = (int) $payout['instructor_id'];
                    $grossAmount = (float) $payout['gross_amount'];
                    $commissionRate = (float) $payout['commission_rate'];
                    $commissionAmount = (float) $payout['commission_amount'];
                    $instructorAmount = (float) $payout['instructor_amount'];
                    $earningCount = (int) $payout['earning_count'];
                    $paidCount = (int) $payout['paid_count'];

                    $isInstructorPaid = $earningCount > 0 && $paidCount === $earningCount;

                    $receivingMethod = get_instructor_receiving_method($conn, $instructorId);
                    $hasReceivingMethod = instructor_has_receiving_method($receivingMethod);
                ?>

                <div class="payout-instructor-box">

                    <div class="payout-top">
                        <div>
                            <strong><?php echo h($payout['instructor_name']); ?></strong>
                            <span><?php echo h($payout['instructor_email']); ?></span>
                        </div>

                        <?php if ($isInstructorPaid): ?>
                            <em class="mini-status paid">Paid</em>
                        <?php else: ?>
                            <em class="mini-status pending">Pending</em>
                        <?php endif; ?>
                    </div>

                    <div class="payout-money-grid">
                        <div>
                            <span>Course Amount</span>
                            <strong>Rs. <?php echo number_format($grossAmount, 2); ?></strong>
                        </div>

                        <div>
                            <span>Admin Commission</span>
                            <strong>Rs. <?php echo number_format($commissionAmount, 2); ?></strong>
                        </div>

                        <div>
                            <span>Instructor Gets</span>
                            <strong>Rs. <?php echo number_format($instructorAmount, 2); ?></strong>
                        </div>
                    </div>

                    <div class="receiving-method-box">
                        <h3>Receiving Method</h3>

                        <?php if (!$hasReceivingMethod): ?>

                            <div class="method-warning">
                                Instructor has not added bank/eSewa/Khalti/QR details yet.
                            </div>

                        <?php else: ?>

                            <?php if (!empty($receivingMethod['bank_name']) || !empty($receivingMethod['account_number'])): ?>
                                <p><strong>Bank:</strong> <?php echo h(display_value($receivingMethod['bank_name'])); ?></p>
                                <p><strong>Account Name:</strong> <?php echo h(display_value($receivingMethod['account_name'])); ?></p>
                                <p><strong>Account Number:</strong> <?php echo h(display_value($receivingMethod['account_number'])); ?></p>
                                <p><strong>Branch:</strong> <?php echo h(display_value($receivingMethod['branch_name'])); ?></p>
                            <?php endif; ?>

                            <?php if (!empty($receivingMethod['esewa_number'])): ?>
                                <p><strong>eSewa:</strong> <?php echo h($receivingMethod['esewa_number']); ?></p>
                            <?php endif; ?>

                            <?php if (!empty($receivingMethod['khalti_number'])): ?>
                                <p><strong>Khalti:</strong> <?php echo h($receivingMethod['khalti_number']); ?></p>
                            <?php endif; ?>

                            <?php if (!empty($receivingMethod['qr_image'])): ?>
                                <div class="admin-side-qr">
                                    <img 
                                        src="assets/uploads/instructor_qr/<?php echo h($receivingMethod['qr_image']); ?>" 
                                        alt="Instructor QR"
                                    >
                                </div>
                            <?php endif; ?>

                        <?php endif; ?>
                    </div>

                    <?php if ($isInstructorPaid): ?>

                        <div class="already-box">
                            Paid to instructor.
                            <br>
                            Reference:
                            <?php echo h($payout['payout_references'] ?: 'Recorded'); ?>
                        </div>

                    <?php elseif ($hasReceivingMethod): ?>

                        <form method="POST" class="side-payout-form">
                              <?php echo csrf_field(); ?>
                            <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                            <input type="hidden" name="payment_id" value="<?php echo $paymentIdForPayout; ?>">
                            <input type="hidden" name="instructor_id" value="<?php echo $instructorId; ?>">

                            <div class="form-group">
                                <label>Configured Commission %</label>
                                <input
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    value="<?php echo number_format($commissionRate, 2, '.', ''); ?>"
                                    readonly
                                >
                            </div>

                            <div class="form-group">
                                <label>Sent By</label>
                                <select name="payment_method" required>
                                    <option value="bank">Bank</option>
                                    <option value="esewa">eSewa</option>
                                    <option value="khalti">Khalti</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Transaction Reference</label>
                                <input
                                    type="text"
                                    name="transaction_reference"
                                    placeholder="eSewa/Khalti/Bank reference"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label>Admin Note</label>
                                <textarea
                                    name="admin_note"
                                    rows="3"
                                    placeholder="Optional note"
                                ></textarea>
                            </div>

                            <button type="submit" name="send_direct_instructor_payout" class="send-money-btn">
                                Send Money to Instructor
                            </button>
                        </form>

                    <?php else: ?>

                        <button type="button" class="send-money-btn disabled" disabled>
                            Waiting Instructor Receiving Method
                        </button>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>
    </div>
<?php endif; ?>
            </aside>

        </div>

    </section>
</main>

</body>
</html>
