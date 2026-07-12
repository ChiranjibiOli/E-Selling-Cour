<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/AdminMiddleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/security_helper.php';

AdminMiddleware::handle();

$message = '';
$messageType = '';

if (isset($_GET['changed'])) {
    $message = $_GET['changed'] === 'blocked'
        ? 'User blocked successfully.'
        : 'User activated successfully.';
    $messageType = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $action = isset($_POST['block_user'])
        ? 'block'
        : (isset($_POST['activate_user']) ? 'activate' : '');

    if ($userId <= 0 || !in_array($action, ['block', 'activate'], true)) {
        $message = 'Invalid user-management request.';
        $messageType = 'error';
    } else {
        $targetStatus = $action === 'block' ? 'blocked' : 'active';
        $allowedCurrentStatuses = $action === 'block'
            ? ['active', 'inactive']
            : ['blocked', 'inactive'];
        $placeholders = implode(',', array_fill(0, count($allowedCurrentStatuses), '?'));
        $types = 'is' . str_repeat('s', count($allowedCurrentStatuses));
        $params = array_merge([$userId, $targetStatus], $allowedCurrentStatuses);

        try {
            $conn->begin_transaction();
            $stmt = $conn->prepare("
                UPDATE users
                SET status = ?
                WHERE id = ?
                  AND role IN ('student', 'instructor')
                  AND status IN ({$placeholders})
            ");

            if (!$stmt) {
                throw new RuntimeException('The user update could not be prepared.');
            }

            $bindTypes = 'si' . str_repeat('s', count($allowedCurrentStatuses));
            $bindValues = array_merge([$targetStatus, $userId], $allowedCurrentStatuses);
            $stmt->bind_param($bindTypes, ...$bindValues);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            if ($affected !== 1) {
                throw new DomainException('That user no longer has an eligible account state for this action.');
            }

            $conn->commit();
            Auth::redirect('admin-users.php?changed=' . rawurlencode($targetStatus));
        } catch (DomainException $exception) {
            $conn->rollback();
            $message = $exception->getMessage();
            $messageType = 'error';
        } catch (Throwable $exception) {
            $conn->rollback();
            error_log('Admin user status update failed: ' . $exception->getMessage());
            $message = 'The user status could not be changed right now.';
            $messageType = 'error';
        }
    }
}

$searchInput = trim((string) ($_GET['search'] ?? ''));
$search = security_clean_text($searchInput, 150);
$roleFilter = trim((string) ($_GET['role'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$allowedRoles = ['student', 'instructor'];
$allowedStatuses = ['active', 'inactive', 'blocked'];

if (!in_array($roleFilter, $allowedRoles, true)) {
    $roleFilter = '';
}

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

$whereParts = ["role IN ('student', 'instructor')"];
$params = [];
$types = '';

if ($search !== '') {
    $whereParts[] = '(full_name LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $searchValue = '%' . $search . '%';
    $params = [$searchValue, $searchValue, $searchValue];
    $types .= 'sss';
}

if ($roleFilter !== '') {
    $whereParts[] = 'role = ?';
    $params[] = $roleFilter;
    $types .= 's';
}

if ($statusFilter !== '') {
    $whereParts[] = 'status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}

$whereSql = 'WHERE ' . implode(' AND ', $whereParts);
$users = [];
$stmt = $conn->prepare("
    SELECT id, full_name, email, phone, profile_image, role, status, created_at
    FROM users
    {$whereSql}
    ORDER BY CASE WHEN role = 'instructor' THEN 1 ELSE 2 END, created_at DESC
");

if ($stmt) {
    if ($params !== []) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($result && $row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();
}

function admin_user_count(mysqli $conn, string $where): int
{
    $result = $conn->query('SELECT COUNT(*) AS total FROM users WHERE ' . $where);
    $row = $result ? $result->fetch_assoc() : null;
    return (int) ($row['total'] ?? 0);
}

$totalUsersCount = admin_user_count($conn, "role IN ('student', 'instructor')");
$totalStudentCount = admin_user_count($conn, "role = 'student'");
$totalInstructorCount = admin_user_count($conn, "role = 'instructor'");
$totalBlockedCount = admin_user_count($conn, "role IN ('student', 'instructor') AND status = 'blocked'");

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function display_value(mixed $value): string
{
    $value = trim((string) $value);
    return $value !== '' ? $value : 'Not provided';
}

function role_label(string $role): string
{
    return $role === 'instructor' ? 'Instructor' : 'Student';
}

function role_class(string $role): string
{
    return $role === 'instructor' ? 'role-instructor' : 'role-student';
}

function status_label(string $status): string
{
    return match ($status) {
        'active' => 'Active',
        'inactive' => 'Inactive / Pending',
        'blocked' => 'Blocked',
        default => ucfirst($status),
    };
}

function status_class(string $status): string
{
    return match ($status) {
        'active' => 'status-active',
        'blocked' => 'status-blocked',
        default => 'status-inactive',
    };
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
                <p>View and manage students and instructors. Administrative accounts remain outside this directory.</p>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="admin-alert <?php echo h($messageType); ?>"><?php echo h($message); ?></div>
        <?php endif; ?>

        <div class="user-stats-grid">
            <a href="admin-users.php" class="stat-card stat-link <?php echo $roleFilter === '' ? 'active-filter' : ''; ?>">
                <span>All Users</span><strong><?php echo $totalUsersCount; ?></strong><p>Students + instructors</p>
            </a>
            <a href="admin-users.php?role=student" class="stat-card stat-link student <?php echo $roleFilter === 'student' ? 'active-filter' : ''; ?>">
                <span>Students</span><strong><?php echo $totalStudentCount; ?></strong><p>Only student users</p>
            </a>
            <a href="admin-users.php?role=instructor" class="stat-card stat-link instructor <?php echo $roleFilter === 'instructor' ? 'active-filter' : ''; ?>">
                <span>Instructors</span><strong><?php echo $totalInstructorCount; ?></strong><p>Only instructors</p>
            </a>
            <a href="admin-users.php?status=blocked" class="stat-card stat-link blocked <?php echo $statusFilter === 'blocked' ? 'active-filter' : ''; ?>">
                <span>Blocked</span><strong><?php echo $totalBlockedCount; ?></strong><p>Blocked users</p>
            </a>
        </div>

        <form method="GET" class="user-filter-box">
            <div class="form-group">
                <label for="search">Search</label>
                <input id="search" type="text" name="search" maxlength="150" value="<?php echo h($search); ?>" placeholder="Search by name, email, or phone">
            </div>
            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role">
                    <option value="">Students + Instructors</option>
                    <option value="student" <?php echo $roleFilter === 'student' ? 'selected' : ''; ?>>Student</option>
                    <option value="instructor" <?php echo $roleFilter === 'instructor' ? 'selected' : ''; ?>>Instructor</option>
                </select>
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive / Pending</option>
                    <option value="blocked" <?php echo $statusFilter === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit">Apply Filter</button>
                <a href="admin-users.php">Reset</a>
            </div>
        </form>

        <?php if ($users === []): ?>
            <div class="empty-users-box">
                <div class="empty-icon">No users</div>
                <h2>No users found</h2>
                <p>No students or instructors matched the current filters.</p>
            </div>
        <?php else: ?>
            <div class="users-grid">
                <?php foreach ($users as $listedUser): ?>
                    <?php
                    $userId = (int) $listedUser['id'];
                    $hasProfileImage = trim((string) ($listedUser['profile_image'] ?? '')) !== '';
                    $firstLetter = strtoupper(substr((string) ($listedUser['full_name'] ?? 'U'), 0, 1));
                    ?>
                    <article class="user-card">
                        <div class="user-top">
                            <div class="user-avatar">
                                <?php if ($hasProfileImage): ?>
                                    <img src="admin-view-user-photo.php?id=<?php echo $userId; ?>" alt="<?php echo h($listedUser['full_name']); ?>">
                                <?php else: ?>
                                    <div class="avatar-letter"><?php echo h($firstLetter); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="user-main-info">
                                <h2><?php echo h($listedUser['full_name']); ?></h2>
                                <div class="badge-row">
                                    <span class="role-pill <?php echo role_class((string) $listedUser['role']); ?>"><?php echo role_label((string) $listedUser['role']); ?></span>
                                    <span class="status-pill <?php echo status_class((string) $listedUser['status']); ?>"><?php echo status_label((string) $listedUser['status']); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="user-details">
                            <div><span>Email</span><strong><?php echo h($listedUser['email']); ?></strong></div>
                            <div><span>Phone</span><strong><?php echo h(display_value($listedUser['phone'])); ?></strong></div>
                            <div><span>Joined</span><strong><?php echo !empty($listedUser['created_at']) ? h(date('M d, Y', strtotime((string) $listedUser['created_at']))) : 'Unknown'; ?></strong></div>
                        </div>

                        <div class="user-actions">
                            <?php if ($listedUser['status'] === 'blocked'): ?>
                                <form method="POST">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                                    <button type="submit" name="activate_user" class="action-btn activate" data-confirm="Activate this account?">Activate</button>
                                </form>
                            <?php else: ?>
                                <form method="POST">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                                    <button type="submit" name="block_user" class="action-btn block" data-confirm="Block this account?">Block</button>
                                </form>
                                <?php if ($listedUser['status'] !== 'active'): ?>
                                    <form method="POST">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                                        <button type="submit" name="activate_user" class="action-btn activate" data-confirm="Activate this account?">Activate</button>
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

<?php require_once __DIR__ . '/../layouts/panel_end.php'; ?>
