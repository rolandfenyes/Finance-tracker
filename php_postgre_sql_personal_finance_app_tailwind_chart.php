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

---

## Router additions — /public/index.php
```php
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
```

---

## /src/controllers/goals.php
```php
<?php
require_once __DIR__ . '/../helpers.php';

function goals_index(PDO $pdo){ require_login(); $u=uid();
  $rows=$pdo->prepare('SELECT * FROM goals WHERE user_id=? ORDER BY priority, id');
  $rows->execute([$u]); $rows=$rows->fetchAll();
  $tot=$pdo->prepare("SELECT COALESCE(SUM(current_amount),0) c, COALESCE(SUM(target_amount),0) t FROM goals WHERE user_id=? AND status='active'");
  $tot->execute([$u]); $tot=$tot->fetch();
  view('goals/index', ['rows'=>$rows,'tot'=>$tot]);
}

function goals_add(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $stmt=$pdo->prepare('INSERT INTO goals(user_id,title,target_amount,current_amount,currency,deadline,priority,status) VALUES(?,?,?,?,?,?,?,?)');
  $stmt->execute([$u,trim($_POST['title']), (float)$_POST['target_amount'], (float)($_POST['current_amount']??0), $_POST['currency']??'HUF', $_POST['deadline']?:null, (int)($_POST['priority']??3), $_POST['status']??'active']);
}

function goals_edit(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $stmt=$pdo->prepare('UPDATE goals SET title=?, target_amount=?, current_amount=?, currency=?, deadline=?, priority=?, status=? WHERE id=? AND user_id=?');
  $stmt->execute([trim($_POST['title']), (float)$_POST['target_amount'], (float)($_POST['current_amount']??0), $_POST['currency']??'HUF', $_POST['deadline']?:null, (int)($_POST['priority']??3), $_POST['status']??'active', (int)$_POST['id'], $u]);
}

function goals_delete(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $pdo->prepare('DELETE FROM goals WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$u]);
}
```

---

## /views/goals/index.php
```php
<?php require_login(); $pct = ($tot['t']>0)? round($tot['c']/$tot['t']*100):0; ?>
<section class="bg-white rounded-2xl p-5 shadow-glass">
  <h1 class="text-xl font-semibold">Financial Goals</h1>
  <div class="mt-3 bg-gray-100 h-2 rounded"><div class="h-2 bg-accent rounded" style="width: <?=$pct?>%"></div></div>
  <p class="text-sm text-gray-500 mt-1">Overall progress: <?=$pct?>%</p>

  <details class="mt-4">
    <summary class="cursor-pointer text-accent">Add goal</summary>
    <form class="mt-3 grid sm:grid-cols-6 gap-2" method="post" action="/goals/add">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input name="title" class="rounded-xl border-gray-300 sm:col-span-2" placeholder="Title" required>
      <input name="target_amount" type="number" step="0.01" class="rounded-xl border-gray-300" placeholder="Target" required>
      <input name="current_amount" type="number" step="0.01" class="rounded-xl border-gray-300" placeholder="Current">
      <input name="currency" class="rounded-xl border-gray-300" value="HUF">
      <input name="deadline" type="date" class="rounded-xl border-gray-300">
      <select name="priority" class="rounded-xl border-gray-300"><option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option></select>
      <select name="status" class="rounded-xl border-gray-300"><option value="active" selected>active</option><option value="paused">paused</option><option value="done">done</option></select>
      <button class="bg-gray-900 text-white rounded-xl px-4">Save</button>
    </form>
  </details>
</section>

<section class="mt-6 bg-white rounded-2xl p-5 shadow-glass overflow-x-auto">
  <table class="min-w-full text-sm">
    <thead><tr class="text-left border-b"><th class="py-2 pr-3">Title</th><th class="py-2 pr-3">Progress</th><th class="py-2 pr-3">Deadline</th><th class="py-2 pr-3">Status</th><th class="py-2 pr-3">Actions</th></tr></thead>
    <tbody>
    <?php foreach($rows as $g): $p = $g['target_amount']>0? round($g['current_amount']/$g['target_amount']*100):0; ?>
      <tr class="border-b">
        <td class="py-2 pr-3 font-medium"><?= htmlspecialchars($g['title']) ?></td>
        <td class="py-2 pr-3"><div class="w-40 bg-gray-100 h-2 rounded inline-block align-middle"><div class="h-2 bg-accent rounded" style="width: <?=$p?>%"></div></div> <span class="text-xs ml-2"><?=$p?>%</span></td>
        <td class="py-2 pr-3"><?= htmlspecialchars($g['deadline']??'—') ?></td>
        <td class="py-2 pr-3 capitalize"><?= htmlspecialchars($g['status']) ?></td>
        <td class="py-2 pr-3">
          <details>
            <summary class="cursor-pointer text-accent">Edit</summary>
            <form class="mt-2 grid sm:grid-cols-6 gap-2" method="post" action="/goals/edit">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="id" value="<?= $g['id'] ?>" />
              <input name="title" value="<?= htmlspecialchars($g['title']) ?>" class="rounded-xl border-gray-300 sm:col-span-2">
              <input name="target_amount" type="number" step="0.01" value="<?= $g['target_amount'] ?>" class="rounded-xl border-gray-300">
              <input name="current_amount" type="number" step="0.01" value="<?= $g['current_amount'] ?>" class="rounded-xl border-gray-300">
              <input name="currency" value="<?= htmlspecialchars($g['currency']) ?>" class="rounded-xl border-gray-300">
              <input name="deadline" type="date" value="<?= $g['deadline'] ?>" class="rounded-xl border-gray-300">
              <select name="priority" class="rounded-xl border-gray-300">
                <?php for($i=1;$i<=5;$i++): ?><option <?=$g['priority']==$i?'selected':''?>><?=$i?></option><?php endfor; ?>
              </select>
              <select name="status" class="rounded-xl border-gray-300">
                <option <?=$g['status']==='active'?'selected':''?> value="active">active</option>
                <option <?=$g['status']==='paused'?'selected':''?> value="paused">paused</option>
                <option <?=$g['status']==='done'?'selected':''?> value="done">done</option>
              </select>
              <button class="bg-gray-900 text-white rounded-xl px-4">Save</button>
            </form>
            <form class="mt-2" method="post" action="/goals/delete" onsubmit="return confirm('Delete goal?')">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="id" value="<?= $g['id'] ?>" />
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

## Router additions — /public/index.php
```php
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
```

---

## /src/controllers/loans.php
```php
<?php
require_once __DIR__ . '/../helpers.php';

function loans_index(PDO $pdo){ require_login(); $u=uid();
  $loans=$pdo->prepare('SELECT * FROM loans WHERE user_id=? ORDER BY id');
  $loans->execute([$u]); $loans=$loans->fetchAll();
  view('loans/index', compact('loans'));
}

function loans_add(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $stmt=$pdo->prepare('INSERT INTO loans(user_id,name,principal,interest_rate,start_date,end_date,payment_day,extra_payment,balance) VALUES(?,?,?,?,?,?,?,?,?)');
  $stmt->execute([$u, trim($_POST['name']), (float)$_POST['principal'], (float)$_POST['interest_rate'], $_POST['start_date'], $_POST['end_date']?:null, (int)$_POST['payment_day'], (float)($_POST['extra_payment']??0), (float)$_POST['principal']]);
}

function loans_edit(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $stmt=$pdo->prepare('UPDATE loans SET name=?, principal=?, interest_rate=?, start_date=?, end_date=?, payment_day=?, extra_payment=?, balance=? WHERE id=? AND user_id=?');
  $stmt->execute([trim($_POST['name']), (float)$_POST['principal'], (float)$_POST['interest_rate'], $_POST['start_date'], $_POST['end_date']?:null, (int)$_POST['payment_day'], (float)($_POST['extra_payment']??0), (float)$_POST['balance'], (int)$_POST['id'], $u]);
}

