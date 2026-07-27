<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class AdminProfilePage
{
    public static function render(array $user): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $name = trim((string) ($user['name'] ?? 'CourseHub Administrator'));
        $email = trim((string) ($user['email'] ?? ''));
        $role = strtolower(trim((string) ($user['role'] ?? 'admin')));
        $id = (int) ($user['id'] ?? 0);
        $parts = preg_split('/\s+/', $name) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }
        if ($initials === '') {
            $initials = 'AD';
        }

        $content = '<section class="admin-profile-panel">'
            . '<div class="admin-profile-identity"><div class="admin-profile-avatar">' . $e($initials) . '</div><div><h2>' . $e($name) . '</h2><p>' . $e($email !== '' ? $email : 'Administrator email unavailable') . '</p><span class="admin-profile-badge">Active administrator</span></div></div>'
            . '<div class="admin-profile-details">'
            . '<div class="admin-profile-field"><small>Account ID</small><strong>#' . $id . '</strong></div>'
            . '<div class="admin-profile-field"><small>Portal role</small><strong>' . $e(ucfirst($role)) . '</strong></div>'
            . '<div class="admin-profile-field"><small>Display name</small><strong>' . $e($name) . '</strong></div>'
            . '<div class="admin-profile-field"><small>Sign-in email</small><strong>' . $e($email !== '' ? $email : 'Unavailable') . '</strong></div>'
            . '</div>'
            . '<div class="admin-profile-actions"><p>This page identifies the administrator currently signed in. Platform configuration belongs in Settings, while active devices and session revocation belong in Security.</p><div class="admin-profile-action-links"><a class="portal-button secondary" href="/admin/security">Open security</a><a class="portal-button" href="/admin/settings">Open settings</a></div></div>'
            . '</section>';

        return PortalPage::render('admin', 'Profile', $content);
    }
}
