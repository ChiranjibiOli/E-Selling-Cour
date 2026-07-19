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
    $raw = (string) file_get_contents('php://input');
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Request body must be a JSON object.');
    }
    return $decoded;
};

$requireOwnedCourse = static function (PDO $database, int $courseId, int $instructorId): array {
    $statement = $database->prepare('SELECT id, title, status FROM courses WHERE id = :id AND instructor_id = :instructor_id LIMIT 1');
    $statement->execute(['id' => $courseId, 'instructor_id' => $instructorId]);
    $course = $statement->fetch();
    if (!is_array($course)) {
        throw new ServiceAuthorizationException('That course is not available in your instructor studio.');
    }
    if ($course['status'] === 'pending') {
        throw new ServiceAuthorizationException('A pending course is locked until the administrator finishes reviewing it.');
    }
    return $course;
};

$curriculum = static function (PDO $database, int $courseId, bool $includeContent = false): array {
    $sectionsStatement = $database->prepare('SELECT id, course_id, title, sort_order, created_at, updated_at FROM course_sections WHERE course_id = :course_id ORDER BY sort_order, id');
    $sectionsStatement->execute(['course_id' => $courseId]);
    $sections = $sectionsStatement->fetchAll();
    $lessonColumns = $includeContent
        ? 'id, section_id, title, content_type, content_url, content_text, duration_minutes, is_preview, sort_order, created_at, updated_at'
        : 'id, section_id, title, content_type, duration_minutes, is_preview, sort_order, created_at, updated_at';
    $lessonsStatement = $database->prepare('SELECT ' . $lessonColumns . ' FROM course_lessons WHERE section_id = :section_id ORDER BY sort_order, id');
    foreach ($sections as &$section) {
        $lessonsStatement->execute(['section_id' => (int) $section['id']]);
        $section['lessons'] = $lessonsStatement->fetchAll();
    }
    unset($section);
    return $sections;
};

