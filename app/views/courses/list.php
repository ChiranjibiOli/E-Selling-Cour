<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../helpers/security_helper.php';

$currentUser = Auth::user();

if ($currentUser && ($currentUser['role'] ?? '') === 'student') {
    Auth::redirect('student-browse-courses.php');
}

$rawSearch = (string) ($_GET['search'] ?? '');
$search = security_clean_text($rawSearch, 150);
$categoryFilter = (int) ($_GET['category_id'] ?? 0);
$levelFilter = trim((string) ($_GET['level'] ?? ''));
$whereParts = [
    "c.status = 'published'",
    "u.role = 'instructor'",
    "u.status = 'active'",
    "cat.status = 'active'",
];
$params = [];
$types = '';

if ($search !== '') {
    $whereParts[] = '(c.title LIKE ? OR c.short_description LIKE ? OR c.full_description LIKE ? OR u.full_name LIKE ?)';
    $searchValue = '%' . $search . '%';
    $params = [$searchValue, $searchValue, $searchValue, $searchValue];
    $types .= 'ssss';
}

if ($categoryFilter > 0) {
    $whereParts[] = 'c.category_id = ?';
    $params[] = $categoryFilter;
    $types .= 'i';
}

if (in_array($levelFilter, ['beginner', 'intermediate', 'advanced'], true)) {
    $whereParts[] = 'c.level = ?';
    $params[] = $levelFilter;
    $types .= 's';
} else {
    $levelFilter = '';
}

$whereSql = 'WHERE ' . implode(' AND ', $whereParts);
$courses = [];
$stmt = $conn->prepare("
    SELECT c.id, c.title, c.slug, c.short_description, c.thumbnail, c.price,
           c.level, c.language, c.duration, c.created_at,
           cat.name AS category_name, u.full_name AS instructor_name,
           (SELECT COUNT(*) FROM course_lessons l INNER JOIN course_sections s ON l.section_id = s.id WHERE s.course_id = c.id) AS lesson_count,
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'active') AS student_count
    FROM courses c
    INNER JOIN users u ON c.instructor_id = u.id
    INNER JOIN categories cat ON c.category_id = cat.id
    {$whereSql}
    ORDER BY c.created_at DESC, c.id DESC
");

if ($params !== []) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
while ($result && $row = $result->fetch_assoc()) {
    $courses[] = $row;
}
$stmt->close();

$categories = [];
$categoryResult = $conn->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC");
while ($categoryResult && $row = $categoryResult->fetch_assoc()) {
    $categories[] = $row;
}

function public_course_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$pageTitle = 'Published courses';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<link rel="stylesheet" href="assets/css/pages/student/browse-courses.css?v=3">

<main class="student-courses-page public-course-catalog-page">
    <section class="student-courses-wrapper">
        <header class="courses-hero">
            <div>
                <p class="page-label">Public course catalog</p>
                <h1>Learn from approved courses</h1>
                <p>Only admin-approved courses from active instructors and active categories appear here.</p>
            </div>
        </header>

        <form method="get" class="course-filter-box">
            <div class="form-group">
                <label for="publicCourseSearch">Search</label>
                <input id="publicCourseSearch" type="search" name="search" maxlength="150" value="<?php echo public_course_h($search); ?>" placeholder="Search course, instructor, topic">
            </div>

            <div class="form-group">
                <label for="publicCategoryFilter">Category</label>
                <select id="publicCategoryFilter" name="category_id">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo (int) $category['id']; ?>" <?php echo $categoryFilter === (int) $category['id'] ? 'selected' : ''; ?>>
                            <?php echo public_course_h($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="publicLevelFilter">Level</label>
                <select id="publicLevelFilter" name="level">
                    <option value="">All Levels</option>
                    <option value="beginner" <?php echo $levelFilter === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                    <option value="intermediate" <?php echo $levelFilter === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                    <option value="advanced" <?php echo $levelFilter === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit">Filter</button>
                <a href="courses.php">Reset</a>
            </div>
        </form>

        <?php if (isset($_GET['login'])): ?>
            <div class="course-alert warning">Sign in as a student, then use Student Browse to add this course to your cart.</div>
        <?php endif; ?>

        <?php if ($courses === []): ?>
            <div class="empty-course-box">
                <div class="empty-icon">No courses</div>
                <h2>No published courses found</h2>
                <p>Try changing your search or filter.</p>
            </div>
        <?php else: ?>
            <div class="student-course-grid" data-page-size="12">
                <?php foreach ($courses as $course): ?>
                    <?php
                    $thumbnail = basename((string) ($course['thumbnail'] ?? ''));
                    $thumbnailPath = $thumbnail !== ''
                        ? 'assets/uploads/course_thumbnails/' . rawurlencode($thumbnail)
                        : 'assets/images/course-placeholder.svg';

                    if ($thumbnail !== '' && !is_file(PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $thumbnailPath))) {
                        $thumbnailPath = 'assets/images/course-placeholder.svg';
                    }

                    $detailsUrl = 'course-details.php?slug=' . rawurlencode((string) $course['slug']);
                    $courseCard = [
                        'context' => 'marketplace',
                        'title' => $course['title'],
                        'summary' => $course['short_description'] ?: 'No description added.',
                        'thumbnail' => $thumbnailPath,
                        'category' => $course['category_name'] ?: 'General',
                        'badge' => ucfirst((string) $course['level']),
                        'eyebrow' => 'By ' . $course['instructor_name'],
                        'language' => $course['language'] ?: 'Language not set',
                        'duration' => $course['duration'] ?: 'Self-paced',
                        'price' => (float) $course['price'] > 0
                            ? 'Rs. ' . number_format((float) $course['price'], 2)
                            : 'Free',
                        'href' => $detailsUrl,
                        'rating_label' => 'Published course',
                        'metrics' => [
                            ['label' => 'Lessons', 'value' => (string) (int) $course['lesson_count']],
                            ['label' => 'Students', 'value' => number_format((int) $course['student_count'])],
                        ],
                        'actions' => [
                            ['label' => 'View details', 'href' => $detailsUrl, 'style' => 'primary'],
                        ],
                    ];
                    require __DIR__ . '/../components/course_card.php';
                    ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
