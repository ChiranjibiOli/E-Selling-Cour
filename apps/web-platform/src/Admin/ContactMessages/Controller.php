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
            $result = $client->post('/api/v1/notifications/contact/' . (int) ($request->body['message_id'] ?? 0), ['status' => (string) ($request->body['status'] ?? 'read')]);
            $message = (string) ($result['message'] ?? 'Message updated.');
        }
        $messages = $client->get('/api/v1/notifications/contact')['data'] ?? [];
    } catch (DomainException $exception) {
        $messages = [];
        $message = $exception->getMessage();
        $success = false;
    }
    return AdminContactMessagesPage::render($messages, $message, $success);
};
