<?php

declare(strict_types=1);

namespace CourseHub\Services\Shared;

final class FeatureRuntime
{
    public static function metadata(string $directory): array
    {
        $feature = basename($directory);
        $service = basename(dirname($directory, 3));
        $manifest = require dirname(__DIR__) . '/features.manifest.php';
        $config = $manifest[$service] ?? ['route'=>'/','features'=>[],'implemented'=>[]];
        return [
            'service'=>$service,
            'feature'=>$feature,
            'route'=>$config['route'],
            'registered'=>in_array($feature,$config['features'],true),
            'status'=>in_array($feature,$config['implemented'],true)?'implemented':'structure-created',
        ];
    }

    public static function handle(string $directory, array $request): array
    {
        return self::metadata($directory) + ['request'=>$request];
    }
}
