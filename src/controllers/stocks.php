<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../fx.php';

function stocks_index(PDO $pdo){ require_login(); $u=uid();
  $currencyParam = strtoupper(trim($_GET['currency'] ?? ''));
  $base_currency = preg_match('/^[A-Z]{3}$/', $currencyParam) ? $currencyParam : 'USD';

  $uc = $pdo->prepare('SELECT code, is_main FROM user_currencies WHERE user_id=? ORDER BY is_main DESC, code');
  $uc->execute([$u]);
  $currencies = array_map(function ($row) {
    return [
      'code' => strtoupper($row['code'] ?? ''),
      'is_main' => !empty($row['is_main'])
    ];
  }, $uc->fetchAll(PDO::FETCH_ASSOC));

  $codes = array_column($currencies, 'code');
  if (!in_array('USD', $codes, true)) {
    array_unshift($currencies, ['code' => 'USD', 'is_main' => empty($currencies)]);
    $codes = array_column($currencies, 'code');
  }

  if (!in_array($base_currency, $codes, true)) {
    $base_currency = 'USD';
  }
  $as_of = date('Y-m-d');

  $positions = stocks_positions_summary($pdo, $u, $base_currency, $as_of);
  $portfolio_cost_basis_main = array_sum(array_map(fn($p)=>$p['cost_main'], $positions));

  $currency_rates = [];
  foreach ($positions as $p) {
    $currency_rates[$p['currency']] = $p['rate_to_main'];
  }

  // Recent trades
  $t=$pdo->prepare('SELECT * FROM stock_trades WHERE user_id=? ORDER BY trade_on DESC, id DESC LIMIT 100');
  $t->execute([$u]); $trades=$t->fetchAll();

  $positions_payload = json_encode($positions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
  $currency_rates_payload = json_encode($currency_rates, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

  view('stocks/index', compact('positions','portfolio_cost_basis_main','trades','base_currency','as_of','positions_payload','currency_rates_payload','currencies'));
}

function trade_buy(PDO $pdo){
  verify_csrf(); require_login(); $u=uid();

  $symbol = strtoupper(trim($_POST['symbol'] ?? ''));
  $price = round((float)($_POST['price'] ?? 0), 4);
  $amount = round(max(0, (float)($_POST['amount'] ?? 0)), 4);
  $fee = round(max(0, isset($_POST['fee']) ? (float)$_POST['fee'] : 0), 4);
  $currency = strtoupper(trim($_POST['currency'] ?? 'USD'));
  if (!preg_match('/^[A-Z]{3}$/', $currency)) { $currency = 'USD'; }
  $tradeOn = !empty($_POST['trade_on']) ? $_POST['trade_on'] : date('Y-m-d');

  if ($price <= 0 || $amount <= 0 || !$symbol) { return; }

  $quantity = $price > 0 ? round($amount / $price, 6) : 0;
  if ($quantity <= 0) { return; }

  $stmt=$pdo->prepare('INSERT INTO stock_trades(user_id,symbol,trade_on,side,quantity,price,amount,fee,currency) VALUES(?,?,?,?,?,?,?,?,?)');
  $stmt->execute([
    $u,
    $symbol,
    $tradeOn,
    'buy',
    $quantity,
    $price,
    $amount,
    $fee,
    $currency
  ]);
}

function trade_sell(PDO $pdo){
  verify_csrf(); require_login(); $u=uid();
  // Optional naive check: prevent selling more than held (best-effort; DB view handles net qty anyway)
  $symbol = strtoupper(trim($_POST['symbol'] ?? ''));
  $price = round((float)($_POST['price'] ?? 0), 4);
  $amount = round(max(0, (float)($_POST['amount'] ?? 0)), 4);
  $fee = round(max(0, isset($_POST['fee']) ? (float)$_POST['fee'] : 0), 4);
  $currency = strtoupper(trim($_POST['currency'] ?? 'USD'));
  if (!preg_match('/^[A-Z]{3}$/', $currency)) { $currency = 'USD'; }
  $tradeOn = !empty($_POST['trade_on']) ? $_POST['trade_on'] : date('Y-m-d');

  if ($price <= 0 || $amount <= 0 || !$symbol) { return; }

  $qty = $price > 0 ? round($amount / $price, 6) : 0;
  if ($qty <= 0) { return; }

  $q=$pdo->prepare('SELECT qty FROM v_stock_positions WHERE user_id=? AND symbol=?');
  $q->execute([$u,$symbol]);
  $held=(float)($q->fetchColumn() ?: 0);
  if ($qty > $held) {
    $qty = $held;
  }
  if ($qty <= 0) { return; }

  $amount = round($qty * $price, 4);

  $pdo->prepare('INSERT INTO stock_trades(user_id,symbol,trade_on,side,quantity,price,amount,fee,currency) VALUES(?,?,?,?,?,?,?,?,?)')
      ->execute([$u, $symbol, $tradeOn, 'sell', $qty, $price, $amount, $fee, $currency]);
}

function trade_delete(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $pdo->prepare('DELETE FROM stock_trades WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$u]);
}