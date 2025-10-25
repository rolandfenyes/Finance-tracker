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

function admin_billing_role_options(): array
{
    $definitions = role_definitions();
    $options = [];
    foreach ($definitions as $slug => $meta) {
        if ($slug === ROLE_GUEST) {
            continue;
        }
        $options[$slug] = $meta['name'] ?? ucfirst($slug);
    }

    return $options;
}

function admin_billing_hydrate_plan_row(array $row): array
{
    $metadata = $row['metadata'] ?? [];
    if (is_string($metadata) && $metadata !== '') {
        $decoded = json_decode($metadata, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $metadata = $decoded;
        }
    }
    if (!is_array($metadata)) {
        $metadata = [];
    }

    return [
        'id' => (int)($row['id'] ?? 0),
        'code' => (string)($row['code'] ?? ''),
        'name' => (string)($row['name'] ?? ''),
        'description' => $row['description'] ?? null,
        'price' => (float)($row['price'] ?? 0),
        'currency' => strtoupper((string)($row['currency'] ?? 'USD')),
        'billing_interval' => (string)($row['billing_interval'] ?? ''),
        'interval_count' => (int)($row['interval_count'] ?? 1),
        'role_slug' => (string)($row['role_slug'] ?? ''),
        'trial_days' => isset($row['trial_days']) && $row['trial_days'] !== null ? (int)$row['trial_days'] : null,
        'is_active' => (bool)($row['is_active'] ?? false),
        'stripe_product_id' => $row['stripe_product_id'] ?? null,
        'stripe_price_id' => $row['stripe_price_id'] ?? null,
        'metadata' => $metadata,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
        'role_name' => $row['role_name'] ?? null,
        'active_subscriptions' => isset($row['active_subscriptions']) ? (int)$row['active_subscriptions'] : 0,
    ];
}

function admin_billing_plan_choices(PDO $pdo): array
{
    $choices = [];

    try {
        $stmt = $pdo->query('SELECT id, code, name FROM billing_plans ORDER BY name');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $choices[$id] = [
                'id' => $id,
                'code' => (string)($row['code'] ?? ''),
                'name' => (string)($row['name'] ?? ''),
            ];
        }
    } catch (Throwable $e) {
        $choices = [];
    }

    return $choices;
}

