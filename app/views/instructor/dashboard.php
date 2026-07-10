<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/InstructorMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

InstructorMiddleware::handle();

$user = Auth::user();
$instructorId = (int) ($user['id'] ?? 0);

function instructor_dashboard_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function instructor_dashboard_scalar(mysqli $conn, string $sql, int $instructorId): float
{
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $instructorId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    return (float) ($row[0] ?? 0);
}

$metrics = [
    'courses' => (int) instructor_dashboard_scalar($conn, "SELECT COUNT(*) FROM courses WHERE instructor_id = ?", $instructorId),
    'drafts' => (int) instructor_dashboard_scalar($conn, "SELECT COUNT(*) FROM courses WHERE instructor_id = ? AND status = 'draft'", $instructorId),
    'pending' => (int) instructor_dashboard_scalar($conn, "SELECT COUNT(*) FROM courses WHERE instructor_id = ? AND status = 'pending'", $instructorId),
    'published' => (int) instructor_dashboard_scalar($conn, "SELECT COUNT(*) FROM courses WHERE instructor_id = ? AND status = 'published'", $instructorId),
    'students' => (int) instructor_dashboard_scalar($conn, "SELECT COUNT(DISTINCT e.student_id) FROM enrollments e INNER JOIN courses c ON c.id = e.course_id WHERE c.instructor_id = ? AND e.status = 'active'", $instructorId),
    'lifetime_earnings' => instructor_dashboard_scalar($conn, "SELECT COALESCE(SUM(instructor_amount),0) FROM instructor_earnings WHERE instructor_id = ? AND earning_status NOT IN ('cancelled','refunded')", $instructorId),
    'available' => instructor_dashboard_scalar($conn, "SELECT COALESCE(SUM(instructor_amount),0) FROM instructor_earnings WHERE instructor_id = ? AND earning_status = 'available'", $instructorId),
    'processing' => instructor_dashboard_scalar($conn, "SELECT COALESCE(SUM(requested_amount),0) FROM withdrawal_requests WHERE instructor_id = ? AND request_status IN ('pending','approved')", $instructorId),
];

$recentCourses = [];
$stmt = $conn->prepare("
    SELECT id, title, slug, status, updated_at,
           (SELECT COUNT(*) FROM course_lessons l INNER JOIN course_sections s ON s.id = l.section_id WHERE s.course_id = c.id) AS lesson_count,
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'active') AS student_count
    FROM courses c
    WHERE instructor_id = ?
    ORDER BY updated_at DESC
    LIMIT 5
");
$stmt->bind_param('i', $instructorId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recentCourses[] = $row;
}
$stmt->close();

$monthlySales = [];
$stmt = $conn->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS sale_month,
           COALESCE(SUM(instructor_amount), 0) AS amount
    FROM instructor_earnings
    WHERE instructor_id = ? AND created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 5 MONTH)
    GROUP BY sale_month
    ORDER BY sale_month
");
$stmt->bind_param('i', $instructorId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $monthlySales[$row['sale_month']] = (float) $row['amount'];
}
$stmt->close();

$chart = [];
$start = new DateTimeImmutable('first day of -5 months');
for ($i = 0; $i < 6; $i++) {
    $month = $start->modify('+' . $i . ' months');
    $key = $month->format('Y-m');
    $chart[] = ['label' => $month->format('M'), 'amount' => $monthlySales[$key] ?? 0];
}
$maxChart = max(1, ...array_column($chart, 'amount'));

$pageTitle = 'Instructor dashboard';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/instructor_navbar.php';
?>

