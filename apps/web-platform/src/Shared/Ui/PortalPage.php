<?php

declare(strict_types=1);
namespace CourseHub\WebPlatform\Shared\Ui;

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Session\AuthSession;

final class PortalPage
{
    public static function render(string $role, string $title, string $content, string $action = ''): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $navigation = self::navigation($role);
        $currentPath = rtrim(parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/', '/') ?: '/';
        $nav = '';

        foreach ($navigation as $group => $links) {
            $nav .= '<div class="portal-nav-group"><span class="portal-nav-label">' . $e($group) . '</span>';
            foreach ($links as $href => [$label, $icon]) {
                $normalHref = rtrim($href, '/') ?: '/';
                $activeMatch = $currentPath === $normalHref;
                if (!$activeMatch && $normalHref !== '/' && str_starts_with($currentPath, $normalHref . '/')) {
                    $activeMatch = !in_array($normalHref, ['/instructor/courses', '/student/payment'], true)
                        || !array_key_exists($currentPath, $links);
                }
                $active = $activeMatch ? ' active' : '';
                $current = $activeMatch ? ' aria-current="page"' : '';
                $nav .= '<a class="portal-nav-link' . $active . '" href="' . $e($href) . '"' . $current . '><span class="portal-nav-icon">' . $e($icon) . '</span><span>' . $e($label) . '</span></a>';
            }
            $nav .= '</div>';
        }

        $user = AuthSession::user();
        $name = (string) ($user['name'] ?? ucfirst($role) . ' account');
        $profileImage = trim((string) ($user['profile_image'] ?? ''));
        $initials = self::initials($name);
        $roleName = match ($role) {
            'admin' => 'Admin',
            'instructor' => 'Teaching workspace',
            default => 'Learning space',
        };
        $dashboard = match ($role) {
            'admin' => '/admin/dashboard',
            'instructor' => '/instructor/dashboard',
            default => '/student/dashboard',
        };
        $profile = match ($role) {
            'admin' => '/admin/profile',
            'instructor' => '/instructor/profile',
            default => '/student/profile',
        };
        $avatarContent = $profileImage !== ''
            ? '<img src="' . $e($profile) . '?photo=1&amp;v=' . $e(hash('sha256', $profileImage)) . '" alt="">'
            : $e($initials);
        $workspace = $role === 'student'
            ? '<div class="portal-workspace"><span>Student</span><strong>' . $e($roleName) . '</strong></div>'
            : '';
        $crumb = match ($role) {
            'admin' => '',
            'instructor' => '<div class="portal-crumb portal-crumb-simple"><strong>' . $e($title) . '</strong></div>',
            default => '<div class="portal-crumb"><span>' . $e(ucfirst($role)) . '</span><i>/</i><strong>' . $e($title) . '</strong></div>',
        };
        $contextActions = trim($action) !== '' ? '<div class="portal-context-actions">' . $action . '</div>' : '';
        $logout = '<form class="portal-logout-form" method="post" action="/logout" data-logout-form>' . Csrf::field()
            . '<button class="portal-logout-button" type="submit"><span class="portal-nav-icon">↪</span><span>Log out</span></button></form>';
        $brand = '<a class="portal-brand" href="' . $e($dashboard) . '"><span class="coursehub-brand-mark"><img src="/assets/images/coursehub-robot.svg" alt=""></span><strong>CourseHub</strong></a>';

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="theme-color" content="#f4ede2"><title>' . $e($title) . ' | CourseHub</title><link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/assets/css/commerce.css"><link rel="stylesheet" href="/assets/css/portal-fixes.css"><link rel="stylesheet" href="/assets/css/admin-console.css"><link rel="stylesheet" href="/assets/css/profile-links.css"><link rel="stylesheet" href="/assets/css/profile-dialog.css"><link rel="stylesheet" href="/assets/css/instructor-console.css"><link rel="stylesheet" href="/assets/css/instructor-identity.css"><link rel="stylesheet" href="/assets/css/workflow-console.css"><link rel="stylesheet" href="/assets/css/learning-commerce.css"><link rel="stylesheet" href="/assets/css/instructor-communication.css"><link rel="stylesheet" href="/assets/css/course-card-theme.css"><link rel="stylesheet" href="/assets/css/portal-headless.css"><link rel="stylesheet" href="/assets/css/coursehub-coral.css"><link rel="stylesheet" href="/assets/css/coursehub-editorial.css"><link rel="stylesheet" href="/assets/css/coursehub-instructor-polish.css"><link rel="stylesheet" href="/assets/css/authoring-height.css?v=20260728-4"><link rel="stylesheet" href="/assets/css/admin-panel-separation.css?v=20260728-1"></head>'
            . '<body class="portal-shell portal-role-' . $e($role) . '" data-portal-role="' . $e($role) . '"><button class="portal-mobile-toggle" type="button" data-portal-toggle aria-label="Open navigation" aria-expanded="false"><span></span><span></span><span></span></button>'
            . '<div class="portal-overlay" data-portal-overlay></div><aside class="portal-sidebar" data-portal-sidebar>' . $brand
            . $workspace . '<nav class="portal-sidebar-nav" data-portal-nav>' . $nav . '</nav>'
            . '<div class="portal-sidebar-foot"><a href="/contact"><span class="portal-nav-icon">?</span><span>Help & support</span></a>' . $logout . '</div></aside>'
            . '<div class="portal-stage"><header class="portal-topbar">' . $crumb . '<div class="portal-top-actions"><label class="portal-search"><span>⌕</span><input type="search" placeholder="Search this page" aria-label="Search this page"></label><a class="portal-icon-button" href="/' . $e($role) . '/notifications" aria-label="Notifications"><span>◔</span><i></i></a><a class="portal-top-avatar portal-top-profile" href="' . $e($profile) . '" aria-label="Open your profile" title="Open profile">' . $avatarContent . '</a></div></header>'
            . '<main class="portal-main">' . $contextActions . $content . '</main><footer class="portal-footer"><span>CourseHub</span><span>Secure role-based access</span></footer></div>'
            . '<dialog class="portal-confirm-dialog" data-logout-dialog aria-labelledby="logout-confirm-title"><div class="portal-confirm-content"><span class="portal-confirm-icon">↪</span><div><h2 id="logout-confirm-title">Log out of CourseHub?</h2><p>Your current session will end on this device.</p></div><div class="portal-confirm-actions"><button class="portal-button secondary" type="button" data-logout-cancel>No, stay signed in</button><button class="portal-button danger" type="button" data-logout-confirm>Yes, log out</button></div></div></dialog>'
            . '<div class="portal-toast" data-portal-toast role="status" aria-live="polite"></div><script src="/assets/js/course-card-theme.js" defer></script><script src="/assets/js/app.js" defer></script><script src="/assets/js/workflow.js" defer></script></body></html>';

        return Response::html($html);
    }

