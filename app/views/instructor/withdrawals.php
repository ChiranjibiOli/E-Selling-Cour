<?php

require_once __DIR__ . '/../../middleware/InstructorMiddleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/notification_helper.php';

InstructorMiddleware::handle();

$user = Auth::user();
$instructorId = (int) ($user['id'] ?? 0);

$message = '';
$messageType = '';

$payoutDetails = [];
$payoutStmt = $conn->prepare("SELECT * FROM instructor_bank_details WHERE instructor_id = ? LIMIT 1");
$payoutStmt->bind_param('i', $instructorId);
$payoutStmt->execute();
$payoutDetails = $payoutStmt->get_result()->fetch_assoc() ?: [];
$payoutStmt->close();

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money($amount)
{
    return 'Rs. ' . number_format((float) $amount, 2);
}

function request_status_label($status)
{
    if ($status === 'pending') {
        return 'Pending';
    }

    if ($status === 'approved') {
        return 'Approved';
    }

    if ($status === 'rejected') {
        return 'Rejected';
    }

    if ($status === 'paid') {
        return 'Paid';
    }

    return ucfirst((string) $status);
}

function request_status_class($status)
{
    if ($status === 'paid') {
        return 'status-paid';
    }

    if ($status === 'approved') {
        return 'status-approved';
    }

    if ($status === 'rejected') {
        return 'status-rejected';
    }

    return 'status-pending';
}

function get_available_balance(mysqli $conn, int $instructorId): float
{
    $sql = "
        SELECT COALESCE(SUM(instructor_amount), 0) AS total
        FROM instructor_earnings
        WHERE instructor_id = ?
          AND earning_status = 'available'
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param("i", $instructorId);
    $stmt->execute();

    $result = $stmt->get_result();
    $total = 0;

    if ($result) {
        $row = $result->fetch_assoc();
        $total = (float) ($row['total'] ?? 0);
    }

    $stmt->close();

    return $total;
}

