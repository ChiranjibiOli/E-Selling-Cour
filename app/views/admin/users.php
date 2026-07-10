<?php

require_once __DIR__ . '/../../middleware/AdminMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

AdminMiddleware::handle();

$message = '';
$messageType = '';

if (isset($_GET['blocked'])) {
    $message = 'User blocked successfully.';
    $messageType = 'success';
}

if (isset($_GET['activated'])) {
    $message = 'User activated successfully.';
    $messageType = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['block_user'])) {
    $userId = (int) ($_POST['user_id'] ?? 0);

    if ($userId > 0) {
        $sql = "
            UPDATE users 
            SET status = 'blocked' 
            WHERE id = ? 
            AND role IN ('student', 'instructor')
        ";

        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();

            header("Location: admin-users.php?blocked=1");
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_user'])) {
    $userId = (int) ($_POST['user_id'] ?? 0);

    if ($userId > 0) {
        $sql = "
            UPDATE users 
            SET status = 'active' 
            WHERE id = ? 
            AND role IN ('student', 'instructor')
        ";

        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();

            header("Location: admin-users.php?activated=1");
            exit;
        }
    }
}

$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$allowedRoles = ['student', 'instructor'];

if (!in_array($roleFilter, $allowedRoles, true)) {
    $roleFilter = '';
}

$whereParts = [];
$params = [];
$types = '';

$whereParts[] = "role IN ('student', 'instructor')";

if ($search !== '') {
    $whereParts[] = "(full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= 'sss';
}

if ($roleFilter !== '') {
    $whereParts[] = "role = ?";
    $params[] = $roleFilter;
    $types .= 's';
}

if ($statusFilter !== '') {
    $whereParts[] = "status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

$whereSql = 'WHERE ' . implode(' AND ', $whereParts);

$users = [];

$sql = "
    SELECT 
        id,
        full_name,
        email,
        phone,
        profile_image,
        role,
        status,
        created_at
    FROM users
    $whereSql
    ORDER BY 
        CASE 
            WHEN role = 'instructor' THEN 1
            WHEN role = 'student' THEN 2
            ELSE 3
        END,
        created_at DESC
";

$stmt = $conn->prepare($sql);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }

    $stmt->close();
}

function get_count(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);

    if ($result) {
        $row = $result->fetch_assoc();
        return (int) ($row['total'] ?? 0);
    }

    return 0;
}

$totalUsersCount = get_count(
    $conn,
    "SELECT COUNT(*) AS total FROM users WHERE role IN ('student', 'instructor')"
);

$totalStudentCount = get_count(
    $conn,
    "SELECT COUNT(*) AS total FROM users WHERE role = 'student'"
);

$totalInstructorCount = get_count(
    $conn,
    "SELECT COUNT(*) AS total FROM users WHERE role = 'instructor'"
);

$totalBlockedCount = get_count(
    $conn,
    "SELECT COUNT(*) AS total FROM users WHERE role IN ('student', 'instructor') AND status = 'blocked'"
);

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function display_value($value)
{
    $value = trim((string) $value);

    return $value !== '' ? $value : 'Not provided';
}

function role_label($role)
{
    if ($role === 'student') {
        return 'Student';
    }

    if ($role === 'instructor') {
        return 'Instructor';
    }

    return ucfirst((string) $role);
}

function role_class($role)
{
    if ($role === 'student') {
        return 'role-student';
    }

    if ($role === 'instructor') {
        return 'role-instructor';
    }

    return 'role-student';
}

function status_label($status)
{
    if ($status === 'active') {
        return 'Active';
    }

    if ($status === 'inactive') {
        return 'Inactive / Pending';
    }

    if ($status === 'blocked') {
        return 'Blocked';
    }

    return ucfirst((string) $status);
}

