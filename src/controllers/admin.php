<?php

require_once __DIR__ . '/../helpers.php';

function admin_normalize_redirect(?string $value, string $fallback = '/admin/users'): string
{
    $value = trim((string)$value);
    if ($value !== '' && str_starts_with($value, '/')) {
        if (str_starts_with($value, '/admin')) {
            return $value;
        }
    }

    return $fallback;
}

function admin_dashboard(PDO $pdo): void
{
    require_admin();

    $stats = [
        'users' => 0,
        'transactions' => 0,
        'goals' => 0,
        'loans' => 0,
    ];

    $countQueries = [
        'users' => 'SELECT COUNT(*) FROM users',
        'transactions' => 'SELECT COUNT(*) FROM transactions',
        'goals' => 'SELECT COUNT(*) FROM goals',
        'loans' => 'SELECT COUNT(*) FROM loans',
    ];

    foreach ($countQueries as $key => $sql) {
        try {
            $value = $pdo->query($sql);
            $stats[$key] = $value ? (int)$value->fetchColumn() : 0;
        } catch (Throwable $e) {
            $stats[$key] = 0;
        }
    }

    $recentUsers = [];
    try {
        $stmt = $pdo->query('SELECT id, email, full_name, role, status, email_verified_at, created_at FROM users ORDER BY created_at DESC LIMIT 6');
        if ($stmt instanceof PDOStatement) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $recentUsers[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'email' => (string)($row['email'] ?? ''),
                    'role' => normalize_user_role($row['role'] ?? null),
                    'status' => normalize_user_status($row['status'] ?? null),
                    'full_name' => pii_decrypt($row['full_name'] ?? null),
                    'created_at' => $row['created_at'] ?? null,
                    'email_verified_at' => $row['email_verified_at'] ?? null,
                ];
            }
        }
    } catch (Throwable $e) {
        $recentUsers = [];
    }

    $pendingMigrations = null;
    $totalMigrations = null;
    try {
        $dir = realpath(__DIR__ . '/../../migrations');
        if ($dir !== false) {
            $files = glob($dir . '/*.sql');
            if ($files !== false) {
                $totalMigrations = count($files);
                $tableExistsStmt = $pdo->query("SELECT to_regclass('public.schema_migrations')");
                $applied = 0;
                if ($tableExistsStmt instanceof PDOStatement) {
                    $tableExists = (string)$tableExistsStmt->fetchColumn();
                    if ($tableExists !== '') {
                        $appliedStmt = $pdo->query('SELECT COUNT(*) FROM schema_migrations');
                        $applied = $appliedStmt ? (int)$appliedStmt->fetchColumn() : 0;
                    }
                }
                $pendingMigrations = max(0, $totalMigrations - $applied);
            }
        }
    } catch (Throwable $e) {
        $pendingMigrations = null;
    }

    view('admin/dashboard', [
        'pageTitle' => __('Administrator dashboard'),
        'stats' => $stats,
        'recentUsers' => $recentUsers,
        'pendingMigrations' => $pendingMigrations,
        'totalMigrations' => $totalMigrations,
        'userManagementUrl' => '/admin/users',
    ]);
}

