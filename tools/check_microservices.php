<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
foreach (['app', 'public', 'routes'] as $legacy) {
    if (is_dir($root . '/' . $legacy)) {
        $errors[] = 'Legacy directory still exists: ' . $legacy;
    }
}

$roomFiles = ['Route.php','Controller.php','Middleware.php','Request.php','Validator.php','Service.php','ApiClient.php','ViewModel.php','Page.php','ROOM.md','Components/README.md','Assets/page.css','Assets/page.js','Tests/README.md'];
$rooms = require $root . '/apps/web-platform/src/config/rooms.php';
$paths = [];
foreach ($rooms as $key => $metadata) {
    if (isset($paths[$metadata['path']])) {
        $errors[] = 'Duplicate route path: ' . $metadata['path'];
    }
    $paths[$metadata['path']] = true;
    $directory = $root . '/apps/web-platform/src/' . $key;
    foreach ($roomFiles as $file) {
        if (!is_file($directory . '/' . $file)) {
            $errors[] = 'Missing frontend room file: ' . $key . '/' . $file;
        }
    }
}

$featureFiles = ['Route.php','Controller.php','Middleware.php','Request.php','Validator.php','Policy.php','Handler.php','Repository.php','Response.php','Event.php','FEATURE.md','Tests/README.md'];
$services = require $root . '/services/features.manifest.php';
foreach ($services as $service => $config) {
    foreach ($config['features'] as $feature) {
        $directory = $root . '/services/' . $service . '/src/Features/' . $feature;
        foreach ($featureFiles as $file) {
            if (!is_file($directory . '/' . $file)) {
                $errors[] = 'Missing backend feature file: ' . $service . '/' . $feature . '/' . $file;
            }
        }
    }
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
        continue;
    }
    $relative = str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($root) + 1));
    if (!str_starts_with($relative, 'apps/') && !str_starts_with($relative, 'services/') && !str_starts_with($relative, 'tools/')) {
        continue;
    }
    $output = [];
    $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($fileInfo->getPathname()) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        $errors[] = implode(PHP_EOL, $output);
    }
}

if ($errors !== []) {
    echo "HOUSE-COMPOUND CHECK: FAIL\n";
    foreach ($errors as $error) {
        echo '- ' . $error . PHP_EOL;
    }
    exit(1);
}

echo "HOUSE-COMPOUND CHECK: PASS\n";
echo 'Frontend rooms: ' . count($rooms) . PHP_EOL;
$featureCount = 0;
foreach ($services as $config) {
    $featureCount += count($config['features']);
}
echo 'Backend feature rooms: ' . $featureCount . PHP_EOL;
