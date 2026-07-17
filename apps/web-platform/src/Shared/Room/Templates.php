<?php

declare(strict_types=1);

return [
    'Route.php' => <<<'PHP'
<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Room\RoomRuntime;

return RoomRuntime::metadata(__DIR__);
PHP,
    'Controller.php' => <<<'PHP'
<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;

return static function (Request $request) {
    RoomRuntime::authorize(__DIR__, $request);
    $model = RoomRuntime::load(__DIR__, $request);
    return RoomRuntime::render(__DIR__, $model);
};
PHP,
    'Middleware.php' => <<<'PHP'
<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;

return static function (Request $request): void {
    RoomRuntime::authorize(__DIR__, $request);
};
PHP,
    'Request.php' => <<<'PHP'
<?php

declare(strict_types=1);

return static fn (array $input): array => $input;
PHP,
    'Validator.php' => <<<'PHP'
<?php

declare(strict_types=1);

return static fn (array $input): array => ['valid' => true, 'errors' => [], 'data' => $input];
PHP,
    'Service.php' => <<<'PHP'
<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;

return static fn (Request $request): array => RoomRuntime::load(__DIR__, $request);
PHP,
    'ApiClient.php' => <<<'PHP'
<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Room\RoomRuntime;

return static fn (): array => RoomRuntime::metadata(__DIR__);
PHP,
    'ViewModel.php' => <<<'PHP'
<?php

declare(strict_types=1);

return static fn (array $data): array => $data;
PHP,
    'Page.php' => <<<'PHP'
<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Room\RoomRuntime;

return static fn (array $model) => RoomRuntime::render(__DIR__, $model);
PHP,
];
