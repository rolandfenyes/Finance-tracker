<?php
session_start();
$config = require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/fx.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base = rtrim($config['app']['base_url'], '/');
if ($base && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base));
}
$path = rtrim($path, '/') ?: '/';

// Normalize request info for the router
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');           // "GET", "POST", etc.
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// (optional) honor method override from forms like _method=DELETE
if ($method === 'POST' && !empty($_POST['_method'])) {
    $override = strtoupper(trim($_POST['_method']));
    if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
        $method = $override;
    }
}

// Simple routing
switch ($path) {
    case '/':
        if (!is_logged_in()) { view('auth/login'); break; }
        view('dashboard');
        break;

    // Registration
    case '/register':
        require __DIR__ . '/../src/controllers/auth.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { register_step1_submit($pdo); }
        register_step1_form(); // GET
        break;

    // Onboarding
    case '/onboard/next':
        require_login();
        require __DIR__ . '/../src/controllers/onboard.php';
        onboard_next($pdo);
        break;

    case '/onboard/rules':
        require_login();
        require __DIR__ . '/../src/controllers/onboard.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { onboard_rules_submit($pdo); }
        onboard_rules_form($pdo);
        break;
    case '/onboard/currencies':
        require_login();
        require __DIR__ . '/../src/controllers/onboard.php';
        onboard_currencies_index($pdo);
        break;

    case '/onboard/currencies/add':
        require_login();
        require __DIR__ . '/../src/controllers/onboard.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { onboard_currencies_add($pdo); }
        redirect('/onboard/currencies');
        break;

    case '/onboard/currencies/delete':
        require_login();
        require __DIR__ . '/../src/controllers/onboard.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { onboard_currencies_delete($pdo); }
        redirect('/onboard/currencies');
        break;
    case '/onboard/income':
        require_login();
        require __DIR__ . '/../src/controllers/onboard.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            onboard_income_add($pdo);
        } else {
            onboard_income($pdo);
        }
        break;

    case '/onboard/income/delete':
        require_login();
        require __DIR__ . '/../src/controllers/onboard.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { onboard_income_delete($pdo); }
        redirect('/onboard/income');
        break;

    case '/onboard/categories':
        require_login();
        require __DIR__ . '/../src/controllers/onboard.php';
        onboard_categories_index($pdo);
        break;

    case '/onboard/categories/add':
        require_login();
        require __DIR__ . '/../src/controllers/onboard.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { onboard_categories_add($pdo); }
        redirect('/onboard/categories');
        break;


    case '/onboard/categories/delete':
        require_login();
        require __DIR__ . '/../src/controllers/onboard.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { onboard_categories_delete($pdo); }
        redirect('/onboard/categories');
        break;
    case '/onboard/done':
        require_login();
        require __DIR__ . '/../src/controllers/onboard.php';
        onboard_done($pdo);
        break;
    case '/tutorial':
        require_login();
        require __DIR__ . '/../src/controllers/tutorial.php';
        tutorial_show($pdo);
        break;

    case '/tutorial/done':
        require_login();
        require __DIR__ . '/../src/controllers/tutorial.php';
        if ($_SERVER['REQUEST_METHOD']==='POST') { tutorial_done($pdo); }
        redirect('/dashboard');
        break;


    // Auth
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
        require __DIR__ . '/../src/controllers/month.php';
        month_show($pdo); // current month
        break;

    // Transactions
    case '/transactions/add':
        require_login();
        require __DIR__ . '/../src/controllers/transactions.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { tx_add($pdo); }
        redirect('/current-month');
        break;
    case '/transactions/edit':
        require_login();
        require __DIR__ . '/../src/controllers/transactions.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { tx_edit($pdo); }
        redirect('/current-month');
        break;
    case '/transactions/delete':
        require_login();
        require __DIR__ . '/../src/controllers/transactions.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { tx_delete($pdo); }
        redirect('/current-month');
        break;

    // Settings
    case '/settings':
        require_login();
        require __DIR__ . '/../src/controllers/settings.php';
        settings_controller($pdo);
        break;

    // Currencies
    case '/settings/currencies':
        require_login();
        require __DIR__ . '/../src/controllers/settings_currencies.php';
        currencies_index($pdo);
        break;
    case '/settings/currencies/add':
        require_login();
        require __DIR__ . '/../src/controllers/settings_currencies.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { currency_add($pdo); }
        redirect('/settings/currencies');
        break;
    case '/settings/currencies/remove':
        require_login();
        require __DIR__ . '/../src/controllers/settings_currencies.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { currency_remove($pdo); }
        redirect('/settings/currencies');
        break;
    case '/settings/currencies/main':
        require_login();
        require __DIR__ . '/../src/controllers/settings_currencies.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { currency_set_main($pdo); }
        redirect('/settings/currencies');
        break;

    // Basic incomes
    case '/settings/basic-incomes':
        require_login();
        require __DIR__ . '/../src/controllers/settings_incomes.php';
        incomes_index($pdo);
        break;
    case '/settings/basic-incomes/add':
        require_login();
        require __DIR__ . '/../src/controllers/settings_incomes.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { incomes_add($pdo); }
        redirect('/settings/basic-incomes');
        break;
    case '/settings/basic-incomes/edit':
        require_login();
        require __DIR__ . '/../src/controllers/settings_incomes.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { incomes_edit($pdo); }
        redirect('/settings/basic-incomes');
        break;
    case '/settings/basic-incomes/delete':
        require_login();
        require __DIR__ . '/../src/controllers/settings_incomes.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { incomes_delete($pdo); }
        redirect('/settings/basic-incomes');
        break;

    // Categories
    case '/settings/categories':
        require_login();
        require __DIR__ . '/../src/controllers/categories.php';
        categories_index($pdo);
        break;
    case '/settings/categories/add':
        require_login();
        require __DIR__ . '/../src/controllers/categories.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { categories_add($pdo); }
        redirect('/settings/categories');
        break;
    case '/settings/categories/edit':
        require_login();
        require __DIR__ . '/../src/controllers/categories.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { categories_edit($pdo); }
        redirect('/settings/categories');
        break;
    case '/settings/categories/delete':
        require_login();
        require __DIR__ . '/../src/controllers/categories.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { categories_delete($pdo); }
        redirect('/settings/categories');
        break;

    // Cashflow Rules
    case '/settings/cashflow':
        require_login();
        require __DIR__ . '/../src/controllers/cashflow.php';
        cashflow_index($pdo);
        break;
    case '/settings/cashflow/add':
        require_login();
        require __DIR__ . '/../src/controllers/cashflow.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { cashflow_add($pdo); }
        redirect('/settings/cashflow');
        break;
    case '/settings/cashflow/edit':
        require_login();
        require __DIR__ . '/../src/controllers/cashflow.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { cashflow_edit($pdo); }
        redirect('/settings/cashflow');
        break;
    case '/settings/cashflow/delete':
        require_login();
        require __DIR__ . '/../src/controllers/cashflow.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { cashflow_delete($pdo); }
        redirect('/settings/cashflow');
        break;
    case '/settings/cashflow/assign':
        require_login();
        require __DIR__ . '/../src/controllers/cashflow.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { cashflow_assign($pdo); }
        redirect('/settings/cashflow');
        break;

    // Goals
    case '/goals':
        require_login();
        require __DIR__ . '/../src/controllers/goals.php';
        goals_index($pdo);
        break;
    case '/goals/add':
        require_login();
        require __DIR__ . '/../src/controllers/goals.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { goals_add($pdo); }
        redirect('/goals');
        break;
    case '/goals/edit':
        require_login();
        require __DIR__ . '/../src/controllers/goals.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { goals_edit($pdo); }
        redirect('/goals');
        break;
    case '/goals/delete':
        require_login();
        require __DIR__ . '/../src/controllers/goals.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { goals_delete($pdo); }
        redirect('/goals');
        break;
    case '/goals/create-schedule':
        if ($method === 'POST') {
            require __DIR__ . '/../src/controllers/goals.php';
            goals_create_schedule($pdo);
        }
        break;
    case '/goals/link-schedule':
        if ($method === 'POST') {
            require __DIR__ . '/../src/controllers/goals.php';
            goals_link_schedule($pdo);
        }
        break;
    case '/goals/unlink-schedule':
        if ($method === 'POST') {
            require __DIR__ . '/../src/controllers/goals.php';            
            goals_unlink_schedule($pdo);
        }
        break;
    case '/goals/tx/add':
        if ($method === 'POST') {
            require __DIR__ . '/../src/controllers/goals.php';
            goals_tx_add($pdo);
        }
        break;
    case '/goals/tx/delete':
        require __DIR__ . '/../src/controllers/goals.php';
        goals_tx_delete($pdo);
        break;

    // Loans
    case '/loans':
        require_login();
        require __DIR__ . '/../src/controllers/loans.php';
        loans_index($pdo);
        break;
    case '/loans/add':
        require_login();
        require __DIR__ . '/../src/controllers/loans.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { loans_add($pdo); }
        redirect('/loans');
        break;
    case '/loans/edit':
        require_login();
        require __DIR__ . '/../src/controllers/loans.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { loans_edit($pdo); }
        redirect('/loans');
        break;
    case '/loans/delete':
        require_login();
        require __DIR__ . '/../src/controllers/loans.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { loans_delete($pdo); }
        redirect('/loans');
        break;
    case '/loans/payment/add':
        require_login();
        require __DIR__ . '/../src/controllers/loans.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { loan_payment_add($pdo); }
        redirect('/loans');
        break;
    case '/loals/unlink-schedule':
        require_login();
        require __DIR__ . '/../src/controllers/goals.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { goals_unlink_schedule($pdo); }
        redirect('/loals');
        break;

    // Stocks
    case '/stocks':
        require_login();
        require __DIR__ . '/../src/controllers/stocks.php';
        stocks_index($pdo);
        break;
    case '/stocks/buy':
        require_login();
        require __DIR__ . '/../src/controllers/stocks.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { trade_buy($pdo); }
        redirect('/stocks');
        break;
    case '/stocks/sell':
        require_login();
        require __DIR__ . '/../src/controllers/stocks.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { trade_sell($pdo); }
        redirect('/stocks');
        break;
    case '/stocks/trade/delete':
        require_login();
        require __DIR__ . '/../src/controllers/stocks.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { trade_delete($pdo); }
        redirect('/stocks');
        break;

    // Scheduled
    case '/scheduled':
        require_login();
        require __DIR__ . '/../src/controllers/scheduled.php';
        scheduled_index($pdo);
        break;
    case '/scheduled/add':
        require_login();
        require __DIR__ . '/../src/controllers/scheduled.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { scheduled_add($pdo); }
        redirect('/scheduled');
        break;
    case '/scheduled/edit':
        require_login();
        require __DIR__ . '/../src/controllers/scheduled.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { scheduled_edit($pdo); }
        redirect('/scheduled');
        break;
    case '/scheduled/delete':
        require_login();
        require __DIR__ . '/../src/controllers/scheduled.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { scheduled_delete($pdo); }
        redirect('/scheduled');
        break;

    // Emergency Fund
    case '/emergency':
        require_login();
        require __DIR__.'/../src/controllers/emergency.php';
        emergency_index($pdo);
        break;
    case '/emergency/target':
        require_login();
        require __DIR__.'/../src/controllers/emergency.php';
        if ($_SERVER['REQUEST_METHOD']==='POST') emergency_set_target($pdo);
        redirect('/emergency');
        break;
    case '/emergency/add':
        require_login();
        require __DIR__.'/../src/controllers/emergency.php';
        if ($_SERVER['REQUEST_METHOD']==='POST') emergency_add($pdo);
        redirect('/emergency');
        break;
    case '/emergency/withdraw':
        require_login();
        require __DIR__.'/../src/controllers/emergency.php';
        if ($_SERVER['REQUEST_METHOD']==='POST') emergency_withdraw($pdo);
        redirect('/emergency');
        break;
    case '/emergency/tx/delete':
        require_login();
        require __DIR__.'/../src/controllers/emergency.php';
        if ($_SERVER['REQUEST_METHOD']==='POST') emergency_tx_delete($pdo);
        redirect('/emergency');
        break;

    // Years
    case '/years':
        require_login();
        require __DIR__ . '/../src/controllers/years.php';
        years_index($pdo);
        break;
    case (preg_match('#^/years/([0-9]{4})$#', $path, $m) ? true : false):
        require_login();
        require __DIR__ . '/../src/controllers/years.php';
        year_detail($pdo, (int)$m[1]);
        break;
    case (preg_match('#^/years/([0-9]{4})/([0-9]{1,2})$#', $path, $m) ? true : false):
        require_login();
        require __DIR__ . '/../src/controllers/month.php';
        month_show($pdo, (int)$m[1], (int)$m[2]); // specific year/month
        break;

    /* Month‑scoped tx helpers so forms can redirect back to the month page */
    case '/months/tx/add':
        require_login();
        require __DIR__ . '/../src/controllers/years.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { month_tx_add($pdo); }
        break;
    case '/months/tx/edit':
        require_login();
        require __DIR__ . '/../src/controllers/years.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { month_tx_edit($pdo); }
        break;
    case '/months/tx/delete':
        require_login();
        require __DIR__ . '/../src/controllers/years.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { month_tx_delete($pdo); }
        break;
    // Month tx: mobile lazy-load fragment (HTML cards)
    case '/months/tx/list':
        require_login();
        require __DIR__ . '/../src/controllers/years.php';
        month_tx_list($pdo); // returns HTML fragment & exits
        break;

    

    default:
        http_response_code(404);
        echo 'Not Found';
}