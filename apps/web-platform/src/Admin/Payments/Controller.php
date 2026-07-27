<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Media\PrivateMedia;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Security\FormInput;

require_once __DIR__ . '/Page.php';

return static function (Request $request): Response {
    RoomRuntime::authorize(__DIR__, $request);
    $client = new ApiClient();

    if ($request->method === 'GET' && isset($request->query['proof'])) {
        try {
            $paymentId = filter_var($request->query['proof'], FILTER_VALIDATE_INT);
            if ($paymentId === false || $paymentId < 1) {
                return Response::html('', 404);
            }
            $payments = $client->get('/api/v1/payments/pending')['data'] ?? [];
            foreach ((array) $payments as $payment) {
                if ((int) ($payment['id'] ?? 0) === (int) $paymentId) {
                    return PrivateMedia::response((string) ($payment['proof_image'] ?? ''), ['private/payment-proofs']);
                }
            }
        } catch (DomainException) {
        }
        return Response::html('', 404);
    }

    $message = '';
    $success = true;
    try {
        if ($request->method === 'POST') {
            Csrf::assertValid((string) ($request->body['_token'] ?? ''));
            $paymentId = FormInput::integer($request->body, 'payment_id', 'Payment', 1, PHP_INT_MAX);
            $decision = FormInput::enum($request->body, 'decision', 'Payment decision', ['approve', 'reject']);
            $note = FormInput::multiline($request->body, 'note', 'Verification note', 0, 1000, false);
            if ($decision === 'reject' && $note === '') {
                throw new DomainException('A rejection reason is required.');
            }
            $result = $client->post('/api/v1/payments/' . $paymentId . '/' . $decision, ['note' => $note]);
            $message = (string) ($result['message'] ?? 'Payment updated.');
        }
        $payments = $client->get('/api/v1/payments/pending')['data'] ?? [];
    } catch (DomainException $exception) {
        $payments = [];
        try { $payments = $client->get('/api/v1/payments/pending')['data'] ?? []; } catch (DomainException) {}
        $message = $exception->getMessage();
        $success = false;
    }
    return AdminPaymentsPage::render(is_array($payments) ? $payments : [], $message, $success);
};
