<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
if ($path === '/health') {
    echo json_encode(['status'=>'ok','service'=>'api-gateway'], JSON_THROW_ON_ERROR);
    exit;
}
$routes = [
'/api/v1/auth'=>'http://identity-service:8080',
'/api/v1/users'=>'http://identity-service:8080',
'/api/v1/courses'=>'http://catalog-service:8080',
'/api/v1/categories'=>'http://catalog-service:8080',
'/api/v1/learning'=>'http://learning-service:8080',
'/api/v1/progress'=>'http://learning-service:8080',
'/api/v1/commerce'=>'http://commerce-service:8080',
'/api/v1/cart'=>'http://commerce-service:8080',
'/api/v1/orders'=>'http://commerce-service:8080',
'/api/v1/payments'=>'http://payment-service:8080',
'/api/v1/enrollments'=>'http://enrollment-service:8080',
'/api/v1/media'=>'http://media-service:8080',
'/api/v1/notifications'=>'http://notification-service:8080',
'/api/v1/reviews'=>'http://review-service:8080',
'/api/v1/reports'=>'http://reporting-service:8080',
];
$target = null;
uksort($routes, static fn(string $a,string $b):int => strlen($b)<=>strlen($a));
foreach ($routes as $prefix=>$url) {
    if ($path === $prefix || str_starts_with($path,$prefix.'/')) {
        $target = $url;
        break;
    }
}
if ($target === null) {
    http_response_code(404);
    echo json_encode(['error'=>'Gateway route not found.'], JSON_THROW_ON_ERROR);
    exit;
}
$ch = curl_init($target . ($_SERVER['REQUEST_URI'] ?? '/'));
curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$_SERVER['REQUEST_METHOD'] ?? 'GET',CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Content-Type: '.($_SERVER['CONTENT_TYPE'] ?? 'application/json'),'Authorization: '.($_SERVER['HTTP_AUTHORIZATION'] ?? '')]]);
$body = file_get_contents('php://input');
if ($body !== '') { curl_setopt($ch,CURLOPT_POSTFIELDS,$body); }
$response = curl_exec($ch);
$status = (int) curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
if ($response === false) {
    http_response_code(502);
    echo json_encode(['error'=>'Upstream service unavailable.'], JSON_THROW_ON_ERROR);
    exit;
}
http_response_code($status ?: 200);
echo $response;
