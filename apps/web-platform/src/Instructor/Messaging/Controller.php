<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Security\FormInput;
use CourseHub\WebPlatform\Shared\Session\AuthSession;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    RoomRuntime::authorize(__DIR__, $request);
    $user = AuthSession::user();
    $message = '';
    $success = true;
    try {
        if ($request->method === 'POST') {
            Csrf::assertValid((string) ($request->body['_token'] ?? ''));
            $subject = FormInput::text($request->body, 'subject', 'Message subject', 3, 200);
            $body = FormInput::multiline($request->body, 'message', 'Message', 10, 10_000);
            $result = (new ApiClient())->post('/api/v1/notifications/contact', [
                'name' => (string) ($user['name'] ?? 'Instructor'),
                'email' => (string) ($user['email'] ?? ''),
                'subject' => '[Instructor] ' . $subject,
                'message' => $body,
            ]);
            $message = (string) ($result['message'] ?? 'Message sent to CourseHub Admin.');
        }
    } catch (DomainException $exception) {
        $message = $exception->getMessage();
        $success = false;
    }
    return InstructorMessagingPage::render($user, $message, $success, $request->method === 'POST' && !$success ? $request->body : []);
};
