<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Routing;

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Room\RoomRegistry;

final class HouseRouter
{
    public function dispatch(Request $request): Response
    {
        if ($request->path === '/health') {
            return Response::json(['status' => 'ok', 'app' => 'web-platform', 'architecture' => 'house-compound']);
        }

        foreach (RoomRegistry::all() as $key => $metadata) {
            if ($metadata['path'] !== $request->path || !in_array($request->method, explode('|', $metadata['methods']), true)) {
                continue;
            }

            $directory = COURSEHUB_WEB_ROOT . '/src/' . $key;
            $middlewareFile = (string) ($metadata['middleware_file'] ?? 'Middleware.php');
            $controllerFile = (string) ($metadata['controller_file'] ?? 'Controller.php');

            try {
                $middleware = require $directory . '/' . $middlewareFile;
                $middleware($request);
                $controller = require $directory . '/' . $controllerFile;
                return $controller($request);
            } catch (\DomainException $exception) {
                return Response::html('<h1>Access denied</h1><p>' . htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>', 403);
            } catch (\Throwable $exception) {
                error_log('Web room failure [' . $key . ']: ' . $exception->getMessage());
                return Response::html('<h1>Room unavailable</h1><p>The request could not be completed.</p>', 500);
            }
        }

        return Response::html('<h1>Room not found</h1>', 404);
    }
}
