<?php

require_once __DIR__ . '/../../middleware/StudentMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

StudentMiddleware::handle();

$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);

$message = '';
$messageType = '';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function upload_payment_proof(array $file, array &$errors): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Payment proof is required.';
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Payment proof upload failed.';
        return null;
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        $errors[] = 'Payment proof must be less than 5MB.';
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
        $errors[] = 'Only JPG, PNG, or PDF payment proof is allowed.';
        return null;
    }

    $uploadDir = __DIR__ . '/../../../storage/private_uploads/payment_proofs';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0750, true);
    }

    $fileName = 'payment-proof-' . bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
    $destination = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $errors[] = 'Failed to save payment proof.';
        return null;
    }

    return $fileName;
}

function get_setting(mysqli $conn, string $key, string $fallback = ''): string
{
    try {
        $sql = "SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return $fallback;
        }

        $stmt->bind_param("s", $key);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $stmt->close();

            return (string) ($row['setting_value'] ?? $fallback);
        }

        $stmt->close();
    } catch (Throwable $e) {
        return $fallback;
    }

    return $fallback;
}

$cartItems = [];

$cartSql = "
    SELECT 
        cart.id AS cart_id,
        c.id AS course_id,
        c.title,
        c.slug,
        c.thumbnail,
        c.price,
        c.instructor_id,
        u.full_name AS instructor_name
    FROM cart
    INNER JOIN courses c ON cart.course_id = c.id
    INNER JOIN users u ON c.instructor_id = u.id
    WHERE cart.student_id = ?
      AND c.status = 'published'
    ORDER BY cart.created_at DESC
";

$cartStmt = $conn->prepare($cartSql);

if ($cartStmt) {
    $cartStmt->bind_param("i", $studentId);
    $cartStmt->execute();

    $cartResult = $cartStmt->get_result();

    if ($cartResult) {
        while ($row = $cartResult->fetch_assoc()) {
            $cartItems[] = $row;
        }
    }

    $cartStmt->close();
}

$totalAmount = 0;

foreach ($cartItems as $item) {
    $totalAmount += (float) $item['price'];
}

