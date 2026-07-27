<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Ui;

use CourseHub\WebPlatform\Shared\Http\Response;

final class PublicInformationPage
{
    /** @param array<int,array{title:string,body:string}> $sections */
    public static function render(string $title, string $eyebrow, string $intro, array $sections, string $notice = ''): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $articles = '';
        foreach ($sections as $index => $section) {
            $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $articles .= '<article class="info-section"><span>' . $e($number) . '</span><div><h2>' . $e($section['title']) . '</h2><div class="info-body">' . $section['body'] . '</div></div></article>';
        }

        $noticeHtml = $notice !== '' ? '<div class="info-notice">' . $e($notice) . '</div>' : '';

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#f7f0e5"><title>' . $e($title) . ' | CourseHub</title>'
            . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;1,500;1,600&display=swap" rel="stylesheet">'
            . '<link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/room-assets/Public/Information/page.css"><link rel="stylesheet" href="/assets/css/public-site.css?v=20260728-1">' . PublicNavbar::styles() . '</head>'
            . '<body class="info-body-page">' . PublicNavbar::render('')
            . '<main><section class="info-hero"><span>' . $e($eyebrow) . '</span><h1>' . $e($title) . '</h1><p>' . $e($intro) . '</p></section>' . $noticeHtml
            . '<section class="info-content">' . $articles . '</section><section class="info-closing"><div><span>NEED HELP?</span><h2>Clear information should still leave room for a real question.</h2></div><a href="/contact">Contact CourseHub support →</a></section></main>'
            . '<footer class="info-footer"><a href="/">CourseHub</a><div><a href="/faq">FAQ</a><a href="/privacy">Privacy</a><a href="/terms">Terms</a><a href="/contact">Support</a></div><span>Education that moves with you.</span></footer>'
            . PublicNavbar::script() . '</body></html>';
        return Response::html($html);
    }
}
