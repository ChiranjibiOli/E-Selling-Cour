<?php

declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_once '../app/actions/admin_instructor_action.php';
    exit;
}

require_once '../app/views/admin/instructors.php';
