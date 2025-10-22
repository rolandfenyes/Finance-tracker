<?php

declare(strict_types=1);

const ADMIN_ALLOWED_ROLES = ['superadmin', 'support', 'finance', 'dev'];

function admin_actor_id(): int
{
    if (admin_is_impersonating()) {
        return impersonator_id();
    }

    return uid();
}

function admin_current_admin(PDO $pdo): ?array
{
    static $cache;

    $actorId = admin_actor_id();
    if ($actorId <= 0) {
        return null;
    }

    if ($cache && (int)($cache['id'] ?? 0) === $actorId) {
        return $cache;
    }

    $stmt = $pdo->prepare('SELECT id, email, admin_role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$actorId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$row || !in_array($row['admin_role'] ?? '', ADMIN_ALLOWED_ROLES, true)) {
        $cache = null;
        return null;
    }

    return $cache = [
        'id' => (int)$row['id'],
        'email' => (string)$row['email'],
        'admin_role' => (string)$row['admin_role'],
    ];
}

function admin_forbidden(string $message = 'You do not have access to this area.'): void
{
    http_response_code(403);
    view('errors/403', [
        'pageTitle' => __('Access denied'),
        'message' => __($message),
    ]);
    exit;
}

function admin_require(PDO $pdo, array|string|null $allowedRoles = null): array
{
    $admin = admin_current_admin($pdo);
    if ($admin === null) {
        admin_forbidden();
    }

    if ($allowedRoles === null) {
        return $admin;
    }

    $roles = is_array($allowedRoles) ? array_map('strval', $allowedRoles) : [ (string)$allowedRoles ];

    if ($admin['admin_role'] === 'superadmin') {
        return $admin;
    }

    if (!in_array($admin['admin_role'], $roles, true)) {
        admin_forbidden();
    }

    return $admin;
}

function admin_log_action(PDO $pdo, ?int $subjectId, string $action, array $meta = []): void
{
    $actorId = admin_actor_id() ?: null;

    $json = '{}';
    if (!empty($meta)) {
        $encoded = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded !== false) {
            $json = $encoded;
        }
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO admin_audit_log(actor_id, subject_id, action, meta) VALUES (?, ?, ?, ?::jsonb)');
        $stmt->execute([$actorId, $subjectId, $action, $json]);
    } catch (Throwable $e) {
        error_log('Failed to record admin audit event: ' . $e->getMessage());
    }
}

function admin_relative_time(null|string|DateTimeInterface $time, ?DateTimeImmutable $now = null): string
{
    if ($time === null || $time === '') {
        return '—';
    }

    if (!$time instanceof DateTimeInterface) {
        try {
            $time = new DateTimeImmutable((string)$time);
        } catch (Throwable $e) {
            return '—';
        }
    }

    $now ??= new DateTimeImmutable('now');
    $diff = $now->getTimestamp() - $time->getTimestamp();

    $abs = abs($diff);
    $suffix = $diff >= 0 ? __('ago') : __('from now');

    if ($abs < 60) {
        return sprintf('%ds %s', $abs, $suffix);
    }

    if ($abs < 3600) {
        $value = max(1, (int)floor($abs / 60));
        return sprintf('%dm %s', $value, $suffix);
    }

    if ($abs < 86400) {
        $value = max(1, (int)floor($abs / 3600));
        return sprintf('%dh %s', $value, $suffix);
    }

    if ($abs < 604800) {
        $value = max(1, (int)floor($abs / 86400));
        return sprintf('%dd %s', $value, $suffix);
    }

    if ($abs < 2629743) {
        $value = max(1, (int)floor($abs / 604800));
        return sprintf('%dw %s', $value, $suffix);
    }

    if ($abs < 31556926) {
        $value = max(1, (int)floor($abs / 2629743));
        return sprintf('%dmo %s', $value, $suffix);
    }

    $years = max(1, (int)floor($abs / 31556926));
    return sprintf('%dy %s', $years, $suffix);
}

