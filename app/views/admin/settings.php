<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/AdminMiddleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/security_helper.php';

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
    'privacy_url' => '',
];

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function settings_logo_directory(): string
{
    return __DIR__ . '/../../../public/assets/uploads/settings';
}

function settings_delete_logo(?string $fileName): void
{
    $safeName = basename((string) $fileName);
    if ($safeName === '' || $safeName === '.' || $safeName === '..') {
        return;
    }

    $path = settings_logo_directory() . DIRECTORY_SEPARATOR . $safeName;
    if (is_file($path)) {
        @unlink($path);
    }
}

function upload_site_logo(array $file, array &$errors): ?string
{
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($errorCode !== UPLOAD_ERR_OK) {
        $errors[] = 'Logo upload failed.';
        return null;
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        $errors[] = 'Logo upload could not be verified.';
        return null;
    }
    if ($size < 1 || $size > 2 * 1024 * 1024) {
        $errors[] = 'Logo must be a non-empty image no larger than 2 MB.';
        return null;
    }

    $imageInfo = @getimagesize($tmpName);
    if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
        $errors[] = 'Logo must contain a genuine image.';
        return null;
    }

    $width = (int) $imageInfo[0];
    $height = (int) $imageInfo[1];
    if ($width < 64 || $height < 64 || $width > 5000 || $height > 5000 || ($width * $height) > 20_000_000) {
        $errors[] = 'Logo dimensions must be between 64 × 64 and 5000 × 5000 pixels.';
        return null;
    }

    $mimeType = Security::detectMimeType($tmpName, (string) ($imageInfo['mime'] ?? ''));
    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!isset($allowedTypes[$mimeType])) {
        $errors[] = 'Only a genuine JPG or PNG logo is allowed.';
        return null;
    }

    $uploadDir = settings_logo_directory();
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        $errors[] = 'The logo directory could not be created.';
        return null;
    }
    if (!is_writable($uploadDir)) {
        $errors[] = 'The logo directory is not writable.';
        return null;
    }

    try {
        $fileName = 'site-logo-' . bin2hex(random_bytes(24)) . '.' . $allowedTypes[$mimeType];
    } catch (Throwable $exception) {
        error_log('Logo filename generation failed: ' . $exception->getMessage());
        $errors[] = 'The logo could not be prepared for storage.';
        return null;
    }

    $destination = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file($tmpName, $destination)) {
        $errors[] = 'Failed to save the logo.';
        return null;
    }

    @chmod($destination, 0644);
    return $fileName;
}

