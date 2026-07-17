<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$roomConfig = require $root . '/apps/web-platform/src/config/rooms.php';
$roomTemplates = require $root . '/apps/web-platform/src/Shared/Room/Templates.php';
$featureTemplates = require $root . '/services/_shared/FeatureTemplates.php';

foreach ($roomConfig as $key => $metadata) {
    [$floor, $room] = explode('/', $key, 2);
    $directory = $root . '/apps/web-platform/src/' . $floor . '/' . $room;
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create room: ' . $directory);
    }

    foreach ($roomTemplates as $filename => $content) {
        file_put_contents($directory . '/' . $filename, $content . PHP_EOL);
    }

    foreach (['Components', 'Assets', 'Tests'] as $subdirectory) {
        $path = $directory . '/' . $subdirectory;
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }

    file_put_contents($directory . '/Components/README.md', "# {$room} components\n\nRoom-owned UI components belong here.\n");
    file_put_contents($directory . '/Assets/page.css', "/* {$floor}/{$room} room styles */\n");
    file_put_contents($directory . '/Assets/page.js', "// {$floor}/{$room} room behavior\n");
    file_put_contents($directory . '/Tests/README.md', "# {$room} tests\n\nUnit, request, authorization, and browser tests belong here.\n");
    file_put_contents($directory . '/ROOM.md', "# {$floor} / {$room}\n\n- Route: `{$metadata['path']}`\n- Methods: `{$metadata['methods']}`\n- Required role: `{$metadata['role']}`\n- Backend service: `{$metadata['service']}`\n- Status: `{$metadata['status']}`\n");
}

$featureMapFile = $root . '/services/features.manifest.php';
if (is_file($featureMapFile)) {
    $featureMap = require $featureMapFile;
    foreach ($featureMap as $service => $features) {
        foreach ($features as $feature => $metadata) {
            $directory = $root . '/services/' . $service . '/src/Features/' . $feature;
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException('Could not create feature: ' . $directory);
            }
            foreach ($featureTemplates as $filename => $content) {
                file_put_contents($directory . '/' . $filename, $content . PHP_EOL);
            }
            if (!is_dir($directory . '/Tests')) {
                mkdir($directory . '/Tests', 0775, true);
            }
            file_put_contents($directory . '/Tests/README.md', "# {$feature} tests\n");
            file_put_contents($directory . '/FEATURE.md', "# {$service} / {$feature}\n\n- Status: `{$metadata['status']}`\n- Route prefix: `{$metadata['route']}`\n");
        }
    }
}

echo "Architecture room files generated.\n";
