<?php

require_once __DIR__ . '/../../middleware/InstructorMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

InstructorMiddleware::handle();

$user = Auth::user();
$instructorId = (int) ($user['id'] ?? 0);

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function valid_date($date)
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
}

function money($amount)
{
    return 'Rs. ' . number_format((float) $amount, 2);
}

function earning_status_label($status)
{
    if ($status === 'available') {
        return 'Pending Admin Payout';
    }

    if ($status === 'withdraw_requested') {
        return 'Withdrawal Requested';
    }

    if ($status === 'paid') {
        return 'Paid to Instructor';
    }

    if ($status === 'cancelled') {
        return 'Cancelled';
    }

    if ($status === 'pending') {
        return 'Pending Student Payment';
    }

    return ucfirst((string) $status);
}
function earning_status_class($status)
{
    if ($status === 'available') {
        return 'status-available';
    }

    if ($status === 'withdraw_requested') {
        return 'status-requested';
    }

    if ($status === 'paid') {
        return 'status-paid';
    }

    if ($status === 'cancelled') {
        return 'status-cancelled';
    }

    return 'status-pending';
}

function get_stat(mysqli $conn, string $sql, string $types, array $params): float
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return 0;
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $value = 0;

    if ($result) {
        $row = $result->fetch_assoc();
        $value = (float) ($row['total'] ?? 0);
    }

    $stmt->close();

    return $value;
}

/*
|--------------------------------------------------------------------------
| Summary stats from instructor_earnings
|--------------------------------------------------------------------------
*/

$totalGrossSales = get_stat(
    $conn,
    "
        SELECT COALESCE(SUM(gross_amount), 0) AS total
        FROM instructor_earnings
        WHERE instructor_id = ?
    ",
    "i",
    [$instructorId]
);

$totalAdminCommission = get_stat(
    $conn,
    "
        SELECT COALESCE(SUM(commission_amount), 0) AS total
        FROM instructor_earnings
        WHERE instructor_id = ?
    ",
    "i",
    [$instructorId]
);

$totalAvailableBalance = get_stat(
    $conn,
    "
        SELECT COALESCE(SUM(instructor_amount), 0) AS total
        FROM instructor_earnings
        WHERE instructor_id = ?
          AND earning_status = 'available'
    ",
    "i",
    [$instructorId]
);

$totalRequestedBalance = get_stat(
    $conn,
    "
        SELECT COALESCE(SUM(instructor_amount), 0) AS total
        FROM instructor_earnings
        WHERE instructor_id = ?
          AND earning_status = 'withdraw_requested'
    ",
    "i",
    [$instructorId]
);

$totalPaidToInstructor = get_stat(
    $conn,
    "
        SELECT COALESCE(SUM(instructor_amount), 0) AS total
        FROM instructor_earnings
        WHERE instructor_id = ?
          AND earning_status = 'paid'
    ",
    "i",
    [$instructorId]
);

$totalSoldItems = (int) get_stat(
    $conn,
    "
        SELECT COUNT(*) AS total
        FROM instructor_earnings
        WHERE instructor_id = ?
    ",
    "i",
    [$instructorId]
);

$totalStudents = (int) get_stat(
    $conn,
    "
        SELECT COUNT(DISTINCT student_id) AS total
        FROM instructor_earnings
        WHERE instructor_id = ?
    ",
    "i",
    [$instructorId]
);

/*
|--------------------------------------------------------------------------
| Pending verification amount
| These are orders where student submitted payment, but admin has not approved.
|--------------------------------------------------------------------------
*/

$totalPendingVerification = get_stat(
    $conn,
    "
        SELECT COALESCE(SUM(oi.final_price), 0) AS total
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.id
        WHERE oi.instructor_id = ?
          AND o.order_status = 'pending'
    ",
    "i",
    [$instructorId]
);

/*
|--------------------------------------------------------------------------
| Earnings list filters
|--------------------------------------------------------------------------
*/

$whereParts = [
    "ie.instructor_id = ?"
];

$params = [$instructorId];
$types = "i";

if ($search !== '') {
    $whereParts[] = "(c.title LIKE ? OR student.full_name LIKE ? OR student.email LIKE ? OR p.transaction_id LIKE ?)";
    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= "ssss";
}