function admin_billing_fetch_plan(PDO $pdo, int $planId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT p.*, r.name AS role_name, 0 AS active_subscriptions FROM billing_plans p '
        . 'LEFT JOIN roles r ON LOWER(r.slug) = LOWER(p.role_slug) '
        . 'WHERE p.id = ? LIMIT 1'
    );
    $stmt->execute([$planId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    return admin_billing_hydrate_plan_row($row);
}

function admin_billing_fetch_plan_by_code(PDO $pdo, string $code): ?array
{
    $stmt = $pdo->prepare(
        'SELECT p.*, r.name AS role_name, 0 AS active_subscriptions FROM billing_plans p '
        . 'LEFT JOIN roles r ON LOWER(r.slug) = LOWER(p.role_slug) '
        . 'WHERE LOWER(p.code) = LOWER(?) LIMIT 1'
    );
    $stmt->execute([trim($code)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    return admin_billing_hydrate_plan_row($row);
}

function admin_billing_hydrate_promotion_row(array $row): array
{
    $metadata = $row['metadata'] ?? [];
    if (is_string($metadata) && $metadata !== '') {
        $decoded = json_decode($metadata, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $metadata = $decoded;
        }
    }
    if (!is_array($metadata)) {
        $metadata = [];
    }

    return [
        'id' => (int)($row['id'] ?? 0),
        'code' => (string)($row['code'] ?? ''),
        'name' => (string)($row['name'] ?? ''),
        'description' => $row['description'] ?? null,
        'discount_percent' => isset($row['discount_percent']) && $row['discount_percent'] !== null ? (float)$row['discount_percent'] : null,
        'discount_amount' => isset($row['discount_amount']) && $row['discount_amount'] !== null ? (float)$row['discount_amount'] : null,
        'currency' => $row['currency'] ?? null,
        'max_redemptions' => isset($row['max_redemptions']) && $row['max_redemptions'] !== null ? (int)$row['max_redemptions'] : null,
        'redeem_by' => $row['redeem_by'] ?? null,
        'trial_days' => isset($row['trial_days']) && $row['trial_days'] !== null ? (int)$row['trial_days'] : null,
        'plan_code' => $row['plan_code'] ?? null,
        'plan_name' => $row['plan_name'] ?? null,
        'stripe_coupon_id' => $row['stripe_coupon_id'] ?? null,
        'stripe_promo_code_id' => $row['stripe_promo_code_id'] ?? null,
        'metadata' => $metadata,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function admin_billing_fetch_promotion(PDO $pdo, int $promotionId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT pr.*, bp.name AS plan_name FROM billing_promotions pr '
        . 'LEFT JOIN billing_plans bp ON bp.code = pr.plan_code '
        . 'WHERE pr.id = ? LIMIT 1'
    );
    $stmt->execute([$promotionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    return admin_billing_hydrate_promotion_row($row);
}

function admin_billing_index(PDO $pdo): void
{
    require_admin();

    $roleOptions = admin_billing_role_options();
    $intervalLabels = billing_interval_labels();
    $settings = billing_settings();

    $plans = [];
    try {
        $sql = <<<SQL
SELECT p.*, r.name AS role_name,
       COALESCE(s.active_subscriptions, 0) AS active_subscriptions
  FROM billing_plans p
  LEFT JOIN roles r ON LOWER(r.slug) = LOWER(p.role_slug)
  LEFT JOIN (
        SELECT plan_code, COUNT(*) FILTER (WHERE status IN ('active','trialing')) AS active_subscriptions
          FROM user_subscriptions
         GROUP BY plan_code
  ) s ON s.plan_code = p.code
 ORDER BY p.is_active DESC, p.price, p.name
SQL;
        $stmt = $pdo->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $plans[] = admin_billing_hydrate_plan_row($row);
        }
    } catch (Throwable $e) {
        $plans = [];
    }

    $planOptions = [];
    foreach ($plans as $plan) {
        $label = $plan['name'];
        if (!empty($plan['code'])) {
            $label .= ' (' . $plan['code'] . ')';
        }
        $planOptions[$plan['id']] = $label;
    }

    $promotions = [];
    try {
        $sql = <<<SQL
SELECT pr.*, bp.name AS plan_name
  FROM billing_promotions pr
  LEFT JOIN billing_plans bp ON bp.code = pr.plan_code
 ORDER BY pr.redeem_by NULLS LAST, pr.created_at DESC
SQL;
        $stmt = $pdo->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $promotions[] = admin_billing_hydrate_promotion_row($row);
        }
    } catch (Throwable $e) {
        $promotions = [];
    }

    $subscriptions = [];
    try {
        $sql = <<<SQL
SELECT s.id, s.user_id, s.plan_code, s.plan_name, s.status, s.billing_interval, s.interval_count,
       s.amount, s.currency, s.started_at, s.current_period_start, s.current_period_end,
       s.cancel_at, s.canceled_at, s.trial_ends_at, s.notes, s.created_at, s.updated_at,
       u.email, u.full_name, u.role
  FROM user_subscriptions s
  LEFT JOIN users u ON u.id = s.user_id
 ORDER BY COALESCE(s.current_period_end, s.created_at) DESC
 LIMIT 25
SQL;
        $stmt = $pdo->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $subscriptions[] = [
                'id' => (int)($row['id'] ?? 0),
                'user_id' => (int)($row['user_id'] ?? 0),
                'plan_code' => (string)($row['plan_code'] ?? ''),
                'plan_name' => (string)($row['plan_name'] ?? ''),
                'status' => (string)($row['status'] ?? ''),
                'billing_interval' => (string)($row['billing_interval'] ?? ''),
                'interval_count' => (int)($row['interval_count'] ?? 1),
                'amount' => (float)($row['amount'] ?? 0),
                'currency' => (string)($row['currency'] ?? ''),
                'started_at' => $row['started_at'] ?? null,
                'current_period_start' => $row['current_period_start'] ?? null,
                'current_period_end' => $row['current_period_end'] ?? null,
                'cancel_at' => $row['cancel_at'] ?? null,
                'canceled_at' => $row['canceled_at'] ?? null,
                'trial_ends_at' => $row['trial_ends_at'] ?? null,
                'notes' => $row['notes'] ?? null,
                'created_at' => $row['created_at'] ?? null,
                'updated_at' => $row['updated_at'] ?? null,
                'email' => $row['email'] ?? null,
                'full_name' => $row['full_name'] ? pii_decrypt($row['full_name']) : null,
                'role' => normalize_user_role($row['role'] ?? null),
            ];
        }
    } catch (Throwable $e) {
        $subscriptions = [];
    }

    $invoices = [];
    try {
        $sql = <<<SQL
SELECT i.id, i.user_id, i.subscription_id, i.invoice_number, i.status, i.total_amount, i.currency,
       i.issued_at, i.due_at, i.paid_at, i.failure_reason, i.refund_reason, i.notes, i.created_at, i.updated_at,
       u.email, u.full_name
  FROM user_invoices i
  LEFT JOIN users u ON u.id = i.user_id
 ORDER BY i.created_at DESC
 LIMIT 25
SQL;
        $stmt = $pdo->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $invoices[] = [
                'id' => (int)($row['id'] ?? 0),
                'user_id' => (int)($row['user_id'] ?? 0),
                'subscription_id' => isset($row['subscription_id']) && $row['subscription_id'] !== null ? (int)$row['subscription_id'] : null,
                'invoice_number' => (string)($row['invoice_number'] ?? ''),
                'status' => (string)($row['status'] ?? ''),
                'total_amount' => (float)($row['total_amount'] ?? 0),
                'currency' => (string)($row['currency'] ?? ''),
                'issued_at' => $row['issued_at'] ?? null,
                'due_at' => $row['due_at'] ?? null,
                'paid_at' => $row['paid_at'] ?? null,
                'failure_reason' => $row['failure_reason'] ?? null,
                'refund_reason' => $row['refund_reason'] ?? null,
                'notes' => $row['notes'] ?? null,
                'created_at' => $row['created_at'] ?? null,
                'updated_at' => $row['updated_at'] ?? null,
                'email' => $row['email'] ?? null,
                'full_name' => $row['full_name'] ? pii_decrypt($row['full_name']) : null,
            ];
        }
    } catch (Throwable $e) {
        $invoices = [];
    }

    $payments = [];
    try {
        $sql = <<<SQL
SELECT p.id, p.user_id, p.invoice_id, p.type, p.status, p.amount, p.currency, p.gateway, p.transaction_reference,
       p.failure_reason, p.notes, p.processed_at, p.created_at, p.updated_at,
       u.email, u.full_name
  FROM user_payments p
  LEFT JOIN users u ON u.id = p.user_id
 ORDER BY p.processed_at DESC
 LIMIT 25
SQL;
        $stmt = $pdo->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $payments[] = [
                'id' => (int)($row['id'] ?? 0),
                'user_id' => (int)($row['user_id'] ?? 0),
                'invoice_id' => isset($row['invoice_id']) && $row['invoice_id'] !== null ? (int)$row['invoice_id'] : null,
                'type' => (string)($row['type'] ?? ''),
                'status' => (string)($row['status'] ?? ''),
                'amount' => (float)($row['amount'] ?? 0),
                'currency' => (string)($row['currency'] ?? ''),
                'gateway' => $row['gateway'] ?? null,
                'transaction_reference' => $row['transaction_reference'] ?? null,
                'failure_reason' => $row['failure_reason'] ?? null,
                'notes' => $row['notes'] ?? null,
                'processed_at' => $row['processed_at'] ?? null,
                'created_at' => $row['created_at'] ?? null,
                'updated_at' => $row['updated_at'] ?? null,
                'email' => $row['email'] ?? null,
                'full_name' => $row['full_name'] ? pii_decrypt($row['full_name']) : null,
            ];
        }
    } catch (Throwable $e) {
        $payments = [];
    }

    $invoiceStatusOptions = [
        'draft' => __('Draft'),
        'open' => __('Open'),
        'past_due' => __('Past due'),
        'paid' => __('Paid'),
        'failed' => __('Failed'),
        'refunded' => __('Refunded'),
        'void' => __('Void'),
    ];

    $paymentStatusOptions = [
        'pending' => __('Pending'),
        'succeeded' => __('Succeeded'),
        'failed' => __('Failed'),
        'canceled' => __('Canceled'),
    ];

    $paymentTypeOptions = [
        'charge' => __('Charge'),
        'refund' => __('Refund'),
        'adjustment' => __('Adjustment'),
    ];

    $subscriptionStatusOptions = [
        'active' => __('Active'),
        'trialing' => __('Trialing'),
        'past_due' => __('Past due'),
        'canceled' => __('Canceled'),
        'expired' => __('Expired'),
    ];

    view('admin/billing', [
        'pageTitle' => __('Billing & plans'),
        'plans' => $plans,
        'planOptions' => $planOptions,
        'promotions' => $promotions,
        'subscriptions' => $subscriptions,
        'invoices' => $invoices,
        'payments' => $payments,
        'roleOptions' => $roleOptions,
        'intervalLabels' => $intervalLabels,
        'invoiceStatusOptions' => $invoiceStatusOptions,
        'paymentStatusOptions' => $paymentStatusOptions,
        'paymentTypeOptions' => $paymentTypeOptions,
        'subscriptionStatusOptions' => $subscriptionStatusOptions,
        'stripeSettings' => $settings,
        'hasStripeKeys' => billing_has_stripe_keys(),
        'defaultCurrency' => billing_default_currency(),
    ]);
}

function admin_billing_plans_create(PDO $pdo): void
{
    require_admin();

    $roleOptions = admin_billing_role_options();
    $plan = [
        'id' => null,
        'code' => '',
        'name' => '',
        'description' => '',
        'price' => 0,
        'currency' => billing_default_currency(),
        'billing_interval' => 'monthly',
        'interval_count' => 1,
        'role_slug' => '',
        'trial_days' => null,
        'is_active' => true,
        'stripe_product_id' => '',
        'stripe_price_id' => '',
    ];

    view('admin/billing_plan_form', [
        'pageTitle' => __('Create pricing plan'),
        'plan' => $plan,
        'roleOptions' => $roleOptions,
        'intervalLabels' => billing_interval_labels(),
        'mode' => 'create',
    ]);
}

function admin_billing_plans_edit(PDO $pdo): void
{
    require_admin();

    $planId = (int)($_GET['id'] ?? 0);
    if ($planId <= 0) {
        $_SESSION['flash'] = __('Plan not found.');
        redirect('/admin/billing');
    }

    $plan = admin_billing_fetch_plan($pdo, $planId);
    if (!$plan) {
        $_SESSION['flash'] = __('Plan not found.');
        redirect('/admin/billing');
    }

    view('admin/billing_plan_form', [
        'pageTitle' => __('Edit pricing plan'),
        'plan' => $plan,
        'roleOptions' => admin_billing_role_options(),
        'intervalLabels' => billing_interval_labels(),
        'mode' => 'edit',
    ]);
}

function admin_billing_validate_plan(array $input, array $roleOptions): array
{
    $code = strtolower(trim((string)($input['code'] ?? '')));
    $name = trim((string)($input['name'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));
    $priceInput = trim((string)($input['price'] ?? '0'));
    $currency = strtoupper(trim((string)($input['currency'] ?? '')));
    $interval = strtolower(trim((string)($input['billing_interval'] ?? '')));
    $intervalCount = (int)($input['interval_count'] ?? 1);
    $roleSlug = strtolower(trim((string)($input['role_slug'] ?? '')));
    $trialDaysInput = trim((string)($input['trial_days'] ?? ''));
    $isActive = !empty($input['is_active']);
    $productId = trim((string)($input['stripe_product_id'] ?? ''));
    $priceId = trim((string)($input['stripe_price_id'] ?? ''));

    if ($name === '') {
        throw new RuntimeException(__('Name is required.'));
    }

    if ($code === '' || !preg_match('/^[a-z0-9._-]+$/', $code)) {
        throw new RuntimeException(__('Code may only contain lowercase letters, numbers, dots, hyphens, and underscores.'));
    }

    if ($currency === '' || !preg_match('/^[A-Z]{3}$/', $currency)) {
        throw new RuntimeException(__('Please provide a valid 3-letter currency code.'));
    }

    $intervalOptions = array_keys(billing_interval_labels());
    if (!in_array($interval, $intervalOptions, true)) {
        throw new RuntimeException(__('Unsupported billing interval.'));
    }

    $intervalCount = $intervalCount > 0 ? $intervalCount : 1;

    if (!isset($roleOptions[$roleSlug])) {
        throw new RuntimeException(__('Please select a valid role.'));
    }

    if ($priceInput === '' || !is_numeric($priceInput)) {
        throw new RuntimeException(__('Please provide a valid price.'));
    }

    $price = round((float)$priceInput, 2);
    $trialDays = null;
    if ($trialDaysInput !== '') {
        if (!ctype_digit($trialDaysInput)) {
            throw new RuntimeException(__('Trial days must be a positive number.'));
        }
        $trialDays = (int)$trialDaysInput;
    }

    return [
        'code' => $code,
        'name' => $name,
        'description' => $description !== '' ? $description : null,
        'price' => $price,
        'currency' => $currency,
        'billing_interval' => $interval,
        'interval_count' => $intervalCount,
        'role_slug' => $roleSlug,
        'trial_days' => $trialDays,
        'is_active' => $isActive,
        'stripe_product_id' => $productId !== '' ? $productId : null,
        'stripe_price_id' => $priceId !== '' ? $priceId : null,
    ];
}

function admin_billing_plans_store(PDO $pdo): void
{
    require_admin();
    verify_csrf();

    $roleOptions = admin_billing_role_options();
    try {
        $data = admin_billing_validate_plan($_POST, $roleOptions);
    } catch (RuntimeException $e) {
        $_SESSION['flash'] = $e->getMessage();
        redirect('/admin/billing/plans/create');
    }

    try {
        $exists = $pdo->prepare('SELECT 1 FROM billing_plans WHERE LOWER(code) = LOWER(?) LIMIT 1');
        $exists->execute([$data['code']]);
        if ($exists->fetchColumn()) {
            $_SESSION['flash'] = __('Plan code must be unique.');
            redirect('/admin/billing/plans/create');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO billing_plans (code, name, description, price, currency, billing_interval, interval_count, role_slug, trial_days, is_active, stripe_product_id, stripe_price_id) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['code'],
            $data['name'],
            $data['description'],
            $data['price'],
            $data['currency'],
            $data['billing_interval'],
            $data['interval_count'],
            $data['role_slug'],
            $data['trial_days'],
            $data['is_active'] ? 1 : 0,
            $data['stripe_product_id'],
            $data['stripe_price_id'],
        ]);
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Unable to create plan.');
        redirect('/admin/billing/plans/create');
    }

    $_SESSION['flash_success'] = __('Plan created successfully.');
    redirect('/admin/billing');
}

function admin_billing_plans_update(PDO $pdo): void
{
    require_admin();
    verify_csrf();

    $planId = (int)($_POST['plan_id'] ?? 0);
    if ($planId <= 0) {
        $_SESSION['flash'] = __('Plan not found.');
        redirect('/admin/billing');
    }

    $plan = admin_billing_fetch_plan($pdo, $planId);
    if (!$plan) {
        $_SESSION['flash'] = __('Plan not found.');
        redirect('/admin/billing');
    }

    $roleOptions = admin_billing_role_options();
    try {
        $data = admin_billing_validate_plan($_POST, $roleOptions);
    } catch (RuntimeException $e) {
        $_SESSION['flash'] = $e->getMessage();
        redirect('/admin/billing/plans/edit?id=' . $planId);
    }

    try {
        $conflict = $pdo->prepare('SELECT id FROM billing_plans WHERE LOWER(code) = LOWER(?) AND id <> ? LIMIT 1');
        $conflict->execute([$data['code'], $planId]);
        if ($conflict->fetchColumn()) {
            $_SESSION['flash'] = __('Plan code must be unique.');
            redirect('/admin/billing/plans/edit?id=' . $planId);
        }

        $stmt = $pdo->prepare(
            'UPDATE billing_plans SET code = ?, name = ?, description = ?, price = ?, currency = ?, billing_interval = ?, '
            . 'interval_count = ?, role_slug = ?, trial_days = ?, is_active = ?, stripe_product_id = ?, stripe_price_id = ? '
            . 'WHERE id = ?'
        );
        $stmt->execute([
            $data['code'],
            $data['name'],
            $data['description'],
            $data['price'],
            $data['currency'],
            $data['billing_interval'],
            $data['interval_count'],
            $data['role_slug'],
            $data['trial_days'],
            $data['is_active'] ? 1 : 0,
            $data['stripe_product_id'],
            $data['stripe_price_id'],
            $planId,
        ]);
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Unable to update plan.');
        redirect('/admin/billing/plans/edit?id=' . $planId);
    }

    $_SESSION['flash_success'] = __('Plan updated successfully.');
    redirect('/admin/billing');
}

function admin_billing_plans_delete(PDO $pdo): void
{
    require_admin();
    verify_csrf();

    $planId = (int)($_POST['plan_id'] ?? 0);
    $redirectTo = admin_normalize_redirect($_POST['redirect'] ?? '/admin/billing', '/admin/billing');

    if ($planId <= 0) {
        $_SESSION['flash'] = __('Plan not found.');
        redirect($redirectTo);
    }

    $plan = admin_billing_fetch_plan($pdo, $planId);
    if (!$plan) {
        $_SESSION['flash'] = __('Plan not found.');
        redirect($redirectTo);
    }

    try {
        $activeStmt = $pdo->prepare("SELECT COUNT(*) FROM user_subscriptions WHERE plan_code = ? AND status IN ('active','trialing','past_due')");
        $activeStmt->execute([$plan['code']]);
        $activeCount = (int)$activeStmt->fetchColumn();
        if ($activeCount > 0) {
            $_SESSION['flash'] = __('Cannot delete a plan with active subscribers.');
            redirect($redirectTo);
        }
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Unable to verify subscriptions for this plan.');
        redirect($redirectTo);
    }

    try {
        $delete = $pdo->prepare('DELETE FROM billing_plans WHERE id = ?');
        $delete->execute([$planId]);
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Unable to delete plan.');
        redirect($redirectTo);
    }

    $_SESSION['flash_success'] = __('Plan removed.');
    redirect('/admin/billing');
}

function admin_billing_promotions_create(PDO $pdo): void
{
    require_admin();

    $promotion = [
        'id' => null,
        'code' => '',
        'name' => '',
        'description' => '',
        'discount_percent' => null,
        'discount_amount' => null,
        'currency' => '',
        'max_redemptions' => null,
        'redeem_by' => '',
        'trial_days' => null,
        'plan_code' => null,
        'stripe_coupon_id' => '',
        'stripe_promo_code_id' => '',
    ];

    view('admin/billing_promotion_form', [
        'pageTitle' => __('Create promotion'),
        'promotion' => $promotion,
        'plans' => admin_billing_plan_choices($pdo),
        'mode' => 'create',
    ]);
}

function admin_billing_promotions_edit(PDO $pdo): void
{
    require_admin();

    $promotionId = (int)($_GET['id'] ?? 0);
    if ($promotionId <= 0) {
        $_SESSION['flash'] = __('Promotion not found.');
        redirect('/admin/billing');
    }

    $promotion = admin_billing_fetch_promotion($pdo, $promotionId);
    if (!$promotion) {
        $_SESSION['flash'] = __('Promotion not found.');
        redirect('/admin/billing');
    }

    view('admin/billing_promotion_form', [
        'pageTitle' => __('Edit promotion'),
        'promotion' => $promotion,
        'plans' => admin_billing_plan_choices($pdo),
        'mode' => 'edit',
    ]);
}

function admin_billing_validate_promotion(PDO $pdo, array $input, ?int $promotionId = null): array
{
    $code = strtoupper(trim((string)($input['code'] ?? '')));
    $name = trim((string)($input['name'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));
    $discountPercentInput = trim((string)($input['discount_percent'] ?? ''));
    $discountAmountInput = trim((string)($input['discount_amount'] ?? ''));
    $currency = strtoupper(trim((string)($input['currency'] ?? '')));
    $maxRedemptionsInput = trim((string)($input['max_redemptions'] ?? ''));
    $redeemByInput = trim((string)($input['redeem_by'] ?? ''));
    $trialDaysInput = trim((string)($input['trial_days'] ?? ''));
    $planId = (int)($input['plan_id'] ?? 0);
    $stripeCouponId = trim((string)($input['stripe_coupon_id'] ?? ''));
    $stripePromoCodeId = trim((string)($input['stripe_promo_code_id'] ?? ''));

    if ($code === '' || !preg_match('/^[A-Z0-9_-]+$/', $code)) {
        throw new RuntimeException(__('Code may only contain uppercase letters, numbers, hyphens, and underscores.'));
    }

    if ($name === '') {
        throw new RuntimeException(__('Name is required.'));
    }

    $discountPercent = null;
    if ($discountPercentInput !== '') {
        if (!is_numeric($discountPercentInput)) {
            throw new RuntimeException(__('Discount percent must be numeric.'));
        }
        $discountPercent = (float)$discountPercentInput;
        if ($discountPercent < 0 || $discountPercent > 100) {
            throw new RuntimeException(__('Discount percent must be between 0 and 100.'));
        }
    }

    $discountAmount = null;
    if ($discountAmountInput !== '') {
        if (!is_numeric($discountAmountInput)) {
            throw new RuntimeException(__('Discount amount must be numeric.'));
        }
        $discountAmount = round((float)$discountAmountInput, 2);
        if ($discountAmount < 0) {
            throw new RuntimeException(__('Discount amount cannot be negative.'));
        }
        if ($currency === '' || !preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new RuntimeException(__('Please provide a valid currency for fixed discounts.'));
        }
    }

    if ($discountAmount === null && $currency !== '' && !preg_match('/^[A-Z]{3}$/', $currency)) {
        throw new RuntimeException(__('Please provide a valid 3-letter currency code.'));
    }

    $trialDays = null;
    if ($trialDaysInput !== '') {
        if (!ctype_digit($trialDaysInput)) {
            throw new RuntimeException(__('Trial days must be numeric.'));
        }
        $trialDays = (int)$trialDaysInput;
    }

    if ($discountPercent === null && $discountAmount === null && $trialDays === null) {
        throw new RuntimeException(__('Provide a discount or a trial period.'));
    }

    $maxRedemptions = null;
    if ($maxRedemptionsInput !== '') {
        if (!ctype_digit($maxRedemptionsInput)) {
            throw new RuntimeException(__('Max redemptions must be numeric.'));
        }
        $maxRedemptions = (int)$maxRedemptionsInput;
    }

    $redeemBy = null;
    if ($redeemByInput !== '') {
        $timestamp = strtotime($redeemByInput);
        if ($timestamp === false) {
            throw new RuntimeException(__('Please provide a valid redemption deadline.'));
        }
        $redeemBy = gmdate('Y-m-d H:i:sP', $timestamp);
    }

    $planCode = null;
    if ($planId > 0) {
        $plan = admin_billing_fetch_plan($pdo, $planId);
        if (!$plan) {
            throw new RuntimeException(__('Selected plan does not exist.'));
        }
        $planCode = $plan['code'];
    }

    return [
        'code' => $code,
        'name' => $name,
        'description' => $description !== '' ? $description : null,
        'discount_percent' => $discountPercent,
        'discount_amount' => $discountAmount,
        'currency' => $currency !== '' ? $currency : null,
        'max_redemptions' => $maxRedemptions,
        'redeem_by' => $redeemBy,
        'trial_days' => $trialDays,
        'plan_code' => $planCode,
        'stripe_coupon_id' => $stripeCouponId !== '' ? $stripeCouponId : null,
        'stripe_promo_code_id' => $stripePromoCodeId !== '' ? $stripePromoCodeId : null,
    ];
}

function admin_billing_promotions_store(PDO $pdo): void
{
    require_admin();
    verify_csrf();

    try {
        $data = admin_billing_validate_promotion($pdo, $_POST, null);
    } catch (RuntimeException $e) {
        $_SESSION['flash'] = $e->getMessage();
        redirect('/admin/billing/promotions/create');
    }

    try {
        $conflict = $pdo->prepare('SELECT id FROM billing_promotions WHERE UPPER(code) = UPPER(?) LIMIT 1');
        $conflict->execute([$data['code']]);
        if ($conflict->fetchColumn()) {
            $_SESSION['flash'] = __('Promotion code must be unique.');
            redirect('/admin/billing/promotions/create');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO billing_promotions (code, name, description, discount_percent, discount_amount, currency, max_redemptions, redeem_by, trial_days, plan_code, stripe_coupon_id, stripe_promo_code_id) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['code'],
            $data['name'],
            $data['description'],
            $data['discount_percent'],
            $data['discount_amount'],
            $data['currency'],
            $data['max_redemptions'],
            $data['redeem_by'],
            $data['trial_days'],
            $data['plan_code'],
            $data['stripe_coupon_id'],
            $data['stripe_promo_code_id'],
        ]);
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Unable to create promotion.');
        redirect('/admin/billing/promotions/create');
    }

    $_SESSION['flash_success'] = __('Promotion created successfully.');
    redirect('/admin/billing');
}

function admin_billing_promotions_update(PDO $pdo): void
{
    require_admin();
    verify_csrf();

    $promotionId = (int)($_POST['promotion_id'] ?? 0);
    if ($promotionId <= 0) {
        $_SESSION['flash'] = __('Promotion not found.');
        redirect('/admin/billing');
    }

    $promotion = admin_billing_fetch_promotion($pdo, $promotionId);
    if (!$promotion) {
        $_SESSION['flash'] = __('Promotion not found.');
        redirect('/admin/billing');
    }

    try {
        $data = admin_billing_validate_promotion($pdo, $_POST, $promotionId);
    } catch (RuntimeException $e) {
        $_SESSION['flash'] = $e->getMessage();
        redirect('/admin/billing/promotions/edit?id=' . $promotionId);
    }

    try {
        $conflict = $pdo->prepare('SELECT id FROM billing_promotions WHERE UPPER(code) = UPPER(?) AND id <> ? LIMIT 1');
        $conflict->execute([$data['code'], $promotionId]);
        if ($conflict->fetchColumn()) {
            $_SESSION['flash'] = __('Promotion code must be unique.');
            redirect('/admin/billing/promotions/edit?id=' . $promotionId);
        }

        $stmt = $pdo->prepare(
            'UPDATE billing_promotions SET code = ?, name = ?, description = ?, discount_percent = ?, discount_amount = ?, currency = ?, '
            . 'max_redemptions = ?, redeem_by = ?, trial_days = ?, plan_code = ?, stripe_coupon_id = ?, stripe_promo_code_id = ? '
            . 'WHERE id = ?'
        );
        $stmt->execute([
            $data['code'],
            $data['name'],
            $data['description'],
            $data['discount_percent'],
            $data['discount_amount'],
            $data['currency'],
            $data['max_redemptions'],
            $data['redeem_by'],
            $data['trial_days'],
            $data['plan_code'],
            $data['stripe_coupon_id'],
            $data['stripe_promo_code_id'],
            $promotionId,
        ]);
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Unable to update promotion.');
        redirect('/admin/billing/promotions/edit?id=' . $promotionId);
    }

    $_SESSION['flash_success'] = __('Promotion updated successfully.');
    redirect('/admin/billing');
}

function admin_billing_promotions_delete(PDO $pdo): void
{
    require_admin();
    verify_csrf();

    $promotionId = (int)($_POST['promotion_id'] ?? 0);
    $redirectTo = admin_normalize_redirect($_POST['redirect'] ?? '/admin/billing', '/admin/billing');

    if ($promotionId <= 0) {
        $_SESSION['flash'] = __('Promotion not found.');
        redirect($redirectTo);
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM billing_promotions WHERE id = ?');
        $stmt->execute([$promotionId]);
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Unable to delete promotion.');
        redirect($redirectTo);
    }

    $_SESSION['flash_success'] = __('Promotion removed.');
    redirect('/admin/billing');
}

function admin_billing_promotions_generate_trial(PDO $pdo): void
{
    require_admin();
    verify_csrf();

    $planId = (int)($_POST['plan_id'] ?? 0);
    $trialDaysInput = trim((string)($_POST['trial_days'] ?? ''));
    $maxRedemptionsInput = trim((string)($_POST['max_redemptions'] ?? ''));
    $redirectTo = admin_normalize_redirect($_POST['redirect'] ?? '/admin/billing', '/admin/billing');

    if ($planId <= 0) {
        $_SESSION['flash'] = __('Plan not found.');
        redirect($redirectTo);
    }

    $plan = admin_billing_fetch_plan($pdo, $planId);
    if (!$plan) {
        $_SESSION['flash'] = __('Plan not found.');
        redirect($redirectTo);
    }

    $trialDays = $plan['trial_days'] ?? 14;
    if ($trialDaysInput !== '') {
        if (!ctype_digit($trialDaysInput)) {
            $_SESSION['flash'] = __('Trial days must be numeric.');
            redirect($redirectTo);
        }
        $trialDays = (int)$trialDaysInput;
    }
    if ($trialDays <= 0) {
        $trialDays = 1;
    }

    $maxRedemptions = null;
    if ($maxRedemptionsInput !== '') {
        if (!ctype_digit($maxRedemptionsInput)) {
            $_SESSION['flash'] = __('Max redemptions must be numeric.');
            redirect($redirectTo);
        }
        $value = (int)$maxRedemptionsInput;
        if ($value > 0) {
            $maxRedemptions = $value;
        }
    }

    $attempts = 0;
    $code = '';
    do {
        $attempts++;
        $raw = strtoupper(substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(8))), 0, 10));
        $code = 'TRIAL-' . $raw;
        $exists = $pdo->prepare('SELECT id FROM billing_promotions WHERE code = ? LIMIT 1');
        $exists->execute([$code]);
    } while ($exists->fetchColumn() && $attempts < 5);

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO billing_promotions (code, name, description, discount_percent, discount_amount, currency, max_redemptions, redeem_by, trial_days, plan_code) '
            . 'VALUES (?, ?, ?, NULL, NULL, NULL, ?, NULL, ?, ?)' 
        );
        $stmt->execute([
            $code,
            __('Trial for :plan', ['plan' => $plan['name']]),
            __('Auto-generated free trial'),
            $maxRedemptions,
            $trialDays,
            $plan['code'],
        ]);
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Unable to generate trial promotion.');
        redirect($redirectTo);
    }

    $_SESSION['flash_success'] = __('Trial promotion created: :code', ['code' => $code]);
    redirect('/admin/billing');
}

