<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\Settings;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class SettingsController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new SettingsPage())->render((new SettingsService())->load($request));
    }
}
