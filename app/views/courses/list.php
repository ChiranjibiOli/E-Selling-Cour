<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Auth.php';

$currentUser = Auth::user();
$isStudent = $currentUser && ($currentUser['role'] ?? '') === 'student';
$studentId = $isStudent ? (int) $currentUser['id'] : 0;

$search = trim($_GET['search'] ?? '');
$categoryFilter = (int) ($_GET['category_id'] ?? 0);
$levelFilter = trim($_GET['level'] ?? '');

$whereParts = ["c.status = 'published'"];
$params = [];
$types = '';

if ($search !== '') {
    $whereParts[] = "(c.title LIKE ? OR c.short_description LIKE ? OR c.full_description LIKE ? OR u.full_name LIKE ?)";
    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= 'ssss';
}

if ($categoryFilter > 0) {
    $whereParts[] = "c.category_id = ?";
    $params[] = $categoryFilter;
    $types .= 'i';
}

if (in_array($levelFilter, ['beginner', 'intermediate', 'advanced'], true)) {
    $whereParts[] = "c.level = ?";
    $params[] = $levelFilter;
    $types .= 's';
}

$whereSql = 'WHERE ' . implode(' AND ', $whereParts);

$courses = [];

$sql = "
    SELECT 
        c.id,
        c.title,
        c.slug,
        c.short_description,
        c.thumbnail,
        c.price,
        c.level,
        c.language,
        c.duration,
        c.created_at,
        cat.name AS category_name,
        u.full_name AS instructor_name,

        (
            SELECT COUNT(*) 
            FROM course_lessons l
            INNER JOIN course_sections s ON l.section_id = s.id
            WHERE s.course_id = c.id
        ) AS lesson_count,

        (
            SELECT COUNT(*) 
            FROM enrollments e
            WHERE e.course_id = c.id 
              AND e.status = 'active'
        ) AS student_count
    FROM courses c
    INNER JOIN users u ON c.instructor_id = u.id
    LEFT JOIN categories cat ON c.category_id = cat.id
    $whereSql
    ORDER BY c.created_at DESC
";

$stmt = $conn->prepare($sql);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $courses[] = $row;
        }
    }

    $stmt->close();
}

$categories = [];

$categoryResult = $conn->query("
    SELECT id, name 
    FROM categories 
    WHERE status = 'active' 
    ORDER BY name ASC
");

if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function level_label($level)
{
    if ($level === 'beginner') {
        return 'Beginner';
    }

    if ($level === 'intermediate') {
        return 'Intermediate';
    }

    if ($level === 'advanced') {
        return 'Advanced';
    }

    return ucfirst((string) $level);
}

require_once __DIR__ . '/../layouts/header.php';

if ($isStudent) {
    require_once __DIR__ . '/../layouts/student_navbar.php';
} else {
    require_once __DIR__ . '/../layouts/navbar.php';
}
?>




<main class="student-courses-page">
    <section class="student-courses-wrapper">

        <div class="courses-hero">
            <div>
                <p class="page-label">Browse Courses</p>
                <h1>Learn from approved courses</h1>
                <p>
                    Explore published courses approved by admin. Real course lessons open only after purchase.
                </p>
            </div>
        </div>

        <form method="GET" class="course-filter-box">

            <div class="form-group">
                <label>Search</label>
                <input 
                    type="text" 
                    name="search" 
                    value="<?php echo h($search); ?>" 
                    placeholder="Search course, instructor, topic"
                >
            </div>

            <div class="form-group">
                <label>Category</label>
                <select name="category_id">
                    <option value="0">All Categories</option>

                    <?php foreach ($categories as $category): ?>
                        <option 
                            value="<?php echo (int) $category['id']; ?>"
                            <?php echo $categoryFilter === (int) $category['id'] ? 'selected' : ''; ?>
                        >
                            <?php echo h($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Level</label>
                <select name="level">
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
            <div class="course-alert warning">
                Please login as student before adding course to cart.
            </div>
        <?php endif; ?>

        <?php if (empty($courses)): ?>

            <div class="empty-course-box">
                <div class="empty-icon">No courses</div>
                <h2>No published courses found</h2>
                <p>Try changing your search or filter.</p>
            </div>

        <?php else: ?>

            <div class="student-course-grid">

                <?php foreach ($courses as $course): ?>
                    <?php
                        $thumbnail = $course['thumbnail'] ?? '';
                        $thumbnailPath = $thumbnail !== ''
                            ? 'assets/uploads/course_thumbnails/' . $thumbnail
                            : 'assets/images/course-placeholder.svg';

                        if ($thumbnail !== '' && !is_file(PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $thumbnailPath))) {
                            $thumbnailPath = 'assets/images/course-placeholder.svg';
                        }
                    ?>

                    <article class="student-course-card">

                        <div class="course-image">
                            <img 
                                src="<?php echo h($thumbnailPath); ?>" 
                                alt="<?php echo h($course['title']); ?>"
                            >
                        </div>

                        <div class="course-body">
                            <div class="course-tags">
                                <span><?php echo h(level_label($course['level'])); ?></span>
                                <span><?php echo h($course['language']); ?></span>
                            </div>

                            <h2><?php echo h($course['title']); ?></h2>

                            <p class="course-short">
                                <?php echo h($course['short_description'] ?: 'No description added.'); ?>
                            </p>

                            <div class="course-info">
                                <div>
                                    <span>Instructor</span>
                                    <strong><?php echo h($course['instructor_name']); ?></strong>
                                </div>

                                <div>
                                    <span>Category</span>
                                    <strong><?php echo h($course['category_name'] ?: 'General'); ?></strong>
                                </div>

                               <div>
    <span>Lessons</span>
    <strong><?php echo (int) $course['lesson_count']; ?></strong>
</div>

<div>
    <span>Students</span>
    <strong><?php echo number_format((int) $course['student_count']); ?></strong>
</div>
                            </div>

                            <div class="course-bottom">
                                <strong class="course-price">
                                    Rs. <?php echo number_format((float) $course['price'], 2); ?>
                                </strong>

                                <a 
                                    href="course-details.php?slug=<?php echo urlencode($course['slug']); ?>" 
                                    class="details-btn"
                                >
                                    View Details
                                </a>
                            </div>
                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>
</main>