function admin_percent_change(float $current, float $previous): array
{
    if ($previous == 0.0) {
        return [
            'change' => $current === 0.0 ? '0%' : __('n/a'),
            'trend' => $current >= 0 ? 'up' : 'down',
        ];
    }

    $diff = $current - $previous;
    $percent = ($diff / abs($previous)) * 100;
    $trend = $diff >= 0 ? 'up' : 'down';

    return [
        'change' => sprintf('%+.1f%%', $percent),
        'trend' => $trend,
    ];
}

function admin_dashboard_index(PDO $pdo): void
{
    $admin = admin_require($pdo);

    $now = new DateTimeImmutable('now');
    $currentStart = $now->sub(new DateInterval('P30D'));
    $previousStart = $now->sub(new DateInterval('P60D'));

    $totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE created_at >= ?');
    $stmt->execute([$currentStart->format('Y-m-d H:i:s')]);
    $newUsersCurrent = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE created_at >= ? AND created_at < ?');
    $stmt->execute([$previousStart->format('Y-m-d H:i:s'), $currentStart->format('Y-m-d H:i:s')]);
    $newUsersPrevious = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(DISTINCT user_id) FROM transactions WHERE occurred_on >= ?');
    $stmt->execute([$currentStart->format('Y-m-d')]);
    $activeUsersCurrent = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(DISTINCT user_id) FROM transactions WHERE occurred_on >= ? AND occurred_on < ?');
    $stmt->execute([$previousStart->format('Y-m-d'), $currentStart->format('Y-m-d')]);
    $activeUsersPrevious = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN kind='income' THEN amount ELSE -amount END),0) FROM transactions WHERE occurred_on >= ?");
    $stmt->execute([$currentStart->format('Y-m-d')]);
    $netVolumeCurrent = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN kind='income' THEN amount ELSE -amount END),0) FROM transactions WHERE occurred_on >= ? AND occurred_on < ?");
    $stmt->execute([$previousStart->format('Y-m-d'), $currentStart->format('Y-m-d')]);
    $netVolumePrevious = (float)$stmt->fetchColumn();

    $openFeedback = (int)$pdo->query("SELECT COUNT(*) FROM feedback WHERE status IN ('open','in_progress')")->fetchColumn();

    $kpis = [];
    $kpis[] = [
        'label' => __('Total users'),
        'value' => number_format($totalUsers),
        'change' => sprintf('%+d %s', $newUsersCurrent - $newUsersPrevious, __('vs. prior 30d')), 
        'trend' => ($newUsersCurrent - $newUsersPrevious) >= 0 ? 'up' : 'down',
    ];

    $change = admin_percent_change((float)$activeUsersCurrent, (float)$activeUsersPrevious);
    $kpis[] = [
        'label' => __('Active users (30d)'),
        'value' => number_format($activeUsersCurrent),
        'change' => $change['change'],
        'trend' => $change['trend'],
    ];

    $changeVolume = admin_percent_change($netVolumeCurrent, $netVolumePrevious);
    $kpis[] = [
        'label' => __('Net cashflow (30d)'),
        'value' => moneyfmt($netVolumeCurrent, ''),
        'change' => $changeVolume['change'],
        'trend' => $changeVolume['trend'],
    ];

    $feedbackChange = $openFeedback > 0 ? sprintf(__('%d open items'), $openFeedback) : __('All caught up');
    $kpis[] = [
        'label' => __('Feedback activity'),
        'value' => sprintf('%d %s', $openFeedback, __('open')), 
        'change' => $feedbackChange,
        'trend' => $openFeedback > 0 ? 'down' : 'up',
    ];

    $recentActivity = admin_recent_activity($pdo, $now);
    $quickActions = admin_quick_actions();
    $systemHealth = admin_system_health($pdo, $now);
    $featureSections = admin_feature_sections($pdo, $totalUsers, $activeUsersCurrent, $openFeedback);

    view('admin/dashboard', [
        'pageTitle' => __('Admin Control Center'),
        'fullWidthMain' => true,
        'kpis' => $kpis,
        'recentActivity' => $recentActivity,
        'quickActions' => $quickActions,
        'systemHealth' => $systemHealth,
        'featureSections' => $featureSections,
        'adminContext' => $admin,
    ]);
}

