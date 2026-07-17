<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$requiredFiles = [
    'architecture.manifest.json',
    'docker-compose.microservices.yml',
    'infrastructure/docker/php-runtime.Dockerfile',
    'apps/public-web/public/index.php',
    'apps/student-web/public/index.php',
    'apps/instructor-web/public/index.php',
    'apps/admin-web/public/index.php',
    'services/api-gateway/public/index.php',
    'services/identity-service/public/index.php',
    'services/identity-service/database/migrations/001_identity_sessions.sql',
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

$errors = [];
foreach ($requiredFiles as $file) {
    if (!is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file))) {
        $errors[] = 'Missing: ' . $file;
    }
}

try {
    $manifest = json_decode(
        (string) file_get_contents($root . '/architecture.manifest.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    if (($manifest['repository_strategy'] ?? null) !== 'monorepo') {
        $errors[] = 'architecture.manifest.json must declare repository_strategy=monorepo.';
    }
    if (($manifest['branch_strategy'] ?? null) !== 'main-only') {
        $errors[] = 'architecture.manifest.json must declare branch_strategy=main-only.';
    }
} catch (Throwable $exception) {
    $errors[] = 'Invalid architecture manifest: ' . $exception->getMessage();
}

$phpFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
        continue;
    }

    $path = $fileInfo->getPathname();
    $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
    if (str_starts_with($relative, 'apps/')
        || str_starts_with($relative, 'services/')
        || str_starts_with($relative, 'packages/')
    ) {
        $phpFiles[] = $path;
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
} else {
    echo "NOTICE: exec() is disabled; PHP syntax checks were skipped.\n";
}

if ($errors !== []) {
    echo "MICROSERVICES CHECK: FAIL\n";
    foreach ($errors as $error) {
        echo '- ' . $error . PHP_EOL;
    }
    exit(1);
}

echo 'MICROSERVICES CHECK: PASS' . PHP_EOL;
echo 'Files checked: ' . count($requiredFiles) . PHP_EOL;
echo 'PHP files linted: ' . count($phpFiles) . PHP_EOL;
