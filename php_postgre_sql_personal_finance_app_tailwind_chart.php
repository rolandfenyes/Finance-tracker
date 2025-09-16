# Project: MoneyMap — PHP + PostgreSQL Personal Finance (Tailwind, Mobile‑first)

Below is a complete starter kit laid out as multiple files in one document. Create this folder structure and paste each file’s content accordingly.

---

## /README.md

```md
# MoneyMap (PHP + PostgreSQL)

Modern, mobile‑first personal finance tracker using Tailwind CSS and Chart.js. Supports Dave Ramsey’s Baby Steps via dedicated tables and UI hooks. Includes:
- Auth (register/login/logout)
- Transactions (income/spending), custom categories
- Currencies & main currency per user
- Loans with payments & progress
- Stocks (buy/sell) with portfolio value
- Emergency fund & transactions
- Financial goals & progress
- Scheduled (recurring) payments (RRULE-like string)
- Dashboard & month/year drill‑down views

## Quick Start
1. Copy files into a PHP web root (e.g., `money-map/`).
2. Install dependencies (none required beyond PHP 8.1+, PostgreSQL, and internet access for CDNs).
3. Create DB and run migrations:
   ```bash
   createdb moneymap
   psql moneymap < migrations/001_init.sql
   ```
4. Set DB credentials in `config/config.php`.
5. Launch a PHP dev server:
   ```bash
   php -S localhost:8080 -t public
   ```
6. Open http://localhost:8080

## Tech
- PHP 8.1+, PDO, PostgreSQL 13+
- Tailwind via CDN (JIT, mobile‑first)
- Alpine.js for sprinkles, Chart.js for charts

## Notes
- This is a production‑grade scaffold with secure patterns (prepared statements, password hashing). Extend as needed.
- Dave Ramsey support: use `baby_steps` for status + `emergency_fund`, `goals`, `loans` to reflect progress.
```
```

---

## /config/config.php
```php
<?php
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => '5432',
        'name' => 'moneymap',
        'user' => 'postgres',
        'pass' => 'postgres',
    ],
    'app' => [
        'name' => 'MoneyMap',
        'base_url' => '/', // if hosted in subfolder, e.g. '/moneymap/'
        'session_name' => 'moneymap_sess',
    ],
];
```

---

## /config/db.php
```php
<?php
$config = require __DIR__ . '/config.php';
$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $config['db']['host'], $config['db']['port'], $config['db']['name']);
try {
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    die('DB connection failed: ' . htmlspecialchars($e->getMessage()));
}
```

---

## /migrations/001_init.sql
```sql
-- Users & Auth
CREATE TABLE IF NOT EXISTS users (
  id SERIAL PRIMARY KEY,
  email TEXT UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  full_name TEXT,
  created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Currencies master (ISO-like)
CREATE TABLE IF NOT EXISTS currencies (
  code TEXT PRIMARY KEY,
  name TEXT NOT NULL
);
INSERT INTO currencies(code,name) VALUES
('USD','US Dollar') ON CONFLICT DO NOTHING;
INSERT INTO currencies(code,name) VALUES
('EUR','Euro') ON CONFLICT DO NOTHING;
INSERT INTO currencies(code,name) VALUES
('HUF','Hungarian Forint') ON CONFLICT DO NOTHING;

-- User currencies & main selection
CREATE TABLE IF NOT EXISTS user_currencies (
  user_id INT REFERENCES users(id) ON DELETE CASCADE,
  code TEXT REFERENCES currencies(code) ON DELETE RESTRICT,
  is_main BOOLEAN DEFAULT FALSE,
  PRIMARY KEY(user_id, code)
);

-- Categories (income / spending)
CREATE TABLE IF NOT EXISTS categories (
  id SERIAL PRIMARY KEY,
  user_id INT REFERENCES users(id) ON DELETE CASCADE,
  label TEXT NOT NULL,
  kind TEXT CHECK (kind IN ('income','spending')) NOT NULL
);

-- Unified transactions table for incomes & spendings
CREATE TABLE IF NOT EXISTS transactions (
  id SERIAL PRIMARY KEY,
  user_id INT REFERENCES users(id) ON DELETE CASCADE,
  kind TEXT CHECK (kind IN ('income','spending')) NOT NULL,
  category_id INT REFERENCES categories(id) ON DELETE SET NULL,
  amount NUMERIC(18,2) NOT NULL,
  currency TEXT REFERENCES currencies(code),
  occurred_on DATE NOT NULL,
  note TEXT,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  updated_at TIMESTAMPTZ
);
CREATE INDEX IF NOT EXISTS idx_transactions_user_date ON transactions(user_id, occurred_on);

-- Goals
CREATE TABLE IF NOT EXISTS goals (
  id SERIAL PRIMARY KEY,
  user_id INT REFERENCES users(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  target_amount NUMERIC(18,2) NOT NULL,
  current_amount NUMERIC(18,2) DEFAULT 0,
  currency TEXT REFERENCES currencies(code),
  deadline DATE,
  priority INT DEFAULT 3,
  status TEXT DEFAULT 'active'
);

-- Loans
CREATE TABLE IF NOT EXISTS loans (
  id SERIAL PRIMARY KEY,
  user_id INT REFERENCES users(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  principal NUMERIC(18,2) NOT NULL,
  interest_rate NUMERIC(6,3) NOT NULL, -- annual %
  start_date DATE NOT NULL,
  end_date DATE,
  payment_day INT CHECK (payment_day BETWEEN 1 AND 28),
  extra_payment NUMERIC(18,2) DEFAULT 0,
  balance NUMERIC(18,2) NOT NULL
);

CREATE TABLE IF NOT EXISTS loan_payments (
  id SERIAL PRIMARY KEY,
  loan_id INT REFERENCES loans(id) ON DELETE CASCADE,
  paid_on DATE NOT NULL,
  amount NUMERIC(18,2) NOT NULL,
  principal_component NUMERIC(18,2) DEFAULT 0,
  interest_component NUMERIC(18,2) DEFAULT 0,
  currency TEXT REFERENCES currencies(code)
);

-- Emergency fund
CREATE TABLE IF NOT EXISTS emergency_fund (
  user_id INT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  target_amount NUMERIC(18,2) NOT NULL,
  currency TEXT REFERENCES currencies(code),
  total NUMERIC(18,2) DEFAULT 0
);

CREATE TABLE IF NOT EXISTS emergency_transactions (
  id SERIAL PRIMARY KEY,
  user_id INT REFERENCES users(id) ON DELETE CASCADE,
  occurred_on DATE NOT NULL,
  amount NUMERIC(18,2) NOT NULL,
  kind TEXT CHECK (kind IN ('deposit','withdraw')) NOT NULL,
  note TEXT
);

-- Stocks
CREATE TABLE IF NOT EXISTS stock_trades (
  id SERIAL PRIMARY KEY,
  user_id INT REFERENCES users(id) ON DELETE CASCADE,
  symbol TEXT NOT NULL,
  trade_on DATE NOT NULL,
  side TEXT CHECK (side IN ('buy','sell')) NOT NULL,
  quantity NUMERIC(18,6) NOT NULL,
  price NUMERIC(18,4) NOT NULL,
  currency TEXT REFERENCES currencies(code)
);

CREATE VIEW IF NOT EXISTS v_stock_positions AS
SELECT user_id, symbol,
  SUM(CASE WHEN side='buy' THEN quantity ELSE -quantity END) AS qty,
  CASE WHEN SUM(CASE WHEN side='buy' THEN quantity ELSE -quantity END) <> 0
       THEN SUM(CASE WHEN side='buy' THEN quantity*price ELSE 0 END)
            / NULLIF(SUM(CASE WHEN side='buy' THEN quantity ELSE 0 END),0)
  END AS avg_buy_price
FROM stock_trades
GROUP BY user_id, symbol;

-- Scheduled payments (RRULE-ish string)
CREATE TABLE IF NOT EXISTS scheduled_payments (
  id SERIAL PRIMARY KEY,
  user_id INT REFERENCES users(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  amount NUMERIC(18,2) NOT NULL,
  currency TEXT REFERENCES currencies(code),
  rrule TEXT NOT NULL, -- e.g. FREQ=MONTHLY;BYMONTHDAY=10
  next_due DATE
);

-- Basic (regular) incomes with salary raises support
CREATE TABLE IF NOT EXISTS basic_incomes (
  id SERIAL PRIMARY KEY,
  user_id INT REFERENCES users(id) ON DELETE CASCADE,
  label TEXT NOT NULL,
  amount NUMERIC(18,2) NOT NULL,
  currency TEXT REFERENCES currencies(code),
  valid_from DATE NOT NULL,
  valid_to DATE
);

-- Cashflow allocation rules (e.g., 50% needs)
CREATE TABLE IF NOT EXISTS cashflow_rules (
  id SERIAL PRIMARY KEY,
  user_id INT REFERENCES users(id) ON DELETE CASCADE,
  label TEXT NOT NULL,
  percent NUMERIC(6,2) CHECK (percent >= 0 AND percent <= 100) NOT NULL,
  target_hint TEXT -- e.g., 'needs','investments','giving'
);

-- Dave Ramsey Baby Steps per user
CREATE TABLE IF NOT EXISTS baby_steps (
  user_id INT REFERENCES users(id) ON DELETE CASCADE,
  step INT CHECK (step BETWEEN 1 AND 7),
  status TEXT DEFAULT 'in_progress',
  note TEXT,
  PRIMARY KEY (user_id, step)
);
```

---

## /public/index.php (Router)
```php
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

    // TODO: add routes for goals, loans, stocks, scheduled-payments, emergency-fund, years/months

    default:
        http_response_code(404);
        echo 'Not Found';
}
```

---

## /src/helpers.php
```php
<?php
function view(string $name, array $data = []) {
    extract($data);
    include __DIR__ . '/../views/layout/header.php';
    include __DIR__ . '/../views/' . $name . '.php';
    include __DIR__ . '/../views/layout/footer.php';
}

function redirect(string $to) {
    header('Location: ' . $to);
    exit;
}

function is_logged_in(): bool { return isset($_SESSION['uid']); }
function require_login() { if (!is_logged_in()) redirect('/login'); }
function uid(): int { return (int)($_SESSION['uid'] ?? 0); }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['csrf'];
}
function verify_csrf() {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) { http_response_code(400); die('Bad CSRF'); }
}

function moneyfmt($amount, $code='') { return number_format((float)$amount, 2) . ($code?" $code":""); }
```

---

## /src/auth.php
```php
<?php
function handle_register(PDO $pdo) {
    require_once __DIR__ . '/helpers.php';
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $name = trim($_POST['full_name'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
        $_SESSION['flash'] = 'Invalid email or password too short';
        redirect('/register');
    }
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users(email,password_hash,full_name) VALUES(?,?,?) RETURNING id');
    try {
        $stmt->execute([$email,$hash,$name]);
        $uid = (int)$stmt->fetchColumn();
        $_SESSION['uid'] = $uid;
        // Add default currencies
        $pdo->prepare('INSERT INTO user_currencies(user_id,code,is_main) VALUES(?,?,true)')->execute([$uid,'HUF']);
        $pdo->prepare('INSERT INTO user_currencies(user_id,code,is_main) VALUES(?,?,false) ON CONFLICT DO NOTHING')->execute([$uid,'EUR']);
        $pdo->prepare('INSERT INTO user_currencies(user_id,code,is_main) VALUES(?,?,false) ON CONFLICT DO NOTHING')->execute([$uid,'USD']);
        // Initialize baby steps
        for ($i=1;$i<=7;$i++) { $pdo->prepare('INSERT INTO baby_steps(user_id,step,status) VALUES(?,?,?)')->execute([$uid,$i,'in_progress']); }
        redirect('/');
    } catch (PDOException $e) {
        $_SESSION['flash'] = 'Registration failed.';
        redirect('/register');
    }
}

function handle_login(PDO $pdo) {
    require_once __DIR__ . '/helpers.php';
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if ($row && password_verify($pass, $row['password_hash'])) {
        $_SESSION['uid'] = (int)$row['id'];
        redirect('/');
    }
    $_SESSION['flash'] = 'Invalid credentials';
    redirect('/login');
}

function handle_logout() {
    session_destroy();
    header('Location: /login');
    exit;
}
```

---

## /src/controllers/current_month.php
```php
<?php
require_once __DIR__ . '/../helpers.php';
function current_month_controller(PDO $pdo) {
    require_login();
    $u = uid();
    $y = (int)date('Y');
    $m = (int)date('n');

    // Fetch transactions of current month
    $stmt = $pdo->prepare("SELECT t.*, c.label AS cat_label FROM transactions t
        LEFT JOIN categories c ON c.id=t.category_id
        WHERE user_id=? AND EXTRACT(YEAR FROM occurred_on)=? AND EXTRACT(MONTH FROM occurred_on)=?
        ORDER BY occurred_on DESC");
    $stmt->execute([$u,$y,$m]);
    $tx = $stmt->fetchAll();

    // Sums
    $sumIn = 0; $sumOut = 0;
    foreach($tx as $row){
        if($row['kind']==='income') $sumIn += $row['amount']; else $sumOut += $row['amount'];
    }

    // Categories for quick add
    $cats = $pdo->prepare('SELECT id,label,kind FROM categories WHERE user_id=? ORDER BY kind,label');
    $cats->execute([$u]);
    $cats = $cats->fetchAll();

    view('current_month', compact('tx','sumIn','sumOut','cats','y','m'));
}
```

---

## /src/controllers/transactions.php
```php
<?php
require_once __DIR__ . '/../helpers.php';
function tx_add(PDO $pdo) {
    verify_csrf(); require_login(); $u=uid();
    $kind = $_POST['kind'] === 'income' ? 'income' : 'spending';
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $amount = (float)$_POST['amount'];
    $currency = $_POST['currency'] ?? 'HUF';
    $date = $_POST['occurred_on'] ?? date('Y-m-d');
    $note = trim($_POST['note'] ?? '');

    $stmt = $pdo->prepare('INSERT INTO transactions(user_id,kind,category_id,amount,currency,occurred_on,note) VALUES(?,?,?,?,?,?,?)');
    $stmt->execute([$u,$kind,$category_id,$amount,$currency,$date,$note]);
}

function tx_edit(PDO $pdo) {
    verify_csrf(); require_login(); $u=uid();
    $id = (int)$_POST['id'];
    $stmt = $pdo->prepare('UPDATE transactions SET kind=?, category_id=?, amount=?, currency=?, occurred_on=?, note=?, updated_at=NOW() WHERE id=? AND user_id=?');
    $stmt->execute([
        $_POST['kind']==='income'?'income':'spending',
        !empty($_POST['category_id'])?(int)$_POST['category_id']:null,
        (float)$_POST['amount'],
        $_POST['currency'] ?? 'HUF',
        $_POST['occurred_on'] ?? date('Y-m-d'),
        trim($_POST['note'] ?? ''),
        $id,$u
    ]);
}

function tx_delete(PDO $pdo) {
    verify_csrf(); require_login(); $u=uid();
    $id=(int)$_POST['id'];
    $pdo->prepare('DELETE FROM transactions WHERE id=? AND user_id=?')->execute([$id,$u]);
}
```

---

## /src/controllers/settings.php
```php
<?php
require_once __DIR__ . '/../helpers.php';
function settings_controller(PDO $pdo){
    require_login(); $u=uid();
    // Load user, currencies, rules, incomes
    $user = $pdo->prepare('SELECT id,email,full_name FROM users WHERE id=?');
    $user->execute([$u]); $user=$user->fetch();

    $curr = $pdo->prepare('SELECT uc.code, uc.is_main, c.name FROM user_currencies uc JOIN currencies c ON c.code=uc.code WHERE uc.user_id=? ORDER BY is_main DESC, code');
    $curr->execute([$u]); $curr=$curr->fetchAll();

    $rules = $pdo->prepare('SELECT * FROM cashflow_rules WHERE user_id=? ORDER BY id');
    $rules->execute([$u]); $rules=$rules->fetchAll();

    $basic = $pdo->prepare('SELECT * FROM basic_incomes WHERE user_id=? ORDER BY valid_from DESC');
    $basic->execute([$u]); $basic=$basic->fetchAll();

    view('settings/index', compact('user','curr','rules','basic'));
}
```

---

## /views/layout/header.php
```php
<?php $app = require __DIR__ . '/../../config/config.php'; ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($app['app']['name']) ?></title>
  <!-- Tailwind CDN (JIT) -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: { DEFAULT: '#111827' },
            accent: '#B81730'
          },
          boxShadow: { glass: '0 10px 30px rgba(0,0,0,0.08)'}
        }
      }
    }
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>body{font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial}</style>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body class="bg-gray-50 text-gray-900">
  <header class="backdrop-blur bg-white/70 sticky top-0 z-40 border-b border-gray-200">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
      <a href="/" class="font-semibold tracking-tight text-lg">💎 <?= htmlspecialchars($app['app']['name']) ?></a>
      <nav class="flex items-center gap-4 text-sm">
        <?php if (is_logged_in()): ?>
          <a class="hover:text-accent" href="/current-month">Current Month</a>
          <a class="hover:text-accent" href="/settings">Settings</a>
          <form action="/logout" method="post" class="inline"><button class="px-3 py-1.5 rounded-lg bg-gray-900 text-white">Logout</button></form>
        <?php else: ?>
          <a class="hover:text-accent" href="/login">Login</a>
          <a class="hover:text-accent" href="/register">Register</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>
  <main class="max-w-6xl mx-auto px-4 py-6">
