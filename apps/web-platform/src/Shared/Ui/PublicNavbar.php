<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Ui;

final class PublicNavbar
{
    public static function render(string $active = '', string $accountState = 'guest'): string
    {
        $activeClass = static fn (string $name): string => $active === $name ? ' active' : '';
        $current = static fn (string $name): string => $active === $name ? ' aria-current="page"' : '';

        $account = $accountState === 'student'
            ? '<div class="coursehub-public-account public-site-account landing-account"><a class="public-login" href="/student/my-courses">My courses</a></div>'
            : '<div class="coursehub-public-account public-site-account landing-account">'
                . '<a class="public-login' . $activeClass('login') . '" href="/learn/sign-in"' . $current('login') . '>Log in</a>'
                . '<a class="public-create landing-create' . $activeClass('register') . '" href="/register/student"' . $current('register') . '>Create account</a>'
                . '</div>';

        return '<header class="coursehub-public-nav public-site-nav landing-nav" data-public-site-nav data-landing-nav>'
            . '<a class="coursehub-public-brand public-site-brand landing-brand" href="/" aria-label="CourseHub home">'
            . '<span class="landing-brand-mark"><img src="/assets/images/coursehub-robot.svg" alt=""></span><strong>CourseHub</strong></a>'
            . '<button class="coursehub-public-menu public-site-menu landing-menu" type="button" data-public-site-menu data-landing-menu aria-label="Open navigation" aria-expanded="false"><span></span><span></span></button>'
            . '<nav class="coursehub-public-links public-site-links landing-links" aria-label="Public navigation" data-landing-links>'
            . '<a class="' . trim($activeClass('home')) . '" data-nav-section="top" href="/#top"' . $current('home') . '>Home</a>'
            . '<a class="' . trim($activeClass('courses')) . '" data-nav-section="courses" href="/courses"' . $current('courses') . '>Courses</a>'
            . '<a class="' . trim($activeClass('categories')) . '" data-nav-section="categories" href="/#categories"' . $current('categories') . '>Categories</a>'
            . '<a class="' . trim($activeClass('about')) . '" data-nav-section="promise" href="/#promise"' . $current('about') . '>About</a>'
            . '<a class="' . trim($activeClass('contact')) . '" data-nav-section="contact" href="/contact"' . $current('contact') . '>Contact</a>'
            . '</nav>'
            . $account
            . '</header>';
    }

    public static function styles(): string
    {
        return '<link rel="stylesheet" href="/assets/css/public-navbar.css?v=20260728-1">';
    }

    public static function script(): string
    {
        return '<script src="/assets/js/public-site.js?v=20260728-3" defer></script>';
    }
}
