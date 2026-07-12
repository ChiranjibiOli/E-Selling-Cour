<?php

declare(strict_types=1);

if (!function_exists('security_text_length')) {
    function security_text_length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}

if (!function_exists('security_clean_text')) {
    function security_clean_text(mixed $value, int $maxLength, bool $multiline = false): string
    {
        if (!is_scalar($value) && $value !== null) {
            return '';
        }

        $text = strip_tags((string) $value);
        $text = str_replace("\0", '', $text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = (string) preg_replace('/[\x{0001}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}]/u', '', $text);

        if ($multiline) {
            $text = trim((string) preg_replace('/\n{3,}/u', "\n\n", $text));
        } else {
            $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        }

        if (security_text_length($text) > $maxLength) {
            return '';
        }

        return $text;
    }
}

if (!function_exists('security_safe_external_url')) {
    function security_safe_external_url(mixed $value): ?string
    {
        if (!is_scalar($value) && $value !== null) {
            return null;
        }

        $url = trim((string) $value);
        if ($url === '') {
            return '';
        }

        if (security_text_length($url) > 2048 || str_contains($url, "\r") || str_contains($url, "\n")) {
            return null;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $port = isset($parts['port']) ? (int) $parts['port'] : null;

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        if ($port !== null && !in_array($port, [80, 443], true)) {
            return null;
        }

        if (
            $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
            || str_ends_with($host, '.lan')
        ) {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return null;
            }
        } else {
            if (!str_contains($host, '.')) {
                return null;
            }

            if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
                return null;
            }
        }

        return $url;
    }
}
