<?php

declare(strict_types=1);
final class CourseDetailsValidator{public static function validate(CourseDetailsRequest $request):void{if($request->courseId<1){throw new DomainException('Invalid course.');}}}
