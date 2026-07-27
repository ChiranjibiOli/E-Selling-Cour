<?php

declare(strict_types=1);

use CourseHub\Identity\Infrastructure\Database;

require_once dirname(__DIR__) . '/src/Infrastructure/Database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

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

$rollback = static function (?PDO $database): void {
    if ($database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
};

$database = null;
try {
    $raw = (string) file_get_contents('php://input');
    $input = json_decode($raw !== '' ? $raw : '{}', true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($input)) {
        throw new InvalidArgumentException('Request body must be a JSON object.');
    }

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
        throw new InvalidArgumentException('Enter your full name.');
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 150) {
        throw new InvalidArgumentException('Enter a valid email address.');
    }
    if ($phone !== '' && preg_match('/^[0-9+() -]{7,20}$/', $phone) !== 1) {
        throw new InvalidArgumentException('Enter a valid phone number.');
    }
    if (strlen($password) < 8 || strlen($password) > 200 || !hash_equals($password, $passwordConfirmation)) {
        throw new InvalidArgumentException('Use a matching password with at least 8 characters.');
    }
    if (mb_strlen($bio) < 40 || mb_strlen($bio) > 3000) {
        throw new InvalidArgumentException('Instructor biography must contain between 40 and 3000 characters.');
    }
    if (mb_strlen($professionalHeadline) < 5 || mb_strlen($professionalHeadline) > 160) {
        throw new InvalidArgumentException('Enter a professional headline between 5 and 160 characters.');
    }
    if (mb_strlen($expertise) < 10 || mb_strlen($expertise) > 1000) {
        throw new InvalidArgumentException('Explain your areas of expertise.');
    }
    if (mb_strlen($teachingExperience) < 20 || mb_strlen($teachingExperience) > 2000) {
        throw new InvalidArgumentException('Explain your teaching or mentoring experience.');
    }
    if (mb_strlen($courseSubjects) < 3 || mb_strlen($courseSubjects) > 1000) {
        throw new InvalidArgumentException('List the course subjects you plan to teach.');
    }
    if ($socialProfileUrl !== '' && (filter_var($socialProfileUrl, FILTER_VALIDATE_URL) === false || mb_strlen($socialProfileUrl) > 500)) {
        throw new InvalidArgumentException('Enter a valid professional profile URL.');
    }
    if (preg_match('#^private/instructor-profiles/[a-f0-9]{40}\.(?:jpg|png|webp)$#', $profileImage) !== 1) {
        throw new InvalidArgumentException('A valid passport-size profile photo is required.');
    }
    if (preg_match('#^private/instructor-identity/[a-f0-9]{40}\.(?:jpg|png|webp|pdf)$#', $identityDocument) !== 1) {
        throw new InvalidArgumentException('A valid identity document is required.');
    }
    if (!$agreedRules) {
        throw new InvalidArgumentException('You must agree to the instructor and content rules.');
    }

    $database = Database::connect();
    $database->beginTransaction();

    $existingStatement = $database->prepare(
        'SELECT u.id, u.role, u.status, u.profile_image, u.identity_document, '
        . 'a.application_status FROM users u '
        . 'LEFT JOIN instructor_applications a ON a.instructor_id=u.id '
        . 'WHERE u.email=:email LIMIT 1 FOR UPDATE'
    );
    $existingStatement->execute(['email' => $email]);
    $existing = $existingStatement->fetch();

    if (is_array($existing)) {
        if ((string) ($existing['role'] ?? '') !== 'instructor') {
            throw new DomainException('That email already belongs to another CourseHub account. Use a different email for the Instructor application.');
        }

        $status = (string) ($existing['status'] ?? '');
        $applicationStatus = (string) ($existing['application_status'] ?? '');
        if ($status === 'active' || $applicationStatus === 'approved') {
            throw new DomainException('This Instructor account is already approved. Use the Instructor sign-in page.');
        }
        if ($status === 'inactive' || $applicationStatus === 'pending') {
            throw new DomainException('An Instructor application with this email is already awaiting administrator review.');
        }
        if ($status !== 'blocked' || $applicationStatus !== 'rejected') {
            throw new DomainException('This Instructor account cannot submit another application in its current state.');
        }

        $userId = (int) $existing['id'];
        $updateUser = $database->prepare(
            'UPDATE users SET full_name=:full_name, password=:password, phone=:phone, bio=:bio, '
            . 'profile_image=:profile_image, identity_document=:identity_document, status=\'inactive\', '
            . 'profile_image_changed_at=NULL, last_login_at=NULL '
            . 'WHERE id=:id AND role=\'instructor\' AND status=\'blocked\''
        );
        $updateUser->execute([
            'full_name' => $fullName,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'phone' => $phone !== '' ? $phone : null,
            'bio' => $bio,
            'profile_image' => $profileImage,
            'identity_document' => $identityDocument,
            'id' => $userId,
        ]);
        if ($updateUser->rowCount() !== 1) {
            throw new DomainException('The Instructor account changed while the new application was being submitted. Please try again.');
        }

        $updateApplication = $database->prepare(
            'UPDATE instructor_applications SET application_status=\'pending\', application_note=:bio, '
            . 'professional_headline=:headline, expertise=:expertise, teaching_experience=:experience, '
            . 'social_profile_url=:social, course_subjects=:subjects, agreed_rules_at=NOW(), '
            . 'review_note=NULL, reviewed_by=NULL, reviewed_at=NULL, created_at=NOW(), updated_at=NOW() '
            . 'WHERE instructor_id=:id AND application_status=\'rejected\''
        );
        $updateApplication->execute([
            'bio' => $bio,
            'headline' => $professionalHeadline,
            'expertise' => $expertise,
            'experience' => $teachingExperience,
            'social' => $socialProfileUrl !== '' ? $socialProfileUrl : null,
            'subjects' => $courseSubjects,
            'id' => $userId,
        ]);
        if ($updateApplication->rowCount() !== 1) {
            throw new DomainException('The rejected Instructor application could not be reopened.');
        }

        $revokeSessions = $database->prepare(
            'UPDATE identity_sessions SET revoked_at=NOW() WHERE user_id=:user_id AND revoked_at IS NULL'
        );
        $revokeSessions->execute(['user_id' => $userId]);

        $notification = $database->prepare(
            'INSERT INTO notifications (user_id, title, message, notification_type) '
            . 'VALUES (:user_id, :title, :message, \'instructor_application\')'
        );
        $notification->execute([
            'user_id' => $userId,
            'title' => 'Instructor application resubmitted',
            'message' => 'Your corrected Instructor application was submitted again and is awaiting administrator review.',
        ]);

        $database->commit();
        $respond([
            'message' => 'Your corrected Instructor application was resubmitted using the same email and is awaiting administrator review.',
            'status' => 'inactive',
            'reapplication' => true,
            'old_profile_image' => (string) ($existing['profile_image'] ?? ''),
            'old_identity_document' => (string) ($existing['identity_document'] ?? ''),
        ], 201);
    }

    $insertUser = $database->prepare(
        'INSERT INTO users (full_name, email, password, phone, role, bio, profile_image, identity_document, status) '
        . 'VALUES (:full_name, :email, :password, :phone, \'instructor\', :bio, :profile_image, :identity_document, \'inactive\')'
    );
    $insertUser->execute([
        'full_name' => $fullName,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'phone' => $phone !== '' ? $phone : null,
        'bio' => $bio,
        'profile_image' => $profileImage,
        'identity_document' => $identityDocument,
    ]);
    $userId = (int) $database->lastInsertId();

    $insertApplication = $database->prepare(
        'INSERT INTO instructor_applications '
        . '(instructor_id, application_note, professional_headline, expertise, teaching_experience, social_profile_url, course_subjects, agreed_rules_at) '
        . 'VALUES (:instructor_id, :application_note, :professional_headline, :expertise, :teaching_experience, :social_profile_url, :course_subjects, NOW())'
    );
    $insertApplication->execute([
        'instructor_id' => $userId,
        'application_note' => $bio,
        'professional_headline' => $professionalHeadline,
        'expertise' => $expertise,
        'teaching_experience' => $teachingExperience,
        'social_profile_url' => $socialProfileUrl !== '' ? $socialProfileUrl : null,
        'course_subjects' => $courseSubjects,
    ]);

    $database->commit();
    $respond([
        'message' => 'Instructor account application submitted for administrator approval.',
        'status' => 'inactive',
        'reapplication' => false,
    ], 201);
} catch (InvalidArgumentException $exception) {
    $rollback($database);
    $respond(['error' => $exception->getMessage()], 422);
} catch (DomainException $exception) {
    $rollback($database);
    $respond(['error' => $exception->getMessage()], 409);
} catch (JsonException) {
    $rollback($database);
    $respond(['error' => 'Malformed JSON request.'], 400);
} catch (PDOException $exception) {
    $rollback($database);
    error_log('Instructor registration database failure [' . $requestId . ']: ' . $exception->getMessage());
    if ($exception->getCode() === '23000') {
        $respond(['error' => 'That email is already connected to an account that cannot be reused for this application.'], 409);
    }
    $respond(['error' => 'The Instructor application could not be saved.'], 409);
} catch (Throwable $exception) {
    $rollback($database);
    error_log('Instructor registration failure [' . $requestId . ']: ' . $exception->getMessage());
    $respond(['error' => 'The Instructor application service is unavailable.'], 503);
}
