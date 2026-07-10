<?php

require_once __DIR__ . '/../../middleware/StudentMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

StudentMiddleware::handle();

$user = Auth::user();

if (!$user) {
    Auth::redirect('login.php');
}

$studentId = (int) $user['id'];
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $notificationId = (int) ($_POST['notification_id'] ?? 0);

    if ($notificationId > 0) {
        $updateSql = "
            UPDATE notifications 
            SET is_read = 1 
            WHERE id = ? AND user_id = ?
        ";

        $updateStmt = $conn->prepare($updateSql);

        if ($updateStmt) {
            $updateStmt->bind_param("ii", $notificationId, $studentId);

            if ($updateStmt->execute()) {
                $message = 'Notification marked as read.';
                $messageType = 'success';
            } else {
                $message = 'Failed to update notification.';
                $messageType = 'error';
            }

            $updateStmt->close();
        }
    }
}

$sql = "
    SELECT 
        id,
        title,
        message,
        is_read,
        created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
";

$stmt = $conn->prepare($sql);
$notifications = null;

if ($stmt) {
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $notifications = $stmt->get_result();
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/student_navbar.php';
?>


<main class="student-page">
    <section class="student-section">
        <div class="container">

            <div class="dashboard-header">
                <div>
                    <p class="dashboard-subtitle">Student Panel</p>
                    <h1>Notifications</h1>
                    <p>Check your course, payment, and account updates here.</p>
                </div>
            </div>

            <?php if ($message !== ''): ?>
                <div class="form-message <?php echo htmlspecialchars($messageType); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="dashboard-panel">
                <?php if ($notifications && $notifications->num_rows > 0): ?>

                    <div class="notification-list">
                        <?php while ($notification = $notifications->fetch_assoc()): ?>
                            <div class="notification-card <?php echo ((int) $notification['is_read'] === 0) ? 'unread' : 'read'; ?>">
                                <div class="notification-content">
                                    <div class="notification-title-row">
                                        <h3><?php echo htmlspecialchars($notification['title']); ?></h3>

                                        <?php if ((int) $notification['is_read'] === 0): ?>
                                            <span class="notification-badge unread-badge">Unread</span>
                                        <?php else: ?>
                                            <span class="notification-badge read-badge">Read</span>
                                        <?php endif; ?>
                                    </div>

                                    <p><?php echo htmlspecialchars($notification['message']); ?></p>

                                    <small>
                                        <?php echo htmlspecialchars($notification['created_at']); ?>
                                    </small>
                                </div>

                                <?php if ((int) $notification['is_read'] === 0): ?>
                                    <form method="POST" class="notification-action">
                                          <?php echo csrf_field(); ?>
                                        <input 
                                            type="hidden" 
                                            name="notification_id" 
                                            value="<?php echo (int) $notification['id']; ?>"
                                        >

                                        <button type="submit" name="mark_read" class="btn btn-outline">
                                            Mark as Read
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>

                <?php else: ?>

                    <div class="empty-state">
                        <h3>No notifications yet</h3>
                        <p>You do not have any notifications right now.</p>
                    </div>

                <?php endif; ?>
            </div>

        </div>
    </section>
</main>

</body>
</html>

<?php

if ($stmt) {
    $stmt->close();
}
?>