<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/StudentMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

StudentMiddleware::handle();

$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);
$message = '';
$messageType = '';

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function checkout_clean_text(mixed $value, int $maxLength, bool $multiline = false): string
{
    if (!is_scalar($value) && $value !== null) {
        return '';
    }

    $text = strip_tags((string) $value);
    $text = str_replace("\0", '', $text);
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = (string) preg_replace('/[\x{0001}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}]/u', '', $text);
    $text = $multiline
        ? trim((string) preg_replace('/\n{3,}/u', "\n\n", $text))
        : trim((string) preg_replace('/\s+/u', ' ', $text));

    if ((function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text)) > $maxLength) {
        return '';
    }

    return $text;
}

function checkout_payment_proof_directory(): string
{
    return __DIR__ . '/../../../storage/private_uploads/payment_proofs';
}

function checkout_delete_payment_proof(?string $fileName): void
{
    $safeName = basename((string) $fileName);
    if ($safeName === '' || $safeName === '.' || $safeName === '..') {
        return;
    }

    $path = checkout_payment_proof_directory() . DIRECTORY_SEPARATOR . $safeName;
    if (is_file($path)) {
        @unlink($path);
    }
}

function upload_payment_proof(array $file, array &$errors): ?string
{
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Payment proof is required.';
        return null;
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        $errors[] = 'Payment proof upload failed.';
        return null;
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        $errors[] = 'Payment proof upload could not be verified.';
        return null;
    }

    if ($size < 1 || $size > 5 * 1024 * 1024) {
        $errors[] = 'Payment proof must be a non-empty file no larger than 5 MB.';
        return null;
    }

    $mimeType = Security::detectMimeType($tmpName);
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
    ];

    if (!isset($allowedTypes[$mimeType])) {
        $errors[] = 'Only a genuine JPG, PNG, or PDF payment proof is allowed.';
        return null;
    }

    if (str_starts_with($mimeType, 'image/')) {
        $imageInfo = @getimagesize($tmpName);
        if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
            $errors[] = 'The payment proof image is invalid.';
            return null;
        }

        $width = (int) $imageInfo[0];
        $height = (int) $imageInfo[1];
        if ($width > 8000 || $height > 8000 || ($width * $height) > 40_000_000) {
            $errors[] = 'The payment proof image dimensions are too large.';
            return null;
        }
    } else {
        $header = (string) file_get_contents($tmpName, false, null, 0, 5);
        if ($header !== '%PDF-') {
            $errors[] = 'The payment proof does not contain a genuine PDF document.';
            return null;
        }
    }

    $uploadDir = checkout_payment_proof_directory();
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true) && !is_dir($uploadDir)) {
        $errors[] = 'The secure payment-proof directory could not be created.';
        return null;
    }

    if (!is_writable($uploadDir)) {
        $errors[] = 'The secure payment-proof directory is not writable.';
        return null;
    }

    try {
        $fileName = 'payment-proof-' . bin2hex(random_bytes(24)) . '.' . $allowedTypes[$mimeType];
    } catch (Throwable $exception) {
        error_log('Payment proof filename generation failed: ' . $exception->getMessage());
        $errors[] = 'The payment proof could not be prepared for storage.';
        return null;
    }

    $destination = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file($tmpName, $destination)) {
        $errors[] = 'Failed to save payment proof.';
        return null;
    }

    @chmod($destination, 0640);
    return $fileName;
}

function get_setting(mysqli $conn, string $key, string $fallback = ''): string
{
    try {
        $stmt = $conn->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1');
        if (!$stmt) {
            return $fallback;
        }

        $stmt->bind_param('s', $key);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result && $result->num_rows === 1 ? $result->fetch_assoc() : null;
        $stmt->close();
        return (string) ($row['setting_value'] ?? $fallback);
    } catch (Throwable $exception) {
        error_log('Checkout setting lookup failed: ' . $exception->getMessage());
        return $fallback;
    }
}

function checkout_load_cart(mysqli $conn, int $studentId, bool $lockRows = false): array
{
    $lockSql = $lockRows ? ' FOR UPDATE' : '';
    $sql = "
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
          AND u.role = 'instructor'
          AND u.status = 'active'
          AND NOT EXISTS (
              SELECT 1
              FROM enrollments e
              WHERE e.student_id = ?
                AND e.course_id = c.id
                AND e.status = 'active'
          )
        ORDER BY cart.id ASC
        {$lockSql}
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $studentId, $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];

    while ($result && $row = $result->fetch_assoc()) {
        $items[] = $row;
    }

    $stmt->close();
    return $items;
}

