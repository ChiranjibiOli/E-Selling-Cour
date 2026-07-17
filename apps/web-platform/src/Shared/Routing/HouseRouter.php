<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Routing;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Contracts\RoomMiddleware;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class HouseRouter
{
    public function dispatch(Request $request): Response
    {
        if ($request->path === '/health') {
            return Response::json(['status' => 'ok', 'app' => 'web-platform', 'architecture' => 'house-compound']);
        }

        foreach ($this->routes() as $route) {
            if ($route['method'] !== $request->method || $route['path'] !== $request->path) {
                continue;
            }

            $middleware = new $route['middleware']();
            if (!$middleware instanceof RoomMiddleware) {
                return Response::html('<h1>Invalid room middleware</h1>', 500);
            }
            $blocked = $middleware->check($request);
            if ($blocked !== null) {
                return $blocked;
            }

            $controller = new $route['controller']();
            if (!$controller instanceof RoomController) {
                return Response::html('<h1>Invalid room controller</h1>', 500);
            }
            return $controller->handle($request);
        }

        return Response::html('<h1>Room not found</h1><p>No route is registered for ' . htmlspecialchars($request->path) . '.</p>', 404);
    }

    private function routes(): array
    {
        return array_merge(
            require COURSEHUB_WEB_ROOT . '/src/Public/routes.php',
            require COURSEHUB_WEB_ROOT . '/src/Student/routes.php',
            require COURSEHUB_WEB_ROOT . '/src/Instructor/routes.php',
            require COURSEHUB_WEB_ROOT . '/src/Admin/routes.php',
        );
    }
}
