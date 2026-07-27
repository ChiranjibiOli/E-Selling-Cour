<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

return static function (Request $request): Response {
    $query = trim((string) ($request->query['q'] ?? $request->query['query'] ?? ''));
    $destination = '/courses';
    if ($query !== '') {
        $destination .= '?' . http_build_query(['q' => $query]);
    }

    return Response::redirect($destination);
};
