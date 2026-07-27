<?php

declare(strict_types=1);

namespace CourseHub\Identity\Infrastructure;

use RuntimeException;

final class SmtpMailer
{
    public static function isConfigured(): bool
    {
        return trim((string) getenv('SMTP_HOST')) !== ''
            && trim((string) getenv('SMTP_USERNAME')) !== ''
            && (string) getenv('SMTP_PASSWORD') !== ''
            && trim((string) getenv('SMTP_FROM_ADDRESS')) !== '';
    }

    public static function sendCode(string $recipient, string $recipientName, string $code, string $purpose): void
    {
        if (!self::isConfigured()) {
            throw new EmailDeliveryException('Email delivery is not configured. Add the SMTP settings to .env.');
        }

        $subject = $purpose === 'student_password_reset'
            ? 'Your CourseHub password reset code'
            : 'Verify your CourseHub student account';
        $action = $purpose === 'student_password_reset'
            ? 'reset your Student password'
            : 'finish creating your Student account';
        $body = "Hello {$recipientName},\n\n"
            . "Use this verification code to {$action}:\n\n"
            . "{$code}\n\n"
            . "The code expires in 10 minutes and can be used only once.\n"
            . "If you did not request this, you can ignore this email.\n\n"
            . "CourseHub";

        self::send($recipient, $subject, $body);
    }

    public static function sendInstructorRejection(
        string $recipient,
        string $recipientName,
        string $reason,
    ): void {
        if (!self::isConfigured()) {
            throw new EmailDeliveryException('Instructor decision email is not configured. Add the SMTP settings to .env.');
        }

        $recipientName = trim($recipientName) !== '' ? trim($recipientName) : 'Instructor applicant';
        $reason = trim($reason);
        if ($reason === '') {
            throw new EmailDeliveryException('A rejection reason is required before the Instructor email can be sent.');
        }

        $subject = 'Your CourseHub Instructor application decision';
        $body = "Hello {$recipientName},\n\n"
            . "Your CourseHub Instructor application was reviewed and was not approved.\n\n"
            . "Reason provided by the CourseHub administrator:\n"
            . "{$reason}\n\n"
            . "You may correct the application and submit it again using this same email address. "
            . "Your account will remain unavailable until a new application is approved.\n\n"
            . "Please contact CourseHub support if you need clarification.\n\n"
            . "CourseHub";

        self::send($recipient, $subject, $body);
    }

    private static function send(string $recipient, string $subject, string $body): void
    {
        $host = trim((string) getenv('SMTP_HOST'));
        $port = max(1, (int) (getenv('SMTP_PORT') ?: 587));
        $username = trim((string) getenv('SMTP_USERNAME'));
        $password = (string) getenv('SMTP_PASSWORD');
        $fromAddress = trim((string) getenv('SMTP_FROM_ADDRESS'));
        $fromName = trim((string) (getenv('SMTP_FROM_NAME') ?: 'CourseHub'));
        $encryption = strtolower(trim((string) (getenv('SMTP_ENCRYPTION') ?: 'tls')));

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            throw new EmailDeliveryException('Email delivery configuration contains an invalid address.');
        }
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            throw new EmailDeliveryException('SMTP_ENCRYPTION must be tls, ssl, or none.');
        }

        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $socket = @stream_socket_client($remote, $errorNumber, $errorMessage, 12, STREAM_CLIENT_CONNECT);
        if (!is_resource($socket)) {
            throw new EmailDeliveryException('Could not connect to the email server. ' . trim((string) $errorMessage));
        }

        stream_set_timeout($socket, 12);
        try {
            self::expect($socket, [220]);
            self::command($socket, 'EHLO coursehub.local', [250]);

            if ($encryption === 'tls') {
                self::command($socket, 'STARTTLS', [220]);
                $cryptoEnabled = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($cryptoEnabled !== true) {
                    throw new EmailDeliveryException('Could not establish an encrypted email connection.');
                }
                self::command($socket, 'EHLO coursehub.local', [250]);
            }

            self::command($socket, 'AUTH LOGIN', [334]);
            self::command($socket, base64_encode($username), [334]);
            self::command($socket, base64_encode($password), [235]);
            self::command($socket, 'MAIL FROM:<' . $fromAddress . '>', [250]);
            self::command($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
            self::command($socket, 'DATA', [354]);

            $headers = [
                'Date: ' . date(DATE_RFC2822),
                'From: ' . self::headerText($fromName) . ' <' . $fromAddress . '>',
                'To: <' . $recipient . '>',
                'Subject: ' . self::headerText($subject),
                'Message-ID: <' . bin2hex(random_bytes(16)) . '@coursehub.local>',
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
            ];
            $message = implode("\r\n", $headers) . "\r\n\r\n" . self::dotStuff($body) . "\r\n.";
            self::command($socket, $message, [250]);
            self::command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket @param list<int> $expectedCodes */
    private static function command($socket, string $command, array $expectedCodes): string
    {
        if (fwrite($socket, $command . "\r\n") === false) {
            throw new EmailDeliveryException('The email server connection was interrupted.');
        }
        return self::expect($socket, $expectedCodes);
    }

    /** @param resource $socket @param list<int> $expectedCodes */
    private static function expect($socket, array $expectedCodes): string
    {
        $response = '';
        while (($line = fgets($socket, 2048)) !== false) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        if ($response === '') {
            throw new EmailDeliveryException('The email server did not respond.');
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new EmailDeliveryException('The email server rejected the message. SMTP code ' . $code . '.');
        }
        return $response;
    }

    private static function headerText(string $value): string
    {
        $clean = str_replace(["\r", "\n"], '', $value);
        return '=?UTF-8?B?' . base64_encode($clean) . '?=';
    }

    private static function dotStuff(string $body): string
    {
        $normalised = str_replace(["\r\n", "\r"], "\n", $body);
        $normalised = preg_replace('/^\./m', '..', $normalised) ?? $normalised;
        return str_replace("\n", "\r\n", $normalised);
    }
}

final class EmailDeliveryException extends RuntimeException
{
}
