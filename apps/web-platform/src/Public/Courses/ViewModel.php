<?php

declare(strict_types=1);

final class CourseCatalogViewModel
{
    public function __construct(
        public readonly CourseCatalogRequest $filters,
        public readonly array $courses,
        public readonly array $categories,
        public readonly bool $available,
    ) {
    }
}
