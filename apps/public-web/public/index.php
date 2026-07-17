<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use CourseHub\PublicWeb\Pages\About\AboutPage;
use CourseHub\PublicWeb\Pages\Contact\ContactPage;
use CourseHub\PublicWeb\Pages\Landing\LandingPage;
use CourseHub\PublicWeb\Pages\Login\LoginPage;
use CourseHub\PublicWeb\Pages\Privacy\PrivacyPage;
use CourseHub\PublicWeb\Pages\Registration\RegistrationPage;
use CourseHub\PublicWeb\Pages\Terms\TermsPage;

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

if ($path === '/health') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'ok', 'app' => 'public-web'], JSON_THROW_ON_ERROR);
    exit;
}

$page = match ($path) {
    '/' => new LandingPage(),
    '/about' => new AboutPage(),
    '/contact' => new ContactPage(),
    '/login', '/oauth/google' => new LoginPage(),
    '/register' => new RegistrationPage(),
    '/privacy' => new PrivacyPage(),
    '/terms' => new TermsPage(),
    default => null,
};

if ($page === null) {
    http_response_code(404);
    echo 'Page not found';
    exit;
}

echo $page->render();
