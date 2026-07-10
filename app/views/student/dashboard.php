<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/StudentMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

StudentMiddleware::handle();

$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);

function student_dashboard_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function student_dashboard_count(mysqli $conn, string $sql, int $studentId): int
{
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    return (int) ($row[0] ?? 0);
}

$metrics = [
    'courses' => student_dashboard_count($conn, "SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND status = 'active'", $studentId),
    'pending_orders' => student_dashboard_count($conn, "SELECT COUNT(*) FROM orders WHERE student_id = ? AND order_status = 'pending'", $studentId),
    'notifications' => student_dashboard_count($conn, "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", $studentId),
    'cart' => student_dashboard_count($conn, "SELECT COUNT(*) FROM cart WHERE student_id = ?", $studentId),
];

$myCourses = [];
$stmt = $conn->prepare("
    SELECT c.id, c.title, c.slug, c.thumbnail, c.level, e.granted_at,
           (SELECT COUNT(*) FROM course_lessons l INNER JOIN course_sections s ON s.id = l.section_id WHERE s.course_id = c.id) AS lesson_count
    FROM enrollments e
    INNER JOIN courses c ON c.id = e.course_id
    WHERE e.student_id = ? AND e.status = 'active'
    ORDER BY e.granted_at DESC
    LIMIT 4
");
$stmt->bind_param('i', $studentId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $myCourses[] = $row;
}
$stmt->close();

$recommended = [];
$stmt = $conn->prepare("
    SELECT c.id, c.title, c.slug, c.thumbnail, c.price, c.level, u.full_name AS instructor_name
    FROM courses c
    INNER JOIN users u ON u.id = c.instructor_id
    WHERE c.status = 'published'
      AND NOT EXISTS (
          SELECT 1 FROM enrollments e
          WHERE e.student_id = ? AND e.course_id = c.id AND e.status = 'active'
      )
    ORDER BY c.is_featured DESC, c.updated_at DESC
    LIMIT 4
");
$stmt->bind_param('i', $studentId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recommended[] = $row;
}
$stmt->close();

$pageTitle = 'Student dashboard';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/student_navbar.php';
?>

<style>
.learning-command{min-height:calc(100vh - 72px);padding:34px 18px 68px}.learning-shell{width:min(1240px,100%);margin:auto}.learning-hero{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:22px;align-items:center;margin-bottom:18px;padding:30px;border-radius:27px;color:#fff;background:linear-gradient(135deg,#0f172a,#1e3a8a 55%,#4f46e5);box-shadow:0 24px 65px rgba(30,58,138,.22)}.learning-hero h1{margin:6px 0 8px;color:#fff;font-size:clamp(2rem,4vw,3.15rem);letter-spacing:-.05em}.learning-hero p{max-width:680px;margin:0;color:#dbeafe}.learning-kicker{color:#bfdbfe!important;font-size:.72rem;font-weight:900;letter-spacing:.14em;text-transform:uppercase}.browse-button{display:inline-flex;min-height:45px;align-items:center;justify-content:center;padding:0 17px;border-radius:12px;color:#1e3a8a;background:#fff;text-decoration:none;font-weight:900;box-shadow:0 10px 25px rgba(0,0,0,.14)}.student-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:20px}.student-metric{padding:19px;border:1px solid #e5eaf1;border-radius:19px;background:#fff;box-shadow:0 10px 28px rgba(15,23,42,.055)}.student-metric span{color:#667085;font-size:.7rem;font-weight:850;text-transform:uppercase}.student-metric strong{display:block;margin:7px 0 2px;color:#101828;font-size:1.7rem;letter-spacing:-.04em}.student-metric small{color:#98a2b3}.learning-section{margin-top:21px}.learning-section-head{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:13px}.learning-section-head h2{margin:0;color:#101828;font-size:1.15rem}.learning-section-head a{color:#4f46e5;text-decoration:none;font-size:.75rem;font-weight:900}.learning-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:15px}.learning-card{display:flex;min-width:0;overflow:hidden;flex-direction:column;border:1px solid #e5eaf1;border-radius:19px;background:#fff;box-shadow:0 10px 28px rgba(15,23,42,.055);transition:.18s}.learning-card:hover{transform:translateY(-3px);border-color:#c7d2fe;box-shadow:0 18px 42px rgba(79,70,229,.09)}.learning-cover{height:145px;background:#e2e8f0}.learning-cover img{width:100%;height:100%;object-fit:cover}.learning-body{display:flex;flex:1;flex-direction:column;padding:15px}.learning-body small{color:#4f46e5;font-size:.67rem;font-weight:900;text-transform:uppercase}.learning-body h3{display:-webkit-box;overflow:hidden;margin:6px 0 8px;color:#101828;font-size:.95rem;line-height:1.4;-webkit-line-clamp:2;-webkit-box-orient:vertical}.learning-body p{margin:0 0 13px;color:#98a2b3;font-size:.72rem}.learning-action{display:inline-flex;min-height:37px;align-items:center;justify-content:center;margin-top:auto;border-radius:10px;color:#fff;background:#4f46e5;text-decoration:none;font-size:.74rem;font-weight:900}.recommended-action{color:#4338ca;background:#eef2ff}.empty-learning{padding:35px 20px;border:2px dashed #cbd5e1;border-radius:20px;background:#fff;color:#667085;text-align:center}@media(max-width:1050px){.learning-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:720px){.learning-command{padding:20px 12px 48px}.learning-hero{grid-template-columns:1fr;padding:22px}.browse-button{width:100%}.student-metrics{grid-template-columns:1fr 1fr}}@media(max-width:480px){.student-metrics,.learning-grid{grid-template-columns:1fr}}
</style>

<main class="learning-command">
    <section class="learning-shell">
        <header class="learning-hero">
            <div>
                <p class="learning-kicker">Learning workspace</p>
                <h1>Hello, <?php echo student_dashboard_h($user['full_name'] ?? 'Student'); ?></h1>
                <p>Continue purchased courses, monitor payment reviews, and discover newly approved courses from one clean dashboard.</p>
            </div>
            <a class="browse-button" href="courses.php">Browse approved courses</a>
        </header>

        <div class="student-metrics">
            <article class="student-metric"><span>My courses</span><strong><?php echo $metrics['courses']; ?></strong><small>Lifetime access</small></article>
            <article class="student-metric"><span>Payment reviews</span><strong><?php echo $metrics['pending_orders']; ?></strong><small>Waiting for admin</small></article>
            <article class="student-metric"><span>Unread updates</span><strong><?php echo $metrics['notifications']; ?></strong><small>Course and payment alerts</small></article>
            <article class="student-metric"><span>Cart items</span><strong><?php echo $metrics['cart']; ?></strong><small>Ready for checkout</small></article>
        </div>

        <section class="learning-section">
            <div class="learning-section-head"><h2>Continue learning</h2><a href="student-my-courses.php">View all</a></div>
            <?php if (!$myCourses): ?>
                <div class="empty-learning">You do not have an active course yet. Browse the approved course marketplace to begin.</div>
            <?php else: ?>
                <div class="learning-grid">
                    <?php foreach ($myCourses as $course): ?>
                        <?php $image = $course['thumbnail'] ? 'assets/uploads/course_thumbnails/' . rawurlencode(basename($course['thumbnail'])) : 'assets/images/course-placeholder.svg'; ?>
                        <article class="learning-card">
                            <div class="learning-cover"><img src="<?php echo student_dashboard_h($image); ?>" alt="<?php echo student_dashboard_h($course['title']); ?>"></div>
                            <div class="learning-body">
                                <small><?php echo student_dashboard_h(ucfirst($course['level'])); ?></small>
                                <h3><?php echo student_dashboard_h($course['title']); ?></h3>
                                <p><?php echo (int) $course['lesson_count']; ?> lessons · Access granted <?php echo student_dashboard_h(date('M j, Y', strtotime($course['granted_at']))); ?></p>
                                <a class="learning-action" href="student-course-view.php?course_id=<?php echo (int) $course['id']; ?>">Open course</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="learning-section">
            <div class="learning-section-head"><h2>New approved courses</h2><a href="courses.php">Explore marketplace</a></div>
            <?php if (!$recommended): ?>
                <div class="empty-learning">No new recommendations right now.</div>
            <?php else: ?>
                <div class="learning-grid">
                    <?php foreach ($recommended as $course): ?>
                        <?php $image = $course['thumbnail'] ? 'assets/uploads/course_thumbnails/' . rawurlencode(basename($course['thumbnail'])) : 'assets/images/course-placeholder.svg'; ?>
                        <article class="learning-card">
                            <div class="learning-cover"><img src="<?php echo student_dashboard_h($image); ?>" alt="<?php echo student_dashboard_h($course['title']); ?>"></div>
                            <div class="learning-body">
                                <small><?php echo student_dashboard_h($course['instructor_name']); ?></small>
                                <h3><?php echo student_dashboard_h($course['title']); ?></h3>
                                <p><?php echo student_dashboard_h(ucfirst($course['level'])); ?> · Rs. <?php echo number_format((float) $course['price'], 2); ?></p>
                                <a class="learning-action recommended-action" href="course-details.php?slug=<?php echo rawurlencode($course['slug']); ?>">View details</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </section>
</main>

<?php require_once __DIR__ . '/../layouts/panel_end.php'; ?>
