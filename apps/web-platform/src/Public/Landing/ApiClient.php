<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;

final class LandingCatalogClient
{
    public function featuredCourses(): array
    {
        return (new ApiClient())->get('/api/v1/courses?featured=1&limit=6');
    }

    public function categories(): array
    {
        return (new ApiClient())->get('/api/v1/categories?limit=8');
    }
}
