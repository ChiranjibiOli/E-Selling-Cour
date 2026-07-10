<?php

require_once __DIR__ . '/../../middleware/StudentMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

StudentMiddleware::handle();

$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);
$courseId = (int) ($_GET['course_id'] ?? 0);
$selectedLessonId = (int) ($_GET['lesson_id'] ?? 0);

if ($courseId <= 0) {
    header("Location: student-my-courses.php");
    exit;
}

$course = null;

$courseSql = "
    SELECT 
        c.*,
        cat.name AS category_name,
        u.full_name AS instructor_name
    FROM enrollments e
    INNER JOIN courses c ON e.course_id = c.id
    INNER JOIN users u ON c.instructor_id = u.id
    LEFT JOIN categories cat ON c.category_id = cat.id
    WHERE e.student_id = ?
      AND e.course_id = ?
      AND e.status = 'active'
    LIMIT 1
";

$courseStmt = $conn->prepare($courseSql);
$courseStmt->bind_param("ii", $studentId, $courseId);
$courseStmt->execute();

$courseResult = $courseStmt->get_result();

if ($courseResult && $courseResult->num_rows === 1) {
    $course = $courseResult->fetch_assoc();
}

$courseStmt->close();

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function safe_lesson_html($html)
{
    return Security::sanitizeRichText((string) $html);
}

function type_label($type)
{
    if ($type === 'text') {
        return 'Lecture Page';
    }

    if ($type === 'pdf') {
        return 'PDF Resource';
    }

    if ($type === 'word') {
        return 'Word Resource';
    }

    if ($type === 'video') {
        return 'Video';
    }

    if ($type === 'link') {
        return 'External Link';
    }

    return ucfirst((string) $type);
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
                <a href="courses.php">Browse Courses</a>
            </div>
        </section>
    </main>

    </body>
    </html>

    <?php
    exit;
}

$sections = [];
$flatLessons = [];

$contentSql = "
    SELECT 
        s.id AS section_id,
        s.title AS section_title,
        s.sort_order AS section_order,

        l.id AS lesson_id,
        l.title AS lesson_title,
        l.content_type,
        l.content_url,
        l.content_text,
        l.duration_minutes,
        l.sort_order AS lesson_order
    FROM course_sections s
    LEFT JOIN course_lessons l ON l.section_id = s.id
    WHERE s.course_id = ?
    ORDER BY s.sort_order ASC, l.sort_order ASC, l.id ASC
";

$contentStmt = $conn->prepare($contentSql);
$contentStmt->bind_param("i", $courseId);
$contentStmt->execute();

$contentResult = $contentStmt->get_result();

if ($contentResult) {
    while ($row = $contentResult->fetch_assoc()) {
        $sectionId = (int) $row['section_id'];

        if (!isset($sections[$sectionId])) {
            $sections[$sectionId] = [
                'section_id' => $sectionId,
                'section_title' => $row['section_title'],
                'lessons' => []
            ];
        }

        if (!empty($row['lesson_id'])) {
            $lesson = [
                'lesson_id' => (int) $row['lesson_id'],
                'lesson_title' => $row['lesson_title'],
                'content_type' => $row['content_type'],
                'content_url' => $row['content_url'],
                'content_text' => $row['content_text'],
                'duration_minutes' => $row['duration_minutes'],
                'section_title' => $row['section_title']
            ];

            $sections[$sectionId]['lessons'][] = $lesson;
            $flatLessons[] = $lesson;
        }
    }
}

$contentStmt->close();

if ($selectedLessonId <= 0 && !empty($flatLessons)) {
    $selectedLessonId = (int) $flatLessons[0]['lesson_id'];
}

$lessonCount = count($flatLessons);

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/student_navbar.php';
?>