function save_setting(mysqli $conn, string $key, string $value): void
{
    $stmt = $conn->prepare("
        INSERT INTO site_settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    if (!$stmt) {
        throw new RuntimeException('A platform setting could not be prepared.');
    }
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
    $stmt->close();
}

$settings = $defaultSettings;
$result = $conn->query('SELECT setting_key, setting_value FROM site_settings');
while ($result && $row = $result->fetch_assoc()) {
    if (array_key_exists((string) $row['setting_key'], $settings)) {
        $settings[(string) $row['setting_key']] = (string) $row['setting_value'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $errors = [];
    $submitted = [
        'site_name' => security_clean_text($_POST['site_name'] ?? '', 150),
        'site_email' => strtolower(trim((string) ($_POST['site_email'] ?? ''))),
        'site_phone' => security_clean_text($_POST['site_phone'] ?? '', 50),
        'site_address' => security_clean_text($_POST['site_address'] ?? '', 500, true),
        'esewa_id' => security_clean_text($_POST['esewa_id'] ?? '', 100),
        'khalti_id' => security_clean_text($_POST['khalti_id'] ?? '', 100),
        'bank_name' => security_clean_text($_POST['bank_name'] ?? '', 150),
        'bank_account_name' => security_clean_text($_POST['bank_account_name'] ?? '', 150),
        'bank_account_number' => security_clean_text($_POST['bank_account_number'] ?? '', 100),
        'payment_instructions' => security_clean_text($_POST['payment_instructions'] ?? '', 2000, true),
        'terms_url' => trim((string) ($_POST['terms_url'] ?? '')),
        'privacy_url' => trim((string) ($_POST['privacy_url'] ?? '')),
    ];

    if ($submitted['site_name'] === '') {
        $errors[] = 'Platform name is required and must be 150 characters or fewer.';
    }
    if ($submitted['site_email'] !== '' && (!filter_var($submitted['site_email'], FILTER_VALIDATE_EMAIL) || strlen($submitted['site_email']) > 150)) {
        $errors[] = 'Enter a valid contact email no longer than 150 characters.';
    }
    if ($submitted['site_phone'] !== '' && !preg_match('/^[0-9+() .\-]{7,50}$/', $submitted['site_phone'])) {
        $errors[] = 'Contact phone contains unsupported characters.';
    }
    if ($submitted['bank_account_number'] !== '' && !preg_match('/^[A-Za-z0-9 .\/-]{4,100}$/', $submitted['bank_account_number'])) {
        $errors[] = 'Bank account number contains unsupported characters.';
    }

    foreach (['terms_url' => 'Terms URL', 'privacy_url' => 'Privacy URL'] as $key => $label) {
        if ($submitted[$key] !== '') {
            $safeUrl = security_safe_external_url($submitted[$key]);
            if ($safeUrl === null || $safeUrl === '') {
                $errors[] = $label . ' must be a public HTTP or HTTPS URL.';
            } else {
                $submitted[$key] = $safeUrl;
            }
        }
    }

    $oldLogo = basename((string) ($settings['site_logo'] ?? ''));
    $newLogo = upload_site_logo($_FILES['site_logo'] ?? [], $errors);

    if ($errors === []) {
        try {
            $conn->begin_transaction();
            foreach ($submitted as $key => $value) {
                save_setting($conn, $key, (string) $value);
            }
            if ($newLogo !== null) {
                save_setting($conn, 'site_logo', $newLogo);
            }
            $conn->commit();

            if ($newLogo !== null && $oldLogo !== '' && $newLogo !== $oldLogo) {
                settings_delete_logo($oldLogo);
            }

            Auth::redirect('admin-settings.php?saved=1');
        } catch (Throwable $exception) {
            $conn->rollback();
            if ($newLogo !== null) {
                settings_delete_logo($newLogo);
            }
            error_log('Platform settings update failed: ' . $exception->getMessage());
            $message = 'Settings could not be saved right now.';
            $messageType = 'error';
        }
    } else {
        if ($newLogo !== null) {
            settings_delete_logo($newLogo);
        }
        $message = implode(' ', array_unique($errors));
        $messageType = 'error';
    }

    $settings = array_merge($settings, $submitted);
}

if (isset($_GET['saved'])) {
    $message = 'Settings updated successfully.';
    $messageType = 'success';
}

$logoPath = '';
$logoName = basename((string) ($settings['site_logo'] ?? ''));
if ($logoName !== '') {
    $possibleLogoPath = settings_logo_directory() . DIRECTORY_SEPARATOR . $logoName;
    if (is_file($possibleLogoPath) && in_array(Security::detectMimeType($possibleLogoPath), ['image/jpeg', 'image/png'], true)) {
        $logoPath = 'assets/uploads/settings/' . rawurlencode($logoName);
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
                <p>Manage website information, payment instructions, legal links, and the platform logo.</p>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="admin-alert <?php echo h($messageType); ?>"><?php echo h($message); ?></div>
        <?php endif; ?>

        <div class="settings-layout">
            <div class="settings-preview-card">
                <h2>Current Platform Preview</h2>
                <div class="logo-preview">
                    <?php if ($logoPath !== ''): ?>
                        <img src="<?php echo h($logoPath); ?>" alt="Site Logo">
                    <?php else: ?>
                        <div class="logo-placeholder"><?php echo h(strtoupper(substr($settings['site_name'], 0, 1))); ?></div>
                    <?php endif; ?>
                </div>
                <h3><?php echo h($settings['site_name']); ?></h3>
                <div class="preview-info-list">
                    <div><span>Email</span><strong><?php echo h($settings['site_email'] ?: 'Not set'); ?></strong></div>
                    <div><span>Phone</span><strong><?php echo h($settings['site_phone'] ?: 'Not set'); ?></strong></div>
                    <div><span>Address</span><strong><?php echo h($settings['site_address'] ?: 'Not set'); ?></strong></div>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" class="settings-form-card">
                <?php echo csrf_field(); ?>

                <div class="form-section">
                    <h2>Basic Website Settings</h2>
                    <p>These details can be shown on the landing page, footer, contact page, and checkout.</p>
                    <div class="form-group">
                        <label for="site_name">Platform Name</label>
                        <input type="text" id="site_name" name="site_name" maxlength="150" value="<?php echo h($settings['site_name']); ?>" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="site_email">Contact Email</label>
                            <input type="email" id="site_email" name="site_email" maxlength="150" value="<?php echo h($settings['site_email']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="site_phone">Contact Phone</label>
                            <input type="text" id="site_phone" name="site_phone" maxlength="50" value="<?php echo h($settings['site_phone']); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="site_address">Address</label>
                        <textarea id="site_address" name="site_address" rows="3" maxlength="500"><?php echo h($settings['site_address']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="site_logo">Platform Logo</label>
                        <input type="file" id="site_logo" name="site_logo" accept="image/png,image/jpeg">
                        <small>Genuine JPG or PNG only. Maximum 2 MB.</small>
                    </div>
                </div>

                <div class="form-section">
                    <h2>Payment Settings</h2>
                    <p>These details are displayed during manual checkout.</p>
                    <div class="form-row">
                        <div class="form-group"><label for="esewa_id">eSewa ID / Number</label><input type="text" id="esewa_id" name="esewa_id" maxlength="100" value="<?php echo h($settings['esewa_id']); ?>"></div>
                        <div class="form-group"><label for="khalti_id">Khalti ID / Number</label><input type="text" id="khalti_id" name="khalti_id" maxlength="100" value="<?php echo h($settings['khalti_id']); ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label for="bank_name">Bank Name</label><input type="text" id="bank_name" name="bank_name" maxlength="150" value="<?php echo h($settings['bank_name']); ?>"></div>
                        <div class="form-group"><label for="bank_account_name">Bank Account Name</label><input type="text" id="bank_account_name" name="bank_account_name" maxlength="150" value="<?php echo h($settings['bank_account_name']); ?>"></div>
                    </div>
                    <div class="form-group"><label for="bank_account_number">Bank Account Number</label><input type="text" id="bank_account_number" name="bank_account_number" maxlength="100" value="<?php echo h($settings['bank_account_number']); ?>"></div>
                    <div class="form-group"><label for="payment_instructions">Payment Instructions</label><textarea id="payment_instructions" name="payment_instructions" rows="5" maxlength="2000" placeholder="Explain how to pay and submit proof."><?php echo h($settings['payment_instructions']); ?></textarea></div>
                </div>

                <div class="form-section">
                    <h2>Legal Links</h2>
                    <div class="form-row">
                        <div class="form-group"><label for="terms_url">Terms URL</label><input type="url" id="terms_url" name="terms_url" maxlength="2048" value="<?php echo h($settings['terms_url']); ?>"></div>
                        <div class="form-group"><label for="privacy_url">Privacy URL</label><input type="url" id="privacy_url" name="privacy_url" maxlength="2048" value="<?php echo h($settings['privacy_url']); ?>"></div>
                    </div>
                </div>

                <button type="submit" name="save_settings" class="save-settings-btn">Save Settings</button>
            </form>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../layouts/panel_end.php'; ?>
