<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\Faq;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class FaqController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new FaqPage())->render((new FaqService())->load($request));
    }
}