if (empty($cartItems)) {
    header("Location: cart.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $paymentMethod = trim($_POST['payment_method'] ?? 'manual');
    $transactionId = trim($_POST['transaction_id'] ?? '');
    $paymentNote = trim($_POST['payment_note'] ?? '');

    $errors = [];

    $allowedPaymentMethods = ['manual', 'esewa', 'khalti'];

    if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
        $errors[] = 'Invalid payment method.';
    }

    if ($transactionId === '') {
        $errors[] = 'Transaction ID / reference number is required.';
    }

    $proofFileName = upload_payment_proof($_FILES['payment_proof'] ?? [], $errors);

    if (empty($errors)) {
        try {
            $conn->begin_transaction();

            $originalAmount = $totalAmount;
            $discountAmount = 0;
            $finalAmount = $totalAmount;
            $orderStatus = 'pending';

            $orderSql = "
                INSERT INTO orders (
                    student_id,
                    coupon_id,
                    original_amount,
                    discount_amount,
                    final_amount,
                    order_status
                ) VALUES (?, NULL, ?, ?, ?, ?)
            ";

            $orderStmt = $conn->prepare($orderSql);

            if (!$orderStmt) {
                throw new Exception('Failed to prepare order.');
            }

            $orderStmt->bind_param(
                "iddds",
                $studentId,
                $originalAmount,
                $discountAmount,
                $finalAmount,
                $orderStatus
            );

            if (!$orderStmt->execute()) {
                throw new Exception('Failed to create order.');
            }

            $orderId = $conn->insert_id;
            $orderStmt->close();

            $itemSql = "
                INSERT INTO order_items (
                    order_id,
                    course_id,
                    instructor_id,
                    course_price,
                    discount_amount,
                    final_price
                ) VALUES (?, ?, ?, ?, 0, ?)
            ";

            $itemStmt = $conn->prepare($itemSql);

            if (!$itemStmt) {
                throw new Exception('Failed to prepare order items.');
            }

            foreach ($cartItems as $item) {
                $courseId = (int) $item['course_id'];
                $instructorId = (int) $item['instructor_id'];
                $coursePrice = (float) $item['price'];
                $finalPrice = (float) $item['price'];

                $itemStmt->bind_param(
                    "iiidd",
                    $orderId,
                    $courseId,
                    $instructorId,
                    $coursePrice,
                    $finalPrice
                );

                if (!$itemStmt->execute()) {
                    throw new Exception('Failed to save order item.');
                }
            }

            $itemStmt->close();

            $paymentSql = "
                INSERT INTO payments (
                    order_id,
                    student_id,
                    payment_method,
                    payment_type,
                    transaction_id,
                    paid_amount,
                    payment_status
                ) VALUES (?, ?, ?, 'manual', ?, ?, 'pending')
            ";

            $paymentStmt = $conn->prepare($paymentSql);

            if (!$paymentStmt) {
                throw new Exception('Failed to prepare payment.');
            }

            $paymentStmt->bind_param(
                "iissd",
                $orderId,
                $studentId,
                $paymentMethod,
                $transactionId,
                $finalAmount
            );

            if (!$paymentStmt->execute()) {
                throw new Exception('Failed to save payment.');
            }

            $paymentId = $conn->insert_id;
            $paymentStmt->close();

            $proofSql = "
                INSERT INTO payment_proofs (
                    payment_id,
                    proof_image,
                    note
                ) VALUES (?, ?, ?)
            ";

            $proofStmt = $conn->prepare($proofSql);

            if (!$proofStmt) {
                throw new Exception('Failed to prepare payment proof.');
            }

            $proofStmt->bind_param(
                "iss",
                $paymentId,
                $proofFileName,
                $paymentNote
            );

            if (!$proofStmt->execute()) {
                throw new Exception('Failed to save payment proof.');
            }

            $proofStmt->close();

            $clearCartSql = "DELETE FROM cart WHERE student_id = ?";
            $clearCartStmt = $conn->prepare($clearCartSql);

            if ($clearCartStmt) {
                $clearCartStmt->bind_param("i", $studentId);
                $clearCartStmt->execute();
                $clearCartStmt->close();
            }

            $conn->commit();

            header("Location: checkout-success.php?order_id=" . $orderId);
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            error_log('Checkout failed: ' . $e->getMessage());
            $message = 'Checkout failed. Please review your details and try again.';
            $messageType = 'error';
        }
    } else {
        $message = implode(' ', $errors);
        $messageType = 'error';
    }
}

$esewaId = get_setting($conn, 'esewa_id', '');
$khaltiId = get_setting($conn, 'khalti_id', '');
$bankName = get_setting($conn, 'bank_name', '');
$bankAccountName = get_setting($conn, 'bank_account_name', '');
$bankAccountNumber = get_setting($conn, 'bank_account_number', '');
$paymentInstructions = get_setting($conn, 'payment_instructions', '');

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/student_navbar.php';
?>