function status_class($status)
{
    if ($status === 'active') {
        return 'status-active';
    }

    if ($status === 'inactive') {
        return 'status-inactive';
    }

    if ($status === 'blocked') {
        return 'status-blocked';
    }

    return 'status-inactive';
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/admin_navbar.php';
?>


<main class="admin-users-page">
    <section class="admin-users-wrapper">

        <div class="admin-users-header">
            <div>
                <p class="page-label">Admin Panel</p>
                <h1>Users Management</h1>
                <p>
                    View and manage only students and instructors. Admin accounts are hidden from this page.
                </p>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="admin-alert <?php echo h($messageType); ?>">
                <?php echo h($message); ?>
            </div>
        <?php endif; ?>

        <div class="user-stats-grid">

            <a href="admin-users.php" class="stat-card stat-link <?php echo $roleFilter === '' ? 'active-filter' : ''; ?>">
                <span>All Users</span>
                <strong><?php echo $totalUsersCount; ?></strong>
                <p>Students + instructors</p>
            </a>

            <a href="admin-users.php?role=student" class="stat-card stat-link student <?php echo $roleFilter === 'student' ? 'active-filter' : ''; ?>">
                <span>Students</span>
                <strong><?php echo $totalStudentCount; ?></strong>
                <p>Only student users</p>
            </a>

            <a href="admin-users.php?role=instructor" class="stat-card stat-link instructor <?php echo $roleFilter === 'instructor' ? 'active-filter' : ''; ?>">
                <span>Instructors</span>
                <strong><?php echo $totalInstructorCount; ?></strong>
                <p>Only instructors</p>
            </a>

            <a href="admin-users.php?status=blocked" class="stat-card stat-link blocked <?php echo $statusFilter === 'blocked' ? 'active-filter' : ''; ?>">
                <span>Blocked</span>
                <strong><?php echo $totalBlockedCount; ?></strong>
                <p>Blocked users</p>
            </a>

        </div>

        <form method="GET" class="user-filter-box">

            <div class="form-group">
                <label>Search</label>
                <input 
                    type="text" 
                    name="search" 
                    value="<?php echo h($search); ?>" 
                    placeholder="Search by name, email, or phone"
                >
            </div>

            <div class="form-group">
                <label>Role</label>
                <select name="role">
                    <option value="">Students + Instructors</option>
                    <option value="student" <?php echo $roleFilter === 'student' ? 'selected' : ''; ?>>
                        Student
                    </option>
                    <option value="instructor" <?php echo $roleFilter === 'instructor' ? 'selected' : ''; ?>>
                        Instructor
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>
                        Active
                    </option>
                    <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>
                        Inactive / Pending
                    </option>
                    <option value="blocked" <?php echo $statusFilter === 'blocked' ? 'selected' : ''; ?>>
                        Blocked
                    </option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit">Apply Filter</button>
                <a href="admin-users.php">Reset</a>
            </div>

        </form>

        <?php if (empty($users)): ?>

            <div class="empty-users-box">
                <div class="empty-icon">No users</div>
                <h2>No users found</h2>
                <p>No students or instructors matched your current search/filter.</p>
            </div>

        <?php else: ?>

            <div class="users-grid">

                <?php foreach ($users as $user): ?>
                    <?php
                        $userId = (int) $user['id'];
                        $profileImage = $user['profile_image'] ?? '';
                        $firstLetter = strtoupper(substr($user['full_name'] ?? 'U', 0, 1));

                        if ($profileImage !== '') {
                            $profilePath = 'assets/uploads/profile_photos/' . $profileImage;
                        } else {
                            $profilePath = '';
                        }
                    ?>

                    <article class="user-card">

                        <div class="user-top">
                            <div class="user-avatar">
                                <?php if ($profilePath !== ''): ?>
                                    <img 
                                        src="<?php echo h($profilePath); ?>" 
                                        alt="<?php echo h($user['full_name']); ?>"
                                    >
                                <?php else: ?>
                                    <div class="avatar-letter">
                                        <?php echo h($firstLetter); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="user-main-info">
                                <h2><?php echo h($user['full_name']); ?></h2>

                                <div class="badge-row">
                                    <span class="role-pill <?php echo role_class($user['role']); ?>">
                                        <?php echo role_label($user['role']); ?>
                                    </span>

                                    <span class="status-pill <?php echo status_class($user['status']); ?>">
                                        <?php echo status_label($user['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="user-details">
                            <div>
                                <span>Email</span>
                                <strong><?php echo h($user['email']); ?></strong>
                            </div>

                            <div>
                                <span>Phone</span>
                                <strong><?php echo h(display_value($user['phone'])); ?></strong>
                            </div>

                            <div>
                                <span>Joined</span>
                                <strong>
                                    <?php echo !empty($user['created_at']) ? h(date('M d, Y', strtotime($user['created_at']))) : 'Unknown'; ?>
                                </strong>
                            </div>
                        </div>

                        <div class="user-actions">

                            <?php if ($user['status'] === 'blocked'): ?>

                                <form method="POST">
                                      <?php echo csrf_field(); ?>
                                    <input type="hidden" name="user_id" value="<?php echo $userId; ?>">

                                    <button type="submit" name="activate_user" class="action-btn activate">
                                        Activate
                                    </button>
                                </form>

                            <?php else: ?>

                                <form method="POST">
                                      <?php echo csrf_field(); ?>
                                    <input type="hidden" name="user_id" value="<?php echo $userId; ?>">

                                    <button type="submit" name="block_user" class="action-btn block">
                                        Block
                                    </button>
                                </form>

                                <?php if ($user['status'] !== 'active'): ?>
                                    <form method="POST">
                                          <?php echo csrf_field(); ?>
                                        <input type="hidden" name="user_id" value="<?php echo $userId; ?>">

                                        <button type="submit" name="activate_user" class="action-btn activate">
                                            Activate
                                        </button>
                                    </form>
                                <?php endif; ?>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>
</main>

</body>
</html>