if (in_array($statusFilter, ['available', 'withdraw_requested', 'paid', 'cancelled'], true)) {
    $whereParts[] = "ie.earning_status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

if ($dateFrom !== '' && valid_date($dateFrom)) {
    $whereParts[] = "DATE(ie.created_at) >= ?";
    $params[] = $dateFrom;
    $types .= "s";
}

if ($dateTo !== '' && valid_date($dateTo)) {
    $whereParts[] = "DATE(ie.created_at) <= ?";
    $params[] = $dateTo;
    $types .= "s";
}

$whereSql = "WHERE " . implode(" AND ", $whereParts);

$earnings = [];

$earningsSql = "
    SELECT
        ie.id AS earning_id,
        ie.instructor_id,
        ie.course_id,
        ie.student_id,
        ie.order_id,
        ie.order_item_id,
        ie.payment_id,
        ie.gross_amount,
        ie.commission_rate,
        ie.commission_amount,
        ie.instructor_amount,
        ie.earning_status,
        ie.created_at,
        ie.paid_at,

        c.title AS course_title,
        c.thumbnail AS course_thumbnail,
        c.slug AS course_slug,

        student.full_name AS student_name,
        student.email AS student_email,
        student.phone AS student_phone,

        o.order_status,
        o.created_at AS order_date,

        p.payment_method,
        p.payment_status,
        p.transaction_id,
        p.verified_at
    FROM instructor_earnings ie
    INNER JOIN courses c ON ie.course_id = c.id
    INNER JOIN users student ON ie.student_id = student.id
    INNER JOIN orders o ON ie.order_id = o.id
    LEFT JOIN payments p ON ie.payment_id = p.id
    $whereSql
    ORDER BY ie.created_at DESC, ie.id DESC
";

$stmt = $conn->prepare($earningsSql);

if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $earnings[] = $row;
        }
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Pending verification list
|--------------------------------------------------------------------------
*/

$pendingSales = [];

$pendingSql = "
    SELECT
        oi.id AS order_item_id,
        oi.order_id,
        oi.course_id,
        oi.final_price,

        o.student_id,
        o.order_status,
        o.created_at AS order_date,

        c.title AS course_title,
        c.thumbnail AS course_thumbnail,

        student.full_name AS student_name,
        student.email AS student_email,

        p.payment_method,
        p.payment_status,
        p.transaction_id
    FROM order_items oi
    INNER JOIN orders o ON oi.order_id = o.id
    INNER JOIN courses c ON oi.course_id = c.id
    INNER JOIN users student ON o.student_id = student.id
    LEFT JOIN payments p ON p.order_id = o.id
    WHERE oi.instructor_id = ?
      AND o.order_status = 'pending'
    ORDER BY o.created_at DESC
";

$pendingStmt = $conn->prepare($pendingSql);

if ($pendingStmt) {
    $pendingStmt->bind_param("i", $instructorId);
    $pendingStmt->execute();

    $pendingResult = $pendingStmt->get_result();

    if ($pendingResult) {
        while ($row = $pendingResult->fetch_assoc()) {
            $pendingSales[] = $row;
        }
    }

    $pendingStmt->close();
}

/*
|--------------------------------------------------------------------------
| Course-wise earnings
|--------------------------------------------------------------------------
*/

$courseStats = [];

$courseStatsSql = "
    SELECT
        c.id AS course_id,
        c.title,
        c.thumbnail,
        COUNT(ie.id) AS total_sales,
        COALESCE(SUM(ie.gross_amount), 0) AS gross_revenue,
        COALESCE(SUM(ie.commission_amount), 0) AS admin_commission,
        COALESCE(SUM(ie.instructor_amount), 0) AS instructor_revenue
    FROM instructor_earnings ie
    INNER JOIN courses c ON ie.course_id = c.id
    WHERE ie.instructor_id = ?
    GROUP BY c.id, c.title, c.thumbnail
    ORDER BY instructor_revenue DESC, total_sales DESC
    LIMIT 8
";

$courseStatsStmt = $conn->prepare($courseStatsSql);

