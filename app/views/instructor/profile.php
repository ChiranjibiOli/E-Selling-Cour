<?php

require_once __DIR__ . '/../../middleware/InstructorMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

InstructorMiddleware::handle();

$currentUser = Auth::user();
$instructorId = (int) ($currentUser['id'] ?? 0);

$message = '';
$messageType = '';

function validate_profile_name(string $name): bool
{
    return (bool) preg_match("/^[a-zA-Z\s.'-]{2,100}$/", $name);
}

function validate_profile_phone(string $phone): bool
{
    return (bool) preg_match('/^\d{10,15}$/', $phone);
}

function upload_instructor_profile_image(array $file, array &$errors): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Profile image upload failed.';
        return null;
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        $errors[] = 'Profile image must be less than 2MB.';
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png'
    ];

    if (!array_key_exists($mimeType, $allowedTypes)) {
        $errors[] = 'Only JPG and PNG profile images are allowed.';
        return null;
    }

    $uploadDir = __DIR__ . '/../../../public/assets/uploads/profile_photos';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
    $destination = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $errors[] = 'Failed to save profile image.';
        return null;
    }

    return $fileName;
}

$existingImageStmt = $conn->prepare("SELECT profile_image FROM users WHERE id = ? AND role = 'instructor' LIMIT 1");
$existingImageStmt->bind_param('i', $instructorId);
$existingImageStmt->execute();
$existingProfileImage = (string) (($existingImageStmt->get_result()->fetch_assoc()['profile_image'] ?? ''));
$existingImageStmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_profile_photo'])) {
    $deleteStmt = $conn->prepare("UPDATE users SET profile_image = NULL WHERE id = ? AND role = 'instructor'");
    $deleteStmt->bind_param('i', $instructorId);
    $deleteStmt->execute();
    $deleteStmt->close();

    if ($existingProfileImage !== '') {
        $oldPath = __DIR__ . '/../../../public/assets/uploads/profile_photos/' . basename($existingProfileImage);
        if (is_file($oldPath)) {
            unlink($oldPath);
        }
    }

    header('Location: instructor-profile.php?photo_deleted=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = preg_replace('/\D+/', '', $_POST['phone'] ?? '');

    $errors = [];

    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    } elseif (!validate_profile_name($fullName)) {
        $errors[] = 'Full name can contain only letters, spaces, apostrophe, dot, and hyphen.';
    }

    if ($phone === '') {
        $errors[] = 'Phone number is required.';
    } elseif (!validate_profile_phone($phone)) {
        $errors[] = 'Phone number must contain 10 to 15 digits.';
    }

    $newProfileImage = upload_instructor_profile_image($_FILES['profile_image'] ?? [], $errors);

    if (empty($errors)) {
        if ($newProfileImage !== null) {
            $updateSql = "
                UPDATE users
                SET full_name = ?, phone = ?, profile_image = ?
                WHERE id = ? AND role = 'instructor'
            ";

            $stmt = $conn->prepare($updateSql);

            if ($stmt) {
                $stmt->bind_param("sssi", $fullName, $phone, $newProfileImage, $instructorId);

                if ($stmt->execute()) {
                    $_SESSION['auth_user']['full_name'] = $fullName;

                    if ($existingProfileImage !== '' && $existingProfileImage !== $newProfileImage) {
                        $oldPath = __DIR__ . '/../../../public/assets/uploads/profile_photos/' . basename($existingProfileImage);
                        if (is_file($oldPath)) {
                            unlink($oldPath);
                        }
                    }

                    $message = 'Profile updated successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update profile.';
                    $messageType = 'error';
                }

                $stmt->close();
            } else {
                $message = 'Failed to prepare update query.';
                $messageType = 'error';
            }
        } else {
            $updateSql = "
                UPDATE users
                SET full_name = ?, phone = ?
                WHERE id = ? AND role = 'instructor'
            ";

            $stmt = $conn->prepare($updateSql);

            if ($stmt) {
                $stmt->bind_param("ssi", $fullName, $phone, $instructorId);

                if ($stmt->execute()) {
                    $_SESSION['auth_user']['full_name'] = $fullName;

                    $message = 'Profile updated successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update profile.';
                    $messageType = 'error';
                }

                $stmt->close();
            } else {
                $message = 'Failed to prepare update query.';
                $messageType = 'error';
            }
        }
    } else {
        $message = implode(' ', $errors);
        $messageType = 'error';
    }
}

$profile = null;

