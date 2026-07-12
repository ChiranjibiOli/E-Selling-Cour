<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/InstructorMiddleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/profile_helper.php';

InstructorMiddleware::handle();

$currentUser = Auth::user();
$instructorId = (int) ($currentUser['id'] ?? 0);
$message = '';
$messageType = '';

function instructor_profile_has_bio_column(mysqli $conn): bool
{
    try {
        $result = $conn->query("SHOW COLUMNS FROM users LIKE 'bio'");
        return $result instanceof mysqli_result && $result->num_rows > 0;
    } catch (Throwable $exception) {
        error_log('Instructor profile bio-column check failed: ' . $exception->getMessage());
        return false;
    }
}

function instructor_profile_fetch(mysqli $conn, int $instructorId, bool $hasBioColumn): ?array
{
    $bioSelect = $hasBioColumn ? 'bio' : "'' AS bio";

    try {
        $stmt = $conn->prepare("
            SELECT id, full_name, email, phone, {$bioSelect}, profile_image, identity_document,
                   role, status, created_at
            FROM users
            WHERE id = ? AND role = 'instructor'
            LIMIT 1
        ");

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $instructorId);
        $stmt->execute();
        $profile = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $profile;
    } catch (Throwable $exception) {
        error_log('Instructor profile load failed: ' . $exception->getMessage());
        return null;
    }
}

$hasBioColumn = instructor_profile_has_bio_column($conn);
$profile = instructor_profile_fetch($conn, $instructorId, $hasBioColumn);

