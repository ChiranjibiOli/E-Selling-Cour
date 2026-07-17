<?php

declare(strict_types=1);

final class CourseCatalogValidator
{
    public static function validate(CourseCatalogRequest $request): void
    {
        if (mb_strlen($request->query) > 120 || mb_strlen($request->category) > 120) {
            throw new DomainException('The catalog filter is too long.');
        }
        if ($request->level !== '' && !in_array($request->level, ['beginner','intermediate','advanced'], true)) {
            throw new DomainException('Invalid course level filter.');
        }
    }
}
