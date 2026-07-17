<?php

declare(strict_types=1);

use CourseHub\Services\Shared\FeatureRuntime;

return static fn (array $request): array => FeatureRuntime::handle(__DIR__, $request);