function admin_billing_settings_update(PDO $pdo): void
{
    require_admin();
    verify_csrf();

    $secret = trim((string)($_POST['stripe_secret_key'] ?? ''));
    $publishable = trim((string)($_POST['stripe_publishable_key'] ?? ''));
    $webhook = trim((string)($_POST['stripe_webhook_secret'] ?? ''));
    $defaultCurrency = strtoupper(trim((string)($_POST['default_currency'] ?? 'USD')));
    $redirectTo = admin_normalize_redirect($_POST['redirect'] ?? '/admin/billing', '/admin/billing');

    if ($defaultCurrency === '' || !preg_match('/^[A-Z]{3}$/', $defaultCurrency)) {
        $_SESSION['flash'] = __('Please provide a valid default currency.');
        redirect($redirectTo);
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO billing_settings (id, stripe_secret_key, stripe_publishable_key, stripe_webhook_secret, default_currency) '
            . 'VALUES (1, ?, ?, ?, ?) '
            . 'ON CONFLICT (id) DO UPDATE '
            . 'SET stripe_secret_key = EXCLUDED.stripe_secret_key, '
            . '    stripe_publishable_key = EXCLUDED.stripe_publishable_key, '
            . '    stripe_webhook_secret = EXCLUDED.stripe_webhook_secret, '
            . '    default_currency = EXCLUDED.default_currency, '
            . '    updated_at = NOW()'
        );
        $stmt->execute([
            $secret !== '' ? $secret : null,
            $publishable !== '' ? $publishable : null,
            $webhook !== '' ? $webhook : null,
            $defaultCurrency,
        ]);
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Unable to update billing settings.');
        redirect($redirectTo);
    }

    reset_billing_settings_cache();
    $_SESSION['flash_success'] = __('Billing settings updated.');
    redirect('/admin/billing');
}

