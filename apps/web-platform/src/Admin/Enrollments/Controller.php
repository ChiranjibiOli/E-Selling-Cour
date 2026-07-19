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
            $requestId = (int) ($request->body['request_id'] ?? 0);
            $decision = (string) ($request->body['decision'] ?? '');
            if (!in_array($decision, ['approve', 'reject'], true)) {
                throw new DomainException('Choose a valid access-request decision.');
            }
            $result = $client->post('/api/v1/enrollments/unsubscribe/' . $requestId . '/' . $decision, []);
            $message = (string) ($result['message'] ?? 'Access request processed.');
        }
        $enrollments = $client->get('/api/v1/enrollments')['data'] ?? [];
        $requests = $client->get('/api/v1/enrollments/unsubscribe/pending')['data'] ?? [];
        return AdminEnrollmentsPage::render($enrollments, $requests, $message, $success);
    } catch (DomainException $exception) {
        try {
            $enrollments = $client->get('/api/v1/enrollments')['data'] ?? [];
            $requests = $client->get('/api/v1/enrollments/unsubscribe/pending')['data'] ?? [];
        } catch (DomainException) {
            $enrollments = [];
            $requests = [];
        }
        return AdminEnrollmentsPage::render($enrollments, $requests, $exception->getMessage(), false);
    }
};
