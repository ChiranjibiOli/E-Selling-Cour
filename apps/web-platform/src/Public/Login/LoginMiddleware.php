<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\Login;

use CourseHub\WebPlatform\Shared\Security\AllowGuestMiddleware;

final class LoginMiddleware extends AllowGuestMiddleware
{
}
