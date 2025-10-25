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
    $definitions = role_definitions();
    $allowedRoles = array_values(array_filter(array_keys($definitions), static fn ($slug) => $slug !== ROLE_GUEST));
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
    $roleDefinition = $definitions[$role] ?? null;
    $roleName = $roleDefinition['name'] ?? ucfirst($role);
    $message = __(':email is now assigned to :role.', ['email' => $email, 'role' => $roleName]);

    $_SESSION['flash_success'] = $message;
    redirect($redirectTo);
}

function admin_role_capability_fields(): array
{
    return [
        'currencies_limit' => [
            'type' => 'number',
            'label' => __('Currency limit'),
            'help' => __('Leave blank for unlimited.'),
        ],
        'goals_limit' => [
            'type' => 'number',
            'label' => __('Active goals limit'),
            'help' => __('Leave blank for unlimited.'),
        ],
        'loans_limit' => [
            'type' => 'number',
            'label' => __('Active loans limit'),
            'help' => __('Leave blank for unlimited.'),
        ],
        'categories_limit' => [
            'type' => 'number',
            'label' => __('Custom categories limit'),
            'help' => __('Leave blank for unlimited.'),
        ],
        'scheduled_payments_limit' => [
            'type' => 'number',
            'label' => __('Scheduled payments limit'),
            'help' => __('Leave blank for unlimited.'),
        ],
        'cashflow_rules_edit' => [
            'type' => 'boolean',
            'label' => __('Allow editing cashflow rules'),
            'help' => __('Enable this option so the role can manage cashflow rules.'),
        ],
    ];
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

    $roleDefinitions = role_definitions();
    $roleOptions = [];
    foreach ($roleDefinitions as $slug => $meta) {
        if ($slug === ROLE_GUEST) {
            continue;
        }
        $roleOptions[$slug] = $meta['name'] ?? ucfirst($slug);
    }

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
        $normalized = strtolower($roleFilter);
        if (isset($roleOptions[$normalized])) {
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
 LIMIT ? OFFSET ?
SQL;

    $stmt = $pdo->prepare($sql);
    foreach ($params as $index => $value) {
        $stmt->bindValue($index + 1, $value);
    }
    $stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
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

    $prevUrl = $page > 1
        ? '/admin/users?' . http_build_query(array_merge($queryBase, ['page' => $page - 1]), '', '&', PHP_QUERY_RFC3986)
        : null;
    $nextUrl = $page < $pageCount
        ? '/admin/users?' . http_build_query(array_merge($queryBase, ['page' => $page + 1]), '', '&', PHP_QUERY_RFC3986)
        : null;

    $currentUrl = admin_normalize_redirect($_SERVER['REQUEST_URI'] ?? '/admin/users');

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
    ]);
}

