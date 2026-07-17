<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;

require_once __DIR__ . '/Request.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/Service.php';
require_once __DIR__ . '/ViewModel.php';
require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    $landingRequest = LandingRequest::from($request->query);
    LandingValidator::validate($landingRequest);
    $model = LandingViewModel::from((new LandingService())->load());
    return LandingPage::render($model);
};