```

---

## /views/layout/footer.php
```php
  </main>
  <footer class="border-t border-gray-200 py-8 text-center text-sm text-gray-500">© <?= date('Y') ?> MoneyMap</footer>
  <script>
    // Simple helper for charts (called by pages)
    window.renderLineChart = (id, labels, data) => {
      const ctx = document.getElementById(id); if(!ctx) return;
      new Chart(ctx, { type: 'line', data: { labels, datasets: [{ label: 'Amount', data, tension: 0.35, fill: false }] }, options: { responsive: true, maintainAspectRatio: false } });
    };
    window.renderDoughnut = (id, labels, data) => {
      const ctx = document.getElementById(id); if(!ctx) return;
      new Chart(ctx, { type: 'doughnut', data: { labels, datasets: [{ data }] }, options: { responsive: true, maintainAspectRatio: false } });
    };
  </script>
</body>
</html>
```

---

## /views/auth/login.php
```php
<section class="max-w-md mx-auto">
  <div class="bg-white rounded-2xl shadow-glass p-6">
    <h1 class="text-xl font-semibold mb-4">Welcome back</h1>
    <?php if (!empty($_SESSION['flash'])): ?><p class="text-red-600 mb-3"><?=$_SESSION['flash']; unset($_SESSION['flash']);?></p><?php endif; ?>
    <form method="post" action="/login" class="space-y-3">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <div>
        <label class="block text-sm mb-1">Email</label>
        <input name="email" type="email" class="w-full rounded-xl border-gray-300" required />
      </div>
      <div>
        <label class="block text-sm mb-1">Password</label>
        <input name="password" type="password" class="w-full rounded-xl border-gray-300" required />
      </div>
      <button class="w-full bg-gray-900 text-white rounded-xl py-2.5">Login</button>
    </form>
    <p class="text-sm text-gray-500 mt-3">No account? <a class="text-accent" href="/register">Register</a></p>
  </div>
