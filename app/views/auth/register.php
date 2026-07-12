<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../helpers/profile_helper.php';

Auth::guestOnly();

$message = '';
$messageType = '';
$fullName = '';
$email = '';
$phone = '';
$requestedRole = trim((string) ($_GET['role'] ?? ''));
$role = $requestedRole === 'instructor' ? 'instructor' : 'student';

function register_upload_image(array $file, string $targetDirectory, array &$errors, string $label): ?string
{
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        $errors[] = $label . ' is required.';
        return null;
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        $errors[] = $label . ' upload failed.';
        return null;
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        $errors[] = $label . ' upload could not be verified.';
        return null;
    }

    if ($size < 1 || $size > 2 * 1024 * 1024) {
        $errors[] = $label . ' must be a non-empty image no larger than 2 MB.';
        return null;
    }

    $imageInfo = @getimagesize($tmpName);
    if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
        $errors[] = $label . ' must contain a genuine image.';
        return null;
    }

    $width = (int) $imageInfo[0];
    $height = (int) $imageInfo[1];

    if ($width < 160 || $height < 160) {
        $errors[] = $label . ' must be at least 160 × 160 pixels.';
        return null;
    }

    if ($width > 6000 || $height > 6000 || ($width * $height) > 30_000_000) {
        $errors[] = $label . ' dimensions are too large.';
        return null;
    }

    $mimeType = profile_detect_image_mime($tmpName, $imageInfo);
    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    if (!isset($allowedMimeTypes[$mimeType])) {
        $errors[] = $label . ' must be a JPG or PNG image.';
        return null;
    }

    if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0750, true) && !is_dir($targetDirectory)) {
        $errors[] = 'The secure upload directory could not be created.';
        return null;
    }

    if (!is_writable($targetDirectory)) {
        $errors[] = 'The secure upload directory is not writable.';
        return null;
    }

    try {
        $safeFileName = bin2hex(random_bytes(24)) . '.' . $allowedMimeTypes[$mimeType];
    } catch (Throwable $exception) {
        error_log('Registration filename generation failed: ' . $exception->getMessage());
        $errors[] = $label . ' could not be prepared for storage.';
        return null;
    }

    $destination = rtrim($targetDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeFileName;

    if (!move_uploaded_file($tmpName, $destination)) {
        $errors[] = 'Failed to save ' . strtolower($label) . '.';
        return null;
    }

    @chmod($destination, 0640);
    return $safeFileName;
}

