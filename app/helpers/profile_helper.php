<?php

declare(strict_types=1);

if (!function_exists('profile_h')) {
    function profile_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('profile_length')) {
    function profile_length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}

if (!function_exists('profile_clean_text')) {
    function profile_clean_text(mixed $value, bool $multiline = false): string
    {
        if (!is_scalar($value) && $value !== null) {
            return '';
        }

        $text = strip_tags((string) $value);
        $text = str_replace("\0", '', $text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = (string) preg_replace('/[\x{0001}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}]/u', '', $text);

        if ($multiline) {
            $text = (string) preg_replace('/[\t ]+/u', ' ', $text);
            $text = (string) preg_replace('/\n{3,}/u', "\n\n", $text);
            return trim($text);
        }

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}

if (!function_exists('profile_valid_name')) {
    function profile_valid_name(string $name): bool
    {
        return profile_length($name) >= 2
            && profile_length($name) <= 100
            && preg_match("/^[\p{L}\p{M}][\p{L}\p{M}\s.'’\-]{1,99}$/u", $name) === 1;
    }
}

if (!function_exists('profile_normalize_phone')) {
    function profile_normalize_phone(mixed $phone): string
    {
        return (string) preg_replace('/\D+/', '', (string) $phone);
    }
}

if (!function_exists('profile_valid_phone')) {
    function profile_valid_phone(string $phone, bool $required = false): bool
    {
        if ($phone === '') {
            return !$required;
        }

        return preg_match('/^\d{10,15}$/', $phone) === 1;
    }
}

if (!function_exists('profile_photo_directory')) {
    function profile_photo_directory(): string
    {
        return dirname(__DIR__, 2) . '/public/assets/uploads/profile_photos';
    }
}

if (!function_exists('profile_photo_public_path')) {
    function profile_photo_public_path(?string $fileName): string
    {
        $safeName = basename((string) $fileName);
        if ($safeName === '' || $safeName === '.' || $safeName === '..') {
            return '';
        }

        $fullPath = profile_photo_directory() . DIRECTORY_SEPARATOR . $safeName;
        if (!is_file($fullPath)) {
            return '';
        }

        return 'assets/uploads/profile_photos/' . rawurlencode($safeName);
    }
}

if (!function_exists('profile_delete_photo_file')) {
    function profile_delete_photo_file(?string $fileName): void
    {
        $safeName = basename((string) $fileName);
        if ($safeName === '' || $safeName === '.' || $safeName === '..') {
            return;
        }

        $path = profile_photo_directory() . DIRECTORY_SEPARATOR . $safeName;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

if (!function_exists('profile_upload_photo')) {
    function profile_upload_photo(array $file, array &$errors): ?string
    {
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($errorCode !== UPLOAD_ERR_OK) {
            $errors[] = 'The profile photo upload failed.';
            return null;
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            $errors[] = 'The profile photo upload could not be verified.';
            return null;
        }

        if ($size < 1 || $size > 2 * 1024 * 1024) {
            $errors[] = 'The profile photo must be a non-empty image no larger than 2 MB.';
            return null;
        }

        $imageInfo = @getimagesize($tmpName);
        if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
            $errors[] = 'The uploaded profile photo is not a valid image.';
            return null;
        }

        $width = (int) $imageInfo[0];
        $height = (int) $imageInfo[1];

        if ($width < 160 || $height < 160) {
            $errors[] = 'The profile photo must be at least 160 × 160 pixels.';
            return null;
        }

        if ($width > 6000 || $height > 6000 || ($width * $height) > 30_000_000) {
            $errors[] = 'The profile photo dimensions are too large.';
            return null;
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!is_string($mime) || !isset($allowed[$mime])) {
            $errors[] = 'The profile photo must contain a genuine JPG, PNG, or WebP image.';
            return null;
        }

        $directory = profile_photo_directory();
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            $errors[] = 'The profile photo directory could not be created.';
            return null;
        }

        $fileName = bin2hex(random_bytes(24)) . '.' . $allowed[$mime];
        $destination = $directory . DIRECTORY_SEPARATOR . $fileName;

        if (!move_uploaded_file($tmpName, $destination)) {
            $errors[] = 'The profile photo could not be saved.';
            return null;
        }

        @chmod($destination, 0644);
        return $fileName;
    }
}

if (!function_exists('profile_initial')) {
    function profile_initial(string $name): string
    {
        $first = function_exists('mb_substr')
            ? mb_substr(trim($name), 0, 1, 'UTF-8')
            : substr(trim($name), 0, 1);

        return strtoupper($first !== '' ? $first : 'U');
    }
}
