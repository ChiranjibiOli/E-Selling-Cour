<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/database.php';

Auth::guestOnly();

$message = '';
$messageType = '';
$email = '';
$allowedStudentRedirects = [
    'student-dashboard.php',
    'student-browse-courses.php',
    'student-my-courses.php',
    'cart.php',
    'checkout.php',
    'course-details.php',
];
$redirect = trim((string) ($_POST['redirect'] ?? $_GET['redirect'] ?? ''));
$safeRedirect = Security::safeInternalPath($redirect, $allowedStudentRedirects) ?? '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $rateIdentity = Security::clientIp() . '|' . $email;
    $retryAfter = Security::rateLimitRetryAfter('login', $rateIdentity, 5, 900, 900);

    if ($retryAfter > 0) {
        $minutes = max(1, (int) ceil($retryAfter / 60));
        $message = "Too many login attempts. Try again in about {$minutes} minute(s).";
        $messageType = 'error';
    } elseif ($email === '') {
        $message = 'Email is required.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } elseif ($password === '' || strlen($password) > 4096) {
        $message = 'Password is required.';
        $messageType = 'error';
    } else {
        $transactionStarted = false;

        try {
            $stmt = $conn->prepare(
                'SELECT id, full_name, email, password, role, status FROM users WHERE LOWER(email) = ? LIMIT 1'
            );
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();

            static $dummyHash = null;
            if (!is_string($dummyHash)) {
                $dummyHash = password_hash('coursehub-invalid-login-placeholder', PASSWORD_DEFAULT);
            }

            $storedHash = $user ? (string) $user['password'] : $dummyHash;
            $passwordMatches = password_verify($password, $storedHash);

            if (!$user || !$passwordMatches) {
                $blockedFor = Security::recordRateLimitFailure('login', $rateIdentity, 5, 900, 900);
                usleep(random_int(180000, 320000));

                if ($blockedFor > 0) {
                    $minutes = max(1, (int) ceil($blockedFor / 60));
                    $message = "Too many login attempts. Try again in about {$minutes} minute(s).";
                } else {
                    $message = 'Invalid email or password.';
                }
                $messageType = 'error';
            } elseif ((string) $user['status'] !== 'active') {
                Security::clearRateLimit('login', $rateIdentity);
                $message = (string) $user['role'] === 'instructor'
                    ? 'Your instructor account is waiting for admin approval.'
                    : 'Your account is not active. Contact the administrator.';
                $messageType = 'error';
            } else {
                $userId = (int) $user['id'];
                $conn->begin_transaction();
                $transactionStarted = true;

                if (password_needs_rehash((string) $user['password'], PASSWORD_DEFAULT)) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $rehashStmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                    $rehashStmt->bind_param('si', $newHash, $userId);
                    $rehashStmt->execute();
                    $rehashStmt->close();
                }

                $loginStmt = $conn->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
                $loginStmt->bind_param('i', $userId);
                $loginStmt->execute();
                $loginStmt->close();
                $conn->commit();
                $transactionStarted = false;

                Security::clearRateLimit('login', $rateIdentity);
                Auth::login($user);

                if ($safeRedirect !== '' && (string) $user['role'] === 'student') {
                    Auth::redirect($safeRedirect);
                }

                Auth::redirectBasedOnRole();
            }
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                try {
                    $conn->rollback();
                } catch (Throwable) {
                }
            }

            error_log('Login failed: ' . $exception->getMessage());
            $message = 'Login could not be completed. Please try again.';
            $messageType = 'error';
        }
    }
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<link rel="stylesheet" href="assets/css/pages/public/auth.css?v=12">
<main class="auth-page">
    <section class="auth-section">
        <div class="container auth-container">
            <div class="auth-card">
                <div class="auth-left">
                    <span class="auth-badge">Welcome Back</span>
                    <h1>Login to Your Account</h1>
                    <p>Access your courses, continue learning, or manage your instructor content from your dashboard.</p>
                    <ul class="auth-benefits">
                        <li>Continue your learning anytime</li>
                        <li>Access purchased lifetime courses</li>
                        <li>Manage courses and profile easily</li>
                    </ul>
                </div>

                <div class="auth-right">
                    <h2>Login Now</h2>

                    <?php if ($message !== ''): ?>
                        <div class="form-message <?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="login.php" id="loginForm" novalidate>
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($safeRedirect, ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter your email" maxlength="150" autocomplete="email" required>
                        </div>

                        <div class="form-group password-group">
                            <label for="password">Password</label>
                            <div class="password-field">
                                <input type="password" id="password" name="password" placeholder="Enter your password" maxlength="4096" autocomplete="current-password" required>
                                <button type="button" class="toggle-password" data-target="password">Show</button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary auth-submit-btn">Login</button>
                        <p class="auth-switch">Don't have an account? <a href="register.php">Register here</a></p>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
