<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Ui;

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Session\AuthSession;

final class PortalPage
{
    public static function render(string $role, string $title, string $content, string $action = ''): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $links = match ($role) {
            'instructor' => [
                '/instructor/dashboard' => 'Overview',
                '/instructor/courses' => 'My courses',
                '/instructor/courses/create' => 'Create course',
                '/instructor/students' => 'Students',
                '/instructor/withdrawals' => 'Withdrawals',
            ],
            'admin' => [
                '/admin/dashboard' => 'Overview',
                '/admin/instructor-approvals' => 'Instructors',
                '/admin/course-approvals' => 'Courses',
                '/admin/payments' => 'Payments',
                '/admin/reports' => 'Reports',
            ],
            default => [
                '/student/dashboard' => 'Overview',
                '/student/my-courses' => 'My courses',
                '/student/cart' => 'Cart',
                '/student/payment-history' => 'Payments',
            ],
        };
        $nav = '';
        foreach ($links as $href => $label) {
            $nav .= '<a href="' . $e($href) . '">' . $e($label) . '</a>';
        }
        $user = AuthSession::user();
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $e($title) . ' | CourseHub</title><link rel="stylesheet" href="/assets/css/app.css"></head>'
            . '<body class="portal-shell"><header class="portal-nav"><a href="/">CourseHub</a><nav>' . $nav . '</nav>'
            . '<span class="portal-user">' . $e($user['name'] ?? ucfirst($role)) . '</span></header><main class="portal-main">'
            . '<header class="portal-head"><div><span class="portal-eyebrow">' . strtoupper($e($role)) . ' PORTAL</span><h1>' . $e($title) . '</h1></div>' . $action . '</header>'
            . $content . '</main></body></html>';
        return Response::html($html);
    }
}
