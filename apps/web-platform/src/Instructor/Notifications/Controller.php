<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Security\FormInput;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    RoomRuntime::authorize(__DIR__, $request);
    $client = new ApiClient();
    $message = '';
    $success = true;
    try {
        if ($request->method === 'POST') {
            Csrf::assertValid((string) ($request->body['_token'] ?? ''));
            $action = FormInput::enum($request->body, 'action', 'Notification action', ['read_one', 'read_all', 'delete_one', 'delete_all'], 'read_one');
            if ($action === 'read_all') {
                $result = $client->post('/api/v1/notifications/read-all', []);
            } elseif ($action === 'delete_all') {
                $result = $client->post('/api/v1/notifications/delete-all', []);
            } else {
                $notificationId = FormInput::integer($request->body, 'notification_id', 'Notification', 1, PHP_INT_MAX);
                $endpoint = $action === 'delete_one'
                    ? '/api/v1/notifications/' . $notificationId . '/delete'
                    : '/api/v1/notifications/' . $notificationId . '/read';
                $result = $client->post($endpoint, []);
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
    return InstructorNotificationsPage::render(is_array($notifications) ? $notifications : [], $unread, $message, $success);
};
