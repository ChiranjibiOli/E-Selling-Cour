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
        if ($request->method === 'GET' && (int) ($request->query['add'] ?? 0) > 0) {
            $result = $client->post('/api/v1/cart', ['course_id' => (int) $request->query['add']]);
            $message = (string) ($result['message'] ?? 'Course added to cart.');
        }
        if ($request->method === 'POST') {
            Csrf::assertValid((string) ($request->body['_token'] ?? ''));
            if (($request->body['action'] ?? '') === 'remove') {
                $result = $client->request('DELETE', '/api/v1/cart/' . (int) ($request->body['course_id'] ?? 0));
                $message = (string) ($result['message'] ?? 'Cart updated.');
            }
        }
        $cart = $client->get('/api/v1/cart')['data'] ?? ['items' => [], 'count' => 0, 'subtotal' => '0.00'];
    } catch (DomainException $exception) {
        $cart = ['items' => [], 'count' => 0, 'subtotal' => '0.00'];
        try {
            $cart = $client->get('/api/v1/cart')['data'] ?? $cart;
        } catch (DomainException) {
        }
        $message = $exception->getMessage();
        $success = false;
    }

    return StudentCartPage::render($cart, $message, $success);
};
