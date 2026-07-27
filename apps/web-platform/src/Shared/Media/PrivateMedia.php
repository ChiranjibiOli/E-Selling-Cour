<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Media;

use CourseHub\WebPlatform\Shared\Http\Response;
use DomainException;
use finfo;

final class PrivateMedia
{
    /** @param list<string> $allowedBuckets */
    public static function response(string $storedPath, array $allowedBuckets): Response
    {
        $storedPath = trim($storedPath);
        if ($storedPath === '' || preg_match('#^[a-z0-9-]+(?:/[a-z0-9-]+)*/[a-f0-9]{40}\.[a-z0-9]{2,5}$#', $storedPath) !== 1) {
            throw new DomainException('The requested private file is unavailable.');
        }

        $bucketAllowed = false;
        foreach ($allowedBuckets as $bucket) {
            $bucket = trim($bucket, '/');
            if ($bucket !== '' && str_starts_with($storedPath, $bucket . '/')) {
                $bucketAllowed = true;
                break;
            }
        }
        if (!$bucketAllowed) {
            throw new DomainException('The requested private file is unavailable.');
        }

        $storageRoot = rtrim((string) (getenv('COURSEHUB_STORAGE_PATH') ?: COURSEHUB_REPOSITORY_ROOT . '/storage'), '/\\');
        $root = realpath($storageRoot);
        $file = realpath($storageRoot . '/' . $storedPath);
        if ($root === false || $file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
            throw new DomainException('The requested private file is unavailable.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (!is_string($mime) || !in_array($mime, $allowedMimeTypes, true)) {
            throw new DomainException('The requested private file type is not allowed.');
        }

        $body = file_get_contents($file);
        if (!is_string($body)) {
            throw new DomainException('The requested private file could not be read.');
        }

        return Response::binary($body, $mime);
    }
}
