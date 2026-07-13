<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/StudentMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

StudentMiddleware::handle();

/** @var mysqli $conn */
$conn = database_connection();

$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);
$message = '';
$messageType = '';

if (isset($_GET['added'])) {
    $message = 'Course added to cart successfully.';
    $messageType = 'success';
} elseif (isset($_GET['removed'])) {
    $message = 'Course removed from cart.';
    $messageType = 'success';
} elseif (isset($_GET['free_courses'])) {
    $message = 'Free courses do not need checkout. Use Enroll Free on each free course first.';
    $messageType = 'warning';
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
    INNER JOIN categories cat ON c.category_id = cat.id
    WHERE cart.student_id = ?
      AND c.status = 'published'
      AND u.role = 'instructor'
      AND u.status = 'active'
      AND cat.status = 'active'
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

$totalAmount = 0.0;
$hasFreeItems = false;
$hasPaidItems = false;

foreach ($cartItems as $item) {
    $price = (float) $item['price'];
    $totalAmount += max(0, $price);
    $hasFreeItems = $hasFreeItems || $price <= 0;
    $hasPaidItems = $hasPaidItems || $price > 0;
}

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
                <p>Paid courses continue to checkout. Free courses activate immediately through Enroll Free.</p>
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
                <p>Add a paid course to your cart or enroll in a free course directly from Student Browse.</p>
                <a href="student-browse-courses.php" class="browse-btn">Browse Courses</a>
            </div>
        <?php else: ?>
            <?php if ($hasFreeItems): ?>
                <div class="cart-alert warning">
                    Free courses require no payment. Select <strong>Enroll Free</strong> on those cards before proceeding to checkout.
                </div>
            <?php endif; ?>

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

                        $courseId = (int) $item['course_id'];
                        $isFree = (float) $item['price'] <= 0;
                        $detailsUrl = 'course-details.php?slug=' . rawurlencode((string) $item['slug']);
                        $actions = [
                            ['label' => 'View', 'href' => $detailsUrl, 'style' => 'secondary'],
                        ];

                        if ($isFree) {
                            $actions[] = [
                                'label' => 'Enroll Free',
                                'href' => 'enroll-free-course.php',
                                'method' => 'post',
                                'style' => 'primary',
                                'hidden' => ['course_id' => $courseId],
                            ];
                        }

                        $actions[] = [
                            'label' => 'Remove',
                            'href' => 'remove-cart-item.php',
                            'method' => 'post',
                            'style' => 'danger',
                            'hidden' => ['cart_id' => (int) $item['cart_id']],
                            'confirm' => 'Remove this course from your cart?',
                        ];

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
                            'price' => $isFree ? 'Free' : 'Rs. ' . number_format((float) $item['price'], 2),
                            'href' => $detailsUrl,
                            'rating_label' => $isFree ? 'No payment required' : 'Ready for checkout',
                            'metrics' => [
                                ['label' => 'Lessons', 'value' => (string) (int) $item['lesson_count']],
                                ['label' => 'Access', 'value' => 'Lifetime'],
                            ],
                            'actions' => $actions,
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

                    <?php if ($hasFreeItems): ?>
                        <p class="checkout-note">Enroll in the free course cards first. They are never sent to payment checkout.</p>
                    <?php elseif ($hasPaidItems): ?>
                        <a href="checkout.php" class="checkout-btn">Proceed to Checkout</a>
                    <?php endif; ?>

                    <a href="student-my-courses.php" class="continue-btn">My Courses</a>
                    <a href="student-browse-courses.php" class="continue-btn">Browse More Courses</a>
                </aside>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../layouts/panel_end.php'; ?>