$sql = "
    SELECT 
        id,
        full_name,
        email,
        phone,
        profile_image,
        identity_document,
        role,
        status,
        created_at
    FROM users
    WHERE id = ? AND role = 'instructor'
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $instructorId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $profile = $result->fetch_assoc();
    }

    $stmt->close();
}

if (!$profile) {
    Auth::redirect('login.php');
}

if (isset($_GET['photo_deleted'])) {
    $message = 'Profile photo deleted.';
    $messageType = 'success';
}

$profileImage = $profile['profile_image'] ?? '';
$profileImagePath = '';

if ($profileImage !== '') {
    $publicImagePath = __DIR__ . '/../../../public/assets/uploads/profile_photos/' . $profileImage;

    if (file_exists($publicImagePath)) {
        $profileImagePath = 'assets/uploads/profile_photos/' . $profileImage;
    }
}

$initials = strtoupper(substr($profile['full_name'], 0, 1));

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/instructor_navbar.php';
?>


<main class="instructor-profile-page">
    <section class="instructor-profile-wrapper">

        <div class="profile-header">
            <div>
                <p class="page-label">Account</p>
                <h1>Instructor profile</h1>
                <p>Manage your instructor account details and contact information.</p>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="profile-alert <?php echo htmlspecialchars($messageType); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="profile-layout">

            <div class="profile-card">

                <div class="profile-avatar-box">
                    <?php if ($profileImagePath !== ''): ?>
                        <img 
                            src="<?php echo htmlspecialchars($profileImagePath); ?>" 
                            alt="<?php echo htmlspecialchars($profile['full_name']); ?>"
                        >
                    <?php else: ?>
                        <div class="profile-initial">
                            <?php echo htmlspecialchars($initials); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($profileImagePath !== ''): ?>
                    <form method="post" style="margin:12px 0 0">
                        <?php echo csrf_field(); ?>
                        <button type="submit" name="delete_profile_photo" class="action-btn danger"
                                data-confirm="Delete your profile photo?">Delete photo</button>
                    </form>
                <?php endif; ?>

                <h2><?php echo htmlspecialchars($profile['full_name']); ?></h2>

                <p><?php echo htmlspecialchars($profile['email']); ?></p>

                <div class="status-badge <?php echo htmlspecialchars($profile['status']); ?>">
                    <?php echo ucfirst(htmlspecialchars($profile['status'])); ?> Instructor
                </div>

                <div class="profile-info-list">
                    <div>
                        <span>Role</span>
                        <strong><?php echo ucfirst(htmlspecialchars($profile['role'])); ?></strong>
                    </div>

                    <div>
                        <span>Phone</span>
                        <strong>
                            <?php echo htmlspecialchars($profile['phone'] ?: 'Not provided'); ?>
                        </strong>
                    </div>

                    <div>
                        <span>Joined</span>
                        <strong>
                            <?php echo date('M d, Y', strtotime($profile['created_at'])); ?>
                        </strong>
                    </div>

                    <div>
                        <span>Identity Document</span>
                        <strong>
                            <?php echo $profile['identity_document'] ? 'Submitted' : 'Not submitted'; ?>
                        </strong>
                    </div>
                </div>

            </div>

            <div class="profile-form-card">

                <div class="form-card-header">
                    <h2>Edit Profile</h2>
                    <p>Update your public instructor information.</p>
                </div>

                <form method="POST" enctype="multipart/form-data">
  <?php echo csrf_field(); ?>
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            value="<?php echo htmlspecialchars($profile['full_name']); ?>"
                            maxlength="100"
                            required
                        >
                        <small>Only letters, spaces, apostrophe, dot, and hyphen allowed.</small>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input
                            type="email"
                            id="email"
                            value="<?php echo htmlspecialchars($profile['email']); ?>"
                            disabled
                        >
                        <small>Email cannot be changed from this page.</small>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            value="<?php echo htmlspecialchars($profile['phone']); ?>"
                            maxlength="15"
                            required
                        >
                        <small>Enter 10 to 15 digits only.</small>
                    </div>

                    <div class="form-group">
                        <label for="profile_image">Profile Photo</label>
                        <input
                            type="file"
                            id="profile_image"
                            name="profile_image"
                            accept="image/png, image/jpeg"
                        >
                        <small>JPG or PNG only. Max 2MB.</small>
                    </div>

                    <button type="submit" name="update_profile" class="profile-save-btn">
                        Save Changes
                    </button>

                </form>

            </div>

        </div>

    </section>
</main>

</body>
</html>
