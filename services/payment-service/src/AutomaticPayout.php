<?php

declare(strict_types=1);

final class AutomaticPayout
{
    /**
     * Queue the net Instructor earnings created by a verified payment and, when
     * a provider-approved disbursement API is configured, attempt the transfer.
     * Payment/enrollment success is never rolled back merely because payout is
     * unavailable. Failed transfers stay approved for Admin settlement.
     *
     * @return array<string,mixed>
     */
    public static function settleForPayment(PDO $database, int $paymentId): array
    {
        if ($paymentId < 1) {
            return ['status' => 'skipped', 'reason' => 'invalid_payment'];
        }

        try {
            $queued = self::queue($database, $paymentId);
            if ($queued === []) {
                return ['status' => 'already_queued_or_empty', 'payment_id' => $paymentId, 'payouts' => []];
            }

            $results = [];
            foreach ($queued as $payout) {
                $results[] = self::dispatch($database, $payout);
            }

            return [
                'status' => 'processed',
                'payment_id' => $paymentId,
                'payouts' => $results,
            ];
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            error_log('Automatic payout preparation failed for payment #' . $paymentId . ': ' . $exception->getMessage());
            return [
                'status' => 'queue_failed',
                'payment_id' => $paymentId,
                'message' => 'Instructor payout could not be queued automatically.',
            ];
        }
    }

    /** @return array<int,array<string,mixed>> */
    private static function queue(PDO $database, int $paymentId): array
    {
        $database->beginTransaction();
        $statement = $database->prepare(
            'SELECT ie.id,ie.instructor_id,ie.order_id,ie.payment_id,ie.instructor_amount,'
            . 'd.bank_name,d.account_name,d.account_number,d.branch_name,d.esewa_number,d.khalti_number '
            . 'FROM instructor_earnings ie '
            . 'LEFT JOIN instructor_bank_details d ON d.instructor_id=ie.instructor_id '
            . 'WHERE ie.payment_id=:payment_id AND ie.earning_status=\'available\' '
            . 'AND NOT EXISTS (SELECT 1 FROM withdrawal_request_earnings wre WHERE wre.earning_id=ie.id) '
            . 'ORDER BY ie.instructor_id,ie.id FOR UPDATE'
        );
        $statement->execute(['payment_id' => $paymentId]);
        $rows = $statement->fetchAll();
        if ($rows === []) {
            $database->commit();
            return [];
        }

        $groups = [];
        foreach ($rows as $row) {
            $instructorId = (int) $row['instructor_id'];
            if (!isset($groups[$instructorId])) {
                $groups[$instructorId] = [
                    'instructor_id' => $instructorId,
                    'order_id' => (int) $row['order_id'],
                    'payment_id' => (int) $row['payment_id'],
                    'amount' => 0.0,
                    'earning_ids' => [],
                    'details' => $row,
                ];
            }
            $groups[$instructorId]['amount'] += (float) $row['instructor_amount'];
            $groups[$instructorId]['earning_ids'][] = (int) $row['id'];
        }

        $create = $database->prepare(
            'INSERT INTO withdrawal_requests '
            . '(instructor_id,requested_amount,payment_method,account_name,account_number,bank_name,esewa_number,khalti_number,request_status,instructor_note,admin_note,processed_at) '
            . 'VALUES (:instructor_id,:amount,:method,:account_name,:account_number,:bank_name,:esewa_number,:khalti_number,\'approved\',:instructor_note,:admin_note,NOW())'
        );
        $map = $database->prepare('INSERT INTO withdrawal_request_earnings (withdrawal_request_id,earning_id) VALUES (:request_id,:earning_id)');
        $reserve = $database->prepare("UPDATE instructor_earnings SET earning_status='withdraw_requested' WHERE id=:id AND earning_status='available'");
        $queued = [];

        foreach ($groups as $group) {
            $destination = self::destination((array) $group['details']);
            if ($destination === null) {
                self::notifyInstructor(
                    $database,
                    (int) $group['instructor_id'],
                    'Payout details required',
                    'Verified earnings are available, but CourseHub could not queue an automatic payout until you save a bank, eSewa or Khalti destination.'
                );
                self::notifyAdmins(
                    $database,
                    'Instructor payout needs details',
                    'Verified payment #' . $paymentId . ' created Instructor earnings, but Instructor #' . (int) $group['instructor_id'] . ' has no usable payout destination.'
                );
                continue;
            }

            $amount = number_format(round((float) $group['amount'], 2), 2, '.', '');
            $create->execute([
                'instructor_id' => (int) $group['instructor_id'],
                'amount' => $amount,
                'method' => $destination['method'],
                'account_name' => $destination['account_name'],
                'account_number' => $destination['account_number'],
                'bank_name' => $destination['bank_name'],
                'esewa_number' => $destination['esewa_number'],
                'khalti_number' => $destination['khalti_number'],
                'instructor_note' => 'Automatic payout generated from verified payment #' . $paymentId . '.',
                'admin_note' => 'Waiting for configured payout API or Admin settlement.',
            ]);
            $requestId = (int) $database->lastInsertId();
            foreach ((array) $group['earning_ids'] as $earningId) {
                $map->execute(['request_id' => $requestId, 'earning_id' => (int) $earningId]);
                $reserve->execute(['id' => (int) $earningId]);
                if ($reserve->rowCount() !== 1) {
                    throw new RuntimeException('An Instructor earning changed while the payout was being queued.');
                }
            }

            $queued[] = [
                'withdrawal_request_id' => $requestId,
                'instructor_id' => (int) $group['instructor_id'],
                'order_id' => (int) $group['order_id'],
                'payment_id' => (int) $group['payment_id'],
                'amount' => $amount,
                'method' => $destination['method'],
                'destination' => $destination,
            ];
        }

        $database->commit();
        return $queued;
    }

