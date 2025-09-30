<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../fx.php';

function stocks_index(PDO $pdo){ require_login(); $u=uid();
  $base_currency = fx_user_main($pdo, $u);
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

  view('stocks/index', compact('positions','portfolio_cost_basis_main','trades','base_currency','as_of','positions_payload','currency_rates_payload'));
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