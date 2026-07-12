<?php

declare(strict_types=1);

if (!function_exists('notification_clean_text')) {
    function notification_clean_text(mixed $value, int $maximum): string
    {
        if (!is_scalar($value) && $value !== null) {
            return '';
        }

        $text = strip_tags((string) $value);
        $text = str_replace(["\0", "\r"], ['', ''], $text);
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);

        return $length <= $maximum ? $text : '';
    }
}

if (!function_exists('notification_type')) {
    function notification_type(mixed $type): string
    {
        $type = strtolower(trim((string) $type));
        $allowed = [
            'general', 'warning', 'payment', 'course', 'course_change',
            'account', 'payout', 'withdrawal', 'system',
        ];

        return in_array($type, $allowed, true) ? $type : 'general';
    }
}

if (!function_exists('send_notification')) {
    function send_notification(
        mysqli $conn,
        int $userId,
        string $title,
        string $message,
        string $type = 'general'
    ): bool {
        $title = notification_clean_text($title, 180);
        $message = notification_clean_text($message, 1000);
        $type = notification_type($type);

        if ($userId <= 0 || $title === '' || $message === '') {
            return false;
        }

        $sql = "
            INSERT INTO notifications (
                user_id, title, message, notification_type, is_read, created_at
            )
            SELECT id, ?, ?, ?, 0, NOW()
            FROM users
            WHERE id = ?
              AND status = 'active'
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('sssi', $title, $message, $type, $userId);
        $ok = $stmt->execute() && $stmt->affected_rows === 1;
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('send_notification_to_role')) {
    function send_notification_to_role(
        mysqli $conn,
        string $role,
        string $title,
        string $message,
        string $type = 'general'
    ): int {
        $allowedRoles = ['student', 'instructor', 'admin'];
        $title = notification_clean_text($title, 180);
        $message = notification_clean_text($message, 1000);
        $type = notification_type($type);

        if (!in_array($role, $allowedRoles, true) || $title === '' || $message === '') {
            return 0;
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE role = ? AND status = 'active'");
        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param('s', $role);
        $stmt->execute();
        $result = $stmt->get_result();
        $sentCount = 0;

        while ($user = $result->fetch_assoc()) {
            if (send_notification($conn, (int) $user['id'], $title, $message, $type)) {
                $sentCount++;
            }
        }

        $stmt->close();
        return $sentCount;
    }
}

if (!function_exists('send_notification_to_all_users')) {
    function send_notification_to_all_users(
        mysqli $conn,
        string $title,
        string $message,
        string $type = 'general'
    ): int {
        $title = notification_clean_text($title, 180);
        $message = notification_clean_text($message, 1000);
        $type = notification_type($type);

        if ($title === '' || $message === '') {
            return 0;
        }

        $result = $conn->query("SELECT id FROM users WHERE status = 'active'");
        $sentCount = 0;

        if ($result) {
            while ($user = $result->fetch_assoc()) {
                if (send_notification($conn, (int) $user['id'], $title, $message, $type)) {
                    $sentCount++;
                }
            }
        }

        return $sentCount;
    }
}

if (!function_exists('get_unread_notification_count')) {
    function get_unread_notification_count(mysqli $conn, int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM notifications
            WHERE user_id = ? AND is_read = 0
        ");

        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return (int) ($row['total'] ?? 0);
    }
}