function admin_recent_activity(PDO $pdo, DateTimeImmutable $now): array
{
    $items = [];

    $signups = $pdo->query('SELECT id, email, created_at FROM users ORDER BY created_at DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($signups as $row) {
        $items[] = [
            'type' => 'Signup',
            'title' => sprintf(__('New account: %s'), $row['email']),
            'meta' => sprintf('ID %d', (int)$row['id']),
            'time' => admin_relative_time($row['created_at'], $now),
            'timestamp' => (string)$row['created_at'],
            'status' => 'success',
        ];
    }

    $transactions = $pdo->query('SELECT t.id, t.user_id, t.kind, t.amount, t.currency, t.created_at, u.email FROM transactions t JOIN users u ON u.id = t.user_id ORDER BY t.created_at DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($transactions as $row) {
        $items[] = [
            'type' => ucfirst((string)$row['kind']),
            'title' => sprintf('%s %s', ucfirst((string)$row['kind']), moneyfmt((float)$row['amount'], (string)$row['currency'])),
            'meta' => sprintf('%s · #%d', $row['email'], (int)$row['id']),
            'time' => admin_relative_time($row['created_at'], $now),
            'timestamp' => (string)$row['created_at'],
            'status' => $row['kind'] === 'income' ? 'success' : 'warning',
        ];
    }

    $feedback = $pdo->query('SELECT f.id, f.kind, f.status, f.created_at, u.email FROM feedback f JOIN users u ON u.id = f.user_id ORDER BY f.created_at DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($feedback as $row) {
        $items[] = [
            'type' => ucfirst((string)$row['kind']),
            'title' => sprintf(__('Feedback #%d (%s)'), (int)$row['id'], __((string)$row['status'])),
            'meta' => $row['email'],
            'time' => admin_relative_time($row['created_at'], $now),
            'timestamp' => (string)$row['created_at'],
            'status' => in_array($row['status'], ['open', 'in_progress'], true) ? 'warning' : 'info',
        ];
    }

    usort($items, static function (array $a, array $b) {
        return strcmp((string)$b['timestamp'], (string)$a['timestamp']);
    });

    return array_slice(array_map(static function (array $item): array {
        unset($item['timestamp']);
        return $item;
    }, $items), 0, 10);
}

function admin_quick_actions(): array
{
    return [
        [
            'label' => __('Manage users'),
            'description' => __('Search accounts, filter by status, and review activity.'),
            'href' => '/admin/users',
            'icon' => '👥',
        ],
        [
            'label' => __('Export user directory'),
            'description' => __('Download a CSV snapshot with verification and activity markers.'),
            'href' => '/admin/users/export',
            'icon' => '⬇️',
        ],
        [
            'label' => __('Review open feedback'),
            'description' => __('Triage product ideas and bug reports from customers.'),
            'href' => '/feedback?tab=open',
            'icon' => '🛠️',
        ],
        [
            'label' => __('Audit latest admin actions'),
            'description' => __('Inspect impersonations, suspensions, and sensitive updates.'),
            'href' => '#admin-audit-log',
            'icon' => '📜',
        ],
    ];
}

