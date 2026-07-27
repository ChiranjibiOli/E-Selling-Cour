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
            $messageId = FormInput::integer($request->body, 'message_id', 'Contact message', 1, PHP_INT_MAX);
            $action = FormInput::enum($request->body, 'action', 'Contact action', ['update_status', 'send_reply']);

            if ($action === 'send_reply') {
                $result = $client->post('/api/v1/notifications/contact/' . $messageId . '/reply', [
                    'reply_subject' => FormInput::text($request->body, 'reply_subject', 'Reply subject', 1, 200),
                    'reply_message' => FormInput::multiline($request->body, 'reply_message', 'Reply message', 2, 10_000),
                ]);
                $message = (string) ($result['message'] ?? 'Support reply emailed.');
            } else {
                $status = FormInput::enum($request->body, 'status', 'Message status', ['new', 'read', 'replied'], 'read');
                $result = $client->post('/api/v1/notifications/contact/' . $messageId, ['status' => $status]);
                $message = (string) ($result['message'] ?? 'Message updated.');
            }
        }
        $messages = $client->get('/api/v1/notifications/contact')['data'] ?? [];
    } catch (DomainException $exception) {
        $messages = [];
        try {
            $messages = $client->get('/api/v1/notifications/contact')['data'] ?? [];
        } catch (DomainException) {
        }
        $message = $exception->getMessage();
        $success = false;
    }

    return AdminContactMessagesPage::render(is_array($messages) ? $messages : [], $message, $success);
};
