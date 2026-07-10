<?php

require_once __DIR__ . '/../../middleware/AdminMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

AdminMiddleware::handle();

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

function date_filter_sql($column, $dateFrom, $dateTo)
{
    $sql = '';

    if ($dateFrom !== '' && valid_date($dateFrom)) {
        $sql .= " AND DATE($column) >= '" . addslashes($dateFrom) . "'";
    }

    if ($dateTo !== '' && valid_date($dateTo)) {
        $sql .= " AND DATE($column) <= '" . addslashes($dateTo) . "'";
    }

    return $sql;
}

function safe_count(mysqli $conn, string $sql): int
{
    try {
        $result = $conn->query($sql);

        if ($result) {
            $row = $result->fetch_assoc();
            return (int) ($row['total'] ?? 0);
        }
    } catch (Throwable $e) {
        return 0;
    }

    return 0;
}

function safe_sum(mysqli $conn, string $sql): float
{
    try {
        $result = $conn->query($sql);

        if ($result) {
            $row = $result->fetch_assoc();
            return (float) ($row['total'] ?? 0);
        }
    } catch (Throwable $e) {
        return 0;
    }

    return 0;
}

function safe_rows(mysqli $conn, string $sql): array
{
    $rows = [];

    try {
        $result = $conn->query($sql);

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
    } catch (Throwable $e) {
        return [];
    }

    return $rows;
}

$orderDateFilter = date_filter_sql('o.created_at', $dateFrom, $dateTo);
$userDateFilter = date_filter_sql('created_at', $dateFrom, $dateTo);
$courseDateFilter = date_filter_sql('created_at', $dateFrom, $dateTo);
$enrollmentDateFilter = date_filter_sql('created_at', $dateFrom, $dateTo);

$totalStudents = safe_count(
    $conn,
    "SELECT COUNT(*) AS total FROM users WHERE role = 'student' $userDateFilter"
);

$totalInstructors = safe_count(
    $conn,
    "SELECT COUNT(*) AS total FROM users WHERE role = 'instructor' $userDateFilter"
);

$totalCourses = safe_count(
    $conn,
    "SELECT COUNT(*) AS total FROM courses WHERE 1=1 $courseDateFilter"
);

$publishedCourses = safe_count(
    $conn,
    "SELECT COUNT(*) AS total FROM courses WHERE status = 'published' $courseDateFilter"
);

$pendingCourses = safe_count(
    $conn,
    "SELECT COUNT(*) AS total FROM courses WHERE status = 'pending' $courseDateFilter"
);

$rejectedCourses = safe_count(
    $conn,
    "SELECT COUNT(*) AS total FROM courses WHERE status = 'rejected' $courseDateFilter"
);

$totalOrders = safe_count(
    $conn,
    "SELECT COUNT(*) AS total FROM orders o WHERE 1=1 $orderDateFilter"
);

$paidOrders = safe_count(
    $conn,
    "SELECT COUNT(*) AS total FROM orders o WHERE o.order_status = 'paid' $orderDateFilter"
);

$pendingOrders = safe_count(
    $conn,
    "SELECT COUNT(*) AS total FROM orders o WHERE o.order_status = 'pending' $orderDateFilter"
);

$totalRevenue = safe_sum(
    $conn,
    "SELECT COALESCE(SUM(o.final_amount), 0) AS total FROM orders o WHERE o.order_status = 'paid' $orderDateFilter"
);

$totalEnrollments = safe_count(
    $conn,
    "SELECT COUNT(*) AS total FROM enrollments WHERE 1=1 $enrollmentDateFilter"
);

$topCoursesSql = "
    SELECT 
        c.id,
        c.title,
        c.price,
        COUNT(oi.id) AS total_sales,
        COALESCE(SUM(oi.final_price), 0) AS total_revenue
    FROM order_items oi
    INNER JOIN orders o ON oi.order_id = o.id
    INNER JOIN courses c ON oi.course_id = c.id
    WHERE o.order_status = 'paid'
    $orderDateFilter
    GROUP BY c.id, c.title, c.price
    ORDER BY total_sales DESC, total_revenue DESC
    LIMIT 5
