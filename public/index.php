<?php
session_start();
$config = require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/auth.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base = rtrim($config['app']['base_url'], '/');
if ($base && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base));
}
$path = rtrim($path, '/') ?: '/';

// Simple routing
switch ($path) {
    case '/':
        if (!is_logged_in()) { view('auth/login'); break; }
        view('dashboard');
        break;
    case '/register':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { handle_register($pdo); }
        view('auth/register');
        break;
    case '/login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { handle_login($pdo); }
        view('auth/login');
        break;
    case '/logout':
        handle_logout();
        break;

    case '/current-month':
        require __DIR__ . '/../src/controllers/current_month.php';
        current_month_controller($pdo);
        break;

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

    case '/settings':
        require_login();
        require __DIR__ . '/../src/controllers/settings.php';
        settings_controller($pdo);
        break;

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

    case '/emergency':
        require_login();
        require __DIR__ . '/../src/controllers/emergency.php';
        emergency_index($pdo);
        break;
    case '/emergency/set':
        require_login();
        require __DIR__ . '/../src/controllers/emergency.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { emergency_set($pdo); }
        redirect('/emergency');
        break;
    case '/emergency/tx/add':
        require_login();
        require __DIR__ . '/../src/controllers/emergency.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { emergency_tx_add($pdo); }
        redirect('/emergency');
        break;
    case '/emergency/tx/delete':
        require_login();
        require __DIR__ . '/../src/controllers/emergency.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { emergency_tx_delete($pdo); }
        redirect('/emergency');
        break;
    
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
        require __DIR__ . '/../src/controllers/years.php';
        month_detail($pdo, (int)$m[1], (int)$m[2]);
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

    default:
        http_response_code(404);
        echo 'Not Found';
}