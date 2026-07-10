<?php

require_once __DIR__ . '/../../middleware/AdminMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

AdminMiddleware::handle();

$message = '';
$messageType = '';

$defaultSettings = [
    'site_name' => 'Course Selling Platform',
    'site_email' => '',
    'site_phone' => '',
    'site_address' => '',
    'site_logo' => '',
    'esewa_id' => '',
    'khalti_id' => '',
    'bank_name' => '',
    'bank_account_name' => '',
    'bank_account_number' => '',
    'payment_instructions' => '',
    'terms_url' => '',
    'privacy_url' => ''
];

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function upload_site_logo(array $file, array &$errors): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Logo upload failed.';
        return null;
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        $errors[] = 'Logo must be less than 2MB.';
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png'
    ];

    if (!array_key_exists($mimeType, $allowedTypes)) {
        $errors[] = 'Only JPG and PNG logo files are allowed.';
        return null;
    }

    $uploadDir = __DIR__ . '/../../../public/assets/uploads/settings';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = 'site-logo-' . bin2hex(random_bytes(10)) . '.' . $allowedTypes[$mimeType];
    $destination = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $errors[] = 'Failed to save logo.';
        return null;
    }

    return $fileName;
}

function save_setting(mysqli $conn, string $key, string $value): void
{
    $sql = "
        INSERT INTO site_settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("ss", $key, $value);
        $stmt->execute();
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $errors = [];

    $siteName = trim($_POST['site_name'] ?? '');
    $siteEmail = trim($_POST['site_email'] ?? '');
    $sitePhone = trim($_POST['site_phone'] ?? '');
    $siteAddress = trim($_POST['site_address'] ?? '');
    $esewaId = trim($_POST['esewa_id'] ?? '');
    $khaltiId = trim($_POST['khalti_id'] ?? '');
    $bankName = trim($_POST['bank_name'] ?? '');
    $bankAccountName = trim($_POST['bank_account_name'] ?? '');
    $bankAccountNumber = trim($_POST['bank_account_number'] ?? '');
    $paymentInstructions = trim($_POST['payment_instructions'] ?? '');
    $termsUrl = trim($_POST['terms_url'] ?? '');
    $privacyUrl = trim($_POST['privacy_url'] ?? '');

    if ($siteName === '') {
        $errors[] = 'Platform name is required.';
    }

    if ($siteEmail !== '' && !filter_var($siteEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid contact email.';
    }

    $newLogo = upload_site_logo($_FILES['site_logo'] ?? [], $errors);

    if (empty($errors)) {
        save_setting($conn, 'site_name', $siteName);
        save_setting($conn, 'site_email', $siteEmail);
        save_setting($conn, 'site_phone', $sitePhone);
        save_setting($conn, 'site_address', $siteAddress);
        save_setting($conn, 'esewa_id', $esewaId);
        save_setting($conn, 'khalti_id', $khaltiId);
        save_setting($conn, 'bank_name', $bankName);
        save_setting($conn, 'bank_account_name', $bankAccountName);
        save_setting($conn, 'bank_account_number', $bankAccountNumber);
        save_setting($conn, 'payment_instructions', $paymentInstructions);
        save_setting($conn, 'terms_url', $termsUrl);
        save_setting($conn, 'privacy_url', $privacyUrl);

        if ($newLogo !== null) {
            save_setting($conn, 'site_logo', $newLogo);
        }

        $message = 'Settings updated successfully.';
        $messageType = 'success';
    } else {
        $message = implode(' ', $errors);
        $messageType = 'error';
    }
}

$settings = $defaultSettings;

$result = $conn->query("SELECT setting_key, setting_value FROM site_settings");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

$logoPath = '';

if (!empty($settings['site_logo'])) {
    $possibleLogoPath = __DIR__ . '/../../../public/assets/uploads/settings/' . $settings['site_logo'];

    if (file_exists($possibleLogoPath)) {
        $logoPath = 'assets/uploads/settings/' . $settings['site_logo'];
    }
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/admin_navbar.php';
?>


<main class="admin-settings-page">
    <section class="admin-settings-wrapper">

        <div class="admin-settings-header">
            <div>
                <p class="page-label">Admin Panel</p>
                <h1>Platform Settings</h1>
                <p>
                    Manage website information, contact details, payment information, and platform logo.
                </p>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="admin-alert <?php echo h($messageType); ?>">
                <?php echo h($message); ?>
            </div>
        <?php endif; ?>

        <div class="settings-layout">

            <div class="settings-preview-card">
                <h2>Current Platform Preview</h2>

                <div class="logo-preview">
                    <?php if ($logoPath !== ''): ?>
                        <img src="<?php echo h($logoPath); ?>" alt="Site Logo">
                    <?php else: ?>
                        <div class="logo-placeholder">
                            <?php echo h(strtoupper(substr($settings['site_name'], 0, 1))); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <h3><?php echo h($settings['site_name']); ?></h3>

                <div class="preview-info-list">
                    <div>
                        <span>Email</span>
                        <strong><?php echo h($settings['site_email'] ?: 'Not set'); ?></strong>
                    </div>

                    <div>
                        <span>Phone</span>
                        <strong><?php echo h($settings['site_phone'] ?: 'Not set'); ?></strong>
                    </div>

                    <div>
                        <span>Address</span>
                        <strong><?php echo h($settings['site_address'] ?: 'Not set'); ?></strong>
                    </div>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" class="settings-form-card">
                  <?php echo csrf_field(); ?>

                <div class="form-section">
                    <h2>Basic Website Settings</h2>
                    <p>These details can be shown on landing page, footer, contact page, and checkout.</p>

                    <div class="form-group">
                        <label for="site_name">Platform Name</label>
                        <input
                            type="text"
                            id="site_name"
                            name="site_name"
                            value="<?php echo h($settings['site_name']); ?>"
                            required
                        >
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="site_email">Contact Email</label>
                            <input
                                type="email"
                                id="site_email"
                                name="site_email"
                                value="<?php echo h($settings['site_email']); ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label for="site_phone">Contact Phone</label>
                            <input
                                type="text"
                                id="site_phone"
                                name="site_phone"
                                value="<?php echo h($settings['site_phone']); ?>"
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="site_address">Address</label>
                        <textarea
                            id="site_address"
                            name="site_address"
                            rows="3"
                        ><?php echo h($settings['site_address']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="site_logo">Platform Logo</label>
                        <input
                            type="file"
                            id="site_logo"
                            name="site_logo"
                            accept="image/png, image/jpeg"
                        >
                        <small>JPG or PNG only. Max 2MB.</small>
                    </div>
                </div>

                <div class="form-section">
                    <h2>Payment Settings</h2>
                    <p>These details help students pay manually through Nepal payment methods.</p>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="esewa_id">eSewa ID / Number</label>
                            <input
                                type="text"
                                id="esewa_id"
                                name="esewa_id"
                                value="<?php echo h($settings['esewa_id']); ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label for="khalti_id">Khalti ID / Number</label>
                            <input
                                type="text"
                                id="khalti_id"
                                name="khalti_id"
                                value="<?php echo h($settings['khalti_id']); ?>"
                            >
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="bank_name">Bank Name</label>
                            <input
                                type="text"
                                id="bank_name"
                                name="bank_name"
                                value="<?php echo h($settings['bank_name']); ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label for="bank_account_name">Bank Account Name</label>
                            <input
                                type="text"
                                id="bank_account_name"
                                name="bank_account_name"
                                value="<?php echo h($settings['bank_account_name']); ?>"
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="bank_account_number">Bank Account Number</label>
                        <input
                            type="text"
                            id="bank_account_number"
                            name="bank_account_number"
                            value="<?php echo h($settings['bank_account_number']); ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="payment_instructions">Payment Instructions</label>
                        <textarea
                            id="payment_instructions"
                            name="payment_instructions"
                            rows="5"
                            placeholder="Example: Pay using eSewa/Khalti/Bank transfer and upload payment proof."
                        ><?php echo h($settings['payment_instructions']); ?></textarea>
                    </div>
                </div>

                <div class="form-section">
                    <h2>Legal Links</h2>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="terms_url">Terms URL</label>
                            <input
                                type="text"
                                id="terms_url"
                                name="terms_url"
                                value="<?php echo h($settings['terms_url']); ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label for="privacy_url">Privacy URL</label>
                            <input
                                type="text"
                                id="privacy_url"
                                name="privacy_url"
                                value="<?php echo h($settings['privacy_url']); ?>"
                            >
                        </div>
                    </div>
                </div>

                <button type="submit" name="save_settings" class="save-settings-btn">
                    Save Settings
                </button>

            </form>

        </div>

    </section>
</main>

</body>
</html>