function admin_update_role(PDO $pdo): void
{
    require_admin();
    verify_csrf();

    $userId = (int)($_POST['user_id'] ?? 0);
    $role = strtolower(trim((string)($_POST['role'] ?? '')));
    $allowedRoles = [ROLE_FREE, ROLE_PREMIUM, ROLE_ADMIN];
    $redirectTo = admin_normalize_redirect($_POST['redirect'] ?? null);

    if ($userId <= 0 || !in_array($role, $allowedRoles, true)) {
        $_SESSION['flash'] = __('Invalid role selection.');
        redirect($redirectTo);
    }

    $stmt = $pdo->prepare('SELECT id, role, email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['flash'] = __('User not found.');
        redirect($redirectTo);
    }

    if ($userId === uid() && $role !== ROLE_ADMIN) {
        $_SESSION['flash'] = __('You cannot remove your own administrator access.');
        redirect($redirectTo);
    }

    if (normalize_user_role($user['role'] ?? null) === $role) {
        $_SESSION['flash_success'] = __('No changes were necessary.');
        redirect($redirectTo);
    }

    try {
        $update = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
        $update->execute([$role, $userId]);
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Could not update role. Please try again.');
        redirect($redirectTo);
    }

    if ($userId === uid()) {
        $_SESSION['role'] = $role;
    }

    $email = $user['email'] ?? '';
    $message = match ($role) {
        ROLE_ADMIN => __(':email is now an administrator.', ['email' => $email]),
        ROLE_PREMIUM => __(':email is now a premium user.', ['email' => $email]),
        default => __(':email is now a free user.', ['email' => $email]),
    };

    $_SESSION['flash_success'] = $message;
    redirect($redirectTo);
}
function admin_users_index(PDO $pdo): void
{
    require_admin();

    $perPage = 25;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $search = trim((string)($_GET['q'] ?? ''));
    $roleFilter = trim((string)($_GET['role'] ?? ''));
    $statusFilter = trim((string)($_GET['status'] ?? ''));
    $verifiedFilter = trim((string)($_GET['verified'] ?? ''));
    $focusId = (int)($_GET['focus'] ?? 0);

    $where = [];
    $params = [];

    if ($search !== '') {
        $term = '%' . strtolower($search) . '%';
        $where[] = '(LOWER(u.email) LIKE ? OR LOWER(COALESCE(u.full_name_search, \'\')) LIKE ?)';
        $params[] = $term;
        $params[] = $term;
    }

    $roleApplied = '';
    if ($roleFilter !== '') {
        $normalized = normalize_user_role($roleFilter, true);
        if (in_array($normalized, [ROLE_FREE, ROLE_PREMIUM, ROLE_ADMIN], true)) {
            $roleApplied = $normalized;
            $where[] = 'LOWER(u.role) = ?';
            $params[] = $roleApplied;
        }
    }

    $statusApplied = '';
    if ($statusFilter !== '') {
        $statusApplied = normalize_user_status($statusFilter);
        $where[] = 'LOWER(u.status) = ?';
        $params[] = $statusApplied;
    }

    $verifiedApplied = '';
    if ($verifiedFilter === 'yes') {
        $where[] = 'u.email_verified_at IS NOT NULL';
        $verifiedApplied = 'yes';
    } elseif ($verifiedFilter === 'no') {
        $where[] = 'u.email_verified_at IS NULL';
        $verifiedApplied = 'no';
    }

    $whereClause = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $countSql = 'SELECT COUNT(*) FROM users u' . $whereClause;
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $index => $value) {
        $countStmt->bindValue($index + 1, $value);
    }
    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();

    $pageCount = max(1, (int)ceil($total / $perPage));
    if ($page > $pageCount) {
        $page = $pageCount;
    }

    $offset = ($page - 1) * $perPage;

    $sql = <<<SQL
SELECT u.id, u.email, u.full_name, u.role, u.status, u.created_at, u.email_verified_at, u.deactivated_at,
       la.last_login_at, la.last_login_ip, la.last_login_user_agent
  FROM users u
  LEFT JOIN LATERAL (
      SELECT created_at AS last_login_at, ip_address AS last_login_ip, user_agent AS last_login_user_agent
        FROM user_login_activity
       WHERE user_id = u.id AND success = TRUE
       ORDER BY created_at DESC
       LIMIT 1
  ) la ON TRUE
{$whereClause}
 ORDER BY u.created_at DESC
 LIMIT :limit OFFSET :offset
