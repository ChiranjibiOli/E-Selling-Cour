<?php

require_once '../app/middleware/StudentMiddleware.php';
require_once '../app/config/database.php';

StudentMiddleware::handle();

$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);
$orderId = (int) ($_GET['order_id'] ?? 0);

if ($orderId <= 0) {
    Auth::redirect('student-my-courses.php');
}

$orderStmt = $conn->prepare('SELECT id FROM orders WHERE id = ? AND student_id = ? LIMIT 1');
$orderStmt->bind_param('ii', $orderId, $studentId);
$orderStmt->execute();
$orderResult = $orderStmt->get_result();
$ownsOrder = $orderResult && $orderResult->num_rows === 1;
$orderStmt->close();

if (!$ownsOrder) {
    http_response_code(404);
    exit('Order not found.');
}

require_once '../app/views/layouts/header.php';
require_once '../app/views/layouts/student_navbar.php';
?>

<link rel="stylesheet" href="assets/css/cart_checkout.css">

<main class="checkout-success-page">
    <section class="checkout-success-wrapper">
        <div class="success-card">
            <div class="success-icon">Submitted</div>

            <h1>Payment proof submitted</h1>

            <p>
                Your order has been submitted successfully. Admin will verify your payment.
                After verification, your course access will be activated in My Courses.
            </p>

            <div class="order-number-box">
                Order ID: #<?php echo $orderId; ?>
            </div>

            <div class="success-actions">
                <a href="student-my-courses.php">My Courses</a>
                <a href="student-browse-courses.php" class="secondary">Browse More Courses</a>
                <a href="student-dashboard.php" class="secondary">Go to Dashboard</a>
            </div>
        </div>
    </section>
</main>

<?php require_once '../app/views/layouts/panel_end.php'; ?>