    /** @param array<string,mixed> $details
     *  @return array<string,string|null>|null
     */
    private static function destination(array $details): ?array
    {
        $supported = ['bank', 'esewa', 'khalti'];
        $configuredPriority = array_filter(array_map(
            static fn (string $value): string => strtolower(trim($value)),
            explode(',', (string) getenv('PAYOUT_METHOD_PRIORITY'))
        ));
        $priority = array_values(array_unique(array_merge($configuredPriority, $supported)));

        foreach ($priority as $method) {
            if (!in_array($method, $supported, true)) {
                continue;
            }
            if ($method === 'bank' && trim((string) ($details['account_number'] ?? '')) !== '') {
                return [
                    'method' => 'bank',
                    'account_name' => self::nullable($details['account_name'] ?? null),
                    'account_number' => self::nullable($details['account_number'] ?? null),
                    'bank_name' => self::nullable($details['bank_name'] ?? null),
                    'branch_name' => self::nullable($details['branch_name'] ?? null),
                    'esewa_number' => null,
                    'khalti_number' => null,
                ];
            }
            if ($method === 'esewa' && trim((string) ($details['esewa_number'] ?? '')) !== '') {
                return [
                    'method' => 'esewa',
                    'account_name' => self::nullable($details['account_name'] ?? null),
                    'account_number' => null,
                    'bank_name' => null,
                    'branch_name' => null,
                    'esewa_number' => self::nullable($details['esewa_number'] ?? null),
                    'khalti_number' => null,
                ];
            }
            if ($method === 'khalti' && trim((string) ($details['khalti_number'] ?? '')) !== '') {
                return [
                    'method' => 'khalti',
                    'account_name' => self::nullable($details['account_name'] ?? null),
                    'account_number' => null,
                    'bank_name' => null,
                    'branch_name' => null,
                    'esewa_number' => null,
                    'khalti_number' => self::nullable($details['khalti_number'] ?? null),
                ];
            }
        }

        return null;
    }

    /** @param array<string,mixed> $payout
     *  @return array<string,mixed>
     */
    private static function dispatch(PDO $database, array $payout): array
    {
        $requestId = (int) $payout['withdrawal_request_id'];
        if (!self::automaticEnabled()) {
            self::recordQueueNotice($database, $payout, 'Automatic payout API is disabled or incomplete. Admin settlement is required.');
            return ['withdrawal_request_id' => $requestId, 'status' => 'queued_for_admin'];
        }

        $url = trim((string) getenv('PAYOUT_API_URL'));
        $token = trim((string) getenv('PAYOUT_API_TOKEN'));
        $payload = [
            'reference' => 'COURSEHUB-PAYOUT-' . $requestId,
            'amount' => (string) $payout['amount'],
            'currency' => 'NPR',
            'method' => (string) $payout['method'],
            'destination' => $payout['destination'],
            'instructor_id' => (int) $payout['instructor_id'],
            'order_id' => (int) $payout['order_id'],
            'payment_id' => (int) $payout['payment_id'],
            'withdrawal_request_id' => $requestId,
        ];
        $headers = [
            'Authorization: Bearer ' . $token,
            'Idempotency-Key: coursehub-payout-' . $requestId,
        ];
        $hmacSecret = trim((string) getenv('PAYOUT_HMAC_SECRET'));
        if ($hmacSecret !== '') {
            $canonical = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $headers[] = 'X-CourseHub-Signature: sha256=' . hash_hmac('sha256', $canonical, $hmacSecret);
        }

        try {
            $response = GatewayClient::postJson($url, $payload, $headers);
            $status = strtolower(trim((string) ($response['status'] ?? '')));
            $reference = trim((string) ($response['transaction_reference'] ?? $response['reference'] ?? ''));
            if (!in_array($status, ['paid', 'success', 'completed'], true)
                || preg_match('/^[A-Za-z0-9._:\/-]{4,150}$/', $reference) !== 1
            ) {
                throw new RuntimeException('The payout API did not confirm a completed transfer.');
            }

            self::markPaid($database, $payout, $reference);
            return [
                'withdrawal_request_id' => $requestId,
                'status' => 'paid',
                'transaction_reference' => $reference,
            ];
        } catch (Throwable $exception) {
            $message = mb_substr(trim($exception->getMessage()), 0, 500);
            self::recordQueueNotice($database, $payout, 'Automatic payout failed: ' . $message);
            error_log('Automatic Instructor payout #' . $requestId . ' failed: ' . $message);
            return [
                'withdrawal_request_id' => $requestId,
                'status' => 'queued_for_admin',
                'message' => $message,
            ];
        }
    }

