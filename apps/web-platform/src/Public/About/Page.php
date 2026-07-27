<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;

final class AboutPage
{
    public static function render(): Response
    {
        return Response::redirect('/#promise');
    }
}