function admin_system_health(PDO $pdo, DateTimeImmutable $now): array
{
    $unverified = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE email_verified_at IS NULL')->fetchColumn();
    $suspended = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE suspended_at IS NOT NULL')->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM scheduled_payments WHERE next_due IS NOT NULL AND next_due < ?');
    $stmt->execute([$now->format('Y-m-d')]);
    $overdueSchedules = (int)$stmt->fetchColumn();

    $openFeedback = (int)$pdo->query("SELECT COUNT(*) FROM feedback WHERE status IN ('open','in_progress')")->fetchColumn();

    return [
        [
            'label' => __('Database connectivity'),
            'value' => __('Operational'),
            'detail' => __('Last health check ran now'),
            'status' => 'operational',
        ],
        [
            'label' => __('Email verification backlog'),
            'value' => sprintf('%d %s', $unverified, __('pending')),
            'detail' => $unverified === 0 ? __('All users verified') : __('Follow up with recent signups'),
            'status' => $unverified > 0 ? 'warning' : 'operational',
        ],
        [
            'label' => __('Suspended accounts'),
            'value' => number_format($suspended),
            'detail' => $suspended ? __('Review status regularly') : __('None currently'),
            'status' => $suspended > 0 ? 'info' : 'operational',
        ],
        [
            'label' => __('Overdue schedules'),
            'value' => number_format($overdueSchedules),
            'detail' => $overdueSchedules ? __('Some recurring payments require attention') : __('All schedules current'),
            'status' => $overdueSchedules > 0 ? 'warning' : 'operational',
        ],
        [
            'label' => __('Open feedback'),
            'value' => number_format($openFeedback),
            'detail' => __('Across bugs and ideas'),
            'status' => $openFeedback > 10 ? 'warning' : 'operational',
        ],
    ];
}

function admin_feature_sections(PDO $pdo, int $totalUsers, int $activeUsers, int $openFeedback): array
{
    $suspended = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE suspended_at IS NOT NULL')->fetchColumn();
    $unverified = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE email_verified_at IS NULL')->fetchColumn();

    $stmt = $pdo->query('SELECT COUNT(*) FROM transactions');
    $transactionsTotal = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM goals WHERE status = 'active'");
    $activeGoals = (int)$stmt->fetchColumn();
    $stmt = $pdo->query("SELECT COUNT(*) FROM goals WHERE status = 'done'");
    $completedGoals = (int)$stmt->fetchColumn();

    $stmt = $pdo->query('SELECT COUNT(*) FROM loans');
    $loanCount = (int)$stmt->fetchColumn();

    $scheduledPayments = (int)$pdo->query('SELECT COUNT(*) FROM scheduled_payments')->fetchColumn();

    $feedbackIdeas = (int)$pdo->query("SELECT COUNT(*) FROM feedback WHERE kind = 'idea'")->fetchColumn();

    $sections = [];

    $sections[] = [
        'title' => __('User management'),
        'description' => __('Stay on top of accounts, verification status, and administrative flags.'),
        'items' => [
            [
                'title' => __('Directory overview'),
                'summary' => sprintf(__('%d total · %d active last 30d'), $totalUsers, $activeUsers),
                'href' => '/admin/users',
            ],
            [
                'title' => __('Verification queue'),
                'summary' => sprintf(__('%d pending email confirmations'), $unverified),
                'href' => '/admin/users?status=unverified',
                'badge' => $unverified ? __('Action') : null,
            ],
            [
                'title' => __('Suspended users'),
                'summary' => sprintf(__('%d accounts currently paused'), $suspended),
                'href' => '/admin/users?status=suspended',
            ],
        ],
    ];

    $sections[] = [
        'title' => __('Financial activity'),
        'description' => __('Understand how households are logging transactions and recurring movements.'),
        'items' => [
            [
                'title' => __('Transactions logged'),
                'summary' => sprintf(__('%d lifetime entries'), $transactionsTotal),
            ],
            [
                'title' => __('Scheduled payments'),
                'summary' => sprintf(__('%d automation rules active'), $scheduledPayments),
            ],
        ],
    ];

    $sections[] = [
        'title' => __('Goals & savings'),
        'description' => __('Track progress across emergency funds and long-term objectives.'),
        'items' => [
            [
                'title' => __('Active goals'),
                'summary' => sprintf(__('%d in progress'), $activeGoals),
            ],
            [
                'title' => __('Completed goals'),
                'summary' => sprintf(__('%d achieved milestones'), $completedGoals),
            ],
        ],
    ];

    $sections[] = [
        'title' => __('Loans & liabilities'),
        'description' => __('Monitor outstanding loans and repayment trends.'),
        'items' => [
            [
                'title' => __('Active loans'),
                'summary' => sprintf(__('%d repayment schedules tracked'), $loanCount),
            ],
        ],
    ];

    $sections[] = [
        'title' => __('Support & feedback'),
        'description' => __('Close the loop on product ideas and bug reports from the community.'),
        'items' => [
            [
                'title' => __('Open items'),
                'summary' => sprintf(__('%d needing attention'), $openFeedback),
                'href' => '/feedback?tab=open',
                'badge' => $openFeedback > 0 ? __('Backlog') : null,
            ],
            [
                'title' => __('Ideas logged'),
                'summary' => sprintf(__('%d suggestions in queue'), $feedbackIdeas),
            ],
        ],
    ];

    return $sections;
}

