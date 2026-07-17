<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_shared/ServiceShell.php';

CourseHub\Services\ServiceShell::run('learning-service', ['/api/v1/learning', '/api/v1/progress']);
