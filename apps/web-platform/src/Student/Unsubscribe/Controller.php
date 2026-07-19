<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;
use CourseHub\WebPlatform\Shared\Security\Csrf;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    RoomRuntime::authorize(__DIR__, $request);
    $client = new ApiClient();
    $message = '';
    $success = true;
    try {
        if ($request->method === 'POST') {
            Csrf::assertValid((string) ($request->body['_token'] ?? ''));
            $enrollmentId = (int) ($request->body['enrollment_id'] ?? 0);
            $result = $client->post('/api/v1/enrollments/' . $enrollmentId . '/unsubscribe', [
                'reason' => trim((string) ($request->body['reason'] ?? '')),
            ]);
            $message = (string) ($result['message'] ?? 'Request submitted.');
        }
        $enrollments = $client->get('/api/v1/enrollments/mine')['data'] ?? [];
        $requests = $client->get('/api/v1/enrollments/unsubscribe/mine')['data'] ?? [];
        return StudentUnsubscribePage::render($enrollments, $requests, $message, $success);
    } catch (DomainException $exception) {
        try {
            $enrollments = $client->get('/api/v1/enrollments/mine')['data'] ?? [];
            $requests = $client->get('/api/v1/enrollments/unsubscribe/mine')['data'] ?? [];
        } catch (DomainException) {
            $enrollments = [];
            $requests = [];
        }
        return StudentUnsubscribePage::render($enrollments, $requests, $exception->getMessage(), false);
    }
};
