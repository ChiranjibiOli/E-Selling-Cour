<?php

declare(strict_types=1);

use CourseHub\Services\Shared\Database;
use CourseHub\Services\Shared\ServiceAuth;
use CourseHub\Services\Shared\ServiceAuthenticationException;
use CourseHub\Services\Shared\ServiceAuthorizationException;

require_once dirname(__DIR__, 2) . '/_shared/Database.php';
require_once dirname(__DIR__, 2) . '/_shared/ServiceAuth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');

$respond = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
};

$jsonInput = static function (): array {
    $decoded = json_decode((string) file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Request body must be a JSON object.');
    }
    return $decoded;
};

$thumbnailUrl = static function (?string $storedPath): string {
    $name = basename(trim((string) $storedPath));
    return $name !== '' ? '/media/course-thumbnails/' . rawurlencode($name) : '';
};

$coursePayload = static function (array $input): array {
    $title = trim((string) ($input['title'] ?? ''));
    $shortDescription = trim((string) ($input['short_description'] ?? ''));
    $fullDescription = trim((string) ($input['full_description'] ?? ''));
    $categoryId = (int) ($input['category_id'] ?? 0);
    $price = filter_var($input['price'] ?? null, FILTER_VALIDATE_FLOAT);
    $level = strtolower(trim((string) ($input['level'] ?? 'beginner')));
    $language = trim((string) ($input['language'] ?? 'English'));
    $duration = trim((string) ($input['duration'] ?? ''));
    $thumbnail = trim((string) ($input['thumbnail'] ?? ''));

    if ($title === '' || mb_strlen($title) > 180) {
        throw new InvalidArgumentException('Course title is required and must be 180 characters or fewer.');
    }
    if ($shortDescription === '' || mb_strlen($shortDescription) > 500) {
        throw new InvalidArgumentException('A short description of 500 characters or fewer is required.');
    }
    if ($fullDescription === '' || mb_strlen($fullDescription) > 50_000) {
        throw new InvalidArgumentException('A complete course description is required.');
    }
    if ($categoryId < 1) {
        throw new InvalidArgumentException('Choose a valid course category.');
    }
    if ($price === false || $price < 0 || $price > 10_000_000) {
        throw new InvalidArgumentException('Enter a valid non-negative price.');
    }
    if (!in_array($level, ['beginner', 'intermediate', 'advanced'], true)) {
        throw new InvalidArgumentException('Choose a valid course level.');
    }
    if ($language === '' || mb_strlen($language) > 60 || mb_strlen($duration) > 80 || mb_strlen($thumbnail) > 255) {
        throw new InvalidArgumentException('Language, duration, or thumbnail value is invalid.');
    }

    return [
        'title' => $title,
        'category_id' => $categoryId,
        'short_description' => $shortDescription,
        'full_description' => $fullDescription,
        'price' => number_format((float) $price, 2, '.', ''),
        'level' => $level,
        'language' => $language,
        'duration' => $duration !== '' ? $duration : null,
        'thumbnail' => $thumbnail !== '' ? basename($thumbnail) : null,
    ];
};

$slugify = static function (string $title): string {
    $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));
    return substr($slug !== '' ? $slug : 'course', 0, 170);
};

