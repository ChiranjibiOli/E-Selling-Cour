<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$sourceRoot = $root . '/apps/web-platform/src';
$errors = [];
$warnings = [];
$postFormFiles = 0;
$inputCount = 0;

$requiredContracts = [
    'apps/web-platform/src/Shared/Http/Request.php' => [
        'MAX_BODY_FIELDS',
        'MAX_DEPTH',
        'validatePayload',
        'null byte',
    ],
    'apps/web-platform/src/Shared/Security/FormInput.php' => [
        'function text(',
        'function multiline(',
        'function integer(',
        'function decimal(',
        'function httpsUrl(',
    ],
    'apps/web-platform/public/index.php' => [
        'catch (InvalidArgumentException $exception)',
        'Invalid request',
    ],
];
foreach ($requiredContracts as $path => $needles) {
    $content = is_file($root . '/' . $path) ? (string) file_get_contents($root . '/' . $path) : '';
    if ($content === '') {
        $errors[] = 'Required form-security file is missing: ' . $path;
        continue;
    }
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $errors[] = 'Missing form-security contract in ' . $path . ': ' . $needle;
        }
    }
}

$expectedTypes = [
    'email' => 'email',
    'phone' => 'tel',
    'password' => 'password',
    'password_confirmation' => 'password',
    'price' => 'number',
    'discount_price' => 'number',
    'duration_hours' => 'number',
    'quantity' => 'number',
    'amount' => 'number',
    'usage_limit' => 'number',
    'intro_video_url' => 'url',
    'social_profile_url' => 'url',
];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    $content = (string) file_get_contents($file->getPathname());
    if ($content === '') {
        continue;
    }

    $hasPostForm = preg_match('/<form\b[^>]*method=["\']post["\']/i', $content) === 1;
    if ($hasPostForm) {
        $postFormFiles++;
        if (!str_contains($content, 'Csrf::field()') && !str_contains($content, 'name="_token"') && !str_contains($content, "name='_token'")) {
            $errors[] = 'POST form has no visible CSRF token contract: ' . $path;
        }
    }

    if (preg_match_all('/<input\b[^>]*name=["\']([^"\']+)["\'][^>]*>/i', $content, $matches) !== false) {
        foreach ($matches[0] as $index => $tag) {
            $inputCount++;
            $name = (string) ($matches[1][$index] ?? '');
            if (!isset($expectedTypes[$name])) {
                continue;
            }
            $type = 'text';
            if (preg_match('/\btype=["\']([^"\']+)["\']/i', $tag, $typeMatch) === 1) {
                $type = strtolower((string) $typeMatch[1]);
            }
            if ($type !== $expectedTypes[$name]) {
                $warnings[] = sprintf('%s uses type="%s" for %s; expected type="%s".', $path, $type, $name, $expectedTypes[$name]);
            }
        }
    }

    if (preg_match('/<input\b[^>]*type=["\']file["\'][^>]*>/i', $content) === 1
        && !str_contains($content, 'accept=')
    ) {
        $warnings[] = 'File input has no accept filter: ' . $path;
    }
}

if ($errors !== []) {
    echo "FORM SECURITY CHECK: FAIL\n";
    foreach ($errors as $error) {
        echo '- ' . $error . PHP_EOL;
    }
    if ($warnings !== []) {
        echo "WARNINGS:\n";
        foreach ($warnings as $warning) {
            echo '- ' . $warning . PHP_EOL;
        }
    }
    exit(1);
}

echo "FORM SECURITY CHECK: PASS\n";
echo 'POST form source files checked: ' . $postFormFiles . PHP_EOL;
echo 'Named input elements checked: ' . $inputCount . PHP_EOL;
echo "Central payload limits, CSRF contracts and typed validation are present.\n";
if ($warnings !== []) {
    echo 'Semantic input warnings: ' . count($warnings) . PHP_EOL;
    foreach ($warnings as $warning) {
        echo '- ' . $warning . PHP_EOL;
    }
} else {
    echo "Semantic input warnings: 0\n";
}
