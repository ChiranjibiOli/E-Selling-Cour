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
            $action = strtolower(trim((string) ($request->body['action'] ?? '')));

            if ($action === 'save_platform') {
                $values = is_array($request->body['values'] ?? null) ? $request->body['values'] : [];
                $result = $client->post('/api/v1/reports/admin-console/settings', [
                    'action' => 'save',
                    'values' => $values,
                ]);
                $message = (string) ($result['message'] ?? 'Platform settings saved.');
            } elseif ($action === 'save_gateways') {
                $result = $client->post('/api/v1/payments/admin/gateways', [
                    'esewa_enabled' => isset($request->body['esewa_enabled']),
                    'khalti_enabled' => isset($request->body['khalti_enabled']),
                ]);
                $message = (string) ($result['message'] ?? 'Payment gateway availability updated.');
            } else {
                throw new DomainException('Choose a valid settings action.');
            }
        }

        $settings = $client->get('/api/v1/reports/admin-console/settings')['data']['values'] ?? [];
        $gateways = $client->get('/api/v1/payments/admin/gateways')['data'] ?? [];
    } catch (DomainException $exception) {
        $message = $exception->getMessage();
        $success = false;
        $settings = [];
        $gateways = [];
        try {
            $settings = $client->get('/api/v1/reports/admin-console/settings')['data']['values'] ?? [];
        } catch (DomainException) {
        }
        try {
            $gateways = $client->get('/api/v1/payments/admin/gateways')['data'] ?? [];
        } catch (DomainException) {
        }
    }

    return AdminSettingsPage::render(
        is_array($settings) ? $settings : [],
        is_array($gateways) ? $gateways : [],
        $message,
        $success,
    );
};