<main class="student-learning-page protected-course-area" oncontextmenu="return false;">

    <div class="protected-watermark">
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

            <a href="student-my-courses.php" class="back-my-courses-btn">
                Back to My Courses
            </a>
        </div>

        <?php if (empty($flatLessons)): ?>

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

                                <?php if (empty($section['lessons'])): ?>
                                    <p class="empty-section-text">No lessons.</p>
                                <?php else: ?>

                                    <?php foreach ($section['lessons'] as $lesson): ?>
                                        <?php
                                            $lessonId = (int) $lesson['lesson_id'];
                                            $isActive = $lessonId === $selectedLessonId;
                                        ?>

                                        <button
                                            type="button"
                                            class="outline-lesson-btn <?php echo $isActive ? 'active' : ''; ?>"
                                            data-lesson-id="<?php echo $lessonId; ?>"
                                        >
                                            <span class="lesson-icon">
                                                <?php echo $lesson['content_type'] === 'pdf' ? 'PDF' : 'Lesson'; ?>
                                            </span>

                                            <span class="lesson-btn-text">
                                                <strong><?php echo h($lesson['lesson_title']); ?></strong>
                                                <small><?php echo h(type_label($lesson['content_type'])); ?></small>
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
                            <p>Copying, downloading, printing, or sharing is not allowed.</p>
                        </div>

                        <div class="viewer-actions">
                            <button type="button" class="toolbar-btn" id="toggleSidebarBtn">
                                Hide Outline
                            </button>

                            <button type="button" class="toolbar-btn focus-btn" id="focusModeBtn">
                                Enter Focus Mode
                            </button>
                        </div>
                    </div>

                    <div class="lesson-panel-area">

                        <?php foreach ($flatLessons as $index => $lesson): ?>
                            <?php
                                $lessonId = (int) $lesson['lesson_id'];
                                $type = $lesson['content_type'];
                                $resourceUrl = 'student-view-course-resource.php?lesson_id=' . $lessonId;
                                $isActive = $lessonId === $selectedLessonId;

                                $previousLessonId = $flatLessons[$index - 1]['lesson_id'] ?? 0;
                                $nextLessonId = $flatLessons[$index + 1]['lesson_id'] ?? 0;
                            ?>

                            <article
                                class="lesson-panel <?php echo $isActive ? 'active' : ''; ?>"
                                data-lesson-panel="<?php echo $lessonId; ?>"
                                data-prev-lesson="<?php echo (int) $previousLessonId; ?>"
                                data-next-lesson="<?php echo (int) $nextLessonId; ?>"
                            >

                                <div class="lesson-panel-header">
                                    <div>
                                        <span class="lesson-type-pill">
                                            <?php echo h(type_label($type)); ?>
                                        </span>

                                        <h2><?php echo h($lesson['lesson_title']); ?></h2>

                                        <p>
                                            Section: <?php echo h($lesson['section_title']); ?>

                                            <?php if (!empty($lesson['duration_minutes'])): ?>
                                                &middot; <?php echo (int) $lesson['duration_minutes']; ?> min
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="lesson-content-display">

                                    <?php if ($type === 'text'): ?>

                                        <div class="lesson-html-content no-copy-content">
                                            <?php echo safe_lesson_html($lesson['content_text']); ?>
                                        </div>

                                    <?php elseif ($type === 'pdf'): ?>

                                        <div class="pdf-viewer-shell">
                                            <iframe
                                               src="<?php echo h($resourceUrl); ?>#toolbar=0&navpanes=0&scrollbar=1&view=Fit&zoom=page-fit"
                                                title="<?php echo h($lesson['lesson_title']); ?>"
                                            ></iframe>
                                        </div>

                                    <?php elseif ($type === 'word'): ?>

                                        <div class="resource-blocked-box">
                                            <h3>Word resource is blocked for direct viewing</h3>
                                            <p>
                                                To prevent downloading, Word files are not opened directly for students.
                                                Ask the instructor to convert this resource into protected lesson content.
                                            </p>
                                        </div>

                                    <?php elseif ($type === 'link' || $type === 'video'): ?>

                                        <div class="resource-file-card">
                                            <h3><?php echo h(type_label($type)); ?></h3>
                                            <p><?php echo h($lesson['content_url']); ?></p>

                                            <?php if (preg_match('/^https?:\/\//i', (string) $lesson['content_url'])): ?>
                                                <a href="<?php echo h($lesson['content_url']); ?>" target="_blank">
                                                    Open Resource
                                                </a>
                                            <?php endif; ?>
                                        </div>

                                    <?php else: ?>

                                        <div class="resource-blocked-box">
                                            Unsupported lesson type.
                                        </div>

                                    <?php endif; ?>

                                </div>

                                <div class="lesson-navigation">
                                    <button
    type="button"
    class="nav-lesson-btn"
    data-go-lesson="<?php echo (int) $previousLessonId; ?>"
    <?php echo $previousLessonId <= 0 ? 'disabled' : ''; ?>
>
    &larr; Previous Lesson
</button>

<button
    type="button"
    class="nav-lesson-btn primary"
    data-go-lesson="<?php echo (int) $nextLessonId; ?>"
    <?php echo $nextLessonId <= 0 ? 'disabled' : ''; ?>
>
    Next Lesson &rarr;
</button>
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

</body>
</html>
