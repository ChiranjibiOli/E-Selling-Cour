<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Media;

use DomainException;
use finfo;

final class SecureUpload
{
    /** @param array<string,mixed> $file @param array<string,string> $allowedMimeTypes */
    public static function store(array $file, string $bucket, array $allowedMimeTypes, int $maxBytes): ?string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new DomainException('The uploaded file could not be received.');
        }

        $temporaryPath = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($temporaryPath === '' || !is_uploaded_file($temporaryPath) || $size < 1 || $size > $maxBytes) {
            throw new DomainException('The uploaded file is invalid or exceeds the allowed size.');
        }
        if (preg_match('/^[a-z0-9-]{3,80}$/', $bucket) !== 1) {
            throw new DomainException('Invalid upload destination.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        if (!is_string($mime) || !isset($allowedMimeTypes[$mime])) {
            throw new DomainException('This file type is not allowed.');
        }

        $storageRoot = rtrim((string) (getenv('COURSEHUB_STORAGE_PATH') ?: COURSEHUB_REPOSITORY_ROOT . '/storage'), '/\\');
        $directory = $storageRoot . '/' . $bucket;
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new DomainException('The private upload directory is unavailable.');
        }

        $filename = bin2hex(random_bytes(20)) . '.' . $allowedMimeTypes[$mime];
        $destination = $directory . '/' . $filename;
        if (!move_uploaded_file($temporaryPath, $destination)) {
            throw new DomainException('The uploaded file could not be stored safely.');
        }
        chmod($destination, 0640);

        return $bucket . '/' . $filename;
    }
}
