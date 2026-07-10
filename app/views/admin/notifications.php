<?php

require_once __DIR__ . '/../../middleware/AdminMiddleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/notification_helper.php';

AdminMiddleware::handle();

function admin_notification_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$messageType = '';

$users = [];

$userSql = "
    SELECT id, full_name, email, role, status
    FROM users
    WHERE status = 'active'
    ORDER BY role ASC, full_name ASC
";

$userResult = $conn->query($userSql);

if ($userResult) {
    while ($row = $userResult->fetch_assoc()) {
        $users[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $targetType = trim($_POST['target_type'] ?? '');
    $targetUserId = (int) ($_POST['target_user_id'] ?? 0);
    $targetRole = trim($_POST['target_role'] ?? '');
    $notificationType = trim($_POST['notification_type'] ?? 'general');
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['message'] ?? '');

    $allowedTypes = [
        'general',
        'warning',
        'payment',
        'course',
        'account',
        'payout',
        'system'
    ];

    if (!in_array($notificationType, $allowedTypes, true)) {
        $notificationType = 'general';
    }

    if ($title === '' || $body === '') {
        $message = 'Title and message are required.';
        $messageType = 'error';
    } else {
        $sentCount = 0;

        if ($targetType === 'single') {
            if ($targetUserId <= 0) {
                $message = 'Please select a user.';
                $messageType = 'error';
            } else {
                $sentCount = send_notification(
                    $conn,
                    $targetUserId,
                    $title,
                    $body,
                    $notificationType
                );
                $sentCount = $sentCount ? 1 : 0;
            }
        } elseif ($targetType === 'role') {
            if (!in_array($targetRole, ['student', 'instructor', 'admin'], true)) {
                $message = 'Please select a valid role.';
                $messageType = 'error';
            } else {
                $sentCount = send_notification_to_role(
                    $conn,
                    $targetRole,
                    $title,
                    $body,
                    $notificationType
                );
            }
        } elseif ($targetType === 'all') {
            $sentCount = send_notification_to_all_users(
                $conn,
                $title,
                $body,
                $notificationType
            );
        } else {
            $message = 'Please select a valid target.';
            $messageType = 'error';
        }

        if ($message === '') {
            if ($sentCount > 0) {
                $message = "Notification sent successfully to {$sentCount} user(s).";
                $messageType = 'success';
            } else {
                $message = 'No notification was sent.';
                $messageType = 'error';
            }
        }
    }
}

$notifications = [];

$historySql = "
    SELECT
        n.id,
        n.title,
        n.message,
        n.notification_type,
        n.is_read,
        n.created_at,
        u.full_name,
        u.email,
        u.role
    FROM notifications n
    INNER JOIN users u ON n.user_id = u.id
    ORDER BY n.created_at DESC
    LIMIT 100
";

$historyResult = $conn->query($historySql);

if ($historyResult) {
    while ($row = $historyResult->fetch_assoc()) {
        $notifications[] = $row;
    }
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/admin_navbar.php';

?>



<main class="notifications-page">
    <section class="notifications-wrapper">

        <div class="notifications-header">
            <div>
                <p class="page-label">Admin Panel</p>
                <h1>Notifications</h1>
                <p>Send messages, warnings, and system updates to students and instructors.</p>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="notify-alert <?php echo admin_notification_h($messageType); ?>">
                <?php echo admin_notification_h($message); ?>
            </div>
        <?php endif; ?>

        <div class="notification-layout">

            <section class="notification-card">
                <h2>Send Notification</h2>

                <form method="POST" class="notification-form">
                    <?php echo csrf_field(); ?>

                    <div class="form-group">
                        <label>Send To</label>
                        <select name="target_type" id="targetType" required>
                            <option value="single">Specific User</option>
                            <option value="role">Role</option>
                            <option value="all">All Active Users</option>
                        </select>
                    </div>

                    <div class="form-group target-single">
                        <label>Select User</label>
                        <select name="target_user_id">
                            <option value="">Choose user</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo (int) $user['id']; ?>">
                                    <?php echo admin_notification_h($user['full_name']); ?>
                                    —
                                    <?php echo admin_notification_h($user['role']); ?>
                                    —
                                    <?php echo admin_notification_h($user['email']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group target-role hidden">
                        <label>Select Role</label>
                        <select name="target_role">
                            <option value="">Choose role</option>
                            <option value="student">Students</option>
                            <option value="instructor">Instructors</option>
                            <option value="admin">Admins</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Notification Type</label>
                        <select name="notification_type">
                            <option value="general">General</option>
                            <option value="warning">Warning</option>
                            <option value="payment">Payment</option>
                            <option value="course">Course</option>
                            <option value="account">Account</option>
                            <option value="payout">Payout</option>
                            <option value="system">System</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Title</label>
                        <input
                            type="text"
                            name="title"
                            placeholder="Example: Payment approved"
                            maxlength="180"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Message</label>
                        <textarea
                            name="message"
                            rows="6"
                            placeholder="Write your message to the selected user(s)."
                            required
                        ></textarea>
                    </div>

                    <button type="submit" name="send_notification" class="send-notification-btn">
                        Send Notification
                    </button>
                </form>
            </section>

            <section class="notification-card">
                <h2>Recent Notifications</h2>

                <?php if (empty($notifications)): ?>
                    <div class="empty-notifications">
                        No notifications sent yet.
                    </div>
                <?php else: ?>
                    <div class="notification-list">
                        <?php foreach ($notifications as $notification): ?>
                            <article class="notification-item">
                                <div class="notification-top">
                                    <strong>
                                        <?php echo admin_notification_h($notification['title']); ?>
                                    </strong>

                                    <span class="notification-type">
                                        <?php echo admin_notification_h($notification['notification_type']); ?>
                                    </span>
                                </div>

                                <p>
                                    <?php echo admin_notification_h($notification['message']); ?>
                                </p>

                                <div class="notification-meta">
                                    <span>
                                        To:
                                        <?php echo admin_notification_h($notification['full_name']); ?>
                                        —
                                        <?php echo admin_notification_h($notification['role']); ?>
                                    </span>

                                    <span>
                                        <?php echo admin_notification_h(date('M d, Y h:i A', strtotime($notification['created_at']))); ?>
                                    </span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

        </div>

    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const targetType = document.getElementById('targetType');
    const singleBox = document.querySelector('.target-single');
    const roleBox = document.querySelector('.target-role');

    function updateTargetBoxes() {
        const value = targetType.value;

        singleBox.classList.toggle('hidden', value !== 'single');
        roleBox.classList.toggle('hidden', value !== 'role');
    }

    targetType.addEventListener('change', updateTargetBoxes);
    updateTargetBoxes();
});
</script>

</body>
</html>