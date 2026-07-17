<?php

declare(strict_types=1);

final class LandingViewModel
{
    public function __construct(
        public readonly array $courses,
        public readonly array $categories,
        public readonly bool $catalogAvailable,
    ) {
    }

    public static function from(array $data): self
    {
        return new self(
            array_values(is_array($data['courses'] ?? null) ? $data['courses'] : []),
            array_values(is_array($data['categories'] ?? null) ? $data['categories'] : []),
            (bool) ($data['catalog_available'] ?? false),
        );
    }
}
