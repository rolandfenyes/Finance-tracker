<?php
function handle_register(PDO $pdo) {
    require_once __DIR__ . '/helpers.php';
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $name = trim($_POST['full_name'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
        $_SESSION['flash'] = __('Invalid email or password too short');
        redirect('/register');
    }
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users(email,password_hash,full_name,full_name_search) VALUES(?,?,?,?) RETURNING id');
    try {
        [$encryptedName, $searchName] = user_prepare_full_name_fields($name);
        $stmt->execute([$email, $hash, $encryptedName, $searchName]);
        $uid = (int)$stmt->fetchColumn();
        $_SESSION['uid'] = $uid;
        $_SESSION['role'] = ROLE_FREE;
        $_SESSION['status'] = USER_STATUS_ACTIVE;
        // Add default currency
        $pdo->prepare('INSERT INTO user_currencies(user_id,code,is_main) VALUES(?,?,true)')
            ->execute([$uid,'HUF']);
        // Initialize baby steps
        for ($i=1;$i<=7;$i++) { $pdo->prepare('INSERT INTO baby_steps(user_id,step,status) VALUES(?,?,?)')->execute([$uid,$i,'in_progress']); }
        redirect('/');
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Registration failed. Please contact support.');
        redirect('/register');
    }
}

const REMEMBER_COOKIE_NAME = 'remember_token';
const REMEMBER_COOKIE_DAYS = 30;

function remember_login(PDO $pdo, int $userId): void {
    $selector = bin2hex(random_bytes(6));
    $validator = bin2hex(random_bytes(32));
    $hash = hash('sha256', $validator);
    $expiresAt = (new DateTimeImmutable('+' . REMEMBER_COOKIE_DAYS . ' days'))->format('Y-m-d H:i:s');

    $pdo->prepare('DELETE FROM user_remember_tokens WHERE user_id = ?')->execute([$userId]);
    $stmt = $pdo->prepare('INSERT INTO user_remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, $selector, $hash, $expiresAt]);

    $cookieValue = $selector . ':' . $validator;
    setcookie(REMEMBER_COOKIE_NAME, $cookieValue, [
        'expires' => time() + REMEMBER_COOKIE_DAYS * 86400,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[REMEMBER_COOKIE_NAME] = $cookieValue;
}

function forget_remember_token(?PDO $pdo = null, ?int $userId = null): void {
    if ($pdo && $userId) {
        $pdo->prepare('DELETE FROM user_remember_tokens WHERE user_id = ?')->execute([$userId]);
    }

    if (!empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
        setcookie(REMEMBER_COOKIE_NAME, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[REMEMBER_COOKIE_NAME]);
    }
}

function attempt_remembered_login(PDO $pdo): void {
    if (is_logged_in()) {
        return;
    }

    $cookie = $_COOKIE[REMEMBER_COOKIE_NAME] ?? '';
    if (!$cookie || strpos($cookie, ':') === false) {
        forget_remember_token();
        return;
    }

    [$selector, $validator] = explode(':', $cookie, 2);
    if (!$selector || !$validator) {
        forget_remember_token();
        return;
    }

    $stmt = $pdo->prepare('SELECT user_id, token_hash, expires_at FROM user_remember_tokens WHERE selector = ? LIMIT 1');
    $stmt->execute([$selector]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        forget_remember_token();
        return;
    }

    $expiresAt = strtotime($row['expires_at'] ?? '');
    if (!$expiresAt || $expiresAt < time()) {
        forget_remember_token($pdo, (int)$row['user_id']);
        return;
    }

    $expected = $row['token_hash'];
    if (!hash_equals($expected, hash('sha256', $validator))) {
        forget_remember_token($pdo, (int)$row['user_id']);
        return;
    }

    $userId = (int)$row['user_id'];
    $_SESSION['uid'] = $userId;
    refresh_user_role($pdo, $userId);

    if (current_user_status() === USER_STATUS_INACTIVE) {
        forget_remember_token($pdo, $userId);
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        return;
    }

    log_user_login_activity($pdo, $userId, true, '', 'remember');

    // Rotate token to prevent replay
    $newValidator = bin2hex(random_bytes(32));
    $newHash = hash('sha256', $newValidator);
    $newExpiry = (new DateTimeImmutable('+' . REMEMBER_COOKIE_DAYS . ' days'))->format('Y-m-d H:i:s');
    $update = $pdo->prepare('UPDATE user_remember_tokens SET token_hash = ?, expires_at = ? WHERE selector = ?');
    $update->execute([$newHash, $newExpiry, $selector]);

    $newCookie = $selector . ':' . $newValidator;
    setcookie(REMEMBER_COOKIE_NAME, $newCookie, [
        'expires' => time() + REMEMBER_COOKIE_DAYS * 86400,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[REMEMBER_COOKIE_NAME] = $newCookie;
}

function post_login_redirect_path(PDO $pdo, int $userId): string {
    $stmt = $pdo->prepare('SELECT needs_tutorial, role, status FROM users WHERE id=?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $role = normalize_user_role($row['role'] ?? null);
    $_SESSION['role'] = $role;

    $status = normalize_user_status($row['status'] ?? null);
    $_SESSION['status'] = $status;
    if ($status === USER_STATUS_INACTIVE) {
        return '/login';
    }

    if ($role === ROLE_ADMIN) {
        return '/admin';
    }

    $needsTutorial = (bool)($row['needs_tutorial'] ?? false);
    if ($needsTutorial) {
        $pdo->prepare('UPDATE users SET needs_tutorial = FALSE WHERE id = ?')->execute([$userId]);
        return '/tutorial';
    }

    return '/';
}

function handle_login(PDO $pdo) {
    require_once __DIR__ . '/helpers.php';
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT id, password_hash, role, status FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && password_verify($pass, $row['password_hash'])) {
        $uid = (int)$row['id'];
        $_SESSION['uid'] = $uid;
        $role = normalize_user_role($row['role'] ?? null);
        $_SESSION['role'] = $role;

        $status = normalize_user_status($row['status'] ?? null);
        if ($status === USER_STATUS_INACTIVE) {
            $_SESSION['flash'] = __('Your account is inactive. Please contact support.');
            forget_remember_token($pdo, $uid);
            unset($_SESSION['uid']);
            redirect('/login');
        }

        $_SESSION['status'] = $status;

        if (!empty($_POST['remember'])) {
            remember_login($pdo, $uid);
        } else {
            forget_remember_token($pdo, $uid);
        }

        log_user_login_activity($pdo, $uid, true, $email, 'password');
        $target = post_login_redirect_path($pdo, $uid);
        redirect($target);
    }
    $_SESSION['flash'] = __('Invalid credentials');
    redirect('/login');
}

function handle_logout() {
    require_once __DIR__ . '/helpers.php';
    global $pdo;
    $userId = uid();
    if ($pdo instanceof PDO) {
        forget_remember_token($pdo, $userId);
    } else {
        forget_remember_token();
    }

    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    header('Location: /login');
    exit;
}
