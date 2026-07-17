<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\ContactMessages;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class ContactMessagesController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new ContactMessagesPage())->render((new ContactMessagesService())->load($request));
    }
}
