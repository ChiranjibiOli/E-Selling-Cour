<?php

declare(strict_types=1);
use CourseHub\WebPlatform\Shared\Http\ApiClient;
final class CourseDetailsApi{public function find(int $id):array{return (new ApiClient())->get('/api/v1/courses/'.$id);}}