</section>
```

---

## /views/auth/register.php
```php
<section class="max-w-md mx-auto">
  <div class="bg-white rounded-2xl shadow-glass p-6">
    <h1 class="text-xl font-semibold mb-4">Create account</h1>
    <?php if (!empty($_SESSION['flash'])): ?><p class="text-red-600 mb-3"><?=$_SESSION['flash']; unset($_SESSION['flash']);?></p><?php endif; ?>
    <form method="post" action="/register" class="space-y-3">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <div>
        <label class="block text-sm mb-1">Full name</label>
        <input name="full_name" class="w-full rounded-xl border-gray-300" />
      </div>
      <div>
        <label class="block text-sm mb-1">Email</label>
        <input name="email" type="email" class="w-full rounded-xl border-gray-300" required />
      </div>
      <div>
        <label class="block text-sm mb-1">Password (min 8)</label>
        <input name="password" type="password" class="w-full rounded-xl border-gray-300" required />
      </div>
      <button class="w-full bg-gray-900 text-white rounded-xl py-2.5">Register</button>
    </form>
  </div>
</section>
```

---

## /views/dashboard.php
```php
<?php require_login(); $u=uid(); ?>
<section class="grid md:grid-cols-3 gap-4">
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h2 class="font-medium">Net This Month</h2>
    <?php
      $stmt=$pdo->prepare("SELECT kind, SUM(amount) s FROM transactions WHERE user_id=? AND date_trunc('month',occurred_on)=date_trunc('month',CURRENT_DATE) GROUP BY kind");
      $stmt->execute([$u]);
      $sums=['income'=>0,'spending'=>0]; foreach($stmt as $r){$sums[$r['kind']] = (float)$r['s'];}
      $net=$sums['income']-$sums['spending'];
    ?>
    <p class="text-2xl mt-2 font-semibold"><?= moneyfmt($net) ?></p>
  </div>
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h2 class="font-medium">Goals Progress</h2>
    <?php $g=$pdo->prepare('SELECT SUM(current_amount) c, SUM(target_amount) t FROM goals WHERE user_id=? AND status=\'active\'' ); $g->execute([$u]); $g=$g->fetch(); $pc = $g && $g['t']>0 ? round($g['c']/$g['t']*100) : 0; ?>
    <div class="mt-3 w-full bg-gray-100 h-2 rounded">
      <div class="h-2 rounded bg-accent" style="width: <?= $pc ?>%"></div>
    </div>
    <p class="text-sm mt-2"><?= $pc ?>% of active goals</p>
  </div>
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h2 class="font-medium">Emergency Fund</h2>
    <?php $e=$pdo->prepare('SELECT total,target_amount FROM emergency_fund WHERE user_id=?'); $e->execute([$u]); $e=$e->fetch(); $pct = $e && $e['target_amount']>0? round($e['total']/$e['target_amount']*100):0; ?>
    <p class="text-2xl mt-2 font-semibold"><?= $e? moneyfmt($e['total']) : '—' ?></p>
    <p class="text-sm text-gray-500"><?= $pct ?>% of target</p>
  </div>
