<?php

require_once __DIR__ . '/../../middleware/AdminMiddleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/notification_helper.php';

AdminMiddleware::handle();

$admin = Auth::user();
$adminId = (int) ($admin['id'] ?? 0);

$message = '';
$messageType = '';

$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

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

    if ($status === 'paid') {
        return 'Paid';
    }

    if ($status === 'rejected') {
        return 'Rejected';
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

function upload_payout_proof(array $file, array &$errors): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Payout proof upload failed.';
        return null;
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        $errors[] = 'Payout proof must be less than 5MB.';
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf'
    ];

    if (!array_key_exists($mimeType, $allowedTypes)) {
        $errors[] = 'Only JPG, PNG, or PDF payout proof is allowed.';
        return null;
    }

    $uploadDir = __DIR__ . '/../../../storage/private_uploads/payout_proofs';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0750, true);
    }

    $fileName = 'payout-proof-' . bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
    $destination = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $errors[] = 'Failed to save payout proof.';
        return null;
    }

    return $fileName;
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

function get_count(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);

    if ($result) {
        $row = $result->fetch_assoc();
        return (int) ($row['total'] ?? 0);
    }

    return 0;
}

/*
|--------------------------------------------------------------------------
| Mark withdrawal as paid
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $transactionReference = trim($_POST['transaction_reference'] ?? '');
    $adminNote = trim($_POST['admin_note'] ?? '');

    $errors = [];

    if ($requestId <= 0) {
        $errors[] = 'Invalid withdrawal request.';
    }

    if ($transactionReference === '') {
        $errors[] = 'Transaction reference is required.';
    }

    $proofFile = upload_payout_proof($_FILES['payout_proof'] ?? [], $errors);

    if (empty($errors)) {
        try {
            $conn->begin_transaction();

            $requestSql = "
                SELECT *
                FROM withdrawal_requests
                WHERE id = ?
                  AND request_status IN ('pending', 'approved')
                LIMIT 1
                FOR UPDATE
            ";

            $requestStmt = $conn->prepare($requestSql);

            if (!$requestStmt) {
                throw new Exception('Failed to prepare withdrawal request.');
            }

            $requestStmt->bind_param("i", $requestId);
            $requestStmt->execute();

            $requestResult = $requestStmt->get_result();

            if (!$requestResult || $requestResult->num_rows !== 1) {
                throw new Exception('Withdrawal request not found or already processed.');
            }

            $request = $requestResult->fetch_assoc();
            $requestStmt->close();

            $instructorId = (int) $request['instructor_id'];
            $requestedAmount = (float) $request['requested_amount'];
            $paymentMethod = $request['payment_method'];

            $payoutSql = "
                INSERT INTO payouts (
                    withdrawal_request_id,
                    instructor_id,
                    paid_amount,
                    payment_method,
                    transaction_reference,
                    proof_image,
                    payout_status,
                    paid_by,
                    admin_note
                ) VALUES (?, ?, ?, ?, ?, ?, 'paid', ?, ?)
            ";

            $payoutStmt = $conn->prepare($payoutSql);

            if (!$payoutStmt) {
                throw new Exception('Failed to prepare payout record.');
            }

            $payoutStmt->bind_param(
                "iidsssis",
                $requestId,
                $instructorId,
                $requestedAmount,
                $paymentMethod,
                $transactionReference,
                $proofFile,
                $adminId,
                $adminNote
            );

            if (!$payoutStmt->execute()) {
                throw new Exception('Failed to save payout record.');
            }

            $payoutStmt->close();

            $updateRequestSql = "
                UPDATE withdrawal_requests
                SET 
                    request_status = 'paid',
                    admin_note = ?,
                    processed_by = ?,
                    processed_at = NOW()
                WHERE id = ?
            ";

            $updateRequestStmt = $conn->prepare($updateRequestSql);

            if (!$updateRequestStmt) {
                throw new Exception('Failed to update withdrawal request.');
            }

            $updateRequestStmt->bind_param(
                "sii",
                $adminNote,
                $adminId,
                $requestId
            );

            $updateRequestStmt->execute();
            $updateRequestStmt->close();

            $updateEarningsSql = "
                UPDATE instructor_earnings ie
                INNER JOIN withdrawal_request_earnings wre ON wre.earning_id = ie.id
                SET 
                    ie.earning_status = 'paid',
                    ie.paid_at = NOW()
                WHERE wre.withdrawal_request_id = ?
                  AND ie.instructor_id = ?
                  AND ie.earning_status = 'withdraw_requested'
            ";

            $updateEarningsStmt = $conn->prepare($updateEarningsSql);

            if (!$updateEarningsStmt) {
                throw new Exception('Failed to update instructor earnings.');
            }

            $updateEarningsStmt->bind_param("ii", $requestId, $instructorId);
            $updateEarningsStmt->execute();
            $updateEarningsStmt->close();

            send_notification(
                $conn,
                $instructorId,
                'Withdrawal paid',
                'Withdrawal request #' . $requestId . ' was paid. Transaction reference: ' . $transactionReference . '.',
                'payout'
            );

            $conn->commit();

            header("Location: admin-withdrawals.php?paid=1");
            exit;

        } catch (Exception $e) {
            $conn->rollback();

            $message = 'Payout failed: ' . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = implode(' ', $errors);
        $messageType = 'error';
    }
}

/*
|--------------------------------------------------------------------------
| Reject withdrawal request
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_request'])) {
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $adminNote = trim($_POST['admin_note'] ?? '');

    $errors = [];

    if ($requestId <= 0) {
        $errors[] = 'Invalid withdrawal request.';
    }

    if ($adminNote === '') {
        $errors[] = 'Admin note is required when rejecting.';
    }

    if (empty($errors)) {
        try {
            $conn->begin_transaction();

            $requestSql = "
                SELECT *
                FROM withdrawal_requests
                WHERE id = ?
                  AND request_status IN ('pending', 'approved')
                LIMIT 1
                FOR UPDATE
            ";

            $requestStmt = $conn->prepare($requestSql);

            if (!$requestStmt) {
                throw new Exception('Failed to prepare withdrawal request.');
            }

            $requestStmt->bind_param("i", $requestId);
            $requestStmt->execute();

            $requestResult = $requestStmt->get_result();

            if (!$requestResult || $requestResult->num_rows !== 1) {
                throw new Exception('Withdrawal request not found or already processed.');
            }

            $request = $requestResult->fetch_assoc();
            $requestStmt->close();

            $instructorId = (int) $request['instructor_id'];

            $restoreEarningsSql = "
                UPDATE instructor_earnings ie
                INNER JOIN withdrawal_request_earnings wre ON wre.earning_id = ie.id
                SET ie.earning_status = 'available'
                WHERE wre.withdrawal_request_id = ?
                  AND ie.instructor_id = ?
                  AND ie.earning_status = 'withdraw_requested'
            ";

            $restoreEarningsStmt = $conn->prepare($restoreEarningsSql);

            if (!$restoreEarningsStmt) {
                throw new Exception('Failed to restore instructor earnings.');
            }

            $restoreEarningsStmt->bind_param("ii", $requestId, $instructorId);
            $restoreEarningsStmt->execute();
            $restoreEarningsStmt->close();

            $updateRequestSql = "
                UPDATE withdrawal_requests
                SET 
                    request_status = 'rejected',
                    admin_note = ?,
                    processed_by = ?,
                    processed_at = NOW()
                WHERE id = ?
            ";

            $updateRequestStmt = $conn->prepare($updateRequestSql);

            if (!$updateRequestStmt) {
                throw new Exception('Failed to reject withdrawal request.');
            }

            $updateRequestStmt->bind_param(
                "sii",
                $adminNote,
                $adminId,
                $requestId
            );

            $updateRequestStmt->execute();
            $updateRequestStmt->close();

            send_notification(
                $conn,
                $instructorId,
                'Withdrawal rejected',
                'Withdrawal request #' . $requestId . ' was rejected and the locked earnings are available again. Admin note: ' . $adminNote,
                'payout'
            );

            $conn->commit();

            header("Location: admin-withdrawals.php?rejected=1");
            exit;

        } catch (Exception $e) {
            $conn->rollback();

            $message = 'Reject failed: ' . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = implode(' ', $errors);
        $messageType = 'error';
    }
}

if (isset($_GET['paid'])) {
    $message = 'Withdrawal request marked as paid successfully.';
    $messageType = 'success';
}

if (isset($_GET['rejected'])) {
    $message = 'Withdrawal request rejected and balance returned to instructor.';
    $messageType = 'success';
}

/*
|--------------------------------------------------------------------------
| Stats
|--------------------------------------------------------------------------
*/

