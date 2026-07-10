<?php

require_once '../app/middleware/StudentMiddleware.php';
require_once '../app/config/database.php';

StudentMiddleware::handle();
Security::requirePost();

$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);
$cartId = (int) ($_POST['cart_id'] ?? 0);

if ($cartId > 0) {
    $sql = "DELETE FROM cart WHERE id = ? AND student_id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("ii", $cartId, $studentId);
        $stmt->execute();
        $stmt->close();
    }
}

header("Location: cart.php?removed=1");
exit;
