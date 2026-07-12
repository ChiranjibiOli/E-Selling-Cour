<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/AdminMiddleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/notification_helper.php';
require_once __DIR__ . '/../../helpers/security_helper.php';

AdminMiddleware::handle();

$admin = Auth::user();
$adminId = (int) ($admin['id'] ?? 0);
$message = '';
$messageType = '';

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(mixed $amount): string
{
    return 'Rs. ' . number_format((float) $amount, 2);
}

function request_status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Pending',
        'approved' => 'Approved',
        'paid' => 'Paid',
        'rejected' => 'Rejected',
        default => ucfirst($status),
    };
}

function request_status_class(string $status): string
{
    return match ($status) {
        'paid' => 'status-paid',
        'approved' => 'status-approved',
        'rejected' => 'status-rejected',
        default => 'status-pending',
    };
}

function payout_proof_directory(): string
{
    return __DIR__ . '/../../../storage/private_uploads/payout_proofs';
}

function delete_payout_proof(?string $fileName): void
{
    $safeName = basename((string) $fileName);
    if ($safeName === '' || $safeName === '.' || $safeName === '..') {
        return;
    }

    $path = payout_proof_directory() . DIRECTORY_SEPARATOR . $safeName;
    if (is_file($path)) {
        @unlink($path);
    }
}

function upload_payout_proof(array $file, array &$errors): ?string
{
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($errorCode !== UPLOAD_ERR_OK) {
        $errors[] = 'Payout proof upload failed.';
        return null;
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        $errors[] = 'Payout proof upload could not be verified.';
        return null;
    }
    if ($size < 1 || $size > 5 * 1024 * 1024) {
        $errors[] = 'Payout proof must be a non-empty file no larger than 5 MB.';
        return null;
    }

    $mimeType = Security::detectMimeType($tmpName);
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
    ];
    if (!isset($allowedTypes[$mimeType])) {
        $errors[] = 'Only a genuine JPG, PNG, or PDF payout proof is allowed.';
        return null;
    }

    if (str_starts_with($mimeType, 'image/')) {
        $imageInfo = @getimagesize($tmpName);
        if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
            $errors[] = 'The payout proof image is invalid.';
            return null;
        }
        $width = (int) $imageInfo[0];
        $height = (int) $imageInfo[1];
        if ($width > 8000 || $height > 8000 || ($width * $height) > 40_000_000) {
            $errors[] = 'The payout proof image dimensions are too large.';
            return null;
        }
    } elseif ((string) file_get_contents($tmpName, false, null, 0, 5) !== '%PDF-') {
        $errors[] = 'The payout proof does not contain a genuine PDF document.';
        return null;
    }

    $uploadDir = payout_proof_directory();
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true) && !is_dir($uploadDir)) {
        $errors[] = 'The private payout-proof directory could not be created.';
        return null;
    }
    if (!is_writable($uploadDir)) {
        $errors[] = 'The private payout-proof directory is not writable.';
        return null;
    }

    try {
        $fileName = 'payout-proof-' . bin2hex(random_bytes(24)) . '.' . $allowedTypes[$mimeType];
    } catch (Throwable $exception) {
        error_log('Payout proof filename generation failed: ' . $exception->getMessage());
        $errors[] = 'The payout proof could not be prepared for storage.';
        return null;
    }

    if (!move_uploaded_file($tmpName, $uploadDir . DIRECTORY_SEPARATOR . $fileName)) {
        $errors[] = 'Failed to save payout proof.';
        return null;
    }

    @chmod($uploadDir . DIRECTORY_SEPARATOR . $fileName, 0640);
    return $fileName;
}

function withdrawal_summary(mysqli $conn, string $sql): float
{
    $result = $conn->query($sql);
    $row = $result ? $result->fetch_assoc() : null;
    return (float) ($row['total'] ?? 0);
}

