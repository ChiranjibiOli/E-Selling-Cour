<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\View;

final class HouseLayout
{
    public static function render(string $title, string $content): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $safeTitle . ' | CourseHub</title>'
            . '<style>body{margin:0;font-family:system-ui;background:#f5efe5;color:#171511}.house-nav{display:flex;gap:18px;padding:18px 5vw;background:#171511}.house-nav a{color:#fff;text-decoration:none}.house-main{max-width:1100px;margin:auto;padding:60px 24px}.room-card{background:#fff;padding:36px;border-radius:20px;box-shadow:0 20px 50px rgba(0,0,0,.08)}.room-floor{text-transform:uppercase;letter-spacing:.14em;font-size:12px;color:#8c6322}</style>'
            . '</head><body><nav class="house-nav"><a href="/">Public</a><a href="/student/login">Student</a><a href="/instructor/login">Instructor</a><a href="/admin/login">Admin</a></nav>'
            . '<main class="house-main">' . $content . '</main></body></html>';
    }
}
