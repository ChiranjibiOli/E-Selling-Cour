<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Routing;

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Room\RoomRegistry;
use CourseHub\WebPlatform\Shared\Session\AuthSession;
use CourseHub\WebPlatform\Shared\Session\SessionEndedException;
use CourseHub\WebPlatform\Shared\Session\SessionGuard;
use CourseHub\WebPlatform\Shared\Session\SessionValidationUnavailableException;

final class HouseRouter
{
    public function dispatch(Request $request): Response
    {
        if ($request->path === '/health') {
            return Response::json(['status' => 'ok', 'app' => 'web-platform', 'architecture' => 'house-compound']);
        }

        if ($request->path === '/session-status' && $request->method === 'GET') {
            return $this->sessionStatus();
        }

        foreach (RoomRegistry::all() as $key => $metadata) {
            if ($metadata['path'] !== $request->path || !in_array($request->method, explode('|', $metadata['methods']), true)) {
                continue;
            }

            $requiredRole = (string) ($metadata['role'] ?? 'guest');
            if (in_array($requiredRole, ['student', 'instructor', 'admin'], true)) {
                try {
                    SessionGuard::verify($requiredRole);
                } catch (SessionEndedException $exception) {
                    return Response::redirect($exception->loginPath() . '?session=ended');
                } catch (SessionValidationUnavailableException $exception) {
                    return Response::html(
                        '<h1>Session verification unavailable</h1><p>'
                        . htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                        . '</p>',
                        503,
                    );
                }
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

    private function sessionStatus(): Response
    {
        $role = AuthSession::role();
        if (!in_array($role, ['student', 'instructor', 'admin'], true) || AuthSession::token() === '') {
            return Response::json([
                'active' => false,
                'login_url' => SessionGuard::loginPath($role),
                'message' => 'No active CourseHub session was found.',
            ], 401);
        }

        try {
            $session = SessionGuard::verify($role);
            return Response::json([
                'active' => true,
                'role' => $role,
                'expires_at' => (string) ($session['expires_at'] ?? ''),
            ]);
        } catch (SessionEndedException $exception) {
            return Response::json([
                'active' => false,
                'login_url' => $exception->loginPath(),
                'message' => $exception->getMessage(),
            ], 401);
        } catch (SessionValidationUnavailableException $exception) {
            return Response::json([
                'active' => true,
                'verification_unavailable' => true,
                'message' => $exception->getMessage(),
            ], 503);
        }
    }
}
