<?php

declare(strict_types=1);
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;
require_once __DIR__.'/Request.php';require_once __DIR__.'/Validator.php';require_once __DIR__.'/Service.php';require_once __DIR__.'/ViewModel.php';require_once __DIR__.'/Page.php';
return static function(Request $request):Response{try{$input=CourseDetailsRequest::from($request->query);CourseDetailsValidator::validate($input);return CourseDetailsPage::render(CourseDetailsViewModel::from((new CourseDetailsService())->load($input->courseId)));}catch(DomainException $e){return Response::html('<h1>Course unavailable</h1><p>'.htmlspecialchars($e->getMessage(),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p>',404);}};
