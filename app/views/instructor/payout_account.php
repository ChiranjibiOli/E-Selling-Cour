<?php

require_once __DIR__ . '/../../middleware/InstructorMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

InstructorMiddleware::handle();

$user = Auth::user();
$instructorId = (int) ($user['id'] ?? 0);

$message = '';
$messageType = '';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function upload_qr_image(array $file, array &$errors): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'QR image upload failed.';
        return null;
    }

    if ($file['size'] > 3 * 1024 * 1024) {
        $errors[] = 'QR image must be less than 3MB.';
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (!array_key_exists($mimeType, $allowedTypes)) {
        $errors[] = 'Only JPG, PNG, or WEBP QR image is allowed.';
        return null;
    }

    $uploadDir = __DIR__ . '/../../../public/assets/uploads/instructor_qr';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = 'qr-' . bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
    $destination = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $errors[] = 'Failed to save QR image.';
        return null;
    }

    return $fileName;
}

$details = null;

$getSql = "
    SELECT *
    FROM instructor_bank_details
    WHERE instructor_id = ?
    LIMIT 1
";

$getStmt = $conn->prepare($getSql);

if ($getStmt) {
    $getStmt->bind_param("i", $instructorId);
    $getStmt->execute();

    $result = $getStmt->get_result();

    if ($result && $result->num_rows === 1) {
        $details = $result->fetch_assoc();
    }

    $getStmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bankName = trim($_POST['bank_name'] ?? '');
    $accountName = trim($_POST['account_name'] ?? '');
    $accountNumber = trim($_POST['account_number'] ?? '');
    $branchName = trim($_POST['branch_name'] ?? '');
    $esewaNumber = trim($_POST['esewa_number'] ?? '');
    $khaltiNumber = trim($_POST['khalti_number'] ?? '');

    $errors = [];

    $qrImage = upload_qr_image($_FILES['qr_image'] ?? [], $errors);

    if ($qrImage === null && $details && !empty($details['qr_image'])) {
        $qrImage = $details['qr_image'];
    }

    if (
        $bankName === '' &&
        $accountNumber === '' &&
        $esewaNumber === '' &&
        $khaltiNumber === '' &&
        $qrImage === null
    ) {
        $errors[] = 'Please add at least one payout method.';
    }

    if (empty($errors)) {
        $saveSql = "
            INSERT INTO instructor_bank_details (
                instructor_id,
                bank_name,
                account_name,
                account_number,
                branch_name,
                esewa_number,
                khalti_number,
                qr_image
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                bank_name = VALUES(bank_name),
                account_name = VALUES(account_name),
                account_number = VALUES(account_number),
                branch_name = VALUES(branch_name),
                esewa_number = VALUES(esewa_number),
                khalti_number = VALUES(khalti_number),
                qr_image = VALUES(qr_image)
        ";

        $saveStmt = $conn->prepare($saveSql);

        if ($saveStmt) {
            $saveStmt->bind_param(
                "isssssss",
                $instructorId,
                $bankName,
                $accountName,
                $accountNumber,
                $branchName,
                $esewaNumber,
                $khaltiNumber,
                $qrImage
            );

            if ($saveStmt->execute()) {
                header("Location: instructor-payout-account.php?saved=1");
                exit;
            }

            $message = 'Failed to save payout details.';
            $messageType = 'error';

            $saveStmt->close();
        } else {
            $message = 'Database error: ' . $conn->error;
            $messageType = 'error';
        }
    } else {
        $message = implode(' ', $errors);
        $messageType = 'error';
    }
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
                <p>
                    Add your bank, eSewa, Khalti, or QR details so admin can send your earnings.
                </p>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="payout-alert <?php echo h($messageType); ?>">
                <?php echo h($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="payout-form-card">
              <?php echo csrf_field(); ?>

            <div class="form-section">
                <h2>Bank Details</h2>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Bank Name</label>
                        <input type="text" name="bank_name" value="<?php echo h($details['bank_name'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Account Name</label>
                        <input type="text" name="account_name" value="<?php echo h($details['account_name'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Account Number</label>
                        <input type="text" name="account_number" value="<?php echo h($details['account_number'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Branch Name</label>
                        <input type="text" name="branch_name" value="<?php echo h($details['branch_name'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2>Wallet Details</h2>

                <div class="form-grid">
                    <div class="form-group">
                        <label>eSewa Number</label>
                        <input type="text" name="esewa_number" value="<?php echo h($details['esewa_number'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Khalti Number</label>
                        <input type="text" name="khalti_number" value="<?php echo h($details['khalti_number'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2>QR Image</h2>

                <?php if (!empty($details['qr_image'])): ?>
                    <div class="current-qr">
                        <img src="assets/uploads/instructor_qr/<?php echo h($details['qr_image']); ?>" alt="Instructor QR">
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Upload QR Image</label>
                    <input type="file" name="qr_image" accept="image/png, image/jpeg, image/webp">
                </div>
            </div>

            <button type="submit" class="save-payout-btn">
                Save Payout Details
            </button>

        </form>

    </section>
</main>

</body>
</html>