$cartItems = checkout_load_cart($conn, $studentId, false);
$totalCents = 0;
foreach ($cartItems as $item) {
    $totalCents += max(0, (int) round((float) $item['price'] * 100));
}
$totalAmount = $totalCents / 100;

if (empty($cartItems)) {
    Auth::redirect('cart.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $paymentMethod = checkout_clean_text($_POST['payment_method'] ?? 'manual', 30);
    $transactionId = checkout_clean_text($_POST['transaction_id'] ?? '', 150);
    $paymentNote = checkout_clean_text($_POST['payment_note'] ?? '', 1000, true);
    $errors = [];
    $proofFileName = null;
    $transactionStarted = false;

    if (!in_array($paymentMethod, ['manual', 'esewa', 'khalti'], true)) {
        $errors[] = 'Invalid payment method.';
    }

    if ($transactionId === '' || strlen($transactionId) < 3) {
        $errors[] = 'Enter a valid transaction ID or reference number.';
    }

    if ($paymentNote === '' && trim((string) ($_POST['payment_note'] ?? '')) !== '') {
        $errors[] = 'The payment note must be 1000 characters or fewer.';
    }

    if (!$errors) {
        $proofFileName = upload_payment_proof($_FILES['payment_proof'] ?? [], $errors);
    }

    if (!$errors && $proofFileName !== null) {
        try {
            $conn->begin_transaction();
            $transactionStarted = true;

            $lockedItems = checkout_load_cart($conn, $studentId, true);
            if ($lockedItems === []) {
                throw new RuntimeException('Your cart changed or contains no purchasable courses.');
            }

            if (count($lockedItems) > 50) {
                throw new RuntimeException('A single order cannot contain more than 50 courses.');
            }

            $lockedTotalCents = 0;
            foreach ($lockedItems as $item) {
                $price = (float) $item['price'];
                if (!is_finite($price) || $price < 0 || $price > 100000000) {
                    throw new RuntimeException('A course in the cart has an invalid price.');
                }
                $lockedTotalCents += (int) round($price * 100);
            }

            $originalAmount = $lockedTotalCents / 100;
            $discountAmount = 0.0;
            $finalAmount = $originalAmount;

            $duplicateStmt = $conn->prepare('SELECT id FROM payments WHERE transaction_id = ? LIMIT 1 FOR UPDATE');
            $duplicateStmt->bind_param('s', $transactionId);
            $duplicateStmt->execute();
            $duplicateExists = $duplicateStmt->get_result()->num_rows > 0;
            $duplicateStmt->close();

            if ($duplicateExists) {
                throw new DomainException('That transaction reference has already been submitted.');
            }

            $orderStmt = $conn->prepare("
                INSERT INTO orders (
                    student_id, coupon_id, original_amount,
                    discount_amount, final_amount, order_status
                ) VALUES (?, NULL, ?, ?, ?, 'pending')
            ");
            $orderStmt->bind_param('iddd', $studentId, $originalAmount, $discountAmount, $finalAmount);
            $orderStmt->execute();
            $orderId = (int) $conn->insert_id;
            $orderStmt->close();

            $itemStmt = $conn->prepare("
                INSERT INTO order_items (
                    order_id, course_id, instructor_id,
                    course_price, discount_amount, final_price
                ) VALUES (?, ?, ?, ?, 0, ?)
            ");

            $deleteCartStmt = $conn->prepare('DELETE FROM cart WHERE id = ? AND student_id = ?');

            foreach ($lockedItems as $item) {
                $courseId = (int) $item['course_id'];
                $instructorId = (int) $item['instructor_id'];
                $coursePrice = round((float) $item['price'], 2);
                $finalPrice = $coursePrice;
                $cartId = (int) $item['cart_id'];

                $itemStmt->bind_param('iiidd', $orderId, $courseId, $instructorId, $coursePrice, $finalPrice);
                $itemStmt->execute();

                $deleteCartStmt->bind_param('ii', $cartId, $studentId);
                $deleteCartStmt->execute();
                if ($deleteCartStmt->affected_rows !== 1) {
                    throw new RuntimeException('A cart item changed while checkout was processing.');
                }
            }

            $itemStmt->close();
            $deleteCartStmt->close();

            $paymentStmt = $conn->prepare("
                INSERT INTO payments (
                    order_id, student_id, payment_method, payment_type,
                    transaction_id, paid_amount, payment_status
                ) VALUES (?, ?, ?, 'manual', ?, ?, 'pending')
            ");
            $paymentStmt->bind_param('iissd', $orderId, $studentId, $paymentMethod, $transactionId, $finalAmount);
            $paymentStmt->execute();
            $paymentId = (int) $conn->insert_id;
            $paymentStmt->close();

            $proofStmt = $conn->prepare("
                INSERT INTO payment_proofs (payment_id, proof_image, note)
                VALUES (?, ?, ?)
            ");
            $proofStmt->bind_param('iss', $paymentId, $proofFileName, $paymentNote);
            $proofStmt->execute();
            $proofStmt->close();

            $conn->commit();
            $transactionStarted = false;
            Auth::redirect('checkout-success.php?order_id=' . $orderId);
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $conn->rollback();
            }

            checkout_delete_payment_proof($proofFileName);
            error_log('Checkout failed: ' . $exception->getMessage());
            $message = $exception instanceof DomainException
                ? $exception->getMessage()
                : 'Checkout failed because the cart or payment data changed. Review the cart and try again.';
            $messageType = 'error';
        }
    } else {
        if ($proofFileName !== null) {
            checkout_delete_payment_proof($proofFileName);
        }
        $message = implode(' ', array_unique($errors));
        $messageType = 'error';
    }

    $cartItems = checkout_load_cart($conn, $studentId, false);
    $totalCents = 0;
    foreach ($cartItems as $item) {
        $totalCents += max(0, (int) round((float) $item['price'] * 100));
    }
    $totalAmount = $totalCents / 100;
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
                <p>Pay using an available method, then upload proof. Admin will verify the payment before course access is activated.</p>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="cart-alert <?php echo h($messageType); ?>"><?php echo h($message); ?></div>
        <?php endif; ?>

        <?php if ($cartItems): ?>
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
                                <div><span>eSewa ID</span><strong><?php echo h($esewaId); ?></strong></div>
                            <?php endif; ?>
                            <?php if ($khaltiId !== ''): ?>
                                <div><span>Khalti ID</span><strong><?php echo h($khaltiId); ?></strong></div>
                            <?php endif; ?>
                            <?php if ($bankName !== '' || $bankAccountNumber !== ''): ?>
                                <div><span>Bank</span><strong><?php echo h($bankName ?: 'Not set'); ?></strong></div>
                                <div><span>Account Name</span><strong><?php echo h($bankAccountName ?: 'Not set'); ?></strong></div>
                                <div><span>Account Number</span><strong><?php echo h($bankAccountNumber ?: 'Not set'); ?></strong></div>
                            <?php endif; ?>
                            <?php if ($paymentInstructions !== ''): ?>
                                <div class="full-info"><span>Instructions</span><strong><?php echo nl2br(h($paymentInstructions)); ?></strong></div>
                            <?php endif; ?>
                            <?php if ($esewaId === '' && $khaltiId === '' && $bankName === '' && $paymentInstructions === ''): ?>
                                <div class="full-info">
                                    <span>Payment Instructions</span>
                                    <strong>Pay using your selected method and upload payment proof. Admin will verify your payment.</strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>Upload Proof</h2>
                        <div class="form-group">
                            <label for="transaction_id">Transaction ID / Reference Number</label>
                            <input type="text" id="transaction_id" name="transaction_id" maxlength="150"
                                   placeholder="Example: eSewa/Khalti transaction code" required>
                        </div>
                        <div class="form-group">
                            <label for="payment_proof">Payment Proof</label>
                            <input type="file" id="payment_proof" name="payment_proof"
                                   accept="image/png,image/jpeg,application/pdf" required>
                            <small>Upload a genuine JPG, PNG, or PDF proof. Maximum 5 MB.</small>
                        </div>
                        <div class="form-group">
                            <label for="payment_note">Note for Admin</label>
                            <textarea id="payment_note" name="payment_note" rows="4" maxlength="1000"
                                      placeholder="Optional note for admin"></textarea>
                        </div>
                    </div>

                    <button type="submit" name="place_order" class="place-order-btn">Place Order &amp; Submit Proof</button>
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
                    <div class="summary-row"><span>Subtotal</span><strong>Rs. <?php echo number_format($totalAmount, 2); ?></strong></div>
                    <div class="summary-row"><span>Discount</span><strong>Rs. 0.00</strong></div>
                    <div class="summary-total"><span>Total Payable</span><strong>Rs. <?php echo number_format($totalAmount, 2); ?></strong></div>
                    <div class="checkout-note">Course access is activated only after admin verifies payment.</div>
                </aside>
            </div>
        <?php else: ?>
            <div class="empty-cart-box">
                <h2>Your cart changed</h2>
                <p>No purchasable courses remain in the cart.</p>
                <a href="student-browse-courses.php" class="continue-btn">Browse courses</a>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
