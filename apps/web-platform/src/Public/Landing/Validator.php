<?php

declare(strict_types=1);

final class LandingValidator
{
    public static function validate(LandingRequest $request): void
    {
        // Landing currently has no user-controlled business input.
    }
}
