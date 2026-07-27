<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Ui;

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use DomainException;

final class AdminConsole
{
    /** @var array<string,string> */
    private const RESOURCES = [
        'Notifications' => 'notifications',
        'Students' => 'students',
        'Instructors' => 'instructors',
        'Users' => 'users',
        'Categories' => 'categories',
        'Refunds' => 'refunds',
        'Coupons' => 'coupons',
        'Reports' => 'reports',
        'AuditLogs' => 'audit-logs',
        'Security' => 'security',
        'Settings' => 'settings',
    ];

    public static function supports(string $room): bool
    {
        return isset(self::RESOURCES[$room]);
    }

    public static function load(array $metadata, Request $request): array
    {
        $room = (string) ($metadata['room'] ?? '');
        $resource = self::RESOURCES[$room] ?? '';
        if ($resource === '') {
            return ['data' => [], 'meta' => [], 'message' => '', 'success' => false, 'query' => []];
        }

        $message = '';
        $success = true;
        $client = new ApiClient();
        try {
            if ($request->method === 'POST') {
                Csrf::assertValid((string) ($request->body['_token'] ?? ''));
                $payload = $request->body;
                unset($payload['_token']);
                $result = $client->post('/api/v1/reports/admin-console/' . $resource, $payload);
                $message = (string) ($result['message'] ?? 'Admin operation completed.');
            }
        } catch (DomainException $exception) {
            $message = $exception->getMessage();
            $success = false;
        }

        $query = [
            'q' => mb_substr(trim((string) ($request->query['q'] ?? '')), 0, 120),
            'status' => mb_substr(strtolower(trim((string) ($request->query['status'] ?? ''))), 0, 30),
        ];
        $path = '/api/v1/reports/admin-console/' . $resource;
        $parameters = array_filter($query, static fn (string $value): bool => $value !== '');
        if ($parameters !== []) {
            $path .= '?' . http_build_query($parameters);
        }

        try {
            $result = $client->get($path);
            $data = $result['data'] ?? [];
            $meta = is_array($result['meta'] ?? null) ? $result['meta'] : [];
        } catch (DomainException $exception) {
            $data = [];
            $meta = [];
            if ($message === '') {
                $message = $exception->getMessage();
                $success = false;
            }
        }

        return [
            'resource' => $resource,
            'data' => $data,
            'meta' => $meta,
            'message' => $message,
            'success' => $success,
            'query' => $query,
        ];
    }

    /** @return array{content:string,action:string} */
    public static function build(array $metadata, array $model): array
    {
        $room = (string) ($metadata['room'] ?? '');
        $resource = self::RESOURCES[$room] ?? '';
        $message = trim((string) ($model['message'] ?? ''));
        $success = ($model['success'] ?? true) === true;
        $alert = $message !== ''
            ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . self::e($message) . '</div>'
            : '';

        $body = match ($resource) {
            'notifications' => self::notifications($model),
            'students', 'instructors', 'users' => self::people($resource, $model),
            'categories' => self::categories($model),
            'refunds' => self::refunds($model),
            'coupons' => self::coupons($model),
            'reports' => self::reports($model),
            'audit-logs' => self::auditLogs($model),
            'security' => self::security($model),
            'settings' => self::settings($model),
            default => '<p class="muted-copy">This Admin operation is unavailable.</p>',
        };

        return [
            'content' => $alert . '<section class="admin-console-panel">' . self::heading($resource) . $body . '</section>',
            'action' => '',
        ];
    }

    private static function heading(string $resource): string
    {
        $copy = [
            'notifications' => ['Admin notifications', 'Review platform events and clear items after they have been handled.'],
            'students' => ['Student accounts', 'Search Students, check enrollment totals and control account access.'],
            'instructors' => ['Instructor accounts', 'Review approved Instructor operations without bypassing the application queue.'],
            'users' => ['All platform users', 'Search every account and apply safe status changes from one directory.'],
            'categories' => ['Course categories', 'Create categories and control whether they are available for course discovery.'],
            'refunds' => ['Refund control', 'Refund eligible paid orders while revoking course access and reversing unpaid earnings together.'],
            'coupons' => ['Coupon control', 'Create limited discounts and disable promotions without deleting their history.'],
            'reports' => ['Platform reports', 'Read current revenue, enrollment and course-performance records.'],
            'audit-logs' => ['Operational audit log', 'Trace review, payment and payout decisions from recorded platform activity.'],
            'security' => ['Session security', 'Review active sessions, revoke access and clear legitimate login locks.'],
            'settings' => ['Platform settings', 'Edit only the approved public, commerce and payment configuration values.'],
        ];
        [$title, $description] = $copy[$resource] ?? ['Admin operations', 'Manage this platform area.'];
        return '<header class="admin-console-head"><div><h2>' . self::e($title) . '</h2><p>' . self::e($description) . '</p></div></header>';
    }

