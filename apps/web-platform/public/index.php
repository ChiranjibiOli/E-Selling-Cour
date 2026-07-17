<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Routing\HouseRouter;

require dirname(__DIR__) . '/src/bootstrap.php';

$request = Request::capture();
$response = (new HouseRouter())->dispatch($request);
$response->send();
