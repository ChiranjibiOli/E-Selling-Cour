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
            $action = (string) ($request->body['decision'] ?? '');
            if (!in_array($action, ['approve', 'reject', 'paid'], true)) {
                throw new DomainException('Choose a valid payout decision.');
            }
            $result = $client->post('/api/v1/reports/withdrawals/' . (int) ($request->body['withdrawal_id'] ?? 0) . '/' . $action, [
                'note' => trim((string) ($request->body['note'] ?? '')),
                'transaction_reference' => trim((string) ($request->body['transaction_reference'] ?? '')),
            ]);
            $message = (string) ($result['message'] ?? 'Withdrawal updated.');
        }
        $withdrawals = $client->get('/api/v1/reports/withdrawals/pending')['data'] ?? [];
    } catch (DomainException $exception) {
        $withdrawals = [];
        try { $withdrawals = $client->get('/api/v1/reports/withdrawals/pending')['data'] ?? []; } catch (DomainException) {}
        $message = $exception->getMessage();
        $success = false;
    }
    return AdminWithdrawalsPage::render($withdrawals, $message, $success);
};
