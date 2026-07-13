<?php

declare(strict_types=1);

require_once '../app/middleware/StudentMiddleware.php';
require_once '../app/config/database.php';

StudentMiddleware::handle();

/** @var mysqli $conn */
$conn = database_connection();

$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);

$freeStmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM cart
    INNER JOIN courses c ON c.id = cart.course_id
    WHERE cart.student_id = ?
      AND c.status = 'published'
      AND c.price <= 0
");
$freeStmt->bind_param('i', $studentId);
$freeStmt->execute();
$freeRow = $freeStmt->get_result()->fetch_assoc() ?: [];
$freeStmt->close();

if ((int) ($freeRow['total'] ?? 0) > 0) {
    Auth::redirect('cart.php?free_courses=1');
}

require_once '../app/views/cart/checkout.php';
