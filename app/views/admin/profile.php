<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/AdminMiddleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/profile_helper.php';

AdminMiddleware::handle();

$currentUser = Auth::user();
$adminId = (int) ($currentUser['id'] ?? 0);
$message = '';
$messageType = '';

$profileStmt = $conn->prepare("
    SELECT id, full_name, email, phone, bio, profile_image, role, status, created_at
    FROM users
    WHERE id = ? AND role = 'admin'
    LIMIT 1
");
$profileStmt->bind_param('i', $adminId);
$profileStmt->execute();
$profile = $profileStmt->get_result()->fetch_assoc() ?: null;
$profileStmt->close();

if (!$profile) {
    Auth::redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_profile_photo'])) {
    $currentPhoto = (string) ($profile['profile_image'] ?? '');
    $delete = $conn->prepare("UPDATE users SET profile_image = NULL WHERE id = ? AND role = 'admin'");
    $delete->bind_param('i', $adminId);
    $delete->execute();
    $delete->close();

    profile_delete_photo_file($currentPhoto);
    header('Location: admin-profile.php?photo_deleted=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullName = profile_clean_text($_POST['full_name'] ?? '');
    $phone = profile_normalize_phone($_POST['phone'] ?? '');
    $bio = profile_clean_text($_POST['bio'] ?? '', true);
    $errors = [];

    if (!profile_valid_name($fullName)) {
        $errors[] = 'Enter a valid full name between 2 and 100 characters.';
    }

    if (!profile_valid_phone($phone, false)) {
        $errors[] = 'Phone number must contain 10 to 15 digits.';
    }

    if (profile_length($bio) > 1000) {
        $errors[] = 'Bio must be 1000 characters or fewer.';
    }

    $newImage = profile_upload_photo($_FILES['profile_image'] ?? [], $errors);

    if (!$errors) {
        $oldImage = (string) ($profile['profile_image'] ?? '');
        $profileImage = $newImage ?: ($oldImage !== '' ? $oldImage : null);
        $update = $conn->prepare("
            UPDATE users
            SET full_name = ?, phone = ?, bio = ?, profile_image = ?
            WHERE id = ? AND role = 'admin'
        ");
        $update->bind_param('ssssi', $fullName, $phone, $bio, $profileImage, $adminId);

        if ($update->execute()) {
            $update->close();

            if ($newImage && $oldImage !== '' && $oldImage !== $newImage) {
                profile_delete_photo_file($oldImage);
            }

            $_SESSION['auth_user']['full_name'] = $fullName;
            header('Location: admin-profile.php?updated=1');
            exit;
        }

        $update->close();
        if ($newImage) {
            profile_delete_photo_file($newImage);
        }
        $errors[] = 'The profile could not be updated right now.';
    } elseif ($newImage) {
        profile_delete_photo_file($newImage);
    }

    $message = implode(' ', array_unique($errors));
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

$image = profile_photo_public_path((string) ($profile['profile_image'] ?? ''));
$initial = profile_initial((string) $profile['full_name']);
$pageTitle = 'Admin profile';

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/admin_navbar.php';
?>

<main class="admin-profile-page">
    <section class="admin-profile-wrapper">
        <header class="profile-header">
            <div>
                <p class="page-label">Administrator account</p>
                <h1>Your admin profile</h1>
                <p>Maintain the account identity used for platform review, moderation, payment verification, and reporting.</p>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="profile-alert <?php echo profile_h($messageType); ?>"><?php echo profile_h($message); ?></div>
        <?php endif; ?>

        <div class="profile-layout">
            <aside class="profile-card">
                <div class="profile-avatar-box">
                    <?php if ($image !== ''): ?>
                        <img src="<?php echo profile_h($image); ?>"
                             alt="<?php echo profile_h($profile['full_name']); ?>"
                             data-profile-photo>
                    <?php else: ?>
                        <span class="profile-initial"><?php echo profile_h($initial); ?></span>
                    <?php endif; ?>
                </div>

                <div class="profile-photo-actions">
                    <?php if ($image !== ''): ?>
                        <button class="profile-photo-action" type="button" data-photo-view>View photo</button>
                    <?php endif; ?>
                    <button class="profile-photo-action" type="button" data-photo-change><?php echo $image !== '' ? 'Change photo' : 'Add photo'; ?></button>
                    <?php if ($image !== ''): ?>
                        <form method="post">
                            <?php echo csrf_field(); ?>
                            <button class="profile-photo-action danger" type="submit" name="delete_profile_photo"
                                    data-confirm="Delete your profile photo?">Delete photo</button>
                        </form>
                    <?php endif; ?>
                </div>

                <h2><?php echo profile_h($profile['full_name']); ?></h2>
                <p><?php echo profile_h($profile['email']); ?></p>

                <div class="profile-info-list">
                    <div><span>Account</span><strong>Administrator</strong></div>
                    <div><span>Status</span><strong><?php echo profile_h(ucfirst((string) $profile['status'])); ?></strong></div>
                    <div><span>Phone</span><strong><?php echo profile_h($profile['phone'] ?: 'Not provided'); ?></strong></div>
                    <div><span>Joined</span><strong><?php echo profile_h(date('M j, Y', strtotime((string) $profile['created_at']))); ?></strong></div>
                </div>
            </aside>

            <section class="profile-form-card">
                <div class="form-card-header">
                    <h2>Edit administrator details</h2>
                    <p>Email and role remain locked to protect administrative access boundaries.</p>
                </div>

                <form method="post" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>

                    <div class="profile-form-grid">
                        <div class="form-group">
                            <label for="full_name">Full name</label>
                            <input id="full_name" name="full_name" maxlength="100" required autocomplete="name"
                                   value="<?php echo profile_h($profile['full_name']); ?>">
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input id="email" type="email" disabled value="<?php echo profile_h($profile['email']); ?>">
                            <small>Email cannot be changed here.</small>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input id="phone" name="phone" inputmode="numeric" maxlength="15" autocomplete="tel"
                                   value="<?php echo profile_h($profile['phone'] ?? ''); ?>">
                        </div>

                        <div class="form-group field-span-2">
                            <label for="bio">Admin bio</label>
                            <textarea id="bio" name="bio" rows="6" maxlength="1000"
                                      placeholder="Add a short internal administrator note."><?php echo profile_h($profile['bio'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group field-span-2">
                            <label for="profile_image">Profile photo</label>
                            <div class="profile-upload-box">
                                <div class="profile-upload-preview">
                                    <img src="<?php echo profile_h($image !== '' ? $image : 'assets/images/course-placeholder.svg'); ?>"
                                         alt="Selected profile photo preview"
                                         data-profile-photo-preview>
                                </div>
                                <div>
                                    <input id="profile_image" name="profile_image" type="file"
                                           accept="image/jpeg,image/png,image/webp"
                                           data-profile-photo-input>
                                    <small>JPG, PNG, or WebP. Maximum 2 MB and at least 160 × 160 pixels.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="update_profile" class="profile-save-btn">Save profile</button>
                </form>
            </section>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../layouts/panel_end.php'; ?>
