<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/core/Security.php';
require_once __DIR__ . '/../app/helpers/security_helper.php';

$failures = [];
$checks = 0;

$expect = static function (bool $condition, string $message) use (&$failures, &$checks): void {
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
};

$expect(Security::safeInternalPath('student-dashboard.php') === 'student-dashboard.php', 'Valid internal path was rejected.');
$expect(Security::safeInternalPath('course-details.php?slug=php-basics') === 'course-details.php?slug=php-basics', 'Valid internal query was rejected.');
$expect(Security::safeInternalPath('https://evil.example/') === null, 'Absolute redirect URL was accepted.');
$expect(Security::safeInternalPath('//evil.example/') === null, 'Protocol-relative redirect URL was accepted.');
$expect(Security::safeInternalPath('../admin-dashboard.php') === null, 'Traversal redirect was accepted.');
$expect(Security::safeInternalPath("login.php\r\nX-Test: injected") === null, 'Header-injection redirect was accepted.');
$expect(Security::safeInternalPath('admin-dashboard.php', ['student-dashboard.php']) === null, 'Redirect allowlist was bypassed.');

$expect(Security::safeDownloadName("../bad\r\nname.pdf") === 'badname.pdf', 'Download filename was not normalized.');
$expect(Security::safeDownloadName('') === 'download', 'Empty download filename did not use fallback.');

$sanitized = Security::sanitizeRichText('<p onclick="alert(1)">Safe</p><script>alert(1)</script><iframe src="x"></iframe>');
$expect($sanitized === '<p>Safe</p>', 'Rich-text sanitizer retained executable markup.');

$expect(security_safe_external_url('https://example.com/course') === 'https://example.com/course', 'Valid external URL was rejected.');
$expect(security_safe_external_url('http://127.0.0.1/admin') === null, 'Loopback URL was accepted.');
$expect(security_safe_external_url('http://10.0.0.5/private') === null, 'Private IPv4 URL was accepted.');
$expect(security_safe_external_url('http://[::1]/private') === null, 'Loopback IPv6 URL was accepted.');
$expect(security_safe_external_url('https://user:pass@example.com/') === null, 'Credential-bearing URL was accepted.');
$expect(security_safe_external_url('https://example.com:8443/') === null, 'Unexpected external port was accepted.');
$expect(security_safe_external_url('javascript:alert(1)') === null, 'Javascript URL was accepted.');
$expect(security_safe_external_url('https://localhost.example.com/') === 'https://localhost.example.com/', 'Legitimate public hostname was rejected.');

$tempDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'coursehub-security-' . bin2hex(random_bytes(8));
mkdir($tempDirectory, 0700, true);
$filePath = $tempDirectory . DIRECTORY_SEPARATOR . 'allowed.txt';
file_put_contents($filePath, 'safe');

$expect(Security::resolveStoredFile('allowed.txt', [$tempDirectory]) === realpath($filePath), 'Stored file resolver rejected an allowed file.');
$expect(Security::resolveStoredFile('../allowed.txt', [$tempDirectory]) === realpath($filePath), 'Stored file resolver should safely normalize a stored basename.');
$expect(Security::resolveStoredFile('missing.txt', [$tempDirectory]) === null, 'Stored file resolver accepted a missing file.');

@unlink($filePath);
@rmdir($tempDirectory);

if ($failures !== []) {
    fwrite(STDERR, "Security smoke tests failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "Security smoke tests passed ({$checks} checks).\n");
