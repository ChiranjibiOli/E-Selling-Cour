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
            if (($request->body['action'] ?? 'save') === 'delete') {
                $result = $client->request('DELETE', '/api/v1/reviews/' . (int) ($request->body['review_id'] ?? 0));
            } else {
                $result = $client->post('/api/v1/reviews', [
                    'course_id' => (int) ($request->body['course_id'] ?? 0),
                    'rating' => (int) ($request->body['rating'] ?? 0),
                    'review_text' => trim((string) ($request->body['review_text'] ?? '')),
                ]);
            }
            $message = (string) ($result['message'] ?? 'Review updated.');
        }
        $reviews = $client->get('/api/v1/reviews/mine')['data'] ?? [];
        $eligible = $client->get('/api/v1/reviews/eligible')['data'] ?? [];
    } catch (DomainException $exception) {
        $reviews = [];
        $eligible = [];
        try { $reviews = $client->get('/api/v1/reviews/mine')['data'] ?? []; } catch (DomainException) {}
        try { $eligible = $client->get('/api/v1/reviews/eligible')['data'] ?? []; } catch (DomainException) {}
        $message = $exception->getMessage();
        $success = false;
    }
    return StudentReviewsPage::render($reviews, $eligible, $message, $success);
};