function admin_users_index(PDO $pdo): void
{
    admin_require($pdo);

    $filters = [
        'q' => trim((string)($_GET['q'] ?? '')),
        'status' => $_GET['status'] ?? 'all',
        'role' => $_GET['role'] ?? 'all',
        'sort' => $_GET['sort'] ?? 'recent',
        'page' => max(1, (int)($_GET['page'] ?? 1)),
    ];

    if (!in_array($filters['status'], ['all', 'active', 'suspended', 'unverified'], true)) {
        $filters['status'] = 'all';
    }

    if (!in_array($filters['role'], array_merge(['all'], ADMIN_ALLOWED_ROLES), true)) {
        $filters['role'] = 'all';
    }

    if (!in_array($filters['sort'], ['recent', 'oldest', 'last-login'], true)) {
        $filters['sort'] = 'recent';
    }

    $where = [];
    $params = [];

    if ($filters['q'] !== '') {
        $where[] = 'u.email ILIKE ?';
        $params[] = '%' . $filters['q'] . '%';
    }

    if ($filters['status'] === 'suspended') {
        $where[] = 'u.suspended_at IS NOT NULL';
    } elseif ($filters['status'] === 'unverified') {
        $where[] = 'u.email_verified_at IS NULL';
    } elseif ($filters['status'] === 'active') {
        $where[] = 'u.suspended_at IS NULL';
    }

    if ($filters['role'] !== 'all') {
        $where[] = 'u.admin_role = ?';
        $params[] = $filters['role'];
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM users u $whereSql");
    $totalStmt->execute($params);
    $total = (int)$totalStmt->fetchColumn();

    $perPage = 25;
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($filters['page'], $pages);
    $offset = ($page - 1) * $perPage;

    $orderBy = match ($filters['sort']) {
        'oldest' => 'u.created_at ASC',
        'last-login' => 'u.last_login_at DESC NULLS LAST',
        default => 'u.created_at DESC',
    };

    $query = "
        SELECT
            u.id,
            u.email,
            u.full_name,
            u.created_at,
            u.admin_role,
            u.email_verified_at,
            u.suspended_at,
            u.last_login_at,
            u.desired_language,
            u.admin_notes
        FROM users u
        $whereSql
        ORDER BY $orderBy, u.id DESC
        LIMIT $perPage OFFSET $offset
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $userIds = array_map(static fn ($row) => (int)$row['id'], $rows);

    $activity = [];
    $goals = [];
    $scheduled = [];
    $feedback = [];

    if ($userIds) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));

        $txStmt = $pdo->prepare("SELECT user_id, COUNT(*) AS cnt, MAX(created_at) AS last_tx FROM transactions WHERE user_id IN ($placeholders) GROUP BY user_id");
        $txStmt->execute($userIds);
        foreach ($txStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $activity[(int)$row['user_id']] = [
                'count' => (int)$row['cnt'],
                'last' => $row['last_tx'],
            ];
        }

        $goalStmt = $pdo->prepare("SELECT user_id, COUNT(*) AS cnt FROM goals WHERE user_id IN ($placeholders) GROUP BY user_id");
        $goalStmt->execute($userIds);
        foreach ($goalStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $goals[(int)$row['user_id']] = (int)$row['cnt'];
        }

        $schedStmt = $pdo->prepare("SELECT user_id, COUNT(*) AS cnt FROM scheduled_payments WHERE user_id IN ($placeholders) GROUP BY user_id");
        $schedStmt->execute($userIds);
        foreach ($schedStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $scheduled[(int)$row['user_id']] = (int)$row['cnt'];
        }

        $feedStmt = $pdo->prepare("SELECT user_id, COUNT(*) AS cnt FROM feedback WHERE status IN ('open','in_progress') AND user_id IN ($placeholders) GROUP BY user_id");
        $feedStmt->execute($userIds);
        foreach ($feedStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $feedback[(int)$row['user_id']] = (int)$row['cnt'];
        }
    }

    foreach ($rows as &$row) {
        $row['full_name'] = pii_decrypt($row['full_name'] ?? null);
        $uid = (int)$row['id'];
        $row['transactions'] = $activity[$uid]['count'] ?? 0;
        $row['last_transaction_at'] = $activity[$uid]['last'] ?? null;
        $row['goals_count'] = $goals[$uid] ?? 0;
        $row['scheduled_count'] = $scheduled[$uid] ?? 0;
        $row['feedback_open'] = $feedback[$uid] ?? 0;
    }
    unset($row);

    $summary = [
        'total' => $total,
        'suspended' => (int)$pdo->query('SELECT COUNT(*) FROM users WHERE suspended_at IS NOT NULL')->fetchColumn(),
        'unverified' => (int)$pdo->query('SELECT COUNT(*) FROM users WHERE email_verified_at IS NULL')->fetchColumn(),
        'openFeedback' => (int)$pdo->query("SELECT COUNT(*) FROM feedback WHERE status IN ('open','in_progress')")->fetchColumn(),
    ];

    view('admin/users/index', [
        'pageTitle' => __('User directory'),
        'users' => $rows,
        'filters' => $filters,
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'summary' => $summary,
    ]);
}

