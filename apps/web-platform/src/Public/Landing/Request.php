<?php

declare(strict_types=1);

final class LandingRequest
{
    public static function from(array $query): self
    {
        return new self();
    }
}
