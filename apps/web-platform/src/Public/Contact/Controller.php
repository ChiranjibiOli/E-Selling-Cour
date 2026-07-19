<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Security\Csrf;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    $message = '';
    $success = true;
    if ($request->method === 'POST') {
        try {
            Csrf::assertValid((string) ($request->body['_token'] ?? ''));
            $result = (new ApiClient())->post('/api/v1/notifications/contact', [
                'name' => trim((string) ($request->body['name'] ?? '')),
                'email' => trim((string) ($request->body['email'] ?? '')),
                'subject' => trim((string) ($request->body['subject'] ?? '')),
                'message' => trim((string) ($request->body['message'] ?? '')),
            ]);
            $message = (string) ($result['message'] ?? 'Message sent.');
        } catch (DomainException $exception) {
            $message = $exception->getMessage();
            $success = false;
        }
    }
    return PublicContactPage::render($request->body, $message, $success);
};
