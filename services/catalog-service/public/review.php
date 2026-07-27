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

$respond = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
};

try {
    $database = Database::connect();
    ServiceAuth::requireUser($database, (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''), 'admin');
    $statement = $database->query(
        'SELECT c.id,c.title,c.subtitle,c.slug,c.short_description,c.full_description,c.learning_outcomes,c.requirements,c.target_audience,c.tags,c.thumbnail,c.intro_video_url,'
        . 'c.price,c.discount_price,c.level,c.language,c.duration,c.submitted_at,u.full_name AS instructor_name,u.email AS instructor_email,cat.name AS category_name '
        . 'FROM courses c INNER JOIN users u ON u.id=c.instructor_id LEFT JOIN categories cat ON cat.id=c.category_id '
        . 'WHERE c.status=\'pending\' ORDER BY c.submitted_at ASC,c.id ASC'
    );
    $courses = $statement->fetchAll();
    $sections = $database->prepare('SELECT id,title,sort_order FROM course_sections WHERE course_id=:course_id ORDER BY sort_order,id');
    $lessons = $database->prepare('SELECT id,title,content_type,content_url,content_name,content_text,duration_minutes,is_preview,sort_order FROM course_lessons WHERE section_id=:section_id ORDER BY sort_order,id');
    foreach ($courses as &$course) {
        foreach (['learning_outcomes', 'requirements', 'target_audience'] as $field) {
            $decoded = json_decode((string) ($course[$field] ?? '[]'), true);
            $course[$field] = is_array($decoded) ? array_values($decoded) : [];
        }
        $course['thumbnail_url'] = trim((string) ($course['thumbnail'] ?? '')) !== ''
            ? '/media/course-thumbnails/' . rawurlencode(basename((string) $course['thumbnail']))
            : '';
        unset($course['thumbnail']);
        $sections->execute(['course_id' => (int) $course['id']]);
        $courseSections = $sections->fetchAll();
        foreach ($courseSections as &$section) {
            $lessons->execute(['section_id' => (int) $section['id']]);
            $section['lessons'] = $lessons->fetchAll();
        }
        unset($section);
        $course['sections'] = $courseSections;
    }
    unset($course);
    $respond(['data' => $courses]);
} catch (ServiceAuthenticationException $exception) {
    $respond(['error' => $exception->getMessage()], 401);
} catch (ServiceAuthorizationException $exception) {
    $respond(['error' => $exception->getMessage()], 403);
} catch (Throwable $exception) {
    error_log('Course review queue failure: ' . $exception->getMessage());
    $respond(['error' => 'The complete course review queue is unavailable.'], 503);
}
