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
        $photo = $hasPhoto
            ? '<img src="/instructor/profile?photo=1&amp;v=' . rawurlencode((string) ($profile['profile_image_changed_at'] ?? 'profile')) . '" alt="Instructor profile photo">'
            : '<span>IN</span>';
        $canChangePhoto = (bool) ($profile['photo_change_allowed'] ?? false);
        $availableAt = trim((string) ($profile['profile_image_change_available_at'] ?? ''));
        $photoRule = $canChangePhoto
            ? '<div class="form-alert success"><strong>Photo change available.</strong> A new passport photo starts another 25-day lock.</div>'
            : '<div class="form-alert"><strong>Photo locked.</strong> You can change it again after ' . $e($availableAt) . '.</div>';
        $disabled = $canChangePhoto ? '' : ' disabled';

        $content = $alert
            . '<section class="portal-grid"><article class="portal-card"><div class="instructor-profile-photo">' . $photo . '</div><h2>' . $e($profile['full_name'] ?? 'Instructor') . '</h2><p>' . $e($profile['professional_headline'] ?? '') . '</p><span class="secure-pill">Approved Instructor</span></article>'
            . '<article class="portal-card"><h2>Profile-photo policy</h2><p>The passport-size photo accepted with your Instructor application is your official profile photo. It can be replaced only once every 25 days.</p>' . $photoRule . '</article></section>'
            . '<section class="data-card"><div class="data-card-head"><div><span>INSTRUCTOR PROFILE</span><h3>Public teaching identity</h3></div><span class="secure-pill">Identity document remains private</span></div>'
            . '<form class="portal-form" method="post" action="/instructor/profile" enctype="multipart/form-data">' . Csrf::field()
            . '<div class="form-columns"><label>Full name<input name="full_name" value="' . $e($profile['full_name'] ?? '') . '" maxlength="100" required></label>'
            . '<label>Email address<input type="email" value="' . $e($profile['email'] ?? '') . '" readonly></label></div>'
            . '<div class="form-columns"><label>Phone number<input name="phone" value="' . $e($profile['phone'] ?? '') . '" maxlength="20"></label>'
            . '<label>Professional headline<input name="professional_headline" value="' . $e($profile['professional_headline'] ?? '') . '" minlength="5" maxlength="160" required></label></div>'
            . '<label>Professional biography<textarea name="bio" rows="6" minlength="40" maxlength="3000" required>' . $e($profile['bio'] ?? '') . '</textarea></label>'
            . '<label>Areas of expertise<textarea name="expertise" rows="4" minlength="10" maxlength="1000" required>' . $e($profile['expertise'] ?? '') . '</textarea></label>'
            . '<label>Teaching experience<textarea name="teaching_experience" rows="5" minlength="20" maxlength="2000" required>' . $e($profile['teaching_experience'] ?? '') . '</textarea></label>'
            . '<label>Course subjects<textarea name="course_subjects" rows="3" minlength="3" maxlength="1000" required>' . $e($profile['course_subjects'] ?? '') . '</textarea></label>'
            . '<label>Social or professional profile<input type="url" name="social_profile_url" value="' . $e($profile['social_profile_url'] ?? '') . '" maxlength="500"></label>'
            . '<label>Replace passport-size profile photo<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp"' . $disabled . '><small>Portrait JPG, PNG, or WebP, at least 300 × 400 pixels, maximum 3 MB.</small></label>'
            . '<button class="portal-button" type="submit">Save Instructor profile</button></form></section>';

        return PortalPage::render('instructor', 'Profile', $content);
    }
}
