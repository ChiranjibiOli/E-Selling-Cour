<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Media\SecureUpload;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Security\FormInput;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    RoomRuntime::authorize(__DIR__, $request);
    $client = new ApiClient();
    $message = '';
    $success = true;
    $orderId = filter_var($request->query['order'] ?? $request->body['order_id'] ?? 0, FILTER_VALIDATE_INT);
    $orderId = $orderId !== false && $orderId > 0 ? (int) $orderId : 0;
    $storedProof = null;

    try {
        if ($request->method === 'GET'
            && (trim((string) ($request->query['data'] ?? '')) !== ''
                || trim((string) ($request->query['pidx'] ?? '')) !== ''
                || trim((string) ($request->query['gateway'] ?? '')) !== '')
        ) {
            $message = 'Automatic gateway payment is not available. Submit a manual payment receipt for Admin verification.';
            $success = false;
        }

        if ($request->method === 'POST') {
            Csrf::assertValid((string) ($request->body['_token'] ?? ''));
            if ($orderId < 1) {
                throw new DomainException('Choose a valid unpaid order.');
            }
            $paymentMethod = strtolower(trim((string) ($request->body['payment_method'] ?? 'manual')));
            if ($paymentMethod !== 'manual') {
                throw new DomainException('Automatic payment is not available. Use manual payment proof.');
            }

            $transactionId = FormInput::text($request->body, 'transaction_id', 'Transaction reference', 3, 150);
            $note = FormInput::multiline($request->body, 'note', 'Payment note', 0, 1000, false);
            $proof = is_array($_FILES['proof_image'] ?? null) ? $_FILES['proof_image'] : [];
            $error = (int) ($proof['error'] ?? UPLOAD_ERR_NO_FILE);
            $temporaryPath = (string) ($proof['tmp_name'] ?? '');
            if ($error !== UPLOAD_ERR_OK || $temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
                throw new DomainException('Upload the actual payment screenshot or PDF receipt.');
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
            if (!is_string($mime)) {
                throw new DomainException('The payment proof type could not be verified.');
            }
            if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                $dimensions = @getimagesize($temporaryPath);
                if (!is_array($dimensions) || (int) ($dimensions[0] ?? 0) < 400 || (int) ($dimensions[1] ?? 0) < 300) {
                    throw new DomainException('Use a readable payment screenshot of at least 400 × 300 pixels.');
                }
            }
            $storedProof = SecureUpload::store(
                $proof,
                'private/payment-proofs',
                ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'],
                8 * 1024 * 1024,
            );
            if ($storedProof === null) {
                throw new DomainException('A valid payment proof file is required.');
            }
            $result = $client->post('/api/v1/payments/manual', [
                'order_id' => $orderId,
                'transaction_id' => $transactionId,
                'proof_image' => $storedProof,
                'note' => $note,
            ]);
            $storedProof = null;
            $message = (string) ($result['message'] ?? 'Payment proof submitted for verification.');
        }

        $order = $orderId > 0 ? ($client->get('/api/v1/orders/' . $orderId)['data'] ?? []) : [];
        if ($order === []) {
            $orders = $client->get('/api/v1/orders/mine')['data'] ?? [];
            foreach (is_array($orders) ? $orders : [] as $candidate) {
                if (($candidate['order_status'] ?? '') === 'pending') {
                    $orderId = (int) ($candidate['id'] ?? 0);
                    if ($orderId > 0) {
                        $order = $client->get('/api/v1/orders/' . $orderId)['data'] ?? [];
                    }
                    break;
                }
            }
        }
    } catch (DomainException $exception) {
        SecureUpload::delete($storedProof);
        $order = $order ?? [];
        $message = $exception->getMessage();
        $success = false;
    }

    return StudentPaymentPage::render(
        is_array($order) ? $order : [],
        $message,
        $success,
        $request->body,
    );
};
