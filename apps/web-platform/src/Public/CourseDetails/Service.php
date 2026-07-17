<?php

declare(strict_types=1);
require_once __DIR__.'/ApiClient.php';
final class CourseDetailsService{public function load(int $id):array{return (new CourseDetailsApi())->find($id);}}
