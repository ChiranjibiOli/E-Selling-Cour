<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_shared/ServiceShell.php';

CourseHub\Services\ServiceShell::run('media-service', ['/api/v1/media', '/api/v1/uploads']);
