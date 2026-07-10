<?php

if (!function_exists('send_notification')) {
    function send_notification(
        mysqli $conn,
        int $userId,
        string $title,
        string $message,
        string $type = 'general'
    ): bool {
        if ($userId <= 0 || trim($title) === '' || trim($message) === '') {
            return false;
        }

        $sql = "
            INSERT INTO notifications (
                user_id,
                title,
                message,
                notification_type,
                is_read,
                created_at
            ) VALUES (?, ?, ?, ?, 0, NOW())
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("isss", $userId, $title, $message, $type);
        $ok = $stmt->execute();
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

        if (!in_array($role, $allowedRoles, true)) {
            return 0;
        }

        $sql = "
            SELECT id
            FROM users
            WHERE role = ?
              AND status = 'active'
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param("s", $role);
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
        $sql = "
            SELECT id
            FROM users
            WHERE status = 'active'
        ";

        $result = $conn->query($sql);
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

        $sql = "
            SELECT COUNT(*) AS total
            FROM notifications
            WHERE user_id = ?
              AND is_read = 0
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;

        $stmt->close();

        return (int) ($row['total'] ?? 0);
    }
}