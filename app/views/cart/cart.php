<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/StudentMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

StudentMiddleware::handle();

$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);
$message = '';
$messageType = '';

if (isset($_GET['added'])) {
    $message = 'Course added to cart successfully.';
    $messageType = 'success';
}

if (isset($_GET['removed'])) {
    $message = 'Course removed from cart.';
    $messageType = 'success';
}

$cartItems = [];
$sql = "
    SELECT
        cart.id AS cart_id,
        c.id AS course_id,
        c.title,
        c.slug,
        c.short_description,
        c.thumbnail,
        c.price,
        c.level,
        c.language,
        c.duration,
        c.status,
        c.instructor_id,
        cat.name AS category_name,
        u.full_name AS instructor_name,
        (SELECT COUNT(*)
         FROM course_lessons lesson
         INNER JOIN course_sections section ON section.id = lesson.section_id
         WHERE section.course_id = c.id) AS lesson_count
    FROM cart
    INNER JOIN courses c ON cart.course_id = c.id
    INNER JOIN users u ON c.instructor_id = u.id
    LEFT JOIN categories cat ON c.category_id = cat.id
    WHERE cart.student_id = ?
      AND c.status = 'published'
    ORDER BY cart.created_at DESC
";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($result && $row = $result->fetch_assoc()) {
        $cartItems[] = $row;
    }

    $stmt->close();
}

$totalAmount = array_reduce(
    $cartItems,
    static fn (float $total, array $item): float => $total + (float) $item['price'],
    0.0
);

function cart_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$pageTitle = 'My cart';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/student_navbar.php';
?>

<main class="cart-page">
    <section class="cart-wrapper">
        <header class="cart-header">
            <div>
                <p class="page-label">Student purchase pipeline</p>
                <h1>My Cart</h1>
                <p>Review the same published course records you selected in Student Browse before creating an order.</p>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="cart-alert <?php echo cart_h($messageType); ?>">
                <?php echo cart_h($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!$cartItems): ?>
            <div class="empty-cart-box">
                <div class="empty-icon">Empty cart</div>
                <h2>Your cart is empty</h2>
                <p>Add a published course from Student Browse before checkout.</p>
                <a href="student-browse-courses.php" class="browse-btn">Browse Courses</a>
            </div>
        <?php else: ?>
            <div class="cart-layout">
                <div class="cart-items-list" data-page-size="12">
                    <?php foreach ($cartItems as $item): ?>
                        <?php
                        $thumbnail = basename((string) ($item['thumbnail'] ?? ''));
                        $thumbnailPath = $thumbnail !== ''
                            ? 'assets/uploads/course_thumbnails/' . rawurlencode($thumbnail)
                            : 'assets/images/course-placeholder.svg';

                        if ($thumbnail !== '' && !is_file(PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $thumbnailPath))) {
                            $thumbnailPath = 'assets/images/course-placeholder.svg';
                        }

                        $detailsUrl = 'course-details.php?slug=' . rawurlencode((string) $item['slug']);
                        $courseCard = [
                            'context' => 'cart',
                            'title' => $item['title'],
                            'summary' => $item['short_description'] ?: 'No description added.',
                            'thumbnail' => $thumbnailPath,
                            'category' => $item['category_name'] ?: 'General',
                            'badge' => ucfirst((string) $item['level']),
                            'eyebrow' => 'By ' . $item['instructor_name'],
                            'language' => $item['language'] ?: 'Language not set',
                            'duration' => $item['duration'] ?: 'Self-paced',
                            'price' => (float) $item['price'] > 0
                                ? 'Rs. ' . number_format((float) $item['price'], 2)
                                : 'Free',
                            'href' => $detailsUrl,
                            'rating_label' => 'Ready for checkout',
                            'metrics' => [
                                ['label' => 'Lessons', 'value' => (string) (int) $item['lesson_count']],
                                ['label' => 'Access', 'value' => 'Lifetime'],
                            ],
                            'actions' => [
                                ['label' => 'View', 'href' => $detailsUrl, 'style' => 'secondary'],
                                [
                                    'label' => 'Remove',
                                    'href' => 'remove-cart-item.php',
                                    'method' => 'post',
                                    'style' => 'danger',
                                    'hidden' => ['cart_id' => (int) $item['cart_id']],
                                    'confirm' => 'Remove this course from your cart?',
                                ],
                            ],
                        ];

                        require __DIR__ . '/../components/course_card.php';
                        ?>
                    <?php endforeach; ?>
                </div>

                <aside class="cart-summary-card">
                    <h2>Order Summary</h2>

                    <div class="summary-row">
                        <span>Total Courses</span>
                        <strong><?php echo count($cartItems); ?></strong>
                    </div>

                    <div class="summary-row">
                        <span>Subtotal</span>
                        <strong>Rs. <?php echo number_format($totalAmount, 2); ?></strong>
                    </div>

                    <div class="summary-row">
                        <span>Discount</span>
                        <strong>Rs. 0.00</strong>
                    </div>

                    <div class="summary-total">
                        <span>Total</span>
                        <strong>Rs. <?php echo number_format($totalAmount, 2); ?></strong>
                    </div>

                    <a href="checkout.php" class="checkout-btn">Proceed to Checkout</a>
                    <a href="student-my-courses.php" class="continue-btn">My Courses</a>
                    <a href="student-browse-courses.php" class="continue-btn">Browse More Courses</a>
                </aside>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../layouts/panel_end.php'; ?>