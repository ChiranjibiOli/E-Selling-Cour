<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;
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

    $gatewayForm = static function (string $action, array $fields): Response {
        $parts = parse_url($action);
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || !in_array($host, ['rc-epay.esewa.com.np', 'epay.esewa.com.np'], true)
        ) {
            throw new DomainException('The eSewa checkout URL is invalid.');
        }
        $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $inputs = '';
        foreach ($fields as $name => $value) {
            if (!is_string($name) || !is_scalar($value)) {
                throw new DomainException('The eSewa checkout fields are invalid.');
            }
            $inputs .= '<input type="hidden" name="' . $escape($name) . '" value="' . $escape($value) . '">';
        }
        return Response::html(
            '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Opening eSewa</title><style>body{font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#f4f7f5;color:#17221c}'
            . '.box{width:min(440px,calc(100% - 40px));padding:32px;border-radius:18px;background:#fff;box-shadow:0 18px 50px rgba(20,50,35,.12);text-align:center}'
            . 'button{border:0;border-radius:10px;padding:12px 20px;background:#1f8f4e;color:#fff;font-weight:700;cursor:pointer}</style></head>'
            . '<body><div class="box"><h1>Opening eSewa</h1><p>Your order is ready. You are being sent to the secure eSewa checkout.</p>'
            . '<form id="gateway-payment" method="post" action="' . $escape($action) . '">' . $inputs . '<button type="submit">Continue to eSewa</button></form></div>'
            . '<script>document.getElementById("gateway-payment").submit();</script></body></html>'
        );
    };

    $gatewayRedirect = static function (string $url): Response {
        $parts = parse_url($url);
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || !in_array($host, ['test-pay.khalti.com', 'pay.khalti.com'], true)
        ) {
            throw new DomainException('The Khalti checkout URL is invalid.');
        }
        $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $jsonUrl = json_encode($url, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        return Response::html(
            '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta http-equiv="refresh" content="0;url=' . $safeUrl . '"><title>Opening Khalti</title>'
            . '<style>body{font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#f7f4fa;color:#25172e}'
            . '.box{width:min(440px,calc(100% - 40px));padding:32px;border-radius:18px;background:#fff;box-shadow:0 18px 50px rgba(55,25,70,.12);text-align:center}'
            . 'a{display:inline-block;border-radius:10px;padding:12px 20px;background:#5c2d91;color:#fff;font-weight:700;text-decoration:none}</style></head>'
            . '<body><div class="box"><h1>Opening Khalti</h1><p>Your order is ready. You are being sent to the secure Khalti checkout.</p>'
            . '<a href="' . $safeUrl . '">Continue to Khalti</a></div><script>window.location.replace(' . $jsonUrl . ');</script></body></html>'
        );
    };

    try {
        if ($request->method === 'GET' && trim((string) ($request->query['data'] ?? '')) !== '') {
            $result = $client->post('/api/v1/payments/esewa/verify', ['data' => (string) $request->query['data']]);
            $orderId = (int) ($result['data']['order_id'] ?? 0);
            return Response::redirect('/student/my-courses?payment=esewa-verified');
        }

        if ($request->method === 'GET' && trim((string) ($request->query['pidx'] ?? '')) !== '') {
            $purchaseOrderId = trim((string) ($request->query['purchase_order_id'] ?? ''));
            if ($orderId < 1 && preg_match('/^COURSEHUB-(\d+)-[A-Za-z0-9]+$/', $purchaseOrderId, $match) === 1) {
                $orderId = (int) $match[1];
            }
            $result = $client->post('/api/v1/payments/khalti/verify', [
                'pidx' => (string) $request->query['pidx'],
                'order_id' => $orderId,
                'purchase_order_id' => $purchaseOrderId,
            ]);
            $orderId = (int) ($result['data']['order_id'] ?? $orderId);
            return Response::redirect('/student/my-courses?payment=khalti-verified');
        }

        if ($request->method === 'GET'
            && ($request->query['gateway'] ?? '') === 'esewa'
            && ($request->query['result'] ?? '') === 'failure'
        ) {
            $message = 'The eSewa sandbox payment was cancelled, failed or left pending. You can safely try again.';
            $success = false;
        }

        if ($request->method === 'POST') {
            Csrf::assertValid((string) ($request->body['_token'] ?? ''));
            if ($orderId < 1) {
                throw new DomainException('Choose a valid unpaid order.');
            }
            $paymentMethod = strtolower(trim((string) ($request->body['payment_method'] ?? 'manual')));
            if ($paymentMethod === 'esewa') {
                $result = $client->post('/api/v1/payments/esewa/initiate', ['order_id' => $orderId]);
                $gateway = is_array($result['data'] ?? null) ? $result['data'] : [];
                return $gatewayForm((string) ($gateway['action'] ?? ''), is_array($gateway['fields'] ?? null) ? $gateway['fields'] : []);
            }
            if ($paymentMethod === 'khalti') {
                $result = $client->post('/api/v1/payments/khalti/initiate', ['order_id' => $orderId]);
                return $gatewayRedirect((string) ($result['data']['payment_url'] ?? ''));
            }
            if ($paymentMethod !== 'manual') {
                throw new DomainException('Choose a supported payment method.');
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
            foreach ($orders as $candidate) {
                if (($candidate['order_status'] ?? '') === 'pending') {
                    $orderId = (int) $candidate['id'];
                    $order = $client->get('/api/v1/orders/' . $orderId)['data'] ?? [];
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

    return StudentPaymentPage::render(is_array($order) ? $order : [], $message, $success, $request->body);
};
