<?php

declare(strict_types=1);
final class CourseDetailsViewModel{public function __construct(public readonly array $course,public readonly array $sections){}public static function from(array $response):self{$course=is_array($response['data']??null)?$response['data']:[];return new self($course,is_array($course['sections']??null)?$course['sections']:[]);}}
