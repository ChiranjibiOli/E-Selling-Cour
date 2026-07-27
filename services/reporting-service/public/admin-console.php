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

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$requestId = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
if ($requestId === '' || preg_match('/^[A-Za-z0-9._-]{8,100}$/', $requestId) !== 1) {
    $requestId = bin2hex(random_bytes(16));
}
header('X-Request-ID: ' . $requestId);

$respond = static function (array $payload, int $status = 200) use ($requestId): never {
    http_response_code($status);
    echo json_encode($payload + ['request_id' => $requestId], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
};

$jsonInput = static function (): array {
    $raw = (string) file_get_contents('php://input');
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Request body must be a JSON object.');
    }
    return $decoded;
};

$rollback = static function (?PDO $database): void {
    if ($database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
};

$searchValue = static function (): string {
    $value = trim((string) ($_GET['q'] ?? ''));
    return mb_substr($value, 0, 120);
};

$statusValue = static function (): string {
    return strtolower(trim((string) ($_GET['status'] ?? '')));
};

$slugify = static function (string $value): string {
    $value = mb_strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
    return trim($value, '-');
};

$database = null;
try {
    if (preg_match('#^/api/v1/reports/admin-console/([a-z-]+)$#', $path, $matches) !== 1) {
        $respond(['error' => 'Admin console route not found.'], 404);
    }

    $resource = $matches[1];
    $supported = [
        'notifications', 'students', 'instructors', 'users', 'categories', 'refunds',
        'coupons', 'reports', 'audit-logs', 'security', 'settings',
    ];
    if (!in_array($resource, $supported, true)) {
        $respond(['error' => 'That Admin console resource is unavailable.'], 404);
    }

    $database = Database::connect();
    $admin = ServiceAuth::requireUser($database, $authorization, 'admin');
    $adminId = (int) ($admin['id'] ?? 0);

    if ($method === 'GET') {
        $query = $searchValue();
        $status = $statusValue();
        $like = '%' . $query . '%';

        if ($resource === 'notifications') {
            $sql = 'SELECT id,title,message,notification_type,is_read,created_at,read_at '
                . 'FROM notifications WHERE user_id=:admin_id';
            $params = ['admin_id' => $adminId];
            if ($query !== '') {
                $sql .= ' AND (title LIKE :query OR message LIKE :query OR notification_type LIKE :query)';
                $params['query'] = $like;
            }
            if (in_array($status, ['read', 'unread'], true)) {
                $sql .= ' AND is_read=:is_read';
                $params['is_read'] = $status === 'read' ? 1 : 0;
            }
            $sql .= ' ORDER BY created_at DESC LIMIT 300';
            $statement = $database->prepare($sql);
            $statement->execute($params);
            $rows = $statement->fetchAll();
            $countStatement = $database->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=:id AND is_read=0');
            $countStatement->execute(['id' => $adminId]);
            $respond(['data' => $rows, 'meta' => ['unread' => (int) $countStatement->fetchColumn()]]);
        }

        if ($resource === 'students') {
            $sql = "SELECT u.id,u.full_name,u.email,u.phone,u.status,u.last_login_at,u.created_at,"
                . "COUNT(DISTINCT e.id) AS enrollments,"
                . "SUM(CASE WHEN e.status='active' THEN 1 ELSE 0 END) AS active_enrollments "
                . "FROM users u LEFT JOIN enrollments e ON e.student_id=u.id WHERE u.role='student'";
            $params = [];
            if ($query !== '') {
                $sql .= ' AND (u.full_name LIKE :query OR u.email LIKE :query OR u.phone LIKE :query)';
                $params['query'] = $like;
            }
            if (in_array($status, ['active', 'inactive', 'blocked'], true)) {
                $sql .= ' AND u.status=:status';
                $params['status'] = $status;
            }
            $sql .= ' GROUP BY u.id ORDER BY u.created_at DESC LIMIT 500';
            $statement = $database->prepare($sql);
            $statement->execute($params);
            $respond(['data' => $statement->fetchAll()]);
        }

        if ($resource === 'instructors') {
            $sql = "SELECT u.id,u.full_name,u.email,u.phone,u.status,u.last_login_at,u.created_at,"
                . "a.application_status,a.review_note,COUNT(DISTINCT c.id) AS courses,"
                . "SUM(CASE WHEN c.status='published' THEN 1 ELSE 0 END) AS published_courses,"
                . "COUNT(DISTINCT e.student_id) AS students "
                . "FROM users u LEFT JOIN instructor_applications a ON a.instructor_id=u.id "
                . "LEFT JOIN courses c ON c.instructor_id=u.id "
                . "LEFT JOIN enrollments e ON e.course_id=c.id AND e.status='active' "
                . "WHERE u.role='instructor'";
            $params = [];
            if ($query !== '') {
                $sql .= ' AND (u.full_name LIKE :query OR u.email LIKE :query OR u.phone LIKE :query)';
                $params['query'] = $like;
            }
            if (in_array($status, ['active', 'inactive', 'blocked'], true)) {
                $sql .= ' AND u.status=:status';
                $params['status'] = $status;
            }
            $sql .= ' GROUP BY u.id ORDER BY u.created_at DESC LIMIT 500';
            $statement = $database->prepare($sql);
            $statement->execute($params);
            $respond(['data' => $statement->fetchAll()]);
        }

        if ($resource === 'users') {
            $sql = 'SELECT id,full_name,email,phone,role,status,last_login_at,created_at FROM users WHERE 1=1';
            $params = [];
            if ($query !== '') {
                $sql .= ' AND (full_name LIKE :query OR email LIKE :query OR phone LIKE :query OR role LIKE :query)';
                $params['query'] = $like;
            }
            if (in_array($status, ['active', 'inactive', 'blocked'], true)) {
                $sql .= ' AND status=:status';
                $params['status'] = $status;
            }
            $sql .= ' ORDER BY created_at DESC LIMIT 500';
            $statement = $database->prepare($sql);
            $statement->execute($params);
            $respond(['data' => $statement->fetchAll(), 'meta' => ['current_admin_id' => $adminId]]);
        }

        if ($resource === 'categories') {
            $sql = 'SELECT c.id,c.name,c.slug,c.description,c.status,c.updated_at,COUNT(co.id) AS courses '
                . 'FROM categories c LEFT JOIN courses co ON co.category_id=c.id WHERE 1=1';
            $params = [];
            if ($query !== '') {
                $sql .= ' AND (c.name LIKE :query OR c.slug LIKE :query OR c.description LIKE :query)';
                $params['query'] = $like;
            }
            if (in_array($status, ['active', 'inactive'], true)) {
                $sql .= ' AND c.status=:status';
                $params['status'] = $status;
            }
            $sql .= ' GROUP BY c.id ORDER BY c.name ASC LIMIT 300';
            $statement = $database->prepare($sql);
            $statement->execute($params);
            $respond(['data' => $statement->fetchAll()]);
        }

        if ($resource === 'refunds') {
            $sql = "SELECT p.id AS payment_id,o.id AS order_id,u.full_name AS student_name,u.email AS student_email,"
                . "p.payment_method,p.transaction_id,p.paid_amount,p.payment_status,o.order_status,p.uploaded_at,"
                . "SUM(CASE WHEN ie.earning_status='paid' THEN 1 ELSE 0 END) AS paid_earnings "
                . "FROM payments p INNER JOIN orders o ON o.id=p.order_id INNER JOIN users u ON u.id=p.student_id "
                . "LEFT JOIN instructor_earnings ie ON ie.payment_id=p.id "
                . "WHERE p.payment_status IN ('paid','refunded')";
            $params = [];
            if ($query !== '') {
                $sql .= ' AND (u.full_name LIKE :query OR u.email LIKE :query OR p.transaction_id LIKE :query OR CAST(o.id AS CHAR) LIKE :query)';
                $params['query'] = $like;
            }
            if (in_array($status, ['paid', 'refunded'], true)) {
                $sql .= ' AND p.payment_status=:status';
                $params['status'] = $status;
            }
            $sql .= ' GROUP BY p.id ORDER BY p.uploaded_at DESC LIMIT 300';
            $statement = $database->prepare($sql);
            $statement->execute($params);
            $respond(['data' => $statement->fetchAll()]);
        }

        if ($resource === 'coupons') {
            $sql = 'SELECT c.id,c.code,c.discount_type,c.discount_value,c.min_order_amount,c.max_discount,c.usage_limit,c.used_count,c.valid_from,c.valid_until,c.status,c.updated_at,u.full_name AS creator_name '
                . 'FROM coupons c LEFT JOIN users u ON u.id=c.created_by WHERE 1=1';
            $params = [];
            if ($query !== '') {
                $sql .= ' AND (c.code LIKE :query OR u.full_name LIKE :query)';
                $params['query'] = $like;
            }
            if (in_array($status, ['active', 'inactive', 'expired'], true)) {
                $sql .= ' AND c.status=:status';
                $params['status'] = $status;
            }
            $sql .= ' ORDER BY c.created_at DESC LIMIT 300';
            $statement = $database->prepare($sql);
            $statement->execute($params);
            $respond(['data' => $statement->fetchAll()]);
        }

        if ($resource === 'reports') {
            $summary = [
                'users' => (int) $database->query('SELECT COUNT(*) FROM users')->fetchColumn(),
                'published_courses' => (int) $database->query("SELECT COUNT(*) FROM courses WHERE status='published'")->fetchColumn(),
                'active_enrollments' => (int) $database->query("SELECT COUNT(*) FROM enrollments WHERE status='active'")->fetchColumn(),
                'paid_orders' => (int) $database->query("SELECT COUNT(*) FROM orders WHERE order_status='paid'")->fetchColumn(),
                'verified_revenue' => (string) ($database->query("SELECT COALESCE(SUM(paid_amount),0) FROM payments WHERE payment_status='paid'")->fetchColumn() ?: '0'),
                'refunded_value' => (string) ($database->query("SELECT COALESCE(SUM(paid_amount),0) FROM payments WHERE payment_status='refunded'")->fetchColumn() ?: '0'),
                'platform_earnings' => (string) ($database->query("SELECT COALESCE(SUM(commission_amount),0) FROM instructor_earnings WHERE earning_status NOT IN ('cancelled','refunded')")->fetchColumn() ?: '0'),
                'instructor_payments' => (string) ($database->query("SELECT COALESCE(SUM(paid_amount),0) FROM payouts WHERE payout_status='paid'")->fetchColumn() ?: '0'),
            ];
            $monthly = $database->query(
                "SELECT DATE_FORMAT(p.uploaded_at,'%Y-%m') AS month,COUNT(*) AS payments,COALESCE(SUM(p.paid_amount),0) AS revenue "
                . "FROM payments p WHERE p.payment_status='paid' AND p.uploaded_at>=DATE_SUB(CURDATE(),INTERVAL 11 MONTH) "
                . "GROUP BY DATE_FORMAT(p.uploaded_at,'%Y-%m') ORDER BY month"
            )->fetchAll();
            $topCourses = $database->query(
                "SELECT c.id,c.title,u.full_name AS instructor_name,COUNT(e.id) AS enrollments,"
                . "COALESCE(SUM(oi.final_price),0) AS sales_value FROM courses c "
                . "INNER JOIN users u ON u.id=c.instructor_id LEFT JOIN enrollments e ON e.course_id=c.id AND e.status='active' "
                . "LEFT JOIN order_items oi ON oi.course_id=c.id LEFT JOIN orders o ON o.id=oi.order_id AND o.order_status='paid' "
                . "GROUP BY c.id ORDER BY enrollments DESC,sales_value DESC LIMIT 15"
            )->fetchAll();
            $respond(['data' => ['summary' => $summary, 'monthly' => $monthly, 'top_courses' => $topCourses]]);
        }

        if ($resource === 'audit-logs') {
            $sql = "SELECT event,actor,resource,context,created_at FROM ("
                . "SELECT 'Instructor application reviewed' AS event,COALESCE(ad.full_name,'System') AS actor,CONCAT('Instructor #',ia.instructor_id) AS resource,CONCAT(ia.application_status,CASE WHEN ia.review_note IS NULL THEN '' ELSE CONCAT(' · ',ia.review_note) END) AS context,ia.reviewed_at AS created_at FROM instructor_applications ia LEFT JOIN users ad ON ad.id=ia.reviewed_by WHERE ia.reviewed_at IS NOT NULL "
                . "UNION ALL SELECT 'Course review',COALESCE(ad.full_name,'System'),CONCAT('Course #',c.id,' · ',c.title),CONCAT(c.status,CASE WHEN c.review_note IS NULL THEN '' ELSE CONCAT(' · ',c.review_note) END),c.reviewed_at FROM courses c LEFT JOIN users ad ON ad.id=c.reviewed_by WHERE c.reviewed_at IS NOT NULL "
                . "UNION ALL SELECT 'Payment verification',COALESCE(ad.full_name,'System'),CONCAT('Payment #',p.id,' · Order #',p.order_id),CONCAT(p.payment_status,' · ',p.transaction_id),p.verified_at FROM payments p LEFT JOIN users ad ON ad.id=p.verified_by WHERE p.verified_at IS NOT NULL "
                . "UNION ALL SELECT 'Withdrawal processing',COALESCE(ad.full_name,'System'),CONCAT('Withdrawal #',wr.id),CONCAT(wr.request_status,CASE WHEN wr.admin_note IS NULL THEN '' ELSE CONCAT(' · ',wr.admin_note) END),wr.processed_at FROM withdrawal_requests wr LEFT JOIN users ad ON ad.id=wr.processed_by WHERE wr.processed_at IS NOT NULL"
                . ") activity WHERE created_at IS NOT NULL";
            $params = [];
            if ($query !== '') {
                $sql .= ' AND (event LIKE :query OR actor LIKE :query OR resource LIKE :query OR context LIKE :query)';
                $params['query'] = $like;
            }
            $sql .= ' ORDER BY created_at DESC LIMIT 500';
            $statement = $database->prepare($sql);
            $statement->execute($params);
            $respond(['data' => $statement->fetchAll()]);
        }

        if ($resource === 'security') {
            $sessions = $database->query(
                "SELECT s.id,u.full_name,u.email,s.portal,s.created_at,s.expires_at,s.revoked_at,"
                . "CASE WHEN s.revoked_at IS NULL AND s.expires_at>NOW() THEN 'active' ELSE 'closed' END AS status "
                . "FROM identity_sessions s INNER JOIN users u ON u.id=s.user_id ORDER BY s.created_at DESC LIMIT 300"
            )->fetchAll();
            $attempts = $database->query(
                "SELECT id,email_hash,ip_hash,attempts,locked_until,last_attempt_at,"
                . "CASE WHEN locked_until IS NOT NULL AND locked_until>NOW() THEN 'locked' ELSE 'clear' END AS status "
                . "FROM identity_login_attempts ORDER BY last_attempt_at DESC LIMIT 300"
            )->fetchAll();
            $summary = [
                'active_sessions' => (int) $database->query("SELECT COUNT(*) FROM identity_sessions WHERE revoked_at IS NULL AND expires_at>NOW()")->fetchColumn(),
                'locked_attempts' => (int) $database->query("SELECT COUNT(*) FROM identity_login_attempts WHERE locked_until IS NOT NULL AND locked_until>NOW()")->fetchColumn(),
                'admin_sessions' => (int) $database->query("SELECT COUNT(*) FROM identity_sessions WHERE portal='admin' AND revoked_at IS NULL AND expires_at>NOW()")->fetchColumn(),
            ];
            $respond(['data' => ['summary' => $summary, 'sessions' => $sessions, 'attempts' => $attempts]]);
        }

        if ($resource === 'settings') {
            $allowed = [
                'site_name', 'site_email', 'site_phone', 'site_address', 'platform_commission_rate',
                'esewa_id', 'khalti_id', 'bank_name', 'bank_account_name', 'bank_account_number',
                'payment_instructions', 'terms_url', 'privacy_url',
            ];
            $placeholders = implode(',', array_fill(0, count($allowed), '?'));
            $statement = $database->prepare('SELECT setting_key,setting_value,updated_at FROM site_settings WHERE setting_key IN (' . $placeholders . ') ORDER BY setting_key');
            $statement->execute($allowed);
            $values = [];
            foreach ($statement->fetchAll() as $row) {
                $values[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
            }
            $respond(['data' => ['values' => $values, 'allowed' => $allowed]]);
        }
    }

    if ($method !== 'POST') {
        $respond(['error' => 'Method not allowed.'], 405);
    }

    $input = $jsonInput();
    $action = strtolower(trim((string) ($input['action'] ?? '')));

    if ($resource === 'notifications') {
        if ($action === 'mark_all_read') {
            $statement = $database->prepare('UPDATE notifications SET is_read=1,read_at=COALESCE(read_at,NOW()) WHERE user_id=:id AND is_read=0');
            $statement->execute(['id' => $adminId]);
            $respond(['message' => 'All Admin notifications were marked as read.', 'updated' => $statement->rowCount()]);
        }
        if ($action === 'mark_read') {
            $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                throw new InvalidArgumentException('Choose a valid notification.');
            }
            $statement = $database->prepare('UPDATE notifications SET is_read=1,read_at=COALESCE(read_at,NOW()) WHERE id=:id AND user_id=:admin_id');
            $statement->execute(['id' => $id, 'admin_id' => $adminId]);
            $respond(['message' => 'Notification marked as read.']);
        }
        throw new InvalidArgumentException('Choose a valid notification action.');
    }

    if (in_array($resource, ['students', 'instructors', 'users'], true)) {
        if ($action !== 'set_status') {
            throw new InvalidArgumentException('Choose a valid account action.');
        }
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        $newStatus = strtolower(trim((string) ($input['status'] ?? '')));
        if ($id === false || $id < 1 || !in_array($newStatus, ['active', 'blocked'], true)) {
            throw new InvalidArgumentException('Choose an account and a valid status.');
        }
        if ((int) $id === $adminId) {
            throw new InvalidArgumentException('You cannot block or reactivate your own Admin account from this screen.');
        }

        $database->beginTransaction();
        $statement = $database->prepare('SELECT u.id,u.role,u.status,a.application_status FROM users u LEFT JOIN instructor_applications a ON a.instructor_id=u.id WHERE u.id=:id LIMIT 1 FOR UPDATE');
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();
        if (!is_array($user)) {
            throw new InvalidArgumentException('That account no longer exists.');
        }
        if ($resource === 'students' && (string) $user['role'] !== 'student') {
            throw new InvalidArgumentException('That record is not a Student account.');
        }
        if ($resource === 'instructors' && (string) $user['role'] !== 'instructor') {
            throw new InvalidArgumentException('That record is not an Instructor account.');
        }
        if ((string) $user['role'] === 'instructor' && $newStatus === 'active' && (string) ($user['application_status'] ?? '') !== 'approved') {
            throw new InvalidArgumentException('Approve the Instructor application before activating this account.');
        }
        $update = $database->prepare('UPDATE users SET status=:status WHERE id=:id');
        $update->execute(['status' => $newStatus, 'id' => $id]);
        if ($newStatus === 'blocked') {
            $revoke = $database->prepare('UPDATE identity_sessions SET revoked_at=NOW() WHERE user_id=:id AND revoked_at IS NULL');
            $revoke->execute(['id' => $id]);
        }
        $notification = $database->prepare("INSERT INTO notifications (user_id,title,message,notification_type) VALUES (:id,'Account status updated',:message,'account_status')");
        $notification->execute(['id' => $id, 'message' => 'An administrator changed your CourseHub account status to ' . $newStatus . '.']);
        $database->commit();
        $respond(['message' => 'Account status updated to ' . $newStatus . '.']);
    }

    if ($resource === 'categories') {
        if ($action === 'create') {
            $name = trim((string) ($input['name'] ?? ''));
            $slug = $slugify((string) ($input['slug'] ?? $name));
            $description = trim((string) ($input['description'] ?? ''));
            if (mb_strlen($name) < 2 || mb_strlen($name) > 100 || $slug === '' || mb_strlen($slug) > 120 || mb_strlen($description) > 2000) {
                throw new InvalidArgumentException('Enter a valid category name, slug and description.');
            }
            $statement = $database->prepare("INSERT INTO categories (name,slug,description,status) VALUES (:name,:slug,:description,'active')");
            $statement->execute(['name' => $name, 'slug' => $slug, 'description' => $description !== '' ? $description : null]);
            $respond(['message' => 'Category created.'], 201);
        }
        if ($action === 'set_status') {
            $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
            $newStatus = strtolower(trim((string) ($input['status'] ?? '')));
            if ($id === false || $id < 1 || !in_array($newStatus, ['active', 'inactive'], true)) {
                throw new InvalidArgumentException('Choose a category and valid status.');
            }
            $statement = $database->prepare('UPDATE categories SET status=:status WHERE id=:id');
            $statement->execute(['status' => $newStatus, 'id' => $id]);
            $respond(['message' => 'Category status updated.']);
        }
        throw new InvalidArgumentException('Choose a valid category action.');
    }

    if ($resource === 'coupons') {
        if ($action === 'create') {
            $code = strtoupper(trim((string) ($input['code'] ?? '')));
            $type = strtolower(trim((string) ($input['discount_type'] ?? 'percent')));
            $value = (float) ($input['discount_value'] ?? 0);
            $minimum = max(0, (float) ($input['min_order_amount'] ?? 0));
            $maximum = trim((string) ($input['max_discount'] ?? ''));
            $usageLimit = trim((string) ($input['usage_limit'] ?? ''));
            $validFrom = trim((string) ($input['valid_from'] ?? ''));
            $validUntil = trim((string) ($input['valid_until'] ?? ''));
            if (preg_match('/^[A-Z0-9_-]{3,50}$/', $code) !== 1 || !in_array($type, ['fixed', 'percent'], true) || $value <= 0) {
                throw new InvalidArgumentException('Enter a valid coupon code, discount type and value.');
            }
            if ($type === 'percent' && $value > 100) {
                throw new InvalidArgumentException('Percentage discounts cannot exceed 100%.');
            }
            if (($validFrom !== '' && strtotime($validFrom) === false) || ($validUntil !== '' && strtotime($validUntil) === false)) {
                throw new InvalidArgumentException('Enter valid coupon dates.');
            }
            if ($validFrom !== '' && $validUntil !== '' && strtotime($validUntil) <= strtotime($validFrom)) {
                throw new InvalidArgumentException('Coupon expiry must be after its start date.');
            }
            $statement = $database->prepare(
                "INSERT INTO coupons (code,created_by,discount_type,discount_value,min_order_amount,max_discount,usage_limit,valid_from,valid_until,status) "
                . "VALUES (:code,:created_by,:type,:value,:minimum,:maximum,:usage_limit,:valid_from,:valid_until,'active')"
            );
            $statement->execute([
                'code' => $code,
                'created_by' => $adminId,
                'type' => $type,
                'value' => number_format($value, 2, '.', ''),
                'minimum' => number_format($minimum, 2, '.', ''),
                'maximum' => $maximum !== '' ? number_format(max(0, (float) $maximum), 2, '.', '') : null,
                'usage_limit' => $usageLimit !== '' ? max(1, (int) $usageLimit) : null,
                'valid_from' => $validFrom !== '' ? date('Y-m-d H:i:s', strtotime($validFrom)) : null,
                'valid_until' => $validUntil !== '' ? date('Y-m-d H:i:s', strtotime($validUntil)) : null,
            ]);
            $respond(['message' => 'Coupon created.'], 201);
        }
        if ($action === 'set_status') {
            $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
            $newStatus = strtolower(trim((string) ($input['status'] ?? '')));
            if ($id === false || $id < 1 || !in_array($newStatus, ['active', 'inactive', 'expired'], true)) {
                throw new InvalidArgumentException('Choose a coupon and valid status.');
            }
            $statement = $database->prepare('UPDATE coupons SET status=:status WHERE id=:id');
            $statement->execute(['status' => $newStatus, 'id' => $id]);
            $respond(['message' => 'Coupon status updated.']);
        }
        throw new InvalidArgumentException('Choose a valid coupon action.');
    }

    if ($resource === 'refunds') {
        if ($action !== 'refund') {
            throw new InvalidArgumentException('Choose a valid refund action.');
        }
        $paymentId = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($paymentId === false || $paymentId < 1 || mb_strlen($reason) < 5 || mb_strlen($reason) > 1000) {
            throw new InvalidArgumentException('Choose a payment and enter a clear refund reason.');
        }
        $database->beginTransaction();
        $statement = $database->prepare('SELECT p.*,o.order_status,u.full_name,u.email FROM payments p INNER JOIN orders o ON o.id=p.order_id INNER JOIN users u ON u.id=p.student_id WHERE p.id=:id LIMIT 1 FOR UPDATE');
        $statement->execute(['id' => $paymentId]);
        $payment = $statement->fetch();
        if (!is_array($payment) || (string) $payment['payment_status'] !== 'paid' || (string) $payment['order_status'] !== 'paid') {
            throw new InvalidArgumentException('Only a paid, non-refunded order can be refunded.');
        }
        $paidEarnings = $database->prepare("SELECT COUNT(*) FROM instructor_earnings WHERE payment_id=:id AND earning_status='paid'");
        $paidEarnings->execute(['id' => $paymentId]);
        if ((int) $paidEarnings->fetchColumn() > 0) {
            throw new InvalidArgumentException('This payment includes Instructor earnings that were already paid. Reconcile that payout before refunding.');
        }
        $database->prepare("UPDATE payments SET payment_status='refunded',verified_by=:admin,verified_at=NOW() WHERE id=:id")
            ->execute(['admin' => $adminId, 'id' => $paymentId]);
        $database->prepare("UPDATE orders SET order_status='refunded' WHERE id=:id")
            ->execute(['id' => (int) $payment['order_id']]);
        $database->prepare("UPDATE enrollments SET status='refunded',revoked_by_admin=:admin,revoked_at=NOW() WHERE payment_id=:id AND status='active'")
            ->execute(['admin' => $adminId, 'id' => $paymentId]);
        $database->prepare("UPDATE instructor_earnings SET earning_status='refunded' WHERE payment_id=:id AND earning_status IN ('pending','available','withdraw_requested')")
            ->execute(['id' => $paymentId]);
        $notification = $database->prepare("INSERT INTO notifications (user_id,title,message,notification_type) VALUES (:user_id,'Order refunded',:message,'refund')");
        $notification->execute([
            'user_id' => (int) $payment['student_id'],
            'message' => 'Order #' . (int) $payment['order_id'] . ' was refunded and course access was revoked. Reason: ' . $reason,
        ]);
        $instructorNotification = $database->prepare(
            "INSERT INTO notifications (user_id,title,message,notification_type) "
            . "SELECT DISTINCT oi.instructor_id,'Order refund recorded',:message,'refund' FROM order_items oi WHERE oi.order_id=:order_id"
        );
        $instructorNotification->execute([
            'message' => 'Order #' . (int) $payment['order_id'] . ' was refunded. Related unpaid earnings were reversed.',
            'order_id' => (int) $payment['order_id'],
        ]);
        $database->commit();
        $respond(['message' => 'Payment refunded and related course access was revoked.']);
    }

    if ($resource === 'security') {
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new InvalidArgumentException('Choose a valid security record.');
        }
        if ($action === 'revoke_session') {
            $statement = $database->prepare('UPDATE identity_sessions SET revoked_at=COALESCE(revoked_at,NOW()) WHERE id=:id');
            $statement->execute(['id' => $id]);
            $respond(['message' => 'Session revoked.']);
        }
        if ($action === 'clear_login_lock') {
            $statement = $database->prepare('UPDATE identity_login_attempts SET attempts=0,locked_until=NULL WHERE id=:id');
            $statement->execute(['id' => $id]);
            $respond(['message' => 'Login lock cleared.']);
        }
        throw new InvalidArgumentException('Choose a valid security action.');
    }

    if ($resource === 'settings') {
        if ($action !== 'save') {
            throw new InvalidArgumentException('Choose a valid settings action.');
        }
        $allowed = [
            'site_name' => 100,
            'site_email' => 150,
            'site_phone' => 30,
            'site_address' => 500,
            'platform_commission_rate' => 6,
            'esewa_id' => 150,
            'khalti_id' => 150,
            'bank_name' => 150,
            'bank_account_name' => 150,
            'bank_account_number' => 100,
            'payment_instructions' => 3000,
            'terms_url' => 500,
            'privacy_url' => 500,
        ];
        $values = is_array($input['values'] ?? null) ? $input['values'] : [];
        if ($values === []) {
            throw new InvalidArgumentException('No settings were supplied.');
        }
        $database->beginTransaction();
        $statement = $database->prepare(
            'INSERT INTO site_settings (setting_key,setting_value) VALUES (:key,:value) '
            . 'ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'
        );
        foreach ($allowed as $key => $limit) {
            if (!array_key_exists($key, $values)) {
                continue;
            }
            $value = trim((string) $values[$key]);
            if (mb_strlen($value) > $limit) {
                throw new InvalidArgumentException('One or more settings exceed the allowed length.');
            }
            if ($key === 'site_email' && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException('Enter a valid support email address.');
            }
            if (in_array($key, ['terms_url', 'privacy_url'], true) && $value !== '' && filter_var($value, FILTER_VALIDATE_URL) === false) {
                throw new InvalidArgumentException('Enter valid Terms and Privacy URLs.');
            }
            if ($key === 'platform_commission_rate') {
                $rate = (float) $value;
                if ($rate < 0 || $rate > 100) {
                    throw new InvalidArgumentException('Commission must be between 0 and 100.');
                }
                $value = number_format($rate, 2, '.', '');
            }
            $statement->execute(['key' => $key, 'value' => $value]);
        }
        $database->commit();
        $respond(['message' => 'Platform settings saved.']);
    }

    throw new InvalidArgumentException('That action is not supported for this Admin page.');
} catch (ServiceAuthenticationException $exception) {
    $rollback($database);
    $respond(['error' => $exception->getMessage()], 401);
} catch (ServiceAuthorizationException $exception) {
    $rollback($database);
    $respond(['error' => $exception->getMessage()], 403);
} catch (InvalidArgumentException|DomainException $exception) {
    $rollback($database);
    $respond(['error' => $exception->getMessage()], 422);
} catch (JsonException) {
    $rollback($database);
    $respond(['error' => 'Malformed JSON request.'], 400);
} catch (PDOException $exception) {
    $rollback($database);
    error_log('Admin console database failure [' . $requestId . ']: ' . $exception->getMessage());
    if ($exception->getCode() === '23000') {
        $respond(['error' => 'That value already exists or conflicts with related data.'], 409);
    }
    $respond(['error' => 'The Admin operation could not be completed.'], 409);
} catch (Throwable $exception) {
    $rollback($database);
    error_log('Admin console failure [' . $requestId . ']: ' . $exception->getMessage());
    $respond(['error' => 'The Admin console service is unavailable.'], 503);
}
