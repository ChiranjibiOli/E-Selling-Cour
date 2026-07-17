<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Config\Environment;
use CourseHub\WebPlatform\Shared\Http\Request;

return static function (Request $request): void {
    $role = (string) ($_SESSION['role'] ?? '');
    if ($role !== '' && $role !== 'admin') {
        throw new DomainException('The control-room entrance cannot be opened from another portal session.');
    }

    // Optional defense-in-depth. Leave ADMIN_ALLOWED_IPS empty to disable local IP filtering.
    $allowedIps = Environment::csv('ADMIN_ALLOWED_IPS');
    if ($allowedIps !== []) {
        $remoteIp = trim((string) ($request->server['REMOTE_ADDR'] ?? ''));
        if ($remoteIp === '' || !in_array($remoteIp, $allowedIps, true)) {
            throw new DomainException('This network is not allowed to open the control-room entrance.');
        }
    }
};