";

$topCourses = safe_rows($conn, $topCoursesSql);

$topInstructorsSql = "
    SELECT 
        u.id,
        u.full_name,
        u.email,
        COUNT(oi.id) AS total_sales,
        COALESCE(SUM(oi.final_price), 0) AS total_revenue
    FROM order_items oi
    INNER JOIN orders o ON oi.order_id = o.id
    INNER JOIN courses c ON oi.course_id = c.id
    INNER JOIN users u ON c.instructor_id = u.id
    WHERE o.order_status = 'paid'
    $orderDateFilter
    GROUP BY u.id, u.full_name, u.email
    ORDER BY total_revenue DESC, total_sales DESC
    LIMIT 5
";

$topInstructors = safe_rows($conn, $topInstructorsSql);

$recentOrdersSql = "
    SELECT 
        o.id,
        o.final_amount,
        o.order_status,
        o.created_at,
        u.full_name AS student_name,
        u.email AS student_email
    FROM orders o
    INNER JOIN users u ON o.student_id = u.id
    WHERE 1=1
    $orderDateFilter
    ORDER BY o.created_at DESC
    LIMIT 8
";

$recentOrders = safe_rows($conn, $recentOrdersSql);

$monthlyRevenueSql = "
    SELECT 
        DATE_FORMAT(o.created_at, '%Y-%m') AS report_month,
        COALESCE(SUM(o.final_amount), 0) AS revenue,
        COUNT(o.id) AS orders_count
    FROM orders o
    WHERE o.order_status = 'paid'
    GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
    ORDER BY report_month DESC
    LIMIT 6
";

$monthlyRevenue = safe_rows($conn, $monthlyRevenueSql);

function status_label($status)
{
    if ($status === 'paid') {
        return 'Paid';
    }

    if ($status === 'pending') {
        return 'Pending';
    }

    if ($status === 'failed') {
        return 'Failed';
    }

    if ($status === 'cancelled') {
        return 'Cancelled';
    }

    return ucfirst((string) $status);
}

