<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Routing\HouseRouter;

require dirname(__DIR__) . '/src/bootstrap.php';

try {
    $request = Request::capture();
} catch (InvalidArgumentException $exception) {
    Response::html(
        '<h1>Invalid request</h1><p>'
        . htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</p>',
        400,
    )->send();
    exit;
}

$response = (new HouseRouter())->dispatch($request);
$response->send();
