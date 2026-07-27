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
        $currentPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $nav = '';

        foreach ($navigation as $group => $links) {
            $nav .= '<div class="portal-nav-group"><span class="portal-nav-label">' . $e($group) . '</span>';
            foreach ($links as $href => [$label, $icon]) {
                $active = $currentPath === $href ? ' active' : '';
                $nav .= '<a class="portal-nav-link' . $active . '" href="' . $e($href) . '"><span class="portal-nav-icon">' . $e($icon) . '</span><span>' . $e($label) . '</span></a>';
            }
            $nav .= '</div>';
        }

        $user = AuthSession::user();
        $name = (string) ($user['name'] ?? ucfirst($role) . ' account');
        $email = (string) ($user['email'] ?? '');
        $initials = self::initials($name);
        $roleName = match ($role) {
            'admin' => 'Platform control',
            'instructor' => 'Instructor studio',
            default => 'Learning space',
        };
        $subtitle = match ($role) {
            'admin' => 'Monitor operations, approvals and platform health.',
            'instructor' => 'Build courses, support students and grow your teaching business.',
            default => 'Continue learning and keep your progress moving.',
        };
        $dashboard = match ($role) {
            'admin' => '/admin/dashboard',
            'instructor' => '/instructor/dashboard',
            default => '/student/dashboard',
        };
        $logout = '<form class="portal-logout-form" method="post" action="/logout">' . Csrf::field()
            . '<button class="portal-logout-button" type="submit"><span class="portal-nav-icon">↪</span><span>Log out</span></button></form>';

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="theme-color" content="#07122d"><title>' . $e($title) . ' | CourseHub</title><link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/assets/css/commerce.css"><link rel="stylesheet" href="/assets/css/portal-fixes.css"><link rel="stylesheet" href="/assets/css/instructor-identity.css"></head>'
            . '<body class="portal-shell portal-role-' . $e($role) . '"><button class="portal-mobile-toggle" type="button" data-portal-toggle aria-label="Open navigation" aria-expanded="false"><span></span><span></span><span></span></button>'
            . '<div class="portal-overlay" data-portal-overlay></div><aside class="portal-sidebar" data-portal-sidebar><a class="portal-brand" href="' . $e($dashboard) . '"><span>CH</span><strong>CourseHub</strong></a>'
            . '<div class="portal-workspace"><span>' . $e(ucfirst($role)) . '</span><strong>' . $e($roleName) . '</strong></div><nav class="portal-sidebar-nav">' . $nav . '</nav>'
            . '<div class="portal-sidebar-foot"><a href="/contact"><span class="portal-nav-icon">?</span><span>Help & support</span></a>' . $logout
            . '<div class="portal-sidebar-user"><span class="portal-avatar">' . $e($initials) . '</span><div><strong>' . $e($name) . '</strong><small>' . $e($email !== '' ? $email : ucfirst($role)) . '</small></div></div></div></aside>'
            . '<div class="portal-stage"><header class="portal-topbar"><div class="portal-crumb"><span>' . $e(ucfirst($role)) . '</span><i>/</i><strong>' . $e($title) . '</strong></div><div class="portal-top-actions"><label class="portal-search"><span>⌕</span><input type="search" placeholder="Search this workspace" aria-label="Search this workspace"></label><button class="portal-icon-button" type="button" aria-label="Notifications"><span>◔</span><i></i></button><span class="portal-top-avatar">' . $e($initials) . '</span></div></header>'
            . '<main class="portal-main"><header class="portal-head"><div><span class="portal-eyebrow">' . strtoupper($e($roleName)) . '</span><h1>' . $e($title) . '</h1><p>' . $e($subtitle) . '</p></div><div class="portal-head-action">' . $action . '</div></header>'
            . $content . '</main><footer class="portal-footer"><span>CourseHub workspace</span><span>Secure role-based access</span></footer></div>'
            . '<div class="portal-toast" data-portal-toast role="status" aria-live="polite"></div><script src="/assets/js/app.js" defer></script></body></html>';

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
                'Workspace' => ['/instructor/dashboard' => ['Overview', 'OV'], '/instructor/verification-pending' => ['Verification', 'VR'], '/instructor/notifications' => ['Notifications', 'NT'], '/instructor/messaging' => ['Messages', 'MS']],
                'Courses' => ['/instructor/courses' => ['All courses', 'CR'], '/instructor/courses/create' => ['Create course', 'NW'], '/instructor/courses/drafts' => ['Drafts', 'DR'], '/instructor/courses/pending' => ['Pending review', 'PN'], '/instructor/courses/published' => ['Published', 'PB'], '/instructor/curriculum' => ['Curriculum', 'CU'], '/instructor/lessons' => ['Lessons', 'LS']],
                'Business' => ['/instructor/students' => ['Students', 'ST'], '/instructor/sales' => ['Sales', 'SL'], '/instructor/coupons' => ['Coupons', 'CP'], '/instructor/withdrawals' => ['Withdrawals', 'WD'], '/instructor/bank-details' => ['Payout details', 'BK']],
                'Account' => ['/instructor/profile' => ['Profile', 'PR']],
            ],
            default => [
                'Learning' => ['/student/dashboard' => ['Overview', 'OV'], '/student/my-courses' => ['My courses', 'CR'], '/student/course-player' => ['Course player', 'PL'], '/student/progress' => ['Progress', 'PG']],
                'Purchases' => ['/student/cart' => ['My cart', 'CT'], '/student/checkout' => ['Checkout', 'CK'], '/student/payment' => ['Payment', 'PY'], '/student/payment-history' => ['Payment history', 'PH']],
                'Account' => ['/student/notifications' => ['Notifications', 'NT'], '/student/reviews' => ['My reviews', 'RV'], '/student/unsubscribe' => ['Access requests', 'AR'], '/student/profile' => ['Profile', 'PR']],
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
