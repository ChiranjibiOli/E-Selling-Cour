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
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    if (preg_match('#^/api/v1/learning/courses/(\d+)/player$#', $path, $matches) !== 1
        || strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET'
    ) {
        $respond(['error' => 'Learning player route not found.'], 404);
    }
    $database = Database::connect();
    $student = ServiceAuth::requireUser($database, (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''), 'student');
    $courseId = (int) $matches[1];
    $course = $database->prepare(
        'SELECT c.id,c.title,c.short_description,c.language,c.duration,c.content_version,u.full_name AS instructor_name '
        . 'FROM enrollments e INNER JOIN courses c ON c.id=e.course_id INNER JOIN users u ON u.id=c.instructor_id '
        . 'WHERE e.student_id=:student_id AND e.course_id=:course_id AND e.status=\'active\' AND c.status=\'published\' LIMIT 1'
    );
    $course->execute(['student_id' => $student['id'], 'course_id' => $courseId]);
    $record = $course->fetch();
    if (!is_array($record)) {
        throw new ServiceAuthorizationException('Active enrollment is required to open this published course.');
    }

    $sections = $database->prepare('SELECT id,title,sort_order FROM course_sections WHERE course_id=:course_id ORDER BY sort_order,id');
    $sections->execute(['course_id' => $courseId]);
    $courseSections = $sections->fetchAll();
    $lessons = $database->prepare(
        'SELECT id,title,content_type,content_url,content_name,content_text,duration_minutes,is_preview,sort_order '
        . 'FROM course_lessons WHERE section_id=:section_id ORDER BY sort_order,id'
    );
    foreach ($courseSections as &$section) {
        $lessons->execute(['section_id' => (int) $section['id']]);
        $section['lessons'] = $lessons->fetchAll();
    }
    unset($section);
    $record['sections'] = $courseSections;

    $completed = $database->prepare('SELECT lesson_id,completed_at FROM lesson_progress WHERE student_id=:student_id AND course_id=:course_id ORDER BY completed_at');
    $completed->execute(['student_id' => $student['id'], 'course_id' => $courseId]);
    $record['completed_lessons'] = $completed->fetchAll();
    $respond(['data' => $record]);
} catch (ServiceAuthenticationException $exception) {
    $respond(['error' => $exception->getMessage()], 401);
} catch (ServiceAuthorizationException $exception) {
    $respond(['error' => $exception->getMessage()], 403);
} catch (Throwable $exception) {
    error_log('Learning player failure: ' . $exception->getMessage());
    $respond(['error' => 'The protected course player is unavailable.'], 503);
}