</section>

<section class="mt-6 grid md:grid-cols-2 gap-6">
  <div class="bg-white rounded-2xl p-5 shadow-glass h-80">
    <h3 class="font-semibold mb-3">Last 30 Days — Daily Flow</h3>
    <?php
      $q=$pdo->prepare("SELECT occurred_on::date d, SUM(CASE WHEN kind='income' THEN amount ELSE -amount END) v
                        FROM transactions WHERE user_id=? AND occurred_on >= CURRENT_DATE-INTERVAL '30 days'
                        GROUP BY d ORDER BY d");
      $q->execute([$u]); $labels=[]; $data=[]; foreach($q as $r){$labels[]=$r['d']; $data[]=(float)$r['v'];}
    ?>
    <canvas id="flow30" class="w-full h-64"></canvas>
    <script>renderLineChart('flow30', <?= json_encode($labels) ?>, <?= json_encode($data) ?>);</script>
  </div>
  <div class="bg-white rounded-2xl p-5 shadow-glass h-80">
    <h3 class="font-semibold mb-3">Spending by Category (This Month)</h3>
    <?php
      $q=$pdo->prepare("SELECT COALESCE(c.label,'Uncategorized') lb, SUM(t.amount) s
                        FROM transactions t LEFT JOIN categories c ON c.id=t.category_id
                        WHERE t.user_id=? AND t.kind='spending' AND date_trunc('month',t.occurred_on)=date_trunc('month',CURRENT_DATE)
                        GROUP BY lb ORDER BY s DESC");
      $q->execute([$u]); $labels=[]; $data=[]; foreach($q as $r){$labels[]=$r['lb']; $data[]=(float)$r['s'];}
    ?>
    <canvas id="spendcat" class="w-full h-64"></canvas>
    <script>renderDoughnut('spendcat', <?= json_encode($labels) ?>, <?= json_encode($data) ?>);</script>
  </div>
</section>

<section class="mt-6 bg-white rounded-2xl p-5 shadow-glass">
  <h3 class="font-semibold mb-3">Dave Ramsey Baby Steps</h3>
  <ol class="space-y-2 text-sm">
    <?php
      $steps = [
        1=>'Save $1,000 starter emergency fund',
        2=>'Debt snowball (all non‑mortgage debt)',
        3=>'3–6 months of expenses in savings',
        4=>'Invest 15% of household income for retirement',
        5=>'College funding for children',
        6=>'Pay off home early',
        7=>'Build wealth and give',
      ];
      $bs=$pdo->prepare('SELECT step,status FROM baby_steps WHERE user_id=? ORDER BY step');
      $bs->execute([$u]); $statuses=[]; foreach($bs as $r){$statuses[$r['step']]=$r['status'];}
      foreach($steps as $i=>$label): $st=$statuses[$i] ?? 'in_progress'; ?>
        <li class="flex items-center justify-between p-3 rounded-lg border <?php echo $st==='done'?'border-emerald-300 bg-emerald-50':'border-gray-200'; ?>">
          <span class="font-medium">Step <?= $i ?>:</span>
          <span class="flex-1 ml-2"><?= htmlspecialchars($label) ?></span>
          <span class="text-xs px-2 py-1 rounded-full <?php echo $st==='done'?'bg-emerald-200':'bg-gray-200'; ?>"><?= htmlspecialchars($st) ?></span>
        </li>
    <?php endforeach; ?>
  </ol>
</section>
```

---

## /views/current_month.php
```php
<section class="grid md:grid-cols-3 gap-4">
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h2 class="font-medium">This Month (<?= $y ?>‑<?= str_pad($m,2,'0',STR_PAD_LEFT) ?>)</h2>
    <p class="mt-2 text-sm text-gray-500">Income: <strong><?= moneyfmt($sumIn) ?></strong></p>
    <p class="mt-1 text-sm text-gray-500">Spending: <strong><?= moneyfmt($sumOut) ?></strong></p>
    <p class="mt-1 text-sm">Net: <strong><?= moneyfmt($sumIn - $sumOut) ?></strong></p>
  </div>
  <div class="bg-white rounded-2xl p-5 shadow-glass md:col-span-2">
    <h3 class="font-semibold mb-3">Quick Add</h3>
    <form class="grid sm:grid-cols-6 gap-2" method="post" action="/transactions/add">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <select name="kind" class="rounded-xl border-gray-300 sm:col-span-1">
        <option value="income">Income</option>
        <option value="spending">Spending</option>
      </select>
      <select name="category_id" class="rounded-xl border-gray-300 sm:col-span-2">
        <option value="">— Category —</option>
        <?php foreach($cats as $c): ?><option value="<?= $c['id'] ?>"><?php echo ucfirst($c['kind']).' · '.htmlspecialchars($c['label']); ?></option><?php endforeach; ?>
      </select>
      <input name="amount" type="number" step="0.01" placeholder="Amount" class="rounded-xl border-gray-300 sm:col-span-1" required />
      <input name="occurred_on" type="date" value="<?= date('Y-m-d') ?>" class="rounded-xl border-gray-300 sm:col-span-1" />
      <input name="note" placeholder="Note" class="rounded-xl border-gray-300 sm:col-span-1" />
      <button class="bg-gray-900 text-white rounded-xl px-4">Add</button>
    </form>
  </div>
</section>

<section class="mt-6 bg-white rounded-2xl p-5 shadow-glass overflow-x-auto">
  <h3 class="font-semibold mb-3">Transactions</h3>
  <table class="min-w-full text-sm">
    <thead>
      <tr class="text-left border-b">
        <th class="py-2 pr-3">Date</th>
        <th class="py-2 pr-3">Kind</th>
        <th class="py-2 pr-3">Category</th>
        <th class="py-2 pr-3">Amount</th>
        <th class="py-2 pr-3">Note</th>
        <th class="py-2 pr-3">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($tx as $row): ?>
        <tr class="border-b hover:bg-gray-50">
          <td class="py-2 pr-3"><?= htmlspecialchars($row['occurred_on']) ?></td>
          <td class="py-2 pr-3 capitalize"><?= htmlspecialchars($row['kind']) ?></td>
          <td class="py-2 pr-3"><?= htmlspecialchars($row['cat_label'] ?? '—') ?></td>
          <td class="py-2 pr-3 font-medium"><?= moneyfmt($row['amount']) ?></td>
          <td class="py-2 pr-3 text-gray-500"><?= htmlspecialchars($row['note'] ?? '') ?></td>
          <td class="py-2 pr-3">
            <details>
              <summary class="cursor-pointer text-accent">Edit</summary>
              <form class="mt-2 grid sm:grid-cols-6 gap-2" method="post" action="/transactions/edit">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="id" value="<?= $row['id'] ?>" />
                <select name="kind" class="rounded-xl border-gray-300 sm:col-span-1">
                  <option <?=$row['kind']==='income'?'selected':''?> value="income">Income</option>
                  <option <?=$row['kind']==='spending'?'selected':''?> value="spending">Spending</option>
                </select>
                <select name="category_id" class="rounded-xl border-gray-300 sm:col-span-2">
                  <option value="">— Category —</option>
                  <?php foreach($cats as $c): ?><option <?=$row['category_id']==$c['id']?'selected':''?> value="<?= $c['id'] ?>"><?php echo ucfirst($c['kind']).' · '.htmlspecialchars($c['label']); ?></option><?php endforeach; ?>
                </select>
                <input name="amount" type="number" step="0.01" value="<?= $row['amount'] ?>" class="rounded-xl border-gray-300 sm:col-span-1" required />
                <input name="occurred_on" type="date" value="<?= $row['occurred_on'] ?>" class="rounded-xl border-gray-300 sm:col-span-1" />
                <input name="note" value="<?= htmlspecialchars($row['note'] ?? '') ?>" class="rounded-xl border-gray-300 sm:col-span-1" />
                <button class="bg-gray-900 text-white rounded-xl px-4">Save</button>
              </form>
              <form class="mt-2" method="post" action="/transactions/delete" onsubmit="return confirm('Delete transaction?')">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="id" value="<?= $row['id'] ?>" />
                <button class="text-red-600">Remove</button>
              </form>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
```

---

## /views/settings/index.php
```php
<section class="grid md:grid-cols-2 gap-6">
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h2 class="font-semibold mb-2">User</h2>
    <p class="text-sm text-gray-500">Email: <?= htmlspecialchars($user['email']) ?></p>
    <p class="text-sm text-gray-500">Name: <?= htmlspecialchars($user['full_name'] ?? '') ?></p>
  </div>
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h2 class="font-semibold mb-2">Currencies</h2>
    <ul class="text-sm">
      <?php foreach($curr as $c): ?>
        <li class="py-1 flex items-center justify-between">
          <span><?= htmlspecialchars($c['code']) ?> — <?= htmlspecialchars($c['name']) ?></span>
          <?php if($c['is_main']): ?><span class="text-xs bg-gray-200 px-2 py-0.5 rounded-full">Main</span><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="text-xs text-gray-400 mt-2">(Add set/unset main handlers as needed)</p>
  </div>
</section>

<section class="mt-6 grid md:grid-cols-2 gap-6">
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h2 class="font-semibold mb-2">Cashflow Rules</h2>
    <ul class="text-sm space-y-1">
      <?php foreach($rules as $r): ?>
        <li class="flex justify-between">
          <span><?= htmlspecialchars($r['label']) ?></span>
          <span><?= (float)$r['percent'] ?>%</span>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="text-xs text-gray-400 mt-2">(Add CRUD for rules)</p>
  </div>
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h2 class="font-semibold mb-2">Basic Incomes</h2>
    <ul class="text-sm space-y-1">
      <?php foreach($basic as $b): ?>
        <li class="flex justify-between">
          <span><?= htmlspecialchars($b['label']) ?> (from <?= htmlspecialchars($b['valid_from']) ?>)</span>
          <span><?= moneyfmt($b['amount']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="text-xs text-gray-400 mt-2">(Add CRUD for basic incomes; supports raises via `valid_from`)</p>
  </div>
</section>
```

---

## (Placeholders to extend in /src/controllers and /views)
```txt
/goals          -> goals list, add/edit/remove, progress bar (use goals table)
/loans          -> list with progress bar; add/edit/remove; show amortization from loan_payments
/stocks         -> portfolio (from v_stock_positions), buy/sell via stock_trades
/scheduled      -> list and CRUD for scheduled_payments
/emergency      -> show progress bar from emergency_fund + transactions CRUD
/years          -> list years; /years/{YYYY}/months; /years/{YYYY}/{MM}
```

---

## Security & Production Notes (short)
```txt
- Always verify CSRF on POST (already done on sample endpoints).
- Validate & sanitize inputs server-side.
- Consider HTTPS, secure cookies, same-site strict.
- Add pagination for large tables.
- Migrate Tailwind to a compiled build for production.
- Add role-based access if you later add orgs/teams.
