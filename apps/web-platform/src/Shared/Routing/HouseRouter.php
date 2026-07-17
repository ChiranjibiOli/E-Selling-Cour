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
            return Response::json([
                'status' => 'ok',
                'app' => 'web-platform',
                'architecture' => 'house-compound',
            ]);
        }

        $assetResponse = $this->roomAsset($request->path);
        if ($assetResponse !== null) {
            return $assetResponse;
        }

        foreach (RoomRegistry::all() as $key => $metadata) {
            $allowedMethods = explode('|', (string) $metadata['methods']);
            if ((string) $metadata['path'] !== $request->path
                || !in_array($request->method, $allowedMethods, true)
            ) {
                continue;
            }

            $directory = COURSEHUB_WEB_ROOT . '/src/' . $key;
            try {
                $middleware = require $directory . '/Middleware.php';
                $middleware($request);

                $controller = require $directory . '/Controller.php';
                return $controller($request);
            } catch (\DomainException $exception) {
                return Response::html(
                    '<h1>Access denied</h1><p>'
                    . htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '</p>',
                    403
                );
            } catch (\Throwable $exception) {
                error_log('Room dispatch failure: ' . $exception->getMessage());
                return Response::html('<h1>Room unavailable</h1>', 500);
            }
        }

        return Response::html('<h1>Room not found</h1>', 404);
    }

    private function roomAsset(string $path): ?Response
    {
        if (preg_match(
            '#^/room-assets/(Public|Student|Instructor|Admin)/([A-Za-z0-9]+)/page\.(css|js)$#',
            $path,
            $matches
        ) !== 1) {
            return null;
        }

        $file = COURSEHUB_WEB_ROOT . '/src/' . $matches[1] . '/' . $matches[2]
            . '/Assets/page.' . $matches[3];
        if (!is_file($file)) {
            return Response::html('Asset not found.', 404);
        }

        $contentType = $matches[3] === 'css'
            ? 'text/css; charset=utf-8'
            : 'application/javascript; charset=utf-8';

        return new Response(
            (string) file_get_contents($file),
            200,
            [
                'Content-Type' => $contentType,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'public, max-age=300',
            ]
        );
    }
}
