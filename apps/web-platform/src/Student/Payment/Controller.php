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
    $orderId = (int) ($request->query['order'] ?? $request->body['order_id'] ?? 0);

    try {
        if ($request->method === 'POST') {
            Csrf::assertValid((string) ($request->body['_token'] ?? ''));
            $result = $client->post('/api/v1/payments/manual', [
                'order_id' => $orderId,
                'transaction_id' => trim((string) ($request->body['transaction_id'] ?? '')),
                'proof_image' => trim((string) ($request->body['proof_image'] ?? '')),
                'note' => trim((string) ($request->body['note'] ?? '')),
            ]);
            $message = (string) ($result['message'] ?? 'Payment submitted.');
        }
        $order = $orderId > 0 ? ($client->get('/api/v1/orders/' . $orderId)['data'] ?? []) : [];
        if ($order === []) {
            $orders = $client->get('/api/v1/orders/mine')['data'] ?? [];
            foreach ($orders as $candidate) {
                if (($candidate['order_status'] ?? '') === 'pending' && empty($candidate['payment_id'])) {
                    $orderId = (int) $candidate['id'];
                    $order = $client->get('/api/v1/orders/' . $orderId)['data'] ?? [];
                    break;
                }
            }
        }
    } catch (DomainException $exception) {
        $order = $order ?? [];
        $message = $exception->getMessage();
        $success = false;
    }

    return StudentPaymentPage::render($order, $message, $success, $request->body);
};
