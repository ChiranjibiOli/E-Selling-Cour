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
$respond = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
};
$jsonInput = static function (): array {
    $raw = (string) file_get_contents('php://input');
    if ($raw === '') { return []; }
    $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) { throw new InvalidArgumentException('Request body must be a JSON object.'); }
    return $decoded;
};

try {
    $database = Database::connect();

    if ($path === '/health' && $method === 'GET') {
        $database->query('SELECT 1');
        $respond(['status' => 'ok', 'service' => 'reporting-service']);
    }

    if ($path === '/api/v1/reports/admin-dashboard' && $method === 'GET') {
        ServiceAuth::requireUser($database, $authorization, 'admin');
        $metrics = [
            'students' => (int) $database->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn(),
            'instructors' => (int) $database->query("SELECT COUNT(*) FROM users WHERE role='instructor'")->fetchColumn(),
            'pending_instructors' => (int) $database->query("SELECT COUNT(*) FROM instructor_applications WHERE application_status='pending'")->fetchColumn(),
            'published_courses' => (int) $database->query("SELECT COUNT(*) FROM courses WHERE status='published'")->fetchColumn(),
            'pending_courses' => (int) $database->query("SELECT COUNT(*) FROM courses WHERE status='pending'")->fetchColumn(),
            'active_enrollments' => (int) $database->query("SELECT COUNT(*) FROM enrollments WHERE status='active'")->fetchColumn(),
            'pending_payments' => (int) $database->query("SELECT COUNT(*) FROM payments WHERE payment_status='pending'")->fetchColumn(),
            'verified_revenue' => (string) ($database->query("SELECT COALESCE(SUM(paid_amount),0) FROM payments WHERE payment_status='paid'")->fetchColumn() ?: '0'),
            'platform_earnings' => (string) ($database->query("SELECT COALESCE(SUM(commission_amount),0) FROM instructor_earnings WHERE earning_status NOT IN ('cancelled','refunded')")->fetchColumn() ?: '0'),
            'pending_withdrawals' => (int) $database->query("SELECT COUNT(*) FROM withdrawal_requests WHERE request_status IN ('pending','approved')")->fetchColumn(),
            'new_messages' => (int) $database->query("SELECT COUNT(*) FROM contact_messages WHERE status='new'")->fetchColumn(),
        ];
        $recent = $database->query("SELECT o.id,o.final_amount,o.order_status,o.created_at,u.full_name AS student_name,COUNT(oi.id) AS item_count FROM orders o INNER JOIN users u ON u.id=o.student_id LEFT JOIN order_items oi ON oi.order_id=o.id GROUP BY o.id ORDER BY o.created_at DESC LIMIT 10")->fetchAll();
        $respond(['data' => ['metrics' => $metrics, 'recent_orders' => $recent]]);
    }

    if ($path === '/api/v1/reports/instructor-dashboard' && $method === 'GET') {
        $instructor = ServiceAuth::requireUser($database, $authorization, 'instructor');
        $courseCounts = $database->prepare('SELECT status,COUNT(*) AS count FROM courses WHERE instructor_id=:id GROUP BY status');
        $courseCounts->execute(['id' => $instructor['id']]);
        $statusCounts = [];
        foreach ($courseCounts->fetchAll() as $row) { $statusCounts[(string) $row['status']] = (int) $row['count']; }
        $statement = $database->prepare("SELECT COUNT(DISTINCT e.student_id) AS students,COUNT(DISTINCT e.id) AS enrollments,COALESCE(SUM(CASE WHEN ie.earning_status NOT IN ('cancelled','refunded') THEN ie.gross_amount ELSE 0 END),0) AS gross_sales,COALESCE(SUM(CASE WHEN ie.earning_status='available' THEN ie.instructor_amount ELSE 0 END),0) AS available_earnings,COALESCE(SUM(CASE WHEN ie.earning_status='withdraw_requested' THEN ie.instructor_amount ELSE 0 END),0) AS reserved_earnings,COALESCE(SUM(CASE WHEN ie.earning_status='paid' THEN ie.instructor_amount ELSE 0 END),0) AS paid_earnings FROM courses c LEFT JOIN enrollments e ON e.course_id=c.id AND e.status='active' LEFT JOIN instructor_earnings ie ON ie.course_id=c.id WHERE c.instructor_id=:id");
        $statement->execute(['id' => $instructor['id']]);
        $respond(['data' => ['courses' => $statusCounts, 'business' => $statement->fetch() ?: []]]);
    }

    if ($path === '/api/v1/reports/instructor-sales' && $method === 'GET') {
        $instructor = ServiceAuth::requireUser($database, $authorization, 'instructor');
        $statement = $database->prepare('SELECT ie.id,ie.course_id,c.title AS course_title,s.full_name AS student_name,ie.order_id,ie.gross_amount,ie.commission_rate,ie.commission_amount,ie.instructor_amount,ie.earning_status,ie.created_at FROM instructor_earnings ie INNER JOIN courses c ON c.id=ie.course_id INNER JOIN users s ON s.id=ie.student_id WHERE ie.instructor_id=:id ORDER BY ie.created_at DESC LIMIT 500');
        $statement->execute(['id' => $instructor['id']]);
        $respond(['data' => $statement->fetchAll()]);
    }

    if ($path === '/api/v1/reports/payout-details' && $method === 'GET') {
        $instructor = ServiceAuth::requireUser($database, $authorization, 'instructor');
        $statement = $database->prepare('SELECT bank_name,account_name,account_number,branch_name,esewa_number,khalti_number,qr_image,updated_at FROM instructor_bank_details WHERE instructor_id=:id LIMIT 1');
        $statement->execute(['id' => $instructor['id']]);
        $respond(['data' => $statement->fetch() ?: []]);
    }

    if ($path === '/api/v1/reports/payout-details' && $method === 'POST') {
        $instructor = ServiceAuth::requireUser($database, $authorization, 'instructor');
        $input = $jsonInput();
        $fields = [];
        foreach (['bank_name'=>150,'account_name'=>150,'account_number'=>100,'branch_name'=>150,'esewa_number'=>30,'khalti_number'=>30,'qr_image'=>255] as $field => $limit) {
            $value = trim((string) ($input[$field] ?? ''));
            if (mb_strlen($value) > $limit) { throw new InvalidArgumentException('One or more payout-detail fields are too long.'); }
            $fields[$field] = $value !== '' ? ($field === 'qr_image' ? basename($value) : $value) : null;
        }
        if ($fields['account_number'] === null && $fields['esewa_number'] === null && $fields['khalti_number'] === null) { throw new InvalidArgumentException('Add at least one bank or wallet payout destination.'); }
        $statement = $database->prepare('INSERT INTO instructor_bank_details (instructor_id,bank_name,account_name,account_number,branch_name,esewa_number,khalti_number,qr_image) VALUES (:instructor_id,:bank_name,:account_name,:account_number,:branch_name,:esewa_number,:khalti_number,:qr_image) ON DUPLICATE KEY UPDATE bank_name=VALUES(bank_name),account_name=VALUES(account_name),account_number=VALUES(account_number),branch_name=VALUES(branch_name),esewa_number=VALUES(esewa_number),khalti_number=VALUES(khalti_number),qr_image=VALUES(qr_image)');
        $statement->execute($fields + ['instructor_id' => $instructor['id']]);
        $respond(['message' => 'Private payout details saved.']);
    }

    if ($path === '/api/v1/reports/withdrawals/mine' && $method === 'GET') {
        $instructor = ServiceAuth::requireUser($database, $authorization, 'instructor');
        $balance = $database->prepare("SELECT COALESCE(SUM(instructor_amount),0) FROM instructor_earnings WHERE instructor_id=:id AND earning_status='available'");
        $balance->execute(['id' => $instructor['id']]);
        $requests = $database->prepare('SELECT * FROM withdrawal_requests WHERE instructor_id=:id ORDER BY requested_at DESC');
        $requests->execute(['id' => $instructor['id']]);
        $respond(['data' => ['available_balance' => (string) $balance->fetchColumn(), 'requests' => $requests->fetchAll()]]);
    }

    if ($path === '/api/v1/reports/withdrawals' && $method === 'POST') {
        $instructor = ServiceAuth::requireUser($database, $authorization, 'instructor');
        $input = $jsonInput();
        $paymentMethod = strtolower(trim((string) ($input['payment_method'] ?? 'bank')));
        $note = trim((string) ($input['note'] ?? ''));
        if (!in_array($paymentMethod, ['bank','esewa','khalti'], true) || mb_strlen($note) > 1000) { throw new InvalidArgumentException('Choose a valid payout method and note.'); }
        $database->beginTransaction();
        $details = $database->prepare('SELECT * FROM instructor_bank_details WHERE instructor_id=:id FOR UPDATE');
        $details->execute(['id' => $instructor['id']]);
        $destination = $details->fetch();
        if (!is_array($destination)) { throw new InvalidArgumentException('Save payout details before requesting a withdrawal.'); }
        if ($paymentMethod === 'bank' && trim((string) ($destination['account_number'] ?? '')) === '') { throw new InvalidArgumentException('A bank account is required for bank withdrawal.'); }
        if ($paymentMethod === 'esewa' && trim((string) ($destination['esewa_number'] ?? '')) === '') { throw new InvalidArgumentException('An eSewa number is required.'); }
        if ($paymentMethod === 'khalti' && trim((string) ($destination['khalti_number'] ?? '')) === '') { throw new InvalidArgumentException('A Khalti number is required.'); }
        $earnings = $database->prepare("SELECT id,instructor_amount FROM instructor_earnings WHERE instructor_id=:id AND earning_status='available' ORDER BY created_at,id FOR UPDATE");
        $earnings->execute(['id' => $instructor['id']]);
        $available = $earnings->fetchAll();
        $amount = array_reduce($available, static fn(float $sum, array $row): float => $sum + (float) $row['instructor_amount'], 0.0);
        if ($amount <= 0 || $available === []) { throw new InvalidArgumentException('No verified available earnings can be withdrawn.'); }
        $request = $database->prepare("INSERT INTO withdrawal_requests (instructor_id,requested_amount,payment_method,account_name,account_number,bank_name,esewa_number,khalti_number,request_status,instructor_note) VALUES (:instructor_id,:amount,:method,:account_name,:account_number,:bank_name,:esewa_number,:khalti_number,'pending',:note)");
        $request->execute(['instructor_id'=>$instructor['id'],'amount'=>number_format($amount,2,'.',''),'method'=>$paymentMethod,'account_name'=>$destination['account_name'],'account_number'=>$destination['account_number'],'bank_name'=>$destination['bank_name'],'esewa_number'=>$destination['esewa_number'],'khalti_number'=>$destination['khalti_number'],'note'=>$note!==''?$note:null]);
        $requestId = (int) $database->lastInsertId();
        $map = $database->prepare('INSERT INTO withdrawal_request_earnings (withdrawal_request_id,earning_id) VALUES (:request_id,:earning_id)');
        $reserve = $database->prepare("UPDATE instructor_earnings SET earning_status='withdraw_requested' WHERE id=:id AND earning_status='available'");
        foreach ($available as $earning) {
            $map->execute(['request_id'=>$requestId,'earning_id'=>(int)$earning['id']]);
            $reserve->execute(['id'=>(int)$earning['id']]);
        }
        $notify = $database->prepare("INSERT INTO notifications (user_id,title,message,notification_type) SELECT id,'Withdrawal needs review',:message,'withdrawal_request' FROM users WHERE role='admin' AND status='active'");
        $notify->execute(['message'=>'Instructor withdrawal #' . $requestId . ' requires review.']);
        $database->commit();
        $respond(['message'=>'All currently available earnings were reserved in withdrawal request #' . $requestId . '.','id'=>$requestId,'amount'=>number_format($amount,2,'.','')],201);
    }

    if ($path === '/api/v1/reports/withdrawals/pending' && $method === 'GET') {
        ServiceAuth::requireUser($database, $authorization, 'admin');
        $statement = $database->query("SELECT wr.*,u.full_name AS instructor_name,u.email AS instructor_email FROM withdrawal_requests wr INNER JOIN users u ON u.id=wr.instructor_id WHERE wr.request_status IN ('pending','approved') ORDER BY wr.requested_at ASC");
        $respond(['data' => $statement->fetchAll()]);
    }

    if (preg_match('#^/api/v1/reports/withdrawals/(\d+)/(approve|reject|paid)$#', $path, $matches) === 1 && $method === 'POST') {
        $admin = ServiceAuth::requireUser($database, $authorization, 'admin');
        $requestId = (int) $matches[1];
        $action = $matches[2];
        $input = $jsonInput();
        $note = trim((string) ($input['note'] ?? ''));
        $reference = trim((string) ($input['transaction_reference'] ?? ''));
        if (mb_strlen($note) > 1000 || mb_strlen($reference) > 150) { throw new InvalidArgumentException('Payout note or reference is too long.'); }
        if ($action === 'reject' && $note === '') { throw new InvalidArgumentException('A rejection reason is required.'); }
        if ($action === 'paid' && ($reference === '' || preg_match('/^[A-Za-z0-9._:\/-]{4,150}$/', $reference) !== 1)) { throw new InvalidArgumentException('A valid payout transaction reference is required.'); }
        $database->beginTransaction();
        $statement = $database->prepare('SELECT * FROM withdrawal_requests WHERE id=:id FOR UPDATE');
        $statement->execute(['id' => $requestId]);
        $withdrawal = $statement->fetch();
        if (!is_array($withdrawal)) { $respond(['error'=>'Withdrawal request not found.'],404); }
        if ($action === 'approve' && $withdrawal['request_status'] !== 'pending') { throw new ServiceAuthorizationException('Only a pending withdrawal can be approved.'); }
        if ($action === 'reject' && !in_array($withdrawal['request_status'], ['pending','approved'], true)) { throw new ServiceAuthorizationException('This withdrawal cannot be rejected.'); }
        if ($action === 'paid' && $withdrawal['request_status'] !== 'approved') { throw new ServiceAuthorizationException('Approve the withdrawal before recording payment.'); }
        if ($action === 'approve') {
            $update = $database->prepare("UPDATE withdrawal_requests SET request_status='approved',admin_note=:note,processed_by=:admin,processed_at=NOW() WHERE id=:id");
            $update->execute(['note'=>$note!==''?$note:null,'admin'=>$admin['id'],'id'=>$requestId]);
            $title='Withdrawal approved'; $body='Your withdrawal request #' . $requestId . ' was approved for payment.';
        } elseif ($action === 'reject') {
            $update = $database->prepare("UPDATE withdrawal_requests SET request_status='rejected',admin_note=:note,processed_by=:admin,processed_at=NOW() WHERE id=:id");
            $update->execute(['note'=>$note,'admin'=>$admin['id'],'id'=>$requestId]);
            $release = $database->prepare("UPDATE instructor_earnings ie INNER JOIN withdrawal_request_earnings wre ON wre.earning_id=ie.id SET ie.earning_status='available' WHERE wre.withdrawal_request_id=:id AND ie.earning_status='withdraw_requested'");
            $release->execute(['id'=>$requestId]);
            $title='Withdrawal rejected'; $body='Your withdrawal request #' . $requestId . ' was rejected. ' . $note;
        } else {
            $payout = $database->prepare("INSERT INTO payouts (withdrawal_request_id,payout_source,instructor_id,paid_amount,payment_method,transaction_reference,payout_status,paid_by,admin_note,paid_at) VALUES (:request_id,'withdrawal',:instructor_id,:amount,:method,:reference,'paid',:admin,:note,NOW())");
            $payout->execute(['request_id'=>$requestId,'instructor_id'=>(int)$withdrawal['instructor_id'],'amount'=>$withdrawal['requested_amount'],'method'=>$withdrawal['payment_method'],'reference'=>$reference,'admin'=>$admin['id'],'note'=>$note!==''?$note:null]);
            $update = $database->prepare("UPDATE withdrawal_requests SET request_status='paid',admin_note=:note,processed_by=:admin,processed_at=NOW() WHERE id=:id");
            $update->execute(['note'=>$note!==''?$note:null,'admin'=>$admin['id'],'id'=>$requestId]);
            $paid = $database->prepare("UPDATE instructor_earnings ie INNER JOIN withdrawal_request_earnings wre ON wre.earning_id=ie.id SET ie.earning_status='paid',ie.paid_at=NOW() WHERE wre.withdrawal_request_id=:id AND ie.earning_status='withdraw_requested'");
            $paid->execute(['id'=>$requestId]);
            $title='Withdrawal paid'; $body='Your withdrawal request #' . $requestId . ' was paid. Reference: ' . $reference;
        }
        $notify = $database->prepare("INSERT INTO notifications (user_id,title,message,notification_type) VALUES (:user_id,:title,:message,'withdrawal_update')");
        $notify->execute(['user_id'=>(int)$withdrawal['instructor_id'],'title'=>$title,'message'=>$body]);
        $database->commit();
        $respond(['message'=>$title . '.']);
    }

    $respond(['error' => 'Reporting route not found.'], 404);
} catch (ServiceAuthenticationException $exception) {
    $respond(['error' => $exception->getMessage()], 401);
} catch (ServiceAuthorizationException $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) { $database->rollBack(); }
    $respond(['error' => $exception->getMessage()], 403);
} catch (InvalidArgumentException $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) { $database->rollBack(); }
    $respond(['error' => $exception->getMessage()], 422);
} catch (JsonException) {
    $respond(['error' => 'Malformed JSON request.'], 400);
} catch (PDOException $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) { $database->rollBack(); }
    error_log('Reporting database failure: ' . $exception->getMessage());
    $respond(['error' => 'Reporting request could not be completed.'], 409);
} catch (Throwable $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) { $database->rollBack(); }
    error_log('Reporting service failure: ' . $exception->getMessage());
    $respond(['error' => 'Reporting service is unavailable.'], 503);
}
