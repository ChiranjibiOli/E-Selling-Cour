<?php

declare(strict_types=1);

require_once __DIR__ . '/notification_helper.php';

if (!function_exists('course_workflow_record_change')) {
    function course_workflow_record_change(
        mysqli $conn,
        int $courseId,
        int $instructorId,
        string $courseTitle,
        string $changeType,
        array $before,
        array $after,
        string $previousStatus,
        string $newStatus
    ): int {
        $beforeJson = json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $afterJson = json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($beforeJson === false || $afterJson === false) {
            throw new RuntimeException('Course change snapshot could not be encoded.');
        }

        $stmt = $conn->prepare("
            INSERT INTO course_change_logs (
                course_id, instructor_id, change_type, before_snapshot,
                after_snapshot, previous_status, new_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            'iisssss',
            $courseId,
            $instructorId,
            $changeType,
            $beforeJson,
            $afterJson,
            $previousStatus,
            $newStatus
        );
        $stmt->execute();
        $logId = (int) $conn->insert_id;
        $stmt->close();

        send_notification_to_role(
            $conn,
            'admin',
            'Course content changed',
            'Course #' . $courseId . ' "' . $courseTitle
                . '" has lesson changes awaiting review. Change log #' . $logId . '.',
            'course_change'
        );

        return $logId;
    }
}

if (!function_exists('course_workflow_notify_instructor')) {
    function course_workflow_notify_instructor(
        mysqli $conn,
        int $instructorId,
        int $courseId,
        string $courseTitle,
        string $status,
        string $note = ''
    ): bool {
        $title = $status === 'published' ? 'Course approved' : 'Course needs revision';
        $message = $status === 'published'
            ? 'Course #' . $courseId . ' "' . $courseTitle . '" is now published.'
            : 'Course #' . $courseId . ' "' . $courseTitle . '" was rejected.';

        if ($note !== '') {
            $message .= ' Admin note: ' . $note;
        }

        return send_notification($conn, $instructorId, $title, $message, 'course');
    }
}