function admin_users_manage(PDO $pdo): void
{
    require_admin();

    $userId = (int)($_GET['id'] ?? 0);
    if ($userId <= 0) {
        $_SESSION['flash'] = __('User not found.');
        redirect('/admin/users');
    }

    $currentUrl = admin_normalize_redirect($_SERVER['REQUEST_URI'] ?? '/admin/users/manage');
    $returnParam = (string)($_GET['return'] ?? '');
    $returnTo = $returnParam !== ''
        ? admin_normalize_redirect(rawurldecode($returnParam), '/admin/users')
        : '/admin/users';

    $stmt = $pdo->prepare(<<<'SQL'
        SELECT u.id, u.email, u.full_name, u.role, u.status, u.email_verified_at, u.desired_language, u.created_at,
               GREATEST(
                   COALESCE(u.created_at, '-infinity'::timestamptz),
                   COALESCE(u.email_verified_at, '-infinity'::timestamptz),
                   COALESCE(u.deactivated_at, '-infinity'::timestamptz)
               ) AS updated_at,
               u.deactivated_at,
               la.last_login_at, la.last_login_ip, la.last_login_user_agent
          FROM users u
          LEFT JOIN LATERAL (
               SELECT created_at AS last_login_at, ip_address AS last_login_ip, user_agent AS last_login_user_agent
                 FROM user_login_activity
                WHERE user_id = u.id AND success = TRUE
                ORDER BY created_at DESC
                LIMIT 1
          ) la ON TRUE
         WHERE u.id = ?
         LIMIT 1
        SQL);
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $_SESSION['flash'] = __('User not found.');
        redirect('/admin/users');
    }

    $updatedAt = $row['updated_at'] ?? null;
    if ($updatedAt === '-infinity') {
        $updatedAt = null;
    }

    $user = [
        'id' => (int)($row['id'] ?? 0),
        'email' => (string)($row['email'] ?? ''),
        'full_name' => pii_decrypt($row['full_name'] ?? null),
        'role' => normalize_user_role($row['role'] ?? null),
        'status' => normalize_user_status($row['status'] ?? null),
        'email_verified_at' => $row['email_verified_at'] ?? null,
        'desired_language' => $row['desired_language'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $updatedAt,
        'deactivated_at' => $row['deactivated_at'] ?? null,
        'last_login_at' => $row['last_login_at'] ?? null,
        'last_login_ip' => $row['last_login_ip'] ?? null,
        'last_login_user_agent' => $row['last_login_user_agent'] ?? null,
    ];

    $activityStmt = $pdo->prepare('SELECT success, method, ip_address, user_agent, created_at FROM user_login_activity WHERE user_id = ? ORDER BY created_at DESC LIMIT 25');
    $activityStmt->execute([$userId]);
    $activity = [];
    while ($activityRow = $activityStmt->fetch(PDO::FETCH_ASSOC)) {
        $activity[] = [
            'success' => (bool)($activityRow['success'] ?? false),
            'method' => (string)($activityRow['method'] ?? ''),
            'ip_address' => $activityRow['ip_address'] ?? null,
            'user_agent' => $activityRow['user_agent'] ?? null,
            'created_at' => $activityRow['created_at'] ?? null,
        ];
    }

    $subscriptionsStmt = $pdo->prepare(<<<'SQL'
        SELECT id, plan_code, plan_name, status, billing_interval, interval_count, amount, currency,
               started_at, current_period_start, current_period_end, cancel_at, canceled_at, trial_ends_at,
               notes, created_at, updated_at
          FROM user_subscriptions
         WHERE user_id = ?
         ORDER BY COALESCE(current_period_end, created_at) DESC
        SQL);
    $subscriptionsStmt->execute([$userId]);
    $subscriptions = [];
    while ($subRow = $subscriptionsStmt->fetch(PDO::FETCH_ASSOC)) {
        $subscriptions[] = [
            'id' => (int)($subRow['id'] ?? 0),
            'plan_code' => (string)($subRow['plan_code'] ?? ''),
            'plan_name' => (string)($subRow['plan_name'] ?? ''),
            'status' => (string)($subRow['status'] ?? ''),
            'billing_interval' => (string)($subRow['billing_interval'] ?? ''),
            'interval_count' => (int)($subRow['interval_count'] ?? 0),
            'amount' => (float)($subRow['amount'] ?? 0),
            'currency' => (string)($subRow['currency'] ?? ''),
            'started_at' => $subRow['started_at'] ?? null,
            'current_period_start' => $subRow['current_period_start'] ?? null,
            'current_period_end' => $subRow['current_period_end'] ?? null,
            'cancel_at' => $subRow['cancel_at'] ?? null,
            'canceled_at' => $subRow['canceled_at'] ?? null,
            'trial_ends_at' => $subRow['trial_ends_at'] ?? null,
            'notes' => $subRow['notes'] ?? null,
            'created_at' => $subRow['created_at'] ?? null,
            'updated_at' => $subRow['updated_at'] ?? null,
        ];
    }

    $invoiceStmt = $pdo->prepare(<<<'SQL'
        SELECT id, subscription_id, invoice_number, status, total_amount, currency, issued_at, due_at,
               paid_at, failure_reason, refund_reason, notes, created_at, updated_at
          FROM user_invoices
         WHERE user_id = ?
         ORDER BY issued_at DESC
         LIMIT 50
        SQL);
    $invoiceStmt->execute([$userId]);
    $invoices = [];
    while ($invoiceRow = $invoiceStmt->fetch(PDO::FETCH_ASSOC)) {
        $invoices[] = [
            'id' => (int)($invoiceRow['id'] ?? 0),
            'subscription_id' => $invoiceRow['subscription_id'] !== null ? (int)$invoiceRow['subscription_id'] : null,
            'invoice_number' => (string)($invoiceRow['invoice_number'] ?? ''),
            'status' => (string)($invoiceRow['status'] ?? ''),
            'total_amount' => (float)($invoiceRow['total_amount'] ?? 0),
            'currency' => (string)($invoiceRow['currency'] ?? ''),
            'issued_at' => $invoiceRow['issued_at'] ?? null,
            'due_at' => $invoiceRow['due_at'] ?? null,
            'paid_at' => $invoiceRow['paid_at'] ?? null,
            'failure_reason' => $invoiceRow['failure_reason'] ?? null,
            'refund_reason' => $invoiceRow['refund_reason'] ?? null,
            'notes' => $invoiceRow['notes'] ?? null,
            'created_at' => $invoiceRow['created_at'] ?? null,
            'updated_at' => $invoiceRow['updated_at'] ?? null,
        ];
    }

    $paymentsStmt = $pdo->prepare(<<<'SQL'
        SELECT id, invoice_id, type, status, amount, currency, gateway, transaction_reference,
               failure_reason, notes, processed_at, created_at, updated_at
          FROM user_payments
         WHERE user_id = ?
         ORDER BY processed_at DESC
         LIMIT 50
        SQL);
    $paymentsStmt->execute([$userId]);
    $payments = [];
    while ($paymentRow = $paymentsStmt->fetch(PDO::FETCH_ASSOC)) {
        $payments[] = [
            'id' => (int)($paymentRow['id'] ?? 0),
            'invoice_id' => $paymentRow['invoice_id'] !== null ? (int)$paymentRow['invoice_id'] : null,
            'type' => (string)($paymentRow['type'] ?? ''),
            'status' => (string)($paymentRow['status'] ?? ''),
            'amount' => (float)($paymentRow['amount'] ?? 0),
            'currency' => (string)($paymentRow['currency'] ?? ''),
            'gateway' => $paymentRow['gateway'] ?? null,
            'transaction_reference' => $paymentRow['transaction_reference'] ?? null,
            'failure_reason' => $paymentRow['failure_reason'] ?? null,
            'notes' => $paymentRow['notes'] ?? null,
            'processed_at' => $paymentRow['processed_at'] ?? null,
            'created_at' => $paymentRow['created_at'] ?? null,
            'updated_at' => $paymentRow['updated_at'] ?? null,
        ];
    }

    $feedbackStmt = $pdo->prepare(<<<'SQL'
        SELECT id, kind, severity, status, title, message, created_at, updated_at
          FROM feedback
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 100
        SQL);
    $feedbackStmt->execute([$userId]);
    $feedback = [];
    while ($feedbackRow = $feedbackStmt->fetch(PDO::FETCH_ASSOC)) {
        $feedback[] = [
            'id' => (int)($feedbackRow['id'] ?? 0),
            'kind' => (string)($feedbackRow['kind'] ?? ''),
            'severity' => $feedbackRow['severity'] ?? null,
            'status' => (string)($feedbackRow['status'] ?? ''),
            'title' => (string)($feedbackRow['title'] ?? ''),
            'message' => (string)($feedbackRow['message'] ?? ''),
            'created_at' => $feedbackRow['created_at'] ?? null,
            'updated_at' => $feedbackRow['updated_at'] ?? null,
        ];
    }

    $feedbackResponses = [];
    if ($feedback) {
        $feedbackIds = array_map(static fn ($item) => (int)$item['id'], $feedback);
        $placeholders = implode(', ', array_fill(0, count($feedbackIds), '?'));
        $responseStmt = $pdo->prepare(
            "SELECT fr.id, fr.feedback_id, fr.admin_id, fr.message, fr.created_at, fr.updated_at, u.email, u.full_name\n" .
            "  FROM feedback_responses fr\n" .
            "  LEFT JOIN users u ON u.id = fr.admin_id\n" .
            " WHERE fr.feedback_id IN ($placeholders)\n" .
            " ORDER BY fr.created_at ASC"
        );
        $responseStmt->execute($feedbackIds);
        while ($responseRow = $responseStmt->fetch(PDO::FETCH_ASSOC)) {
            $feedbackId = (int)($responseRow['feedback_id'] ?? 0);
            if (!isset($feedbackResponses[$feedbackId])) {
                $feedbackResponses[$feedbackId] = [];
            }
            $adminName = null;
            if (!empty($responseRow['full_name'])) {
                $adminName = pii_decrypt($responseRow['full_name']);
            }
            $feedbackResponses[$feedbackId][] = [
                'id' => (int)($responseRow['id'] ?? 0),
                'admin_id' => $responseRow['admin_id'] !== null ? (int)$responseRow['admin_id'] : null,
                'admin_email' => $responseRow['email'] ?? null,
                'admin_name' => $adminName,
                'message' => (string)($responseRow['message'] ?? ''),
                'created_at' => $responseRow['created_at'] ?? null,
                'updated_at' => $responseRow['updated_at'] ?? null,
            ];
        }
    }

    $roleDefinitions = role_definitions();
    $roleOptions = [];
    foreach ($roleDefinitions as $slug => $meta) {
        if ($slug === ROLE_GUEST) {
            continue;
        }
        $roleOptions[$slug] = $meta['name'] ?? ucfirst($slug);
    }
    $roleMeta = $roleDefinitions[$user['role']] ?? null;

    $statusOptions = [
        USER_STATUS_ACTIVE => __('Active'),
        USER_STATUS_INACTIVE => __('Inactive'),
    ];

    $invoiceStatusOptions = [
        'draft' => __('Draft'),
        'open' => __('Open'),
        'past_due' => __('Past due'),
        'paid' => __('Paid'),
        'failed' => __('Failed'),
        'refunded' => __('Refunded'),
        'void' => __('Void'),
    ];

    $paymentTypeOptions = [
        'charge' => __('Charge'),
        'refund' => __('Refund'),
        'adjustment' => __('Adjustment'),
    ];

    $paymentStatusOptions = [
        'pending' => __('Pending'),
        'succeeded' => __('Succeeded'),
        'failed' => __('Failed'),
        'canceled' => __('Canceled'),
    ];

    $feedbackKindOptions = [
        'bug' => __('Bug'),
        'idea' => __('Suggestion'),
    ];

    $feedbackSeverityOptions = [
        'low' => __('Low'),
        'medium' => __('Medium'),
        'high' => __('High'),
    ];

    $feedbackStatusOptions = [
        'open' => __('Open'),
        'in_progress' => __('In progress'),
        'resolved' => __('Resolved'),
        'closed' => __('Closed'),
    ];

    view('admin/user_manage', [
        'pageTitle' => __('Manage user'),
        'user' => $user,
        'roleOptions' => $roleOptions,
        'roleDefinition' => $roleMeta,
        'roleCapabilityFields' => admin_role_capability_fields(),
        'statusOptions' => $statusOptions,
        'activity' => $activity,
        'subscriptions' => $subscriptions,
        'invoices' => $invoices,
        'payments' => $payments,
        'invoiceStatusOptions' => $invoiceStatusOptions,
        'paymentStatusOptions' => $paymentStatusOptions,
        'paymentTypeOptions' => $paymentTypeOptions,
        'feedbackEntries' => $feedback,
        'feedbackResponses' => $feedbackResponses,
        'feedbackKindOptions' => $feedbackKindOptions,
        'feedbackSeverityOptions' => $feedbackSeverityOptions,
        'feedbackStatusOptions' => $feedbackStatusOptions,
        'currentUrl' => $currentUrl,
        'returnUrl' => $returnTo,
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

function admin_users_invoice_update(PDO $pdo): void
{
    require_admin();
    verify_csrf();

    $invoiceId = (int)($_POST['invoice_id'] ?? 0);
    $userId = (int)($_POST['user_id'] ?? 0);
    $status = trim((string)($_POST['status'] ?? ''));
    $note = trim((string)($_POST['note'] ?? ''));
    $reason = trim((string)($_POST['reason'] ?? ''));
    $redirectTo = admin_normalize_redirect($_POST['redirect'] ?? null);

    if ($invoiceId <= 0 || $userId <= 0 || $status === '') {
        $_SESSION['flash'] = __('Unable to update invoice.');
        redirect($redirectTo);
    }

    $allowedStatuses = ['draft', 'open', 'past_due', 'paid', 'failed', 'refunded', 'void'];
    if (!in_array($status, $allowedStatuses, true)) {
        $_SESSION['flash'] = __('Unsupported invoice status.');
        redirect($redirectTo);
    }

    $stmt = $pdo->prepare('SELECT id, user_id FROM user_invoices WHERE id = ? LIMIT 1');
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice || (int)($invoice['user_id'] ?? 0) !== $userId) {
        $_SESSION['flash'] = __('Invoice not found.');
        redirect($redirectTo);
    }

    $noteValue = $note !== '' ? $note : null;
    $failureReason = $status === 'failed' ? ($reason !== '' ? $reason : null) : null;
    $refundReason = $status === 'refunded' ? ($reason !== '' ? $reason : null) : null;

    $assignments = [
        'status = ?',
        'notes = ?',
        'failure_reason = ?',
        'refund_reason = ?',
        'updated_at = NOW()'
    ];
    $params = [$status, $noteValue, $failureReason, $refundReason];

    if ($status === 'paid') {
        $assignments[] = 'paid_at = NOW()';
    } elseif ($status === 'failed') {
        $assignments[] = 'paid_at = NULL';
    }

    $sql = 'UPDATE user_invoices SET ' . implode(', ', $assignments) . ' WHERE id = ?';
    $params[] = $invoiceId;

    try {
        $update = $pdo->prepare($sql);
        $update->execute($params);
        $_SESSION['flash_success'] = __('Invoice updated.');
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Unable to update invoice.');
    }

    redirect($redirectTo);
}

function admin_users_payment_create(PDO $pdo): void
{
    require_admin();
    verify_csrf();

    $userId = (int)($_POST['user_id'] ?? 0);
    $invoiceId = (int)($_POST['invoice_id'] ?? 0) ?: null;
    $type = trim((string)($_POST['type'] ?? ''));
    $status = trim((string)($_POST['status'] ?? ''));
    $amountInput = trim((string)($_POST['amount'] ?? ''));
    $currency = strtoupper(trim((string)($_POST['currency'] ?? '')));
    $gateway = trim((string)($_POST['gateway'] ?? ''));
    $reference = trim((string)($_POST['reference'] ?? ''));
    $failureReasonInput = trim((string)($_POST['failure_reason'] ?? ''));
    $note = trim((string)($_POST['note'] ?? ''));
    $processedAtInput = trim((string)($_POST['processed_at'] ?? ''));
    $redirectTo = admin_normalize_redirect($_POST['redirect'] ?? null);

    if ($userId <= 0) {
        $_SESSION['flash'] = __('User not found.');
        redirect($redirectTo);
    }

    if ($invoiceId !== null) {
        $invoiceStmt = $pdo->prepare('SELECT user_id FROM user_invoices WHERE id = ? LIMIT 1');
        $invoiceStmt->execute([$invoiceId]);
        $invoiceRow = $invoiceStmt->fetch(PDO::FETCH_ASSOC);
        if (!$invoiceRow || (int)($invoiceRow['user_id'] ?? 0) !== $userId) {
            $_SESSION['flash'] = __('Invoice not found.');
            redirect($redirectTo);
        }
    }

    $allowedTypes = ['charge', 'refund', 'adjustment'];
    $allowedStatuses = ['pending', 'succeeded', 'failed', 'canceled'];

    if (!in_array($type, $allowedTypes, true) || !in_array($status, $allowedStatuses, true)) {
        $_SESSION['flash'] = __('Unsupported payment type or status.');
        redirect($redirectTo);
    }

    if ($amountInput === '' || !is_numeric($amountInput)) {
        $_SESSION['flash'] = __('Please provide a valid amount.');
        redirect($redirectTo);
    }

    $amount = (float)$amountInput;
    $currency = $currency !== '' ? $currency : 'USD';
    $failureReason = $status === 'failed' ? ($failureReasonInput !== '' ? $failureReasonInput : null) : null;
    $noteValue = $note !== '' ? $note : null;
    $gatewayValue = $gateway !== '' ? $gateway : null;
    $referenceValue = $reference !== '' ? $reference : null;

    $processedAtValue = null;
    if ($processedAtInput !== '') {
        $timestamp = strtotime($processedAtInput);
        if ($timestamp === false) {
            $_SESSION['flash'] = __('Invalid processed date.');
            redirect($redirectTo);
        }
        $processedAtValue = gmdate('Y-m-d H:i:sP', $timestamp);
    }

    try {
        $insert = $pdo->prepare(
            'INSERT INTO user_payments (user_id, invoice_id, type, status, amount, currency, gateway, transaction_reference, failure_reason, notes, processed_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, COALESCE(?, NOW()))'
        );
        $insert->execute([
            $userId,
            $invoiceId,
            $type,
            $status,
            $amount,
            $currency,
            $gatewayValue,
            $referenceValue,
            $failureReason,
            $noteValue,
            $processedAtValue,
        ]);
        $_SESSION['flash_success'] = __('Payment recorded.');
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Unable to record payment.');
    }

    redirect($redirectTo);
}

function admin_users_payment_update(PDO $pdo): void
{
    require_admin();
    verify_csrf();

    $paymentId = (int)($_POST['payment_id'] ?? 0);
    $userId = (int)($_POST['user_id'] ?? 0);
    $status = trim((string)($_POST['status'] ?? ''));
    $gateway = trim((string)($_POST['gateway'] ?? ''));
    $reference = trim((string)($_POST['reference'] ?? ''));
    $failureReasonInput = trim((string)($_POST['failure_reason'] ?? ''));
    $note = trim((string)($_POST['note'] ?? ''));
    $processedAtInput = trim((string)($_POST['processed_at'] ?? ''));
    $redirectTo = admin_normalize_redirect($_POST['redirect'] ?? null);

    if ($paymentId <= 0 || $userId <= 0 || $status === '') {
        $_SESSION['flash'] = __('Unable to update payment.');
        redirect($redirectTo);
    }

    $allowedStatuses = ['pending', 'succeeded', 'failed', 'canceled'];
    if (!in_array($status, $allowedStatuses, true)) {
        $_SESSION['flash'] = __('Unsupported payment status.');
        redirect($redirectTo);
    }

    $stmt = $pdo->prepare('SELECT id, user_id, status FROM user_payments WHERE id = ? LIMIT 1');
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment || (int)($payment['user_id'] ?? 0) !== $userId) {
        $_SESSION['flash'] = __('Payment not found.');
        redirect($redirectTo);
    }

    $failureReason = $status === 'failed' ? ($failureReasonInput !== '' ? $failureReasonInput : null) : null;
    $noteValue = $note !== '' ? $note : null;
    $gatewayValue = $gateway !== '' ? $gateway : null;
    $referenceValue = $reference !== '' ? $reference : null;

    $assignments = [
        'status = ?',
        'gateway = ?',
        'transaction_reference = ?',
        'failure_reason = ?',
        'notes = ?',
        'updated_at = NOW()'
    ];
    $params = [$status, $gatewayValue, $referenceValue, $failureReason, $noteValue];

    $setProcessedNow = false;
    if ($processedAtInput !== '') {
        $timestamp = strtotime($processedAtInput);
        if ($timestamp === false) {
            $_SESSION['flash'] = __('Invalid processed date.');
            redirect($redirectTo);
        }
        $assignments[] = 'processed_at = ?';
        $params[] = gmdate('Y-m-d H:i:sP', $timestamp);
    } elseif ($status === 'succeeded' && ($payment['status'] ?? '') !== 'succeeded') {
        $setProcessedNow = true;
    }

    if ($setProcessedNow) {
        $assignments[] = 'processed_at = NOW()';
    }

    $sql = 'UPDATE user_payments SET ' . implode(', ', $assignments) . ' WHERE id = ?';
    $params[] = $paymentId;

    try {
        $update = $pdo->prepare($sql);
        $update->execute($params);
        $_SESSION['flash_success'] = __('Payment updated.');
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Unable to update payment.');
    }

    redirect($redirectTo);
}

function admin_users_feedback_update(PDO $pdo): void
{
    require_admin();
    verify_csrf();

    $feedbackId = (int)($_POST['feedback_id'] ?? 0);
    $userId = (int)($_POST['user_id'] ?? 0);
    $kind = trim((string)($_POST['kind'] ?? ''));
    $severity = trim((string)($_POST['severity'] ?? ''));
    $status = trim((string)($_POST['status'] ?? ''));
    $title = trim((string)($_POST['title'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));
    $redirectTo = admin_normalize_redirect($_POST['redirect'] ?? null);

    if ($feedbackId <= 0 || $userId <= 0) {
        $_SESSION['flash'] = __('Feedback not found.');
        redirect($redirectTo);
    }

    if ($title === '' || $message === '') {
        $_SESSION['flash'] = __('Please provide a title and message.');
        redirect($redirectTo);
    }

    $allowedKinds = ['bug', 'idea'];
    $allowedSeverities = ['', 'low', 'medium', 'high'];
    $allowedStatuses = ['open', 'in_progress', 'resolved', 'closed'];

    if (!in_array($kind, $allowedKinds, true)) {
        $_SESSION['flash'] = __('Unsupported feedback type.');
        redirect($redirectTo);
    }

    if (!in_array($severity, $allowedSeverities, true) || !in_array($status, $allowedStatuses, true)) {
        $_SESSION['flash'] = __('Unsupported feedback status.');
        redirect($redirectTo);
    }

    $stmt = $pdo->prepare('SELECT id, user_id FROM feedback WHERE id = ? LIMIT 1');
    $stmt->execute([$feedbackId]);
    $feedback = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$feedback || (int)($feedback['user_id'] ?? 0) !== $userId) {
        $_SESSION['flash'] = __('Feedback not found.');
        redirect($redirectTo);
    }

    $severityValue = $severity !== '' ? $severity : null;

    try {
        $update = $pdo->prepare('UPDATE feedback SET kind = ?, severity = ?, status = ?, title = ?, message = ?, updated_at = NOW() WHERE id = ?');
        $update->execute([$kind, $severityValue, $status, $title, $message, $feedbackId]);
        $_SESSION['flash_success'] = __('Feedback updated.');
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Unable to update feedback.');
    }

    redirect($redirectTo);
}

function admin_users_feedback_respond(PDO $pdo): void
{
    require_admin();
    verify_csrf();

    $feedbackId = (int)($_POST['feedback_id'] ?? 0);
    $userId = (int)($_POST['user_id'] ?? 0);
    $response = trim((string)($_POST['message'] ?? ''));
    $redirectTo = admin_normalize_redirect($_POST['redirect'] ?? null);

    if ($feedbackId <= 0 || $userId <= 0 || $response === '') {
        $_SESSION['flash'] = __('Unable to add response.');
        redirect($redirectTo);
    }

    $stmt = $pdo->prepare('SELECT id, user_id FROM feedback WHERE id = ? LIMIT 1');
    $stmt->execute([$feedbackId]);
    $feedback = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$feedback || (int)($feedback['user_id'] ?? 0) !== $userId) {
        $_SESSION['flash'] = __('Feedback not found.');
        redirect($redirectTo);
    }

    try {
        $insert = $pdo->prepare('INSERT INTO feedback_responses (feedback_id, admin_id, message) VALUES (?, ?, ?)');
        $insert->execute([$feedbackId, uid(), $response]);
        $pdo->prepare('UPDATE feedback SET updated_at = NOW() WHERE id = ?')->execute([$feedbackId]);
        $_SESSION['flash_success'] = __('Response added.');
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Unable to add response.');
    }

    redirect($redirectTo);
}

