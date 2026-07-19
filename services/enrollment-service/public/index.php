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

try {
    $database = Database::connect();

    if ($path === '/health' && $method === 'GET') {
        $database->query('SELECT 1');
        $respond(['status' => 'ok', 'service' => 'enrollment-service']);
    }

    if ($path === '/api/v1/enrollments/mine' && $method === 'GET') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $statement = $database->prepare(
            'SELECT e.id, e.course_id, e.access_type, e.status, e.granted_at, c.title, c.short_description, c.thumbnail, '
            . 'c.level, c.language, c.duration, u.full_name AS instructor_name, '
            . '(SELECT COUNT(*) FROM course_lessons cl INNER JOIN course_sections cs ON cs.id = cl.section_id WHERE cs.course_id = c.id) AS lesson_count '
            . 'FROM enrollments e INNER JOIN courses c ON c.id = e.course_id INNER JOIN users u ON u.id = c.instructor_id '
            . 'WHERE e.student_id = :student_id ORDER BY e.granted_at DESC'
        );
        $statement->execute(['student_id' => $student['id']]);
        $rows = $statement->fetchAll();
        foreach ($rows as &$row) {
            $row['thumbnail_url'] = trim((string) ($row['thumbnail'] ?? '')) !== ''
                ? '/media/course-thumbnails/' . rawurlencode(basename((string) $row['thumbnail']))
                : '';
            unset($row['thumbnail']);
        }
        unset($row);
        $respond(['data' => $rows]);
    }

    if (preg_match('#^/api/v1/enrollments/(\d+)/access$#', $path, $matches) === 1 && $method === 'GET') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $statement = $database->prepare(
            'SELECT e.id, e.status, e.access_type, e.granted_at FROM enrollments e '
            . 'WHERE e.student_id = :student_id AND e.course_id = :course_id AND e.status = \'active\' LIMIT 1'
        );
        $statement->execute(['student_id' => $student['id'], 'course_id' => (int) $matches[1]]);
        $enrollment = $statement->fetch();
        $respond(['data' => ['allowed' => is_array($enrollment), 'enrollment' => is_array($enrollment) ? $enrollment : null]]);
    }

    if ($path === '/api/v1/enrollments' && $method === 'GET') {
        ServiceAuth::requireUser($database, $authorization, 'admin');
        $statement = $database->query(
            'SELECT e.id, e.access_type, e.status, e.granted_at, e.revoked_at, s.full_name AS student_name, s.email AS student_email, '
            . 'c.title AS course_title, i.full_name AS instructor_name, e.order_id, e.payment_id '
            . 'FROM enrollments e INNER JOIN users s ON s.id = e.student_id INNER JOIN courses c ON c.id = e.course_id '
            . 'INNER JOIN users i ON i.id = c.instructor_id ORDER BY e.created_at DESC LIMIT 500'
        );
        $respond(['data' => $statement->fetchAll()]);
    }

    $respond(['error' => 'Enrollment route not found.'], 404);
} catch (ServiceAuthenticationException $exception) {
    $respond(['error' => $exception->getMessage()], 401);
} catch (ServiceAuthorizationException $exception) {
    $respond(['error' => $exception->getMessage()], 403);
} catch (PDOException $exception) {
    error_log('Enrollment database failure: ' . $exception->getMessage());
    $respond(['error' => 'Enrollment request could not be completed.'], 409);
} catch (Throwable $exception) {
    error_log('Enrollment service failure: ' . $exception->getMessage());
    $respond(['error' => 'Enrollment service is unavailable.'], 503);
}
