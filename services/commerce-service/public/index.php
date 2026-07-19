<?php

declare(strict_types=1);

use CourseHub\Services\Shared\Database;
use CourseHub\Services\Shared\ServiceAuth;
use CourseHub\Services\Shared\ServiceAuthenticationException;
use CourseHub\Services\Shared\ServiceAuthorizationException;

require_once dirname(__DIR__, 2) . '/_shared/Database.php';
require_once dirname(__DIR__, 2) . '/_shared/ServiceAuth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');

$respond = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
};

$jsonInput = static function (): array {
    $raw = (string) file_get_contents('php://input');
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Request body must be a JSON object.');
    }
    return $decoded;
};

$cartSummary = static function (PDO $database, int $studentId): array {
    $statement = $database->prepare(
        'SELECT c.id AS course_id, c.title, c.short_description, c.thumbnail, c.price, c.level, c.language, '
        . 'u.full_name AS instructor_name, cat.name AS category_name '
        . 'FROM cart ca INNER JOIN courses c ON c.id = ca.course_id '
        . 'INNER JOIN users u ON u.id = c.instructor_id LEFT JOIN categories cat ON cat.id = c.category_id '
        . 'WHERE ca.student_id = :student_id AND c.status = \'published\' '
        . 'AND NOT EXISTS (SELECT 1 FROM enrollments e WHERE e.student_id = :student_id_enrollment AND e.course_id = c.id AND e.status = \'active\') '
        . 'ORDER BY ca.created_at DESC'
    );
    $statement->execute(['student_id' => $studentId, 'student_id_enrollment' => $studentId]);
    $items = $statement->fetchAll();
    $subtotal = 0.0;
    foreach ($items as &$item) {
        $item['thumbnail_url'] = trim((string) ($item['thumbnail'] ?? '')) !== ''
            ? '/media/course-thumbnails/' . rawurlencode(basename((string) $item['thumbnail']))
            : '';
        unset($item['thumbnail']);
        $subtotal += (float) $item['price'];
    }
    unset($item);
    return ['items' => $items, 'count' => count($items), 'subtotal' => number_format($subtotal, 2, '.', '')];
};

$loadCoupon = static function (PDO $database, string $code, array $courseIds, float $subtotal): ?array {
    $code = strtoupper(trim($code));
    if ($code === '') {
        return null;
    }
    if (preg_match('/^[A-Z0-9_-]{3,50}$/', $code) !== 1) {
        throw new InvalidArgumentException('Enter a valid coupon code.');
    }
    $statement = $database->prepare(
        'SELECT * FROM coupons WHERE code = :code AND status = \'active\' '
        . 'AND (valid_from IS NULL OR valid_from <= NOW()) AND (valid_until IS NULL OR valid_until >= NOW()) '
        . 'AND (usage_limit IS NULL OR used_count < usage_limit) LIMIT 1'
    );
    $statement->execute(['code' => $code]);
    $coupon = $statement->fetch();
    if (!is_array($coupon)) {
        throw new InvalidArgumentException('That coupon is invalid, expired, or fully used.');
    }
    if ($subtotal < (float) $coupon['min_order_amount']) {
        throw new InvalidArgumentException('This order does not meet the coupon minimum.');
    }
    $scope = $database->prepare('SELECT course_id FROM coupon_courses WHERE coupon_id = :coupon_id');
    $scope->execute(['coupon_id' => (int) $coupon['id']]);
    $eligibleCourseIds = array_map('intval', array_column($scope->fetchAll(), 'course_id'));
    if ($eligibleCourseIds !== [] && array_intersect($courseIds, $eligibleCourseIds) === []) {
        throw new InvalidArgumentException('This coupon does not apply to the selected courses.');
    }
    return $coupon;
};