function admin_roles_index(PDO $pdo): void
{
    require_admin();

    $fields = admin_role_capability_fields();
    $roles = [];

    try {
        $sql = <<<SQL
SELECT r.id, r.slug, r.name, r.description, r.is_system, r.capabilities, r.created_at, r.updated_at,
       COALESCE(u.user_count, 0) AS user_count
  FROM roles r
  LEFT JOIN (
      SELECT LOWER(role) AS slug, COUNT(*) AS user_count
        FROM users
       GROUP BY LOWER(role)
  ) u ON u.slug = LOWER(r.slug)
 ORDER BY LOWER(r.name)
SQL;
        $stmt = $pdo->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $capabilities = [];
            $raw = $row['capabilities'] ?? [];
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $raw = $decoded;
                } else {
                    $raw = [];
                }
            }
            if (!is_array($raw)) {
                $raw = [];
            }
            foreach ($fields as $key => $meta) {
                $type = $meta['type'] ?? 'number';
                $value = $raw[$key] ?? null;
                if ($type === 'boolean') {
                    $capabilities[$key] = (bool)$value;
                } elseif ($value === null || $value === '') {
                    $capabilities[$key] = null;
                } else {
                    $capabilities[$key] = (int)$value;
                }
            }

            $roles[] = [
                'id' => (int)($row['id'] ?? 0),
                'slug' => (string)($row['slug'] ?? ''),
                'name' => (string)($row['name'] ?? ''),
                'description' => $row['description'] ?? null,
                'is_system' => (bool)($row['is_system'] ?? false),
                'capabilities' => $capabilities,
                'created_at' => $row['created_at'] ?? null,
                'updated_at' => $row['updated_at'] ?? null,
                'user_count' => (int)($row['user_count'] ?? 0),
            ];
        }
    } catch (Throwable $e) {
        $roles = [];
    }

    view('admin/roles', [
        'pageTitle' => __('Role management'),
        'roles' => $roles,
        'fields' => $fields,
    ]);
}

