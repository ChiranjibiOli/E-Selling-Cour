<?php

declare(strict_types=1);

use CourseHub\Identity\Features\Login\LoginAuthenticationException;
use CourseHub\Identity\Features\Login\LoginHandler;
use CourseHub\Identity\Features\Login\LoginRateLimitException;
use CourseHub\Identity\Features\Login\LoginRateLimiter;
use CourseHub\Identity\Features\Login\LoginValidationException;
use CourseHub\Identity\Features\Session\SessionAuthenticationException;
use CourseHub\Identity\Features\Session\SessionHandler;
use CourseHub\Identity\Infrastructure\Database;

require_once dirname(__DIR__) . '/src/Infrastructure/Database.php';
require_once dirname(__DIR__) . '/src/Features/Login/LoginRateLimiter.php';
require_once dirname(__DIR__) . '/src/Features/Login/LoginHandler.php';
require_once dirname(__DIR__) . '/src/Features/Session/SessionHandler.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$requestId = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
if ($requestId === '' || preg_match('/^[A-Za-z0-9._-]{8,100}$/', $requestId) !== 1) {
    $requestId = bin2hex(random_bytes(16));
}
header('X-Request-ID: ' . $requestId);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

$respond = static function (array $payload, int $status = 200) use ($requestId): never {
    http_response_code($status);
    echo json_encode($payload + ['request_id' => $requestId], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
};

$jsonInput = static function (): array {
    $raw = (string) file_get_contents('php://input');
    $decoded = json_decode($raw !== '' ? $raw : '{}', true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new LoginValidationException('Request body must be a JSON object.');
    }
    return $decoded;
};

try {
    $database = Database::connect();

    if ($path === '/health' && $method === 'GET') {
        $database->query('SELECT 1');
        $respond(['status' => 'ok', 'service' => 'identity-service']);
    }

    if (preg_match('#^/api/v1/auth/register/(student|instructor)$#', $path, $matches) === 1 && $method === 'POST') {
        $input = $jsonInput();
        $role = $matches[1];
        $fullName = trim((string) ($input['full_name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $phone = trim((string) ($input['phone'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $passwordConfirmation = (string) ($input['password_confirmation'] ?? '');
        $bio = trim((string) ($input['bio'] ?? ''));
        $profileImage = trim((string) ($input['profile_image'] ?? ''));
        $identityDocument = trim((string) ($input['identity_document'] ?? ''));
        $professionalHeadline = trim((string) ($input['professional_headline'] ?? ''));
        $expertise = trim((string) ($input['expertise'] ?? ''));
        $teachingExperience = trim((string) ($input['teaching_experience'] ?? ''));
        $socialProfileUrl = trim((string) ($input['social_profile_url'] ?? ''));
        $courseSubjects = trim((string) ($input['course_subjects'] ?? ''));
        $agreedRules = (string) ($input['agree_instructor_rules'] ?? '') === '1';

        if ($fullName === '' || mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
            throw new LoginValidationException('Enter your full name.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            throw new LoginValidationException('Enter a valid email address.');
        }
        if ($phone !== '' && preg_match('/^[0-9+() -]{7,20}$/', $phone) !== 1) {
            throw new LoginValidationException('Enter a valid phone number.');
        }
        if (strlen($password) < 8 || strlen($password) > 200 || !hash_equals($password, $passwordConfirmation)) {
            throw new LoginValidationException('Use a matching password with at least 8 characters.');
        }
        if (mb_strlen($bio) > 3000 || ($role === 'instructor' && mb_strlen($bio) < 40)) {
            throw new LoginValidationException($role === 'instructor' ? 'Instructor biography must contain at least 40 characters.' : 'Biography is too long.');
        }

        if ($role === 'instructor') {
            if (mb_strlen($professionalHeadline) < 5 || mb_strlen($professionalHeadline) > 160) {
                throw new LoginValidationException('Enter a professional headline between 5 and 160 characters.');
            }
            if (mb_strlen($expertise) < 10 || mb_strlen($expertise) > 1000) {
                throw new LoginValidationException('Explain your areas of expertise.');
            }
            if (mb_strlen($teachingExperience) < 20 || mb_strlen($teachingExperience) > 2000) {
                throw new LoginValidationException('Explain your teaching or mentoring experience.');
            }
            if (mb_strlen($courseSubjects) < 3 || mb_strlen($courseSubjects) > 1000) {
                throw new LoginValidationException('List the course subjects you plan to teach.');
            }
            if ($socialProfileUrl !== '' && (!filter_var($socialProfileUrl, FILTER_VALIDATE_URL) || mb_strlen($socialProfileUrl) > 500)) {
                throw new LoginValidationException('Enter a valid professional profile URL.');
            }
            if (preg_match('#^private/instructor-profiles/[a-f0-9]{40}\.(?:jpg|png|webp)$#', $profileImage) !== 1) {
                throw new LoginValidationException('A valid passport-size profile photo is required.');
            }
            if (preg_match('#^private/instructor-identity/[a-f0-9]{40}\.(?:jpg|png|webp|pdf)$#', $identityDocument) !== 1) {
                throw new LoginValidationException('A valid identity document is required.');
            }
            if (!$agreedRules) {
                throw new LoginValidationException('You must agree to the instructor and content rules.');
            }
        }

        $status = $role === 'student' ? 'active' : 'inactive';
        $statement = $database->prepare(
            'INSERT INTO users (full_name, email, password, phone, role, bio, profile_image, identity_document, status) '
            . 'VALUES (:full_name, :email, :password, :phone, :role, :bio, :profile_image, :identity_document, :status)'
        );
        try {
            $database->beginTransaction();
            $statement->execute([
                'full_name' => $fullName,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'phone' => $phone !== '' ? $phone : null,
                'role' => $role,
                'bio' => $bio !== '' ? $bio : null,
                'profile_image' => $profileImage !== '' ? $profileImage : null,
                'identity_document' => $identityDocument !== '' ? $identityDocument : null,
                'status' => $status,
            ]);
            $userId = (int) $database->lastInsertId();
            if ($role === 'instructor') {
                $application = $database->prepare(
                    'INSERT INTO instructor_applications '
                    . '(instructor_id, application_note, professional_headline, expertise, teaching_experience, social_profile_url, course_subjects, agreed_rules_at) '
                    . 'VALUES (:instructor_id, :application_note, :professional_headline, :expertise, :teaching_experience, :social_profile_url, :course_subjects, NOW())'
                );
                $application->execute([
                    'instructor_id' => $userId,
                    'application_note' => $bio,
                    'professional_headline' => $professionalHeadline,
                    'expertise' => $expertise,
                    'teaching_experience' => $teachingExperience,
                    'social_profile_url' => $socialProfileUrl !== '' ? $socialProfileUrl : null,
                    'course_subjects' => $courseSubjects,
                ]);
            }
            $database->commit();
        } catch (PDOException $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            if ($exception->getCode() === '23000') {
                throw new LoginValidationException('An account with that email already exists.');
            }
            throw $exception;
        }

        $respond([
            'message' => $role === 'student'
                ? 'Student account created. You can sign in now.'
                : 'Instructor account application submitted for administrator approval.',
            'status' => $status,
        ], 201);
    }

    if ($path === '/api/v1/auth/forgot-password' && $method === 'POST') {
        $input = $jsonInput();
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            throw new LoginValidationException('Enter a valid email address.');
        }

        $result = ['message' => 'If an eligible account exists, password-reset instructions have been created.'];
        $statement = $database->prepare('SELECT id FROM users WHERE email = :email AND status <> \'blocked\' LIMIT 1');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();
        if (is_array($user)) {
            $rawToken = bin2hex(random_bytes(32));
            $database->beginTransaction();
            $expireOld = $database->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL');
            $expireOld->execute(['user_id' => (int) $user['id']]);
            $insert = $database->prepare(
                'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, requested_ip) '
                . 'VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 30 MINUTE), :requested_ip)'
            );
            $insert->execute([
                'user_id' => (int) $user['id'],
                'token_hash' => hash('sha256', $rawToken),
                'requested_ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 64),
            ]);
            $database->commit();

            $allowLocalToken = filter_var((string) (getenv('ALLOW_LOCAL_RESET_TOKEN') ?: 'false'), FILTER_VALIDATE_BOOLEAN);
            if ((string) getenv('APP_ENV') === 'local' && $allowLocalToken) {
                $result['development_reset_url'] = '/reset-password?token=' . $rawToken;
            }
        }
        $respond($result, 202);
    }

    if ($path === '/api/v1/auth/reset-password' && $method === 'POST') {
        $input = $jsonInput();
        $token = trim((string) ($input['token'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $passwordConfirmation = (string) ($input['password_confirmation'] ?? '');
        if (preg_match('/^[a-f0-9]{64}$/i', $token) !== 1) {
            throw new LoginValidationException('The password-reset link is invalid.');
        }
        if (strlen($password) < 8 || strlen($password) > 200 || !hash_equals($password, $passwordConfirmation)) {
            throw new LoginValidationException('Use a matching password with at least 8 characters.');
        }

        $database->beginTransaction();
        $statement = $database->prepare(
            'SELECT id, user_id FROM password_reset_tokens WHERE token_hash = :token_hash '
            . 'AND used_at IS NULL AND expires_at > NOW() LIMIT 1 FOR UPDATE'
        );
        $statement->execute(['token_hash' => hash('sha256', $token)]);
        $reset = $statement->fetch();
        if (!is_array($reset)) {
            $database->rollBack();
            throw new LoginValidationException('The password-reset link is invalid or expired.');
        }
        $updateUser = $database->prepare('UPDATE users SET password = :password WHERE id = :user_id');
        $updateUser->execute(['password' => password_hash($password, PASSWORD_DEFAULT), 'user_id' => (int) $reset['user_id']]);
        $markUsed = $database->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id');
        $markUsed->execute(['id' => (int) $reset['id']]);
        $revoke = $database->prepare('UPDATE identity_sessions SET revoked_at = NOW() WHERE user_id = :user_id AND revoked_at IS NULL');
        $revoke->execute(['user_id' => (int) $reset['user_id']]);
        $database->commit();
        $respond(['message' => 'Password changed. Sign in with your new password.']);
    }

    if ($path === '/api/v1/auth/login' && $method === 'POST') {
        $input = $jsonInput();
        $handler = new LoginHandler($database, new LoginRateLimiter($database));
        $result = $handler->handle(
            $input,
            (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 500)
        );
        $respond($result);
    }

    $authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $sessionHandler = new SessionHandler($database);

    if ($path === '/api/v1/users/instructor-applications' && $method === 'GET') {
        $session = $sessionHandler->verify($authorization);
        if (($session['user']['role'] ?? '') !== 'admin') {
            throw new SessionAuthenticationException('Administrator access is required.');
        }
        $statement = $database->query(
            'SELECT u.id, u.full_name, u.email, u.phone, u.bio, u.profile_image, u.identity_document, '
            . 'a.professional_headline, a.expertise, a.teaching_experience, a.social_profile_url, a.course_subjects, a.agreed_rules_at, a.created_at '
            . 'FROM instructor_applications a INNER JOIN users u ON u.id=a.instructor_id '
            . 'WHERE a.application_status=\'pending\' AND u.role=\'instructor\' AND u.status=\'inactive\' ORDER BY a.created_at ASC'
        );
        $respond(['data' => $statement->fetchAll()]);
    }

    if (preg_match('#^/api/v1/users/instructor-applications/(\d+)/(approve|reject)$#', $path, $matches) === 1 && $method === 'POST') {
        $session = $sessionHandler->verify($authorization);
        if (($session['user']['role'] ?? '') !== 'admin') {
            throw new SessionAuthenticationException('Administrator access is required.');
        }
        $input = $jsonInput();
        $action = $matches[2];
        $note = trim((string) ($input['note'] ?? ''));
        if (mb_strlen($note) > 1000) {
            throw new LoginValidationException('Decision notes must be 1000 characters or fewer.');
        }
        if ($action === 'reject' && $note === '') {
            throw new LoginValidationException('A rejection reason is required.');
        }

        $database->beginTransaction();
        $application = $database->prepare(
            'UPDATE instructor_applications SET application_status=:application_status, review_note=:review_note, reviewed_by=:reviewed_by, reviewed_at=NOW() '
            . 'WHERE instructor_id=:id AND application_status=\'pending\''
        );
        $application->execute([
            'application_status' => $action === 'approve' ? 'approved' : 'rejected',
            'review_note' => $note !== '' ? $note : null,
            'reviewed_by' => (int) ($session['user']['id'] ?? 0),
            'id' => (int) $matches[1],
        ]);
        if ($application->rowCount() !== 1) {
            $database->rollBack();
            throw new LoginValidationException('The instructor application is no longer pending.');
        }
        $statement = $database->prepare(
            'UPDATE users SET status=:status, '
            . 'profile_image_changed_at=CASE WHEN :approved=1 THEN COALESCE(profile_image_changed_at, NOW()) ELSE profile_image_changed_at END '
            . 'WHERE id=:id AND role=\'instructor\' AND status=\'inactive\''
        );
        $statement->execute([
            'status' => $action === 'approve' ? 'active' : 'blocked',
            'approved' => $action === 'approve' ? 1 : 0,
            'id' => (int) $matches[1],
        ]);
        if ($statement->rowCount() !== 1) {
            $database->rollBack();
            throw new LoginValidationException('The instructor account state changed during review.');
        }
        $notification = $database->prepare('INSERT INTO notifications (user_id, title, message, notification_type) VALUES (:user_id, :title, :message, \'instructor_application\')');
        $notification->execute([
            'user_id' => (int) $matches[1],
            'title' => $action === 'approve' ? 'Instructor application approved' : 'Instructor application reviewed',
            'message' => $action === 'approve'
                ? 'Your Instructor account is active. The accepted passport-size photo is now your profile photo and can be changed after 25 days.'
                : 'Your Instructor application was not approved. Review note: ' . $note,
        ]);
        $database->commit();
        $respond(['message' => $action === 'approve' ? 'Instructor approved with the submitted profile photo.' : 'Instructor application rejected.']);
    }

    if ($path === '/api/v1/users/instructor-profile' && $method === 'GET') {
        $session = $sessionHandler->verify($authorization);
        if (($session['user']['role'] ?? '') !== 'instructor') {
            throw new SessionAuthenticationException('Instructor access is required.');
        }
        $statement = $database->prepare(
            'SELECT u.id, u.full_name, u.email, u.phone, u.bio, u.profile_image, u.profile_image_changed_at, '
            . 'DATE_ADD(u.profile_image_changed_at, INTERVAL 25 DAY) AS profile_image_change_available_at, '
            . 'a.professional_headline, a.expertise, a.teaching_experience, a.social_profile_url, a.course_subjects '
            . 'FROM users u LEFT JOIN instructor_applications a ON a.instructor_id=u.id '
            . 'WHERE u.id=:id AND u.role=\'instructor\' AND u.status=\'active\' LIMIT 1'
        );
        $statement->execute(['id' => (int) ($session['user']['id'] ?? 0)]);
        $profile = $statement->fetch();
        if (!is_array($profile)) {
            throw new SessionAuthenticationException('The Instructor profile is unavailable.');
        }
        $profile['photo_change_allowed'] = $profile['profile_image_changed_at'] === null
            || strtotime((string) $profile['profile_image_change_available_at']) <= time();
        $respond(['data' => $profile]);
    }

    if ($path === '/api/v1/users/instructor-profile' && $method === 'POST') {
        $session = $sessionHandler->verify($authorization);
        if (($session['user']['role'] ?? '') !== 'instructor') {
            throw new SessionAuthenticationException('Instructor access is required.');
        }
        $input = $jsonInput();
        $fullName = trim((string) ($input['full_name'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));
        $bio = trim((string) ($input['bio'] ?? ''));
        $professionalHeadline = trim((string) ($input['professional_headline'] ?? ''));
        $expertise = trim((string) ($input['expertise'] ?? ''));
        $teachingExperience = trim((string) ($input['teaching_experience'] ?? ''));
        $socialProfileUrl = trim((string) ($input['social_profile_url'] ?? ''));
        $courseSubjects = trim((string) ($input['course_subjects'] ?? ''));
        $newProfileImage = trim((string) ($input['profile_image'] ?? ''));

        if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
            throw new LoginValidationException('Enter your full name.');
        }
        if ($phone !== '' && preg_match('/^[0-9+() -]{7,20}$/', $phone) !== 1) {
            throw new LoginValidationException('Enter a valid phone number.');
        }
        if (mb_strlen($bio) < 40 || mb_strlen($bio) > 3000) {
            throw new LoginValidationException('Instructor biography must contain between 40 and 3000 characters.');
        }
        if (mb_strlen($professionalHeadline) < 5 || mb_strlen($professionalHeadline) > 160) {
            throw new LoginValidationException('Enter a professional headline between 5 and 160 characters.');
        }
        if (mb_strlen($expertise) < 10 || mb_strlen($expertise) > 1000) {
            throw new LoginValidationException('Explain your areas of expertise.');
        }
        if (mb_strlen($teachingExperience) < 20 || mb_strlen($teachingExperience) > 2000) {
            throw new LoginValidationException('Explain your teaching or mentoring experience.');
        }
        if (mb_strlen($courseSubjects) < 3 || mb_strlen($courseSubjects) > 1000) {
            throw new LoginValidationException('List the course subjects you teach.');
        }
        if ($socialProfileUrl !== '' && (!filter_var($socialProfileUrl, FILTER_VALIDATE_URL) || mb_strlen($socialProfileUrl) > 500)) {
            throw new LoginValidationException('Enter a valid professional profile URL.');
        }
        if ($newProfileImage !== '' && preg_match('#^private/instructor-profiles/[a-f0-9]{40}\.(?:jpg|png|webp)$#', $newProfileImage) !== 1) {
            throw new LoginValidationException('The new profile photo reference is invalid.');
        }

        $database->beginTransaction();
        $current = $database->prepare(
            'SELECT profile_image, profile_image_changed_at, DATE_ADD(profile_image_changed_at, INTERVAL 25 DAY) AS available_at '
            . 'FROM users WHERE id=:id AND role=\'instructor\' AND status=\'active\' LIMIT 1 FOR UPDATE'
        );
        $current->execute(['id' => (int) ($session['user']['id'] ?? 0)]);
        $currentProfile = $current->fetch();
        if (!is_array($currentProfile)) {
            $database->rollBack();
            throw new SessionAuthenticationException('The Instructor profile is unavailable.');
        }
        if ($newProfileImage !== '' && $currentProfile['profile_image_changed_at'] !== null && strtotime((string) $currentProfile['available_at']) > time()) {
            $availableAt = date('F j, Y', strtotime((string) $currentProfile['available_at']));
            $database->rollBack();
            throw new LoginValidationException('Your profile photo can be changed again on ' . $availableAt . '.');
        }

        $updateUserSql = 'UPDATE users SET full_name=:full_name, phone=:phone, bio=:bio';
        $userParameters = [
            'full_name' => $fullName,
            'phone' => $phone !== '' ? $phone : null,
            'bio' => $bio,
            'id' => (int) ($session['user']['id'] ?? 0),
        ];
        if ($newProfileImage !== '') {
            $updateUserSql .= ', profile_image=:profile_image, profile_image_changed_at=NOW()';
            $userParameters['profile_image'] = $newProfileImage;
        }
        $updateUserSql .= ' WHERE id=:id AND role=\'instructor\' AND status=\'active\'';
        $updateUser = $database->prepare($updateUserSql);
        $updateUser->execute($userParameters);

        $updateApplication = $database->prepare(
            'UPDATE instructor_applications SET application_note=:bio, professional_headline=:headline, expertise=:expertise, '
            . 'teaching_experience=:experience, social_profile_url=:social, course_subjects=:subjects '
            . 'WHERE instructor_id=:id AND application_status=\'approved\''
        );
        $updateApplication->execute([
            'bio' => $bio,
            'headline' => $professionalHeadline,
            'expertise' => $expertise,
            'experience' => $teachingExperience,
            'social' => $socialProfileUrl !== '' ? $socialProfileUrl : null,
            'subjects' => $courseSubjects,
            'id' => (int) ($session['user']['id'] ?? 0),
        ]);
        $database->commit();

        $respond([
            'message' => $newProfileImage !== ''
                ? 'Instructor profile updated. The new profile photo is locked for 25 days.'
                : 'Instructor profile updated.',
            'old_profile_image' => $newProfileImage !== '' ? (string) ($currentProfile['profile_image'] ?? '') : '',
        ]);
    }

    if ($path === '/api/v1/auth/session' && $method === 'GET') {
        $respond($sessionHandler->verify($authorization));
    }

    if ($path === '/api/v1/auth/logout' && $method === 'POST') {
        $sessionHandler->logout($authorization);
        $respond(['message' => 'Logged out.']);
    }

    $respond(['error' => 'Identity route not found.'], 404);
} catch (LoginRateLimitException $exception) {
    http_response_code(429);
    header('Retry-After: 900');
    echo json_encode(['error' => $exception->getMessage(), 'request_id' => $requestId], JSON_THROW_ON_ERROR);
} catch (LoginValidationException $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    $respond(['error' => $exception->getMessage()], 422);
} catch (LoginAuthenticationException|SessionAuthenticationException $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    $respond(['error' => $exception->getMessage()], 401);
} catch (JsonException) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    $respond(['error' => 'Malformed JSON request.'], 400);
} catch (Throwable $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Identity service failure [' . $requestId . ']: ' . $exception->getMessage());
    $respond(['error' => 'Identity service is unavailable.'], 503);
}