function withdrawal_linked_earnings(mysqli $conn, int $requestId, int $instructorId, string $status): array
{
    $stmt = $conn->prepare("
        SELECT ie.id, ie.instructor_amount
        FROM instructor_earnings ie
        INNER JOIN withdrawal_request_earnings wre ON wre.earning_id = ie.id
        WHERE wre.withdrawal_request_id = ?
          AND ie.instructor_id = ?
          AND ie.earning_status = ?
        ORDER BY ie.id
        FOR UPDATE
    ");
    $stmt->bind_param('iis', $requestId, $instructorId, $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($result && $row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $transactionReference = security_clean_text($_POST['transaction_reference'] ?? '', 150);
    $adminNote = security_clean_text($_POST['admin_note'] ?? '', 1000, true);
    $errors = [];

    if ($requestId <= 0) {
        $errors[] = 'Invalid withdrawal request.';
    }
    if ($transactionReference === '' || strlen($transactionReference) < 3) {
        $errors[] = 'Transaction reference is required and must be 3 to 150 characters.';
    }

    $proofFile = upload_payout_proof($_FILES['payout_proof'] ?? [], $errors);
    $transactionStarted = false;

    if ($errors === []) {
        try {
            $conn->begin_transaction();
            $transactionStarted = true;

            $requestStmt = $conn->prepare("
                SELECT id, instructor_id, requested_amount, payment_method
                FROM withdrawal_requests
                WHERE id = ?
                  AND request_status IN ('pending', 'approved')
                LIMIT 1
                FOR UPDATE
            ");
            $requestStmt->bind_param('i', $requestId);
            $requestStmt->execute();
            $request = $requestStmt->get_result()->fetch_assoc() ?: null;
            $requestStmt->close();

            if (!$request) {
                throw new DomainException('Withdrawal request was already processed or no longer exists.');
            }

            $instructorId = (int) $request['instructor_id'];
            $requestedAmount = round((float) $request['requested_amount'], 2);
            $paymentMethod = (string) $request['payment_method'];
            $earnings = withdrawal_linked_earnings($conn, $requestId, $instructorId, 'withdraw_requested');

            if ($earnings === []) {
                throw new DomainException('No locked earnings belong to this withdrawal request.');
            }

            $linkedAmount = round(array_sum(array_map(
                static fn (array $earning): float => (float) $earning['instructor_amount'],
                $earnings
            )), 2);

            if ($requestedAmount <= 0 || abs($linkedAmount - $requestedAmount) > 0.01) {
                throw new DomainException('Withdrawal amount does not match the linked locked earnings.');
            }

            $duplicateStmt = $conn->prepare('SELECT id FROM payouts WHERE withdrawal_request_id = ? LIMIT 1 FOR UPDATE');
            $duplicateStmt->bind_param('i', $requestId);
            $duplicateStmt->execute();
            $alreadyPaid = $duplicateStmt->get_result()->num_rows > 0;
            $duplicateStmt->close();
            if ($alreadyPaid) {
                throw new DomainException('A payout already exists for this withdrawal request.');
            }

            $payoutStmt = $conn->prepare("
                INSERT INTO payouts (
                    withdrawal_request_id, instructor_id, paid_amount, payment_method,
                    transaction_reference, proof_image, payout_status, paid_by, admin_note
                ) VALUES (?, ?, ?, ?, ?, ?, 'paid', ?, ?)
            ");
            $payoutStmt->bind_param(
                'iidsssis',
                $requestId,
                $instructorId,
                $requestedAmount,
                $paymentMethod,
                $transactionReference,
                $proofFile,
                $adminId,
                $adminNote
            );
            $payoutStmt->execute();
            $payoutStmt->close();

            $updateRequestStmt = $conn->prepare("
                UPDATE withdrawal_requests
                SET request_status = 'paid', admin_note = ?, processed_by = ?, processed_at = NOW()
                WHERE id = ? AND request_status IN ('pending', 'approved')
            ");
            $updateRequestStmt->bind_param('sii', $adminNote, $adminId, $requestId);
            $updateRequestStmt->execute();
            if ($updateRequestStmt->affected_rows !== 1) {
                throw new RuntimeException('Withdrawal request state changed during payout processing.');
            }
            $updateRequestStmt->close();

            $updateEarningsStmt = $conn->prepare("
                UPDATE instructor_earnings ie
                INNER JOIN withdrawal_request_earnings wre ON wre.earning_id = ie.id
                SET ie.earning_status = 'paid', ie.paid_at = NOW()
                WHERE wre.withdrawal_request_id = ?
                  AND ie.instructor_id = ?
                  AND ie.earning_status = 'withdraw_requested'
            ");
            $updateEarningsStmt->bind_param('ii', $requestId, $instructorId);
            $updateEarningsStmt->execute();
            if ($updateEarningsStmt->affected_rows !== count($earnings)) {
                throw new RuntimeException('Not every linked earning transitioned to paid.');
            }
            $updateEarningsStmt->close();

            send_notification(
                $conn,
                $instructorId,
                'Withdrawal paid',
                'Withdrawal request #' . $requestId . ' was paid. Transaction reference: ' . $transactionReference . '.',
                'payout'
            );

            $conn->commit();
            $transactionStarted = false;
            Auth::redirect('admin-withdrawals.php?paid=1');
        } catch (DomainException $exception) {
            if ($transactionStarted) {
                $conn->rollback();
            }
            delete_payout_proof($proofFile);
            $message = $exception->getMessage();
            $messageType = 'error';
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $conn->rollback();
            }
            delete_payout_proof($proofFile);
            error_log('Withdrawal payout failed: ' . $exception->getMessage());
            $message = 'The payout could not be completed. No financial state was changed.';
            $messageType = 'error';
        }
    } else {
        delete_payout_proof($proofFile);
        $message = implode(' ', array_unique($errors));
        $messageType = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_request'])) {
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $adminNote = security_clean_text($_POST['admin_note'] ?? '', 1000, true);
    $errors = [];

    if ($requestId <= 0) {
        $errors[] = 'Invalid withdrawal request.';
    }
    if ($adminNote === '') {
        $errors[] = 'A rejection reason is required and must be 1000 characters or fewer.';
    }

    if ($errors === []) {
        $transactionStarted = false;
        try {
            $conn->begin_transaction();
            $transactionStarted = true;

            $requestStmt = $conn->prepare("
                SELECT id, instructor_id
                FROM withdrawal_requests
                WHERE id = ?
                  AND request_status IN ('pending', 'approved')
                LIMIT 1
                FOR UPDATE
            ");
            $requestStmt->bind_param('i', $requestId);
            $requestStmt->execute();
            $request = $requestStmt->get_result()->fetch_assoc() ?: null;
            $requestStmt->close();

            if (!$request) {
                throw new DomainException('Withdrawal request was already processed or no longer exists.');
            }

            $instructorId = (int) $request['instructor_id'];
            $earnings = withdrawal_linked_earnings($conn, $requestId, $instructorId, 'withdraw_requested');
            if ($earnings === []) {
                throw new DomainException('No locked earnings belong to this withdrawal request.');
            }

            $restoreStmt = $conn->prepare("
                UPDATE instructor_earnings ie
                INNER JOIN withdrawal_request_earnings wre ON wre.earning_id = ie.id
                SET ie.earning_status = 'available'
                WHERE wre.withdrawal_request_id = ?
                  AND ie.instructor_id = ?
                  AND ie.earning_status = 'withdraw_requested'
            ");
            $restoreStmt->bind_param('ii', $requestId, $instructorId);
            $restoreStmt->execute();
            if ($restoreStmt->affected_rows !== count($earnings)) {
                throw new RuntimeException('Not every linked earning was restored.');
            }
            $restoreStmt->close();

            $updateRequestStmt = $conn->prepare("
                UPDATE withdrawal_requests
                SET request_status = 'rejected', admin_note = ?, processed_by = ?, processed_at = NOW()
                WHERE id = ? AND request_status IN ('pending', 'approved')
            ");
            $updateRequestStmt->bind_param('sii', $adminNote, $adminId, $requestId);
            $updateRequestStmt->execute();
            if ($updateRequestStmt->affected_rows !== 1) {
                throw new RuntimeException('Withdrawal request state changed during rejection.');
            }
            $updateRequestStmt->close();

            send_notification(
                $conn,
                $instructorId,
                'Withdrawal rejected',
                'Withdrawal request #' . $requestId . ' was rejected. The locked earnings are available again. Admin note: ' . $adminNote,
                'payout'
            );

            $conn->commit();
            $transactionStarted = false;
            Auth::redirect('admin-withdrawals.php?rejected=1');
        } catch (DomainException $exception) {
            if ($transactionStarted) {
                $conn->rollback();
            }
            $message = $exception->getMessage();
            $messageType = 'error';
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $conn->rollback();
            }
            error_log('Withdrawal rejection failed: ' . $exception->getMessage());
            $message = 'The withdrawal could not be rejected. No financial state was changed.';
            $messageType = 'error';
        }
    } else {
        $message = implode(' ', array_unique($errors));
        $messageType = 'error';
    }
}

if (isset($_GET['paid'])) {
    $message = 'Withdrawal request marked as paid successfully.';
    $messageType = 'success';
} elseif (isset($_GET['rejected'])) {
    $message = 'Withdrawal request rejected and locked earnings returned to the instructor.';
    $messageType = 'success';
}

$statusFilter = trim((string) ($_GET['status'] ?? ''));
$search = security_clean_text($_GET['search'] ?? '', 150);
if (!in_array($statusFilter, ['pending', 'approved', 'paid', 'rejected'], true)) {
    $statusFilter = '';
}

$totalPendingAmount = withdrawal_summary($conn, "SELECT COALESCE(SUM(requested_amount), 0) AS total FROM withdrawal_requests WHERE request_status IN ('pending', 'approved')");
$totalPaidAmount = withdrawal_summary($conn, "SELECT COALESCE(SUM(requested_amount), 0) AS total FROM withdrawal_requests WHERE request_status = 'paid'");
$totalPendingRequests = (int) withdrawal_summary($conn, "SELECT COUNT(*) AS total FROM withdrawal_requests WHERE request_status IN ('pending', 'approved')");
$totalPaidRequests = (int) withdrawal_summary($conn, "SELECT COUNT(*) AS total FROM withdrawal_requests WHERE request_status = 'paid'");

$whereParts = ['1=1'];
$params = [];
$types = '';
if ($statusFilter !== '') {
    $whereParts[] = 'wr.request_status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}
if ($search !== '') {
    $whereParts[] = '(u.full_name LIKE ? OR u.email LIKE ? OR wr.account_name LIKE ? OR wr.account_number LIKE ? OR wr.esewa_number LIKE ? OR wr.khalti_number LIKE ?)';
    $searchValue = '%' . $search . '%';
    for ($i = 0; $i < 6; $i++) {
        $params[] = $searchValue;
    }
    $types .= 'ssssss';
}

$whereSql = 'WHERE ' . implode(' AND ', $whereParts);
$withdrawals = [];
$stmt = $conn->prepare("
    SELECT
        wr.id, wr.instructor_id, wr.requested_amount, wr.payment_method,
        wr.account_name, wr.account_number, wr.bank_name, wr.esewa_number,
        wr.khalti_number, wr.request_status, wr.instructor_note, wr.admin_note,
        wr.requested_at, wr.processed_at,
        u.full_name AS instructor_name, u.email AS instructor_email, u.phone AS instructor_phone,
        (SELECT COUNT(*) FROM withdrawal_request_earnings wre WHERE wre.withdrawal_request_id = wr.id) AS earning_count,
        (SELECT COALESCE(SUM(ie.gross_amount), 0) FROM withdrawal_request_earnings wre INNER JOIN instructor_earnings ie ON wre.earning_id = ie.id WHERE wre.withdrawal_request_id = wr.id) AS gross_amount,
        (SELECT COALESCE(SUM(ie.commission_amount), 0) FROM withdrawal_request_earnings wre INNER JOIN instructor_earnings ie ON wre.earning_id = ie.id WHERE wre.withdrawal_request_id = wr.id) AS commission_amount,
        p.id AS payout_id, p.proof_image, p.transaction_reference
    FROM withdrawal_requests wr
    INNER JOIN users u ON wr.instructor_id = u.id
    LEFT JOIN payouts p ON p.withdrawal_request_id = wr.id
    {$whereSql}
    ORDER BY wr.requested_at DESC
");
if ($params !== []) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
while ($result && $row = $result->fetch_assoc()) {
    $withdrawals[] = $row;
}
$stmt->close();

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/admin_navbar.php';
?>

<main class="admin-withdrawals-page">
    <section class="admin-withdrawals-wrapper">
        <div class="withdrawals-header">
            <div>
                <p class="page-label">Admin Panel</p>
                <h1>Instructor Withdrawals</h1>
                <p>Review locked instructor earnings, record an external payout, and close each request exactly once.</p>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="admin-alert <?php echo h($messageType); ?>"><?php echo h($message); ?></div>
        <?php endif; ?>

        <div class="withdrawal-stats-grid">
            <div class="withdrawal-stat-card warning"><span>Pending Amount</span><strong><?php echo money($totalPendingAmount); ?></strong><p>Waiting admin payout</p></div>
            <div class="withdrawal-stat-card success"><span>Paid Amount</span><strong><?php echo money($totalPaidAmount); ?></strong><p>Already paid</p></div>
            <div class="withdrawal-stat-card warning"><span>Pending Requests</span><strong><?php echo $totalPendingRequests; ?></strong><p>Need action</p></div>
            <div class="withdrawal-stat-card success"><span>Paid Requests</span><strong><?php echo $totalPaidRequests; ?></strong><p>Completed payouts</p></div>
        </div>

        <form method="GET" class="withdrawal-filter-box">
            <div class="form-group"><label for="search">Search</label><input id="search" type="text" name="search" maxlength="150" value="<?php echo h($search); ?>" placeholder="Instructor, email, account, phone"></div>
            <div class="form-group"><label for="status">Status</label><select id="status" name="status"><option value="">All Status</option><?php foreach (['pending', 'approved', 'paid', 'rejected'] as $status): ?><option value="<?php echo h($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>><?php echo h(ucfirst($status)); ?></option><?php endforeach; ?></select></div>
            <div class="filter-actions"><button type="submit">Filter</button><a href="admin-withdrawals.php">Reset</a></div>
        </form>

        <?php if ($withdrawals === []): ?>
            <div class="empty-withdrawals-box"><div class="empty-icon">No payouts</div><h2>No withdrawal requests found</h2><p>Instructor withdrawal requests will appear here.</p></div>
        <?php else: ?>
            <div class="withdrawal-request-list">
                <?php foreach ($withdrawals as $withdrawal): ?>
                    <article class="withdrawal-request-card">
                        <div class="request-card-top">
                            <div>
                                <p class="request-label">Request #<?php echo (int) $withdrawal['id']; ?></p>
                                <h2><?php echo h($withdrawal['instructor_name']); ?></h2>
                                <p><?php echo h($withdrawal['instructor_email']); ?><?php echo !empty($withdrawal['instructor_phone']) ? ' · ' . h($withdrawal['instructor_phone']) : ''; ?></p>
                            </div>
                            <span class="status-pill <?php echo request_status_class((string) $withdrawal['request_status']); ?>"><?php echo request_status_label((string) $withdrawal['request_status']); ?></span>
                        </div>

                        <div class="request-detail-grid">
                            <div><span>Requested Amount</span><strong><?php echo money($withdrawal['requested_amount']); ?></strong></div>
                            <div><span>Gross Sales</span><strong><?php echo money($withdrawal['gross_amount']); ?></strong></div>
                            <div><span>Admin Commission</span><strong><?php echo money($withdrawal['commission_amount']); ?></strong></div>
                            <div><span>Earning Records</span><strong><?php echo (int) $withdrawal['earning_count']; ?></strong></div>
                            <div><span>Payment Method</span><strong><?php echo h(ucfirst((string) $withdrawal['payment_method'])); ?></strong></div>
                            <div><span>Requested At</span><strong><?php echo h(date('M d, Y h:i A', strtotime((string) $withdrawal['requested_at']))); ?></strong></div>
                        </div>

                        <div class="payment-info-box">
                            <h3>Instructor Payment Details</h3>
                            <?php if ($withdrawal['payment_method'] === 'bank'): ?>
                                <p><strong>Bank:</strong> <?php echo h($withdrawal['bank_name']); ?></p>
                                <p><strong>Account Name:</strong> <?php echo h($withdrawal['account_name']); ?></p>
                                <p><strong>Account Number:</strong> <?php echo h($withdrawal['account_number']); ?></p>
                            <?php elseif ($withdrawal['payment_method'] === 'esewa'): ?>
                                <p><strong>eSewa Number:</strong> <?php echo h($withdrawal['esewa_number']); ?></p>
                            <?php else: ?>
                                <p><strong>Khalti Number:</strong> <?php echo h($withdrawal['khalti_number']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($withdrawal['instructor_note'])): ?><p><strong>Instructor Note:</strong> <?php echo h($withdrawal['instructor_note']); ?></p><?php endif; ?>
                            <?php if (!empty($withdrawal['admin_note'])): ?><p><strong>Admin Note:</strong> <?php echo h($withdrawal['admin_note']); ?></p><?php endif; ?>
                            <?php if (!empty($withdrawal['payout_id'])): ?>
                                <p><strong>Transaction:</strong> <?php echo h($withdrawal['transaction_reference']); ?></p>
                                <?php if (!empty($withdrawal['proof_image'])): ?><a class="action-btn" href="admin-view-payout-proof.php?payout_id=<?php echo (int) $withdrawal['payout_id']; ?>" target="_blank" rel="noopener">View payout proof</a><?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <?php if (in_array($withdrawal['request_status'], ['pending', 'approved'], true)): ?>
                            <div class="admin-action-grid">
                                <form method="POST" enctype="multipart/form-data" class="payout-form">
                                    <?php echo csrf_field(); ?>
                                    <h3>Mark as Paid</h3>
                                    <input type="hidden" name="request_id" value="<?php echo (int) $withdrawal['id']; ?>">
                                    <div class="form-group"><label>Transaction Reference</label><input type="text" name="transaction_reference" maxlength="150" required></div>
                                    <div class="form-group"><label>Payout Proof</label><input type="file" name="payout_proof" accept="image/png,image/jpeg,application/pdf"><small>Optional genuine JPG, PNG, or PDF. Maximum 5 MB.</small></div>
                                    <div class="form-group"><label>Admin Note</label><textarea name="admin_note" rows="3" maxlength="1000"></textarea></div>
                                    <button type="submit" name="mark_paid" class="paid-btn" data-confirm="Confirm that money was sent and mark this request paid?">Mark Paid</button>
                                </form>
                                <form method="POST" class="reject-form">
                                    <?php echo csrf_field(); ?>
                                    <h3>Reject Request</h3>
                                    <input type="hidden" name="request_id" value="<?php echo (int) $withdrawal['id']; ?>">
                                    <div class="form-group"><label>Reject Reason</label><textarea name="admin_note" rows="5" maxlength="1000" required></textarea></div>
                                    <button type="submit" name="reject_request" class="reject-btn" data-confirm="Reject this request and unlock its earnings?">Reject &amp; Return Balance</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../layouts/panel_end.php'; ?>