function admin_roles_create(PDO $pdo): void
{
    require_admin();

    $fields = admin_role_capability_fields();
    $role = [
        'id' => null,
        'slug' => '',
        'name' => '',
        'description' => '',
        'is_system' => false,
        'capabilities' => array_fill_keys(array_keys($fields), null),
    ];

    view('admin/role_form', [
        'pageTitle' => __('Create role'),
        'role' => $role,
        'fields' => $fields,
        'mode' => 'create',
    ]);
}

function admin_normalize_role_slug(string $slug): string
{
    $slug = strtolower(trim($slug));
    return $slug;
}

function admin_parse_role_capabilities(array $input, array $fields): array
{
    $capabilities = [];
    foreach ($fields as $key => $meta) {
        $type = $meta['type'] ?? 'number';
        $value = $input[$key] ?? null;
        if ($type === 'boolean') {
            $capabilities[$key] = !empty($value);
            continue;
        }

        $value = trim((string)$value);
        if ($value === '') {
            $capabilities[$key] = null;
            continue;
        }

        $int = (int)$value;
        $capabilities[$key] = $int > 0 ? $int : null;
    }

    return $capabilities;
}

function admin_roles_store(PDO $pdo): void
{
    require_admin();
    verify_csrf();

    $fields = admin_role_capability_fields();
    $name = trim((string)($_POST['name'] ?? ''));
    $slugInput = admin_normalize_role_slug((string)($_POST['slug'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));

    if ($name === '') {
        $_SESSION['flash'] = __('Name is required.');
        redirect('/admin/roles/create');
    }

    if ($slugInput === '') {
        $_SESSION['flash'] = __('Slug is required.');
        redirect('/admin/roles/create');
    }

    if (!preg_match('/^[a-z0-9_-]+$/', $slugInput)) {
        $_SESSION['flash'] = __('Slug may only contain lowercase letters, numbers, hyphens, and underscores.');
        redirect('/admin/roles/create');
    }

    $existing = role_definition($slugInput);
    if ($existing !== null) {
        $_SESSION['flash'] = __('Slug must be unique.');
        redirect('/admin/roles/create');
    }

    $capabilities = admin_parse_role_capabilities($_POST['capabilities'] ?? [], $fields);

    try {
        $stmt = $pdo->prepare('INSERT INTO roles (slug, name, description, capabilities, is_system) VALUES (?, ?, ?, ?::jsonb, FALSE)');
        $stmt->execute([
            $slugInput,
            $name,
            $description !== '' ? $description : null,
            json_encode($capabilities, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Unable to create role.');
        redirect('/admin/roles/create');
    }

    reset_role_definitions_cache();
    $_SESSION['flash_success'] = __('Role created successfully.');
    redirect('/admin/roles');
}

function admin_fetch_role(PDO $pdo, int $roleId): ?array
{
    $fields = admin_role_capability_fields();
    $stmt = $pdo->prepare('SELECT id, slug, name, description, is_system, capabilities FROM roles WHERE id = ? LIMIT 1');
    $stmt->execute([$roleId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $raw = $row['capabilities'] ?? [];
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $raw = $decoded;
        } else {
            $raw = [];
        }
    }
    if (!is_array($raw)) {
        $raw = [];
    }

    $capabilities = [];
    foreach ($fields as $key => $meta) {
        $type = $meta['type'] ?? 'number';
        $value = $raw[$key] ?? null;
        if ($type === 'boolean') {
            $capabilities[$key] = (bool)$value;
        } elseif ($value === null || $value === '') {
            $capabilities[$key] = null;
        } else {
            $capabilities[$key] = (int)$value;
        }
    }

    return [
        'id' => (int)($row['id'] ?? 0),
        'slug' => (string)($row['slug'] ?? ''),
        'name' => (string)($row['name'] ?? ''),
        'description' => $row['description'] ?? '',
        'is_system' => (bool)($row['is_system'] ?? false),
        'capabilities' => $capabilities,
    ];
}

function admin_roles_edit(PDO $pdo): void
{
    require_admin();

    $roleId = (int)($_GET['id'] ?? 0);
    if ($roleId <= 0) {
        $_SESSION['flash'] = __('Role not found.');
        redirect('/admin/roles');
    }

    $role = admin_fetch_role($pdo, $roleId);
    if (!$role) {
        $_SESSION['flash'] = __('Role not found.');
        redirect('/admin/roles');
    }

    view('admin/role_form', [
        'pageTitle' => __('Edit role'),
        'role' => $role,
        'fields' => admin_role_capability_fields(),
        'mode' => 'edit',
    ]);
}

function admin_roles_update(PDO $pdo): void
{
    require_admin();
    verify_csrf();

    $fields = admin_role_capability_fields();
    $roleId = (int)($_POST['role_id'] ?? 0);
    if ($roleId <= 0) {
        $_SESSION['flash'] = __('Role not found.');
        redirect('/admin/roles');
    }

    $existing = admin_fetch_role($pdo, $roleId);
    if (!$existing) {
        $_SESSION['flash'] = __('Role not found.');
        redirect('/admin/roles');
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $slugInput = admin_normalize_role_slug((string)($_POST['slug'] ?? $existing['slug']));
    $description = trim((string)($_POST['description'] ?? ''));

    if ($name === '') {
        $_SESSION['flash'] = __('Name is required.');
        redirect('/admin/roles/edit?id=' . $roleId);
    }

    if ($existing['is_system']) {
        $slugInput = $existing['slug'];
    } else {
        if ($slugInput === '') {
            $_SESSION['flash'] = __('Slug is required.');
            redirect('/admin/roles/edit?id=' . $roleId);
        }

        if (!preg_match('/^[a-z0-9_-]+$/', $slugInput)) {
            $_SESSION['flash'] = __('Slug may only contain lowercase letters, numbers, hyphens, and underscores.');
            redirect('/admin/roles/edit?id=' . $roleId);
        }

        if ($slugInput !== $existing['slug']) {
            $check = $pdo->prepare('SELECT 1 FROM roles WHERE slug = ? LIMIT 1');
            $check->execute([$slugInput]);
            if ($check->fetchColumn()) {
                $_SESSION['flash'] = __('Slug must be unique.');
                redirect('/admin/roles/edit?id=' . $roleId);
            }
        }
    }

    $capabilities = admin_parse_role_capabilities($_POST['capabilities'] ?? [], $fields);

    try {
        $pdo->beginTransaction();

        if (!$existing['is_system'] && $slugInput !== $existing['slug']) {
            $updateUsers = $pdo->prepare('UPDATE users SET role = ? WHERE role = ?');
            $updateUsers->execute([$slugInput, $existing['slug']]);
        }

        $update = $pdo->prepare('UPDATE roles SET slug = ?, name = ?, description = ?, capabilities = ?::jsonb WHERE id = ?');
        $update->execute([
            $slugInput,
            $name,
            $description !== '' ? $description : null,
            json_encode($capabilities, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $roleId,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['flash'] = __('Unable to update role.');
        redirect('/admin/roles/edit?id=' . $roleId);
    }

    reset_role_definitions_cache();
    $_SESSION['flash_success'] = __('Role updated successfully.');
    redirect('/admin/roles');
}

function admin_roles_delete(PDO $pdo): void
{
    require_admin();
    verify_csrf();

    $roleId = (int)($_POST['role_id'] ?? 0);
    if ($roleId <= 0) {
        $_SESSION['flash'] = __('Role not found.');
        redirect('/admin/roles');
    }

    $role = admin_fetch_role($pdo, $roleId);
    if (!$role) {
        $_SESSION['flash'] = __('Role not found.');
        redirect('/admin/roles');
    }

    if ($role['is_system']) {
        $_SESSION['flash'] = __('Unable to delete a system role.');
        redirect('/admin/roles');
    }

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE LOWER(role) = LOWER(?)');
    $countStmt->execute([$role['slug']]);
    $count = (int)$countStmt->fetchColumn();
    if ($count > 0) {
        $_SESSION['flash'] = __('Role still has assigned users and cannot be deleted.');
        redirect('/admin/roles');
    }

    try {
        $delete = $pdo->prepare('DELETE FROM roles WHERE id = ?');
        $delete->execute([$roleId]);
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Unable to delete role.');
        redirect('/admin/roles');
    }

    reset_role_definitions_cache();
    $_SESSION['flash_success'] = __('Role removed successfully.');
    redirect('/admin/roles');
}
