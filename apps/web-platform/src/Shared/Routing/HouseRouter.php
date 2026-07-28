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

        $canonicalAlias = $this->canonicalAlias($request);
        if ($canonicalAlias !== null) {
            return Response::redirect($canonicalAlias);
        }

        foreach (RoomRegistry::all() as $key => $metadata) {
            if ($metadata['path'] !== $request->path || !in_array($request->method, explode('|', $metadata['methods']), true)) {
                continue;
            }

            $requiredRole = (string) ($metadata['role'] ?? 'guest');
            if ($requiredRole === 'guest' && $key !== 'Public/Logout') {
                $signedInRedirect = $this->signedInRedirect($request);
                if ($signedInRedirect !== null) {
                    return $signedInRedirect;
                }
            }

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

    private function canonicalAlias(Request $request): ?string
    {
        if ($request->method !== 'GET') {
            return null;
        }

        $target = match ($request->path) {
            '/student/all-course',
            '/student/all-courses',
            '/student/course',
            '/student/course-catalogue',
            '/student/courses.php' => '/student/courses',
            default => null,
        };

        if ($target === null) {
            return null;
        }

        $query = array_filter(
            $request->query,
            static fn (mixed $value): bool => is_scalar($value) && trim((string) $value) !== '',
        );

        return $target . ($query !== [] ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
    }

    private function signedInRedirect(Request $request): ?Response
    {
        $role = AuthSession::role();
        if (!in_array($role, ['student', 'instructor', 'admin'], true) || AuthSession::token() === '') {
            return null;
        }

        if ($role === 'student') {
            if (in_array($request->path, ['/courses', '/search'], true)) {
                $filters = array_filter([
                    'q' => mb_substr(trim((string) ($request->query['q'] ?? '')), 0, 120),
                    'category' => mb_substr(trim((string) ($request->query['category'] ?? '')), 0, 120),
                    'level' => mb_substr(trim((string) ($request->query['level'] ?? '')), 0, 30),
                ], static fn (string $value): bool => $value !== '');
                return Response::redirect('/student/courses' . ($filters !== [] ? '?' . http_build_query($filters, '', '&', PHP_QUERY_RFC3986) : ''));
            }

            if ($request->path === '/course') {
                $courseId = filter_var($request->query['id'] ?? 0, FILTER_VALIDATE_INT);
                return Response::redirect('/student/courses' . ($courseId !== false && $courseId > 0 ? '?course=' . (int) $courseId : ''));
            }

            return Response::redirect('/student/dashboard');
        }

        return Response::redirect($role === 'instructor' ? '/instructor/dashboard' : '/admin/dashboard');
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
