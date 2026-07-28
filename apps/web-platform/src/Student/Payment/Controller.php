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
    $options = [];

    $localCallback = static function (Request $request, int $orderId): ?array {
        $hostHeader = strtolower(trim((string) ($request->server['HTTP_HOST'] ?? '')));
        if ($hostHeader === '' || strlen($hostHeader) > 255 || preg_match('/^[a-z0-9.:-]+$/', $hostHeader) !== 1) {
            return null;
        }

        $parts = parse_url('http://' . $hostHeader);
        if (!is_array($parts)) {
            return null;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || ($port !== null && ($port < 1 || $port > 65535))
        ) {
            return null;
        }

        $https = strtolower((string) ($request->server['HTTPS'] ?? ''));
        $scheme = $https !== '' && $https !== 'off' && $https !== '0' ? 'https' : 'http';
        $authority = str_contains($host, ':') ? '[' . $host . ']' : $host;
        if ($port !== null) {
            $authority .= ':' . $port;
        }
        $callback = $scheme . '://' . $authority . '/student/payment';

        return [
            'success_url' => $callback,
            'failure_url' => $callback . '?gateway=esewa&result=failure&order=' . $orderId,
        ];
    };

    $localEsewaDemo = static function (array $gateway): Response {
        $orderId = (int) ($gateway['order_id'] ?? 0);
        $amount = number_format((float) ($gateway['amount'] ?? 0), 2, '.', '');
        $transactionUuid = trim((string) ($gateway['transaction_uuid'] ?? ''));
        $demoToken = trim((string) ($gateway['demo_token'] ?? ''));
        if ($orderId < 1
            || (float) $amount <= 0
            || preg_match('/^CH-' . $orderId . '-[A-Za-z0-9-]+$/', $transactionUuid) !== 1
            || preg_match('/^[a-f0-9]{64}$/', strtolower($demoToken)) !== 1
        ) {
            throw new DomainException('The local eSewa simulator returned an invalid checkout session.');
        }

        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $account = (string) ($gateway['test_account'] ?? '9711111111');
        $password = (string) ($gateway['test_password'] ?? 'Nepal@123');
        $token = (string) ($gateway['test_token'] ?? '123456');
        $productCode = (string) ($gateway['product_code'] ?? 'EPAYTEST');

        return Response::html(
            '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>eSewa local sandbox</title><style>'
            . '*{box-sizing:border-box}body{font-family:Inter,system-ui,sans-serif;margin:0;min-height:100vh;display:grid;place-items:center;background:#eef7f0;color:#17211a}'
            . '.shell{width:min(520px,calc(100% - 32px));background:#fff;border-radius:22px;box-shadow:0 24px 70px rgba(21,72,35,.16);overflow:hidden}'
            . '.head{padding:26px 30px;background:#55a630;color:#fff}.head small{display:block;letter-spacing:.12em;font-weight:800}.head h1{margin:7px 0 0;font-size:28px}'
            . '.body{padding:28px 30px}.notice{padding:13px 15px;border-radius:12px;background:#fff4d6;margin-bottom:20px;font-size:14px;line-height:1.5}'
            . '.summary{display:flex;justify-content:space-between;gap:20px;padding:15px 0;border-bottom:1px solid #e7ece8;margin-bottom:18px}.summary strong{font-size:21px}'
            . 'label{display:block;font-weight:750;margin:14px 0 6px}input{width:100%;padding:12px 13px;border:1px solid #cbd8ce;border-radius:10px;font:inherit}'
            . '.hint{font-size:12px;color:#5f6d63;margin-top:5px}.actions{display:flex;gap:10px;margin-top:24px}.button{flex:1;border:0;border-radius:11px;padding:13px 16px;font:inherit;font-weight:800;cursor:pointer;text-align:center;text-decoration:none}'
            . '.pay{background:#55a630;color:#fff}.cancel{background:#edf1ee;color:#26352a}.foot{font-size:12px;color:#66736a;margin-top:17px;line-height:1.5}</style></head><body>'
            . '<main class="shell"><header class="head"><small>LOCAL DEVELOPMENT ONLY</small><h1>eSewa sandbox simulator</h1></header><section class="body">'
            . '<div class="notice"><strong>eSewa UAT fallback:</strong> This local simulator is used because the external test gateway is returning HTTP 404. No real money is transferred.</div>'
            . '<div class="summary"><span>CourseHub order #' . $orderId . '<br><small>' . $e($productCode) . '</small></span><strong>NPR ' . $e($amount) . '</strong></div>'
            . '<form method="post" action="/student/payment?order=' . $orderId . '">' . Csrf::field()
            . '<input type="hidden" name="order_id" value="' . $orderId . '">'
            . '<input type="hidden" name="payment_method" value="esewa-demo">'
            . '<input type="hidden" name="transaction_uuid" value="' . $e($transactionUuid) . '">'
            . '<input type="hidden" name="demo_token" value="' . $e($demoToken) . '">'
            . '<label>eSewa test ID</label><input name="esewa_id" inputmode="numeric" value="' . $e($account) . '" required><div class="hint">Test ID: ' . $e($account) . '</div>'
            . '<label>Password</label><input type="password" name="password" value="' . $e($password) . '" required><div class="hint">Test password: ' . $e($password) . '</div>'
            . '<label>Token / OTP</label><input name="token" inputmode="numeric" value="' . $e($token) . '" required><div class="hint">Test token: ' . $e($token) . '</div>'
            . '<div class="actions"><a class="button cancel" href="/student/payment?order=' . $orderId . '">Cancel</a><button class="button pay" type="submit">Complete demo payment</button></div>'
            . '</form><p class="foot">The simulator still verifies the Student, order ownership, pending amount, signed demo session and payment state before activating lifetime course access.</p>'
            . '</section></main></body></html>'
        );
    };

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
            . '<title>Opening eSewa</title><style>body{font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#fff3e0;color:#171611}'
            . '.box{width:min(440px,calc(100% - 40px));padding:32px;border-radius:24px;background:#fffaf3;box-shadow:0 18px 50px rgba(40,30,20,.12);text-align:center}'
            . 'button{border:0;border-radius:999px;padding:13px 22px;background:#ff7043;color:#171611;font-weight:800;cursor:pointer}</style></head>'
            . '<body><div class="box"><h1>Opening eSewa</h1><p>Your order is ready. CourseHub is sending you to the secure eSewa checkout.</p>'
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
            . '<style>body{font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#fff3e0;color:#171611}'
            . '.box{width:min(440px,calc(100% - 40px));padding:32px;border-radius:24px;background:#fffaf3;box-shadow:0 18px 50px rgba(40,30,20,.12);text-align:center}'
            . 'a{display:inline-block;border-radius:999px;padding:13px 22px;background:#ff7043;color:#171611;font-weight:800;text-decoration:none}</style></head>'
            . '<body><div class="box"><h1>Opening Khalti</h1><p>Your order is ready. CourseHub is sending you to the secure Khalti checkout.</p>'
            . '<a href="' . $safeUrl . '">Continue to Khalti</a></div><script>window.location.replace(' . $jsonUrl . ');</script></body></html>'
        );
    };

    try {
        if ($request->method === 'GET' && trim((string) ($request->query['data'] ?? '')) !== '') {
            $client->post('/api/v1/payments/esewa/verify', ['data' => (string) $request->query['data']]);
            return Response::redirect('/student/my-courses?payment=esewa-verified');
        }

        if ($request->method === 'GET' && trim((string) ($request->query['pidx'] ?? '')) !== '') {
            $purchaseOrderId = trim((string) ($request->query['purchase_order_id'] ?? ''));
            if ($orderId < 1 && preg_match('/^COURSEHUB-(\d+)-[A-Za-z0-9]+$/', $purchaseOrderId, $match) === 1) {
                $orderId = (int) $match[1];
            }
            $client->post('/api/v1/payments/khalti/verify', [
                'pidx' => (string) $request->query['pidx'],
                'order_id' => $orderId,
                'purchase_order_id' => $purchaseOrderId,
            ]);
            return Response::redirect('/student/my-courses?payment=khalti-verified');
        }

        if ($request->method === 'GET'
            && ($request->query['gateway'] ?? '') === 'esewa'
            && ($request->query['result'] ?? '') === 'failure'
        ) {
            $message = 'The eSewa payment was cancelled, failed or left pending. You can safely try again.';
            $success = false;
        }

        if ($request->method === 'POST') {
            Csrf::assertValid((string) ($request->body['_token'] ?? ''));
            if ($orderId < 1) {
                throw new DomainException('Choose a valid unpaid order.');
            }
            $paymentMethod = strtolower(trim((string) ($request->body['payment_method'] ?? 'manual')));
            if ($paymentMethod === 'esewa-demo') {
                $client->post('/api/v1/payments/esewa/demo-complete', [
                    'order_id' => $orderId,
                    'transaction_uuid' => (string) ($request->body['transaction_uuid'] ?? ''),
                    'demo_token' => (string) ($request->body['demo_token'] ?? ''),
                    'esewa_id' => (string) ($request->body['esewa_id'] ?? ''),
                    'password' => (string) ($request->body['password'] ?? ''),
                    'token' => (string) ($request->body['token'] ?? ''),
                ]);
                return Response::redirect('/student/my-courses?payment=esewa-demo-verified');
            }
            if ($paymentMethod === 'esewa') {
                $result = $client->post('/api/v1/payments/esewa/initiate', ['order_id' => $orderId]);
                $gateway = is_array($result['data'] ?? null) ? $result['data'] : [];
                if (($gateway['mode'] ?? '') === 'local-demo') {
                    return $localEsewaDemo($gateway);
                }
                $fields = is_array($gateway['fields'] ?? null) ? $gateway['fields'] : [];
                $localUrls = $localCallback($request, $orderId);
                if ($localUrls !== null) {
                    $fields['success_url'] = $localUrls['success_url'];
                    $fields['failure_url'] = $localUrls['failure_url'];
                }
                return $gatewayForm((string) ($gateway['action'] ?? ''), $fields);
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
        $options = $client->get('/api/v1/payments/options')['data'] ?? [];
    } catch (DomainException $exception) {
        SecureUpload::delete($storedProof);
        $order = $order ?? [];
        $message = $exception->getMessage();
        $success = false;
        try {
            $options = $client->get('/api/v1/payments/options')['data'] ?? [];
        } catch (DomainException) {
            $options = [];
        }
    }

    return StudentPaymentPage::render(
        is_array($order) ? $order : [],
        $message,
        $success,
        $request->body,
        is_array($options) ? $options : [],
    );
};
