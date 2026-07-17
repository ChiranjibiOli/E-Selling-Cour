<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Security;

use DomainException;

final class Csrf
{
    public static function token(): string
    {
        if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '">';
    }

    public static function assertValid(string $provided): void
    {
        if ($provided === '' || !hash_equals(self::token(), $provided)) {
            throw new DomainException('The form expired. Refresh the page and try again.');
        }
    }
}
