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

function clean_input(string $value): string
{
    return trim($value);
}

function validate_name(string $name): bool
{
    return (bool) preg_match("/^[a-zA-Z\s.'-]{2,100}$/", $name);
}

function validate_phone(string $phone): bool
{
    return (bool) preg_match('/^\d{10,15}$/', $phone);
}

function validate_password_strength(string $password): bool
{
    return (bool) preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/', $password);
}

function upload_image(array $file, string $targetDirectory, array &$errors): ?string
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed.';
        return null;
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        $errors[] = 'File size must be less than 2MB.';
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/jpg'  => 'jpg'
    ];

    if (!array_key_exists($mimeType, $allowedMimeTypes)) {
        $errors[] = 'Only JPG and PNG images are allowed.';
        return null;
    }

    if (!is_dir($targetDirectory)) {
        mkdir($targetDirectory, 0750, true);
    }

    $extension = $allowedMimeTypes[$mimeType];
    $safeFileName = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = rtrim($targetDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeFileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $errors[] = 'Failed to save uploaded file.';
        return null;
    }

    return $safeFileName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_user'])) {
    $fullName = clean_input($_POST['full_name'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $phone = preg_replace('/\D+/', '', $_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'student';

    $allowedRoles = ['student', 'instructor'];
    $errors = [];

    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    } elseif (!validate_name($fullName)) {
        $errors[] = 'Full name can contain only letters, spaces, apostrophe, dot, and hyphen.';
    }

    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (strlen($email) > 150) {
        $errors[] = 'Email is too long.';
    }

    if ($phone === '') {
        $errors[] = 'Phone number is required.';
    } elseif (!validate_phone($phone)) {
        $errors[] = 'Phone number must contain only 10 to 15 digits.';
    }

    if (!in_array($role, $allowedRoles, true)) {
        $errors[] = 'Invalid role selected.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif (!validate_password_strength($password)) {
        $errors[] = 'Password must be at least 8 characters and include uppercase, lowercase, number, and special character.';
    }

    if ($confirmPassword === '') {
        $errors[] = 'Please confirm your password.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    $identityDocumentName = null;
    $profileImageName = null;

    if ($role === 'instructor') {
        if (!isset($_FILES['identity_document']) || $_FILES['identity_document']['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Identity document image is required for instructors.';
        }

        if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Personal photo is required for instructors.';
        }
    }

    $checkSql = "SELECT id FROM users WHERE email = ?";
    $checkStmt = $conn->prepare($checkSql);

    if ($checkStmt && empty($errors)) {
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $errors[] = 'This email is already registered.';
        }

        $checkStmt->close();
    }

    if (empty($errors) && $role === 'instructor') {
        $privateUploadPath = __DIR__ . '/../../../storage/private_uploads/instructor_documents';
        $profileUploadPath = __DIR__ . '/../../../storage/private_uploads/profile_photos';

        $identityDocumentName = upload_image($_FILES['identity_document'], $privateUploadPath, $errors);
        $profileImageName = upload_image($_FILES['profile_image'], $profileUploadPath, $errors);
    }

    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $insertSql = "
            INSERT INTO users (
                full_name,
                email,
                password,
                phone,
                profile_image,
                identity_document,
                role,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $status = ($role === 'instructor') ? 'inactive' : 'active';

        $insertStmt = $conn->prepare($insertSql);

        if ($insertStmt) {
            $insertStmt->bind_param(
                "ssssssss",
                $fullName,
                $email,
                $hashedPassword,
                $phone,
                $profileImageName,
                $identityDocumentName,
                $role,
                $status
            );

            if ($insertStmt->execute()) {
                $message = ($role === 'instructor')
                    ? 'Instructor registration submitted successfully. Wait for admin approval.'
                    : 'Registration successful! You can now login.';
                $messageType = 'success';

                $fullName = '';
                $email = '';
                $phone = '';
                $role = 'student';
            } else {
                $message = 'Something went wrong while registering.';
                $messageType = 'error';
            }

            $insertStmt->close();
        } else {
            $message = 'Failed to prepare registration query.';
            $messageType = 'error';
        }
    } else {
        $message = implode(' ', $errors);
        $messageType = 'error';
    }
}
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<link rel="stylesheet" href="assets/css/pages/public/auth.css?v=1">
<main class="auth-page">
    <section class="auth-section">
        <div class="container auth-container">
            <div class="auth-card">
                <div class="auth-left">
                    <span class="auth-badge">Join Our Platform</span>
                    <h1>Create Your Account</h1>
                    <p>
                        Register as a student to start learning or as an instructor to teach and sell courses.
                    </p>

                    <ul class="auth-benefits">
                        <li>Buy once and get lifetime access</li>
                        <li>Learn from expert instructors</li>
                        <li>Instructor accounts require identity verification</li>
                    </ul>
                </div>

                <div class="auth-right">
                    <h2>Register Now</h2>

                    <?php if ($message !== ''): ?>
                        <div class="form-message <?php echo htmlspecialchars($messageType); ?>">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" id="registerForm" enctype="multipart/form-data" novalidate>
                        <?php echo csrf_field(); ?>
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                value="<?php echo htmlspecialchars($fullName); ?>"
                                placeholder="Enter your full name"
                                maxlength="100"
                                required
                            >
                            <small class="field-note">Only letters, spaces, apostrophe, dot, and hyphen allowed.</small>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="<?php echo htmlspecialchars($email); ?>"
                                placeholder="Enter your email"
                                maxlength="150"
                                required
                            >
                            <small id="emailFeedback" class="field-note"></small>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input
                                type="text"
                                id="phone"
                                name="phone"
                                value="<?php echo htmlspecialchars($phone); ?>"
                                placeholder="Enter your phone number"
                                inputmode="numeric"
                                maxlength="15"
                                required
                            >
                            <small class="field-note">Only numbers allowed, 10 to 15 digits.</small>
                        </div>

                        <div class="form-group">
                            <label for="role">Register As</label>
                            <select id="role" name="role" required>
                                <option value="student" <?php echo ($role === 'student') ? 'selected' : ''; ?>>Student</option>
                                <option value="instructor" <?php echo ($role === 'instructor') ? 'selected' : ''; ?>>Instructor</option>
                            </select>
                        </div>

                        <div id="instructorFields" style="<?php echo ($role === 'instructor') ? '' : 'display:none;'; ?>">
                            <div class="form-group">
                                <label for="identity_document">Identity Document Photo</label>
                                <input type="file" id="identity_document" name="identity_document" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                                <small class="field-note">Upload citizenship, ID, or similar document image.</small>
                            </div>

                            <div class="form-group">
                                <label for="profile_image">Personal Photo</label>
                                <input type="file" id="profile_image" name="profile_image" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                                <small class="field-note">Upload a clear personal profile image.</small>
                            </div>
                        </div>

                        <div class="form-group password-group">
                            <label for="password">Password</label>
                            <div class="password-field">
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    placeholder="Enter your password"
                                    required
                                >
                                <button type="button" class="toggle-password" data-target="password">Show</button>
                            </div>
                            <small class="field-note">Minimum 8 characters, uppercase, lowercase, number, and special character.</small>
                        </div>

                        <div class="form-group password-group">
                            <label for="confirm_password">Confirm Password</label>
                            <div class="password-field">
                                <input
                                    type="password"
                                    id="confirm_password"
                                    name="confirm_password"
                                    placeholder="Confirm your password"
                                    required
                                >
                                <button type="button" class="toggle-password" data-target="confirm_password">Show</button>
                            </div>
                        </div>

                        <button type="submit" name="register_user" class="btn btn-primary auth-submit-btn">
                            Create Account
                        </button>

                        <p class="auth-switch">
                            Already have an account?
                            <a href="login.php">Login here</a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>