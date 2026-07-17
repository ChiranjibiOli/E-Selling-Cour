<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
$service = basename(dirname(__DIR__));
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
if ($path === '/health') {
    echo json_encode(['status'=>'ok','service'=>$service], JSON_THROW_ON_ERROR);
    exit;
}
http_response_code(501);
echo json_encode(['service'=>$service,'status'=>'structure-created','message'=>'The domain feature room exists; business logic is not yet migrated.'], JSON_THROW_ON_ERROR);
