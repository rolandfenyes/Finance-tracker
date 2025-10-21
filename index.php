<?php
$root = __DIR__;
require $root . '/config/load_env.php';
$config = require $root . '/config/config.php';

$sessionName = $config['app']['session_name'] ?? 'moneymap_sess';
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on');

if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        $cookieParams = [
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        session_set_cookie_params($cookieParams);
        if ($sessionName && session_name() !== $sessionName) {
            session_name($sessionName);
        }
    }
    session_start();
}

require $root . '/config/db.php';
require $root . '/src/helpers.php';
require $root . '/src/auth.php';
require $root . '/src/webauthn.php';
require $root . '/src/fx.php';
require $root . '/src/scheduled_runner.php';

if (isset($pdo) && $pdo instanceof PDO) {
    attempt_remembered_login($pdo);
}

if (isset($pdo) && $pdo instanceof PDO && is_logged_in()) {
    scheduled_process_linked($pdo, uid());
}

$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = rtrim($config['app']['base_url'] ?? '', '/');
if ($base !== '' && str_starts_with($rawPath, $base)) {
    $rawPath = substr($rawPath, strlen($base));
}
$path = rtrim($rawPath, '/') ?: '/';

// Normalize request info for the router
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');           // "GET", "POST", etc.

// (optional) honor method override from forms like _method=DELETE
if ($method === 'POST' && !empty($_POST['_method'])) {
    $override = strtoupper(trim($_POST['_method']));
    if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
        $method = $override;
    }
}

// Dynamic routes for stocks module
if ($method === 'GET' && preg_match('#^/stocks/([A-Za-z0-9\.-:]+)$#', $path, $m)) {
    $slug = strtolower($m[1]);
    $reserved = ['transactions', 'trade', 'import', 'cash', 'refresh', 'clear'];
    if (!in_array($slug, $reserved, true)) {
        require_login();
        require __DIR__ . '/src/controllers/stocks.php';
        stocks_detail($pdo, $m[1]);
        return;
    }
}

if (preg_match('#^/stocks/([A-Za-z0-9\.-:]+)/watch$#', $path, $m)) {
    require_login();
    require __DIR__ . '/src/controllers/stocks.php';
    if ($method === 'POST') {
        stocks_toggle_watch($pdo, $m[1]);
    } else {
        redirect('/stocks/' . urlencode($m[1]));
    }
    return;
}

if (preg_match('#^/api/stocks/([A-Za-z0-9\.-:]+)/history$#', $path, $m)) {
    require_login();
    require __DIR__ . '/src/controllers/stocks.php';
    stocks_history_api($pdo, $m[1]);
    return;
}

