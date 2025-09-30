<?php
require_once __DIR__ . '/../helpers.php';

function settings_privacy_show(PDO $pdo): void
{
    require_login();

    $userId = uid();
    $stmt = $pdo->prepare('SELECT email, full_name, created_at, needs_tutorial, onboard_step FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $account['full_name'] = pii_decrypt($account['full_name'] ?? null);

    $counts = [
        'transactions' => fetch_single_value($pdo, 'SELECT COUNT(*) FROM transactions WHERE user_id = ?', [$userId]),
        'goals' => fetch_single_value($pdo, 'SELECT COUNT(*) FROM goals WHERE user_id = ?', [$userId]),
        'scheduled' => fetch_single_value($pdo, 'SELECT COUNT(*) FROM scheduled_payments WHERE user_id = ?', [$userId]),
        'feedback' => fetch_single_value($pdo, 'SELECT COUNT(*) FROM feedback WHERE user_id = ?', [$userId]),
    ];

    $lastExport = $_SESSION['privacy_export_generated_at'] ?? null;

    view('settings/privacy', [
        'account' => $account,
        'counts' => $counts,
        'lastExport' => $lastExport,
    ]);
}

function settings_privacy_export(PDO $pdo): void
{
    verify_csrf();
    require_login();

    $userId = uid();

    try {
        $payload = build_user_data_export($pdo, $userId);
    } catch (Throwable $e) {
        $_SESSION['flash'] = __('We could not generate your export. Please try again.');
        redirect('/settings/privacy');
    }

    $_SESSION['privacy_export_generated_at'] = $payload['generated_at'] ?? date(DATE_ATOM);

    $filename = 'mymoneymap-export-' . date('Ymd-His') . '.json';
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    if ($json === false) {
        $_SESSION['flash'] = __('We could not generate your export. Please try again.');
        redirect('/settings/privacy');
    }

    if (!headers_sent()) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        header('Pragma: no-cache');
    }

    echo $json;
    exit;
}

function settings_privacy_delete(PDO $pdo): void
{
    verify_csrf();
    require_login();

    $userId = uid();
    $confirmation = trim((string)($_POST['confirm_email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $stmt = $pdo->prepare('SELECT email, password_hash FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['flash'] = __('Account not found.');
        redirect('/settings/privacy');
    }

    $email = (string)($user['email'] ?? '');

    if ($confirmation === '' || strcasecmp($confirmation, $email) !== 0) {
        $_SESSION['flash'] = __('Please type your email address to confirm deletion.');
        redirect('/settings/privacy');
    }

    if (!password_verify($password, (string)($user['password_hash'] ?? ''))) {
        $_SESSION['flash'] = __('Your password confirmation was incorrect.');
        redirect('/settings/privacy');
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare('DELETE FROM user_remember_tokens WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM user_passkeys WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM feedback WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['flash'] = __('We could not delete your account. Please contact support.');
        redirect('/settings/privacy');
    }

    forget_remember_token($pdo, $userId);

    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['flash_success'] = __('Your account and personal data have been deleted.');

    redirect('/login');
}

function fetch_single_value(PDO $pdo, string $sql, array $params = [])
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value !== false ? (int)$value : 0;
    } catch (Throwable $e) {
        return 0;
    }
}

function build_user_data_export(PDO $pdo, int $userId): array
{
    $metaStmt = $pdo->prepare('SELECT id, email, full_name, date_of_birth, created_at, theme FROM users WHERE id = ? LIMIT 1');
    $metaStmt->execute([$userId]);
    $profile = $metaStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $profile['full_name'] = pii_decrypt($profile['full_name'] ?? null);
    unset($profile['id']);

    $collections = [
        'user_currencies' => 'SELECT code, is_main FROM user_currencies WHERE user_id = ? ORDER BY code',
        'cashflow_rules' => 'SELECT id, label, percent, target_hint FROM cashflow_rules WHERE user_id = ? ORDER BY id',
        'categories' => 'SELECT id, label, kind, color, cashflow_rule_id, system_key, protected FROM categories WHERE user_id = ? ORDER BY id',
        'basic_incomes' => 'SELECT id, label, amount, currency, valid_from, valid_to FROM basic_incomes WHERE user_id = ? ORDER BY valid_from',
        'transactions' => 'SELECT id, kind, category_id, amount, currency, occurred_on, note, created_at, updated_at, source, source_ref_id, locked FROM transactions WHERE user_id = ? ORDER BY occurred_on',
        'scheduled_payments' => 'SELECT id, title, amount, currency, rrule, next_due, category_id, loan_id, goal_id FROM scheduled_payments WHERE user_id = ? ORDER BY id',
        'goals' => 'SELECT id, title, target_amount, current_amount, currency, deadline, priority, status FROM goals WHERE user_id = ? ORDER BY id',
        'goal_transactions' => 'SELECT id, goal_id, occurred_on, amount, currency, note FROM goal_transactions WHERE user_id = ? ORDER BY occurred_on',
        'goal_contributions' => 'SELECT id, goal_id, amount, currency, occurred_on, note, created_at FROM goal_contributions WHERE user_id = ? ORDER BY occurred_on',
        'loans' => 'SELECT id, name, principal, interest_rate, start_date, end_date, payment_day, extra_payment, balance, currency, insurance_monthly, history_confirmed, scheduled_payment_id FROM loans WHERE user_id = ? ORDER BY id',
        'loan_payments' => 'SELECT id, loan_id, paid_on, amount, principal_component, interest_component, currency FROM loan_payments WHERE loan_id IN (SELECT id FROM loans WHERE user_id = ?)',
        'emergency_fund' => 'SELECT target_amount, currency, total FROM emergency_fund WHERE user_id = ?',
        'emergency_fund_tx' => 'SELECT id, occurred_on, kind, amount_native, currency_native, amount_main, main_currency, rate_used, note FROM emergency_fund_tx WHERE user_id = ? ORDER BY occurred_on',
        'emergency_transactions' => 'SELECT id, occurred_on, amount, kind, note FROM emergency_transactions WHERE user_id = ? ORDER BY occurred_on',
        'baby_steps' => 'SELECT step, status, note FROM baby_steps WHERE user_id = ? ORDER BY step',
        'stock_trades' => 'SELECT id, symbol, trade_on, side, quantity, price, amount, fee, currency FROM stock_trades WHERE user_id = ? ORDER BY trade_on',
        'feedback' => 'SELECT id, kind, title, message, severity, status, created_at, updated_at FROM feedback WHERE user_id = ? ORDER BY created_at',
        'user_remember_tokens' => 'SELECT selector, token_hash, expires_at, created_at FROM user_remember_tokens WHERE user_id = ? ORDER BY created_at',
        'user_passkeys' => 'SELECT id, credential_id, label, sign_count, created_at, last_used FROM user_passkeys WHERE user_id = ? ORDER BY created_at',
    ];

    $data = [];
    foreach ($collections as $key => $sql) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
            $data[$key] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $data[$key] = [];
        }
    }

    return [
        'generated_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
        'user' => $profile,
        'datasets' => $data,
    ];
}
