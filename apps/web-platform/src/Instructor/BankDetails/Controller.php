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
            $result = $client->post('/api/v1/reports/payout-details', $request->body);
            $message = (string) ($result['message'] ?? 'Payout details saved.');
        }
        $details = $client->get('/api/v1/reports/payout-details')['data'] ?? [];
    } catch (DomainException $exception) {
        $details = $request->body;
        $message = $exception->getMessage();
        $success = false;
    }
    return InstructorBankDetailsPage::render($details, $message, $success);
};