function admin_users_export(PDO $pdo): void
{
    admin_require($pdo, ['superadmin', 'finance']);

    $stmt = $pdo->query(
        "SELECT
            u.id,
            u.email,
            u.full_name,
            u.created_at,
            u.email_verified_at,
            u.admin_role,
            u.suspended_at,
            u.last_login_at,
            u.desired_language,
            (SELECT COUNT(*) FROM transactions WHERE user_id = u.id) AS transactions_count,
            (SELECT COALESCE(SUM(CASE WHEN kind = ''income'' THEN amount ELSE 0 END),0) FROM transactions WHERE user_id = u.id) AS income_total,
            (SELECT COALESCE(SUM(CASE WHEN kind = ''spending'' THEN amount ELSE 0 END),0) FROM transactions WHERE user_id = u.id) AS spending_total,
            (SELECT COUNT(*) FROM goals WHERE user_id = u.id) AS goals_count,
            (SELECT COUNT(*) FROM scheduled_payments WHERE user_id = u.id) AS scheduled_count
        FROM users u
        ORDER BY u.created_at ASC"
    );

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'users-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'wb');
    fputcsv($out, ['id', 'email', 'full_name', 'created_at', 'verified_at', 'admin_role', 'suspended_at', 'last_login_at', 'language', 'transactions', 'income_total', 'spending_total', 'goals', 'schedules']);

    foreach ($rows as $row) {
        $name = pii_decrypt($row['full_name'] ?? null);
        fputcsv($out, [
            $row['id'],
            $row['email'],
            $name,
            $row['created_at'],
            $row['email_verified_at'],
            $row['admin_role'],
            $row['suspended_at'],
            $row['last_login_at'],
            $row['desired_language'],
            $row['transactions_count'],
            $row['income_total'],
            $row['spending_total'],
            $row['goals_count'],
            $row['scheduled_count'],
        ]);
    }

    fclose($out);
    admin_log_action($pdo, null, 'export_users', ['row_count' => count($rows)]);
    exit;
}

