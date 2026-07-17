<?php

declare(strict_types=1);

namespace CourseHub\Services\Shared;

final class FeatureRuntime
{
    /** @return array<string, mixed> */
    public static function metadata(string $directory): array
    {
        $feature = basename($directory);
        $serviceRoot = dirname($directory, 3);
        $service = basename($serviceRoot);
        $configFile = $serviceRoot . '/config/features.php';

        if (!is_file($configFile)) {
            return ['service' => $service, 'feature' => $feature, 'status' => 'planned'];
        }

        /** @var array<string, array<string, mixed>> $features */
        $features = require $configFile;
        return ($features[$feature] ?? ['status' => 'planned']) + [
            'service' => $service,
            'feature' => $feature,
        ];
    }

    /** @param array<string, mixed> $request */
    public static function handle(string $directory, array $request): array
    {
        $metadata = self::metadata($directory);
        return [
            'service' => $metadata['service'],
            'feature' => $metadata['feature'],
            'status' => $metadata['status'] ?? 'planned',
            'message' => ($metadata['status'] ?? 'planned') === 'planned'
                ? 'Feature room exists and is reserved for implementation.'
                : 'Feature room is registered and ready for domain logic.',
            'request' => $request,
        ];
    }
}
