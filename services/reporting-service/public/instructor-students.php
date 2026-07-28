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

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$respond = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
};

try {
    if ($method !== 'GET') {
        $respond(['error' => 'Method not allowed.'], 405);
    }

    $database = Database::connect();
    $instructor = ServiceAuth::requireUser($database, $authorization, 'instructor');

    $statement = $database->prepare(
        "SELECT e.id AS enrollment_id,e.student_id,e.course_id,e.access_type,e.status AS enrollment_status,e.granted_at,"
        . "s.full_name AS student_name,s.email AS student_email,s.status AS student_status,s.profile_image AS student_profile_image,"
        . "c.title AS course_title,c.status AS course_status,"
        . "(SELECT COUNT(*) FROM course_lessons lesson INNER JOIN course_sections section_record ON section_record.id=lesson.section_id WHERE section_record.course_id=c.id) AS total_lessons,"
        . "(SELECT COUNT(*) FROM lesson_progress progress WHERE progress.student_id=e.student_id AND progress.course_id=e.course_id) AS completed_lessons,"
        . "(SELECT MAX(progress_activity.completed_at) FROM lesson_progress progress_activity WHERE progress_activity.student_id=e.student_id AND progress_activity.course_id=e.course_id) AS last_activity_at "
        . "FROM enrollments e "
        . "INNER JOIN courses c ON c.id=e.course_id AND c.instructor_id=:instructor_id "
        . "INNER JOIN users s ON s.id=e.student_id AND s.role='student' "
        . "WHERE e.status='active' "
        . "ORDER BY e.granted_at DESC,e.id DESC LIMIT 1000"
    );
    $statement->execute(['instructor_id' => (int) $instructor['id']]);
    $rows = $statement->fetchAll();

    $studentIds = [];
    $learningStudentIds = [];
    $completedEnrollments = 0;
    $newThisMonth = 0;
    $monthStart = date('Y-m-01 00:00:00');

    foreach ($rows as &$row) {
        $studentId = (int) ($row['student_id'] ?? 0);
        $totalLessons = (int) ($row['total_lessons'] ?? 0);
        $completedLessons = min($totalLessons, (int) ($row['completed_lessons'] ?? 0));
        $progress = $totalLessons > 0 ? (int) round(($completedLessons / $totalLessons) * 100) : 0;

        $row['student_id'] = $studentId;
        $row['course_id'] = (int) ($row['course_id'] ?? 0);
        $row['enrollment_id'] = (int) ($row['enrollment_id'] ?? 0);
        $row['total_lessons'] = $totalLessons;
        $row['completed_lessons'] = $completedLessons;
        $row['progress_percent'] = max(0, min(100, $progress));

        if ($studentId > 0) {
            $studentIds[$studentId] = true;
            if ($completedLessons > 0) {
                $learningStudentIds[$studentId] = true;
            }
        }
        if ($totalLessons > 0 && $completedLessons >= $totalLessons) {
            $completedEnrollments++;
        }
        if ((string) ($row['granted_at'] ?? '') >= $monthStart) {
            $newThisMonth++;
        }
    }
    unset($row);

    $respond([
        'data' => [
            'summary' => [
                'students' => count($studentIds),
                'active_enrollments' => count($rows),
                'learning_started' => count($learningStudentIds),
                'completed_courses' => $completedEnrollments,
                'new_this_month' => $newThisMonth,
            ],
            'students' => $rows,
        ],
    ]);
} catch (ServiceAuthenticationException $exception) {
    $respond(['error' => $exception->getMessage()], 401);
} catch (ServiceAuthorizationException $exception) {
    $respond(['error' => $exception->getMessage()], 403);
} catch (PDOException $exception) {
    error_log('Instructor student roster database failure: ' . $exception->getMessage());
    $respond(['error' => 'The Instructor student roster could not be loaded.'], 409);
} catch (Throwable $exception) {
    error_log('Instructor student roster failure: ' . $exception->getMessage());
    $respond(['error' => 'The Instructor student roster is unavailable.'], 503);
}
