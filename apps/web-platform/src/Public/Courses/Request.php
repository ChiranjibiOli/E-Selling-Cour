<?php

declare(strict_types=1);

final class CourseCatalogRequest
{
    public function __construct(
        public readonly string $query,
        public readonly string $category,
        public readonly string $level,
    ) {
    }

    public static function from(array $query): self
    {
        return new self(
            trim((string) ($query['q'] ?? '')),
            trim((string) ($query['category'] ?? '')),
            trim((string) ($query['level'] ?? '')),
        );
    }
}