function admin_billing_user_plan_assign(PDO $pdo): void
{
    require_admin();
    verify_csrf();

    $identifier = trim((string)($_POST['user_email'] ?? ''));
    $planId = (int)($_POST['plan_id'] ?? 0);
    $status = strtolower(trim((string)($_POST['subscription_status'] ?? 'active')));
    $createSubscription = !empty($_POST['create_subscription']);
    $note = trim((string)($_POST['note'] ?? ''));
    $trialOverrideInput = trim((string)($_POST['trial_days'] ?? ''));
    $redirectTo = admin_normalize_redirect($_POST['redirect'] ?? '/admin/billing', '/admin/billing');

    if ($identifier === '') {
        $_SESSION['flash'] = __('Please provide an email address.');
        redirect($redirectTo);
    }

    if ($planId <= 0) {
        $_SESSION['flash'] = __('Plan not found.');
        redirect($redirectTo);
    }

    $plan = admin_billing_fetch_plan($pdo, $planId);
    if (!$plan) {
        $_SESSION['flash'] = __('Plan not found.');
        redirect($redirectTo);
    }

    $allowedStatuses = ['active', 'trialing', 'past_due', 'canceled', 'expired'];
    if (!in_array($status, $allowedStatuses, true)) {
        $_SESSION['flash'] = __('Unsupported subscription status.');
        redirect($redirectTo);
    }

    $user = null;
    $stmt = $pdo->prepare('SELECT id, email, role FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
    $stmt->execute([$identifier]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user && ctype_digit($identifier)) {
        $stmt = $pdo->prepare('SELECT id, email, role FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$user) {
        $_SESSION['flash'] = __('User not found.');
        redirect($redirectTo);
    }

    $userId = (int)($user['id'] ?? 0);
    if ($userId <= 0) {
        $_SESSION['flash'] = __('User not found.');
        redirect($redirectTo);
    }

    $roleSlug = strtolower($plan['role_slug'] ?? '');
    try {
        $update = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
        $update->execute([$roleSlug, $userId]);
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Unable to update user role.');
        redirect($redirectTo);
    }

    if ($userId === uid()) {
        $_SESSION['role'] = $roleSlug;
    }

    if ($createSubscription) {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $startedAt = $now->format('Y-m-d H:i:sP');
        $currentPeriodStart = $startedAt;
        $currentPeriodEnd = null;

        $interval = $plan['billing_interval'] ?? 'monthly';
        $intervalCount = (int)($plan['interval_count'] ?? 1);
        if ($intervalCount <= 0) {
            $intervalCount = 1;
        }

        if ($interval !== 'lifetime') {
            $periodEnd = $now;
            if ($interval === 'weekly') {
                $periodEnd = $periodEnd->modify('+' . $intervalCount . ' week');
            } elseif ($interval === 'monthly') {
                $periodEnd = $periodEnd->modify('+' . $intervalCount . ' month');
            } elseif ($interval === 'yearly') {
                $periodEnd = $periodEnd->modify('+' . $intervalCount . ' year');
            }
            $currentPeriodEnd = $periodEnd->format('Y-m-d H:i:sP');
        }

        $trialDays = $plan['trial_days'] ?? null;
        if ($trialOverrideInput !== '') {
            if (!ctype_digit($trialOverrideInput)) {
                $_SESSION['flash'] = __('Trial days must be numeric.');
                redirect($redirectTo);
            }
            $trialDays = (int)$trialOverrideInput;
        }

        $trialEndsAt = null;
        if ($status === 'trialing' && $trialDays !== null && $trialDays > 0) {
            $trialEndsAt = $now->modify('+' . $trialDays . ' day')->format('Y-m-d H:i:sP');
        }

        $cancelAt = null;
        $canceledAt = null;
        if ($status === 'canceled') {
            $cancelAt = $now->format('Y-m-d H:i:sP');
            $canceledAt = $cancelAt;
        } elseif ($status === 'expired') {
            $currentPeriodEnd = $now->format('Y-m-d H:i:sP');
        }

        $notes = $note !== '' ? $note : null;

        try {
            $insert = $pdo->prepare(
                'INSERT INTO user_subscriptions (user_id, plan_code, plan_name, status, billing_interval, interval_count, amount, currency, started_at, current_period_start, current_period_end, cancel_at, canceled_at, trial_ends_at, notes) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([
                $userId,
                $plan['code'],
                $plan['name'],
                $status,
                $plan['billing_interval'],
                $intervalCount,
                $plan['price'],
                $plan['currency'],
                $startedAt,
                $currentPeriodStart,
                $currentPeriodEnd,
                $cancelAt,
                $canceledAt,
                $trialEndsAt,
                $notes,
            ]);
        } catch (Throwable $e) {
            $_SESSION['flash'] = __('Unable to create subscription record.');
            redirect($redirectTo);
        }
    }

    $_SESSION['flash_success'] = __('User upgraded to :plan.', ['plan' => $plan['name']]);
    redirect('/admin/billing');
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