// Simple routing
switch ($path) {
    case '/':
        if (!is_logged_in()) {
            view('marketing/landing', [
                'pageTitle' => 'MyMoneyMap — Clarity for your money',
                'pageDescription' => 'Understand where your money goes and build savings faster with MyMoneyMap.',
                'pageOgImage' => '/android-chrome-512x512.png',
                'fullWidthMain' => true,
                'disableMainPadding' => true,
                'mainClassOverride' => 'relative z-10 flex-1 w-full px-0',
                'skipGlobalFooter' => true,
            ]);
            break;
        }
        view('dashboard');
        break;

    // Registration
    case '/register':
        require __DIR__ . '/src/controllers/auth.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { register_step1_submit($pdo); }
        register_step1_form(); // GET
        break;

    case '/verify-email':
        require __DIR__ . '/src/controllers/email_verification.php';
        email_verification_handle($pdo);
        break;

    // Onboarding
    case '/onboard/next':
        require_login();
        require __DIR__ . '/src/controllers/onboard.php';
        onboard_next($pdo);
        break;

    case '/onboard/theme':
        require_login();
        require __DIR__ . '/src/controllers/onboard.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { onboard_theme_submit($pdo); }
        onboard_theme_show($pdo);
        break;

    case '/onboard/rules':
        require_login();
        require __DIR__ . '/src/controllers/onboard.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { onboard_rules_submit($pdo); }
        onboard_rules_form($pdo);
        break;
    case '/onboard/currencies':
        require_login();
        require __DIR__ . '/src/controllers/onboard.php';
        onboard_currencies_index($pdo);
        break;

    case '/onboard/currencies/add':
        require_login();
        require __DIR__ . '/src/controllers/onboard.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { onboard_currencies_add($pdo); }
        redirect('/onboard/currencies');
        break;

    case '/onboard/currencies/delete':
        require_login();
        require __DIR__ . '/src/controllers/onboard.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { onboard_currencies_delete($pdo); }
        redirect('/onboard/currencies');
        break;
    case '/onboard/income':
        require_login();
        require __DIR__ . '/src/controllers/onboard.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            onboard_income_add($pdo);
        } else {
            onboard_income($pdo);
        }
        break;

    case '/onboard/income/delete':
        require_login();
        require __DIR__ . '/src/controllers/onboard.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { onboard_income_delete($pdo); }
        redirect('/onboard/income');
        break;

    case '/onboard/categories':
        require_login();
        require __DIR__ . '/src/controllers/onboard.php';
        onboard_categories_index($pdo);
        break;

    case '/onboard/categories/add':
        require_login();
        require __DIR__ . '/src/controllers/onboard.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { onboard_categories_add($pdo); }
        redirect('/onboard/categories');
        break;


    case '/onboard/categories/delete':
        require_login();
        require __DIR__ . '/src/controllers/onboard.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { onboard_categories_delete($pdo); }
        redirect('/onboard/categories');
        break;
    case '/onboard/done':
        require_login();
        require __DIR__ . '/src/controllers/onboard.php';
        onboard_done($pdo);
        break;
    case '/tutorial':
        require_login();
        require __DIR__ . '/src/controllers/tutorial.php';
        tutorial_show($pdo);
        break;

    case '/tutorial/done':
        require_login();
        require __DIR__ . '/src/controllers/onboard.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            onboard_done_finish($pdo); // mark onboarding_complete (and optionally tutorial skip)
            redirect('/');
        } else {
            onboard_done_show($pdo);
        }
        break;


    // Auth
    case '/webauthn/options/register':
        require_login();
        if ($method !== 'POST') {
            header('Allow: POST');
            http_response_code(405);
            exit;
        }
        try {
            $options = webauthn_registration_options($pdo, uid());
        } catch (Throwable $e) {
            json_error(__('Could not start passkey registration.'));
        }
        json_response(['success' => true] + $options);
        break;

    case '/webauthn/register':
        require_login();
        if ($method !== 'POST') {
            header('Allow: POST');
            http_response_code(405);
            exit;
        }
        $input = read_json_input();
        $credential = $input['credential'] ?? null;
        if (!is_array($credential)) {
            json_error(__('Invalid registration payload.'));
        }
        $label = isset($input['label']) ? (string)$input['label'] : null;
        $result = webauthn_finish_registration($pdo, $credential, $label);
        if (empty($result['success'])) {
            $message = isset($result['error']) ? (string)$result['error'] : __('Registration failed.');
            json_error($message);
        }
        json_response([
            'success' => true,
            'id' => $result['id'] ?? null,
        ]);
        break;

    case '/webauthn/options/login':
        if ($method !== 'POST') {
            header('Allow: POST');
            http_response_code(405);
            exit;
        }
        try {
            $options = webauthn_login_options();
        } catch (Throwable $e) {
            json_error(__('Could not start passkey login.'));
        }
        json_response(['success' => true] + $options);
        break;

    case '/webauthn/login':
        if ($method !== 'POST') {
            header('Allow: POST');
            http_response_code(405);
            exit;
        }
        $input = read_json_input();
        $credential = $input['credential'] ?? null;
        if (!is_array($credential)) {
            json_error(__('Invalid login payload.'));
        }
        $result = webauthn_finish_login($pdo, $credential);
        if (empty($result['success']) || empty($result['user_id'])) {
            $message = isset($result['error']) ? (string)$result['error'] : __('Login failed.');
            json_error($message);
        }

        $userId = (int)$result['user_id'];
        $_SESSION['uid'] = $userId;
        if ($pdo instanceof PDO) {
            forget_remember_token($pdo, $userId);
        }

        try {
            $redirectTo = post_login_redirect_path($pdo, $userId);
        } catch (Throwable $e) {
            $redirectTo = '/';
        }

        json_response([
            'success' => true,
            'redirect' => $redirectTo,
        ]);
        break;

    case '/login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { handle_login($pdo); }
        view('auth/login');
        break;
    case '/logout':
        handle_logout();
        break;

    // Current Month
    case '/current-month':
        require_login();
        require __DIR__ . '/src/controllers/month.php';
        month_show($pdo); // current month
        break;

    // Transactions
    case '/transactions/add':
        require_login();
        require __DIR__ . '/src/controllers/transactions.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { tx_add($pdo); }
        redirect('/current-month');
        break;
    case '/transactions/edit':
        require_login();
        require __DIR__ . '/src/controllers/transactions.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { tx_edit($pdo); }
        redirect('/current-month');
        break;
    case '/transactions/delete':
        require_login();
        require __DIR__ . '/src/controllers/transactions.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { tx_delete($pdo); }
        redirect('/current-month');
        break;

    // Settings
    case '/settings':
        require_login();
        require __DIR__ . '/src/controllers/settings.php';
        settings_controller($pdo);
        break;

    case '/settings/privacy':
        require_login();
        require __DIR__ . '/src/controllers/settings_privacy.php';
        settings_privacy_show($pdo);
        break;

    case '/settings/privacy/export':
        require_login();
        require __DIR__ . '/src/controllers/settings_privacy.php';
        if ($method === 'POST') {
            settings_privacy_export($pdo);
        }
        redirect('/settings/privacy');
        break;

    case '/settings/privacy/delete':
        require_login();
        require __DIR__ . '/src/controllers/settings_privacy.php';
        if ($method === 'POST') {
            settings_privacy_delete($pdo);
        }
        redirect('/settings/privacy');
        break;

    // Profile
    case '/settings/profile':
        require_login();
        require __DIR__ . '/src/controllers/settings_profile.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            settings_profile_update($pdo);
        } else {
            settings_profile_show($pdo);
        }
        break;

    case '/settings/profile/password':
        require_login();
        require __DIR__ . '/src/controllers/settings_profile.php';
        if ($method === 'POST') {
            settings_profile_password_update($pdo);
        }
        redirect('/settings/profile');
        break;

    case '/settings/passkeys/delete':
        require_login();
        require __DIR__ . '/src/controllers/settings_profile.php';
        if ($method === 'POST') {
            settings_passkeys_delete($pdo);
        }
        redirect('/settings/profile');
        break;

    case '/settings/theme':
        require_login();
        require __DIR__ . '/src/controllers/settings_theme.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            settings_theme_update($pdo);
        } else {
            settings_theme_show($pdo);
        }
        break;

    // Currencies
    case '/settings/currencies':
        require_login();
        require __DIR__ . '/src/controllers/settings_currencies.php';
        currencies_index($pdo);
        break;
    case '/settings/currencies/add':
        require_login();
        require __DIR__ . '/src/controllers/settings_currencies.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { currency_add($pdo); }
        redirect('/settings/currencies');
        break;
    case '/settings/currencies/remove':
        require_login();
        require __DIR__ . '/src/controllers/settings_currencies.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { currency_remove($pdo); }
        redirect('/settings/currencies');
        break;
    case '/settings/currencies/main':
        require_login();
        require __DIR__ . '/src/controllers/settings_currencies.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { currency_set_main($pdo); }
        redirect('/settings/currencies');
        break;

    // Basic incomes
    case '/settings/basic-incomes':
        require_login();
        require __DIR__ . '/src/controllers/settings_incomes.php';
        incomes_index($pdo);
        break;
    case '/settings/basic-incomes/add':
        require_login();
        require __DIR__ . '/src/controllers/settings_incomes.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { incomes_add($pdo); }
        redirect('/settings/basic-incomes');
        break;
    case '/settings/basic-incomes/edit':
        require_login();
        require __DIR__ . '/src/controllers/settings_incomes.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { incomes_edit($pdo); }
        redirect('/settings/basic-incomes');
        break;
    case '/settings/basic-incomes/delete':
        require_login();
        require __DIR__ . '/src/controllers/settings_incomes.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { incomes_delete($pdo); }
        redirect('/settings/basic-incomes');
        break;

    // Categories
    case '/settings/categories':
        require_login();
        require __DIR__ . '/src/controllers/categories.php';
        categories_index($pdo);
        break;
    case '/settings/categories/add':
        require_login();
        require __DIR__ . '/src/controllers/categories.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { categories_add($pdo); }
        redirect('/settings/categories');
        break;
    case '/settings/categories/edit':
        require_login();
        require __DIR__ . '/src/controllers/categories.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { categories_edit($pdo); }
        redirect('/settings/categories');
        break;
    case '/settings/categories/delete':
        require_login();
        require __DIR__ . '/src/controllers/categories.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { categories_delete($pdo); }
        redirect('/settings/categories');
        break;

    // Cashflow Rules
    case '/settings/cashflow':
        require_login();
        require __DIR__ . '/src/controllers/cashflow.php';
        cashflow_index($pdo);
        break;
    case '/settings/cashflow/add':
        require_login();
        require __DIR__ . '/src/controllers/cashflow.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { cashflow_add($pdo); }
        redirect('/settings/cashflow');
        break;
    case '/settings/cashflow/edit':
        require_login();
        require __DIR__ . '/src/controllers/cashflow.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { cashflow_edit($pdo); }
        redirect('/settings/cashflow');
        break;
    case '/settings/cashflow/delete':
        require_login();
        require __DIR__ . '/src/controllers/cashflow.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { cashflow_delete($pdo); }
        redirect('/settings/cashflow');
        break;
    case '/settings/cashflow/assign':
        require_login();
        require __DIR__ . '/src/controllers/cashflow.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { cashflow_assign($pdo); }
        redirect('/settings/cashflow');
        break;

    // Goals
    case '/goals':
        require_login();
        require __DIR__ . '/src/controllers/goals.php';
        goals_index($pdo);
        break;
    case '/goals/add':
        require_login();
        require __DIR__ . '/src/controllers/goals.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { goals_add($pdo); }
        redirect('/goals');
        break;
    case '/goals/edit':
        require_login();
        require __DIR__ . '/src/controllers/goals.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { goals_edit($pdo); }
        redirect('/goals');
        break;
    case '/goals/delete':
        require_login();
        require __DIR__ . '/src/controllers/goals.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { goals_delete($pdo); }
        redirect('/goals');
        break;
    case '/goals/create-schedule':
        if ($method === 'POST') {
            require __DIR__ . '/src/controllers/goals.php';
            goals_create_schedule($pdo);
        }
        break;
    case '/goals/link-schedule':
        if ($method === 'POST') {
            require __DIR__ . '/src/controllers/goals.php';
            goals_link_schedule($pdo);
        }
        break;
    case '/goals/unlink-schedule':
        if ($method === 'POST') {
            require __DIR__ . '/src/controllers/goals.php';            
            goals_unlink_schedule($pdo);
        }
        break;
    case '/goals/tx/add':
        if ($method === 'POST') {
            require __DIR__ . '/src/controllers/goals.php';
            goals_tx_add($pdo);
        }
        break;
    case '/goals/tx/update':
        if ($method === 'POST') {
            require __DIR__ . '/src/controllers/goals.php';
            goals_tx_update($pdo);
        }
        break;
    case '/goals/tx/delete':
        require __DIR__ . '/src/controllers/goals.php';
        goals_tx_delete($pdo);
        break;

    // Loans
    case '/loans':
        require_login();
        require __DIR__ . '/src/controllers/loans.php';
        loans_index($pdo);
        break;
    case '/loans/add':
        require_login();
        require __DIR__ . '/src/controllers/loans.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { loans_add($pdo); }
        redirect('/loans');
        break;
    case '/loans/edit':
        require_login();
        require __DIR__ . '/src/controllers/loans.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { loans_edit($pdo); }
        redirect('/loans');
        break;
    case '/loans/delete':
        require_login();
        require __DIR__ . '/src/controllers/loans.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { loans_delete($pdo); }
        redirect('/loans');
        break;
    case '/loans/payment/add':
        require_login();
        require __DIR__ . '/src/controllers/loans.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { loan_payment_add($pdo); }
        redirect('/loans');
        break;
    case '/loans/payment/update':
        require_login();
        require __DIR__ . '/src/controllers/loans.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { loan_payment_update($pdo); }
        redirect('/loans');
        break;
    case '/loans/payment/delete':
        require_login();
        require __DIR__ . '/src/controllers/loans.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { loan_payment_delete($pdo); }
        redirect('/loans');
        break;
    case '/loals/unlink-schedule':
        require_login();
        require __DIR__ . '/src/controllers/goals.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { goals_unlink_schedule($pdo); }
        redirect('/loals');
        break;

    // Stocks
    case '/stocks':
        require_login();
        require __DIR__ . '/src/controllers/stocks.php';
        stocks_index($pdo);
        break;
    case '/stocks/transactions':
        require_login();
        require __DIR__ . '/src/controllers/stocks.php';
        stocks_transactions($pdo);
        break;
    case '/stocks/trade':
        require_login();
        require __DIR__ . '/src/controllers/stocks.php';
        if ($method === 'POST') {
            stocks_trade($pdo);
        } else {
            redirect('/stocks');
        }
        break;
    case '/stocks/import':
        require_login();
        require __DIR__ . '/src/controllers/stocks.php';
        if ($method === 'POST') {
            stocks_import($pdo);
        }
        redirect('/stocks');
        break;
    case '/stocks/cash':
        require_login();
        require __DIR__ . '/src/controllers/stocks.php';
        if ($method === 'POST') {
            stocks_cash_movement($pdo);
        }
        redirect('/stocks');
        break;
    case '/stocks/refresh':
        require_login();
        require __DIR__ . '/src/controllers/stocks.php';
        if ($method === 'POST') {
            $target = stocks_refresh_overview($pdo);
            if ($target !== null) {
                redirect($target);
            }
        } else {
            redirect('/stocks');
        }
        break;
    case '/stocks/clear':
        require_login();
        require __DIR__ . '/src/controllers/stocks.php';
        if ($method === 'POST') {
            stocks_clear_history($pdo);
        }
        redirect('/stocks');
        break;
    case '/stocks/trade/delete':
        require_login();
        require __DIR__ . '/src/controllers/stocks.php';
        if ($method === 'POST') {
            stocks_delete_trade($pdo);
        }
        redirect('/stocks');
        break;
    case '/api/stocks/live':
        require_login();
        require __DIR__ . '/src/controllers/stocks.php';
        stocks_live_api($pdo);
        break;

    // Scheduled
    case '/scheduled':
        require_login();
        require __DIR__ . '/src/controllers/scheduled.php';
        scheduled_index($pdo);
        break;
    case '/scheduled/add':
        require_login();
        require __DIR__ . '/src/controllers/scheduled.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { scheduled_add($pdo); }
        redirect('/scheduled');
        break;
    case '/scheduled/edit':
        require_login();
        require __DIR__ . '/src/controllers/scheduled.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { scheduled_edit($pdo); }
        redirect('/scheduled');
        break;
    case '/scheduled/delete':
        require_login();
        require __DIR__ . '/src/controllers/scheduled.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { scheduled_delete($pdo); }
        redirect('/scheduled');
        break;

    case '/investments':
        require_login();
        require __DIR__ . '/src/controllers/investments.php';
        investments_index($pdo);
        break;
    case '/investments/add':
        require_login();
        require __DIR__ . '/src/controllers/investments.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { investments_add($pdo); }
        redirect('/investments');
        break;
    case '/investments/update':
        require_login();
        require __DIR__ . '/src/controllers/investments.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { investments_update($pdo); }
        redirect('/investments');
        break;
    case '/investments/adjust':
        require_login();
        require __DIR__ . '/src/controllers/investments.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { investments_adjust($pdo); }
        redirect('/investments');
        break;
    case '/investments/scheduled/create':
        require_login();
        require __DIR__ . '/src/controllers/investments.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { investments_schedule_create($pdo); }
        redirect('/investments');
        break;
    case '/investments/delete':
        require_login();
        require __DIR__ . '/src/controllers/investments.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { investments_delete($pdo); }
        redirect('/investments');
        break;

    // Emergency Fund
    case '/emergency':
        require_login();
        require __DIR__ . '/src/controllers/emergency.php';
        emergency_index($pdo);
        break;
    case '/emergency/target':
        require_login();
        require __DIR__ . '/src/controllers/emergency.php';
        if ($_SERVER['REQUEST_METHOD']==='POST') emergency_set_target($pdo);
        redirect('/emergency');
        break;
    case '/emergency/add':
        require_login();
        require __DIR__ . '/src/controllers/emergency.php';
        if ($_SERVER['REQUEST_METHOD']==='POST') emergency_add($pdo);
        redirect('/emergency');
        break;
    case '/emergency/withdraw':
        require_login();
        require __DIR__ . '/src/controllers/emergency.php';
        if ($_SERVER['REQUEST_METHOD']==='POST') emergency_withdraw($pdo);
        redirect('/emergency');
        break;
    case '/emergency/tx/delete':
        require_login();
        require __DIR__ . '/src/controllers/emergency.php';
        if ($_SERVER['REQUEST_METHOD']==='POST') emergency_tx_delete($pdo);
        redirect('/emergency');
        break;

    // Years
    case '/years':
        require_login();
        require __DIR__ . '/src/controllers/years.php';
        years_index($pdo);
        break;
    case (preg_match('#^/years/([0-9]{4})$#', $path, $m) ? true : false):
        require_login();
        require __DIR__ . '/src/controllers/years.php';
        year_detail($pdo, (int)$m[1]);
        break;
    case (preg_match('#^/years/([0-9]{4})/([0-9]{1,2})$#', $path, $m) ? true : false):
        require_login();
        require __DIR__ . '/src/controllers/month.php';
        month_show($pdo, (int)$m[1], (int)$m[2]); // specific year/month
        break;

    /* Month‑scoped tx helpers so forms can redirect back to the month page */
    case '/months/tx/add':
        require_login();
        require __DIR__ . '/src/controllers/years.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { month_tx_add($pdo); }
        break;
    case '/months/tx/edit':
        require_login();
        require __DIR__ . '/src/controllers/years.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { month_tx_edit($pdo); }
        break;
    case '/months/tx/delete':
        require_login();
        require __DIR__ . '/src/controllers/years.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { month_tx_delete($pdo); }
        break;
    // Month tx: mobile lazy-load fragment (HTML cards)
    case '/months/tx/list':
        require_login();
        require __DIR__ . '/src/controllers/years.php';
        month_tx_list($pdo); // returns HTML fragment & exits
        break;

    case '/feedback':
        require_login();
        require __DIR__ . '/src/controllers/feedback.php';
        feedback_index($pdo);
        break;

    case '/more':
        require_login();
        require __DIR__ . '/src/controllers/more.php';
        more_show($pdo);
        break;

    case '/feedback/add':
        require_login();
        require __DIR__ . '/src/controllers/feedback.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { feedback_add($pdo); }
        redirect('/feedback');
        break;

    case '/feedback/status':
        require_login();
        require __DIR__ . '/src/controllers/feedback.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { feedback_update_status($pdo); }
        redirect('/feedback');
        break;

    case '/feedback/delete':
        require_login();
        require __DIR__ . '/src/controllers/feedback.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { feedback_delete($pdo); }
        redirect('/feedback');
        break;

    case '/privacy':
        view('legal/privacy');
        break;


    default:
        http_response_code(404);
        echo 'Not Found';
}