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

    try {
        if ($request->method === 'POST') {
            Csrf::assertValid((string) ($request->body['_token'] ?? ''));
            $action = FormInput::enum($request->body, 'action', 'Review action', ['review_course', 'edit_permission', 'review_revision']);
            $note = FormInput::multiline($request->body, 'note', 'Review note', 0, 1000, false);

            if ($action === 'review_course') {
                $id = FormInput::integer($request->body, 'course_id', 'Course', 1, PHP_INT_MAX);
                $decision = FormInput::enum($request->body, 'decision', 'Course decision', ['approve', 'reject']);
                $result = $client->post('/api/v1/courses/' . $id . '/' . $decision, ['note' => $note]);
            } elseif ($action === 'edit_permission') {
                $id = FormInput::integer($request->body, 'course_id', 'Course', 1, PHP_INT_MAX);
                $decision = FormInput::enum($request->body, 'decision', 'Edit-permission decision', ['grant', 'deny']);
                $result = $client->post('/api/v1/courses/' . $id . '/edit-permission/' . $decision, ['note' => $note]);
            } else {
                $id = FormInput::integer($request->body, 'revision_id', 'Course revision', 1, PHP_INT_MAX);
                $decision = FormInput::enum($request->body, 'decision', 'Revision decision', ['approve', 'reject']);
                $result = $client->post('/api/v1/courses/revisions/' . $id . '/' . $decision, ['note' => $note]);
                foreach (array_merge((array) ($result['retired_files'] ?? []), (array) ($result['discarded_files'] ?? [])) as $storedFile) {
                    SecureUpload::delete(is_string($storedFile) ? $storedFile : null);
                }
            }
            $message = (string) ($result['message'] ?? 'Course workflow updated.');
        }

        $courses = $client->get('/api/v1/courses/pending')['data'] ?? [];
        $permissions = $client->get('/api/v1/courses/edit-permissions')['data'] ?? [];
        $revisions = $client->get('/api/v1/courses/revisions/pending')['data'] ?? [];
    } catch (DomainException $exception) {
        $courses = [];
        $permissions = [];
        $revisions = [];
        try { $courses = $client->get('/api/v1/courses/pending')['data'] ?? []; } catch (DomainException) {}
        try { $permissions = $client->get('/api/v1/courses/edit-permissions')['data'] ?? []; } catch (DomainException) {}
        try { $revisions = $client->get('/api/v1/courses/revisions/pending')['data'] ?? []; } catch (DomainException) {}
        $message = $exception->getMessage();
        $success = false;
    }

    return CourseApprovalsPage::render(
        is_array($courses) ? $courses : [],
        is_array($permissions) ? $permissions : [],
        is_array($revisions) ? $revisions : [],
        $message,
        $success,
    );
};
