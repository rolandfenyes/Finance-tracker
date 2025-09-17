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