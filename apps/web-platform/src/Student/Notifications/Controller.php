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
            if (($request->body['action'] ?? '') === 'read_all') {
                $result = $client->post('/api/v1/notifications/read-all', []);
            } else {
                $result = $client->post('/api/v1/notifications/' . (int) ($request->body['notification_id'] ?? 0) . '/read', []);
            }
            $message = (string) ($result['message'] ?? 'Notifications updated.');
        }
        $result = $client->get('/api/v1/notifications?limit=100');
        $notifications = $result['data'] ?? [];
        $unread = (int) ($result['meta']['unread'] ?? 0);
    } catch (DomainException $exception) {
        $notifications = [];
        $unread = 0;
        $message = $exception->getMessage();
        $success = false;
    }
    return StudentNotificationsPage::render($notifications, $unread, $message, $success);
};