function admin_users_show(PDO $pdo, int $userId): void
{
    admin_require($pdo);

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        view('errors/404', ['pageTitle' => __('User not found')]);
        return;
    }

    $user['full_name'] = pii_decrypt($user['full_name'] ?? null);

    $currenciesStmt = $pdo->prepare('SELECT code, is_main FROM user_currencies WHERE user_id = ? ORDER BY is_main DESC, code');
    $currenciesStmt->execute([$userId]);
    $currencies = $currenciesStmt->fetchAll(PDO::FETCH_ASSOC);

    $transactionsSummary = admin_user_transactions_summary($pdo, $userId);
    $recentTransactions = admin_user_recent_transactions($pdo, $userId);
    $goals = admin_user_goals($pdo, $userId);
    $loans = admin_user_loans($pdo, $userId);
    $scheduled = admin_user_schedules($pdo, $userId);
    $feedback = admin_user_feedback($pdo, $userId);
    $emergency = admin_user_emergency($pdo, $userId);
    $auditLog = admin_user_audit_log($pdo, $userId);

    view('admin/users/show', [
        'pageTitle' => sprintf(__('User #%d'), $userId),
        'user' => $user,
        'currencies' => $currencies,
        'transactionsSummary' => $transactionsSummary,
        'recentTransactions' => $recentTransactions,
        'goals' => $goals,
        'loans' => $loans,
        'scheduled' => $scheduled,
        'feedback' => $feedback,
        'emergency' => $emergency,
        'auditLog' => $auditLog,
        'availableRoles' => ADMIN_ALLOWED_ROLES,
        'availableLocales' => available_locales(),
    ]);
}

function admin_user_transactions_summary(PDO $pdo, int $userId): array
{
    $summary = [
        'income_total' => 0.0,
        'spending_total' => 0.0,
        'net_total' => 0.0,
        'income_30d' => 0.0,
        'spending_30d' => 0.0,
        'net_30d' => 0.0,
        'count' => 0,
    ];

    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(CASE WHEN kind='income' THEN amount ELSE 0 END),0) AS incomes, COALESCE(SUM(CASE WHEN kind='spending' THEN amount ELSE 0 END),0) AS spendings FROM transactions WHERE user_id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $summary['count'] = (int)$row['cnt'];
        $summary['income_total'] = (float)$row['incomes'];
        $summary['spending_total'] = (float)$row['spendings'];
        $summary['net_total'] = $summary['income_total'] - $summary['spending_total'];
    }

    $since = (new DateTimeImmutable('now'))->sub(new DateInterval('P30D'))->format('Y-m-d');
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN kind='income' THEN amount ELSE 0 END),0) AS incomes, COALESCE(SUM(CASE WHEN kind='spending' THEN amount ELSE 0 END),0) AS spendings FROM transactions WHERE user_id = ? AND occurred_on >= ?");
    $stmt->execute([$userId, $since]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $summary['income_30d'] = (float)$row['incomes'];
        $summary['spending_30d'] = (float)$row['spendings'];
        $summary['net_30d'] = $summary['income_30d'] - $summary['spending_30d'];
    }

    return $summary;
}