$totalPendingAmount = get_sum(
    $conn,
    "SELECT COALESCE(SUM(requested_amount), 0) AS total FROM withdrawal_requests WHERE request_status IN ('pending', 'approved')"
);

$totalPaidAmount = get_sum(
    $conn,
    "SELECT COALESCE(SUM(requested_amount), 0) AS total FROM withdrawal_requests WHERE request_status = 'paid'"
);

$totalPendingRequests = get_count(
    $conn,
    "SELECT COUNT(*) AS total FROM withdrawal_requests WHERE request_status IN ('pending', 'approved')"
);

$totalPaidRequests = get_count(
    $conn,
    "SELECT COUNT(*) AS total FROM withdrawal_requests WHERE request_status = 'paid'"
);

/*
|--------------------------------------------------------------------------
| Withdrawal list filters
|--------------------------------------------------------------------------
*/

$whereParts = ["1=1"];
$params = [];
$types = "";

if (in_array($statusFilter, ['pending', 'approved', 'paid', 'rejected'], true)) {
    $whereParts[] = "wr.request_status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

if ($search !== '') {
    $whereParts[] = "(u.full_name LIKE ? OR u.email LIKE ? OR wr.account_name LIKE ? OR wr.account_number LIKE ? OR wr.esewa_number LIKE ? OR wr.khalti_number LIKE ?)";
    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= "ssssss";
}

$whereSql = "WHERE " . implode(" AND ", $whereParts);

$withdrawals = [];

$withdrawalSql = "
    SELECT
        wr.id,
        wr.instructor_id,
        wr.requested_amount,
        wr.payment_method,
        wr.account_name,
        wr.account_number,
        wr.bank_name,
        wr.esewa_number,
        wr.khalti_number,
        wr.request_status,
        wr.instructor_note,
        wr.admin_note,
        wr.requested_at,
        wr.processed_at,

        u.full_name AS instructor_name,
        u.email AS instructor_email,
        u.phone AS instructor_phone,

        (
            SELECT COUNT(*)
            FROM withdrawal_request_earnings wre
            WHERE wre.withdrawal_request_id = wr.id
        ) AS earning_count,

        (
            SELECT COALESCE(SUM(ie.gross_amount), 0)
            FROM withdrawal_request_earnings wre
            INNER JOIN instructor_earnings ie ON wre.earning_id = ie.id
            WHERE wre.withdrawal_request_id = wr.id
        ) AS gross_amount,

        (
            SELECT COALESCE(SUM(ie.commission_amount), 0)
            FROM withdrawal_request_earnings wre
            INNER JOIN instructor_earnings ie ON wre.earning_id = ie.id
            WHERE wre.withdrawal_request_id = wr.id
        ) AS commission_amount
    FROM withdrawal_requests wr
    INNER JOIN users u ON wr.instructor_id = u.id
    $whereSql
    ORDER BY wr.requested_at DESC
";

$stmt = $conn->prepare($withdrawalSql);

if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $withdrawals[] = $row;
        }
    }

    $stmt->close();
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/admin_navbar.php';
?>