    /** @param array<string,mixed> $payout */
    private static function markPaid(PDO $database, array $payout, string $reference): void
    {
        $requestId = (int) $payout['withdrawal_request_id'];
        $database->beginTransaction();
        $lock = $database->prepare('SELECT request_status FROM withdrawal_requests WHERE id=:id FOR UPDATE');
        $lock->execute(['id' => $requestId]);
        $status = $lock->fetchColumn();
        if ($status === 'paid') {
            $database->commit();
            return;
        }
        if ($status !== 'approved') {
            throw new RuntimeException('The payout request is no longer approved.');
        }

        $insert = $database->prepare(
            "INSERT INTO payouts (withdrawal_request_id,order_id,payment_id,payout_source,instructor_id,paid_amount,payment_method,transaction_reference,payout_status,admin_note,paid_at) "
            . "VALUES (:withdrawal_id,:order_id,:payment_id,'withdrawal',:instructor_id,:amount,:method,:reference,'paid','Paid automatically through configured payout API.',NOW())"
        );
        $insert->execute([
            'withdrawal_id' => $requestId,
            'order_id' => (int) $payout['order_id'],
            'payment_id' => (int) $payout['payment_id'],
            'instructor_id' => (int) $payout['instructor_id'],
            'amount' => (string) $payout['amount'],
            'method' => (string) $payout['method'],
            'reference' => $reference,
        ]);
        $update = $database->prepare("UPDATE withdrawal_requests SET request_status='paid',admin_note='Paid automatically through configured payout API.',processed_at=NOW() WHERE id=:id AND request_status='approved'");
        $update->execute(['id' => $requestId]);
        $earnings = $database->prepare("UPDATE instructor_earnings ie INNER JOIN withdrawal_request_earnings wre ON wre.earning_id=ie.id SET ie.earning_status='paid',ie.paid_at=NOW() WHERE wre.withdrawal_request_id=:id AND ie.earning_status='withdraw_requested'");
        $earnings->execute(['id' => $requestId]);
        self::notifyInstructor(
            $database,
            (int) $payout['instructor_id'],
            'Instructor payout completed',
            'Your CourseHub payout #' . $requestId . ' was transferred automatically. Reference: ' . $reference
        );
        $database->commit();
    }

    /** @param array<string,mixed> $payout */
    private static function recordQueueNotice(PDO $database, array $payout, string $reason): void
    {
        $requestId = (int) $payout['withdrawal_request_id'];
        $database->beginTransaction();
        $update = $database->prepare("UPDATE withdrawal_requests SET admin_note=:note WHERE id=:id AND request_status='approved'");
        $update->execute(['note' => mb_substr($reason, 0, 1000), 'id' => $requestId]);
        self::notifyInstructor(
            $database,
            (int) $payout['instructor_id'],
            'Instructor payout queued',
            'Your verified net earnings are reserved in payout #' . $requestId . '. The transfer is waiting for the configured payout provider or Admin settlement.'
        );
        self::notifyAdmins(
            $database,
            'Instructor payout needs settlement',
            'Payout #' . $requestId . ' for Instructor #' . (int) $payout['instructor_id'] . ' is approved but not yet transferred. ' . mb_substr($reason, 0, 500)
        );
        $database->commit();
    }

    private static function automaticEnabled(): bool
    {
        $enabled = filter_var((string) getenv('AUTO_PAYOUT_ENABLED'), FILTER_VALIDATE_BOOL);
        $url = trim((string) getenv('PAYOUT_API_URL'));
        $token = trim((string) getenv('PAYOUT_API_TOKEN'));
        $parts = parse_url($url);

        return $enabled === true
            && $token !== ''
            && is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && trim((string) ($parts['host'] ?? '')) !== ''
            && !isset($parts['user'], $parts['pass'], $parts['fragment']);
    }

    private static function notifyInstructor(PDO $database, int $userId, string $title, string $message): void
    {
        $statement = $database->prepare('INSERT INTO notifications (user_id,title,message,notification_type) VALUES (:user_id,:title,:message,\'withdrawal_update\')');
        $statement->execute([
            'user_id' => $userId,
            'title' => mb_substr($title, 0, 180),
            'message' => mb_substr($message, 0, 1000),
        ]);
    }

    private static function notifyAdmins(PDO $database, string $title, string $message): void
    {
        $statement = $database->prepare(
            'INSERT INTO notifications (user_id,title,message,notification_type) '
            . 'SELECT id,:title,:message,\'withdrawal_request\' FROM users WHERE role=\'admin\' AND status=\'active\''
        );
        $statement->execute([
            'title' => mb_substr($title, 0, 180),
            'message' => mb_substr($message, 0, 1000),
        ]);
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }
}
