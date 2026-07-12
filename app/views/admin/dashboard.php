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
.command-page{min-height:calc(100vh - 72px);padding:34px 18px 68px;background:transparent}.command-shell{width:min(1320px,100%);margin:auto}.command-hero{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:30px;align-items:end;margin-bottom:22px;padding:34px 0 28px;border-bottom:1px solid rgba(72,58,39,.28);color:#3f3932;background:transparent;box-shadow:none}.command-hero h1{margin:8px 0 9px;color:#171511;font-family:Georgia,"Times New Roman",serif;font-size:clamp(2.8rem,5vw,5.2rem);font-weight:500;letter-spacing:-.06em;line-height:.9}.command-hero p{max-width:720px;margin:0;color:#746b61;line-height:1.72}.command-kicker{display:inline-flex;align-items:center;gap:10px;color:#7d5c23!important;font-size:.68rem;font-weight:950;letter-spacing:.15em;text-transform:uppercase}.command-kicker:before{content:"";width:34px;height:1px;background:#b88939}.hero-alerts{display:grid;grid-template-columns:repeat(3,112px);gap:10px}.hero-alerts a{display:grid;place-items:center;min-height:94px;padding:10px;border:1px solid rgba(72,58,39,.16);border-radius:18px;color:#3f3932;background:rgba(255,253,248,.84);box-shadow:0 9px 26px rgba(39,31,21,.065);text-decoration:none;text-align:center;transition:.18s}.hero-alerts a:hover{transform:translateY(-2px);border-color:rgba(184,137,57,.45);box-shadow:0 18px 42px rgba(39,31,21,.1)}.hero-alerts strong{display:block;color:#171511;font-family:Georgia,"Times New Roman",serif;font-size:1.65rem;font-weight:500}.hero-alerts span{color:#746b61;font-size:.64rem;font-weight:900}.metric-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:22px}.command-metric{position:relative;min-height:126px;overflow:hidden;padding:20px;border:1px solid rgba(72,58,39,.16);border-radius:20px;background:rgba(255,253,248,.94);box-shadow:0 9px 26px rgba(39,31,21,.065)}.command-metric:before{content:"";position:absolute;inset:0 auto 0 0;width:4px;background:linear-gradient(180deg,#d6b36e,#8c6423)}.command-metric span{color:#746b61;font-size:.68rem;font-weight:900;letter-spacing:.04em;text-transform:uppercase}.command-metric strong{display:block;margin:8px 0 3px;color:#171511;font-family:Georgia,"Times New Roman",serif;font-size:1.8rem;font-weight:500}.command-metric small{color:#8d8377}.command-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.command-panel{overflow:hidden;border:1px solid rgba(72,58,39,.16);border-radius:20px;background:rgba(255,253,248,.94);box-shadow:0 11px 30px rgba(39,31,21,.07)}.panel-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:17px 18px;border-bottom:1px solid rgba(72,58,39,.12);background:#f3e7d5}.panel-heading h2{margin:0;color:#171511;font-family:Georgia,"Times New Roman",serif;font-size:1.2rem;font-weight:500}.panel-heading a{color:#7d5c23;text-decoration:none;font-size:.7rem;font-weight:900}.activity-list{display:grid}.activity-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 18px;border-bottom:1px solid rgba(72,58,39,.1);text-decoration:none;transition:.16s}.activity-row:last-child{border-bottom:0}.activity-row:hover{background:rgba(184,137,57,.055)}.activity-row strong{display:block;color:#3f3932;font-size:.82rem}.activity-row span{display:block;margin-top:3px;color:#8d8377;font-size:.68rem}.activity-badge{padding:6px 8px;border:1px solid rgba(72,58,39,.12);border-radius:999px;color:#5c5145!important;background:#f2e8d9;font-size:.64rem!important;font-weight:900}.empty-activity{padding:28px 18px;color:#8d8377;text-align:center;font-size:.82rem}.quick-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:20px}.quick-grid a{padding:17px;border:1px solid rgba(72,58,39,.16);border-radius:17px;color:#3f3932;background:rgba(255,253,248,.9);box-shadow:0 8px 22px rgba(39,31,21,.05);text-decoration:none;font-size:.76rem;font-weight:900;transition:.18s}.quick-grid a:hover{transform:translateY(-2px);border-color:rgba(184,137,57,.45);color:#7d5c23}@media(max-width:1050px){.metric-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.command-grid{grid-template-columns:1fr 1fr}.command-panel:last-child{grid-column:1/-1}.quick-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:720px){.command-page{padding:20px 12px 48px}.command-hero{grid-template-columns:1fr;padding-top:18px}.hero-alerts{grid-template-columns:repeat(3,minmax(0,1fr))}.command-grid{grid-template-columns:1fr}.command-panel:last-child{grid-column:auto}.metric-grid,.quick-grid{grid-template-columns:1fr 1fr}}@media(max-width:430px){.hero-alerts,.metric-grid,.quick-grid{grid-template-columns:1fr}}
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
