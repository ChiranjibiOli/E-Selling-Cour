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
.instructor-command{min-height:calc(100vh - 72px);padding:34px 18px 68px;background:transparent}.instructor-command-shell{width:min(1280px,100%);margin:auto}.instructor-hero{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:30px;align-items:end;margin-bottom:22px;padding:34px 0 28px;border-bottom:1px solid rgba(72,58,39,.28);color:#3f3932;background:transparent;box-shadow:none}.instructor-hero h1{margin:8px 0 9px;color:#171511;font-family:Georgia,"Times New Roman",serif;font-size:clamp(2.8rem,5vw,5.2rem);font-weight:500;letter-spacing:-.06em;line-height:.9}.instructor-hero p{max-width:700px;margin:0;color:#746b61;line-height:1.72}.instructor-kicker{display:inline-flex;align-items:center;gap:10px;color:#7d5c23!important;font-size:.68rem;font-weight:950;letter-spacing:.15em;text-transform:uppercase}.instructor-kicker:before{content:"";width:34px;height:1px;background:#b88939}.hero-actions{display:flex;gap:9px}.hero-actions a{display:inline-flex;min-height:42px;align-items:center;justify-content:center;padding:0 16px;border:1px solid #171511;border-radius:999px;text-decoration:none;font-size:.74rem;font-weight:900;transition:.18s}.hero-primary{color:#fffaf0;background:#171511}.hero-secondary{color:#4f473e;background:#f7f0e5;border-color:rgba(72,58,39,.22)!important}.hero-actions a:hover{transform:translateY(-1px);border-color:#7d5c23;background:#7d5c23;color:#fffaf0}.instructor-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:20px}.instructor-metric{position:relative;min-height:126px;overflow:hidden;padding:20px;border:1px solid rgba(72,58,39,.16);border-radius:20px;background:rgba(255,253,248,.94);box-shadow:0 9px 26px rgba(39,31,21,.065)}.instructor-metric:before{content:"";position:absolute;inset:0 auto 0 0;width:4px;background:linear-gradient(180deg,#d6b36e,#8c6423)}.instructor-metric span{color:#746b61;font-size:.68rem;font-weight:900;letter-spacing:.04em;text-transform:uppercase}.instructor-metric strong{display:block;margin:8px 0 3px;color:#171511;font-family:Georgia,"Times New Roman",serif;font-size:1.76rem;font-weight:500}.instructor-metric small{color:#8d8377}.instructor-layout{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(330px,.8fr);gap:18px}.insight-panel{overflow:hidden;border:1px solid rgba(72,58,39,.16);border-radius:20px;background:rgba(255,253,248,.94);box-shadow:0 11px 30px rgba(39,31,21,.07)}.insight-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:17px 18px;border-bottom:1px solid rgba(72,58,39,.12);background:#f3e7d5}.insight-heading h2{margin:0;color:#171511;font-family:Georgia,"Times New Roman",serif;font-size:1.2rem;font-weight:500}.insight-heading a{color:#7d5c23;text-decoration:none;font-size:.7rem;font-weight:900}.course-list{display:grid}.course-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:14px;align-items:center;padding:14px 18px;border-bottom:1px solid rgba(72,58,39,.1);text-decoration:none;transition:.16s}.course-row:last-child{border-bottom:0}.course-row:hover{background:rgba(184,137,57,.055)}.course-row strong{display:block;color:#3f3932;font-size:.84rem}.course-row small{display:block;margin-top:4px;color:#8d8377}.course-row-meta{display:flex;gap:6px}.course-row-meta span{padding:6px 8px;border:1px solid rgba(72,58,39,.12);border-radius:999px;color:#5c5145;background:#f2e8d9;font-size:.64rem;font-weight:900}.sales-chart{display:flex;height:230px;align-items:flex-end;gap:12px;padding:22px 18px 18px}.sales-column{flex:1;display:grid;align-content:end;gap:8px;height:100%;text-align:center}.sales-column strong{color:#5c5145;font-size:.66rem}.sales-bar{width:100%;min-height:5px;border-radius:8px 8px 3px 3px;background:linear-gradient(180deg,#d6b36e,#8c6423);box-shadow:0 8px 16px rgba(125,92,35,.18)}.sales-column span{color:#8d8377;font-size:.66rem;font-weight:900}.dashboard-quick{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:18px}.dashboard-quick a{padding:17px;border:1px solid rgba(72,58,39,.16);border-radius:17px;color:#3f3932;background:rgba(255,253,248,.9);box-shadow:0 8px 22px rgba(39,31,21,.05);text-decoration:none;font-size:.76rem;font-weight:900;transition:.18s}.dashboard-quick a:hover{transform:translateY(-2px);border-color:rgba(184,137,57,.45);color:#7d5c23}@media(max-width:1000px){.instructor-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.instructor-layout{grid-template-columns:1fr}.dashboard-quick{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:680px){.instructor-command{padding:20px 12px 48px}.instructor-hero{grid-template-columns:1fr;padding-top:18px}.hero-actions{display:grid}.instructor-metrics,.dashboard-quick{grid-template-columns:1fr 1fr}.course-row{grid-template-columns:1fr}.course-row-meta{flex-wrap:wrap}}@media(max-width:430px){.instructor-metrics,.dashboard-quick{grid-template-columns:1fr}}
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
                <?php if (!$recentCourses): ?><div style="padding:30px;text-align:center;color:#8d8377">No courses yet.</div><?php endif; ?>
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