function get_count_available_earnings(mysqli $conn, int $instructorId): int
{
    $sql = "
        SELECT COUNT(*) AS total
        FROM instructor_earnings
        WHERE instructor_id = ?
          AND earning_status = 'available'
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param("i", $instructorId);
    $stmt->execute();

    $result = $stmt->get_result();
    $total = 0;

    if ($result) {
        $row = $result->fetch_assoc();
        $total = (int) ($row['total'] ?? 0);
    }

    $stmt->close();

    return $total;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_withdrawal'])) {
    $paymentMethod = trim($_POST['payment_method'] ?? 'bank');
    $accountName = trim($_POST['account_name'] ?? '');
    $accountNumber = trim($_POST['account_number'] ?? '');
    $bankName = trim($_POST['bank_name'] ?? '');
    $esewaNumber = trim($_POST['esewa_number'] ?? '');
    $khaltiNumber = trim($_POST['khalti_number'] ?? '');
    $instructorNote = trim($_POST['instructor_note'] ?? '');

    $errors = [];

    if (!in_array($paymentMethod, ['bank', 'esewa', 'khalti'], true)) {
        $errors[] = 'Invalid payment method.';
    }

    if ($paymentMethod === 'bank') {
        if ($accountName === '') {
            $errors[] = 'Bank account name is required.';
        }

        if ($accountNumber === '') {
            $errors[] = 'Bank account number is required.';
        }

        if ($bankName === '') {
            $errors[] = 'Bank name is required.';
        }
    }

    if ($paymentMethod === 'esewa' && $esewaNumber === '') {
        $errors[] = 'eSewa number is required.';
    }

    if ($paymentMethod === 'khalti' && $khaltiNumber === '') {
        $errors[] = 'Khalti number is required.';
    }

    $availableBalance = get_available_balance($conn, $instructorId);
    $availableCount = get_count_available_earnings($conn, $instructorId);

    if ($availableBalance <= 0 || $availableCount <= 0) {
        $errors[] = 'You do not have available balance for withdrawal.';
    }

    if (empty($errors)) {
        try {
            $conn->begin_transaction();

            $earningSql = "
                SELECT id, instructor_amount
                FROM instructor_earnings
                WHERE instructor_id = ?
                  AND earning_status = 'available'
                ORDER BY id
                FOR UPDATE
            ";

            $earningStmt = $conn->prepare($earningSql);

            if (!$earningStmt) {
                throw new Exception('Failed to prepare earnings list.');
            }

            $earningStmt->bind_param("i", $instructorId);
            $earningStmt->execute();

            $earningResult = $earningStmt->get_result();
            $earningIds = [];
            $availableBalance = 0.0;

            while ($earning = $earningResult->fetch_assoc()) {
                $earningIds[] = (int) $earning['id'];
                $availableBalance += (float) $earning['instructor_amount'];
            }

            $earningStmt->close();

            if (empty($earningIds) || $availableBalance <= 0) {
                throw new Exception('No available earnings found.');
            }

            $requestSql = "
                INSERT INTO withdrawal_requests (
                    instructor_id,
                    requested_amount,
                    payment_method,
                    account_name,
                    account_number,
                    bank_name,
                    esewa_number,
                    khalti_number,
                    request_status,
                    instructor_note
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
            ";

            $requestStmt = $conn->prepare($requestSql);

            if (!$requestStmt) {
                throw new Exception('Failed to prepare withdrawal request.');
            }

            $requestStmt->bind_param(
                "idsssssss",
                $instructorId,
                $availableBalance,
                $paymentMethod,
                $accountName,
                $accountNumber,
                $bankName,
                $esewaNumber,
                $khaltiNumber,
                $instructorNote
            );

            if (!$requestStmt->execute()) {
                throw new Exception('Failed to create withdrawal request.');
            }

            $withdrawalRequestId = $conn->insert_id;
            $requestStmt->close();

            $mapSql = "
                INSERT INTO withdrawal_request_earnings (
                    withdrawal_request_id,
                    earning_id
                ) VALUES (?, ?)
            ";

            $mapStmt = $conn->prepare($mapSql);

            if (!$mapStmt) {
                throw new Exception('Failed to prepare withdrawal earning mapping.');
            }

            foreach ($earningIds as $earningId) {
                $mapStmt->bind_param("ii", $withdrawalRequestId, $earningId);

                if (!$mapStmt->execute()) {
                    throw new Exception('Failed to map an earning to the withdrawal request.');
                }
            }

            $mapStmt->close();

            $updateSql = "
                UPDATE instructor_earnings
                SET earning_status = 'withdraw_requested'
                WHERE id = ?
                  AND instructor_id = ?
                  AND earning_status = 'available'
            ";

            $updateStmt = $conn->prepare($updateSql);

            if (!$updateStmt) {
                throw new Exception('Failed to update earnings status.');
            }

            foreach ($earningIds as $earningId) {
                $updateStmt->bind_param("ii", $earningId, $instructorId);

                if (!$updateStmt->execute() || $updateStmt->affected_rows !== 1) {
                    throw new Exception('An earning changed while the withdrawal was being created.');
                }
            }

            $updateStmt->close();

            send_notification_to_role(
                $conn,
                'admin',
                'New withdrawal request',
                'Instructor #' . $instructorId . ' requested ' . money($availableBalance) . ' for payout.',
                'withdrawal'
            );

            $conn->commit();

            header("Location: instructor-withdrawals.php?requested=1");
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            error_log('Withdrawal request failed: ' . $e->getMessage());
            $message = 'Withdrawal request failed. Please try again.';
            $messageType = 'error';
        }
    } else {
        $message = implode(' ', $errors);
        $messageType = 'error';
    }
}

if (isset($_GET['requested'])) {
    $message = 'Withdrawal request submitted successfully. Please wait for admin payout.';
    $messageType = 'success';
}

$availableBalance = get_available_balance($conn, $instructorId);
$availableCount = get_count_available_earnings($conn, $instructorId);

$processingStmt = $conn->prepare("SELECT COALESCE(SUM(requested_amount),0) AS total FROM withdrawal_requests WHERE instructor_id = ? AND request_status IN ('pending','approved')");
$processingStmt->bind_param('i', $instructorId);
$processingStmt->execute();
$processingAmount = (float) ($processingStmt->get_result()->fetch_assoc()['total'] ?? 0);
$processingStmt->close();

$paidStmt = $conn->prepare("SELECT COALESCE(SUM(paid_amount),0) AS total FROM payouts WHERE instructor_id = ? AND payout_status = 'paid'");
$paidStmt->bind_param('i', $instructorId);
$paidStmt->execute();
$paidAmount = (float) ($paidStmt->get_result()->fetch_assoc()['total'] ?? 0);
$paidStmt->close();

$withdrawals = [];

$withdrawalSql = "
    SELECT
        id,
        requested_amount,
        payment_method,
        account_name,
        account_number,
        bank_name,
        esewa_number,
        khalti_number,
        request_status,
        instructor_note,
        admin_note,
        requested_at,
        processed_at
    FROM withdrawal_requests
    WHERE instructor_id = ?
    ORDER BY requested_at DESC
";

$withdrawalStmt = $conn->prepare($withdrawalSql);

if ($withdrawalStmt) {
    $withdrawalStmt->bind_param("i", $instructorId);
    $withdrawalStmt->execute();

    $withdrawalResult = $withdrawalStmt->get_result();

    if ($withdrawalResult) {
        while ($row = $withdrawalResult->fetch_assoc()) {
            $withdrawals[] = $row;
        }
    }

    $withdrawalStmt->close();
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/instructor_navbar.php';
?>



<main class="instructor-withdrawals-page">
    <section class="instructor-withdrawals-wrapper">

        <div class="withdrawals-header">
            <div>
                <p class="page-label">Finance</p>
                <h1>Withdrawals</h1>
                <p>
                    Move verified available earnings to your saved bank or wallet account and track every payout state.
                </p>
            </div>

            <a href="instructor-sales.php" class="back-sales-btn">
                Back to Earnings
            </a>
        </div>

        <?php if ($message !== ''): ?>
            <div class="withdraw-alert <?php echo h($messageType); ?>">
                <?php echo h($message); ?>
            </div>
        <?php endif; ?>

        <div class="withdrawal-summary-grid">

            <div class="summary-card success">
                <span>Available Balance</span>
                <strong><?php echo money($availableBalance); ?></strong>
                <p>Ready for withdrawal</p>
            </div>

            <div class="summary-card">
                <span>Available Earnings</span>
                <strong><?php echo $availableCount; ?></strong>
                <p>Verified earning records</p>
            </div>

            <div class="summary-card warning">
                <span>Processing</span>
                <strong><?php echo money($processingAmount); ?></strong>
                <p>Requested but not paid</p>
            </div>

            <div class="summary-card">
                <span>Total Paid Out</span>
                <strong><?php echo money($paidAmount); ?></strong>
                <p>Completed payouts</p>
            </div>

        </div>

        <div class="withdrawals-layout">

            <form method="POST" class="withdrawal-form-card">
  <?php echo csrf_field(); ?>
                <div class="form-section">
                    <h2>Request Withdrawal</h2>
                    <p>
                        This request will include your full available balance:
                        <strong><?php echo money($availableBalance); ?></strong>
                    </p>

                    <?php if ($availableBalance <= 0): ?>
                        <div class="no-balance-box">
                            You currently have no available balance to withdraw.
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="payment_method" id="paymentMethod">
                            <option value="bank">Bank Transfer</option>
                            <option value="esewa">eSewa</option>
                            <option value="khalti">Khalti</option>
                        </select>
                    </div>
                </div>

                <div class="form-section method-box" id="bankFields">
                    <h2>Bank Details</h2>

                    <div class="form-group">
                        <label>Account Name</label>
                        <input type="text" name="account_name" value="<?php echo h($payoutDetails['account_name'] ?? ''); ?>" placeholder="Account holder name">
                    </div>

                    <div class="form-group">
                        <label>Account Number</label>
                        <input type="text" name="account_number" value="<?php echo h($payoutDetails['account_number'] ?? ''); ?>" placeholder="Bank account number">
                    </div>

                    <div class="form-group">
                        <label>Bank Name</label>
                        <input type="text" name="bank_name" value="<?php echo h($payoutDetails['bank_name'] ?? ''); ?>" placeholder="Bank name">
                    </div>
                </div>

                <div class="form-section method-box hidden" id="esewaFields">
                    <h2>eSewa Details</h2>

                    <div class="form-group">
                        <label>eSewa Number</label>
                        <input type="text" name="esewa_number" value="<?php echo h($payoutDetails['esewa_number'] ?? ''); ?>" placeholder="eSewa mobile number">
                    </div>
                </div>

                <div class="form-section method-box hidden" id="khaltiFields">
                    <h2>Khalti Details</h2>

                    <div class="form-group">
                        <label>Khalti Number</label>
                        <input type="text" name="khalti_number" value="<?php echo h($payoutDetails['khalti_number'] ?? ''); ?>" placeholder="Khalti mobile number">
                    </div>
                </div>

                <div class="form-section">
                    <h2>Note</h2>

                    <div class="form-group">
                        <label>Note for Admin</label>
                        <textarea
                            name="instructor_note"
                            rows="4"
                            placeholder="Optional message for admin"
                        ></textarea>
                    </div>
                </div>

                <button
                    type="submit"
                    name="request_withdrawal"
                    class="request-btn"
                    <?php echo $availableBalance <= 0 ? 'disabled' : ''; ?>
                >
                    Request Full Available Balance
                </button>

            </form>

            <aside class="withdrawal-history-card">
                <h2>Withdrawal History</h2>

                <?php if (empty($withdrawals)): ?>

                    <div class="empty-history-box">
                        No withdrawal requests yet.
                    </div>

                <?php else: ?>

                    <div class="withdrawal-list">

                        <?php foreach ($withdrawals as $withdrawal): ?>
                            <article class="withdrawal-item">

                                <div class="withdrawal-top">
                                    <div>
                                        <strong>
                                            Request #<?php echo (int) $withdrawal['id']; ?>
                                        </strong>

                                        <p>
                                            <?php echo h(date('M d, Y h:i A', strtotime($withdrawal['requested_at']))); ?>
                                        </p>
                                    </div>

                                    <span class="status-pill <?php echo request_status_class($withdrawal['request_status']); ?>">
                                        <?php echo request_status_label($withdrawal['request_status']); ?>
                                    </span>
                                </div>

                                <div class="withdrawal-detail-grid">
                                    <div>
                                        <span>Amount</span>
                                        <strong><?php echo money($withdrawal['requested_amount']); ?></strong>
                                    </div>

                                    <div>
                                        <span>Method</span>
                                        <strong><?php echo h(ucfirst($withdrawal['payment_method'])); ?></strong>
                                    </div>
                                </div>

                                <?php if (!empty($withdrawal['admin_note'])): ?>
                                    <div class="admin-note-box">
                                        <span>Admin Note</span>
                                        <p><?php echo h($withdrawal['admin_note']); ?></p>
                                    </div>
                                <?php endif; ?>

                            </article>
                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </aside>

        </div>

    </section>
</main>

<script src="assets/js/instructor_withdrawals.js"></script>

</body>
</html>