function status_class($status)
{
    if ($status === 'paid') {
        return 'status-paid';
    }

    if ($status === 'pending') {
        return 'status-pending';
    }

    return 'status-failed';
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/admin_navbar.php';
?>



<main class="admin-reports-page">
    <section class="admin-reports-wrapper">

        <div class="admin-reports-header">
            <div>
                <p class="page-label">Admin Panel</p>
                <h1>Reports & Analytics</h1>
                <p>
                    View platform performance, users, courses, revenue, orders, enrollments, and top course statistics.
                </p>
            </div>
        </div>

        <form method="GET" class="report-filter-box">
            <div class="form-group">
                <label>From Date</label>
                <input type="date" name="date_from" value="<?php echo h($dateFrom); ?>">
            </div>

            <div class="form-group">
                <label>To Date</label>
                <input type="date" name="date_to" value="<?php echo h($dateTo); ?>">
            </div>

            <div class="filter-actions">
                <button type="submit">Apply Filter</button>
                <a href="admin-reports.php">Reset</a>
            </div>
        </form>

        <div class="report-stats-grid">

            <div class="report-stat-card">
                <span>Students</span>
                <strong><?php echo $totalStudents; ?></strong>
                <p>Total student accounts</p>
            </div>

            <div class="report-stat-card">
                <span>Instructors</span>
                <strong><?php echo $totalInstructors; ?></strong>
                <p>Total instructor accounts</p>
            </div>

            <div class="report-stat-card">
                <span>Total Courses</span>
                <strong><?php echo $totalCourses; ?></strong>
                <p>All created courses</p>
            </div>

            <div class="report-stat-card success">
                <span>Published Courses</span>
                <strong><?php echo $publishedCourses; ?></strong>
                <p>Visible to students</p>
            </div>

            <div class="report-stat-card warning">
                <span>Pending Courses</span>
                <strong><?php echo $pendingCourses; ?></strong>
                <p>Waiting approval</p>
            </div>

            <div class="report-stat-card danger">
                <span>Rejected Courses</span>
                <strong><?php echo $rejectedCourses; ?></strong>
                <p>Rejected by admin</p>
            </div>

            <div class="report-stat-card">
                <span>Total Orders</span>
                <strong><?php echo $totalOrders; ?></strong>
                <p>All purchase orders</p>
            </div>

            <div class="report-stat-card success">
                <span>Paid Orders</span>
                <strong><?php echo $paidOrders; ?></strong>
                <p>Verified orders</p>
            </div>

            <div class="report-stat-card warning">
                <span>Pending Orders</span>
                <strong><?php echo $pendingOrders; ?></strong>
                <p>Need verification</p>
            </div>

            <div class="report-stat-card success">
                <span>Total Revenue</span>
                <strong>Rs. <?php echo number_format($totalRevenue, 2); ?></strong>
                <p>Paid order revenue</p>
            </div>

            <div class="report-stat-card">
                <span>Enrollments</span>
                <strong><?php echo $totalEnrollments; ?></strong>
                <p>Course access granted</p>
            </div>

        </div>

        <div class="report-grid">

            <div class="report-panel">
                <div class="panel-header">
                    <h2>Top Selling Courses</h2>
                    <p>Based on paid orders</p>
                </div>

                <?php if (empty($topCourses)): ?>
                    <p class="empty-text">No course sales found.</p>
                <?php else: ?>
                    <div class="report-list">
                        <?php foreach ($topCourses as $course): ?>
                            <div class="report-list-item">
                                <div>
                                    <h3><?php echo h($course['title']); ?></h3>
                                    <p><?php echo (int) $course['total_sales']; ?> sale(s)</p>
                                </div>

                                <strong>Rs. <?php echo number_format((float) $course['total_revenue'], 2); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="report-panel">
                <div class="panel-header">
                    <h2>Top Instructors</h2>
                    <p>Based on revenue from paid orders</p>
                </div>

                <?php if (empty($topInstructors)): ?>
                    <p class="empty-text">No instructor revenue found.</p>
                <?php else: ?>
                    <div class="report-list">
                        <?php foreach ($topInstructors as $instructor): ?>
                            <div class="report-list-item">
                                <div>
                                    <h3><?php echo h($instructor['full_name']); ?></h3>
                                    <p><?php echo h($instructor['email']); ?></p>
                                </div>

                                <strong>Rs. <?php echo number_format((float) $instructor['total_revenue'], 2); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="report-panel">
                <div class="panel-header">
                    <h2>Monthly Revenue</h2>
                    <p>Last 6 paid months</p>
                </div>

                <?php if (empty($monthlyRevenue)): ?>
                    <p class="empty-text">No monthly revenue found.</p>
                <?php else: ?>
                    <div class="report-list">
                        <?php foreach ($monthlyRevenue as $month): ?>
                            <div class="report-list-item">
                                <div>
                                    <h3><?php echo h($month['report_month']); ?></h3>
                                    <p><?php echo (int) $month['orders_count']; ?> paid order(s)</p>
                                </div>

                                <strong>Rs. <?php echo number_format((float) $month['revenue'], 2); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="report-panel">
                <div class="panel-header">
                    <h2>Recent Orders</h2>
                    <p>Latest purchase activity</p>
                </div>

                <?php if (empty($recentOrders)): ?>
                    <p class="empty-text">No recent orders found.</p>
                <?php else: ?>
                    <div class="report-list">
                        <?php foreach ($recentOrders as $order): ?>
                            <div class="report-list-item">
                                <div>
                                    <h3>Order #<?php echo (int) $order['id']; ?></h3>
                                    <p>
                                        <?php echo h($order['student_name']); ?>
                                        &middot; <?php echo h(date('M d, Y', strtotime($order['created_at']))); ?>
                                    </p>
                                </div>

                                <div class="right-info">
                                    <span class="status-pill <?php echo status_class($order['order_status']); ?>">
                                        <?php echo status_label($order['order_status']); ?>
                                    </span>

                                    <strong>Rs. <?php echo number_format((float) $order['final_amount'], 2); ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </section>
</main>

</body>
</html>