    private static function toolbar(array $model, array $statuses = []): string
    {
        $query = is_array($model['query'] ?? null) ? $model['query'] : [];
        $status = (string) ($query['status'] ?? '');
        $options = '<option value="">All statuses</option>';
        foreach ($statuses as $value => $label) {
            $options .= '<option value="' . self::e($value) . '"' . ($status === $value ? ' selected' : '') . '>' . self::e($label) . '</option>';
        }
        return '<form class="admin-console-toolbar" method="get"><label>Search<input type="search" name="q" value="' . self::e($query['q'] ?? '') . '" placeholder="Name, email, ID or keyword"></label>'
            . ($statuses !== [] ? '<label>Status<select name="status">' . $options . '</select></label>' : '')
            . '<button class="portal-button secondary" type="submit">Apply filters</button><a class="text-button" href="' . self::e(parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '') . '">Clear</a></form>';
    }

    private static function notifications(array $model): string
    {
        $rows = self::rows($model);
        $html = self::toolbar($model, ['unread' => 'Unread', 'read' => 'Read']);
        $html .= '<div class="admin-console-actions"><form method="post">' . Csrf::field() . '<input type="hidden" name="action" value="mark_all_read"><button class="portal-button secondary" type="submit">Mark all read</button></form><span>' . (int) (($model['meta']['unread'] ?? 0)) . ' unread</span></div>';
        $body = '';
        foreach ($rows as $row) {
            $read = (int) ($row['is_read'] ?? 0) === 1;
            $action = $read ? '<span class="muted-copy">Read</span>' : '<form method="post">' . Csrf::field() . '<input type="hidden" name="action" value="mark_read"><input type="hidden" name="id" value="' . (int) ($row['id'] ?? 0) . '"><button class="text-button" type="submit">Mark read</button></form>';
            $body .= '<tr><td><strong>' . self::e($row['title'] ?? '') . '</strong><small>' . self::e($row['message'] ?? '') . '</small></td><td>' . self::e($row['notification_type'] ?? 'general') . '</td><td>' . self::badge($read ? 'read' : 'unread') . '</td><td>' . self::e($row['created_at'] ?? '') . '</td><td>' . $action . '</td></tr>';
        }
        return $html . self::table(['Notification', 'Type', 'Status', 'Created', 'Action'], $body, 5, 'No Admin notifications match these filters.');
    }