    /** @return array<string, array<string, array{string, string}>> */
    private static function navigation(string $role): array
    {
        return match ($role) {
            'admin' => [
                'Workspace' => ['/admin/dashboard' => ['Overview', 'OV'], '/admin/notifications' => ['Notifications', 'NT']],
                'People' => ['/admin/instructor-approvals' => ['Instructor approvals', 'IA'], '/admin/students' => ['Students', 'ST'], '/admin/instructors' => ['Instructors', 'IN'], '/admin/users' => ['All users', 'US']],
                'Learning' => ['/admin/course-approvals' => ['Course approvals', 'CA'], '/admin/categories' => ['Categories', 'CT'], '/admin/enrollments' => ['Enrollments', 'EN']],
                'Commerce' => ['/admin/orders' => ['Orders', 'OR'], '/admin/payments' => ['Payments', 'PY'], '/admin/refunds' => ['Refunds', 'RF'], '/admin/withdrawals' => ['Withdrawals', 'WD'], '/admin/coupons' => ['Coupons', 'CP']],
                'Operations' => ['/admin/reports' => ['Reports', 'RP'], '/admin/contact-messages' => ['Messages', 'MS'], '/admin/audit-logs' => ['Audit logs', 'AL'], '/admin/security' => ['Security', 'SC'], '/admin/settings' => ['Settings', 'SE']],
            ],
            'instructor' => [
                'Workspace' => ['/instructor/dashboard' => ['Overview', 'OV'], '/instructor/notifications' => ['Notifications', 'NT'], '/instructor/messaging' => ['Messages', 'MS']],
                'Courses' => ['/instructor/courses' => ['All courses', 'CR'], '/instructor/courses/create' => ['Complete authoring', 'NW'], '/instructor/courses/drafts' => ['Drafts', 'DR'], '/instructor/courses/pending' => ['Pending review', 'PN'], '/instructor/courses/published' => ['Published', 'PB']],
                'Business' => ['/instructor/students' => ['Students', 'ST'], '/instructor/sales' => ['Sales', 'SL'], '/instructor/coupons' => ['Coupons', 'CP'], '/instructor/withdrawals' => ['Withdrawals', 'WD'], '/instructor/bank-details' => ['Payout details', 'BK']],
            ],
            default => [
                'Learning' => ['/student/dashboard' => ['Overview', 'OV'], '/student/my-courses' => ['My courses', 'CR'], '/student/course-player' => ['Course player', 'PL'], '/student/progress' => ['Progress', 'PG']],
                'Purchases' => ['/student/cart' => ['My cart', 'CT'], '/student/checkout' => ['Checkout', 'CK'], '/student/payment' => ['Payment', 'PY'], '/student/payment-history' => ['Payment history', 'PH']],
                'Account' => ['/student/notifications' => ['Notifications', 'NT'], '/student/reviews' => ['My reviews', 'RV'], '/student/unsubscribe' => ['Access requests', 'AR']],
            ],
        };
    }

    private static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }
        return $initials !== '' ? $initials : 'CH';
    }
}
