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

$database = null;
try {
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    if (preg_match('#^/api/v1/courses/(\d+)/duration$#', $path, $matches) !== 1
        || strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST'
    ) {
        $respond(['error' => 'Course duration route not found.'], 404);
    }

    $input = json_decode((string) file_get_contents('php://input') ?: '{}', true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($input)) {
        throw new InvalidArgumentException('Request body must be a JSON object.');
    }
    $duration = trim((string) ($input['duration'] ?? ''));
    if ($duration !== '' && (mb_strlen($duration) > 80 || preg_match('/^[0-9]+(?:\.[0-9]{1,2})? hours$/', $duration) !== 1)) {
        throw new InvalidArgumentException('Course duration must be empty or a validated number of hours.');
    }

    $database = Database::connect();
    $instructor = ServiceAuth::requireUser($database, (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''), 'instructor');
    $courseId = (int) $matches[1];
    $database->beginTransaction();
    $course = $database->prepare('SELECT id,status FROM courses WHERE id=:id AND instructor_id=:instructor_id FOR UPDATE');
    $course->execute(['id' => $courseId, 'instructor_id' => $instructor['id']]);
    $record = $course->fetch();
    if (!is_array($record)) {
        throw new ServiceAuthorizationException('That course is not available in your Instructor studio.');
    }

    if ((string) $record['status'] === 'published') {
        $revision = $database->prepare(
            'SELECT id,revision_snapshot FROM course_revisions '
            . 'WHERE course_id=:course_id AND instructor_id=:instructor_id AND revision_status IN (\'draft\',\'pending\') '
            . 'ORDER BY id DESC LIMIT 1 FOR UPDATE'
        );
        $revision->execute(['course_id' => $courseId, 'instructor_id' => $instructor['id']]);
        $revisionRecord = $revision->fetch();
        if (!is_array($revisionRecord)) {
            throw new ServiceAuthorizationException('No editable course revision is available.');
        }
        $snapshot = json_decode((string) $revisionRecord['revision_snapshot'], true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($snapshot) || !is_array($snapshot['course'] ?? null)) {
            throw new RuntimeException('The stored course revision is unreadable.');
        }
        $snapshot['course']['duration'] = $duration;
        $update = $database->prepare('UPDATE course_revisions SET revision_snapshot=:snapshot WHERE id=:id');
        $update->execute([
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'id' => (int) $revisionRecord['id'],
        ]);
    } else {
        $update = $database->prepare('UPDATE courses SET duration=:duration WHERE id=:id AND instructor_id=:instructor_id');
        $update->execute([
            'duration' => $duration !== '' ? $duration : null,
            'id' => $courseId,
            'instructor_id' => $instructor['id'],
        ]);
    }

    $database->commit();
    $respond(['message' => 'Course duration synchronized.', 'duration' => $duration]);
} catch (ServiceAuthenticationException $exception) {
    if ($database instanceof PDO && $database->inTransaction()) $database->rollBack();
    $respond(['error' => $exception->getMessage()], 401);
} catch (ServiceAuthorizationException $exception) {
    if ($database instanceof PDO && $database->inTransaction()) $database->rollBack();
    $respond(['error' => $exception->getMessage()], 403);
} catch (InvalidArgumentException $exception) {
    if ($database instanceof PDO && $database->inTransaction()) $database->rollBack();
    $respond(['error' => $exception->getMessage()], 422);
} catch (JsonException $exception) {
    if ($database instanceof PDO && $database->inTransaction()) $database->rollBack();
    $respond(['error' => 'The course duration request is malformed.'], 400);
} catch (Throwable $exception) {
    if ($database instanceof PDO && $database->inTransaction()) $database->rollBack();
    error_log('Course duration synchronization failure: ' . $exception->getMessage());
    $respond(['error' => 'The course duration could not be synchronized.'], 503);
}
