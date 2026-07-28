<?php

declare(strict_types=1);

use CourseHub\Identity\Features\Session\SessionAuthenticationException;
use CourseHub\Identity\Features\Session\SessionHandler;
use CourseHub\Identity\Infrastructure\Database;

require_once dirname(__DIR__) . '/src/Infrastructure/Database.php';
require_once dirname(__DIR__) . '/src/Features/Session/SessionHandler.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$requestId = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
if ($requestId === '' || preg_match('/^[A-Za-z0-9._-]{8,100}$/', $requestId) !== 1) {
    $requestId = bin2hex(random_bytes(16));
}
header('X-Request-ID: ' . $requestId);

$respond = static function (array $payload, int $status = 200) use ($requestId): never {
    http_response_code($status);
    echo json_encode($payload + ['request_id' => $requestId], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
};

$jsonInput = static function (): array {
    $raw = (string) file_get_contents('php://input');
    $decoded = json_decode($raw !== '' ? $raw : '{}', true, 24, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Request body must be a JSON object.');
    }
    return $decoded;
};

$cleanText = static function (mixed $value, string $label, int $min, int $max, bool $multiline = false, bool $required = true): string {
    if (!is_scalar($value) && $value !== null) {
        throw new InvalidArgumentException($label . ' must be a single text value.');
    }
    $text = (string) $value;
    $text = $multiline
        ? str_replace(["\r\n", "\r"], "\n", trim($text))
        : trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if ($text === '') {
        if ($required) {
            throw new InvalidArgumentException($label . ' is required.');
        }
        return '';
    }
    if (function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8')) {
        throw new InvalidArgumentException($label . ' contains invalid text encoding.');
    }
    $controls = $multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u';
    if (preg_match($controls, $text) === 1) {
        throw new InvalidArgumentException($label . ' contains unsupported control characters.');
    }
    $length = mb_strlen($text);
    if ($length < $min || $length > $max) {
        throw new InvalidArgumentException(sprintf('%s must contain between %d and %d characters.', $label, $min, $max));
    }
    return $text;
};

$profile = static function (PDO $database, int $userId): array {
    $statement = $database->prepare(
        'SELECT u.id,u.full_name,u.email,u.phone,u.bio,u.profile_image,u.profile_image_changed_at,'
        . 'a.professional_headline,a.expertise,a.teaching_experience,a.social_profile_url,a.course_subjects '
        . 'FROM users u LEFT JOIN instructor_applications a ON a.instructor_id=u.id '
        . 'WHERE u.id=:id AND u.role=\'instructor\' AND u.status=\'active\' LIMIT 1'
    );
    $statement->execute(['id' => $userId]);
    $record = $statement->fetch();
    if (!is_array($record)) {
        throw new SessionAuthenticationException('The Instructor profile is unavailable.');
    }

    // Retain these response fields for existing clients, but profile photos are never time-locked.
    $record['photo_change_allowed'] = true;
    $record['profile_image_change_available_at'] = null;
    return $record;
};

$database = null;
try {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'POST'], true)) {
        $respond(['error' => 'Method not allowed.'], 405);
    }

    $database = Database::connect();
    $session = (new SessionHandler($database))->verify((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (($session['user']['role'] ?? '') !== 'instructor') {
        throw new SessionAuthenticationException('Instructor access is required.');
    }
    $userId = (int) ($session['user']['id'] ?? 0);

    if ($method === 'GET') {
        $respond(['data' => $profile($database, $userId)]);
    }

    $input = $jsonInput();
    $action = strtolower(trim((string) ($input['action'] ?? 'save_profile')));
    if (!in_array($action, ['save_profile', 'remove_photo'], true)) {
        throw new InvalidArgumentException('Choose a valid Instructor profile action.');
    }

    $database->beginTransaction();
    $currentStatement = $database->prepare(
        'SELECT profile_image,profile_image_changed_at FROM users '
        . 'WHERE id=:id AND role=\'instructor\' AND status=\'active\' LIMIT 1 FOR UPDATE'
    );
    $currentStatement->execute(['id' => $userId]);
    $current = $currentStatement->fetch();
    if (!is_array($current)) {
        $database->rollBack();
        throw new SessionAuthenticationException('The Instructor profile is unavailable.');
    }

    $oldProfileImage = trim((string) ($current['profile_image'] ?? ''));

    if ($action === 'remove_photo') {
        $update = $database->prepare(
            'UPDATE users SET profile_image=NULL,profile_image_changed_at=NULL '
            . 'WHERE id=:id AND role=\'instructor\' AND status=\'active\''
        );
        $update->execute(['id' => $userId]);
        $database->commit();
        $respond([
            'message' => $oldProfileImage !== '' ? 'Instructor profile photo removed.' : 'No Instructor profile photo was stored.',
            'old_profile_image' => $oldProfileImage,
            'data' => $profile($database, $userId),
        ]);
    }

    $fullName = $cleanText($input['full_name'] ?? '', 'Full name', 2, 100);
    if (preg_match("/^[\\p{L}\\p{M}][\\p{L}\\p{M} .'-]*$/u", $fullName) !== 1) {
        throw new InvalidArgumentException('Full name contains unsupported characters.');
    }
    $phone = $cleanText($input['phone'] ?? '', 'Phone number', 7, 20, false, false);
    if ($phone !== '' && preg_match('/^[0-9+() -]+$/', $phone) !== 1) {
        throw new InvalidArgumentException('Phone number may contain digits, spaces, parentheses, plus and hyphen only.');
    }
    $bio = $cleanText($input['bio'] ?? '', 'Professional biography', 40, 3000, true);
    $headline = $cleanText($input['professional_headline'] ?? '', 'Professional headline', 5, 160);
    $expertise = $cleanText($input['expertise'] ?? '', 'Areas of expertise', 10, 1000, true);
    $experience = $cleanText($input['teaching_experience'] ?? '', 'Teaching experience', 20, 2000, true);
    $subjects = $cleanText($input['course_subjects'] ?? '', 'Course subjects', 3, 1000, true);
    $social = trim((string) ($input['social_profile_url'] ?? ''));
    if ($social !== '') {
        $parts = parse_url($social);
        if (mb_strlen($social) > 500
            || filter_var($social, FILTER_VALIDATE_URL) === false
            || !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || trim((string) ($parts['host'] ?? '')) === ''
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new InvalidArgumentException('Professional profile URL must be a normal HTTPS address.');
        }
    }

    $newProfileImage = trim((string) ($input['profile_image'] ?? ''));
    if ($newProfileImage !== ''
        && preg_match('#^private/instructor-profiles/[a-f0-9]{40}\.(?:jpg|png|webp)$#', $newProfileImage) !== 1
    ) {
        throw new InvalidArgumentException('The new Instructor profile photo reference is invalid.');
    }

    $updateUserSql = 'UPDATE users SET full_name=:full_name,phone=:phone,bio=:bio';
    $userParameters = [
        'full_name' => $fullName,
        'phone' => $phone !== '' ? $phone : null,
        'bio' => $bio,
        'id' => $userId,
    ];
    if ($newProfileImage !== '') {
        $updateUserSql .= ',profile_image=:profile_image,profile_image_changed_at=NOW()';
        $userParameters['profile_image'] = $newProfileImage;
    }
    $updateUserSql .= ' WHERE id=:id AND role=\'instructor\' AND status=\'active\'';
    $database->prepare($updateUserSql)->execute($userParameters);

    $application = $database->prepare(
        'UPDATE instructor_applications SET application_note=:bio,professional_headline=:headline,expertise=:expertise,'
        . 'teaching_experience=:experience,social_profile_url=:social,course_subjects=:subjects '
        . 'WHERE instructor_id=:id AND application_status=\'approved\''
    );
    $application->execute([
        'bio' => $bio,
        'headline' => $headline,
        'expertise' => $expertise,
        'experience' => $experience,
        'social' => $social !== '' ? $social : null,
        'subjects' => $subjects,
        'id' => $userId,
    ]);
    $database->commit();

    $respond([
        'message' => $newProfileImage !== ''
            ? 'Instructor profile and photo updated.'
            : 'Instructor profile updated.',
        'old_profile_image' => $newProfileImage !== '' ? $oldProfileImage : '',
        'data' => $profile($database, $userId),
    ]);
} catch (SessionAuthenticationException $exception) {
    if ($database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    $respond(['error' => $exception->getMessage()], 401);
} catch (InvalidArgumentException $exception) {
    if ($database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    $respond(['error' => $exception->getMessage()], 422);
} catch (JsonException) {
    if ($database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    $respond(['error' => 'Malformed JSON request.'], 400);
} catch (Throwable $exception) {
    if ($database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Instructor profile failure [' . $requestId . ']: ' . $exception->getMessage());
    $respond(['error' => 'The Instructor profile service is unavailable.'], 503);
}
