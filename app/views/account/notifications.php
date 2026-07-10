<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Auth.php';

Auth::requireLogin();

$user = Auth::user();
$userId = (int) ($user['id'] ?? 0);
$userRole = $user['role'] ?? '';

function my_notification_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    $sql = "
        UPDATE notifications
        SET is_read = 1,
            read_at = NOW()
        WHERE user_id = ?
          AND is_read = 0
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: my-notifications.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_one_read'])) {
    $notificationId = (int) ($_POST['notification_id'] ?? 0);

    if ($notificationId > 0) {
        $sql = "
            UPDATE notifications
            SET is_read = 1,
                read_at = NOW()
            WHERE id = ?
              AND user_id = ?
        ";

        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("ii", $notificationId, $userId);
            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: my-notifications.php");
    exit;
}

$notifications = [];

$sql = "
    SELECT
        id,
        title,
        message,
        notification_type,
        is_read,
        created_at,
        read_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 100
";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
    }

    $stmt->close();
}

$unreadCount = 0;

foreach ($notifications as $notification) {
    if ((int) $notification['is_read'] === 0) {
        $unreadCount++;
    }
}

require_once __DIR__ . '/../layouts/header.php';

if ($userRole === 'admin') {
    require_once __DIR__ . '/../layouts/admin_navbar.php';
} elseif ($userRole === 'instructor') {
    require_once __DIR__ . '/../layouts/instructor_navbar.php';
} else {
    require_once __DIR__ . '/../layouts/student_navbar.php';
}

?>



<main class="notifications-page">
    <section class="notifications-wrapper">

        <div class="notifications-header">
            <div>
                <p class="page-label">My Account</p>
                <h1>My Notifications</h1>
                <p>You have <?php echo (int) $unreadCount; ?> unread notification(s).</p>
            </div>

            <?php if ($unreadCount > 0): ?>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <button type="submit" name="mark_all_read" class="mark-all-btn">
                        Mark all as read
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <section class="notification-card">
            <?php if (empty($notifications)): ?>

                <div class="empty-notifications">
                    No notifications yet.
                </div>

            <?php else: ?>

                <div class="notification-list">
                    <?php foreach ($notifications as $notification): ?>
                        <article class="notification-item <?php echo ((int) $notification['is_read'] === 0) ? 'unread' : ''; ?>">
                            <div class="notification-top">
                                <strong>
                                    <?php echo my_notification_h($notification['title']); ?>
                                </strong>

                                <span class="notification-type">
                                    <?php echo my_notification_h($notification['notification_type']); ?>
                                </span>
                            </div>

                            <p>
                                <?php echo my_notification_h($notification['message']); ?>
                            </p>

                            <div class="notification-meta">
                                <span>
                                    <?php echo my_notification_h(date('M d, Y h:i A', strtotime($notification['created_at']))); ?>
                                </span>

                                <?php if ((int) $notification['is_read'] === 0): ?>
                                    <form method="POST">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="notification_id" value="<?php echo (int) $notification['id']; ?>">
                                        <button type="submit" name="mark_one_read" class="mark-one-btn">
                                            Mark as read
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span>Read</span>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>
        </section>

    </section>
</main>

</body>
</html>