<?php

declare(strict_types=1);

return [
    'Route.php' => "<?php\n\ndeclare(strict_types=1);\n\nuse CourseHub\\Services\\Shared\\FeatureRuntime;\n\nreturn FeatureRuntime::metadata(__DIR__);\n",
    'Controller.php' => "<?php\n\ndeclare(strict_types=1);\n\nuse CourseHub\\Services\\Shared\\FeatureRuntime;\n\nreturn static fn (array \$request): array => FeatureRuntime::handle(__DIR__, \$request);\n",
    'Middleware.php' => "<?php\n\ndeclare(strict_types=1);\n\nreturn static fn (array \$request): array => \$request;\n",
    'Request.php' => "<?php\n\ndeclare(strict_types=1);\n\nreturn static fn (array \$input): array => \$input;\n",
    'Validator.php' => "<?php\n\ndeclare(strict_types=1);\n\nreturn static fn (array \$input): array => ['valid' => true, 'errors' => [], 'data' => \$input];\n",
    'Policy.php' => "<?php\n\ndeclare(strict_types=1);\n\nreturn static fn (array \$context): bool => true;\n",
    'Handler.php' => "<?php\n\ndeclare(strict_types=1);\n\nuse CourseHub\\Services\\Shared\\FeatureRuntime;\n\nreturn static fn (array \$request): array => FeatureRuntime::handle(__DIR__, \$request);\n",
    'Repository.php' => "<?php\n\ndeclare(strict_types=1);\n\nreturn static fn (): array => [];\n",
    'Response.php' => "<?php\n\ndeclare(strict_types=1);\n\nreturn static fn (array \$data): array => \$data;\n",
    'Event.php' => "<?php\n\ndeclare(strict_types=1);\n\nreturn static fn (array \$payload): array => \$payload;\n",
];
