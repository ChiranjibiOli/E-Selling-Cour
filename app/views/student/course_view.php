<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/StudentMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

StudentMiddleware::handle();

$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);
$courseId = (int) ($_GET['course_id'] ?? 0);
$selectedLessonId = (int) ($_GET['lesson_id'] ?? 0);

if ($courseId <= 0) {
    Auth::redirect('student-my-courses.php');
}

$courseStmt = $conn->prepare("
    SELECT c.id, c.title, c.instructor_id, cat.name AS category_name,
           instructor.full_name AS instructor_name
    FROM enrollments e
    INNER JOIN courses c ON c.id = e.course_id
    INNER JOIN users instructor ON instructor.id = c.instructor_id
    LEFT JOIN categories cat ON cat.id = c.category_id
    WHERE e.student_id = ?
      AND e.course_id = ?
      AND e.status = 'active'
    LIMIT 1
");
$courseStmt->bind_param('ii', $studentId, $courseId);
$courseStmt->execute();
$course = $courseStmt->get_result()->fetch_assoc() ?: null;
$courseStmt->close();

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function safe_lesson_html(mixed $html): string
{
    return Security::sanitizeRichText((string) $html);
}

function type_label(string $type): string
{
    return match ($type) {
        'text' => 'Lecture Page',
        'pdf' => 'PDF Resource',
        'word' => 'Word Resource',
        'video' => 'Video',
        'link' => 'External Link',
        default => ucfirst($type),
    };
}

if (!$course) {
    require_once __DIR__ . '/../layouts/header.php';
    require_once __DIR__ . '/../layouts/student_navbar.php';
    ?>
    <main class="student-learning-page">
        <section class="student-learning-wrapper">
            <div class="access-denied-card">
                <div class="empty-icon">Locked</div>
                <h1>Access Denied</h1>
                <p>You can access this course only after payment is verified by admin.</p>
                <a href="student-browse-courses.php">Browse Courses</a>
            </div>
        </section>
    </main>
    <?php require_once __DIR__ . '/../layouts/panel_end.php'; ?>
    <?php
    exit;
}

$sections = [];
$flatLessons = [];
$contentStmt = $conn->prepare("
    SELECT s.id AS section_id, s.title AS section_title, s.sort_order AS section_order,
           l.id AS lesson_id, l.title AS lesson_title, l.content_type,
           l.content_url, l.content_text, l.duration_minutes, l.sort_order AS lesson_order
    FROM course_sections s
    LEFT JOIN course_lessons l ON l.section_id = s.id
    WHERE s.course_id = ?
    ORDER BY s.sort_order ASC, l.sort_order ASC, l.id ASC
");
$contentStmt->bind_param('i', $courseId);
$contentStmt->execute();
$contentResult = $contentStmt->get_result();

while ($contentResult && $row = $contentResult->fetch_assoc()) {
    $sectionId = (int) $row['section_id'];
    if (!isset($sections[$sectionId])) {
        $sections[$sectionId] = [
            'section_id' => $sectionId,
            'section_title' => (string) $row['section_title'],
            'lessons' => [],
        ];
    }

    if (!empty($row['lesson_id'])) {
        $lesson = [
            'lesson_id' => (int) $row['lesson_id'],
            'lesson_title' => (string) $row['lesson_title'],
            'content_type' => (string) $row['content_type'],
            'content_url' => (string) ($row['content_url'] ?? ''),
            'content_text' => (string) ($row['content_text'] ?? ''),
            'duration_minutes' => (int) ($row['duration_minutes'] ?? 0),
            'section_title' => (string) $row['section_title'],
        ];
        $sections[$sectionId]['lessons'][] = $lesson;
        $flatLessons[] = $lesson;
    }
}
$contentStmt->close();

$validLessonIds = array_map(static fn (array $lesson): int => (int) $lesson['lesson_id'], $flatLessons);
if ($flatLessons !== [] && !in_array($selectedLessonId, $validLessonIds, true)) {
    $selectedLessonId = (int) $flatLessons[0]['lesson_id'];
}

$lessonCount = count($flatLessons);
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/student_navbar.php';
?>

<main class="student-learning-page protected-course-area" oncontextmenu="return false;">
    <div class="protected-watermark" aria-hidden="true">
        <?php for ($i = 0; $i < 24; $i++): ?>
            <span><?php echo h($user['email'] ?? 'Student'); ?></span>
        <?php endfor; ?>
    </div>

    <section class="student-learning-wrapper">
        <div class="learning-top-header">
            <div>
                <p class="page-label">My Course</p>
                <h1><?php echo h($course['title']); ?></h1>
                <p>
                    Instructor: <?php echo h($course['instructor_name']); ?>
                    &middot; Category: <?php echo h($course['category_name'] ?: 'General'); ?>
                    &middot; <?php echo $lessonCount; ?> lesson(s)
                </p>
            </div>
            <a href="student-my-courses.php" class="back-my-courses-btn">Back to My Courses</a>
        </div>

        <?php if ($flatLessons === []): ?>
            <div class="access-denied-card">
                <div class="empty-icon">No lessons</div>
                <h1>No Lessons Found</h1>
                <p>This course has no lessons yet.</p>
            </div>
        <?php else: ?>
            <div class="learning-player" id="learningPlayer">
                <aside class="lesson-sidebar" id="lessonSidebar">
                    <div class="sidebar-header">
                        <h2>Course Outline</h2>
                        <p>Select a lesson to start learning.</p>
                    </div>

                    <div class="lesson-outline">
                        <?php foreach ($sections as $section): ?>
                            <div class="outline-section">
                                <h3><?php echo h($section['section_title']); ?></h3>
                                <?php if ($section['lessons'] === []): ?>
                                    <p class="empty-section-text">No lessons.</p>
                                <?php else: ?>
                                    <?php foreach ($section['lessons'] as $lesson): ?>
                                        <?php $lessonId = (int) $lesson['lesson_id']; ?>
                                        <button type="button"
                                                class="outline-lesson-btn <?php echo $lessonId === $selectedLessonId ? 'active' : ''; ?>"
                                                data-lesson-id="<?php echo $lessonId; ?>">
                                            <span class="lesson-icon"><?php echo $lesson['content_type'] === 'pdf' ? 'PDF' : 'Lesson'; ?></span>
                                            <span class="lesson-btn-text">
                                                <strong><?php echo h($lesson['lesson_title']); ?></strong>
                                                <small><?php echo h(type_label((string) $lesson['content_type'])); ?></small>
                                            </span>
                                        </button>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </aside>

                <section class="lesson-viewer">
                    <div class="viewer-toolbar">
                        <div>
                            <span class="protected-badge">Protected Content</span>
                            <p>Course access is tied to your active enrollment.</p>
                        </div>
                        <div class="viewer-actions">
                            <button type="button" class="toolbar-btn" id="toggleSidebarBtn">Hide Outline</button>
                            <button type="button" class="toolbar-btn focus-btn" id="focusModeBtn">Enter Focus Mode</button>
                        </div>
                    </div>

                    <div class="lesson-panel-area">
                        <?php foreach ($flatLessons as $index => $lesson): ?>
                            <?php
                            $lessonId = (int) $lesson['lesson_id'];
                            $type = (string) $lesson['content_type'];
                            $isActive = $lessonId === $selectedLessonId;
                            $previousLessonId = (int) ($flatLessons[$index - 1]['lesson_id'] ?? 0);
                            $nextLessonId = (int) ($flatLessons[$index + 1]['lesson_id'] ?? 0);
                            $protectedFileUrl = 'student-view-course-resource.php?lesson_id=' . $lessonId;
                            $protectedExternalUrl = 'student-open-course-link.php?lesson_id=' . $lessonId;
                            ?>
                            <article class="lesson-panel <?php echo $isActive ? 'active' : ''; ?>"
                                     data-lesson-panel="<?php echo $lessonId; ?>"
                                     data-prev-lesson="<?php echo $previousLessonId; ?>"
                                     data-next-lesson="<?php echo $nextLessonId; ?>">
                                <div class="lesson-panel-header">
                                    <div>
                                        <span class="lesson-type-pill"><?php echo h(type_label($type)); ?></span>
                                        <h2><?php echo h($lesson['lesson_title']); ?></h2>
                                        <p>
                                            Section: <?php echo h($lesson['section_title']); ?>
                                            <?php if ((int) $lesson['duration_minutes'] > 0): ?>
                                                &middot; <?php echo (int) $lesson['duration_minutes']; ?> min
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="lesson-content-display">
                                    <?php if ($type === 'text'): ?>
                                        <div class="lesson-html-content no-copy-content"><?php echo safe_lesson_html($lesson['content_text']); ?></div>
                                    <?php elseif ($type === 'pdf'): ?>
                                        <div class="pdf-viewer-shell">
                                            <iframe src="<?php echo h($protectedFileUrl); ?>#toolbar=0&navpanes=0&scrollbar=1&view=Fit&zoom=page-fit"
                                                    title="<?php echo h($lesson['lesson_title']); ?>"></iframe>
                                        </div>
                                    <?php elseif ($type === 'word'): ?>
                                        <div class="resource-blocked-box">
                                            <h3>Word resource is blocked for direct viewing</h3>
                                            <p>Ask the instructor to convert it into protected lesson content or PDF.</p>
                                        </div>
                                    <?php elseif ($type === 'link' || $type === 'video'): ?>
                                        <div class="resource-file-card">
                                            <h3><?php echo h(type_label($type)); ?></h3>
                                            <p>The destination is validated when you open it.</p>
                                            <?php if (trim((string) $lesson['content_url']) !== ''): ?>
                                                <a href="<?php echo h($protectedExternalUrl); ?>" target="_blank" rel="noopener noreferrer">Open Resource</a>
                                            <?php else: ?>
                                                <span>Resource URL is not available.</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="resource-blocked-box">Unsupported lesson type.</div>
                                    <?php endif; ?>
                                </div>

                                <div class="lesson-navigation">
                                    <button type="button" class="nav-lesson-btn" data-go-lesson="<?php echo $previousLessonId; ?>" <?php echo $previousLessonId <= 0 ? 'disabled' : ''; ?>>&larr; Previous Lesson</button>
                                    <button type="button" class="nav-lesson-btn primary" data-go-lesson="<?php echo $nextLessonId; ?>" <?php echo $nextLessonId <= 0 ? 'disabled' : ''; ?>>Next Lesson &rarr;</button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        <?php endif; ?>
    </section>
</main>

<script src="assets/js/student_learning_player.js?v=<?php echo (int) filemtime(PUBLIC_PATH . '/assets/js/student_learning_player.js'); ?>"></script>
<?php require_once __DIR__ . '/../layouts/panel_end.php'; ?>
