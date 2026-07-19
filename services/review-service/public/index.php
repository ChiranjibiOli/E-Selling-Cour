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
    if ($raw === '') { return []; }
    $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) { throw new InvalidArgumentException('Request body must be a JSON object.'); }
    return $decoded;
};

try {
    $database = Database::connect();

    if ($path === '/health' && $method === 'GET') {
        $database->query('SELECT 1');
        $respond(['status' => 'ok', 'service' => 'review-service']);
    }

    if (preg_match('#^/api/v1/reviews/course/(\d+)$#', $path, $matches) === 1 && $method === 'GET') {
        $statement = $database->prepare(
            'SELECT r.id, r.rating, r.review_text, r.created_at, r.updated_at, u.full_name AS student_name '
            . 'FROM reviews r INNER JOIN users u ON u.id = r.student_id '
            . 'WHERE r.course_id = :course_id AND r.status = \'visible\' ORDER BY r.updated_at DESC LIMIT 200'
        );
        $statement->execute(['course_id' => (int) $matches[1]]);
        $reviews = $statement->fetchAll();
        $average = $reviews === [] ? null : round(array_sum(array_map(static fn (array $review): int => (int) $review['rating'], $reviews)) / count($reviews), 2);
        $respond(['data' => $reviews, 'meta' => ['count' => count($reviews), 'average_rating' => $average]]);
    }

    if ($path === '/api/v1/reviews/mine' && $method === 'GET') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $statement = $database->prepare(
            'SELECT r.id, r.course_id, r.rating, r.review_text, r.status, r.created_at, r.updated_at, c.title AS course_title '
            . 'FROM reviews r INNER JOIN courses c ON c.id = r.course_id WHERE r.student_id = :student_id ORDER BY r.updated_at DESC'
        );
        $statement->execute(['student_id' => $student['id']]);
        $respond(['data' => $statement->fetchAll()]);
    }

    if ($path === '/api/v1/reviews/eligible' && $method === 'GET') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $statement = $database->prepare(
            'SELECT c.id AS course_id, c.title, u.full_name AS instructor_name FROM enrollments e '
            . 'INNER JOIN courses c ON c.id = e.course_id INNER JOIN users u ON u.id = c.instructor_id '
            . 'LEFT JOIN reviews r ON r.course_id = c.id AND r.student_id = e.student_id '
            . 'WHERE e.student_id = :student_id AND e.status = \'active\' AND r.id IS NULL ORDER BY e.granted_at DESC'
        );
        $statement->execute(['student_id' => $student['id']]);
        $respond(['data' => $statement->fetchAll()]);
    }

    if ($path === '/api/v1/reviews' && $method === 'POST') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $input = $jsonInput();
        $courseId = (int) ($input['course_id'] ?? 0);
        $rating = (int) ($input['rating'] ?? 0);
        $text = trim((string) ($input['review_text'] ?? ''));
        if ($courseId < 1 || $rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('Choose a purchased course and a rating from 1 to 5.');
        }
        if (mb_strlen($text) > 5000) {
            throw new InvalidArgumentException('Review text must be 5000 characters or fewer.');
        }
        $enrollment = $database->prepare('SELECT id FROM enrollments WHERE student_id = :student_id AND course_id = :course_id AND status = \'active\' LIMIT 1');
        $enrollment->execute(['student_id' => $student['id'], 'course_id' => $courseId]);
        if ($enrollment->fetch() === false) {
            throw new ServiceAuthorizationException('Only a student with active lifetime access can review this course.');
        }
        $statement = $database->prepare(
            'INSERT INTO reviews (course_id, student_id, rating, review_text, status) VALUES (:course_id, :student_id, :rating, :review_text, \'visible\') '
            . 'ON DUPLICATE KEY UPDATE rating = VALUES(rating), review_text = VALUES(review_text), status = \'visible\', updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute(['course_id' => $courseId, 'student_id' => $student['id'], 'rating' => $rating, 'review_text' => $text !== '' ? $text : null]);
        $respond(['message' => 'Your verified course review has been saved.'], 201);
    }

    if (preg_match('#^/api/v1/reviews/(\d+)$#', $path, $matches) === 1 && $method === 'DELETE') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $statement = $database->prepare('DELETE FROM reviews WHERE id = :id AND student_id = :student_id');
        $statement->execute(['id' => (int) $matches[1], 'student_id' => $student['id']]);
        $respond(['message' => 'Review removed.']);
    }

    if (preg_match('#^/api/v1/reviews/(\d+)/moderate$#', $path, $matches) === 1 && $method === 'POST') {
        ServiceAuth::requireUser($database, $authorization, 'admin');
        $input = $jsonInput();
        $status = strtolower(trim((string) ($input['status'] ?? 'hidden')));
        if (!in_array($status, ['visible', 'hidden'], true)) {
            throw new InvalidArgumentException('Choose visible or hidden review status.');
        }
        $statement = $database->prepare('UPDATE reviews SET status = :status WHERE id = :id');
        $statement->execute(['status' => $status, 'id' => (int) $matches[1]]);
        $respond(['message' => 'Review moderation status updated.']);
    }

    $respond(['error' => 'Review route not found.'], 404);
} catch (ServiceAuthenticationException $exception) {
    $respond(['error' => $exception->getMessage()], 401);
} catch (ServiceAuthorizationException $exception) {
    $respond(['error' => $exception->getMessage()], 403);
} catch (InvalidArgumentException $exception) {
    $respond(['error' => $exception->getMessage()], 422);
} catch (JsonException) {
    $respond(['error' => 'Malformed JSON request.'], 400);
} catch (PDOException $exception) {
    error_log('Review database failure: ' . $exception->getMessage());
    $respond(['error' => 'Review request could not be completed.'], 409);
} catch (Throwable $exception) {
    error_log('Review service failure: ' . $exception->getMessage());
    $respond(['error' => 'Review service is unavailable.'], 503);
}
