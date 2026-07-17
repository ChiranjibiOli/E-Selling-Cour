<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;

final class CourseCatalogApi
{
    public function search(CourseCatalogRequest $request): array
    {
        $query = http_build_query(array_filter([
            'q' => $request->query,
            'category' => $request->category,
            'level' => $request->level,
            'limit' => 24,
        ], static fn (mixed $value): bool => $value !== ''));

        return (new ApiClient())->get('/api/v1/courses' . ($query !== '' ? '?' . $query : ''));
    }

    public function categories(): array
    {
        return (new ApiClient())->get('/api/v1/categories?limit=30');
    }
}
