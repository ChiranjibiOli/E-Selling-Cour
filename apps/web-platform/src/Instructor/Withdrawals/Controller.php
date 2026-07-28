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
            $action = strtolower(trim((string) ($request->body['action'] ?? 'request')));
            if ($action === 'retry') {
                $requestId = filter_var($request->body['request_id'] ?? 0, FILTER_VALIDATE_INT);
                if ($requestId === false || $requestId < 1) {
                    throw new DomainException('Choose a rejected withdrawal to request again.');
                }
                $result = $client->post('/api/v1/reports/withdrawals', [
                    'retry_request_id' => (int) $requestId,
                    'note' => 'Instructor requested Admin payment again after rejection.',
                ]);
            } else {
                $result = $client->post('/api/v1/reports/withdrawals', [
                    'payment_method' => (string) ($request->body['payment_method'] ?? 'bank'),
                    'note' => trim((string) ($request->body['note'] ?? '')),
                ]);
            }
            $message = (string) ($result['message'] ?? 'Withdrawal requested.');
        }
        $data = $client->get('/api/v1/reports/withdrawals/mine')['data'] ?? ['available_balance' => '0', 'reserved_balance' => '0', 'paid_total' => '0', 'requests' => []];
    } catch (DomainException $exception) {
        $data = ['available_balance' => '0', 'reserved_balance' => '0', 'paid_total' => '0', 'requests' => []];
        try { $data = $client->get('/api/v1/reports/withdrawals/mine')['data'] ?? $data; } catch (DomainException) {}
        $message = $exception->getMessage();
        $success = false;
    }
    return InstructorWithdrawalsPage::render($data, $message, $success);
};
