<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/AdminMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

AdminMiddleware::handle();

function admin_dashboard_scalar(mysqli $conn, string $sql): float
{
    try {
        $result = $conn->query($sql);
        $row = $result ? $result->fetch_row() : [0];
        return (float) ($row[0] ?? 0);
    } catch (mysqli_sql_exception $exception) {
        error_log('Admin dashboard metric query failed: ' . $exception->getMessage());
        return 0;
    }
}

function admin_dashboard_rows(mysqli $conn, string $sql): array
{
    $rows = [];

    try {
        $result = $conn->query($sql);
        while ($result && $row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    } catch (mysqli_sql_exception $exception) {
        error_log('Admin dashboard list query failed: ' . $exception->getMessage());
    }

    return $rows;
}

function admin_dashboard_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$metrics = [
    'students' => (int) admin_dashboard_scalar($conn, "SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'"),
    'instructors' => (int) admin_dashboard_scalar($conn, "SELECT COUNT(*) FROM users WHERE role = 'instructor' AND status = 'active'"),
    'instructor_queue' => (int) admin_dashboard_scalar($conn, "SELECT COUNT(*) FROM users WHERE role = 'instructor' AND status = 'inactive'"),
    'course_queue' => (int) admin_dashboard_scalar($conn, "SELECT COUNT(*) FROM courses WHERE status = 'pending'"),
    'published_courses' => (int) admin_dashboard_scalar($conn, "SELECT COUNT(*) FROM courses WHERE status = 'published'"),
    'pending_orders' => (int) admin_dashboard_scalar($conn, "SELECT COUNT(*) FROM orders WHERE order_status = 'pending'"),
    'pending_withdrawals' => (int) admin_dashboard_scalar($conn, "SELECT COUNT(*) FROM withdrawal_requests WHERE request_status IN ('pending', 'approved')"),
    'revenue' => admin_dashboard_scalar($conn, "SELECT COALESCE(SUM(paid_amount), 0) FROM payments WHERE payment_status = 'paid'"),
];

$pendingCourses = admin_dashboard_rows($conn, "
    SELECT c.id, c.title, c.slug, c.submitted_at, u.full_name AS instructor_name
    FROM courses c
    INNER JOIN users u ON u.id = c.instructor_id
    WHERE c.status = 'pending'
    ORDER BY c.submitted_at DESC, c.updated_at DESC
    LIMIT 5
");

$recentOrders = admin_dashboard_rows($conn, "
    SELECT o.id, o.final_amount, o.order_status, o.created_at, u.full_name AS student_name
    FROM orders o
    INNER JOIN users u ON u.id = o.student_id
    ORDER BY o.created_at DESC
    LIMIT 5
");

$pendingInstructors = admin_dashboard_rows($conn, "
    SELECT id, full_name, email, created_at
    FROM users
    WHERE role = 'instructor' AND status = 'inactive'
    ORDER BY created_at DESC
    LIMIT 5
");

$pageTitle = 'Admin dashboard';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/admin_navbar.php';
?>

<style>
.command-page{min-height:calc(100vh - 72px);padding:34px 18px 68px}.command-shell{width:min(1320px,100%);margin:auto}.command-hero{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:22px;align-items:center;margin-bottom:20px;padding:30px;border-radius:26px;color:#fff;background:linear-gradient(135deg,#0f172a,#1e293b 55%,#312e81);box-shadow:0 24px 65px rgba(15,23,42,.2)}.command-hero h1{margin:6px 0 8px;color:#fff;font-size:clamp(2rem,4vw,3.2rem);letter-spacing:-.05em}.command-hero p{max-width:720px;margin:0;color:#cbd5e1}.command-kicker{color:#a5b4fc!important;font-size:.72rem;font-weight:900;letter-spacing:.14em;text-transform:uppercase}.hero-alerts{display:grid;grid-template-columns:repeat(3,110px);gap:10px}.hero-alerts a{display:grid;place-items:center;min-height:90px;border:1px solid rgba(255,255,255,.13);border-radius:17px;color:#fff;background:rgba(255,255,255,.08);text-decoration:none;text-align:center}.hero-alerts strong{display:block;font-size:1.45rem}.hero-alerts span{font-size:.67rem;font-weight:800}.metric-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:20px}.command-metric{padding:19px;border:1px solid #e5eaf1;border-radius:19px;background:#fff;box-shadow:0 10px 28px rgba(15,23,42,.055)}.command-metric span{color:#667085;font-size:.72rem;font-weight:850;text-transform:uppercase}.command-metric strong{display:block;margin-top:7px;color:#101828;font-size:1.75rem}.command-metric small{color:#98a2b3}.command-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.command-panel{overflow:hidden;border:1px solid #e5eaf1;border-radius:21px;background:#fff;box-shadow:0 11px 30px rgba(15,23,42,.06)}.panel-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:17px 18px;border-bottom:1px solid #edf0f5}.panel-heading h2{margin:0;color:#101828;font-size:1rem}.panel-heading a{color:#4f46e5;text-decoration:none;font-size:.72rem;font-weight:900}.activity-list{display:grid}.activity-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 18px;border-bottom:1px solid #eef2f6;text-decoration:none}.activity-row:last-child{border-bottom:0}.activity-row strong{display:block;color:#344054;font-size:.82rem}.activity-row span{display:block;margin-top:3px;color:#98a2b3;font-size:.68rem}.activity-badge{padding:6px 8px;border-radius:999px;color:#475467!important;background:#f1f5f9;font-size:.66rem!important;font-weight:850}.empty-activity{padding:28px 18px;color:#98a2b3;text-align:center;font-size:.82rem}.quick-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:20px}.quick-grid a{padding:17px;border:1px solid #e5eaf1;border-radius:17px;color:#344054;background:#fff;text-decoration:none;font-size:.78rem;font-weight:900;box-shadow:0 8px 22px rgba(15,23,42,.04)}@media(max-width:1050px){.metric-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.command-grid{grid-template-columns:1fr 1fr}.command-panel:last-child{grid-column:1/-1}.quick-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:720px){.command-page{padding:20px 12px 48px}.command-hero{grid-template-columns:1fr;padding:22px}.hero-alerts{grid-template-columns:repeat(3,minmax(0,1fr))}.command-grid{grid-template-columns:1fr}.command-panel:last-child{grid-column:auto}.metric-grid,.quick-grid{grid-template-columns:1fr 1fr}}@media(max-width:430px){.hero-alerts,.metric-grid,.quick-grid{grid-template-columns:1fr}}
</style>

<main class="command-page">
    <section class="command-shell">
        <header class="command-hero">
            <div>
                <p class="command-kicker">Platform command center</p>
                <h1>Admin overview</h1>
                <p>Review courses, payments, instructor applications, and platform activity.</p>
            </div>
            <div class="hero-alerts">
                <a href="admin-courses.php?status=pending"><strong><?php echo $metrics['course_queue']; ?></strong><span>Course reviews</span></a>
                <a href="admin-orders.php?status=pending"><strong><?php echo $metrics['pending_orders']; ?></strong><span>Payment reviews</span></a>
                <a href="admin-withdrawals.php?status=pending"><strong><?php echo $metrics['pending_withdrawals']; ?></strong><span>Payout actions</span></a>
            </div>
        </header>

        <div class="metric-grid">
            <article class="command-metric"><span>Active students</span><strong><?php echo $metrics['students']; ?></strong><small>Learning accounts</small></article>
            <article class="command-metric"><span>Active instructors</span><strong><?php echo $metrics['instructors']; ?></strong><small><?php echo $metrics['instructor_queue']; ?> applications waiting</small></article>
            <article class="command-metric"><span>Published courses</span><strong><?php echo $metrics['published_courses']; ?></strong><small>Marketplace courses</small></article>
            <article class="command-metric"><span>Verified revenue</span><strong>Rs. <?php echo number_format($metrics['revenue'], 0); ?></strong><small>Paid transactions</small></article>
        </div>

        <div class="command-grid">
            <section class="command-panel">
                <div class="panel-heading"><h2>Course review queue</h2><a href="admin-courses.php?status=pending">View all</a></div>
                <?php if (!$pendingCourses): ?><div class="empty-activity">No courses awaiting review.</div><?php endif; ?>
                <div class="activity-list">
                    <?php foreach ($pendingCourses as $course): ?>
                        <a class="activity-row" href="course-details.php?slug=<?php echo rawurlencode((string) $course['slug']); ?>">
                            <div><strong><?php echo admin_dashboard_h($course['title']); ?></strong><span><?php echo admin_dashboard_h($course['instructor_name']); ?></span></div>
                            <span class="activity-badge">Review</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="command-panel">
                <div class="panel-heading"><h2>Recent orders</h2><a href="admin-orders.php">View all</a></div>
                <?php if (!$recentOrders): ?><div class="empty-activity">No orders recorded.</div><?php endif; ?>
                <div class="activity-list">
                    <?php foreach ($recentOrders as $order): ?>
                        <a class="activity-row" href="admin-order-details.php?order_id=<?php echo (int) $order['id']; ?>">
                            <div><strong>Order #<?php echo (int) $order['id']; ?> · Rs. <?php echo number_format((float) $order['final_amount'], 2); ?></strong><span><?php echo admin_dashboard_h($order['student_name']); ?></span></div>
                            <span class="activity-badge"><?php echo admin_dashboard_h(ucfirst((string) $order['order_status'])); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="command-panel">
                <div class="panel-heading"><h2>Instructor applications</h2><a href="admin-instructors.php">View all</a></div>
                <?php if (!$pendingInstructors): ?><div class="empty-activity">No instructor applications.</div><?php endif; ?>
                <div class="activity-list">
                    <?php foreach ($pendingInstructors as $instructor): ?>
                        <a class="activity-row" href="admin-instructors.php">
                            <div><strong><?php echo admin_dashboard_h($instructor['full_name']); ?></strong><span><?php echo admin_dashboard_h($instructor['email']); ?></span></div>
                            <span class="activity-badge">Verify</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <nav class="quick-grid" aria-label="Admin quick actions">
            <a href="admin-users.php">Manage users</a>
            <a href="admin-reports.php">Business reports</a>
            <a href="admin-notifications.php">Send notifications</a>
            <a href="admin-settings.php">Platform settings</a>
        </nav>
    </section>
</main>

<?php require_once __DIR__ . '/../layouts/panel_end.php'; ?>