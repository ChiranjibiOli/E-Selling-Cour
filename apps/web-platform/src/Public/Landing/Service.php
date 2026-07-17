<?php

declare(strict_types=1);

require_once __DIR__ . '/ApiClient.php';

final class LandingService
{
    public function load(): array
    {
        $client = new LandingCatalogClient();

        try {
            $courses = $client->featuredCourses();
            $categories = $client->categories();
            return [
                'courses' => $courses['data'] ?? [],
                'categories' => $categories['data'] ?? [],
                'catalog_available' => true,
            ];
        } catch (DomainException) {
            // A graceful empty state keeps the public site readable while services restart.
            return ['courses' => [], 'categories' => [], 'catalog_available' => false];
        }
    }
}
