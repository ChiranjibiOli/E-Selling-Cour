<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Session\AuthSession;

require_once __DIR__ . '/Request.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/Service.php';
require_once __DIR__ . '/ViewModel.php';
require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    $dashboard = match (AuthSession::role()) {
        'student' => '/student/dashboard',
        'instructor' => '/instructor/dashboard',
        'admin' => '/admin/dashboard',
        default => '',
    };
    if ($dashboard !== '') {
        return Response::redirect($dashboard);
    }

    $landingRequest = LandingRequest::from($request->query);
    LandingValidator::validate($landingRequest);
    $model = LandingViewModel::from((new LandingService())->load());
    return LandingPage::render($model);
};