$lessonPayload = static function (array $input): array {
    $title = trim((string) ($input['title'] ?? ''));
    $type = strtolower(trim((string) ($input['content_type'] ?? 'text')));
    $url = trim((string) ($input['content_url'] ?? ''));
    $text = trim((string) ($input['content_text'] ?? ''));
    $duration = (int) ($input['duration_minutes'] ?? 0);
    $preview = filter_var($input['is_preview'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    $sortOrder = max(1, (int) ($input['sort_order'] ?? 1));

    if ($title === '' || mb_strlen($title) > 180) {
        throw new InvalidArgumentException('Lesson title is required and must be 180 characters or fewer.');
    }
    if (!in_array($type, ['text', 'pdf', 'word', 'video', 'link'], true)) {
        throw new InvalidArgumentException('Choose a supported lesson content type.');
    }
    if ($duration < 0 || $duration > 10_000 || mb_strlen($url) > 500 || mb_strlen($text) > 200_000) {
        throw new InvalidArgumentException('Lesson duration or content is invalid.');
    }
    if (in_array($type, ['video', 'pdf', 'word', 'link'], true) && $url === '') {
        throw new InvalidArgumentException('This lesson type requires a validated content URL or stored media path.');
    }
    if ($type === 'text' && $text === '') {
        throw new InvalidArgumentException('Text lessons require lesson content.');
    }
    return [
        'title' => $title,
        'content_type' => $type,
        'content_url' => $url !== '' ? $url : null,
        'content_text' => $text !== '' ? $text : null,
        'duration_minutes' => $duration,
        'is_preview' => $preview,
        'sort_order' => $sortOrder,
    ];
};

try {
    $database = Database::connect();

    if ($path === '/health' && $method === 'GET') {
        $database->query('SELECT 1');
        $respond(['status' => 'ok', 'service' => 'learning-service']);
    }

    if (preg_match('#^/api/v1/learning/courses/(\d+)/manage$#', $path, $matches) === 1 && $method === 'GET') {
        $instructor = ServiceAuth::requireUser($database, $authorization, 'instructor');
        $courseId = (int) $matches[1];
        $course = $requireOwnedCourse($database, $courseId, $instructor['id']);
        $course['sections'] = $curriculum($database, $courseId, true);
        $respond(['data' => $course]);
    }

    if (preg_match('#^/api/v1/learning/courses/(\d+)/sections$#', $path, $matches) === 1 && $method === 'POST') {
        $instructor = ServiceAuth::requireUser($database, $authorization, 'instructor');
        $courseId = (int) $matches[1];
        $requireOwnedCourse($database, $courseId, $instructor['id']);
        $input = $jsonInput();
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 180) {
            throw new InvalidArgumentException('Section title is required and must be 180 characters or fewer.');
        }
        $sort = max(1, (int) ($input['sort_order'] ?? 1));
        $statement = $database->prepare('INSERT INTO course_sections (course_id, title, sort_order) VALUES (:course_id, :title, :sort_order)');
        $statement->execute(['course_id' => $courseId, 'title' => $title, 'sort_order' => $sort]);
        $respond(['message' => 'Course section created.', 'id' => (int) $database->lastInsertId()], 201);
    }

    if (preg_match('#^/api/v1/learning/sections/(\d+)$#', $path, $matches) === 1 && in_array($method, ['PATCH', 'PUT'], true)) {
        $instructor = ServiceAuth::requireUser($database, $authorization, 'instructor');
        $input = $jsonInput();
        $section = $database->prepare('SELECT cs.id, cs.course_id FROM course_sections cs INNER JOIN courses c ON c.id = cs.course_id WHERE cs.id = :id AND c.instructor_id = :instructor_id LIMIT 1');
        $section->execute(['id' => (int) $matches[1], 'instructor_id' => $instructor['id']]);
        $record = $section->fetch();
        if (!is_array($record)) {
            throw new ServiceAuthorizationException('That section is not available in your course.');
        }
        $requireOwnedCourse($database, (int) $record['course_id'], $instructor['id']);
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 180) {
            throw new InvalidArgumentException('Section title is required.');
        }
        $statement = $database->prepare('UPDATE course_sections SET title = :title, sort_order = :sort_order WHERE id = :id');
        $statement->execute(['title' => $title, 'sort_order' => max(1, (int) ($input['sort_order'] ?? 1)), 'id' => (int) $matches[1]]);
        $respond(['message' => 'Section updated.']);
    }

    if (preg_match('#^/api/v1/learning/sections/(\d+)$#', $path, $matches) === 1 && $method === 'DELETE') {
        $instructor = ServiceAuth::requireUser($database, $authorization, 'instructor');
        $section = $database->prepare('SELECT cs.id, cs.course_id FROM course_sections cs INNER JOIN courses c ON c.id = cs.course_id WHERE cs.id = :id AND c.instructor_id = :instructor_id LIMIT 1');
        $section->execute(['id' => (int) $matches[1], 'instructor_id' => $instructor['id']]);
        $record = $section->fetch();
        if (!is_array($record)) {
            throw new ServiceAuthorizationException('That section is not available in your course.');
        }
        $requireOwnedCourse($database, (int) $record['course_id'], $instructor['id']);
        $delete = $database->prepare('DELETE FROM course_sections WHERE id = :id');
        $delete->execute(['id' => (int) $matches[1]]);
        $respond(['message' => 'Section and its lessons deleted.']);
    }

    if (preg_match('#^/api/v1/learning/sections/(\d+)/lessons$#', $path, $matches) === 1 && $method === 'POST') {
        $instructor = ServiceAuth::requireUser($database, $authorization, 'instructor');
        $sectionId = (int) $matches[1];
        $section = $database->prepare('SELECT cs.course_id FROM course_sections cs INNER JOIN courses c ON c.id = cs.course_id WHERE cs.id = :id AND c.instructor_id = :instructor_id LIMIT 1');
        $section->execute(['id' => $sectionId, 'instructor_id' => $instructor['id']]);
        $record = $section->fetch();
        if (!is_array($record)) {
            throw new ServiceAuthorizationException('That section is not available in your course.');
        }
        $requireOwnedCourse($database, (int) $record['course_id'], $instructor['id']);
        $input = $lessonPayload($jsonInput());
        $statement = $database->prepare(
            'INSERT INTO course_lessons (section_id, title, content_type, content_url, content_text, duration_minutes, is_preview, sort_order) '
            . 'VALUES (:section_id, :title, :content_type, :content_url, :content_text, :duration_minutes, :is_preview, :sort_order)'
        );
        $statement->execute($input + ['section_id' => $sectionId]);
        $respond(['message' => 'Lesson created.', 'id' => (int) $database->lastInsertId()], 201);
    }

    if (preg_match('#^/api/v1/learning/lessons/(\d+)$#', $path, $matches) === 1 && in_array($method, ['PATCH', 'PUT'], true)) {
        $instructor = ServiceAuth::requireUser($database, $authorization, 'instructor');
        $lessonId = (int) $matches[1];
        $lesson = $database->prepare('SELECT cl.id, cs.course_id FROM course_lessons cl INNER JOIN course_sections cs ON cs.id = cl.section_id INNER JOIN courses c ON c.id = cs.course_id WHERE cl.id = :id AND c.instructor_id = :instructor_id LIMIT 1');
        $lesson->execute(['id' => $lessonId, 'instructor_id' => $instructor['id']]);
        $record = $lesson->fetch();
        if (!is_array($record)) {
            throw new ServiceAuthorizationException('That lesson is not available in your course.');
        }
        $requireOwnedCourse($database, (int) $record['course_id'], $instructor['id']);
        $input = $lessonPayload($jsonInput());
        $statement = $database->prepare(
            'UPDATE course_lessons SET title=:title, content_type=:content_type, content_url=:content_url, content_text=:content_text, duration_minutes=:duration_minutes, is_preview=:is_preview, sort_order=:sort_order WHERE id=:id'
        );
        $statement->execute($input + ['id' => $lessonId]);
        $respond(['message' => 'Lesson updated.']);
    }

    if (preg_match('#^/api/v1/learning/lessons/(\d+)$#', $path, $matches) === 1 && $method === 'DELETE') {
        $instructor = ServiceAuth::requireUser($database, $authorization, 'instructor');
        $lessonId = (int) $matches[1];
        $lesson = $database->prepare('SELECT cl.id, cs.course_id FROM course_lessons cl INNER JOIN course_sections cs ON cs.id = cl.section_id INNER JOIN courses c ON c.id = cs.course_id WHERE cl.id = :id AND c.instructor_id = :instructor_id LIMIT 1');
        $lesson->execute(['id' => $lessonId, 'instructor_id' => $instructor['id']]);
        $record = $lesson->fetch();
        if (!is_array($record)) {
            throw new ServiceAuthorizationException('That lesson is not available in your course.');
        }
        $requireOwnedCourse($database, (int) $record['course_id'], $instructor['id']);
        $delete = $database->prepare('DELETE FROM course_lessons WHERE id = :id');
        $delete->execute(['id' => $lessonId]);
        $respond(['message' => 'Lesson deleted.']);
    }

    if (preg_match('#^/api/v1/learning/courses/(\d+)/player$#', $path, $matches) === 1 && $method === 'GET') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $courseId = (int) $matches[1];
        $course = $database->prepare(
            'SELECT c.id, c.title, c.short_description, c.language, c.duration, u.full_name AS instructor_name '
            . 'FROM enrollments e INNER JOIN courses c ON c.id = e.course_id INNER JOIN users u ON u.id = c.instructor_id '
            . 'WHERE e.student_id = :student_id AND e.course_id = :course_id AND e.status = \'active\' LIMIT 1'
        );
        $course->execute(['student_id' => $student['id'], 'course_id' => $courseId]);
        $record = $course->fetch();
        if (!is_array($record)) {
            throw new ServiceAuthorizationException('Active enrollment is required to open this course.');
        }
        $record['sections'] = $curriculum($database, $courseId, true);
        $completed = $database->prepare('SELECT lesson_id, completed_at FROM lesson_progress WHERE student_id = :student_id AND course_id = :course_id');
        $completed->execute(['student_id' => $student['id'], 'course_id' => $courseId]);
        $record['completed_lessons'] = $completed->fetchAll();
        $respond(['data' => $record]);
    }

    if (preg_match('#^/api/v1/progress/lessons/(\d+)/complete$#', $path, $matches) === 1 && $method === 'POST') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $lessonId = (int) $matches[1];
        $lesson = $database->prepare(
            'SELECT cl.id, cs.course_id FROM course_lessons cl INNER JOIN course_sections cs ON cs.id = cl.section_id '
            . 'INNER JOIN enrollments e ON e.course_id = cs.course_id WHERE cl.id = :lesson_id AND e.student_id = :student_id AND e.status = \'active\' LIMIT 1'
        );
        $lesson->execute(['lesson_id' => $lessonId, 'student_id' => $student['id']]);
        $record = $lesson->fetch();
        if (!is_array($record)) {
            throw new ServiceAuthorizationException('This lesson is not part of an actively enrolled course.');
        }
        $statement = $database->prepare(
            'INSERT INTO lesson_progress (student_id, course_id, lesson_id, completed_at) VALUES (:student_id, :course_id, :lesson_id, NOW()) '
            . 'ON DUPLICATE KEY UPDATE completed_at = completed_at, updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute(['student_id' => $student['id'], 'course_id' => (int) $record['course_id'], 'lesson_id' => $lessonId]);
        $respond(['message' => 'Lesson marked complete.']);
    }

    if ($path === '/api/v1/progress/mine' && $method === 'GET') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $statement = $database->prepare(
            'SELECT e.course_id, c.title, c.thumbnail, u.full_name AS instructor_name, e.granted_at, '
            . '(SELECT COUNT(*) FROM course_lessons cl INNER JOIN course_sections cs ON cs.id = cl.section_id WHERE cs.course_id = c.id) AS total_lessons, '
            . '(SELECT COUNT(*) FROM lesson_progress lp WHERE lp.student_id = e.student_id AND lp.course_id = c.id) AS completed_lessons, '
            . '(SELECT MAX(lp.completed_at) FROM lesson_progress lp WHERE lp.student_id = e.student_id AND lp.course_id = c.id) AS last_activity '
            . 'FROM enrollments e INNER JOIN courses c ON c.id = e.course_id INNER JOIN users u ON u.id = c.instructor_id '
            . 'WHERE e.student_id = :student_id AND e.status = \'active\' ORDER BY COALESCE(last_activity, e.granted_at) DESC'
        );
        $statement->execute(['student_id' => $student['id']]);
        $courses = $statement->fetchAll();
        foreach ($courses as &$course) {
            $total = (int) $course['total_lessons'];
            $completedCount = (int) $course['completed_lessons'];
            $course['progress_percent'] = $total > 0 ? (int) floor(($completedCount / $total) * 100) : 0;
            $course['thumbnail_url'] = trim((string) ($course['thumbnail'] ?? '')) !== ''
                ? '/media/course-thumbnails/' . rawurlencode(basename((string) $course['thumbnail']))
                : '';
            unset($course['thumbnail']);
        }
        unset($course);
        $respond(['data' => $courses]);
    }

    $respond(['error' => 'Learning route not found.'], 404);
} catch (ServiceAuthenticationException $exception) {
    $respond(['error' => $exception->getMessage()], 401);
} catch (ServiceAuthorizationException $exception) {
    $respond(['error' => $exception->getMessage()], 403);
} catch (InvalidArgumentException $exception) {
    $respond(['error' => $exception->getMessage()], 422);
} catch (JsonException) {
    $respond(['error' => 'Malformed JSON request.'], 400);
} catch (PDOException $exception) {
    error_log('Learning database failure: ' . $exception->getMessage());
    $respond(['error' => 'Learning request could not be completed. Apply the learning progress migration if required.'], 409);
} catch (Throwable $exception) {
    error_log('Learning service failure: ' . $exception->getMessage());
    $respond(['error' => 'Learning service is unavailable.'], 503);
}
