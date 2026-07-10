<?php

require_once __DIR__ . '/../../middleware/AdminMiddleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/notification_helper.php';

AdminMiddleware::handle();

$admin = Auth::user();
$currentAdminId = (int) ($admin['id'] ?? 0);

$message = '';
$messageType = '';

if (isset($_GET['verified'])) {
    $message = 'Payment verified successfully and enrollment created. Instructor money is now pending admin payout.';
    $messageType = 'success';
}

if (isset($_GET['rejected'])) {
    $message = 'Payment rejected successfully.';
    $messageType = 'success';
}

if (isset($_GET['payout_sent'])) {
    $message = 'Instructor payout recorded successfully. Instructor panel is updated to Paid to Instructor.';
    $messageType = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment'])) {
    $paymentId = (int) ($_POST['payment_id'] ?? 0);
    $orderId = (int) ($_POST['order_id'] ?? 0);

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

            $insertEarningSql = "
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

            $insertEarningStmt = $conn->prepare($insertEarningSql);

            if (!$insertEarningStmt) {
                throw new Exception('Failed to prepare instructor earning insert.');
            }

            $notifiedInstructorIds = [];
            $orderStudentId = 0;

            while ($item = $itemsResult->fetch_assoc()) {
                $studentId = (int) $item['student_id'];
                $orderStudentId = $studentId;
                $courseId = (int) $item['course_id'];
                $instructorIdForEarning = (int) $item['instructor_id'];
                $notifiedInstructorIds[$instructorIdForEarning] = true;
                $orderItemId = (int) $item['order_item_id'];

                $grossAmount = (float) $item['final_price'];
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

                $insertEarningStmt->bind_param(
                    "iiiiiidddd",
                    $instructorIdForEarning,
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

                if (!$insertEarningStmt->execute()) {
                    throw new Exception('Failed to create instructor earning.');
                }
            }

            $insertEarningStmt->close();
            $enrollStmt->close();
            $itemStmt->close();

            send_notification(
                $conn,
                $orderStudentId,
                'Payment approved',
                'Order #' . $orderId . ' was approved. Your lifetime course access is now active.',
                'payment'
            );

            foreach (array_keys($notifiedInstructorIds) as $notifiedInstructorId) {
                send_notification(
                    $conn,
                    (int) $notifiedInstructorId,
                    'New verified sale',
                    'A payment in order #' . $orderId . ' was verified and your earning is now available.',
                    'sale'
                );
            }

            $conn->commit();

            header("Location: admin-orders.php?verified=1");
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
    $orderId = (int) ($_POST['order_id'] ?? 0);

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
                throw new Exception('Failed to prepare payment rejection.');
            }

            $paymentStmt->bind_param("iii", $currentAdminId, $paymentId, $orderId);
            $paymentStmt->execute();

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
                throw new Exception('Failed to prepare order rejection.');
            }

            $orderStmt->bind_param("i", $orderId);
            $orderStmt->execute();

            if ($orderStmt->affected_rows !== 1) {
                throw new Exception('Order is not pending.');
            }

            $orderStmt->close();

            $studentLookup = $conn->prepare('SELECT student_id FROM orders WHERE id = ? LIMIT 1');
            $studentLookup->bind_param('i', $orderId);
            $studentLookup->execute();
            $rejectedStudentId = (int) (($studentLookup->get_result()->fetch_assoc()['student_id'] ?? 0));
            $studentLookup->close();

            if ($rejectedStudentId > 0) {
                send_notification(
                    $conn,
                    $rejectedStudentId,
                    'Payment rejected',
                    'Order #' . $orderId . ' was rejected. Review your transaction reference and proof before trying again.',
                    'payment'
                );
            }

            $conn->commit();

            header("Location: admin-orders.php?rejected=1");
            exit;
        } catch (Throwable $exception) {
            $conn->rollback();
            $message = 'Failed to reject payment: ' . $exception->getMessage();
            $messageType = 'error';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_direct_instructor_payout'])) {
    $orderId = (int) ($_POST['order_id'] ?? 0);
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
        $message = 'Please provide valid payout details.';
        $messageType = 'error';
    } else {
        try {
            $conn->begin_transaction();

            $checkSql = "
                SELECT 
                    o.id AS order_id,
                    o.order_status,
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
                throw new Exception('Failed to check order payment.');
            }

            $checkStmt->bind_param("ii", $orderId, $paymentId);
            $checkStmt->execute();

            $checkResult = $checkStmt->get_result();

            if (!$checkResult || $checkResult->num_rows !== 1) {
                throw new Exception('Order/payment not found.');
            }

            $checkRow = $checkResult->fetch_assoc();
            $checkStmt->close();

            if ($checkRow['order_status'] !== 'paid' || $checkRow['payment_status'] !== 'paid') {
                throw new Exception('Student payment must be verified before instructor payout.');
            }

            $earningSql = "
                SELECT 
                    id,
                    gross_amount
                FROM instructor_earnings
                WHERE order_id = ?
                  AND instructor_id = ?
                  AND earning_status = 'available'
                FOR UPDATE
            ";

            $earningStmt = $conn->prepare($earningSql);

            if (!$earningStmt) {
                throw new Exception('Failed to load instructor earnings.');
            }

            $earningStmt->bind_param("ii", $orderId, $instructorId);
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
                  AND instructor_id = ?
                  AND earning_status = 'available'
            ";

            $updateEarningStmt = $conn->prepare($updateEarningSql);

            if (!$updateEarningStmt) {
                throw new Exception('Failed to update instructor earnings.');
            }

            $updateEarningStmt->bind_param(
                "dddii",
                $commissionRate,
                $commissionRate,
                $commissionRate,
                $orderId,
                $instructorId
            );

            if (!$updateEarningStmt->execute()) {
                throw new Exception('Failed to mark instructor earning as paid.');
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
                throw new Exception('Failed to prepare payout insert.');
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
                throw new Exception('Failed to record instructor payout.');
            }

            $payoutStmt->close();

            send_notification(
                $conn,
                $instructorId,
                'Instructor payout sent',
                'The payout for order #' . $orderId . ' was sent directly. Transaction reference: ' . $transactionReference . '.',
                'payout'
            );

            $conn->commit();

            header("Location: admin-orders.php?payout_sent=1");
            exit;

        } catch (Exception $e) {
            $conn->rollback();

            $message = 'Instructor payout failed: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$whereParts = [];
$params = [];
$types = '';

if ($search !== '') {
    $whereParts[] = "(student.full_name LIKE ? OR student.email LIKE ? OR c.title LIKE ? OR p.transaction_id LIKE ?)";
    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= 'ssss';
}

if ($statusFilter !== '') {
    $whereParts[] = "o.order_status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

$whereSql = '';

if (!empty($whereParts)) {
    $whereSql = 'WHERE ' . implode(' AND ', $whereParts);
}

$orders = [];

$sql = "
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
        pp.note AS proof_note,

        GROUP_CONCAT(DISTINCT c.title ORDER BY c.title SEPARATOR '||') AS course_titles,
        GROUP_CONCAT(DISTINCT instructor.full_name ORDER BY instructor.full_name SEPARATOR '||') AS instructor_names,
        COUNT(DISTINCT oi.id) AS total_items
    FROM orders o
    INNER JOIN users student ON o.student_id = student.id
    LEFT JOIN payments p ON p.order_id = o.id
    LEFT JOIN payment_proofs pp ON pp.payment_id = p.id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN courses c ON oi.course_id = c.id
    LEFT JOIN users instructor ON oi.instructor_id = instructor.id
    $whereSql
    GROUP BY 
        o.id,
        o.student_id,
        o.original_amount,
        o.discount_amount,
        o.final_amount,
        o.order_status,
        o.created_at,
        student.full_name,
        student.email,
        student.phone,
        p.id,
        p.payment_method,
        p.payment_type,
        p.transaction_id,
        p.paid_amount,
        p.payment_status,
        p.verified_at,
        pp.id,
        pp.proof_image,
        pp.note
    ORDER BY o.created_at DESC
";

$stmt = $conn->prepare($sql);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }

    $stmt->close();
}

