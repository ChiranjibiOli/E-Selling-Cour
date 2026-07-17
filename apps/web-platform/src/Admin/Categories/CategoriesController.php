<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Admin\Categories;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class CategoriesController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new CategoriesPage())->render((new CategoriesService())->load($request));
    }
}
