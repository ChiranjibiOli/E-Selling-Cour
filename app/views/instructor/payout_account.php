<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/InstructorMiddleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/security_helper.php';

InstructorMiddleware::handle();

$user = Auth::user();
$instructorId = (int) ($user['id'] ?? 0);
$message = '';
$messageType = '';

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function payout_qr_directory(): string
{
    return __DIR__ . '/../../../storage/private_uploads/instructor_qr';
}

function payout_delete_qr(?string $fileName): void
{
    $safeName = basename((string) $fileName);
    if ($safeName === '' || $safeName === '.' || $safeName === '..') {
        return;
    }

    foreach ([
        payout_qr_directory(),
        __DIR__ . '/../../../public/assets/uploads/instructor_qr',
    ] as $directory) {
        $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeName;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function upload_qr_image(array $file, array &$errors): ?string
{
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        $errors[] = 'QR image upload failed.';
        return null;
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        $errors[] = 'QR image upload could not be verified.';
        return null;
    }

    if ($size < 1 || $size > 3 * 1024 * 1024) {
        $errors[] = 'QR image must be a non-empty image no larger than 3 MB.';
        return null;
    }

    $imageInfo = @getimagesize($tmpName);
    if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
        $errors[] = 'QR upload must contain a genuine image.';
        return null;
    }

    $width = (int) $imageInfo[0];
    $height = (int) $imageInfo[1];
    if ($width < 160 || $height < 160 || $width > 5000 || $height > 5000 || ($width * $height) > 20_000_000) {
        $errors[] = 'QR image dimensions must be between 160 × 160 and 5000 × 5000 pixels.';
        return null;
    }

    $mimeType = Security::detectMimeType($tmpName, (string) ($imageInfo['mime'] ?? ''));
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedTypes[$mimeType])) {
        $errors[] = 'Only a genuine JPG, PNG, or WebP QR image is allowed.';
        return null;
    }

    $uploadDir = payout_qr_directory();
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true) && !is_dir($uploadDir)) {
        $errors[] = 'The private QR directory could not be created.';
        return null;
    }

    if (!is_writable($uploadDir)) {
        $errors[] = 'The private QR directory is not writable.';
        return null;
    }

    try {
        $fileName = 'qr-' . bin2hex(random_bytes(24)) . '.' . $allowedTypes[$mimeType];
    } catch (Throwable $exception) {
        error_log('QR filename generation failed: ' . $exception->getMessage());
        $errors[] = 'The QR image could not be prepared for storage.';
        return null;
    }

    $destination = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file($tmpName, $destination)) {
        $errors[] = 'Failed to save the QR image.';
        return null;
    }

    @chmod($destination, 0640);
    return $fileName;
}

