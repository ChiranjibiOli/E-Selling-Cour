<?php

declare(strict_types=1);

namespace CourseHub\PublicWeb\Pages\Login;

use CourseHub\SharedHttp\ApiClient;
use CourseHub\SharedUi\PortalShell;

final class LoginPage
{
    public function render(): string
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/login'), PHP_URL_PATH) ?: '/login';
        $result = null;

        if ($path === '/oauth/google') {
            $result = ['ok' => false, 'data' => ['error' => 'Google OAuth requires provider credentials and callback configuration before it can be enabled.']];
        } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $client = new ApiClient((string) (getenv('API_BASE_URL') ?: 'http://localhost:9000'));
            $result = $client->post('/api/v1/auth/login', [
                'portal' => 'student',
                'email' => trim((string) ($_POST['email'] ?? '')),
                'password' => (string) ($_POST['password'] ?? ''),
            ]);
        }

        return PortalShell::page(
            'Student sign in',
            PortalShell::login('student', 'Continue learning.', $result, true),
            '#765522'
        );
    }
}
