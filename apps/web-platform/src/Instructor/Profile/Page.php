<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class InstructorProfilePage
{
    public static function render(array $profile, string $message = '', bool $success = true): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $hasPhoto = trim((string) ($profile['profile_image'] ?? '')) !== '';
        $canChangePhoto = (bool) ($profile['photo_change_allowed'] ?? false);
        $availableAt = trim((string) ($profile['profile_image_change_available_at'] ?? ''));
        $name = trim((string) ($profile['full_name'] ?? 'Instructor'));
        $parts = preg_split('/\s+/', $name) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }
        $initials = $initials !== '' ? $initials : 'IN';
        $photoUrl = '/instructor/profile?photo=1&amp;v=' . rawurlencode((string) ($profile['profile_image_changed_at'] ?? 'profile'));
        $photo = $hasPhoto
            ? '<button class="instructor-profile-photo profile-photo-trigger" type="button" data-photo-open aria-label="View profile photo"><img src="' . $photoUrl . '" alt="' . $e($name) . ' profile photo"></button>'
            : '<div class="instructor-profile-photo instructor-profile-photo-empty"><span>' . $e($initials) . '</span></div>';
        $viewButton = $hasPhoto
            ? '<button class="portal-button secondary" type="button" data-photo-open>View photo</button>'
            : '';
        $removeButton = $hasPhoto
            ? '<form method="post" data-profile-photo-remove>' . Csrf::field() . '<input type="hidden" name="action" value="remove_photo"><button class="portal-button danger" type="submit"' . ($canChangePhoto ? '' : ' disabled') . '>Remove photo</button></form>'
            : '';
        $photoRule = $canChangePhoto
            ? '<span class="profile-policy available">Photo change and removal are available.</span>'
            : '<span class="profile-policy locked">Photo controls unlock after ' . $e($availableAt !== '' ? $availableAt : 'the cooldown period') . '.</span>';
        $disabled = $canChangePhoto ? '' : ' disabled';

        $photoDialog = $hasPhoto
            ? '<dialog class="profile-photo-dialog" data-photo-dialog aria-labelledby="profile-photo-title"><div class="profile-photo-dialog-shell"><header><div><span>PROFILE PHOTO</span><h2 id="profile-photo-title">' . $e($name) . '</h2></div><button type="button" data-photo-close aria-label="Close photo viewer">×</button></header><div class="profile-photo-stage"><img src="' . $photoUrl . '" alt="' . $e($name) . ' profile photo" data-photo-image></div><footer><button class="portal-button secondary" type="button" data-photo-zoom-out aria-label="Zoom out">−</button><button class="portal-button secondary" type="button" data-photo-reset>Reset</button><button class="portal-button secondary" type="button" data-photo-zoom-in aria-label="Zoom in">+</button><button class="portal-button" type="button" data-photo-close>Close</button></footer></div></dialog>'
            : '';
        $removeDialog = $hasPhoto
            ? '<dialog class="portal-confirm-dialog" data-photo-remove-dialog aria-labelledby="remove-photo-title"><div class="portal-confirm-content"><span class="portal-confirm-icon">×</span><div><h2 id="remove-photo-title">Remove Instructor photo?</h2><p>The initials avatar will replace the photo. You can upload a new verified portrait afterward.</p></div><div class="portal-confirm-actions"><button class="portal-button secondary" type="button" data-photo-remove-cancel>No, keep photo</button><button class="portal-button danger" type="button" data-photo-remove-confirm>Yes, remove photo</button></div></div></dialog>'
            : '';

        $content = $alert
            . '<section class="instructor-profile-surface">'
            . '<div class="instructor-profile-hero">' . $photo . '<div class="instructor-profile-heading"><span>APPROVED INSTRUCTOR</span><h2>' . $e($name) . '</h2><p>' . $e($profile['professional_headline'] ?? '') . '</p><small>' . $e($profile['email'] ?? '') . '</small><div class="instructor-profile-photo-actions">' . $viewButton . $removeButton . '</div>' . $photoRule . '</div></div>'
            . '<div class="instructor-profile-note"><strong>Verified teaching identity</strong><p>Your identity document remains private. The public profile photo can be viewed here, replaced or removed only when the 25-day photo cooldown permits it.</p></div>'
            . '<form class="portal-form instructor-profile-form" method="post" action="/instructor/profile" enctype="multipart/form-data">' . Csrf::field() . '<input type="hidden" name="action" value="save_profile">'
            . '<div class="instructor-profile-section"><div><span>ACCOUNT</span><h3>Personal information</h3></div><div class="form-columns"><label>Full name<input type="text" inputmode="text" name="full_name" value="' . $e($profile['full_name'] ?? '') . '" minlength="2" maxlength="100" autocomplete="name" required></label>'
            . '<label>Email address<input type="email" value="' . $e($profile['email'] ?? '') . '" autocomplete="email" readonly></label></div>'
            . '<div class="form-columns"><label>Phone number<input type="tel" inputmode="tel" name="phone" value="' . $e($profile['phone'] ?? '') . '" minlength="7" maxlength="20" pattern="[0-9+() -]{7,20}" autocomplete="tel"></label>'
            . '<label>Professional headline<input type="text" inputmode="text" name="professional_headline" value="' . $e($profile['professional_headline'] ?? '') . '" minlength="5" maxlength="160" autocomplete="organization-title" required></label></div></div>'
            . '<div class="instructor-profile-section"><div><span>TEACHING PROFILE</span><h3>Public expertise</h3></div><label>Professional biography<textarea name="bio" rows="6" minlength="40" maxlength="3000" required>' . $e($profile['bio'] ?? '') . '</textarea></label>'
            . '<label>Areas of expertise<textarea name="expertise" rows="4" minlength="10" maxlength="1000" required>' . $e($profile['expertise'] ?? '') . '</textarea></label>'
            . '<label>Teaching experience<textarea name="teaching_experience" rows="5" minlength="20" maxlength="2000" required>' . $e($profile['teaching_experience'] ?? '') . '</textarea></label>'
            . '<label>Course subjects<textarea name="course_subjects" rows="3" minlength="3" maxlength="1000" required>' . $e($profile['course_subjects'] ?? '') . '</textarea></label>'
            . '<label>Social or professional profile<input type="url" inputmode="url" name="social_profile_url" value="' . $e($profile['social_profile_url'] ?? '') . '" maxlength="500" placeholder="https://..."><small>HTTPS only.</small></label></div>'
            . '<div class="instructor-profile-section"><div><span>PROFILE PHOTO</span><h3>Change portrait</h3></div><label>Choose a new passport-size photo<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp"' . $disabled . '><small>Portrait JPG, PNG or WebP, at least 300 × 400 pixels, maximum 3 MB.</small></label></div>'
            . '<div class="instructor-profile-submit"><button class="portal-button" type="submit">Save Instructor profile</button></div></form></section>'
            . $photoDialog . $removeDialog;

        return PortalPage::render('instructor', 'Profile', $content);
    }
}