function get_count(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);

    if ($result) {
        $row = $result->fetch_assoc();
        return (int) ($row['total'] ?? 0);
    }

    return 0;
}

function get_sum(mysqli $conn, string $sql): float
{
    $result = $conn->query($sql);

    if ($result) {
        $row = $result->fetch_assoc();
        return (float) ($row['total'] ?? 0);
    }

    return 0;
}

$totalOrders = get_count($conn, "SELECT COUNT(*) AS total FROM orders");
$paidOrders = get_count($conn, "SELECT COUNT(*) AS total FROM orders WHERE order_status = 'paid'");
$pendingOrders = get_count($conn, "SELECT COUNT(*) AS total FROM orders WHERE order_status = 'pending'");
$totalRevenue = get_sum($conn, "SELECT COALESCE(SUM(final_amount), 0) AS total FROM orders WHERE order_status = 'paid'");

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

    if ($status === 'failed' || $status === 'cancelled' || $status === 'rejected') {
        return 'status-failed';
    }

    return 'status-pending';
}

function split_list($value)
{
    $value = (string) $value;

    if ($value === '') {
        return [];
    }

    return array_filter(explode('||', $value));
}

function get_order_instructor_payouts(mysqli $conn, int $orderId): array
{
    $rows = [];

    $sql = "
        SELECT
            ie.instructor_id,
            instructor.full_name AS instructor_name,
            instructor.email AS instructor_email,

            COUNT(ie.id) AS earning_count,
            SUM(ie.gross_amount) AS gross_amount,
            MAX(ie.commission_rate) AS commission_rate,
            SUM(ie.commission_amount) AS commission_amount,
            SUM(ie.instructor_amount) AS instructor_amount,

            SUM(CASE WHEN ie.earning_status = 'paid' THEN 1 ELSE 0 END) AS paid_count,

            GROUP_CONCAT(DISTINCT p.transaction_reference SEPARATOR ', ') AS payout_references
        FROM instructor_earnings ie
        INNER JOIN users instructor ON ie.instructor_id = instructor.id
        LEFT JOIN payouts p 
            ON p.order_id = ie.order_id
           AND p.instructor_id = ie.instructor_id
           AND p.payout_source = 'direct_order'
        WHERE ie.order_id = ?
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

    $stmt->bind_param("i", $orderId);
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

function get_instructor_payout_details(mysqli $conn, int $instructorId): ?array
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

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/admin_navbar.php';
?>


<main class="admin-orders-page">
    <section class="admin-orders-wrapper">

        <div class="admin-orders-header">
            <div>
                <p class="page-label">Admin Panel</p>
                <h1>Orders Management</h1>
                <p>
                    Review student purchases, verify payments, enroll students, and send money to instructors.
                </p>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="admin-alert <?php echo h($messageType); ?>">
                <?php echo h($message); ?>
            </div>
        <?php endif; ?>

        <div class="order-stats-grid">
            <a href="admin-orders.php" class="stat-card stat-link">
                <span>Total Orders</span>
                <strong><?php echo $totalOrders; ?></strong>
                <p>All purchase orders</p>
            </a>

            <a href="admin-orders.php?status=pending" class="stat-card stat-link pending">
                <span>Pending</span>
                <strong><?php echo $pendingOrders; ?></strong>
                <p>Need verification</p>
            </a>

            <a href="admin-orders.php?status=paid" class="stat-card stat-link paid">
                <span>Paid</span>
                <strong><?php echo $paidOrders; ?></strong>
                <p>Verified orders</p>
            </a>

            <div class="stat-card revenue">
                <span>Revenue</span>
                <strong>Rs. <?php echo number_format($totalRevenue, 2); ?></strong>
                <p>Paid order revenue</p>
            </div>
        </div>

        <form method="GET" class="order-filter-box">

            <div class="form-group">
                <label>Search</label>
                <input 
                    type="text" 
                    name="search" 
                    value="<?php echo h($search); ?>" 
                    placeholder="Search student, email, course, transaction"
                >
            </div>

            <div class="form-group">
                <label>Order Status</label>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="paid" <?php echo $statusFilter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="failed" <?php echo $statusFilter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                    <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit">Apply Filter</button>
                <a href="admin-orders.php">Reset</a>
            </div>

        </form>

        <?php if (empty($orders)): ?>

            <div class="empty-orders-box">
                <div class="empty-icon">No orders</div>
                <h2>No orders found</h2>
                <p>No order matched your current filter/search.</p>
            </div>

        <?php else: ?>

            <div class="orders-grid">

                <?php foreach ($orders as $order): ?>
                    <?php
                        $orderId = (int) $order['order_id'];
                        $paymentId = (int) ($order['payment_id'] ?? 0);
                        $proofId = (int) ($order['proof_id'] ?? 0);

                        $modalId = 'orderModal' . $orderId;

                        $courseTitles = split_list($order['course_titles']);
                        $instructorNames = split_list($order['instructor_names']);

                        $proofPath = $proofId > 0
                            ? 'admin-view-payment-proof.php?proof_id=' . $proofId
                            : '';
                    ?>

                  <a 
    href="admin-order-details.php?order_id=<?php echo $orderId; ?>"
    class="order-card order-card-link"
>

                        <div class="order-card-top">
                            <div>
                                <span class="order-number">Order #<?php echo $orderId; ?></span>
                                <h2><?php echo h($order['student_name']); ?></h2>
                            </div>

                            <span class="status-pill <?php echo status_class($order['order_status']); ?>">
                                <?php echo status_label($order['order_status']); ?>
                            </span>
                        </div>

                        <div class="order-basic-info">
                            <div>
                                <span>Email</span>
                                <strong><?php echo h($order['student_email']); ?></strong>
                            </div>

                            <div>
                                <span>Courses</span>
                                <strong><?php echo (int) $order['total_items']; ?> item(s)</strong>
                            </div>

                            <div>
                                <span>Final Amount</span>
                                <strong>Rs. <?php echo number_format((float) $order['final_amount'], 2); ?></strong>
                            </div>

                            <div>
                                <span>Payment</span>
                                <strong><?php echo h(status_label($order['payment_status'] ?? 'pending')); ?></strong>
                            </div>
                        </div>

                        <div class="click-hint">
                            Click to view full order details
                        </div>

                    </article>

                    <div class="order-modal-overlay" id="<?php echo h($modalId); ?>">
                        <div class="order-modal-card">

                            <div class="modal-header">
                                <div>
                                    <p class="modal-label">Order Details</p>
                                    <h2>Order #<?php echo $orderId; ?></h2>
                                </div>

                                <button type="button" class="close-modal-btn" data-close-modal>
                                    &times;
                                </button>
                            </div>

                            <div class="review-section">
                                <h3>Student Details</h3>

                                <div class="details-grid">
                                    <div class="detail-box">
                                        <span>Name</span>
                                        <strong><?php echo h($order['student_name']); ?></strong>
                                    </div>

                                    <div class="detail-box">
                                        <span>Email</span>
                                        <strong><?php echo h($order['student_email']); ?></strong>
                                    </div>

                                    <div class="detail-box">
                                        <span>Phone</span>
                                        <strong><?php echo h(display_value($order['student_phone'])); ?></strong>
                                    </div>

                                    <div class="detail-box">
                                        <span>Order Date</span>
                                        <strong><?php echo h(date('M d, Y h:i A', strtotime($order['order_date']))); ?></strong>
                                    </div>
                                </div>
                            </div>

                            <div class="review-section">
                                <h3>Purchased Courses</h3>

                                <div class="list-box">
                                    <?php if (empty($courseTitles)): ?>
                                        <p>No course item found.</p>
                                    <?php else: ?>
                                        <?php foreach ($courseTitles as $courseTitle): ?>
                                            <div class="list-item">
                                                Course: <?php echo h($courseTitle); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="review-section">
                                <h3>Related Instructors</h3>

                                <div class="list-box">
                                    <?php if (empty($instructorNames)): ?>
                                        <p>No instructor found.</p>
                                    <?php else: ?>
                                        <?php foreach ($instructorNames as $instructorName): ?>
                                            <div class="list-item">
                                                Instructor: <?php echo h($instructorName); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="review-section">
                                <h3>Amount Details</h3>

                                <div class="details-grid">
                                    <div class="detail-box">
                                        <span>Original Amount</span>
                                        <strong>Rs. <?php echo number_format((float) $order['original_amount'], 2); ?></strong>
                                    </div>

                                    <div class="detail-box">
                                        <span>Discount</span>
                                        <strong>Rs. <?php echo number_format((float) $order['discount_amount'], 2); ?></strong>
                                    </div>

                                    <div class="detail-box">
                                        <span>Final Amount</span>
                                        <strong>Rs. <?php echo number_format((float) $order['final_amount'], 2); ?></strong>
                                    </div>

                                    <div class="detail-box">
                                        <span>Paid Amount</span>
                                        <strong>Rs. <?php echo number_format((float) ($order['paid_amount'] ?? 0), 2); ?></strong>
                                    </div>
                                </div>
                            </div>

                            <div class="review-section">
                                <h3>Payment Details</h3>

                                <div class="details-grid">
                                    <div class="detail-box">
                                        <span>Payment Method</span>
                                        <strong><?php echo h(display_value($order['payment_method'])); ?></strong>
                                    </div>

                                    <div class="detail-box">
                                        <span>Payment Type</span>
                                        <strong><?php echo h(display_value($order['payment_type'])); ?></strong>
                                    </div>

                                    <div class="detail-box">
                                        <span>Transaction ID</span>
                                        <strong><?php echo h(display_value($order['transaction_id'])); ?></strong>
                                    </div>

                                    <div class="detail-box">
                                        <span>Payment Status</span>
                                        <strong><?php echo h(status_label($order['payment_status'] ?? 'pending')); ?></strong>
                                    </div>
                                </div>
                            </div>

                            <div class="review-section">
                                <h3>Payment Proof</h3>

                                <?php if ($proofId > 0): ?>
                                    <div class="proof-preview-box">
                                        <img 
                                            src="<?php echo h($proofPath); ?>" 
                                            alt="Payment proof"
                                        >
                                    </div>

                                    <a 
                                        href="<?php echo h($proofPath); ?>" 
                                        target="_blank" 
                                        class="open-proof-btn"
                                    >
                                        Open Proof Full Size
                                    </a>

                                    <?php if (!empty($order['proof_note'])): ?>
                                        <div class="proof-note">
                                            <strong>Student Note:</strong>
                                            <?php echo h($order['proof_note']); ?>
                                        </div>
                                    <?php endif; ?>

                                <?php else: ?>
                                    <div class="no-proof-box">
                                        No payment proof uploaded.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if (($order['order_status'] ?? '') === 'paid' && ($order['payment_status'] ?? '') === 'paid'): ?>
                                <?php $instructorPayouts = get_order_instructor_payouts($conn, $orderId); ?>

                                <div class="review-section">
                                    <h3>Send Money to Instructor</h3>

                                    <?php if (empty($instructorPayouts)): ?>

                                        <div class="no-proof-box">
                                            Instructor earning has not been created for this order.
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
                                                $payoutDetails = get_instructor_payout_details($conn, $instructorId);
                                            ?>

                                            <div class="instructor-payout-box">

                                                <div class="payout-top">
                                                    <div>
                                                        <strong><?php echo h($payout['instructor_name']); ?></strong>
                                                        <p><?php echo h($payout['instructor_email']); ?></p>
                                                    </div>

                                                    <?php if ($isInstructorPaid): ?>
                                                        <span class="payout-status paid">Paid to Instructor</span>
                                                    <?php else: ?>
                                                        <span class="payout-status pending">Pending Admin Payout</span>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="payout-breakdown">
                                                    <div>
                                                        <span>Gross Course Amount</span>
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

                                                <div class="instructor-payment-details">
                                                    <h4>Instructor Payment Details</h4>

                                                    <?php if (!$payoutDetails): ?>

                                                        <div class="no-proof-box">
                                                            Instructor has not added bank/eSewa/Khalti/QR details yet.
                                                        </div>

                                                    <?php else: ?>

                                                        <?php if (!empty($payoutDetails['bank_name']) || !empty($payoutDetails['account_number'])): ?>
                                                            <p><strong>Bank:</strong> <?php echo h(display_value($payoutDetails['bank_name'])); ?></p>
                                                            <p><strong>Account Name:</strong> <?php echo h(display_value($payoutDetails['account_name'])); ?></p>
                                                            <p><strong>Account Number:</strong> <?php echo h(display_value($payoutDetails['account_number'])); ?></p>
                                                            <p><strong>Branch:</strong> <?php echo h(display_value($payoutDetails['branch_name'])); ?></p>
                                                        <?php endif; ?>

                                                        <?php if (!empty($payoutDetails['esewa_number'])): ?>
                                                            <p><strong>eSewa:</strong> <?php echo h($payoutDetails['esewa_number']); ?></p>
                                                        <?php endif; ?>

                                                        <?php if (!empty($payoutDetails['khalti_number'])): ?>
                                                            <p><strong>Khalti:</strong> <?php echo h($payoutDetails['khalti_number']); ?></p>
                                                        <?php endif; ?>

                                                        <?php if (!empty($payoutDetails['qr_image'])): ?>
                                                            <div class="admin-qr-box">
                                                                <img src="assets/uploads/instructor_qr/<?php echo h($payoutDetails['qr_image']); ?>" alt="Instructor QR">
                                                            </div>
                                                        <?php endif; ?>

                                                    <?php endif; ?>
                                                </div>

                                                <?php if ($isInstructorPaid): ?>

                                                    <div class="paid-reference-box">
                                                        <strong>Transaction Reference:</strong>
                                                        <?php echo h($payout['payout_references'] ?: 'Recorded'); ?>
                                                    </div>

                                                <?php else: ?>

                                                    <form method="POST" class="direct-payout-form">
                                                          <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                                                        <input type="hidden" name="payment_id" value="<?php echo $paymentId; ?>">
                                                        <input type="hidden" name="instructor_id" value="<?php echo $instructorId; ?>">

                                                        <div class="payout-form-grid">

                                                            <div class="form-group">
                                                                <label>Configured Commission %</label>
                                                                <input 
                                                                    type="number"
                                                                    step="0.01"
                                                                    min="0"
                                                                    max="100"
                                                                    value="<?php echo number_format($commissionRate, 2, '.', ''); ?>"
                                                                    readonly
                                                                >
                                                            </div>

                                                            <div class="form-group">
                                                                <label>Payment Method</label>
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
                                                                    placeholder="Bank/eSewa/Khalti reference"
                                                                    required
                                                                >
                                                            </div>

                                                        </div>

                                                        <div class="form-group">
                                                            <label>Admin Note</label>
                                                            <textarea
                                                                name="admin_note"
                                                                rows="3"
                                                                placeholder="Optional note"
                                                            ></textarea>
                                                        </div>

                                                        <button type="submit" name="send_direct_instructor_payout" class="action-btn verify">
                                                            Send Money to Instructor
                                                        </button>
                                                    </form>

                                                <?php endif; ?>

                                            </div>

                                        <?php endforeach; ?>

                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="modal-actions">

                                <?php if (($order['payment_status'] ?? '') !== 'paid' && $paymentId > 0): ?>

                                    <form method="POST">
                                          <?php echo csrf_field(); ?>
                                        <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                                        <input type="hidden" name="payment_id" value="<?php echo $paymentId; ?>">

                                        <button type="submit" name="verify_payment" class="action-btn verify">
                                            Verify Payment & Enroll Student
                                        </button>
                                    </form>

                                    <form method="POST">
                                          <?php echo csrf_field(); ?>
                                        <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                                        <input type="hidden" name="payment_id" value="<?php echo $paymentId; ?>">

                                        <button type="submit" name="reject_payment" class="action-btn reject">
                                            Reject Payment
                                        </button>
                                    </form>

                                <?php else: ?>

                                    <button type="button" class="action-btn disabled" disabled>
                                        Payment Already Verified
                                    </button>

                                <?php endif; ?>

                            </div>

                        </div>
                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>
</main>

<script src="assets/js/admin_orders.js?v=<?php echo (int) filemtime(PUBLIC_PATH . '/assets/js/admin_orders.js'); ?>"></script>

</body>
</html>
