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

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');

$respond = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
};

$jsonInput = static function (): array {
    $raw = (string) file_get_contents('php://input');
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Request body must be a JSON object.');
    }
    return $decoded;
};

$environment = static function (string $name, string $fallback = ''): string {
    $value = trim((string) getenv($name));
    return $value !== '' ? $value : $fallback;
};

$booleanEnvironment = static function (string $name, bool $fallback): bool {
    $raw = trim((string) getenv($name));
    if ($raw === '') {
        return $fallback;
    }
    $value = filter_var($raw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    return is_bool($value) ? $value : $fallback;
};

$isPublicUrl = static function (string $url): bool {
    $parts = parse_url(trim($url));
    if (!is_array($parts)) {
        return false;
    }
    return in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
        && trim((string) ($parts['host'] ?? '')) !== ''
        && !isset($parts['user'], $parts['pass'], $parts['fragment']);
};

$settingValue = static function (PDO $database, string $key): string {
    $statement = $database->prepare('SELECT setting_value FROM site_settings WHERE setting_key = :key LIMIT 1');
    $statement->execute(['key' => $key]);
    $value = $statement->fetchColumn();
    return $value === false ? '' : trim((string) $value);
};

$gatewayStates = static function (PDO $database) use ($environment, $booleanEnvironment, $isPublicUrl, $settingValue): array {
    $appUrl = $environment('APP_URL');
    $appEnvironment = strtolower($environment('APP_ENV', 'local'));
    $configuredEsewaEnvironment = strtolower($environment('ESEWA_ENV', 'sandbox'));
    $useLocalEsewaDemo = $appEnvironment !== 'production'
        && $configuredEsewaEnvironment !== 'production'
        && $booleanEnvironment('ESEWA_LOCAL_DEMO', true);

    $esewaMode = $useLocalEsewaDemo
        ? 'local-demo'
        : ($configuredEsewaEnvironment === 'production' ? 'production' : 'sandbox');
    $esewaPaymentUrl = $environment(
        'ESEWA_PAYMENT_URL',
        $esewaMode === 'production'
            ? 'https://epay.esewa.com.np/api/epay/main/v2/form'
            : 'https://rc-epay.esewa.com.np/api/epay/main/v2/form'
    );
    $esewaHost = strtolower((string) (parse_url($esewaPaymentUrl, PHP_URL_HOST) ?: ''));
    $esewaConfigured = $isPublicUrl($appUrl)
        && $environment('ESEWA_PRODUCT_CODE') !== ''
        && $environment('ESEWA_SECRET_KEY') !== ''
        && ($useLocalEsewaDemo || in_array($esewaHost, ['rc-epay.esewa.com.np', 'epay.esewa.com.np'], true));
    $esewaEnabled = $settingValue($database, 'esewa_enabled') === '1';

    $khaltiMode = strtolower($environment('KHALTI_ENV', 'sandbox')) === 'production' ? 'production' : 'sandbox';
    $khaltiApiUrl = $environment(
        'KHALTI_API_URL',
        $khaltiMode === 'production' ? 'https://khalti.com/api/v2' : 'https://dev.khalti.com/api/v2'
    );
    $khaltiHost = strtolower((string) (parse_url($khaltiApiUrl, PHP_URL_HOST) ?: ''));
    $websiteUrl = $environment('KHALTI_WEBSITE_URL', $appUrl);
    $khaltiConfigured = $isPublicUrl($appUrl)
        && $isPublicUrl($websiteUrl)
        && $environment('KHALTI_SECRET_KEY') !== ''
        && in_array($khaltiHost, ['dev.khalti.com', 'khalti.com'], true);
    $khaltiEnabled = $settingValue($database, 'khalti_enabled') === '1';

    return [
        'manual' => [
            'configured' => true,
            'enabled' => true,
            'available' => true,
            'mode' => 'manual',
        ],
        'esewa' => [
            'configured' => $esewaConfigured,
            'enabled' => $esewaEnabled,
            'available' => $esewaConfigured && $esewaEnabled,
            'mode' => $esewaMode,
            'merchant_identifier' => $settingValue($database, 'esewa_id'),
            'product_code' => $environment('ESEWA_PRODUCT_CODE'),
        ],
        'khalti' => [
            'configured' => $khaltiConfigured,
            'enabled' => $khaltiEnabled,
            'available' => $khaltiConfigured && $khaltiEnabled,
            'mode' => $khaltiMode,
            'merchant_identifier' => $settingValue($database, 'khalti_id'),
        ],
    ];
};

try {
    $database = Database::connect();

    if ($path === '/api/v1/payments/options' && $method === 'GET') {
        ServiceAuth::requireUser($database, $authorization, 'student');
        $states = $gatewayStates($database);
        foreach (['esewa', 'khalti'] as $provider) {
            unset($states[$provider]['merchant_identifier'], $states[$provider]['product_code']);
        }
        $respond(['data' => $states]);
    }

    if ($path === '/api/v1/payments/admin/gateways' && $method === 'GET') {
        ServiceAuth::requireUser($database, $authorization, 'admin');
        $respond(['data' => $gatewayStates($database)]);
    }

    if ($path === '/api/v1/payments/admin/gateways' && $method === 'POST') {
        ServiceAuth::requireUser($database, $authorization, 'admin');
        $input = $jsonInput();
        $requested = [
            'esewa' => filter_var($input['esewa_enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'khalti' => filter_var($input['khalti_enabled'] ?? false, FILTER_VALIDATE_BOOL),
        ];
        $states = $gatewayStates($database);
        foreach ($requested as $provider => $enabled) {
            if ($enabled && ($states[$provider]['configured'] ?? false) !== true) {
                throw new InvalidArgumentException(ucfirst($provider) . ' cannot be enabled until its merchant credentials and application URLs are configured in the server environment.');
            }
        }

        $database->beginTransaction();
        $save = $database->prepare(
            'INSERT INTO site_settings (setting_key, setting_value) VALUES (:key, :value) '
            . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        foreach ($requested as $provider => $enabled) {
            $save->execute([
                'key' => $provider . '_enabled',
                'value' => $enabled ? '1' : '0',
            ]);
        }
        $database->commit();

        $respond([
            'message' => 'Payment gateway availability updated.',
            'data' => $gatewayStates($database),
        ]);
    }

    $respond(['error' => 'Gateway settings route not found.'], 404);
} catch (ServiceAuthenticationException $exception) {
    $respond(['error' => $exception->getMessage()], 401);
} catch (ServiceAuthorizationException $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    $respond(['error' => $exception->getMessage()], 403);
} catch (InvalidArgumentException $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    $respond(['error' => $exception->getMessage()], 422);
} catch (JsonException) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    $respond(['error' => 'Malformed JSON request.'], 400);
} catch (PDOException $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Gateway settings database failure: ' . $exception->getMessage());
    $respond(['error' => 'Gateway settings could not be saved.'], 409);
} catch (Throwable $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Gateway settings failure: ' . $exception->getMessage());
    $respond(['error' => 'Gateway settings are unavailable.'], 503);
}
