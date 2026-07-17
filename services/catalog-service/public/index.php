<?php

declare(strict_types=1);

use CourseHub\Services\Shared\Database;

require_once dirname(__DIR__, 2) . '/_shared/Database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

$respond = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
};
$thumbnailUrl = static function (?string $path): string {
    $name = basename(trim((string) $path));
    return $name !== '' ? '/media/course-thumbnails/' . rawurlencode($name) : '';
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

    if ($method === 'GET' && preg_match('#^/api/v1/courses/(\d+)$#', $path, $matches) === 1) {
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
        $lessonStatement = $database->prepare(
            'SELECT id, title, content_type, duration_minutes, is_preview, sort_order '
            . 'FROM course_lessons WHERE section_id = :section_id ORDER BY sort_order, id'
        );
        foreach ($sections as &$section) {
            $lessonStatement->execute(['section_id' => (int) $section['id']]);
            $section['lessons'] = $lessonStatement->fetchAll();
        }
        unset($section);
        $course['sections'] = $sections;
        $respond(['data' => $course]);
    }

    $respond(['error' => 'Catalog route not found.'], 404);
} catch (Throwable $exception) {
    error_log('Catalog service failure: ' . $exception->getMessage());
    $respond(['error' => 'Catalog service is unavailable.'], 503);
}
