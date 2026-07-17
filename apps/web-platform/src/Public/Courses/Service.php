<?php

declare(strict_types=1);

require_once __DIR__ . '/ApiClient.php';

final class CourseCatalogService
{
    public function load(CourseCatalogRequest $request): array
    {
        $client = new CourseCatalogApi();
        try {
            return [
                'courses' => $client->search($request)['data'] ?? [],
                'categories' => $client->categories()['data'] ?? [],
                'available' => true,
            ];
        } catch (DomainException) {
            return ['courses' => [], 'categories' => [], 'available' => false];
        }
    }
}