function admin_user_recent_transactions(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('
        SELECT t.id, t.kind, t.amount, t.currency, t.occurred_on, t.created_at, t.note, c.label AS category
        FROM transactions t
        LEFT JOIN categories c ON c.id = t.category_id
        WHERE t.user_id = ?
        ORDER BY t.created_at DESC
        LIMIT 10
    ');
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function admin_user_goals(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT id, title, target_amount, current_amount, currency, status, deadline FROM goals WHERE user_id = ? ORDER BY id DESC LIMIT 10');
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function admin_user_loans(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT id, name, principal, balance, interest_rate, start_date, end_date FROM loans WHERE user_id = ? ORDER BY id DESC LIMIT 10');
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function admin_user_schedules(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT id, title, amount, currency, next_due, rrule FROM scheduled_payments WHERE user_id = ? ORDER BY next_due ASC NULLS LAST, id DESC LIMIT 15');
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function admin_user_feedback(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT id, kind, status, title, created_at, updated_at FROM feedback WHERE user_id = ? ORDER BY created_at DESC LIMIT 10');
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function admin_user_emergency(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT target_amount, total, currency FROM emergency_fund WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($row) {
        $row['progress'] = $row['target_amount'] > 0 ? min(100, round(($row['total'] / $row['target_amount']) * 100)) : null;
    }

    return $row;
}

function admin_user_audit_log(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('
        SELECT l.id, l.actor_id, l.action, l.meta, l.created_at, a.email AS actor_email
        FROM admin_audit_log l
        LEFT JOIN users a ON a.id = l.actor_id
        WHERE l.subject_id = ?
        ORDER BY l.created_at DESC
        LIMIT 20
    ');
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        if (is_string($row['meta'])) {
            $decoded = json_decode($row['meta'], true);
            if (is_array($decoded)) {
                $row['meta'] = $decoded;
            }
        }
    }
    unset($row);

    return $rows;
}

function admin_users_update(PDO $pdo, int $userId): void
{
    admin_require($pdo, ['superadmin']);
    verify_csrf();

    $stmt = $pdo->prepare('SELECT id, email, admin_role, admin_notes, suspended_at FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        view('errors/404', ['pageTitle' => __('User not found')]);
        return;
    }

    $role = $_POST['admin_role'] ?? '';
    if ($role !== '' && !in_array($role, ADMIN_ALLOWED_ROLES, true)) {
        $role = null;
    }

    $desiredLanguage = $_POST['desired_language'] ?? null;
    $locales = available_locales();
    if ($desiredLanguage !== null && !isset($locales[$desiredLanguage])) {
        $desiredLanguage = null;
    }

    $notes = trim((string)($_POST['admin_notes'] ?? ''));
    $shouldSuspend = isset($_POST['suspend']) && $_POST['suspend'] === '1';

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE users SET admin_role = ?, desired_language = ?, admin_notes = ?, suspended_at = CASE WHEN ? THEN COALESCE(suspended_at, NOW()) ELSE NULL END WHERE id = ?');
        $stmt->execute([
            $role,
            $desiredLanguage,
            $notes !== '' ? $notes : null,
            $shouldSuspend,
            $userId,
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        $_SESSION['flash'] = __('Failed to update user.');
        redirect('/admin/users/' . $userId);
    }

    admin_log_action($pdo, $userId, 'update_user', [
        'admin_role' => $role,
        'suspended' => $shouldSuspend,
    ]);

    $_SESSION['flash_success'] = __('User updated successfully.');
    redirect('/admin/users/' . $userId);
}

function admin_users_impersonate(PDO $pdo, int $userId): void
{
    $admin = admin_require($pdo, ['superadmin', 'support']);
    verify_csrf();

    if ($userId === $admin['id']) {
        $_SESSION['flash'] = __('You are already using your own account.');
        redirect('/admin/users/' . $userId);
    }

    $stmt = $pdo->prepare('SELECT id, email FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $_SESSION['flash'] = __('Target user not found.');
        redirect('/admin/users');
    }

    $_SESSION['impersonator_id'] = $admin['id'];
    $_SESSION['impersonator_email'] = $admin['email'];
    $_SESSION['impersonated_email'] = $user['email'];
    $_SESSION['uid'] = (int)$user['id'];

    admin_log_action($pdo, (int)$user['id'], 'impersonate_start', ['target_email' => $user['email']]);

    $_SESSION['flash_success'] = __('Impersonation active. You are now viewing the product as the selected user.');
    redirect('/');
}

function admin_users_stop_impersonating(PDO $pdo): void
{
    verify_csrf();

    $impersonator = impersonator_id();
    if ($impersonator <= 0) {
        redirect('/');
    }

    $previousUser = uid();
    $_SESSION['uid'] = $impersonator;
    unset($_SESSION['impersonator_id'], $_SESSION['impersonator_email'], $_SESSION['impersonated_email']);

    admin_log_action($pdo, $previousUser, 'impersonate_stop', []);

    $_SESSION['flash_success'] = __('Returned to your admin session.');
    redirect('/admin/users/' . $previousUser);
}
