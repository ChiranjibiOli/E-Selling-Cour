<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Security;

use DomainException;

final class FormInput
{
    public static function text(
        array $input,
        string $key,
        string $label,
        int $min,
        int $max,
        bool $required = true,
        ?string $pattern = null,
    ): string {
        $value = self::scalar($input, $key, $label);
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        self::assertUtf8AndControlSafe($value, $label, false);

        if ($value === '') {
            if ($required) {
                throw new DomainException($label . ' is required.');
            }
            return '';
        }

        $length = mb_strlen($value);
        if ($length < $min || $length > $max) {
            throw new DomainException(sprintf('%s must contain between %d and %d characters.', $label, $min, $max));
        }
        if ($pattern !== null && preg_match($pattern, $value) !== 1) {
            throw new DomainException($label . ' contains unsupported characters.');
        }

        return $value;
    }

    public static function multiline(
        array $input,
        string $key,
        string $label,
        int $min,
        int $max,
        bool $required = true,
    ): string {
        $value = self::scalar($input, $key, $label);
        $value = str_replace(["\r\n", "\r"], "\n", trim($value));
        self::assertUtf8AndControlSafe($value, $label, true);

        if ($value === '') {
            if ($required) {
                throw new DomainException($label . ' is required.');
            }
            return '';
        }

        $length = mb_strlen($value);
        if ($length < $min || $length > $max) {
            throw new DomainException(sprintf('%s must contain between %d and %d characters.', $label, $min, $max));
        }

        return $value;
    }

    public static function integer(array $input, string $key, string $label, int $min, int $max): int
    {
        $value = self::scalar($input, $key, $label);
        if (preg_match('/^[0-9]+$/', $value) !== 1) {
            throw new DomainException($label . ' must contain numbers only.');
        }
        $number = filter_var($value, FILTER_VALIDATE_INT);
        if ($number === false || $number < $min || $number > $max) {
            throw new DomainException(sprintf('%s must be between %d and %d.', $label, $min, $max));
        }
        return (int) $number;
    }

    public static function decimal(
        array $input,
        string $key,
        string $label,
        float $min,
        float $max,
        bool $required = true,
        int $scale = 2,
    ): ?float {
        $value = trim(self::scalar($input, $key, $label));
        if ($value === '') {
            if ($required) {
                throw new DomainException($label . ' is required.');
            }
            return null;
        }

        $pattern = '/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,' . max(0, $scale) . '})?$/';
        if (preg_match($pattern, $value) !== 1 || !is_numeric($value)) {
            throw new DomainException($label . ' must be a valid number with no more than ' . $scale . ' decimal places.');
        }
        $number = (float) $value;
        if (!is_finite($number) || $number < $min || $number > $max) {
            throw new DomainException($label . ' is outside the permitted range.');
        }
        return $number;
    }

    public static function enum(array $input, string $key, string $label, array $allowed, string $default = ''): string
    {
        $value = strtolower(trim(self::scalar($input, $key, $label, $default)));
        if (!in_array($value, $allowed, true)) {
            throw new DomainException('Choose a valid ' . strtolower($label) . '.');
        }
        return $value;
    }

    public static function httpsUrl(array $input, string $key, string $label, int $max = 500, bool $required = false): string
    {
        $value = trim(self::scalar($input, $key, $label));
        if ($value === '') {
            if ($required) {
                throw new DomainException($label . ' is required.');
            }
            return '';
        }
        self::assertUtf8AndControlSafe($value, $label, false);
        if (mb_strlen($value) > $max || filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new DomainException('Enter a valid ' . strtolower($label) . '.');
        }
        $parts = parse_url($value);
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || trim((string) ($parts['host'] ?? '')) === ''
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new DomainException($label . ' must use a normal HTTPS address without embedded credentials.');
        }
        return $value;
    }

    public static function listText(array $input, string $key, string $label, int $maxItems = 30, int $maxItemLength = 300): string
    {
        $value = self::multiline($input, $key, $label, 0, $maxItems * ($maxItemLength + 1), false);
        if ($value === '') {
            return '';
        }
        $items = [];
        foreach (explode("\n", $value) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (mb_strlen($line) > $maxItemLength) {
                throw new DomainException($label . ' entries must be ' . $maxItemLength . ' characters or fewer.');
            }
            $items[$line] = true;
        }
        if (count($items) > $maxItems) {
            throw new DomainException($label . ' may contain at most ' . $maxItems . ' entries.');
        }
        return implode("\n", array_keys($items));
    }

    private static function scalar(array $input, string $key, string $label, string $default = ''): string
    {
        $value = $input[$key] ?? $default;
        if (!is_scalar($value) && $value !== null) {
            throw new DomainException($label . ' must be a single value.');
        }
        return (string) $value;
    }

    private static function assertUtf8AndControlSafe(string $value, string $label, bool $allowNewlines): void
    {
        if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
            throw new DomainException($label . ' contains invalid text encoding.');
        }
        $controlPattern = $allowNewlines ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u';
        if (preg_match($controlPattern, $value) === 1) {
            throw new DomainException($label . ' contains unsupported control characters.');
        }
    }
}
