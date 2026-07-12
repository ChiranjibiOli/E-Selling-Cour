<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$scanRoots = [$root . '/app', $root . '/public'];
$failures = [];
$warnings = [];
$filesScanned = 0;

$forbiddenFunctionPatterns = [
    '/\beval\s*\(/i' => 'eval()',
    '/\bassert\s*\(\s*\$/i' => 'dynamic assert()',
    '/\bshell_exec\s*\(/i' => 'shell_exec()',
    '/\bexec\s*\(/i' => 'exec()',
    '/\bsystem\s*\(/i' => 'system()',
    '/\bpassthru\s*\(/i' => 'passthru()',
    '/\bproc_open\s*\(/i' => 'proc_open()',
    '/\bpopen\s*\(/i' => 'popen()',
    '/\bunserialize\s*\(/i' => 'unserialize()',
];

$secretPatterns = [
    '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/' => 'private key material',
    '/\bsk-(?:proj-)?[A-Za-z0-9_-]{20,}\b/' => 'API secret key',
    '/DB_PASSWORD\s*=\s*[^\s#][^\r\n]*/' => 'committed database password',
];

foreach ($scanRoots as $scanRoot) {
    if (!is_dir($scanRoot)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
            continue;
        }

        $path = $fileInfo->getPathname();
        $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
        $content = file_get_contents($path);
        $filesScanned++;

        if (!is_string($content)) {
            $failures[] = "Unable to read {$relative}.";
            continue;
        }

        foreach ($forbiddenFunctionPatterns as $pattern => $label) {
            if (preg_match($pattern, $content)) {
                $failures[] = "{$relative} contains forbidden {$label}.";
            }
        }

        foreach ($secretPatterns as $pattern => $label) {
            if (preg_match($pattern, $content)) {
                $failures[] = "{$relative} appears to contain {$label}.";
            }
        }

        if (preg_match('/header\s*\(\s*[\'\"]Location:\s*[\'\"]\s*\.\s*\$_(?:GET|POST|REQUEST|SERVER)/i', $content)) {
            $failures[] = "{$relative} builds a redirect directly from a superglobal.";
        }

        if (preg_match('/(?:include|require)(?:_once)?\s*\(?\s*\$_(?:GET|POST|REQUEST)/i', $content)) {
            $failures[] = "{$relative} includes a file path from request input.";
        }

        if (preg_match('/SELECT\s+\*\s+FROM\s+users\b/i', $content) && !str_contains($relative, 'admin')) {
            $warnings[] = "{$relative} selects every users column; prefer an explicit field list.";
        }

        if (preg_match('/move_uploaded_file\s*\(/i', $content)
            && !preg_match('/random_bytes\s*\(/i', $content)) {
            $warnings[] = "{$relative} moves uploaded files without an obvious random server filename.";
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Static security audit failed:\n- " . implode("\n- ", array_unique($failures)) . "\n");
    exit(1);
}

fwrite(STDOUT, "Static security audit passed ({$filesScanned} PHP files scanned).\n");

if ($warnings !== []) {
    fwrite(STDOUT, "Warnings:\n- " . implode("\n- ", array_unique($warnings)) . "\n");
}