if (!$profile) {
    Auth::redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_profile_photo'])) {
    $currentPhoto = (string) ($profile['profile_image'] ?? '');

    try {
        $delete = $conn->prepare("UPDATE users SET profile_image = NULL WHERE id = ? AND role = 'instructor'");

        if (!$delete) {
            throw new RuntimeException('The profile update could not be prepared.');
        }

        $delete->bind_param('i', $instructorId);
        $delete->execute();
        $changed = $delete->affected_rows >= 0;
        $delete->close();

        if (!$changed) {
            throw new RuntimeException('The profile photo could not be removed.');
        }

        profile_delete_photo_file($currentPhoto);
        Auth::redirect('instructor-profile.php?photo_deleted=1');
    } catch (Throwable $exception) {
        error_log('Instructor profile photo deletion failed: ' . $exception->getMessage());
        $message = 'The profile photo could not be deleted right now.';
        $messageType = 'error';
    }
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
        $errors[] = 'Phone number must contain 10 to 15 digits when provided.';
    }

    if ($hasBioColumn && profile_length($bio) > 1000) {
        $errors[] = 'Bio must be 1000 characters or fewer.';
    }

    $newImage = profile_upload_photo($_FILES['profile_image'] ?? [], $errors);

    if (!$errors) {
        $oldImage = (string) ($profile['profile_image'] ?? '');
        $profileImage = $newImage ?: ($oldImage !== '' ? $oldImage : null);

        try {
            if ($hasBioColumn) {
                $update = $conn->prepare("
                    UPDATE users
                    SET full_name = ?, phone = ?, bio = ?, profile_image = ?
                    WHERE id = ? AND role = 'instructor'
                ");

                if (!$update) {
                    throw new RuntimeException('The profile update could not be prepared.');
                }

                $update->bind_param('ssssi', $fullName, $phone, $bio, $profileImage, $instructorId);
            } else {
                $update = $conn->prepare("
                    UPDATE users
                    SET full_name = ?, phone = ?, profile_image = ?
                    WHERE id = ? AND role = 'instructor'
                ");

                if (!$update) {
                    throw new RuntimeException('The profile update could not be prepared.');
                }

                $update->bind_param('sssi', $fullName, $phone, $profileImage, $instructorId);
            }

            $update->execute();
            $update->close();

            if ($newImage && $oldImage !== '' && $oldImage !== $newImage) {
                profile_delete_photo_file($oldImage);
            }

            $_SESSION['auth_user']['full_name'] = $fullName;
            Auth::redirect('instructor-profile.php?updated=1');
        } catch (Throwable $exception) {
            error_log('Instructor profile update failed: ' . $exception->getMessage());

            if ($newImage) {
                profile_delete_photo_file($newImage);
            }

            $errors[] = 'The profile could not be updated right now.';
        }
    } elseif ($newImage) {
        profile_delete_photo_file($newImage);
    }

    if ($errors) {
        $message = implode(' ', array_unique($errors));
        $messageType = 'error';
        $profile['full_name'] = $fullName;
        $profile['phone'] = $phone;
        $profile['bio'] = $bio;
    }
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
$displayImage = $image !== '' ? $image : 'assets/images/profile-placeholder.svg';
$pageTitle = 'Instructor profile';

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/instructor_navbar.php';
?>

<main class="instructor-profile-page profile-page-shell">
    <section class="instructor-profile-wrapper">
        <header class="profile-header">
            <div>
                <p class="page-label">Instructor account</p>
                <h1>Your teaching profile</h1>
                <p>Manage the identity, contact information, and profile photo used throughout your instructor workspace.</p>
            </div>
            <span class="profile-status-chip <?php echo profile_h((string) $profile['status']); ?>">
                <?php echo profile_h(ucfirst((string) $profile['status'])); ?> account
            </span>
        </header>

        <?php if ($message !== ''): ?>
            <div class="profile-alert <?php echo profile_h($messageType); ?>" role="status" aria-live="polite">
                <?php echo profile_h($message); ?>
            </div>
        <?php endif; ?>

        <div class="profile-layout instructor-profile-layout">
            <aside class="profile-card instructor-profile-summary">
                <div class="profile-avatar-box">
                    <img src="<?php echo profile_h($displayImage); ?>"
                         alt="<?php echo profile_h($profile['full_name']); ?> profile photo"
                         data-profile-photo>
                </div>

                <div class="profile-photo-actions">
                    <button class="profile-photo-action" type="button" data-photo-view>View photo</button>
                    <button class="profile-photo-action" type="button" data-photo-change>
                        <?php echo $image !== '' ? 'Change photo' : 'Add photo'; ?>
                    </button>

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
                    <div><span>Account</span><strong>Instructor</strong></div>
                    <div><span>Status</span><strong><?php echo profile_h(ucfirst((string) $profile['status'])); ?></strong></div>
                    <div><span>Phone</span><strong><?php echo profile_h($profile['phone'] ?: 'Not provided'); ?></strong></div>
                    <div><span>Identity file</span><strong><?php echo !empty($profile['identity_document']) ? 'Submitted' : 'Not submitted'; ?></strong></div>
                    <div><span>Joined</span><strong><?php echo profile_h(date('M j, Y', strtotime((string) $profile['created_at']))); ?></strong></div>
                </div>
            </aside>

            <section class="profile-form-card instructor-profile-form-card">
                <div class="form-card-header">
                    <h2>Edit instructor details</h2>
                    <p>Changes are validated by the server and do not alter your approval or course-review status.</p>
                </div>

                <form method="post" enctype="multipart/form-data" id="instructorProfileForm">
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
                            <small>Email is tied to the authenticated account and cannot be changed here.</small>
                        </div>

                        <div class="form-group field-span-2">
                            <label for="phone">Phone</label>
                            <input id="phone" name="phone" inputmode="numeric" maxlength="15" autocomplete="tel"
                                   placeholder="10 to 15 digits"
                                   value="<?php echo profile_h($profile['phone'] ?? ''); ?>">
                            <small>Phone is optional here, but any entered value must contain 10 to 15 digits.</small>
                        </div>

                        <?php if ($hasBioColumn): ?>
                            <div class="form-group field-span-2">
                                <label for="bio">Instructor bio</label>
                                <textarea id="bio" name="bio" rows="6" maxlength="1000"
                                          placeholder="Describe your teaching background and subject expertise."><?php echo profile_h($profile['bio'] ?? ''); ?></textarea>
                            </div>
                        <?php endif; ?>

                        <div class="form-group field-span-2">
                            <label for="profile_image">Profile photo</label>
                            <div class="profile-upload-box">
                                <div class="profile-upload-preview">
                                    <img src="<?php echo profile_h($displayImage); ?>"
                                         alt="Selected profile photo preview"
                                         data-profile-photo-preview>
                                </div>
                                <div class="profile-upload-copy">
                                    <input id="profile_image" name="profile_image" type="file"
                                           accept="image/jpeg,image/png,image/webp"
                                           data-profile-photo-input>
                                    <small>JPG, PNG, or WebP. Maximum 2 MB, at least 160 × 160 pixels.</small>
                                    <p>Selecting an image updates the preview immediately. Press Save profile to store it.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="profile-form-actions">
                        <button type="submit" name="update_profile" class="profile-save-btn">Save profile</button>
                        <a href="instructor-dashboard.php" class="profile-cancel-link">Back to dashboard</a>
                    </div>
                </form>
            </section>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../layouts/panel_end.php'; ?>
