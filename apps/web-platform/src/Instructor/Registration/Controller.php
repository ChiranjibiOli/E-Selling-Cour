<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

return static function (Request $request) {
    return Response::redirect('/register/instructor');
};