<main class="checkout-page">
    <section class="checkout-wrapper">

        <div class="checkout-header">
            <div>
                <p class="page-label">Checkout</p>
                <h1>Submit Payment Proof</h1>
                <p>
                    Pay using available method, then upload proof. Admin will verify and activate your courses.
                </p>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="cart-alert <?php echo h($messageType); ?>">
                <?php echo h($message); ?>
            </div>
        <?php endif; ?>

        <div class="checkout-layout">

            <form method="POST" enctype="multipart/form-data" class="checkout-form-card">
                  <?php echo csrf_field(); ?>

                <div class="form-section">
                    <h2>Payment Method</h2>

                    <div class="payment-method-grid">

                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="manual" checked>
                            <span>Manual Payment</span>
                        </label>

                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="esewa">
                            <span>eSewa</span>
                        </label>

                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="khalti">
                            <span>Khalti</span>
                        </label>

                    </div>
                </div>

                <div class="form-section">
                    <h2>Payment Information</h2>

                    <div class="payment-info-box">

                        <?php if ($esewaId !== ''): ?>
                            <div>
                                <span>eSewa ID</span>
                                <strong><?php echo h($esewaId); ?></strong>
                            </div>
                        <?php endif; ?>

                        <?php if ($khaltiId !== ''): ?>
                            <div>
                                <span>Khalti ID</span>
                                <strong><?php echo h($khaltiId); ?></strong>
                            </div>
                        <?php endif; ?>

                        <?php if ($bankName !== '' || $bankAccountNumber !== ''): ?>
                            <div>
                                <span>Bank</span>
                                <strong><?php echo h($bankName ?: 'Not set'); ?></strong>
                            </div>

                            <div>
                                <span>Account Name</span>
                                <strong><?php echo h($bankAccountName ?: 'Not set'); ?></strong>
                            </div>

                            <div>
                                <span>Account Number</span>
                                <strong><?php echo h($bankAccountNumber ?: 'Not set'); ?></strong>
                            </div>
                        <?php endif; ?>

                        <?php if ($paymentInstructions !== ''): ?>
                            <div class="full-info">
                                <span>Instructions</span>
                                <strong><?php echo nl2br(h($paymentInstructions)); ?></strong>
                            </div>
                        <?php endif; ?>

                        <?php if ($esewaId === '' && $khaltiId === '' && $bankName === '' && $paymentInstructions === ''): ?>
                            <div class="full-info">
                                <span>Payment Instructions</span>
                                <strong>
                                    Pay using your selected method and upload payment proof.
                                    Admin will verify your payment.
                                </strong>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>

                <div class="form-section">
                    <h2>Upload Proof</h2>

                    <div class="form-group">
                        <label for="transaction_id">Transaction ID / Reference Number</label>
                        <input
                            type="text"
                            id="transaction_id"
                            name="transaction_id"
                            placeholder="Example: eSewa/Khalti transaction code"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="payment_proof">Payment Proof</label>
                        <input
                            type="file"
                            id="payment_proof"
                            name="payment_proof"
                            accept="image/png, image/jpeg, application/pdf"
                            required
                        >
                        <small>Upload screenshot/photo/PDF proof. Max 5MB.</small>
                    </div>

                    <div class="form-group">
                        <label for="payment_note">Note for Admin</label>
                        <textarea
                            id="payment_note"
                            name="payment_note"
                            rows="4"
                            placeholder="Optional note for admin"
                        ></textarea>
                    </div>
                </div>

                <button type="submit" name="place_order" class="place-order-btn">
                    Place Order & Submit Proof
                </button>

            </form>

            <aside class="checkout-summary-card">
                <h2>Order Summary</h2>

                <div class="checkout-items">
                    <?php foreach ($cartItems as $item): ?>
                        <div class="checkout-item">
                            <div>
                                <strong><?php echo h($item['title']); ?></strong>
                                <span>By <?php echo h($item['instructor_name']); ?></span>
                            </div>

                            <p>Rs. <?php echo number_format((float) $item['price'], 2); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="summary-row">
                    <span>Subtotal</span>
                    <strong>Rs. <?php echo number_format($totalAmount, 2); ?></strong>
                </div>

                <div class="summary-row">
                    <span>Discount</span>
                    <strong>Rs. 0.00</strong>
                </div>

                <div class="summary-total">
                    <span>Total Payable</span>
                    <strong>Rs. <?php echo number_format($totalAmount, 2); ?></strong>
                </div>

                <div class="checkout-note">
                    Course access is activated only after admin verifies payment.
                </div>
            </aside>

        </div>

    </section>
</main>
</body>
</html>