<main class="admin-withdrawals-page">
    <section class="admin-withdrawals-wrapper">

        <div class="withdrawals-header">
            <div>
                <p class="page-label">Admin Panel</p>
                <h1>Instructor Withdrawals</h1>
                <p>
                    Review instructor withdrawal requests, send money manually, then mark payout as paid.
                </p>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="admin-alert <?php echo h($messageType); ?>">
                <?php echo h($message); ?>
            </div>
        <?php endif; ?>

        <div class="withdrawal-stats-grid">

            <div class="withdrawal-stat-card warning">
                <span>Pending Amount</span>
                <strong><?php echo money($totalPendingAmount); ?></strong>
                <p>Waiting admin payout</p>
            </div>

            <div class="withdrawal-stat-card success">
                <span>Paid Amount</span>
                <strong><?php echo money($totalPaidAmount); ?></strong>
                <p>Already paid to instructors</p>
            </div>

            <div class="withdrawal-stat-card warning">
                <span>Pending Requests</span>
                <strong><?php echo $totalPendingRequests; ?></strong>
                <p>Need action</p>
            </div>

            <div class="withdrawal-stat-card success">
                <span>Paid Requests</span>
                <strong><?php echo $totalPaidRequests; ?></strong>
                <p>Completed payouts</p>
            </div>

        </div>

        <form method="GET" class="withdrawal-filter-box">

            <div class="form-group">
                <label>Search</label>
                <input
                    type="text"
                    name="search"
                    value="<?php echo h($search); ?>"
                    placeholder="Instructor, email, account, phone"
                >
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="paid" <?php echo $statusFilter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit">Filter</button>
                <a href="admin-withdrawals.php">Reset</a>
            </div>

        </form>

        <?php if (empty($withdrawals)): ?>

            <div class="empty-withdrawals-box">
                <div class="empty-icon">No payouts</div>
                <h2>No withdrawal requests found</h2>
                <p>Instructor withdrawal requests will appear here.</p>
            </div>

        <?php else: ?>

            <div class="withdrawal-request-list">

                <?php foreach ($withdrawals as $withdrawal): ?>
                    <article class="withdrawal-request-card">

                        <div class="request-card-top">
                            <div>
                                <p class="request-label">
                                    Request #<?php echo (int) $withdrawal['id']; ?>
                                </p>

                                <h2><?php echo h($withdrawal['instructor_name']); ?></h2>

                                <p>
                                    <?php echo h($withdrawal['instructor_email']); ?>
                                    <?php if (!empty($withdrawal['instructor_phone'])): ?>
                                        &middot; <?php echo h($withdrawal['instructor_phone']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>

                            <span class="status-pill <?php echo request_status_class($withdrawal['request_status']); ?>">
                                <?php echo request_status_label($withdrawal['request_status']); ?>
                            </span>
                        </div>

                        <div class="request-detail-grid">

                            <div>
                                <span>Requested Amount</span>
                                <strong><?php echo money($withdrawal['requested_amount']); ?></strong>
                            </div>

                            <div>
                                <span>Gross Sales</span>
                                <strong><?php echo money($withdrawal['gross_amount']); ?></strong>
                            </div>

                            <div>
                                <span>Admin Commission</span>
                                <strong><?php echo money($withdrawal['commission_amount']); ?></strong>
                            </div>

                            <div>
                                <span>Earning Records</span>
                                <strong><?php echo (int) $withdrawal['earning_count']; ?></strong>
                            </div>

                            <div>
                                <span>Payment Method</span>
                                <strong><?php echo h(ucfirst($withdrawal['payment_method'])); ?></strong>
                            </div>

                            <div>
                                <span>Requested At</span>
                                <strong><?php echo h(date('M d, Y h:i A', strtotime($withdrawal['requested_at']))); ?></strong>
                            </div>

                        </div>

                        <div class="payment-info-box">
                            <h3>Instructor Payment Details</h3>

                            <?php if ($withdrawal['payment_method'] === 'bank'): ?>
                                <p><strong>Bank:</strong> <?php echo h($withdrawal['bank_name']); ?></p>
                                <p><strong>Account Name:</strong> <?php echo h($withdrawal['account_name']); ?></p>
                                <p><strong>Account Number:</strong> <?php echo h($withdrawal['account_number']); ?></p>
                            <?php elseif ($withdrawal['payment_method'] === 'esewa'): ?>
                                <p><strong>eSewa Number:</strong> <?php echo h($withdrawal['esewa_number']); ?></p>
                            <?php elseif ($withdrawal['payment_method'] === 'khalti'): ?>
                                <p><strong>Khalti Number:</strong> <?php echo h($withdrawal['khalti_number']); ?></p>
                            <?php endif; ?>

                            <?php if (!empty($withdrawal['instructor_note'])): ?>
                                <p><strong>Instructor Note:</strong> <?php echo h($withdrawal['instructor_note']); ?></p>
                            <?php endif; ?>

                            <?php if (!empty($withdrawal['admin_note'])): ?>
                                <p><strong>Admin Note:</strong> <?php echo h($withdrawal['admin_note']); ?></p>
                            <?php endif; ?>
                        </div>

                        <?php if (in_array($withdrawal['request_status'], ['pending', 'approved'], true)): ?>

                            <div class="admin-action-grid">

                                <form method="POST" enctype="multipart/form-data" class="payout-form">
                                      <?php echo csrf_field(); ?>
                                    <h3>Mark as Paid</h3>

                                    <input type="hidden" name="request_id" value="<?php echo (int) $withdrawal['id']; ?>">

                                    <div class="form-group">
                                        <label>Transaction Reference</label>
                                        <input
                                            type="text"
                                            name="transaction_reference"
                                            placeholder="Example: eSewa/Khalti/Bank reference"
                                            required
                                        >
                                    </div>

                                    <div class="form-group">
                                        <label>Payout Proof</label>
                                        <input
                                            type="file"
                                            name="payout_proof"
                                            accept="image/png, image/jpeg, application/pdf"
                                        >
                                        <small>Optional. JPG, PNG, PDF. Max 5MB.</small>
                                    </div>

                                    <div class="form-group">
                                        <label>Admin Note</label>
                                        <textarea
                                            name="admin_note"
                                            rows="3"
                                            placeholder="Optional payout note"
                                        ></textarea>
                                    </div>

                                    <button type="submit" name="mark_paid" class="paid-btn">
                                        Mark Paid
                                    </button>
                                </form>

                                <form method="POST" class="reject-form">
                                      <?php echo csrf_field(); ?>
                                    <h3>Reject Request</h3>

                                    <input type="hidden" name="request_id" value="<?php echo (int) $withdrawal['id']; ?>">

                                    <div class="form-group">
                                        <label>Reject Reason</label>
                                        <textarea
                                            name="admin_note"
                                            rows="5"
                                            placeholder="Explain why this withdrawal is rejected"
                                            required
                                        ></textarea>
                                    </div>

                                    <button type="submit" name="reject_request" class="reject-btn">
                                        Reject & Return Balance
                                    </button>
                                </form>

                            </div>

                        <?php endif; ?>

                    </article>
                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>
</main>

</body>
</html>
