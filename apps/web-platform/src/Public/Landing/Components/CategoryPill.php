<?php

declare(strict_types=1);

final class LandingCategoryPill
{
    public static function render(array $category): string
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<a class="category-pill" href="/courses?category=' . rawurlencode((string) ($category['slug'] ?? '')) . '">'
            . '<strong>' . $e($category['name'] ?? 'Category') . '</strong><span>Explore courses</span></a>';
    }
}