    private static function people(string $resource, array $model): string
    {
        $rows = self::rows($model);
        $html = self::toolbar($model, ['active' => 'Active', 'inactive' => 'Inactive', 'blocked' => 'Blocked']);
        $body = '';
        $currentAdminId = (int) (($model['meta']['current_admin_id'] ?? 0));
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $status = (string) ($row['status'] ?? 'inactive');
            $identity = '<strong>' . self::e($row['full_name'] ?? '') . '</strong><small>' . self::e($row['email'] ?? '') . '</small>';
            $action = self::statusForm($id, $status, $resource === 'users' && $id === $currentAdminId, $resource === 'instructors' ? (string) ($row['application_status'] ?? '') : '');
            if ($resource === 'students') {
                $body .= '<tr><td>' . $identity . '</td><td>' . self::e($row['phone'] ?? 'Not supplied') . '</td><td>' . (int) ($row['active_enrollments'] ?? 0) . ' active / ' . (int) ($row['enrollments'] ?? 0) . ' total</td><td>' . self::badge($status) . '</td><td>' . self::e($row['created_at'] ?? '') . '</td><td>' . $action . '</td></tr>';
            } elseif ($resource === 'instructors') {
                $body .= '<tr><td>' . $identity . '</td><td>' . self::badge((string) ($row['application_status'] ?? 'none')) . '</td><td>' . (int) ($row['published_courses'] ?? 0) . ' published / ' . (int) ($row['courses'] ?? 0) . ' total</td><td>' . (int) ($row['students'] ?? 0) . '</td><td>' . self::badge($status) . '</td><td>' . $action . '</td></tr>';
            } else {
                $body .= '<tr><td>' . $identity . '</td><td>' . self::e(ucfirst((string) ($row['role'] ?? ''))) . '</td><td>' . self::e($row['last_login_at'] ?? 'Never') . '</td><td>' . self::badge($status) . '</td><td>' . self::e($row['created_at'] ?? '') . '</td><td>' . $action . '</td></tr>';
            }
        }
        $headers = match ($resource) {
            'students' => ['Student', 'Phone', 'Enrollments', 'Status', 'Joined', 'Action'],
            'instructors' => ['Instructor', 'Application', 'Courses', 'Students', 'Status', 'Action'],
            default => ['User', 'Role', 'Last login', 'Status', 'Joined', 'Action'],
        };
        return $html . self::table($headers, $body, count($headers), 'No accounts match these filters.');
    }

    private static function statusForm(int $id, string $status, bool $disabled, string $applicationStatus = ''): string
    {
        if ($disabled) {
            return '<span class="muted-copy">Current Admin</span>';
        }
        if ($applicationStatus === 'pending' || ($status === 'inactive' && $applicationStatus !== 'approved' && $applicationStatus !== '')) {
            return '<a class="text-button" href="/admin/instructor-approvals">Use approval queue</a>';
        }
        $target = $status === 'blocked' ? 'active' : 'blocked';
        return '<form class="admin-inline-action" method="post">' . Csrf::field() . '<input type="hidden" name="action" value="set_status"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="status" value="' . $target . '"><button class="text-button' . ($target === 'blocked' ? ' danger-text' : '') . '" type="submit">' . ($target === 'blocked' ? 'Block access' : 'Activate') . '</button></form>';
    }

    private static function categories(array $model): string
    {
        $html = self::toolbar($model, ['active' => 'Active', 'inactive' => 'Inactive']);
        $html .= '<details class="admin-create-panel"><summary>+ Create category</summary><form method="post" class="admin-create-form">' . Csrf::field() . '<input type="hidden" name="action" value="create"><label>Name<input name="name" maxlength="100" required></label><label>Slug<input name="slug" maxlength="120" placeholder="Generated from name when blank"></label><label class="wide">Description<textarea name="description" rows="3" maxlength="2000"></textarea></label><button class="portal-button" type="submit">Create category</button></form></details>';
        $body = '';
        foreach (self::rows($model) as $row) {
            $status = (string) ($row['status'] ?? 'inactive');
            $target = $status === 'active' ? 'inactive' : 'active';
            $body .= '<tr><td><strong>' . self::e($row['name'] ?? '') . '</strong><small>' . self::e($row['description'] ?? '') . '</small></td><td>' . self::e($row['slug'] ?? '') . '</td><td>' . (int) ($row['courses'] ?? 0) . '</td><td>' . self::badge($status) . '</td><td>' . self::e($row['updated_at'] ?? '') . '</td><td><form method="post">' . Csrf::field() . '<input type="hidden" name="action" value="set_status"><input type="hidden" name="id" value="' . (int) ($row['id'] ?? 0) . '"><input type="hidden" name="status" value="' . $target . '"><button class="text-button" type="submit">Set ' . $target . '</button></form></td></tr>';
        }
        return $html . self::table(['Category', 'Slug', 'Courses', 'Status', 'Updated', 'Action'], $body, 6, 'No categories match these filters.');
    }

    private static function refunds(array $model): string
    {
        $html = self::toolbar($model, ['paid' => 'Refundable paid', 'refunded' => 'Refunded']);
        $body = '';
        foreach (self::rows($model) as $row) {
            $status = (string) ($row['payment_status'] ?? 'paid');
            if ($status === 'refunded') {
                $action = '<span class="muted-copy">Completed</span>';
            } elseif ((int) ($row['paid_earnings'] ?? 0) > 0) {
                $action = '<span class="muted-copy">Reconcile paid payout first</span>';
            } else {
                $action = '<form class="admin-refund-form" method="post">' . Csrf::field() . '<input type="hidden" name="action" value="refund"><input type="hidden" name="id" value="' . (int) ($row['payment_id'] ?? 0) . '"><input name="reason" maxlength="1000" placeholder="Refund reason" required><button class="text-button danger-text" type="submit">Refund</button></form>';
            }
            $body .= '<tr><td>#' . (int) ($row['payment_id'] ?? 0) . '<small>Order #' . (int) ($row['order_id'] ?? 0) . '</small></td><td><strong>' . self::e($row['student_name'] ?? '') . '</strong><small>' . self::e($row['student_email'] ?? '') . '</small></td><td>' . self::e($row['payment_method'] ?? '') . '<small>' . self::e($row['transaction_id'] ?? '') . '</small></td><td>NPR ' . number_format((float) ($row['paid_amount'] ?? 0), 2) . '</td><td>' . self::badge($status) . '</td><td>' . $action . '</td></tr>';
        }
        return $html . self::table(['Payment', 'Student', 'Transaction', 'Amount', 'Status', 'Action'], $body, 6, 'No refundable or refunded payments match these filters.');
    }

    private static function coupons(array $model): string
    {
        $html = self::toolbar($model, ['active' => 'Active', 'inactive' => 'Inactive', 'expired' => 'Expired']);
        $html .= '<details class="admin-create-panel"><summary>+ Create coupon</summary><form method="post" class="admin-create-form">' . Csrf::field() . '<input type="hidden" name="action" value="create"><label>Code<input name="code" maxlength="50" pattern="[A-Za-z0-9_-]{3,50}" required></label><label>Type<select name="discount_type"><option value="percent">Percent</option><option value="fixed">Fixed</option></select></label><label>Value<input type="number" name="discount_value" min="0.01" step="0.01" required></label><label>Minimum order<input type="number" name="min_order_amount" min="0" step="0.01" value="0"></label><label>Maximum discount<input type="number" name="max_discount" min="0" step="0.01"></label><label>Usage limit<input type="number" name="usage_limit" min="1"></label><label>Valid from<input type="datetime-local" name="valid_from"></label><label>Valid until<input type="datetime-local" name="valid_until"></label><button class="portal-button" type="submit">Create coupon</button></form></details>';
        $body = '';
        foreach (self::rows($model) as $row) {
            $status = (string) ($row['status'] ?? 'inactive');
            $target = $status === 'active' ? 'inactive' : 'active';
            $value = (string) ($row['discount_type'] ?? '') === 'percent'
                ? number_format((float) ($row['discount_value'] ?? 0), 2) . '%'
                : 'NPR ' . number_format((float) ($row['discount_value'] ?? 0), 2);
            $body .= '<tr><td><strong>' . self::e($row['code'] ?? '') . '</strong><small>Created by ' . self::e($row['creator_name'] ?? 'System') . '</small></td><td>' . $value . '</td><td>' . (int) ($row['used_count'] ?? 0) . ' / ' . self::e($row['usage_limit'] ?? '∞') . '</td><td>' . self::e($row['valid_until'] ?? 'No expiry') . '</td><td>' . self::badge($status) . '</td><td><form method="post">' . Csrf::field() . '<input type="hidden" name="action" value="set_status"><input type="hidden" name="id" value="' . (int) ($row['id'] ?? 0) . '"><input type="hidden" name="status" value="' . $target . '"><button class="text-button" type="submit">Set ' . $target . '</button></form></td></tr>';
        }
        return $html . self::table(['Coupon', 'Discount', 'Usage', 'Expires', 'Status', 'Action'], $body, 6, 'No coupons match these filters.');
    }

    private static function reports(array $model): string
    {
        $data = is_array($model['data'] ?? null) ? $model['data'] : [];
        $summary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
        $monthly = is_array($data['monthly'] ?? null) ? $data['monthly'] : [];
        $topCourses = is_array($data['top_courses'] ?? null) ? $data['top_courses'] : [];
        $strip = '<div class="admin-summary-strip"><span><small>Users</small><strong>' . (int) ($summary['users'] ?? 0) . '</strong></span><span><small>Published courses</small><strong>' . (int) ($summary['published_courses'] ?? 0) . '</strong></span><span><small>Active enrollments</small><strong>' . (int) ($summary['active_enrollments'] ?? 0) . '</strong></span><span><small>Verified revenue</small><strong>NPR ' . number_format((float) ($summary['verified_revenue'] ?? 0), 2) . '</strong></span><span><small>Refunded</small><strong>NPR ' . number_format((float) ($summary['refunded_value'] ?? 0), 2) . '</strong></span><span><small>Instructor payouts</small><strong>NPR ' . number_format((float) ($summary['instructor_payments'] ?? 0), 2) . '</strong></span></div>';
        $monthlyRows = '';
        foreach ($monthly as $row) {
            $monthlyRows .= '<tr><td>' . self::e($row['month'] ?? '') . '</td><td>' . (int) ($row['payments'] ?? 0) . '</td><td>NPR ' . number_format((float) ($row['revenue'] ?? 0), 2) . '</td></tr>';
        }
        $courseRows = '';
        foreach ($topCourses as $row) {
            $courseRows .= '<tr><td><strong>' . self::e($row['title'] ?? '') . '</strong><small>' . self::e($row['instructor_name'] ?? '') . '</small></td><td>' . (int) ($row['enrollments'] ?? 0) . '</td><td>NPR ' . number_format((float) ($row['sales_value'] ?? 0), 2) . '</td></tr>';
        }
        return $strip . '<div class="admin-report-sections"><section><h3>Monthly paid revenue</h3>' . self::table(['Month', 'Payments', 'Revenue'], $monthlyRows, 3, 'No paid revenue has been recorded.') . '</section><section><h3>Top courses</h3>' . self::table(['Course', 'Enrollments', 'Sales value'], $courseRows, 3, 'No course sales have been recorded.') . '</section></div>';
    }

    private static function auditLogs(array $model): string
    {
        $html = self::toolbar($model);
        $body = '';
        foreach (self::rows($model) as $row) {
            $body .= '<tr><td><strong>' . self::e($row['event'] ?? '') . '</strong><small>' . self::e($row['context'] ?? '') . '</small></td><td>' . self::e($row['actor'] ?? '') . '</td><td>' . self::e($row['resource'] ?? '') . '</td><td>' . self::e($row['created_at'] ?? '') . '</td></tr>';
        }
        return $html . self::table(['Event', 'Actor', 'Resource', 'Date'], $body, 4, 'No recorded Admin activity matches this search.');
    }

    private static function security(array $model): string
    {
        $data = is_array($model['data'] ?? null) ? $model['data'] : [];
        $summary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
        $sessions = is_array($data['sessions'] ?? null) ? $data['sessions'] : [];
        $attempts = is_array($data['attempts'] ?? null) ? $data['attempts'] : [];
        $strip = '<div class="admin-summary-strip"><span><small>Active sessions</small><strong>' . (int) ($summary['active_sessions'] ?? 0) . '</strong></span><span><small>Admin sessions</small><strong>' . (int) ($summary['admin_sessions'] ?? 0) . '</strong></span><span><small>Active login locks</small><strong>' . (int) ($summary['locked_attempts'] ?? 0) . '</strong></span></div>';
        $sessionRows = '';
        foreach ($sessions as $row) {
            $status = (string) ($row['status'] ?? 'closed');
            $action = $status === 'active' ? '<form method="post">' . Csrf::field() . '<input type="hidden" name="action" value="revoke_session"><input type="hidden" name="id" value="' . (int) ($row['id'] ?? 0) . '"><button class="text-button danger-text" type="submit">Revoke</button></form>' : '<span class="muted-copy">Closed</span>';
            $sessionRows .= '<tr><td><strong>' . self::e($row['full_name'] ?? '') . '</strong><small>' . self::e($row['email'] ?? '') . '</small></td><td>' . self::e($row['portal'] ?? '') . '</td><td>' . self::e($row['created_at'] ?? '') . '</td><td>' . self::e($row['expires_at'] ?? '') . '</td><td>' . self::badge($status) . '</td><td>' . $action . '</td></tr>';
        }
        $attemptRows = '';
        foreach ($attempts as $row) {
            $status = (string) ($row['status'] ?? 'clear');
            $action = $status === 'locked' ? '<form method="post">' . Csrf::field() . '<input type="hidden" name="action" value="clear_login_lock"><input type="hidden" name="id" value="' . (int) ($row['id'] ?? 0) . '"><button class="text-button" type="submit">Clear lock</button></form>' : '<span class="muted-copy">No action</span>';
            $attemptRows .= '<tr><td><code>' . self::e(self::shortHash((string) ($row['email_hash'] ?? ''))) . '</code></td><td><code>' . self::e(self::shortHash((string) ($row['ip_hash'] ?? ''))) . '</code></td><td>' . (int) ($row['attempts'] ?? 0) . '</td><td>' . self::e($row['locked_until'] ?? '—') . '</td><td>' . self::badge($status) . '</td><td>' . $action . '</td></tr>';
        }
        return $strip . '<div class="admin-security-sections"><section><h3>Identity sessions</h3>' . self::table(['Account', 'Portal', 'Created', 'Expires', 'Status', 'Action'], $sessionRows, 6, 'No identity sessions recorded.') . '</section><section><h3>Login attempts</h3>' . self::table(['Email hash', 'IP hash', 'Attempts', 'Locked until', 'Status', 'Action'], $attemptRows, 6, 'No login-attempt records.') . '</section></div>';
    }

    private static function settings(array $model): string
    {
        $data = is_array($model['data'] ?? null) ? $model['data'] : [];
        $values = is_array($data['values'] ?? null) ? $data['values'] : [];
        $fields = [
            'site_name' => ['Platform name', 'text'],
            'site_email' => ['Support email', 'email'],
            'site_phone' => ['Support phone', 'text'],
            'site_address' => ['Platform address', 'text'],
            'platform_commission_rate' => ['Commission rate (%)', 'number'],
            'esewa_id' => ['eSewa account ID', 'text'],
            'khalti_id' => ['Khalti account ID', 'text'],
            'bank_name' => ['Bank name', 'text'],
            'bank_account_name' => ['Bank account name', 'text'],
            'bank_account_number' => ['Bank account number', 'text'],
            'terms_url' => ['Terms URL', 'url'],
            'privacy_url' => ['Privacy URL', 'url'],
        ];
        $inputs = '';
        foreach ($fields as $key => [$label, $type]) {
            $extra = $key === 'platform_commission_rate' ? ' min="0" max="100" step="0.01"' : '';
            $inputs .= '<label>' . self::e($label) . '<input type="' . self::e($type) . '" name="values[' . self::e($key) . ']" value="' . self::e($values[$key] ?? '') . '" maxlength="500"' . $extra . '></label>';
        }
        return '<form class="admin-settings-form" method="post">' . Csrf::field() . '<input type="hidden" name="action" value="save"><div class="admin-settings-grid">' . $inputs . '<label class="wide">Payment instructions<textarea name="values[payment_instructions]" rows="5" maxlength="3000">' . self::e($values['payment_instructions'] ?? '') . '</textarea></label></div><div class="admin-settings-submit"><button class="portal-button" type="submit">Save platform settings</button><p>Secrets such as SMTP passwords and Admin access codes remain in <code>.env</code> and are intentionally not editable here.</p></div></form>';
    }

    private static function table(array $headers, string $rows, int $colspan, string $emptyMessage): string
    {
        $head = '';
        foreach ($headers as $header) {
            $head .= '<th>' . self::e($header) . '</th>';
        }
        if ($rows === '') {
            $rows = '<tr class="empty-row"><td colspan="' . $colspan . '"><div><strong>' . self::e($emptyMessage) . '</strong></div></td></tr>';
        }
        return '<div class="admin-console-table"><div class="table-wrap"><table><thead><tr>' . $head . '</tr></thead><tbody>' . $rows . '</tbody></table></div></div>';
    }

    private static function rows(array $model): array
    {
        return is_array($model['data'] ?? null) && array_is_list($model['data']) ? $model['data'] : [];
    }

    private static function badge(string $status): string
    {
        $status = strtolower(trim($status));
        $class = preg_replace('/[^a-z0-9-]/', '-', $status) ?: 'unknown';
        return '<span class="status-badge ' . self::e($class) . '">' . self::e($status !== '' ? ucfirst(str_replace('_', ' ', $status)) : 'Unknown') . '</span>';
    }

    private static function shortHash(string $hash): string
    {
        return mb_strlen($hash) > 16 ? mb_substr($hash, 0, 8) . '…' . mb_substr($hash, -6) : $hash;
    }

    private static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
