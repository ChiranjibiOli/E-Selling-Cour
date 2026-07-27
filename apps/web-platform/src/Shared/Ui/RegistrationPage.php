<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Ui;

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;

final class RegistrationPage
{
    public static function render(string $role, array $values = [], string $message = '', bool $success = false, int $status = 200): Response
    {
        $instructor = $role === 'instructor';
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $title = $instructor ? 'Create an Instructor account' : 'Create your Student account';
        $intro = $instructor
            ? 'Submit your teaching details, passport-size profile photo and identity document. A rejected applicant can correct the application and submit again using the same email. Access opens only after administrator approval.'
            : 'Use a Gmail address. CourseHub sends a six-digit code, and the Student account activates only after that code is verified.';
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';

        $instructorFields = '';
        if ($instructor) {
            $checked = isset($values['agree_instructor_rules']) ? ' checked' : '';
            $instructorFields = '<div class="form-columns"><label>Professional headline<input type="text" inputmode="text" name="professional_headline" value="' . $e($values['professional_headline'] ?? '') . '" minlength="5" maxlength="160" placeholder="Cybersecurity instructor and web application tester" autocomplete="organization-title" required></label>'
                . '<label>Social or professional profile<input type="url" inputmode="url" name="social_profile_url" value="' . $e($values['social_profile_url'] ?? '') . '" maxlength="500" placeholder="https://linkedin.com/in/..."></label></div>'
                . '<label>Areas of expertise<textarea name="expertise" rows="4" minlength="10" maxlength="1000" placeholder="Web security, networking, PHP, UI design..." required>' . $e($values['expertise'] ?? '') . '</textarea></label>'
                . '<label>Teaching experience<textarea name="teaching_experience" rows="5" minlength="20" maxlength="2000" placeholder="Explain where, what, and how long you have taught or mentored." required>' . $e($values['teaching_experience'] ?? '') . '</textarea></label>'
                . '<label>Professional biography<textarea name="bio" rows="6" minlength="40" maxlength="3000" required>' . $e($values['bio'] ?? '') . '</textarea><small>At least 40 characters. This appears beside approved published courses.</small></label>'
                . '<label>Course subjects<textarea name="course_subjects" rows="3" minlength="3" maxlength="1000" placeholder="Ethical hacking, frontend development, business, design..." required>' . $e($values['course_subjects'] ?? '') . '</textarea></label>'
                . '<div class="form-columns"><label>Passport-size profile photo<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" required><small>Portrait JPG, PNG or WebP, at least 300 × 400 pixels, maximum 3 MB. After approval, this becomes your Instructor profile photo.</small></label>'
                . '<label>Government identity document<input type="file" name="identity_document" accept="image/jpeg,image/png,image/webp,application/pdf" required><small>Citizenship, passport, licence or other valid ID. Maximum 6 MB and stored privately.</small></label></div>'
                . '<div class="form-alert"><strong>Reapplication rule:</strong> if Admin rejected your earlier application, submit corrected details here using the same email. CourseHub replaces the rejected application instead of creating another account.</div>'
                . '<div class="form-alert"><strong>Profile-photo rule:</strong> after your account is approved, the accepted photo can be changed only once every 25 days.</div>'
                . '<label class="check-line"><input type="checkbox" name="agree_instructor_rules" value="1"' . $checked . ' required> I confirm that the information and documents are accurate and agree to the instructor, content-quality, copyright, payment and platform rules.</label>';
        }

        $enctype = $instructor ? ' enctype="multipart/form-data"' : '';
        $signInPath = $instructor ? '/teach/studio-access' : '/learn/sign-in';
        $emailLabel = $instructor ? 'Email address' : 'Gmail address';
        $emailPlaceholder = $instructor ? 'name@example.com' : 'yourname@gmail.com';
        $emailHelp = $instructor
            ? '<small>Use the same email when correcting a rejected application. Pending and approved accounts cannot be registered again.</small>'
            : '<small>Student accounts require an address ending in @gmail.com. A verification code will be sent before activation.</small>';
        $emailPattern = $instructor ? '' : ' pattern="[A-Za-z0-9._%+\-]+@[Gg][Mm][Aa][Ii][Ll]\.[Cc][Oo][Mm]"';
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $e($title) . ' | CourseHub</title><link rel="stylesheet" href="/assets/css/app.css"></head><body>'
            . '<header class="house-header"><a href="/">CourseHub</a><nav><a href="' . $signInPath . '">' . ($instructor ? 'Instructor sign in' : 'Student sign in') . '</a></nav></header>'
            . '<main class="form-shell"><section class="form-intro"><span>' . ($instructor ? 'SEPARATE INSTRUCTOR APPLICATION' : 'STUDENT ACCOUNT') . '</span><h1>' . $e($title) . '</h1><p>' . $e($intro) . '</p></section>'
            . '<section class="form-card">' . $alert . '<form method="post" action="/register/' . $role . '"' . $enctype . '>' . Csrf::field()
            . '<label>Full name<input type="text" inputmode="text" name="full_name" value="' . $e($values['full_name'] ?? '') . '" minlength="2" maxlength="100" autocomplete="name" required></label>'
            . '<label>' . $emailLabel . '<input type="email" inputmode="email" name="email" value="' . $e($values['email'] ?? '') . '" maxlength="150" autocomplete="email" placeholder="' . $emailPlaceholder . '"' . $emailPattern . ' required>' . $emailHelp . '</label>'
            . '<label>Phone number<input type="tel" inputmode="tel" name="phone" value="' . $e($values['phone'] ?? '') . '" minlength="7" maxlength="20" pattern="[0-9+() -]{7,20}" autocomplete="tel" required></label>'
            . $instructorFields
            . '<div class="form-columns"><label>Password<input type="password" name="password" minlength="8" maxlength="200" autocomplete="new-password" required></label>'
            . '<label>Confirm password<input type="password" name="password_confirmation" minlength="8" maxlength="200" autocomplete="new-password" required></label></div>'
            . '<button type="submit">' . ($instructor ? 'Submit Instructor application' : 'Send Gmail verification code') . '</button></form>'
            . '<p class="form-foot">Already registered? <a href="' . $signInPath . '">' . ($instructor ? 'Open Instructor sign in' : 'Open Student sign in') . '</a></p></section></main></body></html>';

        return Response::html($html, $status);
    }
}
