<?php

declare(strict_types=1);

use CourseHub\Services\Shared\Database;
use CourseHub\Services\Shared\ServiceAuth;
use CourseHub\Services\Shared\ServiceAuthenticationException;
use CourseHub\Services\Shared\ServiceAuthorizationException;

require_once dirname(__DIR__, 2) . '/_shared/Database.php';
require_once dirname(__DIR__, 2) . '/_shared/ServiceAuth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$respond = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
};

try {
    $database = Database::connect();
    ServiceAuth::requireUser($database, (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''), 'instructor');
    $respond([
        'error' => 'Legacy section and lesson mutations are disabled. Use the complete course authoring page, where section and lesson numbers are assigned automatically and the whole course is validated together.',
        'authoring_path' => '/instructor/courses/create',
    ], 409);
} catch (ServiceAuthenticationException $exception) {
    $respond(['error' => $exception->getMessage()], 401);
} catch (ServiceAuthorizationException $exception) {
    $respond(['error' => $exception->getMessage()], 403);
} catch (Throwable $exception) {
    error_log('Curriculum mutation guard failure: ' . $exception->getMessage());
    $respond(['error' => 'The curriculum guard is unavailable.'], 503);
}
