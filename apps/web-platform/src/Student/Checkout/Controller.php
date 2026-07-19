<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;
use CourseHub\WebPlatform\Shared\Security\Csrf;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    RoomRuntime::authorize(__DIR__, $request);
    $client = new ApiClient();

    try {
        $cart = $client->get('/api/v1/cart')['data'] ?? ['items' => [], 'count' => 0, 'subtotal' => '0.00'];
        if ($request->method === 'GET') {
            return StudentCheckoutPage::render($cart);
        }
        Csrf::assertValid((string) ($request->body['_token'] ?? ''));
        $result = $client->post('/api/v1/orders/checkout', ['coupon_code' => trim((string) ($request->body['coupon_code'] ?? ''))]);
        $orderId = (int) ($result['data']['order_id'] ?? 0);
        if ($orderId < 1) {
            throw new DomainException('The checkout service did not return a valid order.');
        }
        return Response::redirect('/student/payment?order=' . $orderId);
    } catch (DomainException $exception) {
        return StudentCheckoutPage::render($cart ?? ['items' => [], 'count' => 0, 'subtotal' => '0.00'], $exception->getMessage(), false, $request->body);
    }
};
