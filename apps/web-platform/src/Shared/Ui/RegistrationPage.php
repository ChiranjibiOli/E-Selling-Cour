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
        $title = $instructor ? 'Apply to teach' : 'Create your student account';
        $intro = $instructor
            ? 'Tell us about your teaching background. Your studio opens after an administrator reviews the application.'
            : 'Create one account to purchase courses, keep lifetime access and track every completed lesson.';
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $bio = $instructor
            ? '<label>Teaching background and expertise<textarea name="bio" rows="6" minlength="40" maxlength="3000" required>' . $e($values['bio'] ?? '') . '</textarea><small>At least 40 characters. Include what you teach and your experience.</small></label>'
            : '';

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $e($title) . ' | CourseHub</title><link rel="stylesheet" href="/assets/css/app.css"></head><body>'
            . '<header class="house-header"><a href="/">CourseHub</a><nav><a href="/courses">Courses</a><a href="/login">Sign in</a></nav></header>'
            . '<main class="form-shell"><section class="form-intro"><span>' . ($instructor ? 'INSTRUCTOR APPLICATION' : 'STUDENT ACCOUNT') . '</span><h1>' . $e($title) . '</h1><p>' . $e($intro) . '</p></section>'
            . '<section class="form-card">' . $alert . '<form method="post" action="/register/' . $role . '">' . Csrf::field()
            . '<label>Full name<input name="full_name" value="' . $e($values['full_name'] ?? '') . '" maxlength="100" autocomplete="name" required></label>'
            . '<label>Email address<input type="email" name="email" value="' . $e($values['email'] ?? '') . '" maxlength="150" autocomplete="email" required></label>'
            . '<label>Phone number<input name="phone" value="' . $e($values['phone'] ?? '') . '" maxlength="20" autocomplete="tel"></label>'
            . $bio
            . '<div class="form-columns"><label>Password<input type="password" name="password" minlength="8" maxlength="200" autocomplete="new-password" required></label>'
            . '<label>Confirm password<input type="password" name="password_confirmation" minlength="8" maxlength="200" autocomplete="new-password" required></label></div>'
            . '<button type="submit">' . ($instructor ? 'Submit application' : 'Create student account') . '</button></form>'
            . '<p class="form-foot">Already registered? <a href="' . ($instructor ? '/teach/studio-access' : '/learn/sign-in') . '">Open your portal</a></p></section></main></body></html>';

        return Response::html($html, $status);
    }
}