<style>
.instructor-command{min-height:calc(100vh - 72px);padding:34px 18px 68px}.instructor-command-shell{width:min(1280px,100%);margin:auto}.instructor-hero{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:22px;align-items:center;margin-bottom:18px;padding:29px;border-radius:26px;color:#fff;background:linear-gradient(135deg,#111827,#312e81 58%,#4f46e5);box-shadow:0 24px 65px rgba(49,46,129,.22)}.instructor-hero h1{margin:6px 0 8px;color:#fff;font-size:clamp(2rem,4vw,3.1rem);letter-spacing:-.05em}.instructor-hero p{max-width:690px;margin:0;color:#dbeafe}.instructor-kicker{color:#c7d2fe!important;font-size:.72rem;font-weight:900;letter-spacing:.14em;text-transform:uppercase}.hero-actions{display:flex;gap:9px}.hero-actions a{display:inline-flex;min-height:44px;align-items:center;justify-content:center;padding:0 15px;border-radius:12px;text-decoration:none;font-size:.78rem;font-weight:900}.hero-primary{color:#312e81;background:#fff}.hero-secondary{color:#fff;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2)}.instructor-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}.instructor-metric{padding:19px;border:1px solid #e5eaf1;border-radius:19px;background:#fff;box-shadow:0 10px 28px rgba(15,23,42,.055)}.instructor-metric span{color:#667085;font-size:.7rem;font-weight:850;text-transform:uppercase}.instructor-metric strong{display:block;margin:7px 0 2px;color:#101828;font-size:1.7rem;letter-spacing:-.04em}.instructor-metric small{color:#98a2b3}.instructor-layout{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(330px,.8fr);gap:18px}.insight-panel{border:1px solid #e5eaf1;border-radius:21px;background:#fff;box-shadow:0 11px 30px rgba(15,23,42,.06)}.insight-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:18px;border-bottom:1px solid #edf0f5}.insight-heading h2{margin:0;color:#101828;font-size:1rem}.insight-heading a{color:#4f46e5;text-decoration:none;font-size:.72rem;font-weight:900}.course-list{display:grid}.course-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:14px;align-items:center;padding:14px 18px;border-bottom:1px solid #eef2f6;text-decoration:none}.course-row:last-child{border-bottom:0}.course-row:hover{background:#fafbff}.course-row strong{display:block;color:#344054;font-size:.84rem}.course-row small{display:block;margin-top:4px;color:#98a2b3}.course-row-meta{display:flex;gap:6px}.course-row-meta span{padding:6px 8px;border-radius:999px;color:#475467;background:#f1f5f9;font-size:.65rem;font-weight:850}.sales-chart{display:flex;height:230px;align-items:flex-end;gap:12px;padding:22px 18px 18px}.sales-column{flex:1;display:grid;align-content:end;gap:8px;height:100%;text-align:center}.sales-column strong{color:#475467;font-size:.68rem}.sales-bar{width:100%;min-height:5px;border-radius:8px 8px 3px 3px;background:linear-gradient(180deg,#6366f1,#4338ca);box-shadow:0 8px 16px rgba(79,70,229,.18)}.sales-column span{color:#98a2b3;font-size:.68rem;font-weight:850}.dashboard-quick{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:18px}.dashboard-quick a{padding:16px;border:1px solid #e5eaf1;border-radius:17px;color:#344054;background:#fff;text-decoration:none;font-size:.78rem;font-weight:900;box-shadow:0 8px 22px rgba(15,23,42,.04)}.dashboard-quick a:hover{border-color:#c7d2fe;color:#4338ca}@media(max-width:1000px){.instructor-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.instructor-layout{grid-template-columns:1fr}.dashboard-quick{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:680px){.instructor-command{padding:20px 12px 48px}.instructor-hero{grid-template-columns:1fr;padding:22px}.hero-actions{display:grid}.instructor-metrics,.dashboard-quick{grid-template-columns:1fr 1fr}.course-row{grid-template-columns:1fr}.course-row-meta{flex-wrap:wrap}}@media(max-width:430px){.instructor-metrics,.dashboard-quick{grid-template-columns:1fr}}
</style>

<main class="instructor-command">
    <section class="instructor-command-shell">
        <header class="instructor-hero">
            <div>
                <p class="instructor-kicker">Instructor command center</p>
                <h1>Welcome, <?php echo instructor_dashboard_h($user['full_name'] ?? 'Instructor'); ?></h1>
                <p>Track course reviews, students, sales, and withdrawable earnings without searching through crowded cards.</p>
            </div>
            <div class="hero-actions">
                <a class="hero-primary" href="instructor-create-course.php">Create course</a>
                <a class="hero-secondary" href="instructor-courses.php">Course library</a>
            </div>
        </header>

        <div class="instructor-metrics">
            <article class="instructor-metric"><span>Published courses</span><strong><?php echo $metrics['published']; ?></strong><small><?php echo $metrics['drafts']; ?> drafts · <?php echo $metrics['pending']; ?> in review</small></article>
            <article class="instructor-metric"><span>Unique students</span><strong><?php echo $metrics['students']; ?></strong><small>Across your active enrollments</small></article>
            <article class="instructor-metric"><span>Available balance</span><strong>Rs. <?php echo number_format($metrics['available'], 2); ?></strong><small>Ready to withdraw</small></article>
            <article class="instructor-metric"><span>Lifetime earnings</span><strong>Rs. <?php echo number_format($metrics['lifetime_earnings'], 2); ?></strong><small>Rs. <?php echo number_format($metrics['processing'], 2); ?> processing</small></article>
        </div>

        <div class="instructor-layout">
            <section class="insight-panel">
                <div class="insight-heading"><h2>Recently updated courses</h2><a href="instructor-courses.php">View library</a></div>
                <?php if (!$recentCourses): ?><div style="padding:30px;text-align:center;color:#98a2b3">No courses yet.</div><?php endif; ?>
                <div class="course-list">
                    <?php foreach ($recentCourses as $course): ?>
                        <a class="course-row" href="course-details.php?slug=<?php echo rawurlencode($course['slug']); ?>">
                            <div><strong>#<?php echo (int) $course['id']; ?> · <?php echo instructor_dashboard_h($course['title']); ?></strong><small>Updated <?php echo instructor_dashboard_h(date('M j, Y', strtotime($course['updated_at']))); ?></small></div>
                            <div class="course-row-meta"><span><?php echo instructor_dashboard_h(ucfirst($course['status'])); ?></span><span><?php echo (int) $course['lesson_count']; ?> lessons</span><span><?php echo (int) $course['student_count']; ?> students</span></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="insight-panel">
                <div class="insight-heading"><h2>Six-month earnings</h2><a href="instructor-sales.php">Sales details</a></div>
                <div class="sales-chart">
                    <?php foreach ($chart as $month): ?>
                        <?php $height = max(3, (int) round(($month['amount'] / $maxChart) * 100)); ?>
                        <div class="sales-column">
                            <strong>Rs. <?php echo number_format($month['amount'], 0); ?></strong>
                            <div class="sales-bar" style="height:<?php echo $height; ?>%"></div>
                            <span><?php echo instructor_dashboard_h($month['label']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <nav class="dashboard-quick" aria-label="Instructor quick actions">
            <a href="instructor-students.php">My enrolled students</a>
            <a href="instructor-sales.php">Sales and earnings</a>
            <a href="instructor-withdrawals.php">Withdraw funds</a>
            <a href="instructor-payout-account.php">Payout account</a>
        </nav>
    </section>
</main>

<?php require_once __DIR__ . '/../layouts/panel_end.php'; ?>
