<?php

declare(strict_types=1);

namespace CourseHub\Apps;

use CourseHub\SharedHttp\ApiClient;
use CourseHub\SharedUi\PortalShell;

final class PortalApplication
{
    public static function run(string $role, bool $allowSignup, string $accent): void
    {
        $root = dirname(__DIR__, 2);
        require_once $root . '/packages/shared-ui/src/PortalShell.php';
        require_once $root . '/packages/shared-http/src/ApiClient.php';

        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

        if ($path === '/health') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'ok', 'app' => $role . '-web'], JSON_THROW_ON_ERROR);
            return;
        }

        if ($path === '/register') {
            if (!$allowSignup) {
                http_response_code(404);
                echo PortalShell::page('Not found', '<h1>Not found.</h1><p>This portal does not allow public account creation.</p>', $accent);
                return;
            }

            echo PortalShell::page(
                ucfirst($role) . ' registration',
                '<span class="eyebrow">' . htmlspecialchars($role, ENT_QUOTES, 'UTF-8') . ' registration</span>'
                . '<h1>Create your account.</h1><p>The registration UI is isolated in this portal. Its identity-service endpoint will be added in the registration migration slice.</p>'
                . '<div class="notice">Registration submission is intentionally disabled until verification rules and database migration are installed.</div>'
                . '<div class="links"><a class="button secondary" href="/login">Back to login</a></div>',
                $accent
            );
            return;
        }

        $result = null;
        if ($path === '/oauth/google') {
            $result = ['ok' => false, 'data' => ['error' => 'OAuth is isolated but remains disabled until credentials, state storage and callback validation are configured.']];
        } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $path === '/login') {
            $client = new ApiClient((string) (getenv('API_BASE_URL') ?: 'http://localhost:9000'));
            $result = $client->post('/api/v1/auth/login', [
                'portal' => $role,
                'email' => trim((string) ($_POST['email'] ?? '')),
                'password' => (string) ($_POST['password'] ?? ''),
            ]);
        }

        echo PortalShell::page(
            ucfirst($role) . ' portal',
            PortalShell::login($role, self::heading($role), $result, $allowSignup),
            $accent
        );
    }

    private static function heading(string $role): string
    {
        return match ($role) {
            'student' => 'Continue learning.',
            'instructor' => 'Build and teach.',
            'admin' => 'Secure administration.',
            default => 'Sign in.',
        };
    }
}
