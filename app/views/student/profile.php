<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/StudentMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

StudentMiddleware::handle();

$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);
$message = '';
$messageType = '';

function student_profile_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function student_profile_upload(array $file, array &$errors): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'Profile photo upload failed.';
        return null;
    }

    if ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
        $errors[] = 'Profile photo must be 2 MB or smaller.';
        return null;
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    if (!isset($allowed[$mime])) {
        $errors[] = 'Profile photo must be JPG, PNG, or WebP.';
        return null;
    }

    $directory = __DIR__ . '/../../../public/assets/uploads/profile_photos';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        $errors[] = 'Profile photo directory could not be created.';
        return null;
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file((string) $file['tmp_name'], $directory . '/' . $fileName)) {
        $errors[] = 'Profile photo could not be saved.';
        return null;
    }

    return $fileName;
}

$profileStmt = $conn->prepare("
    SELECT full_name, email, phone, bio, profile_image, status, created_at
    FROM users
    WHERE id = ? AND role = 'student'
    LIMIT 1
");
$profileStmt->bind_param('i', $studentId);
$profileStmt->execute();
$profile = $profileStmt->get_result()->fetch_assoc() ?: null;
$profileStmt->close();

if (!$profile) {
    Auth::logout();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_profile_photo'])) {
    $delete = $conn->prepare("UPDATE users SET profile_image = NULL WHERE id = ? AND role = 'student'");
    $delete->bind_param('i', $studentId);
    $delete->execute();
    $delete->close();

    if (!empty($profile['profile_image'])) {
        $path = __DIR__ . '/../../../public/assets/uploads/profile_photos/' . basename((string) $profile['profile_image']);
        if (is_file($path)) {
            unlink($path);
        }
    }

    header('Location: student-profile.php?photo_deleted=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $phone = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? ''));
    $bio = trim((string) ($_POST['bio'] ?? ''));
    $errors = [];

    if (!preg_match("/^[a-zA-Z\s.'-]{2,100}$/", $fullName)) {
        $errors[] = 'Enter a valid full name using letters, spaces, apostrophe, dot, or hyphen.';
    }

    if ($phone !== '' && !preg_match('/^\d{10,15}$/', $phone)) {
        $errors[] = 'Phone number must contain 10 to 15 digits.';
    }

    if (strlen($bio) > 1000) {
        $errors[] = 'Bio must be 1000 characters or fewer.';
    }

    $newImage = student_profile_upload($_FILES['profile_image'] ?? [], $errors);

    if (!$errors) {
        $profileImage = $newImage ?: ($profile['profile_image'] ?? null);
        $update = $conn->prepare("
            UPDATE users
            SET full_name = ?, phone = ?, bio = ?, profile_image = ?
            WHERE id = ? AND role = 'student'
        ");
        $update->bind_param('ssssi', $fullName, $phone, $bio, $profileImage, $studentId);
        $update->execute();
        $update->close();

        if ($newImage && !empty($profile['profile_image'])) {
            $oldPath = __DIR__ . '/../../../public/assets/uploads/profile_photos/' . basename((string) $profile['profile_image']);
            if (is_file($oldPath)) {
                unlink($oldPath);
            }
        }

        $_SESSION['auth_user']['full_name'] = $fullName;
        header('Location: student-profile.php?updated=1');
        exit;
    }

    if ($newImage) {
        $newPath = __DIR__ . '/../../../public/assets/uploads/profile_photos/' . basename($newImage);
        if (is_file($newPath)) {
            unlink($newPath);
        }
    }

    $message = implode(' ', $errors);
    $messageType = 'error';
    $profile['full_name'] = $fullName;
    $profile['phone'] = $phone;
    $profile['bio'] = $bio;
}

if (isset($_GET['updated'])) {
    $message = 'Profile updated successfully.';
    $messageType = 'success';
}

if (isset($_GET['photo_deleted'])) {
    $message = 'Profile photo deleted.';
    $messageType = 'success';
    $profile['profile_image'] = null;
}

$image = !empty($profile['profile_image'])
    ? 'assets/uploads/profile_photos/' . rawurlencode(basename((string) $profile['profile_image']))
    : '';
$initial = strtoupper(substr((string) $profile['full_name'], 0, 1));
$pageTitle = 'Student profile';

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/student_navbar.php';
?>

<main class="student-page">
    <section class="student-section">
        <div class="profile-header">
            <div>
                <p class="page-label">Account settings</p>
                <h1>Student profile</h1>
                <p>Your profile photo stays inside account pages and is not repeated across every panel.</p>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="profile-alert <?php echo student_profile_h($messageType); ?>"><?php echo student_profile_h($message); ?></div>
        <?php endif; ?>

        <div class="profile-layout">
            <aside class="profile-card">
                <div class="profile-avatar-box">
                    <?php if ($image !== ''): ?>
                        <img src="<?php echo student_profile_h($image); ?>" alt="<?php echo student_profile_h($profile['full_name']); ?>">
                    <?php else: ?>
                        <span class="profile-initial"><?php echo student_profile_h($initial); ?></span>
                    <?php endif; ?>
                </div>
                <h2><?php echo student_profile_h($profile['full_name']); ?></h2>
                <p><?php echo student_profile_h($profile['email']); ?></p>
                <div class="profile-info-list">
                    <div><span>Status</span><strong><?php echo student_profile_h(ucfirst($profile['status'])); ?></strong></div>
                    <div><span>Joined</span><strong><?php echo student_profile_h(date('M j, Y', strtotime($profile['created_at']))); ?></strong></div>
                </div>

                <?php if ($image !== ''): ?>
                    <form method="post">
                        <?php echo csrf_field(); ?>
                        <button class="action-btn danger" type="submit" name="delete_profile_photo"
                                data-confirm="Delete your profile photo?">Delete photo</button>
                    </form>
                <?php endif; ?>
            </aside>

            <section class="profile-form-card">
                <div class="form-card-header">
                    <h2>Edit account</h2>
                    <p>Update your contact information and profile photo.</p>
                </div>

                <form method="post" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>

                    <div class="form-group">
                        <label for="full_name">Full name</label>
                        <input id="full_name" name="full_name" maxlength="100" required
                               value="<?php echo student_profile_h($profile['full_name']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input id="email" type="email" disabled value="<?php echo student_profile_h($profile['email']); ?>">
                        <small>Email cannot be changed here.</small>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input id="phone" name="phone" inputmode="numeric" maxlength="15"
                               value="<?php echo student_profile_h($profile['phone'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="bio">Bio</label>
                        <textarea id="bio" name="bio" rows="5" maxlength="1000"><?php echo student_profile_h($profile['bio'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="profile_image">Replace profile photo</label>
                        <input id="profile_image" name="profile_image" type="file" accept="image/jpeg,image/png,image/webp">
                        <small>JPG, PNG, or WebP. Maximum 2 MB.</small>
                    </div>

                    <button class="profile-save-btn" type="submit" name="update_profile">Save profile</button>
                </form>
            </section>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../layouts/panel_end.php'; ?>
