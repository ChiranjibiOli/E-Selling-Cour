<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;

final class LoginPage
{
    public static function render(bool $sessionEnded = false): Response
    {
        return Response::redirect('/learn/sign-in' . ($sessionEnded ? '?session=ended' : ''));
    }
}