if ($courseStatsStmt) {
    $courseStatsStmt->bind_param("i", $instructorId);
    $courseStatsStmt->execute();

    $courseStatsResult = $courseStatsStmt->get_result();

    if ($courseStatsResult) {
        while ($row = $courseStatsResult->fetch_assoc()) {
            $courseStats[] = $row;
        }
    }

    $courseStatsStmt->close();
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/instructor_navbar.php';
?>



<main class="instructor-sales-page">
    <section class="instructor-sales-wrapper">

        <div class="sales-header">
            <div>
                <p class="page-label">Revenue</p>
                <h1>Sales and earnings</h1>
                <p>
                    Track student payments, admin commission, your available balance, and paid instructor payouts.
                </p>
            </div>

            <a href="instructor-withdrawals.php" class="withdraw-btn">
                Request Withdrawal
            </a>
        </div>

        <div class="sales-stats-grid">

            <div class="sales-stat-card">
                <span>Gross Sales</span>
                <strong><?php echo money($totalGrossSales); ?></strong>
                <p>Total course sales before commission</p>
            </div>

            <div class="sales-stat-card danger">
                <span>Admin Commission</span>
                <strong><?php echo money($totalAdminCommission); ?></strong>
                <p>Platform/admin commission</p>
            </div>

            <div class="sales-stat-card success">
                <span>Available Balance</span>
                <strong><?php echo money($totalAvailableBalance); ?></strong>
                <p>You can request withdrawal</p>
            </div>

            <div class="sales-stat-card warning">
                <span>Pending Verification</span>
                <strong><?php echo money($totalPendingVerification); ?></strong>
                <p>Waiting admin payment approval</p>
            </div>

            <div class="sales-stat-card request">
                <span>Withdrawal Requested</span>
                <strong><?php echo money($totalRequestedBalance); ?></strong>
                <p>Waiting admin payout</p>
            </div>

            <div class="sales-stat-card paid">
                <span>Paid to Instructor</span>
                <strong><?php echo money($totalPaidToInstructor); ?></strong>
                <p>Already paid by admin</p>
            </div>
<div class="sales-stat-card">
    <span>Gross Sales</span>
    <strong><?php echo money($totalGrossSales); ?></strong>
    <p>Total course sales before admin commission.</p>
</div>

<div class="sales-stat-card danger">
    <span>Commission Deducted</span>
    <strong><?php echo money($totalAdminCommission); ?></strong>
    <p>Admin/platform commission deducted from your sales.</p>
</div>

<div class="sales-stat-card success">
    <span>Your Net Earning</span>
    <strong><?php echo money($totalAvailableBalance + $totalRequestedBalance + $totalPaidToInstructor); ?></strong>
    <p>Your earning after commission deduction.</p>
</div>

<div class="sales-stat-card warning">
    <span>Pending Admin Payout</span>
    <strong><?php echo money($totalAvailableBalance); ?></strong>
    <p>Admin has not sent this amount yet.</p>
</div>

<div class="sales-stat-card paid">
    <span>Paid to You</span>
    <strong><?php echo money($totalPaidToInstructor); ?></strong>
    <p>Admin already marked this amount as paid.</p>
</div>
            <div class="sales-stat-card">
                <span>Total Sales</span>
                <strong><?php echo $totalSoldItems; ?></strong>
                <p>Verified sold course items</p>
            </div>

            <div class="sales-stat-card">
                <span>Students</span>
                <strong><?php echo $totalStudents; ?></strong>
                <p>Unique verified students</p>
            </div>

        </div>

        <form method="GET" class="sales-filter-box">

            <div class="form-group">
                <label>Search</label>
                <input
                    type="text"
                    name="search"
                    value="<?php echo h($search); ?>"
                    placeholder="Search course, student, email, transaction"
                >
            </div>

            <div class="form-group">
                <label>Earning Status</label>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="available" <?php echo $statusFilter === 'available' ? 'selected' : ''; ?>>Available</option>
                    <option value="withdraw_requested" <?php echo $statusFilter === 'withdraw_requested' ? 'selected' : ''; ?>>Withdrawal Requested</option>
                    <option value="paid" <?php echo $statusFilter === 'paid' ? 'selected' : ''; ?>>Paid to Instructor</option>
                    <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>

            <div class="form-group">
                <label>From</label>
                <input type="date" name="date_from" value="<?php echo h($dateFrom); ?>">
            </div>

            <div class="form-group">
                <label>To</label>
                <input type="date" name="date_to" value="<?php echo h($dateTo); ?>">
            </div>

            <div class="filter-actions">
                <button type="submit">Filter</button>
                <a href="instructor-sales.php">Reset</a>
            </div>

        </form>

        <?php if (!empty($pendingSales)): ?>
            <div class="pending-box">
                <h2>Pending Payment Verification</h2>
                <p>
                    These sales are waiting for admin approval. After admin verifies payment,
                    they will move to your available balance.
                </p>

                <div class="pending-list">
                    <?php foreach ($pendingSales as $pending): ?>
                        <div class="pending-card">
                            <div>
                                <strong><?php echo h($pending['course_title']); ?></strong>
                                <p>
                                    Student: <?php echo h($pending['student_name']); ?>
                                    &middot; Order #<?php echo (int) $pending['order_id']; ?>
                                </p>
                            </div>

                            <span><?php echo money($pending['final_price']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="sales-layout">

            <div class="sales-main">

                <div class="section-title-box">
                    <h2>Verified Earnings</h2>
                    <p>These records are created after admin verifies student payment.</p>
                </div>

                <?php if (empty($earnings)): ?>

                    <div class="empty-sales-box">
                        <div class="empty-icon">No sales</div>
                        <h2>No verified earnings found</h2>
                        <p>Your verified earnings will appear here after admin approves student payments.</p>
                    </div>

                <?php else: ?>

                    <div class="sales-table-card">
                        <div class="sales-table-scroll">
                            <table class="sales-table">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Course</th>
                                        <th>Student</th>
                                        <th>Gross</th>
                                        <th>Commission</th>
                                        <th>Your Earning</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($earnings as $earning): ?>
                                        <tr>
                                            <td>
                                                <strong>#<?php echo (int) $earning['order_id']; ?></strong>
                                            </td>

                                            <td>
                                                <div class="course-cell">
                                                    <?php
                                                        $thumbnail = $earning['course_thumbnail'] ?? '';
                                                        $thumbnailPath = $thumbnail !== ''
                                                            ? 'assets/uploads/course_thumbnails/' . $thumbnail
                                                            : 'assets/images/course-placeholder.svg';
                                                    ?>

                                                    <img
                                                        src="<?php echo h($thumbnailPath); ?>"
                                                        alt="<?php echo h($earning['course_title']); ?>"
                                                    >

                                                    <span><?php echo h($earning['course_title']); ?></span>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="student-cell">
                                                    <strong><?php echo h($earning['student_name']); ?></strong>
                                                    <span><?php echo h($earning['student_email']); ?></span>
                                                </div>
                                            </td>

                                            <td>
                                                <strong><?php echo money($earning['gross_amount']); ?></strong>
                                            </td>

                                            <td>
                                                <div class="commission-cell">
                                                    <strong><?php echo money($earning['commission_amount']); ?></strong>
                                                    <span><?php echo number_format((float) $earning['commission_rate'], 2); ?>%</span>
                                                </div>
                                            </td>

                                            <td>
                                                <strong class="amount-text">
                                                    <?php echo money($earning['instructor_amount']); ?>
                                                </strong>
                                            </td>

                                            <td>
                                                <span class="status-pill <?php echo earning_status_class($earning['earning_status']); ?>">
                                                    <?php echo earning_status_label($earning['earning_status']); ?>
                                                </span>
                                            </td>

                                            <td>
                                                <?php echo h(date('M d, Y', strtotime($earning['created_at']))); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php endif; ?>

            </div>

            <aside class="sales-side">

                <div class="section-title-box">
                    <h2>Top Courses</h2>
                    <p>Your best performing courses after commission tracking.</p>
                </div>

                <?php if (empty($courseStats)): ?>

                    <div class="side-empty-box">
                        No verified course earnings yet.
                    </div>

                <?php else: ?>

                    <div class="top-course-list">

                        <?php foreach ($courseStats as $course): ?>
                            <?php
                                $thumbnail = $course['thumbnail'] ?? '';
                                $thumbnailPath = $thumbnail !== ''
                                    ? 'assets/uploads/course_thumbnails/' . $thumbnail
                                    : 'assets/images/course-placeholder.svg';
                            ?>

                            <div class="top-course-card">
                                <img
                                    src="<?php echo h($thumbnailPath); ?>"
                                    alt="<?php echo h($course['title']); ?>"
                                >

                                <div>
                                    <h3><?php echo h($course['title']); ?></h3>
                                    <p><?php echo (int) $course['total_sales']; ?> sale(s)</p>
                                    <small>Gross: <?php echo money($course['gross_revenue']); ?></small>
                                    <small>Commission: <?php echo money($course['admin_commission']); ?></small>
                                    <strong>Your earning: <?php echo money($course['instructor_revenue']); ?></strong>
                                </div>
                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </aside>

        </div>

    </section>
</main>

</body>
</html>
