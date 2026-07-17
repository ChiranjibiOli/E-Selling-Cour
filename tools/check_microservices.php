<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$requiredFiles = [
    'architecture.manifest.json',
    'docs/architecture/HOUSE_COMPOUND.md',
    'docker-compose.microservices.yml',
    'infrastructure/docker/php-runtime.Dockerfile',
    'apps/web-platform/public/index.php',
    'apps/web-platform/src/bootstrap.php',
    'apps/web-platform/src/Public/routes.php',
    'apps/web-platform/src/Student/routes.php',
    'apps/web-platform/src/Instructor/routes.php',
    'apps/web-platform/src/Admin/routes.php',
    'services/api-gateway/public/index.php',
    'services/identity-service/public/index.php',
    'services/catalog-service/public/index.php',
    'services/learning-service/public/index.php',
    'services/commerce-service/public/index.php',
    'services/payment-service/public/index.php',
    'services/enrollment-service/public/index.php',
    'services/media-service/public/index.php',
    'services/notification-service/public/index.php',
    'services/review-service/public/index.php',
    'services/reporting-service/public/index.php',
    'packages/api-contracts/openapi.yaml',
];

$forbiddenFiles = [
    'apps/public-web/public/index.php',
    'apps/student-web/public/index.php',
    'apps/instructor-web/public/index.php',
    'apps/admin-web/public/index.php',
];

$errors = [];
foreach ($requiredFiles as $file) {
    if (!is_file($root . '/' . $file)) {
        $errors[] = 'Missing: ' . $file;
    }
}
foreach ($forbiddenFiles as $file) {
    if (is_file($root . '/' . $file)) {
        $errors[] = 'Obsolete four-app scaffold still exists: ' . $file;
    }
}

try {
    $manifest = json_decode((string) file_get_contents($root . '/architecture.manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    if (($manifest['repository_strategy'] ?? null) !== 'monorepo') {
        $errors[] = 'repository_strategy must be monorepo.';
    }
    if (($manifest['branch_strategy'] ?? null) !== 'main-only') {
        $errors[] = 'branch_strategy must be main-only.';
    }
    if (($manifest['frontend']['application'] ?? null) !== 'apps/web-platform') {
        $errors[] = 'The frontend application must be apps/web-platform.';
    }
} catch (Throwable $exception) {
    $errors[] = 'Invalid architecture manifest: ' . $exception->getMessage();
}

$implementedRooms = 0;
foreach (['Public', 'Student', 'Instructor', 'Admin'] as $floor) {
    $floorPath = $root . '/apps/web-platform/src/' . $floor;
    if (!is_dir($floorPath)) {
        $errors[] = 'Missing floor: ' . $floor;
        continue;
    }

    foreach (new DirectoryIterator($floorPath) as $room) {
        if ($room->isDot() || !$room->isDir() || $room->getFilename() === 'Shared') {
            continue;
        }
        $path = $room->getPathname();
        $phpFiles = glob($path . '/*.php') ?: [];
        if ($phpFiles === []) {
            if (!is_file($path . '/README.md')) {
                $errors[] = 'Planned room needs README.md: ' . $floor . '/' . $room->getFilename();
            }
            continue;
        }
        foreach (['Controller.php', 'Middleware.php', 'Service.php', 'Page.php'] as $suffix) {
            $matches = glob($path . '/*' . $suffix) ?: [];
            if ($matches === []) {
                $errors[] = 'Room is missing ' . $suffix . ': ' . $floor . '/' . $room->getFilename();
            }
        }
        $implementedRooms++;
    }
}

require_once $root . '/apps/web-platform/src/bootstrap.php';
$routePaths = [];
foreach (['Public', 'Student', 'Instructor', 'Admin'] as $floor) {
    $routes = require $root . '/apps/web-platform/src/' . $floor . '/routes.php';
    foreach ($routes as $route) {
        $path = (string) ($route['path'] ?? '');
        if ($path === '') {
            $errors[] = 'Route without path on floor ' . $floor;
            continue;
        }
        if (isset($routePaths[$path])) {
            $errors[] = 'Duplicate route path: ' . $path;
        }
        $routePaths[$path] = $floor;
    }
}
if (isset($routePaths['/admin/register'])) {
    $errors[] = 'Admin registration route must not exist.';
}

$phpFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
        continue;
    }
    $relative = str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($root) + 1));
    if (str_starts_with($relative, 'apps/') || str_starts_with($relative, 'services/') || str_starts_with($relative, 'packages/')) {
        $phpFiles[] = $fileInfo->getPathname();
    }
}

if (function_exists('exec')) {
    foreach ($phpFiles as $phpFile) {
        $output = [];
        $exitCode = 0;
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($phpFile) . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            $errors[] = implode(PHP_EOL, $output);
        }
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
echo 'Implemented frontend rooms: ' . $implementedRooms . PHP_EOL;
echo 'Unique routes: ' . count($routePaths) . PHP_EOL;
echo 'PHP files linted: ' . count($phpFiles) . PHP_EOL;
