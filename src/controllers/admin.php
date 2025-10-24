<?php

require_once __DIR__ . '/../helpers.php';

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
        $stmt = $pdo->query('SELECT id, email, full_name, role, created_at FROM users ORDER BY created_at DESC LIMIT 8');
        if ($stmt instanceof PDOStatement) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $recentUsers[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'email' => (string)($row['email'] ?? ''),
                    'role' => (string)($row['role'] ?? 'user'),
                    'full_name' => pii_decrypt($row['full_name'] ?? null),
                    'created_at' => $row['created_at'] ?? null,
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

    $roleOptions = [
        'user' => __('User'),
        'admin' => __('Administrator'),
    ];

    view('admin/dashboard', [
        'pageTitle' => __('Administrator dashboard'),
        'stats' => $stats,
        'recentUsers' => $recentUsers,
        'roleOptions' => $roleOptions,
        'pendingMigrations' => $pendingMigrations,
        'totalMigrations' => $totalMigrations,
    ]);
}

function admin_update_role(PDO $pdo): void
{
    require_admin();
    verify_csrf();

    $userId = (int)($_POST['user_id'] ?? 0);
    $role = strtolower(trim((string)($_POST['role'] ?? '')));
    $allowedRoles = ['user', 'admin'];

    if ($userId <= 0 || !in_array($role, $allowedRoles, true)) {
        $_SESSION['flash'] = __('Invalid role selection.');
        redirect('/admin');
    }

    $stmt = $pdo->prepare('SELECT id, role, email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['flash'] = __('User not found.');
        redirect('/admin');
    }

    if ($userId === uid() && $role !== 'admin') {
        $_SESSION['flash'] = __('You cannot remove your own administrator access.');
        redirect('/admin');
    }

    if (($user['role'] ?? 'user') === $role) {
        $_SESSION['flash_success'] = __('No changes were necessary.');
        redirect('/admin');
    }

    try {
        $update = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
        $update->execute([$role, $userId]);
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('Could not update role. Please try again.');
        redirect('/admin');
    }

    if ($userId === uid()) {
        $_SESSION['role'] = $role;
    }

    $email = $user['email'] ?? '';
    $message = $role === 'admin'
        ? __(':email is now an administrator.', ['email' => $email])
        : __(':email is now a standard user.', ['email' => $email]);

    $_SESSION['flash_success'] = $message;
    redirect('/admin');
}
