<?php

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
        c.status,
        c.instructor_id,
        cat.name AS category_name,
        u.full_name AS instructor_name
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
    $stmt->bind_param("i", $studentId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $cartItems[] = $row;
        }
    }

    $stmt->close();
}

$totalAmount = 0;

foreach ($cartItems as $item) {
    $totalAmount += (float) $item['price'];
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function level_label($level)
{
    if ($level === 'beginner') {
        return 'Beginner';
    }

    if ($level === 'intermediate') {
        return 'Intermediate';
    }

    if ($level === 'advanced') {
        return 'Advanced';
    }

    return ucfirst((string) $level);
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/student_navbar.php';
?>



<main class="cart-page">
    <section class="cart-wrapper">

        <div class="cart-header">
            <div>
                <p class="page-label">Student Cart</p>
                <h1>My Cart</h1>
                <p>Review your selected courses before checkout.</p>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="cart-alert <?php echo h($messageType); ?>">
                <?php echo h($message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($cartItems)): ?>

            <div class="empty-cart-box">
                <div class="empty-icon">Empty cart</div>
                <h2>Your cart is empty</h2>
                <p>Add published courses to your cart before checkout.</p>
                <a href="courses.php" class="browse-btn">Browse Courses</a>
            </div>

        <?php else: ?>

            <div class="cart-layout">

                <div class="cart-items-list">

                    <?php foreach ($cartItems as $item): ?>
                        <?php
                            $thumbnail = $item['thumbnail'] ?? '';

                            $thumbnailPath = $thumbnail !== ''
                                ? 'assets/uploads/course_thumbnails/' . $thumbnail
                                : 'assets/images/course-placeholder.svg';

                            if ($thumbnail !== '' && !is_file(PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $thumbnailPath))) {
                                $thumbnailPath = 'assets/images/course-placeholder.svg';
                            }
                        ?>

                        <article class="cart-item-card">

                            <div class="cart-course-image">
                                <img 
                                    src="<?php echo h($thumbnailPath); ?>" 
                                    alt="<?php echo h($item['title']); ?>"
                                >
                            </div>

                            <div class="cart-course-info">
                                <div class="cart-course-tags">
                                    <span><?php echo h(level_label($item['level'])); ?></span>
                                    <span><?php echo h($item['language']); ?></span>
                                    <span><?php echo h($item['category_name'] ?: 'General'); ?></span>
                                </div>

                                <h2><?php echo h($item['title']); ?></h2>

                                <p><?php echo h($item['short_description'] ?: 'No description added.'); ?></p>

                                <div class="cart-meta">
                                    <span>Instructor: <strong><?php echo h($item['instructor_name']); ?></strong></span>
                                    <span>Price: <strong>Rs. <?php echo number_format((float) $item['price'], 2); ?></strong></span>
                                </div>

                                <div class="cart-actions">
                                    <a href="course-details.php?slug=<?php echo urlencode($item['slug']); ?>">
                                        View Details
                                    </a>

                                    <form method="POST" action="remove-cart-item.php">
                                          <?php echo csrf_field(); ?>
                                        <input type="hidden" name="cart_id" value="<?php echo (int) $item['cart_id']; ?>">

                                        <button type="submit">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </div>

                        </article>

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

                    <a href="checkout.php" class="checkout-btn">
                        Proceed to Checkout
                    </a>

               <a href="student-my-courses.php" class="continue-btn">
    My Courses
</a>

<a href="courses.php" class="continue-btn">
    Browse More Courses
</a>
                </aside>

            </div>

        <?php endif; ?>

    </section>
</main>