SQL;

    $stmt = $pdo->prepare($sql);
    foreach ($params as $index => $value) {
        $stmt->bindValue($index + 1, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $users = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $users[] = [
            'id' => (int)($row['id'] ?? 0),
            'email' => (string)($row['email'] ?? ''),
            'role' => normalize_user_role($row['role'] ?? null),
            'status' => normalize_user_status($row['status'] ?? null),
            'full_name' => pii_decrypt($row['full_name'] ?? null),
            'created_at' => $row['created_at'] ?? null,
            'email_verified_at' => $row['email_verified_at'] ?? null,
            'deactivated_at' => $row['deactivated_at'] ?? null,
            'last_login_at' => $row['last_login_at'] ?? null,
            'last_login_ip' => $row['last_login_ip'] ?? null,
            'last_login_user_agent' => $row['last_login_user_agent'] ?? null,
        ];
    }

    $queryBase = [];
    if ($search !== '') {
        $queryBase['q'] = $search;
    }
    if ($roleApplied !== '') {
        $queryBase['role'] = $roleApplied;
    }
    if ($statusApplied !== '') {
        $queryBase['status'] = $statusApplied;
    }
    if ($verifiedApplied !== '') {
        $queryBase['verified'] = $verifiedApplied;
    }
    if ($focusId > 0) {
        $queryBase['focus'] = $focusId;
    }

    $prevUrl = $page > 1
        ? '/admin/users?' . http_build_query(array_merge($queryBase, ['page' => $page - 1]), '', '&', PHP_QUERY_RFC3986)
        : null;
    $nextUrl = $page < $pageCount
        ? '/admin/users?' . http_build_query(array_merge($queryBase, ['page' => $page + 1]), '', '&', PHP_QUERY_RFC3986)
        : null;

    $currentUrl = admin_normalize_redirect($_SERVER['REQUEST_URI'] ?? '/admin/users');

    $focusUser = null;
    $focusActivity = [];
    if ($focusId > 0) {
        foreach ($users as $candidate) {
            if ($candidate['id'] === $focusId) {
                $focusUser = $candidate;
                break;
            }
        }

        if ($focusUser === null) {
            $detail = $pdo->prepare('SELECT id, email, full_name, role, status, email_verified_at, created_at FROM users WHERE id = ? LIMIT 1');
            $detail->execute([$focusId]);
            $row = $detail->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $focusUser = [
                    'id' => (int)($row['id'] ?? 0),
                    'email' => (string)($row['email'] ?? ''),
                    'role' => normalize_user_role($row['role'] ?? null),
                    'status' => normalize_user_status($row['status'] ?? null),
                    'full_name' => pii_decrypt($row['full_name'] ?? null),
                    'created_at' => $row['created_at'] ?? null,
                    'email_verified_at' => $row['email_verified_at'] ?? null,
                ];
            }
        }

        $activityStmt = $pdo->prepare('SELECT success, method, ip_address, user_agent, created_at FROM user_login_activity WHERE user_id = ? ORDER BY created_at DESC LIMIT 25');
        $activityStmt->execute([$focusId]);
        while ($row = $activityStmt->fetch(PDO::FETCH_ASSOC)) {
            $focusActivity[] = [
                'success' => (bool)($row['success'] ?? false),
                'method' => (string)($row['method'] ?? ''),
                'ip_address' => $row['ip_address'] ?? null,
                'user_agent' => $row['user_agent'] ?? null,
                'created_at' => $row['created_at'] ?? null,
            ];
        }
    }

    $roleOptions = [
        ROLE_FREE => __('Free user'),
        ROLE_PREMIUM => __('Premium user'),
        ROLE_ADMIN => __('Administrator'),
    ];

    $statusOptions = [
        USER_STATUS_ACTIVE => __('Active'),
        USER_STATUS_INACTIVE => __('Inactive'),
    ];

    $verifiedOptions = [
        'yes' => __('Verified'),
        'no' => __('Unverified'),
    ];

    view('admin/users', [
        'pageTitle' => __('User management'),
        'users' => $users,
        'filters' => [
            'search' => $search,
            'role' => $roleApplied,
            'status' => $statusApplied,
            'verified' => $verifiedApplied,
            'page' => $page,
        ],
        'pagination' => [
            'page' => $page,
            'pages' => $pageCount,
            'total' => $total,
            'prev' => $prevUrl,
            'next' => $nextUrl,
        ],
        'roleOptions' => $roleOptions,
        'statusOptions' => $statusOptions,
        'verifiedOptions' => $verifiedOptions,
        'currentUrl' => $currentUrl,
        'focusId' => $focusId,
        'focusUser' => $focusUser,
        'focusActivity' => $focusActivity,
    ]);
}

function admin_users_reset_password(PDO $pdo): void
{
    require_admin();
    verify_csrf();

    $userId = (int)($_POST['user_id'] ?? 0);
    $redirectTo = admin_normalize_redirect($_POST['redirect'] ?? null);

    if ($userId <= 0) {
        $_SESSION['flash'] = __('User not found.');
        redirect($redirectTo);
    }

    $stmt = $pdo->prepare('SELECT id, email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['flash'] = __('User not found.');
        redirect($redirectTo);
    }

    try {
        $raw = str_replace(['+', '/', '='], '', base64_encode(random_bytes(12)));
        if (strlen($raw) < 10) {
            $raw .= bin2hex(random_bytes(4));
        }
        $tempPassword = substr($raw, 0, 12);
        $hash = password_hash($tempPassword, PASSWORD_DEFAULT);
        $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $update->execute([$hash, $userId]);
        forget_remember_token($pdo, $userId);
        $_SESSION['flash_success'] = __('Password reset. Temporary password: :password', ['password' => $tempPassword]);
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Could not reset password.');
    }

    redirect($redirectTo);
}