function loans_delete(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $pdo->prepare('DELETE FROM loans WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$u]);
}

function loan_payment_add(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $loanId=(int)$_POST['loan_id'];
  $amount=(float)$_POST['amount']; $interest=(float)($_POST['interest_component']??0); $principal=max(0,$amount-$interest);
  $pdo->prepare('INSERT INTO loan_payments(loan_id,paid_on,amount,principal_component,interest_component,currency) VALUES(?,?,?,?,?,?)')
      ->execute([$loanId, $_POST['paid_on'], $amount, $principal, $interest, $_POST['currency']??'HUF']);
  $pdo->prepare('UPDATE loans SET balance = GREATEST(0,balance-?) WHERE id=? AND user_id=?')->execute([$principal,$loanId,$u]);
}
```

---

## /views/loans/index.php
```php
<section class="bg-white rounded-2xl p-5 shadow-glass">
  <h1 class="text-xl font-semibold">Loans</h1>
  <details class="mt-4">
    <summary class="cursor-pointer text-accent">Add loan</summary>
    <form class="mt-3 grid sm:grid-cols-6 gap-2" method="post" action="/loans/add">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input name="name" class="rounded-xl border-gray-300 sm:col-span-2" placeholder="Name" required>
      <input name="principal" type="number" step="0.01" class="rounded-xl border-gray-300" placeholder="Principal" required>
      <input name="interest_rate" type="number" step="0.001" class="rounded-xl border-gray-300" placeholder="APR %" required>
      <input name="start_date" type="date" class="rounded-xl border-gray-300" required>
      <input name="end_date" type="date" class="rounded-xl border-gray-300">
      <input name="payment_day" type="number" min="1" max="28" class="rounded-xl border-gray-300" placeholder="Pay day">
      <input name="extra_payment" type="number" step="0.01" class="rounded-xl border-gray-300" placeholder="Extra">
      <button class="bg-gray-900 text-white rounded-xl px-4">Save</button>
    </form>
  </details>
</section>

<section class="mt-6 bg-white rounded-2xl p-5 shadow-glass overflow-x-auto">
  <table class="min-w-full text-sm">
    <thead><tr class="text-left border-b"><th class="py-2 pr-3">Loan</th><th class="py-2 pr-3">APR</th><th class="py-2 pr-3">Balance</th><th class="py-2 pr-3">Period</th><th class="py-2 pr-3">Actions</th></tr></thead>
    <tbody>
    <?php foreach($loans as $l): ?>
      <tr class="border-b">
        <td class="py-2 pr-3 font-medium"><?= htmlspecialchars($l['name']) ?></td>
        <td class="py-2 pr-3"><?= (float)$l['interest_rate'] ?>%</td>
        <td class="py-2 pr-3 font-medium"><?= moneyfmt($l['balance']) ?></td>
        <td class="py-2 pr-3"><?= htmlspecialchars($l['start_date']) ?> → <?= htmlspecialchars($l['end_date']??'—') ?></td>
        <td class="py-2 pr-3">
          <details>
            <summary class="cursor-pointer text-accent">Edit / Pay</summary>
            <form class="mt-2 grid sm:grid-cols-6 gap-2" method="post" action="/loans/edit">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="id" value="<?= $l['id'] ?>" />
              <input name="name" value="<?= htmlspecialchars($l['name']) ?>" class="rounded-xl border-gray-300 sm:col-span-2">
              <input name="principal" type="number" step="0.01" value="<?= $l['principal'] ?>" class="rounded-xl border-gray-300">
              <input name="interest_rate" type="number" step="0.001" value="<?= $l['interest_rate'] ?>" class="rounded-xl border-gray-300">
              <input name="start_date" type="date" value="<?= $l['start_date'] ?>" class="rounded-xl border-gray-300">
              <input name="end_date" type="date" value="<?= $l['end_date'] ?>" class="rounded-xl border-gray-300">
              <input name="payment_day" type="number" value="<?= $l['payment_day'] ?>" class="rounded-xl border-gray-300">
              <input name="extra_payment" type="number" step="0.01" value="<?= $l['extra_payment'] ?>" class="rounded-xl border-gray-300">
              <input name="balance" type="number" step="0.01" value="<?= $l['balance'] ?>" class="rounded-xl border-gray-300">
              <button class="bg-gray-900 text-white rounded-xl px-4">Save</button>
            </form>
            <form class="mt-2 grid sm:grid-cols-5 gap-2" method="post" action="/loans/payment/add">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="loan_id" value="<?= $l['id'] ?>" />
              <input name="paid_on" type="date" value="<?= date('Y-m-d') ?>" class="rounded-xl border-gray-300">
              <input name="amount" type="number" step="0.01" placeholder="Payment amount" class="rounded-xl border-gray-300" required>
              <input name="interest_component" type="number" step="0.01" placeholder="Interest" class="rounded-xl border-gray-300">
              <input name="currency" value="HUF" class="rounded-xl border-gray-300">
              <button class="bg-emerald-600 text-white rounded-xl px-4">Record Payment</button>
            </form>
            <form class="mt-2" method="post" action="/loans/delete" onsubmit="return confirm('Delete loan?')">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="id" value="<?= $l['id'] ?>" />
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

## Router additions — /public/index.php
```php
    case '/stocks':
        require_login();
        require __DIR__ . '/../src/controllers/stocks.php';
        stocks_index($pdo);
        break;
    case '/stocks/buy':
        require_login();
        require __DIR__ . '/../src/controllers/stocks.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { stocks_buy($pdo); }
        redirect('/stocks');
        break;
    case '/stocks/sell':
        require_login();
        require __DIR__ . '/../src/controllers/stocks.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { stocks_sell($pdo); }
        redirect('/stocks');
        break;
```

---

## /src/controllers/stocks.php
```php
<?php
require_once __DIR__ . '/../helpers.php';

function stocks_index(PDO $pdo){ require_login(); $u=uid();
  // Portfolio value
  $stmt=$pdo->prepare("SELECT symbol, qty, avg_buy_price FROM v_stock_positions WHERE user_id=?");
  $stmt->execute([$u]);
  $positions=$stmt->fetchAll();
  view('stocks/index', compact('positions'));
}

function stocks_buy(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $stmt=$pdo->prepare('INSERT INTO stock_trades(user_id,symbol,trade_on,side,quantity,price,currency) VALUES(?,?,?,?,?,?,?)');
  $stmt->execute([$u, strtoupper(trim($_POST['symbol'])), $_POST['trade_on']?:date('Y-m-d'), 'buy', (float)$_POST['quantity'], (float)$_POST['price'], $_POST['currency']??'USD']);
}

function stocks_sell(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $stmt=$pdo->prepare('INSERT INTO stock_trades(user_id,symbol,trade_on,side,quantity,price,currency) VALUES(?,?,?,?,?,?,?)');
  $stmt->execute([$u, strtoupper(trim($_POST['symbol'])), $_POST['trade_on']?:date('Y-m-d'), 'sell', (float)$_POST['quantity'], (float)$_POST['price'], $_POST['currency']??'USD']);
}
```

---

## /views/stocks/index.php
```php
<section class="bg-white rounded-2xl p-5 shadow-glass">
  <h1 class="text-xl font-semibold">Stocks Portfolio</h1>
  <details class="mt-4">
    <summary class="cursor-pointer text-accent">Buy Stock</summary>
    <form class="mt-3 grid sm:grid-cols-6 gap-2" method="post" action="/stocks/buy">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input name="symbol" class="rounded-xl border-gray-300 sm:col-span-2" placeholder="Ticker" required>
      <input name="quantity" type="number" step="0.0001" class="rounded-xl border-gray-300" placeholder="Quantity" required>
      <input name="price" type="number" step="0.01" class="rounded-xl border-gray-300" placeholder="Price" required>
      <input name="trade_on" type="date" value="<?= date('Y-m-d') ?>" class="rounded-xl border-gray-300">
      <input name="currency" value="USD" class="rounded-xl border-gray-300">
      <button class="bg-gray-900 text-white rounded-xl px-4">Buy</button>
    </form>
  </details>
  <details class="mt-4">
    <summary class="cursor-pointer text-accent">Sell Stock</summary>
    <form class="mt-3 grid sm:grid-cols-6 gap-2" method="post" action="/stocks/sell">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input name="symbol" class="rounded-xl border-gray-300 sm:col-span-2" placeholder="Ticker" required>
      <input name="quantity" type="number" step="0.0001" class="rounded-xl border-gray-300" placeholder="Quantity" required>
      <input name="price" type="number" step="0.01" class="rounded-xl border-gray-300" placeholder="Price" required>
      <input name="trade_on" type="date" value="<?= date('Y-m-d') ?>" class="rounded-xl border-gray-300">
      <input name="currency" value="USD" class="rounded-xl border-gray-300">
      <button class="bg-red-600 text-white rounded-xl px-4">Sell</button>
    </form>
  </details>
</section>

<section class="mt-6 bg-white rounded-2xl p-5 shadow-glass overflow-x-auto">
  <table class="min-w-full text-sm">
    <thead><tr class="text-left border-b"><th class="py-2 pr-3">Symbol</th><th class="py-2 pr-3">Quantity</th><th class="py-2 pr-3">Avg Buy Price</th></tr></thead>
    <tbody>
    <?php foreach($positions as $p): ?>
      <tr class="border-b">
        <td class="py-2 pr-3 font-medium"><?= htmlspecialchars($p['symbol']) ?></td>
        <td class="py-2 pr-3"><?= $p['qty'] ?></td>
        <td class="py-2 pr-3"><?= moneyfmt($p['avg_buy_price']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
```

---

## Router additions — /public/index.php
```php
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
```

---

## /src/controllers/stocks.php
```php
<?php
require_once __DIR__ . '/../helpers.php';

function stocks_index(PDO $pdo){ require_login(); $u=uid();
  // Open positions & cost basis (from view)
  $pos=$pdo->prepare('SELECT symbol, qty, avg_buy_price FROM v_stock_positions WHERE user_id=? ORDER BY symbol');
  $pos->execute([$u]); $positions=$pos->fetchAll();

  // Portfolio cost basis value (qty * avg_buy_price for qty>0)
  $portfolio_value = 0.0; foreach($positions as $p){ if((float)$p['qty']>0){ $portfolio_value += (float)$p['qty'] * (float)$p['avg_buy_price']; } }

  // Recent trades
  $t=$pdo->prepare('SELECT * FROM stock_trades WHERE user_id=? ORDER BY trade_on DESC, id DESC LIMIT 100');
  $t->execute([$u]); $trades=$t->fetchAll();

  view('stocks/index', compact('positions','portfolio_value','trades'));
}

function trade_buy(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $stmt=$pdo->prepare('INSERT INTO stock_trades(user_id,symbol,trade_on,side,quantity,price,currency) VALUES(?,?,?,?,?,?,?)');
  $stmt->execute([$u, strtoupper(trim($_POST['symbol'])), $_POST['trade_on'] ?: date('Y-m-d'), 'buy', (float)$_POST['quantity'], (float)$_POST['price'], $_POST['currency'] ?: 'USD']);
}

function trade_sell(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  // Optional naive check: prevent selling more than held (best-effort; DB view handles net qty anyway)
  $symbol = strtoupper(trim($_POST['symbol'])); $qty=(float)$_POST['quantity'];
  $q=$pdo->prepare('SELECT qty FROM v_stock_positions WHERE user_id=? AND symbol=?'); $q->execute([$u,$symbol]); $held=(float)($q->fetchColumn() ?: 0);
  if ($qty > $held) { $qty = $held; }
  if ($qty <= 0) { return; }
  $pdo->prepare('INSERT INTO stock_trades(user_id,symbol,trade_on,side,quantity,price,currency) VALUES(?,?,?,?,?,?,?)')
      ->execute([$u, $symbol, $_POST['trade_on'] ?: date('Y-m-d'), 'sell', $qty, (float)$_POST['price'], $_POST['currency'] ?: 'USD']);
}

function trade_delete(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $pdo->prepare('DELETE FROM stock_trades WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$u]);
}
```

---

## /views/stocks/index.php
```php
<section class="grid md:grid-cols-3 gap-4">
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h2 class="font-medium">Portfolio (cost basis)</h2>
    <p class="text-2xl mt-2 font-semibold"><?= moneyfmt($portfolio_value) ?></p>
    <p class="text-xs text-gray-500">Sum of qty × avg buy price for open positions.</p>
  </div>
  <div class="bg-white rounded-2xl p-5 shadow-glass md:col-span-2">
    <h3 class="font-semibold mb-3">Trade — Buy</h3>
    <form class="grid sm:grid-cols-6 gap-2" method="post" action="/stocks/buy">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input name="symbol" class="rounded-xl border-gray-300 sm:col-span-1" placeholder="AAPL" required>
      <input name="quantity" type="number" step="0.000001" class="rounded-xl border-gray-300 sm:col-span-1" placeholder="Qty" required>
      <input name="price" type="number" step="0.0001" class="rounded-xl border-gray-300 sm:col-span-1" placeholder="Price" required>
      <input name="currency" class="rounded-xl border-gray-300 sm:col-span-1" value="USD">
      <input name="trade_on" type="date" value="<?= date('Y-m-d') ?>" class="rounded-xl border-gray-300 sm:col-span-1">
      <button class="bg-gray-900 text-white rounded-xl px-4">Buy</button>
    </form>

    <h3 class="font-semibold mt-6 mb-3">Trade — Sell</h3>
    <form class="grid sm:grid-cols-6 gap-2" method="post" action="/stocks/sell">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input name="symbol" class="rounded-xl border-gray-300 sm:col-span-1" placeholder="AAPL" required>
      <input name="quantity" type="number" step="0.000001" class="rounded-xl border-gray-300 sm:col-span-1" placeholder="Qty" required>
      <input name="price" type="number" step="0.0001" class="rounded-xl border-gray-300 sm:col-span-1" placeholder="Price" required>
      <input name="currency" class="rounded-xl border-gray-300 sm:col-span-1" value="USD">
      <input name="trade_on" type="date" value="<?= date('Y-m-d') ?>" class="rounded-xl border-gray-300 sm:col-span-1">
      <button class="bg-accent text-white rounded-xl px-4">Sell</button>
    </form>
  </div>
</section>

<section class="mt-6 grid md:grid-cols-2 gap-6">
  <div class="bg-white rounded-2xl p-5 shadow-glass overflow-x-auto">
    <h3 class="font-semibold mb-3">Open Positions</h3>
    <table class="min-w-full text-sm">
      <thead><tr class="text-left border-b"><th class="py-2 pr-3">Symbol</th><th class="py-2 pr-3">Qty</th><th class="py-2 pr-3">Avg Buy</th><th class="py-2 pr-3">Cost</th></tr></thead>
      <tbody>
        <?php foreach($positions as $p): if((float)$p['qty']<=0) continue; $cost=(float)$p['qty']*(float)$p['avg_buy_price']; ?>
          <tr class="border-b">
            <td class="py-2 pr-3 font-medium"><?= htmlspecialchars($p['symbol']) ?></td>
            <td class="py-2 pr-3"><?= (float)$p['qty'] ?></td>
            <td class="py-2 pr-3"><?= moneyfmt($p['avg_buy_price']) ?></td>
            <td class="py-2 pr-3 font-medium"><?= moneyfmt($cost) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="bg-white rounded-2xl p-5 shadow-glass overflow-x-auto">
    <h3 class="font-semibold mb-3">Recent Trades</h3>
    <table class="min-w-full text-sm">
      <thead><tr class="text-left border-b"><th class="py-2 pr-3">Date</th><th class="py-2 pr-3">Side</th><th class="py-2 pr-3">Symbol</th><th class="py-2 pr-3">Qty</th><th class="py-2 pr-3">Price</th><th class="py-2 pr-3">Currency</th><th class="py-2 pr-3">Actions</th></tr></thead>
      <tbody>
        <?php foreach($trades as $t): ?>
          <tr class="border-b">
            <td class="py-2 pr-3"><?= htmlspecialchars($t['trade_on']) ?></td>
            <td class="py-2 pr-3 capitalize"><?= htmlspecialchars($t['side']) ?></td>
            <td class="py-2 pr-3 font-medium"><?= htmlspecialchars($t['symbol']) ?></td>
            <td class="py-2 pr-3"><?= (float)$t['quantity'] ?></td>
            <td class="py-2 pr-3"><?= moneyfmt($t['price']) ?></td>
            <td class="py-2 pr-3"><?= htmlspecialchars($t['currency']) ?></td>
            <td class="py-2 pr-3">
              <form method="post" action="/stocks/trade/delete" onsubmit="return confirm('Delete trade?')">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="id" value="<?= $t['id'] ?>" />
                <button class="text-red-600">Remove</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
```


---

## Router additions — /public/index.php
```php
    case '/scheduled-payments':
        require_login();
        require __DIR__ . '/../src/controllers/scheduled.php';
        scheduled_index($pdo);
        break;
    case '/scheduled-payments/add':
        require_login();
        require __DIR__ . '/../src/controllers/scheduled.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { scheduled_add($pdo); }
        redirect('/scheduled-payments');
        break;
    case '/scheduled-payments/edit':
        require_login();
        require __DIR__ . '/../src/controllers/scheduled.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { scheduled_edit($pdo); }
        redirect('/scheduled-payments');
        break;
    case '/scheduled-payments/delete':
        require_login();
        require __DIR__ . '/../src/controllers/scheduled.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { scheduled_delete($pdo); }
        redirect('/scheduled-payments');
        break;
```

---

## /src/controllers/scheduled.php
```php
<?php
require_once __DIR__ . '/../helpers.php';

function scheduled_index(PDO $pdo){ require_login(); $u=uid();
  $rows=$pdo->prepare('SELECT * FROM scheduled_payments WHERE user_id=? ORDER BY next_due');
  $rows->execute([$u]); $rows=$rows->fetchAll();
  view('scheduled/index', compact('rows'));
}

function scheduled_add(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $pdo->prepare('INSERT INTO scheduled_payments(user_id,title,amount,currency,rrule,next_due) VALUES(?,?,?,?,?,?)')
      ->execute([$u, trim($_POST['title']), (float)$_POST['amount'], $_POST['currency']??'HUF', $_POST['rrule'], $_POST['next_due']]);
}

function scheduled_edit(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $pdo->prepare('UPDATE scheduled_payments SET title=?, amount=?, currency=?, rrule=?, next_due=? WHERE id=? AND user_id=?')
      ->execute([trim($_POST['title']), (float)$_POST['amount'], $_POST['currency']??'HUF', $_POST['rrule'], $_POST['next_due'], (int)$_POST['id'], $u]);
}

function scheduled_delete(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $pdo->prepare('DELETE FROM scheduled_payments WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$u]);
}
```

---

## /views/scheduled/index.php
```php
<section class="bg-white rounded-2xl p-5 shadow-glass">
  <h1 class="text-xl font-semibold">Scheduled Payments</h1>
  <details class="mt-4">
    <summary class="cursor-pointer text-accent">Add scheduled payment</summary>
    <form class="mt-3 grid sm:grid-cols-6 gap-2" method="post" action="/scheduled-payments/add">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input name="title" class="rounded-xl border-gray-300 sm:col-span-2" placeholder="Title" required>
      <input name="amount" type="number" step="0.01" class="rounded-xl border-gray-300" placeholder="Amount" required>
      <input name="currency" class="rounded-xl border-gray-300" value="HUF">
      <input name="rrule" class="rounded-xl border-gray-300 sm:col-span-2" placeholder="FREQ=MONTHLY;BYMONTHDAY=10" required>
      <input name="next_due" type="date" class="rounded-xl border-gray-300" required>
      <button class="bg-gray-900 text-white rounded-xl px-4">Save</button>
    </form>
  </details>
</section>

<section class="mt-6 bg-white rounded-2xl p-5 shadow-glass overflow-x-auto">
  <table class="min-w-full text-sm">
    <thead><tr class="text-left border-b"><th class="py-2 pr-3">Title</th><th class="py-2 pr-3">Amount</th><th class="py-2 pr-3">Currency</th><th class="py-2 pr-3">Rule</th><th class="py-2 pr-3">Next Due</th><th class="py-2 pr-3">Actions</th></tr></thead>
    <tbody>
    <?php foreach($rows as $r): ?>
      <tr class="border-b">
        <td class="py-2 pr-3 font-medium"><?= htmlspecialchars($r['title']) ?></td>
        <td class="py-2 pr-3 font-medium"><?= moneyfmt($r['amount']) ?></td>
        <td class="py-2 pr-3"><?= htmlspecialchars($r['currency']) ?></td>
        <td class="py-2 pr-3 text-xs text-gray-500"><?= htmlspecialchars($r['rrule']) ?></td>
        <td class="py-2 pr-3"><?= htmlspecialchars($r['next_due']) ?></td>
        <td class="py-2 pr-3">
          <details>
            <summary class="cursor-pointer text-accent">Edit</summary>
            <form class="mt-2 grid sm:grid-cols-6 gap-2" method="post" action="/scheduled-payments/edit">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="id" value="<?= $r['id'] ?>" />
              <input name="title" value="<?= htmlspecialchars($r['title']) ?>" class="rounded-xl border-gray-300 sm:col-span-2">
              <input name="amount" type="number" step="0.01" value="<?= $r['amount'] ?>" class="rounded-xl border-gray-300">
              <input name="currency" value="<?= htmlspecialchars($r['currency']) ?>" class="rounded-xl border-gray-300">
              <input name="rrule" value="<?= htmlspecialchars($r['rrule']) ?>" class="rounded-xl border-gray-300 sm:col-span-2">
              <input name="next_due" type="date" value="<?= $r['next_due'] ?>" class="rounded-xl border-gray-300">
              <button class="bg-gray-900 text-white rounded-xl px-4">Save</button>
            </form>
            <form class="mt-2" method="post" action="/scheduled-payments/delete" onsubmit="return confirm('Delete scheduled payment?')">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="id" value="<?= $r['id'] ?>" />
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

## Router additions — /public/index.php
```php
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
```

---

## /src/controllers/scheduled.php
```php
<?php
require_once __DIR__ . '/../helpers.php';

function scheduled_index(PDO $pdo){ require_login(); $u=uid();
  $rows=$pdo->prepare('SELECT * FROM scheduled_payments WHERE user_id=? ORDER BY next_due NULLS LAST, id');
  $rows->execute([$u]); $rows=$rows->fetchAll();
  view('scheduled/index', compact('rows'));
}

function scheduled_add(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $stmt=$pdo->prepare('INSERT INTO scheduled_payments(user_id,title,amount,currency,rrule,next_due) VALUES(?,?,?,?,?,?)');
  $stmt->execute([$u, trim($_POST['title']), (float)$_POST['amount'], $_POST['currency']?:'HUF', trim($_POST['rrule']), $_POST['next_due']?:null]);
}

function scheduled_edit(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $stmt=$pdo->prepare('UPDATE scheduled_payments SET title=?, amount=?, currency=?, rrule=?, next_due=? WHERE id=? AND user_id=?');
  $stmt->execute([trim($_POST['title']), (float)$_POST['amount'], $_POST['currency']?:'HUF', trim($_POST['rrule']), $_POST['next_due']?:null, (int)$_POST['id'], $u]);
}

function scheduled_delete(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $pdo->prepare('DELETE FROM scheduled_payments WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$u]);
}
```

---

## /views/scheduled/index.php
```php
<section class="bg-white rounded-2xl p-5 shadow-glass">
  <h1 class="text-xl font-semibold">Scheduled Payments</h1>
  <p class="text-sm text-gray-500">Use RRULE like <code class="bg-gray-100 px-1 rounded">FREQ=MONTHLY;BYMONTHDAY=10</code> or <code class="bg-gray-100 px-1 rounded">FREQ=WEEKLY;BYDAY=MO</code>.</p>

  <details class="mt-4">
    <summary class="cursor-pointer text-accent">Add scheduled payment</summary>
    <form class="mt-3 grid sm:grid-cols-6 gap-2" method="post" action="/scheduled/add">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input name="title" class="rounded-xl border-gray-300 sm:col-span-2" placeholder="Title (e.g., Rent)" required>
      <input name="amount" type="number" step="0.01" class="rounded-xl border-gray-300" placeholder="Amount" required>
      <input name="currency" class="rounded-xl border-gray-300" value="HUF">
      <input name="next_due" type="date" class="rounded-xl border-gray-300" placeholder="Next due">
      <input name="rrule" class="rounded-xl border-gray-300 sm:col-span-2" placeholder="FREQ=MONTHLY;BYMONTHDAY=10" required>
      <button class="bg-gray-900 text-white rounded-xl px-4">Save</button>
    </form>
  </details>
</section>

<section class="mt-6 bg-white rounded-2xl p-5 shadow-glass overflow-x-auto">
  <table class="min-w-full text-sm">
    <thead>
      <tr class="text-left border-b">
        <th class="py-2 pr-3">Title</th>
        <th class="py-2 pr-3">Amount</th>
        <th class="py-2 pr-3">Currency</th>
        <th class="py-2 pr-3">RRULE</th>
        <th class="py-2 pr-3">Next Due</th>
        <th class="py-2 pr-3">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($rows as $r): ?>
        <tr class="border-b">
          <td class="py-2 pr-3 font-medium"><?= htmlspecialchars($r['title']) ?></td>
          <td class="py-2 pr-3 font-medium"><?= moneyfmt($r['amount']) ?></td>
          <td class="py-2 pr-3"><?= htmlspecialchars($r['currency']) ?></td>
          <td class="py-2 pr-3 text-xs text-gray-600 whitespace-pre-wrap"><?= htmlspecialchars($r['rrule']) ?></td>
          <td class="py-2 pr-3"><?= htmlspecialchars($r['next_due'] ?? '—') ?></td>
          <td class="py-2 pr-3">
            <details>
              <summary class="cursor-pointer text-accent">Edit</summary>
              <form class="mt-2 grid sm:grid-cols-6 gap-2" method="post" action="/scheduled/edit">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="id" value="<?= $r['id'] ?>" />
                <input name="title" value="<?= htmlspecialchars($r['title']) ?>" class="rounded-xl border-gray-300 sm:col-span-2">
                <input name="amount" type="number" step="0.01" value="<?= $r['amount'] ?>" class="rounded-xl border-gray-300">
                <input name="currency" value="<?= htmlspecialchars($r['currency']) ?>" class="rounded-xl border-gray-300">
                <input name="next_due" type="date" value="<?= $r['next_due'] ?>" class="rounded-xl border-gray-300">
                <input name="rrule" value="<?= htmlspecialchars($r['rrule']) ?>" class="rounded-xl border-gray-300 sm:col-span-2">
                <button class="bg-gray-900 text-white rounded-xl px-4">Save</button>
              </form>
              <form class="mt-2" method="post" action="/scheduled/delete" onsubmit="return confirm('Delete scheduled payment?')">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="id" value="<?= $r['id'] ?>" />
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

## Router additions — /public/index.php
```php
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
```

---

## /src/controllers/emergency.php
```php
<?php
require_once __DIR__ . '/../helpers.php';

function emergency_index(PDO $pdo){ require_login(); $u=uid();
  $fund=$pdo->prepare('SELECT * FROM emergency_fund WHERE user_id=?');
  $fund->execute([$u]); $fund=$fund->fetch();

  $tx=$pdo->prepare('SELECT * FROM emergency_transactions WHERE user_id=? ORDER BY occurred_on DESC, id DESC');
  $tx->execute([$u]); $tx=$tx->fetchAll();

  view('emergency/index', compact('fund','tx'));
}

function emergency_set(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  // upsert
  $stmt=$pdo->prepare('INSERT INTO emergency_fund(user_id,target_amount,currency,total) VALUES(?,?,?,?) ON CONFLICT(user_id) DO UPDATE SET target_amount=excluded.target_amount,currency=excluded.currency');
  $stmt->execute([$u,(float)$_POST['target_amount'],$_POST['currency']?:'HUF',0]);
}

function emergency_tx_add(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $kind = $_POST['kind']==='withdraw'?'withdraw':'deposit';
  $amount=(float)$_POST['amount'];
  $pdo->prepare('INSERT INTO emergency_transactions(user_id,occurred_on,amount,kind,note) VALUES(?,?,?,?,?)')
      ->execute([$u,$_POST['occurred_on']?:date('Y-m-d'),$amount,$kind,trim($_POST['note']??'')]);
  // update fund total
  if($kind==='deposit') $pdo->prepare('UPDATE emergency_fund SET total=total+? WHERE user_id=?')->execute([$amount,$u]);
  else $pdo->prepare('UPDATE emergency_fund SET total=GREATEST(0,total-?) WHERE user_id=?')->execute([$amount,$u]);
}

function emergency_tx_delete(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $id=(int)$_POST['id'];
  // adjust total back
  $row=$pdo->prepare('SELECT amount,kind FROM emergency_transactions WHERE id=? AND user_id=?'); $row->execute([$id,$u]); $row=$row->fetch();
  if($row){
    if($row['kind']==='deposit') $pdo->prepare('UPDATE emergency_fund SET total=GREATEST(0,total-?) WHERE user_id=?')->execute([$row['amount'],$u]);
    else $pdo->prepare('UPDATE emergency_fund SET total=total+? WHERE user_id=?')->execute([$row['amount'],$u]);
  }
  $pdo->prepare('DELETE FROM emergency_transactions WHERE id=? AND user_id=?')->execute([$id,$u]);
}
```

---

## /views/emergency/index.php
```php
<section class="bg-white rounded-2xl p-5 shadow-glass">
  <h1 class="text-xl font-semibold">Emergency Fund</h1>
  <?php $pct = ($fund && $fund['target_amount']>0)? round($fund['total']/$fund['target_amount']*100):0; ?>
  <p class="text-sm mt-1">Total: <?= $fund? moneyfmt($fund['total'],$fund['currency']) : '—' ?></p>
  <div class="mt-2 bg-gray-100 h-2 rounded"><div class="h-2 bg-accent rounded" style="width: <?=$pct?>%"></div></div>
  <p class="text-xs text-gray-500 mt-1">Target: <?= $fund? moneyfmt($fund['target_amount'],$fund['currency']) : '—' ?> (<?=$pct?>%)</p>

  <details class="mt-4">
    <summary class="cursor-pointer text-accent">Set target</summary>
    <form class="mt-3 grid sm:grid-cols-4 gap-2" method="post" action="/emergency/set">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input name="target_amount" type="number" step="0.01" value="<?= $fund['target_amount']??'' ?>" class="rounded-xl border-gray-300" placeholder="Target amount" required>
      <input name="currency" value="<?= $fund['currency']??'HUF' ?>" class="rounded-xl border-gray-300">
      <button class="bg-gray-900 text-white rounded-xl px-4">Save</button>
    </form>
  </details>

  <details class="mt-4">
    <summary class="cursor-pointer text-accent">Add transaction</summary>
    <form class="mt-3 grid sm:grid-cols-5 gap-2" method="post" action="/emergency/tx/add">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <select name="kind" class="rounded-xl border-gray-300"><option value="deposit">Deposit</option><option value="withdraw">Withdraw</option></select>
      <input name="amount" type="number" step="0.01" class="rounded-xl border-gray-300" placeholder="Amount" required>
      <input name="occurred_on" type="date" value="<?= date('Y-m-d') ?>" class="rounded-xl border-gray-300">
      <input name="note" class="rounded-xl border-gray-300" placeholder="Note">
      <button class="bg-gray-900 text-white rounded-xl px-4">Save</button>
    </form>
  </details>
</section>

<section class="mt-6 bg-white rounded-2xl p-5 shadow-glass overflow-x-auto">
  <h2 class="font-semibold mb-3">Transactions</h2>
  <table class="min-w-full text-sm">
    <thead><tr class="text-left border-b"><th class="py-2 pr-3">Date</th><th class="py-2 pr-3">Kind</th><th class="py-2 pr-3">Amount</th><th class="py-2 pr-3">Note</th><th class="py-2 pr-3">Actions</th></tr></thead>
    <tbody>
      <?php foreach($tx as $t): ?>
        <tr class="border-b">
          <td class="py-2 pr-3"><?= htmlspecialchars($t['occurred_on']) ?></td>
          <td class="py-2 pr-3 capitalize"><?= htmlspecialchars($t['kind']) ?></td>
          <td class="py-2 pr-3 font-medium"><?= moneyfmt($t['amount']) ?></td>
          <td class="py-2 pr-3 text-gray-500"><?= htmlspecialchars($t['note']??'') ?></td>
          <td class="py-2 pr-3">
            <form method="post" action="/emergency/tx/delete" onsubmit="return confirm('Delete transaction?')">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
              <input type="hidden" name="id" value="<?= $t['id'] ?>" />
              <button class="text-red-600">Remove</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
```

---

## Router additions — /public/index.php
```php
    case '/years':
        require_login();
        require __DIR__ . '/../src/controllers/years.php';
        years_index($pdo);
        break;
    case (preg_match('#^/years/(\d{4})$#',$path,$m)?true:false):
        require_login();
        require __DIR__ . '/../src/controllers/years.php';
        year_view($pdo,(int)$m[1]);
        break;
    case (preg_match('#^/years/(\d{4})/(\d{1,2})$#',$path,$m)?true:false):
        require_login();
        require __DIR__ . '/../src/controllers/years.php';
        month_view($pdo,(int)$m[1],(int)$m[2]);
        break;
```

---

## /src/controllers/years.php
```php
<?php
require_once __DIR__ . '/../helpers.php';

function years_index(PDO $pdo){ require_login(); $u=uid();
  $stmt=$pdo->prepare("SELECT DISTINCT EXTRACT(YEAR FROM occurred_on) AS y FROM transactions WHERE user_id=? ORDER BY y DESC");
  $stmt->execute([$u]); $years=$stmt->fetchAll(PDO::FETCH_COLUMN);
  view('years/index', compact('years'));
}

function year_view(PDO $pdo,int $y){ require_login(); $u=uid();
  $stmt=$pdo->prepare("SELECT EXTRACT(MONTH FROM occurred_on) m, SUM(CASE WHEN kind='income' THEN amount ELSE 0 END) income, SUM(CASE WHEN kind='spending' THEN amount ELSE 0 END) spending
                       FROM transactions WHERE user_id=? AND EXTRACT(YEAR FROM occurred_on)=? GROUP BY m ORDER BY m");
  $stmt->execute([$u,$y]); $months=$stmt->fetchAll();
  view('years/year', compact('y','months'));
}

function month_view(PDO $pdo,int $y,int $m){ require_login(); $u=uid();
  $stmt=$pdo->prepare("SELECT * FROM transactions WHERE user_id=? AND EXTRACT(YEAR FROM occurred_on)=? AND EXTRACT(MONTH FROM occurred_on)=? ORDER BY occurred_on DESC");
  $stmt->execute([$u,$y,$m]); $tx=$stmt->fetchAll();

  $sumIn=0;$sumOut=0; foreach($tx as $t){ if($t['kind']==='income') $sumIn+=$t['amount']; else $sumOut+=$t['amount']; }
  view('years/month', compact('y','m','tx','sumIn','sumOut'));
}
```

---

## /views/years/index.php
```php
<section class="bg-white rounded-2xl p-5 shadow-glass">
  <h1 class="text-xl font-semibold">Years</h1>
  <ul class="mt-3 space-y-2">
    <?php foreach($years as $y): ?>
      <li><a class="text-accent" href="/years/<?= $y ?>">Year <?= $y ?></a></li>
    <?php endforeach; ?>
  </ul>
</section>
```

---

## /views/years/year.php
```php
<section class="bg-white rounded-2xl p-5 shadow-glass">
  <h1 class="text-xl font-semibold">Year <?= $y ?></h1>
  <table class="mt-3 min-w-full text-sm">
    <thead><tr class="text-left border-b"><th class="py-2 pr-3">Month</th><th class="py-2 pr-3">Income</th><th class="py-2 pr-3">Spending</th><th class="py-2 pr-3">Net</th></tr></thead>
    <tbody>
      <?php foreach($months as $m): $net=$m['income']-$m['spending']; ?>
        <tr class="border-b">
          <td class="py-2 pr-3"><a class="text-accent" href="/years/<?= $y ?>/<?= $m['m'] ?>">Month <?= $m['m'] ?></a></td>
          <td class="py-2 pr-3 font-medium"><?= moneyfmt($m['income']) ?></td>
          <td class="py-2 pr-3 font-medium"><?= moneyfmt($m['spending']) ?></td>
          <td class="py-2 pr-3 font-medium"><?= moneyfmt($net) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
```

---

## /views/years/month.php
```php
<section class="bg-white rounded-2xl p-5 shadow-glass">
  <h1 class="text-xl font-semibold"><?= $y ?>‑<?= str_pad($m,2,'0',STR_PAD_LEFT) ?></h1>
  <p class="mt-2 text-sm text-gray-500">Income: <?= moneyfmt($sumIn) ?> | Spending: <?= moneyfmt($sumOut) ?> | Net: <?= moneyfmt($sumIn-$sumOut) ?></p>

  <table class="mt-4 min-w-full text-sm">
    <thead><tr class="text-left border-b"><th class="py-2 pr-3">Date</th><th class="py-2 pr-3">Kind</th><th class="py-2 pr-3">Amount</th><th class="py-2 pr-3">Note</th></tr></thead>
    <tbody>
      <?php foreach($tx as $t): ?>
        <tr class="border-b">
          <td class="py-2 pr-3"><?= htmlspecialchars($t['occurred_on']) ?></td>
          <td class="py-2 pr-3 capitalize"><?= htmlspecialchars($t['kind']) ?></td>
          <td class="py-2 pr-3 font-medium"><?= moneyfmt($t['amount']) ?></td>
          <td class="py-2 pr-3 text-gray-500"><?= htmlspecialchars($t['note']??'') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
```

---

## Router additions — /public/index.php
```php
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
```

---

## /src/controllers/years.php
```php
<?php
require_once __DIR__ . '/../helpers.php';

function years_index(PDO $pdo){
  require_login(); $u=uid();
  // Determine min/max year from transactions & loan_payments & basic_incomes
  $q = $pdo->prepare("SELECT MIN(EXTRACT(YEAR FROM d))::int AS y_min, MAX(EXTRACT(YEAR FROM d))::int AS y_max FROM (
      SELECT occurred_on AS d FROM transactions WHERE user_id=?
      UNION ALL SELECT paid_on FROM loan_payments lp JOIN loans l ON l.id=lp.loan_id AND l.user_id=?
      UNION ALL SELECT valid_from FROM basic_incomes WHERE user_id=?
  ) s");
  $q->execute([$u,$u,$u]); $row=$q->fetch();
  $ymin = $row && $row['y_min'] ? (int)$row['y_min'] : (int)date('Y');
  $ymax = $row && $row['y_max'] ? (int)$row['y_max'] : (int)date('Y');
  // Build yearly aggregates
  $agg=$pdo->prepare("SELECT EXTRACT(YEAR FROM occurred_on)::int y,
     SUM(CASE WHEN kind='income' THEN amount ELSE 0 END) income,
     SUM(CASE WHEN kind='spending' THEN amount ELSE 0 END) spending
     FROM transactions WHERE user_id=? GROUP BY y ORDER BY y DESC");
  $agg->execute([$u]); $byYear=[]; foreach($agg as $r){ $byYear[(int)$r['y']]=$r; }
  view('years/index', compact('ymin','ymax','byYear'));
}

function year_detail(PDO $pdo, int $year){
  require_login(); $u=uid();
  // Monthly sums for the year
  $q=$pdo->prepare("SELECT EXTRACT(MONTH FROM occurred_on)::int m,
     SUM(CASE WHEN kind='income' THEN amount ELSE 0 END) income,
     SUM(CASE WHEN kind='spending' THEN amount ELSE 0 END) spending
     FROM transactions WHERE user_id=? AND EXTRACT(YEAR FROM occurred_on)=? GROUP BY m ORDER BY m");
  $q->execute([$u,$year]); $rows = $q->fetchAll();
  // Map 1..12
  $byMonth = array_fill(1,12,['income'=>0,'spending'=>0]);
  foreach($rows as $r){ $byMonth[(int)$r['m']] = ['income'=>(float)$r['income'],'spending'=>(float)$r['spending']]; }
  view('years/year', compact('year','byMonth'));
}

function month_detail(PDO $pdo, int $year, int $month){
  require_login(); $u=uid();
  // Transactions for month
  $tx=$pdo->prepare("SELECT t.*, c.label AS cat_label FROM transactions t
    LEFT JOIN categories c ON c.id=t.category_id
    WHERE t.user_id=? AND EXTRACT(YEAR FROM occurred_on)=? AND EXTRACT(MONTH FROM occurred_on)=?
    ORDER BY occurred_on DESC, id DESC");
  $tx->execute([$u,$year,$month]); $tx=$tx->fetchAll();
  $sumIn=0; $sumOut=0; foreach($tx as $r){ if($r['kind']==='income') $sumIn+=$r['amount']; else $sumOut+=$r['amount']; }

  // Goals snapshot
  $g=$pdo->prepare("SELECT SUM(current_amount) c, SUM(target_amount) t FROM goals WHERE user_id=? AND status='active'");
  $g->execute([$u]); $g=$g->fetch();

  // Emergency fund snapshot
  $e=$pdo->prepare('SELECT total,target_amount,currency FROM emergency_fund WHERE user_id=?');
  $e->execute([$u]); $e=$e->fetch();

  // Scheduled payments due in that month
  $sp=$pdo->prepare("SELECT id,title,amount,currency,next_due FROM scheduled_payments WHERE user_id=? AND next_due >= make_date(?, ?, 1) AND next_due < (make_date(?, ?, 1) + INTERVAL '1 month') ORDER BY next_due");
  $sp->execute([$u,$year,$month,$year,$month]); $scheduled=$sp->fetchAll();

  // Loan payments in that month
  $lp=$pdo->prepare("SELECT l.name, lp.* FROM loan_payments lp JOIN loans l ON l.id=lp.loan_id AND l.user_id=?
                     WHERE lp.paid_on >= make_date(?, ?, 1) AND lp.paid_on < (make_date(?, ?, 1) + INTERVAL '1 month')
                     ORDER BY lp.paid_on DESC");
  $lp->execute([$u,$year,$month,$year,$month]); $loanPayments=$lp->fetchAll();

  // Categories for quick add
  $cats = $pdo->prepare('SELECT id,label,kind FROM categories WHERE user_id=? ORDER BY kind,label');
  $cats->execute([$u]); $cats=$cats->fetchAll();

  view('years/month', compact('year','month','tx','sumIn','sumOut','g','e','scheduled','loanPayments','cats'));
}

/* Month‑scoped transaction POST endpoints (redirect back to the month page) */
function month_tx_add(PDO $pdo){ verify_csrf(); require_login();
  $y=(int)$_POST['y']; $m=(int)$_POST['m']; $u=uid();
  $stmt=$pdo->prepare('INSERT INTO transactions(user_id,kind,category_id,amount,currency,occurred_on,note) VALUES(?,?,?,?,?,?,?)');
  $stmt->execute([$u, $_POST['kind']==='income'?'income':'spending', !empty($_POST['category_id'])?(int)$_POST['category_id']:null, (float)$_POST['amount'], $_POST['currency']??'HUF', $_POST['occurred_on']??date('Y-m-d'), trim($_POST['note']??'')]);
  redirect('/years/'.$y.'/'.$m);
}
function month_tx_edit(PDO $pdo){ verify_csrf(); require_login();
  $y=(int)$_POST['y']; $m=(int)$_POST['m']; $u=uid();
  $stmt=$pdo->prepare('UPDATE transactions SET kind=?, category_id=?, amount=?, currency=?, occurred_on=?, note=?, updated_at=NOW() WHERE id=? AND user_id=?');
  $stmt->execute([$_POST['kind']==='income'?'income':'spending', !empty($_POST['category_id'])?(int)$_POST['category_id']:null, (float)$_POST['amount'], $_POST['currency']??'HUF', $_POST['occurred_on']??date('Y-m-d'), trim($_POST['note']??''), (int)$_POST['id'], $u]);
  redirect('/years/'.$y.'/'.$m);
}
function month_tx_delete(PDO $pdo){ verify_csrf(); require_login();
  $y=(int)$_POST['y']; $m=(int)$_POST['m']; $u=uid();
  $pdo->prepare('DELETE FROM transactions WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$u]);
  redirect('/years/'.$y.'/'.$m);
}
```

---

## /views/years/index.php
```php
<section class="bg-white rounded-2xl p-5 shadow-glass">
  <h1 class="text-xl font-semibold">Years</h1>
  <ul class="mt-4 grid sm:grid-cols-3 gap-3">
    <?php for($y=$ymax; $y>=$ymin; $y--): $row=$byYear[$y] ?? ['income'=>0,'spending'=>0]; $net=$row['income']-$row['spending']; ?>
      <li class="border rounded-2xl p-4 hover:shadow">
        <a class="flex items-center justify-between" href="/years/<?= $y ?>">
          <div>
            <div class="text-lg font-semibold"><?= $y ?></div>
            <div class="text-xs text-gray-500">Income: <?= moneyfmt($row['income']) ?> · Spending: <?= moneyfmt($row['spending']) ?></div>
          </div>
          <div class="text-sm font-medium <?= $net>=0?'text-emerald-600':'text-red-600' ?>"><?= moneyfmt($net) ?></div>
        </a>
      </li>
    <?php endfor; ?>
  </ul>
</section>
```

---

## /views/years/year.php
```php
<section class="bg-white rounded-2xl p-5 shadow-glass">
  <h1 class="text-xl font-semibold"><?= $year ?></h1>
  <div class="mt-4 grid sm:grid-cols-3 md:grid-cols-4 gap-3">
    <?php for($m=1;$m<=12;$m++): $r=$byMonth[$m]; $net=$r['income']-$r['spending']; ?>
      <a href="/years/<?= $year ?>/<?= $m ?>" class="border rounded-2xl p-4 hover:shadow block">
        <div class="font-medium"><?= date('M', mktime(0,0,0,$m,1,$year)) ?></div>
        <div class="text-xs text-gray-500 mt-1">Inc: <?= moneyfmt($r['income']) ?> · Sp: <?= moneyfmt($r['spending']) ?></div>
        <div class="text-sm font-medium mt-1 <?= $net>=0?'text-emerald-600':'text-red-600' ?>"><?= moneyfmt($net) ?></div>
      </a>
    <?php endfor; ?>
  </div>
</section>
```

---

## /views/years/month.php
```php
<?php $ym = sprintf('%04d-%02d', $year, $month); ?>
<section class="grid md:grid-cols-3 gap-4">
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h2 class="font-medium"><?= date('F Y', mktime(0,0,0,$month,1,$year)) ?></h2>
    <p class="mt-2 text-sm text-gray-500">Income: <strong><?= moneyfmt($sumIn) ?></strong></p>
    <p class="mt-1 text-sm text-gray-500">Spending: <strong><?= moneyfmt($sumOut) ?></strong></p>
    <p class="mt-1 text-sm">Net: <strong><?= moneyfmt($sumIn - $sumOut) ?></strong></p>
  </div>
  <div class="bg-white rounded-2xl p-5 shadow-glass md:col-span-2">
    <h3 class="font-semibold mb-3">Quick Add</h3>
    <form class="grid sm:grid-cols-6 gap-2" method="post" action="/months/tx/add">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input type="hidden" name="y" value="<?= $year ?>" />
      <input type="hidden" name="m" value="<?= $month ?>" />
      <select name="kind" class="rounded-xl border-gray-300 sm:col-span-1">
        <option value="income">Income</option>
        <option value="spending">Spending</option>
      </select>
      <select name="category_id" class="rounded-xl border-gray-300 sm:col-span-2">
        <option value="">— Category —</option>
        <?php foreach($cats as $c): ?><option value="<?= $c['id'] ?>"><?php echo ucfirst($c['kind']).' · '.htmlspecialchars($c['label']); ?></option><?php endforeach; ?>
      </select>
      <input name="amount" type="number" step="0.01" placeholder="Amount" class="rounded-xl border-gray-300 sm:col-span-1" required />
      <input name="occurred_on" type="date" value="<?= $ym ?>-<?= min( cal_days_in_month(CAL_GREGORIAN,$month,$year), (int)date('d') ) ?>" class="rounded-xl border-gray-300 sm:col-span-1" />
      <input name="note" placeholder="Note" class="rounded-xl border-gray-300 sm:col-span-1" />
      <button class="bg-gray-900 text-white rounded-xl px-4">Add</button>
    </form>
  </div>
</section>

<section class="mt-6 grid md:grid-cols-2 gap-6">
  <div class="bg-white rounded-2xl p-5 shadow-glass h-80">
    <h3 class="font-semibold mb-3">Spending by Category</h3>
    <?php
      $sp=$pdo->prepare("SELECT COALESCE(c.label,'Uncategorized') lb, SUM(t.amount) s
        FROM transactions t LEFT JOIN categories c ON c.id=t.category_id
        WHERE t.user_id=? AND t.kind='spending' AND EXTRACT(YEAR FROM t.occurred_on)=? AND EXTRACT(MONTH FROM t.occurred_on)=?
        GROUP BY lb ORDER BY s DESC");
      $sp->execute([uid(),$year,$month]); $labels=[]; $data=[]; foreach($sp as $r){$labels[]=$r['lb']; $data[]=(float)$r['s'];}
    ?>
    <canvas id="spendcat-month" class="w-full h-64"></canvas>
    <script>renderDoughnut('spendcat-month', <?= json_encode($labels) ?>, <?= json_encode($data) ?>);</script>
  </div>
  <div class="bg-white rounded-2xl p-5 shadow-glass h-80">
    <h3 class="font-semibold mb-3">Daily Flow</h3>
    <?php
      $q=$pdo->prepare("SELECT occurred_on::date d, SUM(CASE WHEN kind='income' THEN amount ELSE -amount END) v
                        FROM transactions WHERE user_id=? AND EXTRACT(YEAR FROM occurred_on)=? AND EXTRACT(MONTH FROM occurred_on)=?
                        GROUP BY d ORDER BY d");
      $q->execute([uid(),$year,$month]); $labels=[]; $data=[]; foreach($q as $r){$labels[]=$r['d']; $data[]=(float)$r['v'];}
    ?>
    <canvas id="flow-month" class="w-full h-64"></canvas>
    <script>renderLineChart('flow-month', <?= json_encode($labels) ?>, <?= json_encode($data) ?>);</script>
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
              <form class="mt-2 grid sm:grid-cols-6 gap-2" method="post" action="/months/tx/edit">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="y" value="<?= $year ?>" />
                <input type="hidden" name="m" value="<?= $month ?>" />
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
              <form class="mt-2" method="post" action="/months/tx/delete" onsubmit="return confirm('Delete transaction?')">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                <input type="hidden" name="y" value="<?= $year ?>" />
                <input type="hidden" name="m" value="<?= $month ?>" />
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

<section class="mt-6 grid md:grid-cols-3 gap-4">
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h3 class="font-semibold">Goals snapshot</h3>
    <?php $pc = ($g && $g['t']>0) ? round($g['c']/$g['t']*100) : 0; ?>
    <div class="mt-2 w-full bg-gray-100 h-2 rounded"><div class="h-2 rounded bg-accent" style="width: <?= $pc ?>%"></div></div>
    <p class="text-xs text-gray-500 mt-1"><?= $pc ?>% of active goals</p>
  </div>
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h3 class="font-semibold">Emergency fund</h3>
    <?php $epc = ($e && $e['target_amount']>0)? round($e['total']/$e['target_amount']*100):0; ?>
    <p class="text-sm">Total: <?= $e? moneyfmt($e['total'],$e['currency']) : '—' ?></p>
    <div class="mt-2 w-full bg-gray-100 h-2 rounded"><div class="h-2 rounded bg-accent" style="width: <?= $epc ?>%"></div></div>
  </div>
  <div class="bg-white rounded-2xl p-5 shadow-glass">
    <h3 class="font-semibold">Scheduled payments this month</h3>
    <ul class="mt-2 text-sm">
      <?php foreach($scheduled as $s): ?>
        <li class="flex justify-between py-1"><span><?= htmlspecialchars($s['title']) ?></span><span><?= moneyfmt($s['amount'],$s['currency']) ?></span></li>
      <?php endforeach; if(!count($scheduled)): ?>
        <li class="text-gray-500 text-sm">No scheduled payments due.</li>
      <?php endif; ?>
    </ul>
  </div>
</section>

<section class="mt-6 bg-white rounded-2xl p-5 shadow-glass overflow-x-auto">
  <h3 class="font-semibold mb-3">Loan payments this month</h3>
  <table class="min-w-full text-sm">
    <thead><tr class="text-left border-b"><th class="py-2 pr-3">Date</th><th class="py-2 pr-3">Loan</th><th class="py-2 pr-3">Amount</th><th class="py-2 pr-3">Principal</th><th class="py-2 pr-3">Interest</th></tr></thead>
    <tbody>
      <?php foreach($loanPayments as $p): ?>
        <tr class="border-b">
          <td class="py-2 pr-3"><?= htmlspecialchars($p['paid_on']) ?></td>
          <td class="py-2 pr-3 font-medium"><?= htmlspecialchars($p['name']) ?></td>
          <td class="py-2 pr-3 font-medium"><?= moneyfmt($p['amount']) ?></td>
          <td class="py-2 pr-3"><?= moneyfmt($p['principal_component']) ?></td>
          <td class="py-2 pr-3"><?= moneyfmt($p['interest_component']) ?></td>
        </tr>
      <?php endforeach; if(!count($loanPayments)): ?>
        <tr><td class="py-3 text-gray-500" colspan="5">No loan payments recorded this month.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>
```


---

## Router additions — `/public/index.php`
```php
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
```

---

## Controller — `/src/controllers/settings_currencies.php`
```php
<?php
require_once __DIR__ . '/../helpers.php';

function currencies_index(PDO $pdo){
    require_login(); $u=uid();
    // Current user currencies
    $cur = $pdo->prepare('SELECT uc.code, uc.is_main, c.name FROM user_currencies uc JOIN currencies c ON c.code=uc.code WHERE uc.user_id=? ORDER BY uc.is_main DESC, uc.code');
    $cur->execute([$u]); $userCurrencies = $cur->fetchAll();

    // All available minus already added
    $avail = $pdo->prepare('SELECT code, name FROM currencies WHERE code NOT IN (SELECT code FROM user_currencies WHERE user_id=?) ORDER BY code');
    $avail->execute([$u]); $available = $avail->fetchAll();

    view('settings/currencies', compact('userCurrencies','available'));
}

function currency_add(PDO $pdo){
    verify_csrf(); require_login(); $u=uid();
    $code = strtoupper(trim($_POST['code'] ?? ''));
    if ($code === '') return;
    // If user has no currencies yet, set as main
    $has = $pdo->prepare('SELECT COUNT(*) FROM user_currencies WHERE user_id=?');
    $has->execute([$u]); $count = (int)$has->fetchColumn();
    $stmt=$pdo->prepare('INSERT INTO user_currencies(user_id,code,is_main) VALUES(?,?,?) ON CONFLICT (user_id,code) DO NOTHING');
    $stmt->execute([$u,$code,$count===0]);
}

function currency_remove(PDO $pdo){
    verify_csrf(); require_login(); $u=uid();
    $code = strtoupper(trim($_POST['code'] ?? ''));
    if ($code==='') return;
    // prevent removing main currency
    $m=$pdo->prepare('SELECT is_main FROM user_currencies WHERE user_id=? AND code=?');
    $m->execute([$u,$code]); $row=$m->fetch();
    if ($row && !$row['is_main']) {
        $pdo->prepare('DELETE FROM user_currencies WHERE user_id=? AND code=?')->execute([$u,$code]);
    }
}

function currency_set_main(PDO $pdo){
    verify_csrf(); require_login(); $u=uid();
    $code = strtoupper(trim($_POST['code'] ?? ''));
    if ($code==='') return;
    $pdo->prepare('UPDATE user_currencies SET is_main=false WHERE user_id=?')->execute([$u]);
    $pdo->prepare('UPDATE user_currencies SET is_main=true WHERE user_id=? AND code=?')->execute([$u,$code]);
}
```

---

## View — `/views/settings/currencies.php`
```php
<section class="max-w-3xl mx-auto bg-white rounded-2xl p-6 shadow-glass">
  <div class="flex items-center justify-between">
    <h1 class="text-xl font-semibold">Manage Currencies</h1>
    <a href="/settings" class="text-sm text-accent">← Back to Settings</a>
  </div>

  <div class="mt-6 grid md:grid-cols-2 gap-6">
    <div>
      <h2 class="font-medium mb-2">Your currencies</h2>
      <ul class="divide-y">
        <?php foreach($userCurrencies as $c): ?>
          <li class="py-3 flex items-center justify-between">
            <div>
              <div class="font-medium">
                <?= htmlspecialchars($c['code']) ?>
                <?php if ($c['is_main']): ?><span class="ml-2 text-xs px-2 py-0.5 rounded-full bg-gray-900 text-white">Main</span><?php endif; ?>
              </div>
              <div class="text-xs text-gray-500"><?= htmlspecialchars($c['name']) ?></div>
            </div>
            <div class="flex gap-2">
              <?php if (!$c['is_main']): ?>
                <form method="post" action="/settings/currencies/main">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="code" value="<?= htmlspecialchars($c['code']) ?>" />
                  <button class="px-3 py-1.5 rounded-lg border hover:bg-gray-50">Set main</button>
                </form>
                <form method="post" action="/settings/currencies/remove" onsubmit="return confirm('Remove currency <?= htmlspecialchars($c['code']) ?>?')">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="code" value="<?= htmlspecialchars($c['code']) ?>" />
                  <button class="px-3 py-1.5 rounded-lg border text-red-600 hover:bg-red-50">Remove</button>
                </form>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; if (!count($userCurrencies)): ?>
          <li class="py-3 text-sm text-gray-500">No currencies yet.</li>
        <?php endif; ?>
      </ul>
    </div>

    <div>
      <h2 class="font-medium mb-2">Add currency</h2>
      <form method="post" action="/settings/currencies/add" class="flex gap-2">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <select name="code" class="flex-1 rounded-xl border-gray-300" required>
          <option value="">— Select currency —</option>
          <?php foreach($available as $a): ?>
            <option value="<?= htmlspecialchars($a['code']) ?>"><?= htmlspecialchars($a['code']) ?> — <?= htmlspecialchars($a['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="px-4 rounded-xl bg-gray-900 text-white">Add</button>
      </form>
      <p class="text-xs text-gray-500 mt-2">The first currency you add becomes your main by default.</p>
    </div>
  </div>
</section>
```

---

## (Optional) Link from Settings index — `/views/settings/index.php`
Add a small link so users can reach the page:
```php
<p class="text-sm mt-3"><a class="text-accent" href="/settings/currencies">Manage currencies →</a></p>
```


---

## New migration — `/migrations/002_seed_currencies.sql`
```sql
-- Seed ISO 4217 active currencies (ON CONFLICT DO NOTHING ensures idempotency)
INSERT INTO currencies(code,name) VALUES
('AED','United Arab Emirates Dirham'),
('AFN','Afghan Afghani'),
('ALL','Albanian Lek'),
('AMD','Armenian Dram'),
('ANG','Netherlands Antillean Guilder'),
('AOA','Angolan Kwanza'),
('ARS','Argentine Peso'),
('AUD','Australian Dollar'),
('AWG','Aruban Florin'),
('AZN','Azerbaijani Manat'),
('BAM','Bosnia and Herzegovina Convertible Mark'),
('BBD','Barbados Dollar'),
('BDT','Bangladeshi Taka'),
('BGN','Bulgarian Lev'),
('BHD','Bahraini Dinar'),
('BIF','Burundian Franc'),
('BMD','Bermudian Dollar'),
('BND','Brunei Dollar'),
('BOB','Boliviano'),
('BRL','Brazilian Real'),
('BSD','Bahamian Dollar'),
('BTN','Bhutanese Ngultrum'),
('BWP','Botswana Pula'),
('BYN','Belarusian Ruble'),
('BZD','Belize Dollar'),
('CAD','Canadian Dollar'),
('CDF','Congolese Franc'),
('CHF','Swiss Franc'),
('CLP','Chilean Peso'),
('CNY','Chinese Yuan'),
('COP','Colombian Peso'),
('CRC','Costa Rican Colon'),
('CUP','Cuban Peso'),
('CVE','Cabo Verde Escudo'),
('CZK','Czech Koruna'),
('DJF','Djiboutian Franc'),
('DKK','Danish Krone'),
('DOP','Dominican Peso'),
('DZD','Algerian Dinar'),
('EGP','Egyptian Pound'),
('ERN','Eritrean Nakfa'),
('ETB','Ethiopian Birr'),
('EUR','Euro'),
('FJD','Fiji Dollar'),
('FKP','Falkland Islands Pound'),
('FOK','Faroese Króna'),
('GBP','Pound Sterling'),
('GEL','Georgian Lari'),
('GGP','Guernsey Pound'),
('GHS','Ghanaian Cedi'),
('GIP','Gibraltar Pound'),
('GMD','Gambian Dalasi'),
('GNF','Guinean Franc'),
('GTQ','Guatemalan Quetzal'),
('GYD','Guyanese Dollar'),
('HKD','Hong Kong Dollar'),
('HNL','Honduran Lempira'),
('HRK','Croatian Kuna'), -- legacy, still used in older data
('HTG','Haitian Gourde'),
('HUF','Hungarian Forint'),
('IDR','Indonesian Rupiah'),
('ILS','Israeli New Shekel'),
('IMP','Isle of Man Pound'),
('INR','Indian Rupee'),
('IQD','Iraqi Dinar'),
('IRR','Iranian Rial'),
('ISK','Icelandic Króna'),
('JEP','Jersey Pound'),
('JMD','Jamaican Dollar'),
('JOD','Jordanian Dinar'),
('JPY','Japanese Yen'),
('KES','Kenyan Shilling'),
('KGS','Kyrgyzstani Som'),
('KHR','Cambodian Riel'),
('KID','Kiribati Dollar'),
('KMF','Comorian Franc'),
('KRW','South Korean Won'),
('KWD','Kuwaiti Dinar'),
('KYD','Cayman Islands Dollar'),
('KZT','Kazakhstani Tenge'),
('LAK','Lao Kip'),
('LBP','Lebanese Pound'),
('LKR','Sri Lanka Rupee'),
('LRD','Liberian Dollar'),
('LSL','Lesotho Loti'),
('LYD','Libyan Dinar'),
('MAD','Moroccan Dirham'),
('MDL','Moldovan Leu'),
('MGA','Malagasy Ariary'),
('MKD','Macedonian Denar'),
('MMK','Myanmar Kyat'),
('MNT','Mongolian Tögrög'),
('MOP','Macanese Pataca'),
('MRU','Mauritanian Ouguiya'),
('MUR','Mauritius Rupee'),
('MVR','Maldivian Rufiyaa'),
('MWK','Malawian Kwacha'),
('MXN','Mexican Peso'),
('MYR','Malaysian Ringgit'),
('MZN','Mozambican Metical'),
('NAD','Namibian Dollar'),
('NGN','Nigerian Naira'),
('NIO','Nicaraguan Córdoba'),
('NOK','Norwegian Krone'),
('NPR','Nepalese Rupee'),
('NZD','New Zealand Dollar'),
('OMR','Omani Rial'),
('PAB','Panamanian Balboa'),
('PEN','Peruvian Sol'),
('PGK','Papua New Guinean Kina'),
('PHP','Philippine Peso'),
('PKR','Pakistani Rupee'),
('PLN','Polish Złoty'),
('PYG','Paraguayan Guaraní'),
('QAR','Qatari Riyal'),
('RON','Romanian Leu'),
('RSD','Serbian Dinar'),
('RUB','Russian Ruble'),
('RWF','Rwandan Franc'),
('SAR','Saudi Riyal'),
('SBD','Solomon Islands Dollar'),
('SCR','Seychelles Rupee'),
('SDG','Sudanese Pound'),
('SEK','Swedish Krona'),
('SGD','Singapore Dollar'),
('SHP','Saint Helena Pound'),
('SLE','Sierra Leonean Leone'),
('SOS','Somali Shilling'),
('SRD','Surinam Dollar'),
('SSP','South Sudanese Pound'),
('STN','São Tomé and Príncipe Dobra'),
('SYP','Syrian Pound'),
('SZL','Eswatini Lilangeni'),
('THB','Thai Baht'),
('TJS','Tajikistani Somoni'),
('TMT','Turkmenistan Manat'),
('TND','Tunisian Dinar'),
('TOP','Tongan Paʻanga'),
('TRY','Turkish Lira'),
('TTD','Trinidad and Tobago Dollar'),
('TVD','Tuvaluan Dollar'),
('TWD','New Taiwan Dollar'),
('TZS','Tanzanian Shilling'),
('UAH','Ukrainian Hryvnia'),
('UGX','Ugandan Shilling'),
('USD','US Dollar'),
('UYU','Uruguayan Peso'),
('UZS','Uzbekistani Soʻm'),
('VES','Venezuelan Bolívar Soberano'),
('VND','Vietnamese Đồng'),
('VUV','Vanuatu Vatu'),
('WST','Samoan Tālā'),
('XAF','CFA Franc BEAC'),
('XCD','East Caribbean Dollar'),
('XOF','CFA Franc BCEAO'),
('XPF','CFP Franc'),
('YER','Yemeni Rial'),
('ZAR','South African Rand'),
('ZMW','Zambian Kwacha'),
('ZWL','Zimbabwean Dollar')
ON CONFLICT (code) DO NOTHING;
```

---

## (Optional) PHP seeder — `/scripts/seed_currencies.php`
```php
<?php
// php scripts/seed_currencies.php
require __DIR__ . '/../config/db.php';
$codes = $pdo->query("SELECT COUNT(*) FROM currencies")->fetchColumn();
if ($codes > 10) { echo "Currencies already populated
"; exit; }
$sql = file_get_contents(__DIR__.'/../migrations/002_seed_currencies.sql');
$pdo->exec($sql);
echo "Seeded currencies.
";
```

---

## How to apply
```bash
psql moneymap < migrations/002_seed_currencies.sql
# or
php scripts/seed_currencies.php
```

Your **Add currency** selector on `/settings/currencies` now lists the full ISO set (minus those you already added).

---

## Router additions — `/public/index.php`
```php
    case '/settings/basic-incomes':
        require_login();
        require __DIR__ . '/../src/controllers/settings_basic_incomes.php';
        basic_incomes_index($pdo);
        break;
    case '/settings/basic-incomes/add':
        require_login();
        require __DIR__ . '/../src/controllers/settings_basic_incomes.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { basic_income_add($pdo); }
        redirect('/settings/basic-incomes');
        break;
    case '/settings/basic-incomes/edit':
        require_login();
        require __DIR__ . '/../src/controllers/settings_basic_incomes.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { basic_income_edit($pdo); }
        redirect('/settings/basic-incomes');
        break;
    case '/settings/basic-incomes/delete':
        require_login();
        require __DIR__ . '/../src/controllers/settings_basic_incomes.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { basic_income_delete($pdo); }
        redirect('/settings/basic-incomes');
        break;
```

---

## Controller — `/src/controllers/settings_basic_incomes.php`
```php
<?php
require_once __DIR__ . '/../helpers.php';

function basic_incomes_index(PDO $pdo){
    require_login(); $u = uid();
    $rows = $pdo->prepare('SELECT * FROM basic_incomes WHERE user_id=? ORDER BY valid_from DESC, id DESC');
    $rows->execute([$u]);
    $incomes = $rows->fetchAll();

    view('settings/basic_incomes', compact('incomes'));
}

function basic_income_add(PDO $pdo){
    verify_csrf(); require_login(); $u = uid();
    $stmt=$pdo->prepare('INSERT INTO basic_incomes(user_id,label,amount,currency,valid_from) VALUES(?,?,?,?,?)');
    $stmt->execute([$u, trim($_POST['label']), (float)$_POST['amount'], $_POST['currency']?:'HUF', $_POST['valid_from']?:date('Y-m-d')]);
}

function basic_income_edit(PDO $pdo){
    verify_csrf(); require_login(); $u = uid();
    $stmt=$pdo->prepare('UPDATE basic_incomes SET label=?, amount=?, currency=?, valid_from=? WHERE id=? AND user_id=?');
    $stmt->execute([trim($_POST['label']), (float)$_POST['amount'], $_POST['currency']?:'HUF', $_POST['valid_from']?:date('Y-m-d'), (int)$_POST['id'], $u]);
}

function basic_income_delete(PDO $pdo){
    verify_csrf(); require_login(); $u = uid();
    $pdo->prepare('DELETE FROM basic_incomes WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$u]);
}
```

---

## View — `/views/settings/basic_incomes.php`
```php
<section class="max-w-3xl mx-auto bg-white rounded-2xl p-6 shadow-glass">
  <div class="flex items-center justify-between">
    <h1 class="text-xl font-semibold">Manage Basic Incomes</h1>
    <a href="/settings" class="text-sm text-accent">← Back to Settings</a>
  </div>

  <details class="mt-4">
    <summary class="cursor-pointer text-accent">Add basic income</summary>
    <form class="mt-3 grid sm:grid-cols-5 gap-2" method="post" action="/settings/basic-incomes/add">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
      <input name="label" placeholder="Label (e.g., Salary)" class="rounded-xl border-gray-300 sm:col-span-2" required>
      <input name="amount" type="number" step="0.01" class="rounded-xl border-gray-300" placeholder="Amount" required>
      <input name="currency" value="HUF" class="rounded-xl border-gray-300">
      <input name="valid_from" type="date" value="<?= date('Y-m-d') ?>" class="rounded-xl border-gray-300">
      <button class="bg-gray-900 text-white rounded-xl px-4">Save</button>
    </form>
  </details>

  <div class="mt-6 overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3">Label</th>
          <th class="py-2 pr-3">Amount</th>
          <th class="py-2 pr-3">Currency</th>
          <th class="py-2 pr-3">Valid From</th>
          <th class="py-2 pr-3">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($incomes as $row): ?>
          <tr class="border-b">
            <td class="py-2 pr-3 font-medium"><?= htmlspecialchars($row['label']) ?></td>
            <td class="py-2 pr-3 font-medium"><?= moneyfmt($row['amount']) ?></td>
            <td class="py-2 pr-3"><?= htmlspecialchars($row['currency']) ?></td>
            <td class="py-2 pr-3"><?= htmlspecialchars($row['valid_from']) ?></td>
            <td class="py-2 pr-3">
              <details>
                <summary class="cursor-pointer text-accent">Edit</summary>
                <form class="mt-2 grid sm:grid-cols-5 gap-2" method="post" action="/settings/basic-incomes/edit">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="id" value="<?= $row['id'] ?>" />
                  <input name="label" value="<?= htmlspecialchars($row['label']) ?>" class="rounded-xl border-gray-300 sm:col-span-2" required>
                  <input name="amount" type="number" step="0.01" value="<?= $row['amount'] ?>" class="rounded-xl border-gray-300" required>
                  <input name="currency" value="<?= htmlspecialchars($row['currency']) ?>" class="rounded-xl border-gray-300">
                  <input name="valid_from" type="date" value="<?= $row['valid_from'] ?>" class="rounded-xl border-gray-300">
                  <button class="bg-gray-900 text-white rounded-xl px-4">Save</button>
                </form>
                <form class="mt-2" method="post" action="/settings/basic-incomes/delete" onsubmit="return confirm('Delete this income?')">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="id" value="<?= $row['id'] ?>" />
                  <button class="text-red-600">Remove</button>
                </form>
              </details>
            </td>
          </tr>
        <?php endforeach; if (!count($incomes)): ?>
          <tr><td colspan="5" class="py-3 text-gray-500">No basic incomes defined.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
```

---

## (Optional) Link from Settings index — `/views/settings/index.php`
```php
<p class="text-sm mt-3"><a class="text-accent" href="/settings/basic-incomes">Manage basic incomes →</a></p>
```

---

## Router additions — `/public/index.php`
```php
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
```

---

## Controller — `/src/controllers/settings_incomes.php`
```php
<?php
require_once __DIR__ . '/../helpers.php';

function incomes_index(PDO $pdo){
    require_login(); $u=uid();
    // group by label with history, newest first
    $q = $pdo->prepare('SELECT * FROM basic_incomes WHERE user_id=? ORDER BY label, valid_from DESC, id DESC');
    $q->execute([$u]);
    $rows = $q->fetchAll();

    // available user currencies (for the add form default)
    $c = $pdo->prepare('SELECT code, is_main FROM user_currencies WHERE user_id=? ORDER BY is_main DESC, code');
    $c->execute([$u]);
    $currencies = $c->fetchAll();

    view('settings/basic_incomes', compact('rows','currencies'));
}

// Add new base income or record a raise by same label (auto-closes previous period)
function incomes_add(PDO $pdo){
    verify_csrf(); require_login(); $u=uid();
    $label = trim($_POST['label'] ?? '');
    if ($label==='') return;
    $amount = (float)($_POST['amount'] ?? 0);
    $currency = $_POST['currency'] ?? 'HUF';
    $valid_from = $_POST['valid_from'] ?? date('Y-m-d');

    $pdo->beginTransaction();
    try {
        // close any open-ended record for the same label prior to new valid_from
        $stmt = $pdo->prepare("UPDATE basic_incomes SET valid_to = (DATE ? - INTERVAL '1 day')
                               WHERE user_id=? AND label=? AND (valid_to IS NULL OR valid_to >= ?) AND valid_from < ?");
        $stmt->execute([$valid_from, $u, $label, $valid_from, $valid_from]);

        // insert new row
        $ins = $pdo->prepare('INSERT INTO basic_incomes(user_id,label,amount,currency,valid_from,valid_to) VALUES (?,?,?,?,?,NULL)');
        $ins->execute([$u,$label,$amount,$currency,$valid_from]);
        $pdo->commit();
    } catch (Throwable $e){ $pdo->rollBack(); }
}

function incomes_edit(PDO $pdo){
    verify_csrf(); require_login(); $u=uid();
    $id = (int)($_POST['id'] ?? 0);
    if(!$id) return;

    $label = trim($_POST['label'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $currency = $_POST['currency'] ?? 'HUF';
    $valid_from = $_POST['valid_from'] ?? null;
    $valid_to = $_POST['valid_to'] !== '' ? $_POST['valid_to'] : null;

    $stmt = $pdo->prepare('UPDATE basic_incomes SET label=?, amount=?, currency=?, valid_from=?, valid_to=? WHERE id=? AND user_id=?');
    $stmt->execute([$label,$amount,$currency,$valid_from,$valid_to,$id,$u]);
}

function incomes_delete(PDO $pdo){
    verify_csrf(); require_login(); $u=uid();
    $id = (int)($_POST['id'] ?? 0);
    if(!$id) return;

    // If deleting the most recent record of a label where previous was auto-closed, you may want to reopen it.
    // Simple approach: just delete; advanced reopening can be added later.
    $pdo->prepare('DELETE FROM basic_incomes WHERE id=? AND user_id=?')->execute([$id,$u]);
}
```

---

## View — `/views/settings/basic_incomes.php`
```php
<section class="max-w-4xl mx-auto bg-white rounded-2xl p-6 shadow-glass">
  <div class="flex items-center justify-between">
    <h1 class="text-xl font-semibold">Manage Basic Incomes</h1>
    <a href="/settings" class="text-sm text-accent">← Back to Settings</a>
  </div>

  <div class="mt-6 grid md:grid-cols-2 gap-6">
    <!-- Add / Raise form -->
    <div class="order-2 md:order-1">
      <h2 class="font-medium mb-2">Add income / Record a raise</h2>
      <form method="post" action="/settings/basic-incomes/add" class="grid sm:grid-cols-6 gap-2">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
        <input name="label" class="rounded-xl border-gray-300 sm:col-span-2" placeholder="e.g., Salary" required>
        <input name="amount" type="number" step="0.01" class="rounded-xl border-gray-300 sm:col-span-2" placeholder="Amount" required>
        <select name="currency" class="rounded-xl border-gray-300 sm:col-span-1">
          <?php foreach($currencies as $c): ?>
            <option value="<?= htmlspecialchars($c['code']) ?>" <?= $c['is_main']? 'selected':'' ?>><?= htmlspecialchars($c['code']) ?></option>
          <?php endforeach; if(!count($currencies)): ?>
            <option value="HUF">HUF</option>
          <?php endif; ?>
        </select>
        <input name="valid_from" type="date" value="<?= date('Y-m-d') ?>" class="rounded-xl border-gray-300 sm:col-span-1">
        <button class="bg-gray-900 text-white rounded-xl px-4 sm:col-span-6">Save</button>
      </form>
      <p class="text-xs text-gray-500 mt-2">If an income with the same label exists, its previous period is automatically closed the day before the new start date.</p>
    </div>

    <!-- Current income snapshot by label (latest row) -->
    <div class="order-1 md:order-2">
      <h2 class="font-medium mb-2">Current snapshot</h2>
      <?php
        $latest = [];
        foreach ($rows as $r) {
          $lab = $r['label'];
          if (!isset($latest[$lab])) $latest[$lab] = $r; // rows sorted by valid_from DESC
        }
      ?>
      <ul class="divide-y">
        <?php foreach($latest as $lab=>$r): ?>
          <li class="py-3 flex items-center justify-between">
            <div>
              <div class="font-medium"><?= htmlspecialchars($lab) ?></div>
              <div class="text-xs text-gray-500">Since <?= htmlspecialchars($r['valid_from']) ?> — <?= moneyfmt($r['amount'], $r['currency']) ?></div>
            </div>
          </li>
        <?php endforeach; if(!count($latest)): ?>
          <li class="py-3 text-sm text-gray-500">No basic incomes yet.</li>
        <?php endif; ?>
      </ul>
    </div>
  </div>

  <!-- Full history table -->
  <div class="mt-8 overflow-x-auto">
    <h2 class="font-medium mb-2">History</h2>
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 pr-3">Label</th>
          <th class="py-2 pr-3">Amount</th>
          <th class="py-2 pr-3">Currency</th>
          <th class="py-2 pr-3">Valid From</th>
          <th class="py-2 pr-3">Valid To</th>
          <th class="py-2 pr-3">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr class="border-b">
            <td class="py-2 pr-3 font-medium"><?= htmlspecialchars($r['label']) ?></td>
            <td class="py-2 pr-3 font-medium"><?= moneyfmt($r['amount']) ?></td>
            <td class="py-2 pr-3"><?= htmlspecialchars($r['currency']) ?></td>
            <td class="py-2 pr-3"><?= htmlspecialchars($r['valid_from']) ?></td>
            <td class="py-2 pr-3"><?= htmlspecialchars($r['valid_to'] ?? '—') ?></td>
            <td class="py-2 pr-3">
              <details>
                <summary class="cursor-pointer text-accent">Edit</summary>
                <form class="mt-2 grid sm:grid-cols-6 gap-2" method="post" action="/settings/basic-incomes/edit">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="id" value="<?= $r['id'] ?>" />
                  <input name="label" value="<?= htmlspecialchars($r['label']) ?>" class="rounded-xl border-gray-300 sm:col-span-2">
                  <input name="amount" type="number" step="0.01" value="<?= $r['amount'] ?>" class="rounded-xl border-gray-300 sm:col-span-2">
                  <input name="currency" value="<?= htmlspecialchars($r['currency']) ?>" class="rounded-xl border-gray-300 sm:col-span-1">
                  <input name="valid_from" type="date" value="<?= $r['valid_from'] ?>" class="rounded-xl border-gray-300 sm:col-span-1">
                  <input name="valid_to" type="date" value="<?= $r['valid_to'] ?>" class="rounded-xl border-gray-300 sm:col-span-2">
                  <button class="bg-gray-900 text-white rounded-xl px-4">Save</button>
                </form>
                <form class="mt-2" method="post" action="/settings/basic-incomes/delete" onsubmit="return confirm('Delete this record?')">
                  <input type="hidden" name="csrf" value="<?= csrf_token() ?>" />
                  <input type="hidden" name="id" value="<?= $r['id'] ?>" />
                  <button class="text-red-600">Remove</button>
                </form>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
```

---

## (Optional) Link from Settings index — `/views/settings/index.php`
Add a quick link near the Basic Incomes section header:
```php
<p class="text-sm mt-3"><a class="text-accent" href="/settings/basic-incomes">Manage basic incomes →</a></p>
```


---

## New migration — `/migrations/003_fx_rates.sql`
```sql
-- Stores daily FX rates normalized to a single base (EUR by default)
CREATE TABLE IF NOT EXISTS fx_rates (
  rate_date DATE NOT NULL,
  base_code TEXT NOT NULL DEFAULT 'EUR',
  code      TEXT NOT NULL,
  rate      NUMERIC(20,8) NOT NULL, -- 1 base_code = rate code
  PRIMARY KEY(rate_date, base_code, code)
);
CREATE INDEX IF NOT EXISTS idx_fx_rates_code_date ON fx_rates(code, rate_date);

-- Helpful view to see latest available rate on/before a date
CREATE OR REPLACE VIEW v_fx_latest AS
SELECT DISTINCT ON (code, asof)
  code,
  asof,
  rate
FROM (
  SELECT f.code,
         d::date AS asof,
         (SELECT rate FROM fx_rates fr WHERE fr.code=f.code AND fr.rate_date<=d ORDER BY fr.rate_date DESC LIMIT 1) AS rate
  FROM (
    SELECT DISTINCT code FROM fx_rates
  ) f
  CROSS JOIN LATERAL generate_series(date_trunc('day', CURRENT_DATE - INTERVAL '365 days')::date, CURRENT_DATE + INTERVAL '365 days', INTERVAL '1 day') AS d
) s
WHERE rate IS NOT NULL;
```

---

## New helper — `/src/fx.php`
```php
<?php
/** FX utilities: look up & cache daily FX rates, convert amounts, and handle the
 *  special rule for basic incomes (use 1st-of-month or latest available).
 */

function fx_user_main(PDO $pdo, int $userId): string {
  $q=$pdo->prepare('SELECT code FROM user_currencies WHERE user_id=? AND is_main=true LIMIT 1');
  $q->execute([$userId]);
  return $q->fetchColumn() ?: 'HUF';
}

// Get EUR->CODE for a given date (or latest before). Inserts if fetched.
function fx_get_eur_to(PDO $pdo, string $code, string $date): ?float {
  if (strtoupper($code)==='EUR') return 1.0;
  $q=$pdo->prepare('SELECT rate FROM fx_rates WHERE base_code=\'EUR\' AND code=? AND rate_date<=?::date ORDER BY rate_date DESC LIMIT 1');
  $q->execute([strtoupper($code), $date]);
  $rate=$q->fetchColumn();
  if ($rate) return (float)$rate;
  // try fetch from exchangerate.host single-date endpoint
  $d = $date; // expected YYYY-MM-DD
  $url = "https://api.exchangerate.host/{$d}?base=EUR&symbols=".urlencode(strtoupper($code));
  try {
    $json = @file_get_contents($url);
    if ($json) {
      $data = json_decode($json, true);
      if (isset($data['rates'][strtoupper($code)])) {
        $rate = (float)$data['rates'][strtoupper($code)];
        $ins=$pdo->prepare('INSERT INTO fx_rates(rate_date,base_code,code,rate) VALUES(?::date,\'EUR\',?,?) ON CONFLICT DO NOTHING');
        $ins->execute([$date, strtoupper($code), $rate]);
        return $rate;
      }
    }
  } catch (Throwable $e) { /* ignore, allow null */ }
  return null;
}

// General converter via EUR as pivot: amount FROM->TO on a given date (latest<=date)
function fx_convert(PDO $pdo, float $amount, string $from, string $to, string $date): float {
  $from = strtoupper($from); $to = strtoupper($to);
  if ($from === $to) return $amount;
  // EUR pivot: amt_in_eur = amount / (EUR->FROM); result = amt_in_eur * (EUR->TO)
  $eur_to_from = fx_get_eur_to($pdo, $from, $date);
  $eur_to_to   = fx_get_eur_to($pdo, $to,   $date);
  if (!$eur_to_from || !$eur_to_to) return $amount; // fallback: no conversion
  $amt_eur = $amount / $eur_to_from;
  return $amt_eur * $eur_to_to;
}

// For basic incomes: use rate of the 1st of the target month, or latest available prior to it.
function fx_convert_basic_income(PDO $pdo, float $amount, string $from, string $to, int $year, int $month): float {
  $first = sprintf('%04d-%02d-01', $year, $month);
  return fx_convert($pdo, $amount, $from, $to, $first);
}
```

---

## Patch — include FX helper where needed
In `/public/index.php` after other requires, add:
```php
require __DIR__ . '/../src/fx.php';
```

---

## Patch — Current Month controller uses FX + main currency
Replace `src/controllers/current_month.php` with:
```php
<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../fx.php';

function current_month_controller(PDO $pdo) {
  require_login();
  $u = uid();
  $y = (int)date('Y');
  $m = (int)date('n');
  $first = date('Y-m-01');
  $last  = date('Y-m-t');
  $main  = fx_user_main($pdo, $u);

  // Load transactions
  $stmt = $pdo->prepare("SELECT t.*, c.label AS cat_label FROM transactions t
    LEFT JOIN categories c ON c.id=t.category_id AND c.user_id=t.user_id
    WHERE t.user_id=? AND t.occurred_on BETWEEN ?::date AND ?::date
    ORDER BY t.occurred_on DESC, t.id DESC");
  $stmt->execute([$u,$first,$last]);
  $tx = $stmt->fetchAll();

  // Sums (native + main-currency)
  $sumIn_native=0; $sumOut_native=0; $sumIn_main=0; $sumOut_main=0;
  foreach($tx as $r){
    $amt = (float)$r['amount'];
    $from = $r['currency'] ?: $main; // default to main if null
    $to   = $main;
    $amt_main = fx_convert($pdo, $amt, $from, $to, $r['occurred_on']);
    if ($r['kind']==='income'){ $sumIn_native += $amt;  $sumIn_main  += $amt_main; }
    else                      { $sumOut_native+= $amt;  $sumOut_main += $amt_main; }
  }

  // Basic incomes this month (active window); convert with 1st-of-month rule
  $bi=$pdo->prepare("SELECT amount, currency FROM basic_incomes
                     WHERE user_id=? AND valid_from<=?::date AND (valid_to IS NULL OR valid_to>=?::date)");
  $bi->execute([$u,$last,$first]);
  foreach($bi as $b){
    $sumIn_native += (float)$b['amount'];
    $sumIn_main   += fx_convert_basic_income($pdo, (float)$b['amount'], $b['currency'] ?: $main, $main, $y, $m);
  }

  // Categories for quick add
  $cats = $pdo->prepare('SELECT id,label,kind FROM categories WHERE user_id=? ORDER BY kind,label');
  $cats->execute([$u]); $cats=$cats->fetchAll();

  view('current_month', compact('tx','sumIn_native','sumOut_native','sumIn_main','sumOut_main','cats','y','m','main'));
}
```

---

## Patch — `/views/current_month.php` shows both native & main totals
Replace the top summary card with:
```php
<div class="bg-white rounded-2xl p-5 shadow-glass">
  <h2 class="font-medium">This Month (<?= $y ?>‑<?= str_pad($m,2,'0',STR_PAD_LEFT) ?>)</h2>
  <p class="mt-2 text-sm text-gray-500">Income (main <?= htmlspecialchars($main) ?>): <strong><?= moneyfmt($sumIn_main,$main) ?></strong></p>
  <p class="mt-1 text-sm text-gray-500">Spending (main <?= htmlspecialchars($main) ?>): <strong><?= moneyfmt($sumOut_main,$main) ?></strong></p>
  <p class="mt-1 text-sm">Net (main): <strong><?= moneyfmt($sumIn_main - $sumOut_main,$main) ?></strong></p>
  <p class="mt-3 text-xs text-gray-400">Native totals (sum of entered amounts): Inc <?= moneyfmt($sumIn_native) ?> · Sp <?= moneyfmt($sumOut_native) ?></p>
</div>
```

---

## Patch — Dashboard “Net This Month” in main currency
In `/views/dashboard.php` replace the first card’s PHP with:
```php
<?php require_login(); $u=uid(); require_once __DIR__.'/../config/db.php'; require_once __DIR__.'/../src/fx.php';
  $first = date('Y-m-01'); $last = date('Y-m-t'); $main = fx_user_main($pdo,$u);
  // Transactions
  $tx=$pdo->prepare("SELECT kind, amount, currency, occurred_on FROM transactions WHERE user_id=? AND occurred_on BETWEEN ?::date AND ?::date");
  $tx->execute([$u,$first,$last]); $sumIn=0; $sumOut=0; foreach($tx as $r){ $amt_main=fx_convert($pdo,(float)$r['amount'],$r['currency']?:$main,$main,$r['occurred_on']); if($r['kind']==='income') $sumIn+=$amt_main; else $sumOut+=$amt_main; }
  // Basic incomes
  $y=(int)date('Y'); $m=(int)date('n');
  $bi=$pdo->prepare("SELECT amount,currency FROM basic_incomes WHERE user_id=? AND valid_from<=?::date AND (valid_to IS NULL OR valid_to>=?::date)");
  $bi->execute([$u,$last,$first]); foreach($bi as $b){ $sumIn += fx_convert_basic_income($pdo,(float)$b['amount'],$b['currency']?:$main,$main,$y,$m); }
  $net=$sumIn-$sumOut; ?>
<p class="text-2xl mt-2 font-semibold"><?= moneyfmt($net,$main) ?></p>
```

---

## Patch — Year/Month detail uses FX
In `/src/controllers/years.php`, within `month_detail()`:
```php
require_once __DIR__ . '/../fx.php';
...
$first = sprintf('%04d-%02d-01', $year, $month);
$last  = date('Y-m-t', strtotime($first));
$main  = fx_user_main($pdo, $u);
...
$sumIn_native=0; $sumOut_native=0; $sumIn_main=0; $sumOut_main=0;
foreach($tx as $r){ $amt=(float)$r['amount']; $amt_main=fx_convert($pdo,$amt,$r['currency']?:$main,$main,$r['occurred_on']); if($r['kind']==='income'){ $sumIn_native+=$amt; $sumIn_main+=$amt_main; } else { $sumOut_native+=$amt; $sumOut_main+=$amt_main; } }
// basic incomes
$bi=$pdo->prepare("SELECT amount,currency FROM basic_incomes WHERE user_id=? AND valid_from<=?::date AND (valid_to IS NULL OR valid_to>=?::date)");
$bi->execute([$u,$last,$first]); foreach($bi as $b){ $sumIn_native+=(float)$b['amount']; $sumIn_main+=fx_convert_basic_income($pdo,(float)$b['amount'],$b['currency']?:$main,$main,$year,$month); }
```
And pass `$sumIn_main,$sumOut_main,$sumIn_native,$sumOut_native,$main` to the view via `view(...)`.

In `/views/years/month.php`, update the summary card similarly to the Current Month card.

---

## Notes & behavior
- **Caching**: first time a date/currency is needed, we fetch from exchangerate.host and store in `fx_rates`. Subsequent conversions are offline.
- **Pivot**: we store EUR-based rates; conversions do EUR→FROM and EUR→TO to compute cross-rates.
- **Basic Incomes rule**: rate on the **1st of the month** (or latest prior date if missing/future) via `fx_convert_basic_income()`.
- **Future months**: the helper selects the latest available rate ≤ the 1st day.
- **Privacy/Resilience**: if the API is unreachable or returns no data, we fall back to leaving the amount unconverted (so UI never breaks). You can add admin warnings if desired.
```

