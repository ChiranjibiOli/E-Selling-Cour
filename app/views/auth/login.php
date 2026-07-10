<?php

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/database.php';

Auth::guestOnly();

$message = '';
$messageType = '';
$email = '';
$redirect = trim((string) ($_POST['redirect'] ?? $_GET['redirect'] ?? ''));
$safeRedirect = preg_match('/^[A-Za-z0-9_-]+\.php(?:\?[A-Za-z0-9_=&%+.\-]*)?$/', $redirect)
    ? $redirect
    : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_user'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $attemptKey = hash('sha256', strtolower($email) . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'local'));
    $attempt = $_SESSION['login_attempts'][$attemptKey] ?? ['count' => 0, 'time' => 0];
    $isRateLimited = (int) $attempt['count'] >= 5 && (time() - (int) $attempt['time']) < 300;

    if ($isRateLimited) {
        $message = 'Too many login attempts. Please wait five minutes and try again.';
        $messageType = 'error';
    } elseif ($email === '') {
        $message = 'Email is required.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } elseif ($password === '') {
        $message = 'Password is required.';
        $messageType = 'error';
    } else {
        $sql = "SELECT id, full_name, email, password, role, status FROM users WHERE email = ? LIMIT 1";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();

                if (!password_verify($password, $user['password'])) {
                    $message = 'Invalid email or password.';
                    $messageType = 'error';
                    $_SESSION['login_attempts'][$attemptKey] = [
                        'count' => (int) $attempt['count'] + 1,
                        'time' => time(),
                    ];
                } elseif ($user['status'] === 'blocked') {
                    $message = 'Your account is blocked.';
                    $messageType = 'error';
                } elseif ($user['role'] === 'instructor' && $user['status'] !== 'active') {
                    $message = 'Your instructor account is waiting for admin approval.';
                    $messageType = 'error';
                } else {
                    unset($_SESSION['login_attempts'][$attemptKey]);
                    $userId = (int) $user['id'];

                    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $rehashStmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                        $rehashStmt->bind_param('si', $newHash, $userId);
                        $rehashStmt->execute();
                        $rehashStmt->close();
                    }

                    $loginStampStmt = $conn->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
                    $loginStampStmt->bind_param('i', $userId);
                    $loginStampStmt->execute();
                    $loginStampStmt->close();

                    Auth::login($user);

                    if ($safeRedirect !== '' && $user['role'] === 'student') {
                        Auth::redirect($safeRedirect);
                    }

                    Auth::redirectBasedOnRole();
                }
            } else {
                $message = 'Invalid email or password.';
                $messageType = 'error';
                $_SESSION['login_attempts'][$attemptKey] = [
                    'count' => (int) $attempt['count'] + 1,
                    'time' => time(),
                ];
            }

            $stmt->close();
        } else {
            $message = 'Failed to prepare login query.';
            $messageType = 'error';
        }
    }
}
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<link rel="stylesheet" href="assets/css/pages/public/auth.css?v=1">
<main class="auth-page">
    <section class="auth-section">
        <div class="container auth-container">
            <div class="auth-card">
                <div class="auth-left">
                    <span class="auth-badge">Welcome Back</span>
                    <h1>Login to Your Account</h1>
                    <p>
                        Access your courses, continue learning, or manage your instructor content from your dashboard.
                    </p>

                    <ul class="auth-benefits">
                        <li>Continue your learning anytime</li>
                        <li>Access purchased lifetime courses</li>
                        <li>Manage courses and profile easily</li>
                    </ul>
                </div>

                <div class="auth-right">
                    <h2>Login Now</h2>

                    <?php if ($message !== ''): ?>
                        <div class="form-message <?php echo htmlspecialchars($messageType); ?>">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" id="loginForm" novalidate>
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($safeRedirect); ?>">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="<?php echo htmlspecialchars($email); ?>"
                                placeholder="Enter your email"
                                maxlength="150"
                                required
                            >
                            <small id="emailFeedback" class="field-note"></small>
                        </div>

                        <div class="form-group password-group">
                            <label for="password">Password</label>
                            <div class="password-field">
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    placeholder="Enter your password"
                                    required
                                >
                                <button type="button" class="toggle-password" data-target="password">Show</button>
                            </div>
                        </div>

                        <button type="submit" name="login_user" class="btn btn-primary auth-submit-btn">
                            Login
                        </button>

                        <p class="auth-switch">
                            Don't have an account?
                            <a href="register.php">Register here</a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
