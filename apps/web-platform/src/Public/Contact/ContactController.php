<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\Contact;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class ContactController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new ContactPage())->render((new ContactService())->load($request));
    }
}