try {
    $database = Database::connect();

    if ($path === '/health' && $method === 'GET') {
        $database->query('SELECT 1');
        $respond(['status' => 'ok', 'service' => 'catalog-service']);
    }

    if ($path === '/api/v1/categories' && $method === 'GET') {
        $limit = max(1, min(50, (int) ($_GET['limit'] ?? 30)));
        $statement = $database->prepare(
            'SELECT cat.id, cat.name, cat.slug, cat.description, COUNT(c.id) AS course_count '
            . 'FROM categories cat LEFT JOIN courses c ON c.category_id = cat.id AND c.status = \'published\' '
            . 'WHERE cat.status = \'active\' GROUP BY cat.id ORDER BY cat.name ASC LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $respond(['data' => $statement->fetchAll()]);
    }

    if ($path === '/api/v1/categories' && $method === 'POST') {
        ServiceAuth::requireUser($database, $authorization, 'admin');
        $input = $jsonInput();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            throw new InvalidArgumentException('Category name is required and must be 100 characters or fewer.');
        }
        $slug = $slugify($name);
        $statement = $database->prepare('INSERT INTO categories (name, slug, description, status) VALUES (:name, :slug, :description, \'active\')');
        $statement->execute(['name' => $name, 'slug' => $slug, 'description' => trim((string) ($input['description'] ?? '')) ?: null]);
        $respond(['message' => 'Category created.', 'id' => (int) $database->lastInsertId()], 201);
    }

    if ($path === '/api/v1/courses/mine' && $method === 'GET') {
        $instructor = ServiceAuth::requireUser($database, $authorization, 'instructor');
        $status = strtolower(trim((string) ($_GET['status'] ?? '')));
        $conditions = ['c.instructor_id = :instructor_id'];
        $parameters = ['instructor_id' => $instructor['id']];
        if (in_array($status, ['draft', 'pending', 'published', 'rejected', 'archived'], true)) {
            $conditions[] = 'c.status = :status';
            $parameters['status'] = $status;
        }
        $statement = $database->prepare(
            'SELECT c.id, c.title, c.slug, c.short_description, c.price, c.level, c.language, c.duration, c.status, c.review_note, '
            . 'c.submitted_at, c.reviewed_at, c.updated_at, cat.name AS category_name FROM courses c '
            . 'LEFT JOIN categories cat ON cat.id = c.category_id WHERE ' . implode(' AND ', $conditions) . ' ORDER BY c.updated_at DESC'
        );
        $statement->execute($parameters);
        $respond(['data' => $statement->fetchAll()]);
    }

    if ($path === '/api/v1/courses/pending' && $method === 'GET') {
        ServiceAuth::requireUser($database, $authorization, 'admin');
        $statement = $database->query(
            'SELECT c.id, c.title, c.slug, c.short_description, c.full_description, c.price, c.level, c.language, c.duration, '
            . 'c.submitted_at, u.full_name AS instructor_name, u.email AS instructor_email, cat.name AS category_name '
            . 'FROM courses c INNER JOIN users u ON u.id = c.instructor_id LEFT JOIN categories cat ON cat.id = c.category_id '
            . 'WHERE c.status = \'pending\' ORDER BY c.submitted_at ASC, c.id ASC'
        );
        $respond(['data' => $statement->fetchAll()]);
    }

    if ($path === '/api/v1/courses' && $method === 'POST') {
        $instructor = ServiceAuth::requireUser($database, $authorization, 'instructor');
        $input = $coursePayload($jsonInput());
        $category = $database->prepare('SELECT id FROM categories WHERE id = :id AND status = \'active\' LIMIT 1');
        $category->execute(['id' => $input['category_id']]);
        if ($category->fetch() === false) {
            throw new InvalidArgumentException('The selected category is unavailable.');
        }
        $baseSlug = $slugify($input['title']);
        $slug = $baseSlug;
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $exists = $database->prepare('SELECT id FROM courses WHERE slug = :slug LIMIT 1');
            $exists->execute(['slug' => $slug]);
            if ($exists->fetch() === false) {
                break;
            }
            $slug = $baseSlug . '-' . bin2hex(random_bytes(2));
        }
        $statement = $database->prepare(
            'INSERT INTO courses (instructor_id, category_id, title, slug, short_description, full_description, thumbnail, price, level, language, duration, status) '
            . 'VALUES (:instructor_id, :category_id, :title, :slug, :short_description, :full_description, :thumbnail, :price, :level, :language, :duration, \'draft\')'
        );
        $statement->execute($input + ['instructor_id' => $instructor['id'], 'slug' => $slug]);
        $respond(['message' => 'Course saved as draft.', 'id' => (int) $database->lastInsertId(), 'status' => 'draft'], 201);
    }

    if ($path === '/api/v1/courses' && $method === 'GET') {
        $conditions = ['c.status = \'published\''];
        $parameters = [];
        $query = trim((string) ($_GET['q'] ?? ''));
        $category = trim((string) ($_GET['category'] ?? ''));
        $level = trim((string) ($_GET['level'] ?? ''));
        $featured = (string) ($_GET['featured'] ?? '');
        $limit = max(1, min(48, (int) ($_GET['limit'] ?? 24)));

        if ($query !== '') {
            $conditions[] = '(c.title LIKE :search OR c.short_description LIKE :search)';
            $parameters['search'] = '%' . mb_substr($query, 0, 120) . '%';
        }
        if ($category !== '') {
            $conditions[] = 'cat.slug = :category';
            $parameters['category'] = mb_substr($category, 0, 120);
        }
        if (in_array($level, ['beginner', 'intermediate', 'advanced'], true)) {
            $conditions[] = 'c.level = :level';
            $parameters['level'] = $level;
        }
        if ($featured === '1') {
            $conditions[] = 'c.is_featured = 1';
        }

        $sql = 'SELECT c.id, c.title, c.slug, c.short_description, c.thumbnail, c.price, c.level, c.language, c.duration, c.is_featured, '
            . 'u.full_name AS instructor_name, cat.name AS category_name, cat.slug AS category_slug '
            . 'FROM courses c INNER JOIN users u ON u.id = c.instructor_id '
            . 'LEFT JOIN categories cat ON cat.id = c.category_id WHERE ' . implode(' AND ', $conditions)
            . ' ORDER BY c.is_featured DESC, c.updated_at DESC LIMIT :limit';
        $statement = $database->prepare($sql);
        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $courses = $statement->fetchAll();
        foreach ($courses as &$course) {
            $course['thumbnail_url'] = $thumbnailUrl($course['thumbnail'] ?? null);
            unset($course['thumbnail']);
        }
        unset($course);
        $respond(['data' => $courses]);
    }

    if (preg_match('#^/api/v1/courses/(\d+)$#', $path, $matches) === 1 && $method === 'GET') {
        $statement = $database->prepare(
            'SELECT c.id, c.title, c.slug, c.short_description, c.full_description, c.thumbnail, c.price, c.level, c.language, c.duration, '
            . 'u.full_name AS instructor_name, cat.name AS category_name, cat.slug AS category_slug '
            . 'FROM courses c INNER JOIN users u ON u.id = c.instructor_id LEFT JOIN categories cat ON cat.id = c.category_id '
            . 'WHERE c.id = :id AND c.status = \'published\' LIMIT 1'
        );
        $statement->execute(['id' => (int) $matches[1]]);
        $course = $statement->fetch();
        if (!is_array($course)) {
            $respond(['error' => 'Published course not found.'], 404);
        }
        $course['thumbnail_url'] = $thumbnailUrl($course['thumbnail'] ?? null);
        unset($course['thumbnail']);
        $sectionsStatement = $database->prepare('SELECT id, title, sort_order FROM course_sections WHERE course_id = :course_id ORDER BY sort_order, id');
        $sectionsStatement->execute(['course_id' => (int) $course['id']]);
        $sections = $sectionsStatement->fetchAll();
        $lessonStatement = $database->prepare('SELECT id, title, content_type, duration_minutes, is_preview, sort_order FROM course_lessons WHERE section_id = :section_id ORDER BY sort_order, id');
        foreach ($sections as &$section) {
            $lessonStatement->execute(['section_id' => (int) $section['id']]);
            $section['lessons'] = $lessonStatement->fetchAll();
        }
        unset($section);
        $course['sections'] = $sections;
        $respond(['data' => $course]);
    }

    if (preg_match('#^/api/v1/courses/(\d+)/edit$#', $path, $matches) === 1 && $method === 'GET') {
        $instructor = ServiceAuth::requireUser($database, $authorization, 'instructor');
        $statement = $database->prepare('SELECT * FROM courses WHERE id = :id AND instructor_id = :instructor_id LIMIT 1');
        $statement->execute(['id' => (int) $matches[1], 'instructor_id' => $instructor['id']]);
        $course = $statement->fetch();
        if (!is_array($course)) {
            $respond(['error' => 'Course not found.'], 404);
        }
        $respond(['data' => $course]);
    }

    if (preg_match('#^/api/v1/courses/(\d+)$#', $path, $matches) === 1 && in_array($method, ['PUT', 'PATCH'], true)) {
        $instructor = ServiceAuth::requireUser($database, $authorization, 'instructor');
        $input = $coursePayload($jsonInput());
        $category = $database->prepare('SELECT id FROM categories WHERE id = :id AND status = \'active\' LIMIT 1');
        $category->execute(['id' => $input['category_id']]);
        if ($category->fetch() === false) {
            throw new InvalidArgumentException('The selected category is unavailable.');
        }
        $owned = $database->prepare('SELECT id, status FROM courses WHERE id = :id AND instructor_id = :instructor_id LIMIT 1');
        $owned->execute(['id' => (int) $matches[1], 'instructor_id' => $instructor['id']]);
        $course = $owned->fetch();
        if (!is_array($course)) {
            $respond(['error' => 'Course not found.'], 404);
        }
        if ($course['status'] === 'pending') {
            throw new ServiceAuthorizationException('A pending course cannot be edited until the administrator reviews it.');
        }
        $newStatus = $course['status'] === 'published' ? 'pending' : 'draft';
        $statement = $database->prepare(
            'UPDATE courses SET category_id=:category_id, title=:title, short_description=:short_description, full_description=:full_description, '
            . 'thumbnail=:thumbnail, price=:price, level=:level, language=:language, duration=:duration, status=:status, '
            . 'submitted_at=IF(:submitted_status = \'pending\', NOW(), NULL), reviewed_at=NULL, reviewed_by=NULL, review_note=NULL WHERE id=:id AND instructor_id=:instructor_id'
        );
        $statement->execute($input + ['status' => $newStatus, 'submitted_status' => $newStatus, 'id' => (int) $matches[1], 'instructor_id' => $instructor['id']]);
        $respond(['message' => $newStatus === 'pending' ? 'Changes submitted for renewed approval.' : 'Draft updated.', 'status' => $newStatus]);
    }

    if (preg_match('#^/api/v1/courses/(\d+)/submit$#', $path, $matches) === 1 && $method === 'POST') {
        $instructor = ServiceAuth::requireUser($database, $authorization, 'instructor');
        $statement = $database->prepare(
            'UPDATE courses SET status=\'pending\', submitted_at=NOW(), reviewed_at=NULL, reviewed_by=NULL, review_note=NULL '
            . 'WHERE id=:id AND instructor_id=:instructor_id AND status IN (\'draft\',\'rejected\')'
        );
        $statement->execute(['id' => (int) $matches[1], 'instructor_id' => $instructor['id']]);
        if ($statement->rowCount() !== 1) {
            throw new ServiceAuthorizationException('Only your draft or rejected course can be submitted.');
        }
        $respond(['message' => 'Course submitted for approval.', 'status' => 'pending']);
    }

    if (preg_match('#^/api/v1/courses/(\d+)/(approve|reject)$#', $path, $matches) === 1 && $method === 'POST') {
        $admin = ServiceAuth::requireUser($database, $authorization, 'admin');
        $action = $matches[2];
        $input = $jsonInput();
        $note = trim((string) ($input['note'] ?? ''));
        if (mb_strlen($note) > 1000) {
            throw new InvalidArgumentException('Review notes must be 1000 characters or fewer.');
        }
        if ($action === 'reject' && $note === '') {
            throw new InvalidArgumentException('A clear rejection reason is required.');
        }
        $status = $action === 'approve' ? 'published' : 'rejected';
        $database->beginTransaction();
        $courseStatement = $database->prepare('SELECT id, instructor_id, title FROM courses WHERE id=:id AND status=\'pending\' FOR UPDATE');
        $courseStatement->execute(['id' => (int) $matches[1]]);
        $pendingCourse = $courseStatement->fetch();
        if (!is_array($pendingCourse)) {
            $database->rollBack();
            throw new ServiceAuthorizationException('Only a pending course can be reviewed.');
        }
        $statement = $database->prepare(
            'UPDATE courses SET status=:status, reviewed_by=:reviewed_by, reviewed_at=NOW(), review_note=:review_note '
            . 'WHERE id=:id AND status=\'pending\''
        );
        $statement->execute(['status' => $status, 'reviewed_by' => $admin['id'], 'review_note' => $note !== '' ? $note : null, 'id' => (int) $matches[1]]);
        if ($statement->rowCount() !== 1) {
            $database->rollBack();
            throw new ServiceAuthorizationException('Only a pending course can be reviewed.');
        }
        $notification = $database->prepare('INSERT INTO notifications (user_id, title, message, notification_type) VALUES (:user_id, :title, :message, \'course_review\')');
        $notification->execute([
            'user_id' => (int) $pendingCourse['instructor_id'],
            'title' => $action === 'approve' ? 'Course published' : 'Course needs changes',
            'message' => $action === 'approve'
                ? 'Your course "' . (string) $pendingCourse['title'] . '" is now published.'
                : 'Your course "' . (string) $pendingCourse['title'] . '" was returned with this note: ' . $note,
        ]);
        $database->commit();
        $respond(['message' => $action === 'approve' ? 'Course published.' : 'Course rejected.', 'status' => $status]);
    }

    $respond(['error' => 'Catalog route not found.'], 404);
} catch (ServiceAuthenticationException $exception) {
    $respond(['error' => $exception->getMessage()], 401);
} catch (ServiceAuthorizationException $exception) {
    $respond(['error' => $exception->getMessage()], 403);
} catch (InvalidArgumentException $exception) {
    $respond(['error' => $exception->getMessage()], 422);
} catch (JsonException) {
    $respond(['error' => 'Malformed JSON request.'], 400);
} catch (PDOException $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Catalog database failure: ' . $exception->getMessage());
    $respond(['error' => $exception->getCode() === '23000' ? 'That value is already in use.' : 'Catalog request could not be completed.'], 409);
} catch (Throwable $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Catalog service failure: ' . $exception->getMessage());
    $respond(['error' => 'Catalog service is unavailable.'], 503);
}