function admin_users_resend_verification(PDO $pdo): void
{
    require_admin();
    verify_csrf();

    $userId = (int)($_POST['user_id'] ?? 0);
    $redirectTo = admin_normalize_redirect($_POST['redirect'] ?? null);

    if ($userId <= 0) {
        $_SESSION['flash'] = __('User not found.');
        redirect($redirectTo);
    }

    $stmt = $pdo->prepare('SELECT id, email, full_name, email_verification_token, email_verified_at, desired_language FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['flash'] = __('User not found.');
        redirect($redirectTo);
    }

    if (!empty($user['email_verified_at'])) {
        $_SESSION['flash_success'] = __('Email address is already verified.');
        redirect($redirectTo);
    }

    require_once __DIR__ . '/../services/email_notifications.php';

    try {
        email_send_verification($pdo, $user, true);
        $_SESSION['flash_success'] = __('Verification email sent.');
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Could not send verification email.');
    }

    redirect($redirectTo);
}

function admin_users_reset_email(PDO $pdo): void
{
    require_admin();
    verify_csrf();

    $userId = (int)($_POST['user_id'] ?? 0);
    $newEmail = trim((string)($_POST['new_email'] ?? ''));
    $redirectTo = admin_normalize_redirect($_POST['redirect'] ?? null);

    if ($userId <= 0) {
        $_SESSION['flash'] = __('User not found.');
        redirect($redirectTo);
    }

    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash'] = __('Please provide a valid email address.');
        redirect($redirectTo);
    }

    $conflict = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
    $conflict->execute([$newEmail, $userId]);
    if ($conflict->fetchColumn()) {
        $_SESSION['flash'] = __('Email address is already in use.');
        redirect($redirectTo);
    }

    try {
        $pdo->prepare('UPDATE users SET email = ?, email_verified_at = NULL WHERE id = ?')->execute([$newEmail, $userId]);
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Could not update email.');
        redirect($redirectTo);
    }

    $stmt = $pdo->prepare('SELECT id, email, full_name, email_verification_token, email_verified_at, desired_language FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    require_once __DIR__ . '/../services/email_notifications.php';

    try {
        email_send_verification($pdo, $user, true);
        $_SESSION['flash_success'] = __('Email reset. Verification sent to :email.', ['email' => $newEmail]);
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Email updated but verification email could not be sent.');
    }

    redirect($redirectTo);
}

function admin_users_update_status(PDO $pdo): void
{
    require_admin();
    verify_csrf();

    $userId = (int)($_POST['user_id'] ?? 0);
    $status = normalize_user_status($_POST['status'] ?? USER_STATUS_ACTIVE);
    $redirectTo = admin_normalize_redirect($_POST['redirect'] ?? null);

    if ($userId <= 0) {
        $_SESSION['flash'] = __('User not found.');
        redirect($redirectTo);
    }

    if ($status === USER_STATUS_INACTIVE && $userId === uid()) {
        $_SESSION['flash'] = __('You cannot deactivate your own account.');
        redirect($redirectTo);
    }

    $stmt = $pdo->prepare('SELECT id, email, status FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['flash'] = __('User not found.');
        redirect($redirectTo);
    }

    $currentStatus = normalize_user_status($user['status'] ?? null);
    if ($currentStatus === $status) {
        $_SESSION['flash_success'] = __('No changes were necessary.');
        redirect($redirectTo);
    }

    try {
        if ($status === USER_STATUS_INACTIVE) {
            $update = $pdo->prepare('UPDATE users SET status = ?, deactivated_at = NOW() WHERE id = ?');
            $update->execute([$status, $userId]);
            forget_remember_token($pdo, $userId);
        } else {
            $update = $pdo->prepare('UPDATE users SET status = ?, deactivated_at = NULL WHERE id = ?');
            $update->execute([$status, $userId]);
        }
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Could not update status.');
        redirect($redirectTo);
    }

    if ($userId === uid()) {
        $_SESSION['status'] = $status;
    }

    $label = $status === USER_STATUS_ACTIVE ? __('Active') : __('Inactive');
    $_SESSION['flash_success'] = __('Status updated to :status.', ['status' => $label]);
    redirect($redirectTo);
}
