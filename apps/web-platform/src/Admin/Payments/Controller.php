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
            $paymentId = (int) ($request->body['payment_id'] ?? 0);
            $decision = (string) ($request->body['decision'] ?? '');
            if (!in_array($decision, ['approve', 'reject'], true)) {
                throw new DomainException('Choose a valid payment decision.');
            }
            $result = $client->post('/api/v1/payments/' . $paymentId . '/' . $decision, ['note' => trim((string) ($request->body['note'] ?? ''))]);
            $message = (string) ($result['message'] ?? 'Payment updated.');
        }
        $payments = $client->get('/api/v1/payments/pending')['data'] ?? [];
    } catch (DomainException $exception) {
        $payments = [];
        try {
            $payments = $client->get('/api/v1/payments/pending')['data'] ?? [];
        } catch (DomainException) {
        }
        $message = $exception->getMessage();
        $success = false;
    }
    return AdminPaymentsPage::render($payments, $message, $success);
};