try {
    $database = Database::connect();

    if ($path === '/health' && $method === 'GET') {
        $database->query('SELECT 1');
        $respond(['status' => 'ok', 'service' => 'commerce-service']);
    }

    if ($path === '/api/v1/cart' && $method === 'GET') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $respond(['data' => $cartSummary($database, $student['id'])]);
    }

    if ($path === '/api/v1/cart' && $method === 'POST') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $input = $jsonInput();
        $courseId = (int) ($input['course_id'] ?? 0);
        if ($courseId < 1) {
            throw new InvalidArgumentException('Choose a valid course.');
        }
        $course = $database->prepare('SELECT id FROM courses WHERE id = :id AND status = \'published\' LIMIT 1');
        $course->execute(['id' => $courseId]);
        if ($course->fetch() === false) {
            throw new InvalidArgumentException('The selected course is not available for purchase.');
        }
        $owned = $database->prepare('SELECT id FROM enrollments WHERE student_id = :student_id AND course_id = :course_id AND status = \'active\' LIMIT 1');
        $owned->execute(['student_id' => $student['id'], 'course_id' => $courseId]);
        if ($owned->fetch() !== false) {
            throw new ServiceAuthorizationException('You already have lifetime access to this course.');
        }
        $insert = $database->prepare('INSERT IGNORE INTO cart (student_id, course_id) VALUES (:student_id, :course_id)');
        $insert->execute(['student_id' => $student['id'], 'course_id' => $courseId]);
        $respond(['message' => $insert->rowCount() === 1 ? 'Course added to cart.' : 'Course is already in your cart.', 'data' => $cartSummary($database, $student['id'])], 201);
    }

    if (preg_match('#^/api/v1/cart/(\d+)$#', $path, $matches) === 1 && $method === 'DELETE') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $delete = $database->prepare('DELETE FROM cart WHERE student_id = :student_id AND course_id = :course_id');
        $delete->execute(['student_id' => $student['id'], 'course_id' => (int) $matches[1]]);
        $respond(['message' => 'Cart updated.', 'data' => $cartSummary($database, $student['id'])]);
    }

    if ($path === '/api/v1/orders/checkout' && $method === 'POST') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $input = $jsonInput();
        $couponCode = trim((string) ($input['coupon_code'] ?? ''));
        $database->beginTransaction();

        $statement = $database->prepare(
            'SELECT c.id, c.instructor_id, c.price FROM cart ca INNER JOIN courses c ON c.id = ca.course_id '
            . 'WHERE ca.student_id = :student_id AND c.status = \'published\' '
            . 'AND NOT EXISTS (SELECT 1 FROM enrollments e WHERE e.student_id = :student_id_enrollment AND e.course_id = c.id AND e.status = \'active\') '
            . 'ORDER BY c.id FOR UPDATE'
        );
        $statement->execute(['student_id' => $student['id'], 'student_id_enrollment' => $student['id']]);
        $courses = $statement->fetchAll();
        if ($courses === []) {
            throw new InvalidArgumentException('Your cart does not contain any purchasable courses.');
        }

        $subtotal = array_reduce($courses, static fn (float $sum, array $course): float => $sum + (float) $course['price'], 0.0);
        $courseIds = array_map('intval', array_column($courses, 'id'));
        $coupon = $loadCoupon($database, $couponCode, $courseIds, $subtotal);
        $discount = 0.0;
        if (is_array($coupon)) {
            $discount = $coupon['discount_type'] === 'percent'
                ? $subtotal * min(100.0, (float) $coupon['discount_value']) / 100
                : min($subtotal, (float) $coupon['discount_value']);
            if ($coupon['max_discount'] !== null) {
                $discount = min($discount, (float) $coupon['max_discount']);
            }
        }
        $discount = round(max(0, min($subtotal, $discount)), 2);
        $finalAmount = round($subtotal - $discount, 2);

        $order = $database->prepare(
            'INSERT INTO orders (student_id, coupon_id, original_amount, discount_amount, final_amount, order_status) '
            . 'VALUES (:student_id, :coupon_id, :original_amount, :discount_amount, :final_amount, \'pending\')'
        );
        $order->execute([
            'student_id' => $student['id'],
            'coupon_id' => is_array($coupon) ? (int) $coupon['id'] : null,
            'original_amount' => number_format($subtotal, 2, '.', ''),
            'discount_amount' => number_format($discount, 2, '.', ''),
            'final_amount' => number_format($finalAmount, 2, '.', ''),
        ]);
        $orderId = (int) $database->lastInsertId();
        $itemInsert = $database->prepare(
            'INSERT INTO order_items (order_id, course_id, instructor_id, course_price, discount_amount, final_price) '
            . 'VALUES (:order_id, :course_id, :instructor_id, :course_price, :discount_amount, :final_price)'
        );
        $remainingDiscount = $discount;
        foreach ($courses as $index => $course) {
            $coursePrice = (float) $course['price'];
            $itemDiscount = $index === array_key_last($courses)
                ? $remainingDiscount
                : round($subtotal > 0 ? $discount * ($coursePrice / $subtotal) : 0, 2);
            $itemDiscount = min($coursePrice, max(0, $itemDiscount));
            $remainingDiscount = round($remainingDiscount - $itemDiscount, 2);
            $itemInsert->execute([
                'order_id' => $orderId,
                'course_id' => (int) $course['id'],
                'instructor_id' => (int) $course['instructor_id'],
                'course_price' => number_format($coursePrice, 2, '.', ''),
                'discount_amount' => number_format($itemDiscount, 2, '.', ''),
                'final_price' => number_format($coursePrice - $itemDiscount, 2, '.', ''),
            ]);
        }
        $clear = $database->prepare('DELETE FROM cart WHERE student_id = :student_id');
        $clear->execute(['student_id' => $student['id']]);
        $database->commit();
        $respond([
            'message' => 'Order created. Choose a payment method to complete enrollment.',
            'data' => [
                'order_id' => $orderId,
                'original_amount' => number_format($subtotal, 2, '.', ''),
                'discount_amount' => number_format($discount, 2, '.', ''),
                'final_amount' => number_format($finalAmount, 2, '.', ''),
                'status' => 'pending',
            ],
        ], 201);
    }

    if ($path === '/api/v1/orders/mine' && $method === 'GET') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $statement = $database->prepare(
            'SELECT o.id, o.original_amount, o.discount_amount, o.final_amount, o.order_status, o.created_at, '
            . 'COUNT(oi.id) AS item_count, p.id AS payment_id, p.payment_method, p.payment_status '
            . 'FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.id LEFT JOIN payments p ON p.order_id = o.id '
            . 'WHERE o.student_id = :student_id GROUP BY o.id, p.id ORDER BY o.created_at DESC'
        );
        $statement->execute(['student_id' => $student['id']]);
        $respond(['data' => $statement->fetchAll()]);
    }

    if (preg_match('#^/api/v1/orders/(\d+)$#', $path, $matches) === 1 && $method === 'GET') {
        $user = ServiceAuth::requireUser($database, $authorization);
        $conditions = $user['role'] === 'admin' ? 'o.id = :id' : 'o.id = :id AND o.student_id = :student_id';
        $parameters = ['id' => (int) $matches[1]];
        if ($user['role'] !== 'admin') {
            if ($user['role'] !== 'student') {
                throw new ServiceAuthorizationException('This order is not available in your portal.');
            }
            $parameters['student_id'] = $user['id'];
        }
        $order = $database->prepare('SELECT * FROM orders o WHERE ' . $conditions . ' LIMIT 1');
        $order->execute($parameters);
        $record = $order->fetch();
        if (!is_array($record)) {
            $respond(['error' => 'Order not found.'], 404);
        }
        $items = $database->prepare(
            'SELECT oi.*, c.title, c.thumbnail, u.full_name AS instructor_name FROM order_items oi '
            . 'INNER JOIN courses c ON c.id = oi.course_id INNER JOIN users u ON u.id = oi.instructor_id '
            . 'WHERE oi.order_id = :order_id ORDER BY oi.id'
        );
        $items->execute(['order_id' => (int) $record['id']]);
        $record['items'] = $items->fetchAll();
        $respond(['data' => $record]);
    }

    $respond(['error' => 'Commerce route not found.'], 404);
} catch (ServiceAuthenticationException $exception) {
    $respond(['error' => $exception->getMessage()], 401);
} catch (ServiceAuthorizationException $exception) {
    $respond(['error' => $exception->getMessage()], 403);
} catch (InvalidArgumentException $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    $respond(['error' => $exception->getMessage()], 422);
} catch (JsonException) {
    $respond(['error' => 'Malformed JSON request.'], 400);
} catch (PDOException $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Commerce database failure: ' . $exception->getMessage());
    $respond(['error' => 'Commerce request could not be completed.'], 409);
} catch (Throwable $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Commerce service failure: ' . $exception->getMessage());
    $respond(['error' => 'Commerce service is unavailable.'], 503);
}