function register_delete_private_file(string $directory, ?string $fileName): void
{
    $safeName = basename((string) $fileName);
    if ($safeName === '' || $safeName === '.' || $safeName === '..') {
        return;
    }

    $realDirectory = realpath($directory);
    if ($realDirectory === false) {
        return;
    }

    $path = realpath($realDirectory . DIRECTORY_SEPARATOR . $safeName);
    if ($path !== false && is_file($path) && str_starts_with($path, $realDirectory . DIRECTORY_SEPARATOR)) {
        @unlink($path);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $rateIdentity = Security::clientIp();
    $retryAfter = Security::rateLimitRetryAfter('register', $rateIdentity, 5, 3600, 3600);

    $fullName = profile_clean_text($_POST['full_name'] ?? '');
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = profile_normalize_phone($_POST['phone'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $role = (string) ($_POST['role'] ?? 'student');
    $errors = [];

    if ($retryAfter > 0) {
        $errors[] = 'Too many registration attempts. Try again later.';
    }

    if (!profile_valid_name($fullName)) {
        $errors[] = 'Enter a valid full name between 2 and 100 characters.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $errors[] = 'Enter a valid email address.';
    }

    if (!profile_valid_phone($phone, true)) {
        $errors[] = 'Phone number must contain 10 to 15 digits.';
    }

    if (!in_array($role, ['student', 'instructor'], true)) {
        $errors[] = 'Invalid role selected.';
    }

    if (strlen($password) > 4096 || !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/', $password)) {
        $errors[] = 'Password must be 8 to 4096 characters and include uppercase, lowercase, number, and special character.';
    }

    if (!hash_equals($password, $confirmPassword)) {
        $errors[] = 'Passwords do not match.';
    }

    $identityDirectory = __DIR__ . '/../../../storage/private_uploads/instructor_documents';
    $profileDirectory = __DIR__ . '/../../../storage/private_uploads/profile_photos';
    $identityDocumentName = null;
    $profileImageName = null;
    $transactionStarted = false;

    try {
        if (!$errors) {
            $checkStmt = $conn->prepare('SELECT id FROM users WHERE LOWER(email) = ? LIMIT 1');
            $checkStmt->bind_param('s', $email);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows > 0) {
                $errors[] = 'This email is already registered.';
            }

            $checkStmt->close();
        }

        if (!$errors && $role === 'instructor') {
            $identityDocumentName = register_upload_image(
                $_FILES['identity_document'] ?? [],
                $identityDirectory,
                $errors,
                'Identity document image'
            );
            $profileImageName = register_upload_image(
                $_FILES['profile_image'] ?? [],
                $profileDirectory,
                $errors,
                'Personal photo'
            );
        }

        if (!$errors) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $status = $role === 'instructor' ? 'inactive' : 'active';

            $conn->begin_transaction();
            $transactionStarted = true;

            $insertStmt = $conn->prepare(
                'INSERT INTO users (full_name, email, password, phone, profile_image, identity_document, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insertStmt->bind_param(
                'ssssssss',
                $fullName,
                $email,
                $hashedPassword,
                $phone,
                $profileImageName,
                $identityDocumentName,
                $role,
                $status
            );
            $insertStmt->execute();
            $insertStmt->close();
            $conn->commit();
            $transactionStarted = false;

            Security::clearRateLimit('register', $rateIdentity);
            $_SESSION['registration_success'] = $role === 'instructor'
                ? 'Instructor registration submitted. Wait for admin approval before logging in.'
                : 'Registration successful. You can now log in.';

            Auth::redirect('login.php');
        }
    } catch (Throwable $exception) {
        if ($transactionStarted) {
            $conn->rollback();
        }

        error_log('Registration failed: ' . $exception->getMessage());
        Security::recordRateLimitFailure('register', $rateIdentity, 5, 3600, 3600);
        $errors[] = $exception instanceof mysqli_sql_exception && (int) $exception->getCode() === 1062
            ? 'This email is already registered.'
            : 'Registration could not be completed. Please try again.';
    }

    if ($errors) {
        if ($identityDocumentName !== null) {
            register_delete_private_file($identityDirectory, $identityDocumentName);
        }
        if ($profileImageName !== null) {
            register_delete_private_file($profileDirectory, $profileImageName);
        }

        if ($retryAfter <= 0) {
            Security::recordRateLimitFailure('register', $rateIdentity, 5, 3600, 3600);
        }

        $message = implode(' ', array_unique($errors));
        $messageType = 'error';
    }
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<link rel="stylesheet" href="assets/css/pages/public/auth.css?v=12">
<main class="auth-page">
    <section class="auth-section">
        <div class="container auth-container">
            <div class="auth-card">
                <div class="auth-left">
                    <span class="auth-badge">Join Our Platform</span>
                    <h1>Create Your Account</h1>
                    <p>Register as a student to start learning or as an instructor to teach and sell courses.</p>
                    <ul class="auth-benefits">
                        <li>Buy once and get lifetime access</li>
                        <li>Learn from approved instructors</li>
                        <li>Instructor accounts require admin approval</li>
                    </ul>
                </div>

                <div class="auth-right">
                    <h2>Register Now</h2>

                    <?php if ($message !== ''): ?>
                        <div class="form-message <?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="register.php<?php echo $role === 'instructor' ? '?role=instructor' : ''; ?>" id="registerForm" enctype="multipart/form-data" novalidate>
                        <?php echo csrf_field(); ?>

                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?>" maxlength="100" autocomplete="name" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" maxlength="150" autocomplete="email" required>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>" inputmode="numeric" maxlength="15" autocomplete="tel" required>
                        </div>

                        <div class="form-group">
                            <label for="role">Register As</label>
                            <select id="role" name="role" required>
                                <option value="student" <?php echo $role === 'student' ? 'selected' : ''; ?>>Student</option>
                                <option value="instructor" <?php echo $role === 'instructor' ? 'selected' : ''; ?>>Instructor</option>
                            </select>
                        </div>

                        <div id="instructorFields" style="<?php echo $role === 'instructor' ? '' : 'display:none;'; ?>">
                            <div class="form-group">
                                <label for="identity_document">Identity Document Photo</label>
                                <input type="file" id="identity_document" name="identity_document" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                            </div>
                            <div class="form-group">
                                <label for="profile_image">Personal Photo</label>
                                <input type="file" id="profile_image" name="profile_image" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                            </div>
                        </div>

                        <div class="form-group password-group">
                            <label for="password">Password</label>
                            <div class="password-field">
                                <input type="password" id="password" name="password" maxlength="4096" autocomplete="new-password" required>
                                <button type="button" class="toggle-password" data-target="password">Show</button>
                            </div>
                            <small class="field-note">At least 8 characters with uppercase, lowercase, number, and special character.</small>
                        </div>

                        <div class="form-group password-group">
                            <label for="confirm_password">Confirm Password</label>
                            <div class="password-field">
                                <input type="password" id="confirm_password" name="confirm_password" maxlength="4096" autocomplete="new-password" required>
                                <button type="button" class="toggle-password" data-target="confirm_password">Show</button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary auth-submit-btn">Create Account</button>
                        <p class="auth-switch">Already have an account? <a href="login.php">Login here</a></p>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