$details = null;
$getStmt = $conn->prepare('SELECT * FROM instructor_bank_details WHERE instructor_id = ? LIMIT 1');
$getStmt->bind_param('i', $instructorId);
$getStmt->execute();
$details = $getStmt->get_result()->fetch_assoc() ?: null;
$getStmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bankName = security_clean_text($_POST['bank_name'] ?? '', 150);
    $accountName = security_clean_text($_POST['account_name'] ?? '', 150);
    $accountNumber = security_clean_text($_POST['account_number'] ?? '', 100);
    $branchName = security_clean_text($_POST['branch_name'] ?? '', 150);
    $esewaNumber = (string) preg_replace('/\D+/', '', (string) ($_POST['esewa_number'] ?? ''));
    $khaltiNumber = (string) preg_replace('/\D+/', '', (string) ($_POST['khalti_number'] ?? ''));
    $errors = [];
    $newQrImage = upload_qr_image($_FILES['qr_image'] ?? [], $errors);
    $oldQrImage = (string) ($details['qr_image'] ?? '');
    $qrImage = $newQrImage ?: ($oldQrImage !== '' ? $oldQrImage : null);

    foreach ([
        'Bank name' => [$bankName, 150],
        'Account name' => [$accountName, 150],
        'Account number' => [$accountNumber, 100],
        'Branch name' => [$branchName, 150],
    ] as $label => [$value, $maximum]) {
        if ($value === '' && trim((string) ($_POST[strtolower(str_replace(' ', '_', $label))] ?? '')) !== '') {
            $errors[] = $label . " must be {$maximum} characters or fewer.";
        }
    }

    if ($accountNumber !== '' && !preg_match('/^[A-Za-z0-9 .\/-]{4,100}$/', $accountNumber)) {
        $errors[] = 'Bank account number contains unsupported characters.';
    }

    if ($esewaNumber !== '' && !preg_match('/^\d{10,15}$/', $esewaNumber)) {
        $errors[] = 'eSewa number must contain 10 to 15 digits.';
    }

    if ($khaltiNumber !== '' && !preg_match('/^\d{10,15}$/', $khaltiNumber)) {
        $errors[] = 'Khalti number must contain 10 to 15 digits.';
    }

    $hasBankMethod = $bankName !== '' || $accountName !== '' || $accountNumber !== '' || $branchName !== '';
    if ($hasBankMethod && ($bankName === '' || $accountName === '' || $accountNumber === '')) {
        $errors[] = 'Bank payout requires bank name, account name, and account number together.';
    }

    if (!$hasBankMethod && $esewaNumber === '' && $khaltiNumber === '' && $qrImage === null) {
        $errors[] = 'Add at least one complete payout method.';
    }

    if (!$errors) {
        try {
            $conn->begin_transaction();
            $saveStmt = $conn->prepare("
                INSERT INTO instructor_bank_details (
                    instructor_id, bank_name, account_name, account_number,
                    branch_name, esewa_number, khalti_number, qr_image
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    bank_name = VALUES(bank_name),
                    account_name = VALUES(account_name),
                    account_number = VALUES(account_number),
                    branch_name = VALUES(branch_name),
                    esewa_number = VALUES(esewa_number),
                    khalti_number = VALUES(khalti_number),
                    qr_image = VALUES(qr_image)
            ");
            $saveStmt->bind_param(
                'isssssss',
                $instructorId,
                $bankName,
                $accountName,
                $accountNumber,
                $branchName,
                $esewaNumber,
                $khaltiNumber,
                $qrImage
            );
            $saveStmt->execute();
            $saveStmt->close();
            $conn->commit();

            if ($newQrImage && $oldQrImage !== '' && $oldQrImage !== $newQrImage) {
                payout_delete_qr($oldQrImage);
            }

            Auth::redirect('instructor-payout-account.php?saved=1');
        } catch (Throwable $exception) {
            $conn->rollback();
            if ($newQrImage) {
                payout_delete_qr($newQrImage);
            }
            error_log('Payout account update failed: ' . $exception->getMessage());
            $message = 'Payout details could not be saved. Please try again.';
            $messageType = 'error';
        }
    } else {
        if ($newQrImage) {
            payout_delete_qr($newQrImage);
        }
        $message = implode(' ', array_unique($errors));
        $messageType = 'error';
    }

    $details = array_merge($details ?: [], [
        'bank_name' => $bankName,
        'account_name' => $accountName,
        'account_number' => $accountNumber,
        'branch_name' => $branchName,
        'esewa_number' => $esewaNumber,
        'khalti_number' => $khaltiNumber,
        'qr_image' => $oldQrImage,
    ]);
}

if (isset($_GET['saved'])) {
    $message = 'Payout account details saved successfully.';
    $messageType = 'success';
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/instructor_navbar.php';
?>

<main class="payout-account-page">
    <section class="payout-account-wrapper">
        <div class="payout-header">
            <div>
                <p class="page-label">Finance settings</p>
                <h1>Payout account</h1>
                <p>Add your bank, eSewa, Khalti, or private QR details so admin can send verified earnings.</p>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="payout-alert <?php echo h($messageType); ?>"><?php echo h($message); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="payout-form-card">
            <?php echo csrf_field(); ?>

            <div class="form-section">
                <h2>Bank Details</h2>
                <p>Complete all three required bank fields when using bank transfer.</p>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="bank_name">Bank Name</label>
                        <input id="bank_name" type="text" name="bank_name" maxlength="150" value="<?php echo h($details['bank_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="account_name">Account Name</label>
                        <input id="account_name" type="text" name="account_name" maxlength="150" value="<?php echo h($details['account_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="account_number">Account Number</label>
                        <input id="account_number" type="text" name="account_number" maxlength="100" value="<?php echo h($details['account_number'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="branch_name">Branch Name</label>
                        <input id="branch_name" type="text" name="branch_name" maxlength="150" value="<?php echo h($details['branch_name'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2>Wallet Details</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="esewa_number">eSewa Number</label>
                        <input id="esewa_number" type="text" name="esewa_number" inputmode="numeric" maxlength="15" value="<?php echo h($details['esewa_number'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="khalti_number">Khalti Number</label>
                        <input id="khalti_number" type="text" name="khalti_number" inputmode="numeric" maxlength="15" value="<?php echo h($details['khalti_number'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2>Private QR Image</h2>
                <?php if (!empty($details['qr_image'])): ?>
                    <div class="current-qr">
                        <img src="view-payout-qr.php" alt="Instructor payout QR">
                    </div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="qr_image">Upload QR Image</label>
                    <input id="qr_image" type="file" name="qr_image" accept="image/png,image/jpeg,image/webp">
                    <small>JPG, PNG, or WebP. Maximum 3 MB. Stored privately.</small>
                </div>
            </div>

            <button type="submit" class="save-payout-btn">Save Payout Details</button>
        </form>
    </section>
</main>

<?php require_once __DIR__ . '/../layouts/panel_end.php'; ?>
