<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Auth.php';

Auth::guestOnly();

$message = '';
$messageType = '';
$fullName = '';
$email = '';
$phone = '';
$requestedRole = trim((string) ($_GET['role'] ?? ''));
$role = $requestedRole === 'instructor' ? 'instructor' : 'student';

function register_upload_image(array $file, string $targetDirectory, array &$errors): ?string
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed.';
        return null;
    }

    if ((int) $file['size'] > 2 * 1024 * 1024) {
        $errors[] = 'File size must be less than 2MB.';
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = (string) $finfo->file((string) $file['tmp_name']);
    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    if (!isset($allowedMimeTypes[$mimeType])) {
        $errors[] = 'Only JPG and PNG images are allowed.';
        return null;
    }

    if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0750, true) && !is_dir($targetDirectory)) {
        $errors[] = 'Upload directory could not be created.';
        return null;
    }

    $safeFileName = bin2hex(random_bytes(16)) . '.' . $allowedMimeTypes[$mimeType];
    $destination = rtrim($targetDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeFileName;

    if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
        $errors[] = 'Failed to save uploaded file.';
        return null;
    }

    return $safeFileName;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? '')) ?? '';
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $role = (string) ($_POST['role'] ?? 'student');
    $errors = [];

    if (!preg_match("/^[a-zA-Z\s.'-]{2,100}$/", $fullName)) {
        $errors[] = 'Enter a valid full name.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $errors[] = 'Enter a valid email address.';
    }

    if (!preg_match('/^\d{10,15}$/', $phone)) {
        $errors[] = 'Phone number must contain 10 to 15 digits.';
    }

    if (!in_array($role, ['student', 'instructor'], true)) {
        $errors[] = 'Invalid role selected.';
    }

    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/', $password)) {
        $errors[] = 'Password must be at least 8 characters and include uppercase, lowercase, number, and special character.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    $identityDocumentName = null;
    $profileImageName = null;

    if ($role === 'instructor') {
        if (!isset($_FILES['identity_document']) || ($_FILES['identity_document']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Identity document image is required for instructors.';
        }

        if (!isset($_FILES['profile_image']) || ($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Personal photo is required for instructors.';
        }
    }

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
                $_FILES['identity_document'],
                __DIR__ . '/../../../storage/private_uploads/instructor_documents',
                $errors
            );
            $profileImageName = register_upload_image(
                $_FILES['profile_image'],
                __DIR__ . '/../../../storage/private_uploads/profile_photos',
                $errors
            );
        }

        if (!$errors) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $status = $role === 'instructor' ? 'inactive' : 'active';

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

            $_SESSION['registration_success'] = $role === 'instructor'
                ? 'Instructor registration submitted. Wait for admin approval before logging in.'
                : 'Registration successful. You can now log in.';

            Auth::redirect('login.php');
        }
    } catch (mysqli_sql_exception $exception) {
        error_log('Registration failed: ' . $exception->getMessage());
        $errors[] = 'Registration could not be completed. Please try again.';
    }

    if ($errors) {
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
                                <input type="password" id="password" name="password" autocomplete="new-password" required>
                                <button type="button" class="toggle-password" data-target="password">Show</button>
                            </div>
                            <small class="field-note">At least 8 characters with uppercase, lowercase, number, and special character.</small>
                        </div>

                        <div class="form-group password-group">
                            <label for="confirm_password">Confirm Password</label>
                            <div class="password-field">
                                <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
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