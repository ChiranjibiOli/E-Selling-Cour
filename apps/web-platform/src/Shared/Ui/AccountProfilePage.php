<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Ui;

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;

final class AccountProfilePage
{
    public static function render(string $role, array $profile, string $message = '', bool $success = true): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $name = trim((string) ($profile['full_name'] ?? ucfirst($role) . ' account'));
        $email = trim((string) ($profile['email'] ?? ''));
        $id = (int) ($profile['id'] ?? 0);
        $profileImage = trim((string) ($profile['profile_image'] ?? ''));
        $profilePath = $role === 'admin' ? '/admin/profile' : '/student/profile';
        $parts = preg_split('/\s+/', $name) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }
        if ($initials === '') {
            $initials = $role === 'admin' ? 'AD' : 'ST';
        }

        $alert = $message !== ''
            ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>'
            : '';
        $photo = $profileImage !== ''
            ? '<a class="account-profile-photo" href="' . $e($profilePath) . '?photo=1" target="_blank" rel="noopener"><img src="' . $e($profilePath) . '?photo=1&amp;v=' . $e(hash('sha256', $profileImage)) . '" alt="' . $e($name) . ' profile photo"></a>'
            : '<div class="account-profile-photo account-profile-photo-empty" aria-label="No profile photo"><span>' . $e($initials) . '</span></div>';
        $view = $profileImage !== ''
            ? '<a class="portal-button secondary" href="' . $e($profilePath) . '?photo=1" target="_blank" rel="noopener">View photo</a>'
            : '';
        $remove = $profileImage !== ''
            ? '<form method="post" data-profile-photo-remove>' . Csrf::field() . '<input type="hidden" name="action" value="remove_photo"><button class="portal-button danger" type="submit">Remove photo</button></form>'
            : '';

        $roleLinks = $role === 'admin'
            ? '<a class="portal-button secondary" href="/admin/security">Open security</a><a class="portal-button" href="/admin/settings">Open settings</a>'
            : '<a class="portal-button secondary" href="/student/payment-history">Payment history</a><a class="portal-button" href="/student/my-courses">My courses</a>';

        $content = $alert
            . '<section class="account-profile-panel">'
            . '<div class="account-profile-hero">' . $photo . '<div class="account-profile-heading"><span>' . $e(ucfirst($role)) . ' profile</span><h2>' . $e($name) . '</h2><p>' . $e($email !== '' ? $email : 'Email unavailable') . '</p></div></div>'
            . '<div class="account-profile-controls"><div><h3>Profile photo</h3><p>JPG, PNG, or WebP. Use a clear, mostly square image of at least 200 × 200 pixels and no more than 3 MB.</p></div><div class="account-profile-buttons">' . $view . $remove . '</div>'
            . '<form class="account-profile-upload" method="post" enctype="multipart/form-data">' . Csrf::field() . '<input type="hidden" name="action" value="change_photo"><label>Choose new photo<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" required></label><button class="portal-button" type="submit">Change photo</button></form></div>'
            . '<div class="account-profile-details"><div><small>Account ID</small><strong>#' . $id . '</strong></div><div><small>Portal role</small><strong>' . $e(ucfirst($role)) . '</strong></div><div><small>Display name</small><strong>' . $e($name) . '</strong></div><div><small>Sign-in email</small><strong>' . $e($email !== '' ? $email : 'Unavailable') . '</strong></div></div>'
            . '<div class="account-profile-footer"><p>The photo is served through your authenticated profile route. Removing it restores the initials avatar in the sidebar and top bar.</p><div>' . $roleLinks . '</div></div>'
            . '</section>';

        return PortalPage::render($role, 'Profile', $content);
    }
}
