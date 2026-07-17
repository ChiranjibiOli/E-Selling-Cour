<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/ServiceRegistry.php';
require_once dirname(__DIR__) . '/src/ProxyController.php';

(new CourseHub\Gateway\ProxyController())->handle();
