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

  $netAmount = $amount - $fee;
  if ($netAmount <= 0) { return; }

  $quantity = $price > 0 ? round($netAmount / $price, 6) : 0;
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

  $netAmount = $amount - $fee;
  if ($netAmount <= 0) { return; }

  $qty = $price > 0 ? round($netAmount / $price, 6) : 0;
  if ($qty <= 0) { return; }

  $q=$pdo->prepare('SELECT qty FROM v_stock_positions WHERE user_id=? AND symbol=?');
  $q->execute([$u,$symbol]);
  $held=(float)($q->fetchColumn() ?: 0);
  if ($qty > $held) {
    $qty = $held;
  }
  if ($qty <= 0) { return; }

  $amount = round($qty * $price + $fee, 4);

  $pdo->prepare('INSERT INTO stock_trades(user_id,symbol,trade_on,side,quantity,price,amount,fee,currency) VALUES(?,?,?,?,?,?,?,?,?)')
      ->execute([$u, $symbol, $tradeOn, 'sell', $qty, $price, $amount, $fee, $currency]);
}

function trade_delete(PDO $pdo){ verify_csrf(); require_login(); $u=uid();
  $pdo->prepare('DELETE FROM stock_trades WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$u]);
}

function stocks_api_quotes(PDO $pdo){
  require_login();

  $symbolsParam = $_GET['symbols'] ?? '';
  $rawSymbols = [];
  if (is_array($symbolsParam)) {
    $rawSymbols = $symbolsParam;
  } elseif (is_string($symbolsParam)) {
    $rawSymbols = preg_split('/[\s,]+/', $symbolsParam) ?: [];
  }

  $symbols = [];
  foreach ($rawSymbols as $raw) {
    $symbol = strtoupper(trim((string)$raw));
    if ($symbol === '' || !preg_match('/^[A-Z0-9\.\-]{1,15}$/', $symbol)) {
      continue;
    }
    $symbols[] = $symbol;
  }

  $symbols = array_values(array_unique($symbols));

  if (empty($symbols)) {
    json_response(['success' => true, 'quotes' => new stdClass()]);
  }

  $chunks = array_chunk($symbols, 8);
  $results = [];
  $hadSuccess = false;

  foreach ($chunks as $group) {
    $response = stocks_yahoo_request('/v7/finance/quote', [
      'symbols' => implode(',', $group),
      'lang' => 'en-US',
      'region' => 'US',
      'corsDomain' => 'finance.yahoo.com',
      'formatted' => 'false',
    ], 30);

    if (!is_array($response)) {
      continue;
    }

    $hadSuccess = true;
    $items = $response['quoteResponse']['result'] ?? [];
    if (!is_array($items)) {
      continue;
    }

    foreach ($items as $item) {
      if (!is_array($item) || empty($item['symbol'])) {
        continue;
      }
      $key = strtoupper((string)$item['symbol']);
      $results[$key] = $item;
    }
  }

  if (!$hadSuccess && empty($results)) {
    json_error(__('Live quote service is unavailable right now.'), 502);
  }

  json_response([
    'success' => true,
    'quotes' => $results,
  ]);
}

function stocks_api_history(PDO $pdo){
  require_login();

  $symbol = strtoupper(trim((string)($_GET['symbol'] ?? '')));
  if ($symbol === '' || !preg_match('/^[A-Z0-9\.\-]{1,15}$/', $symbol)) {
    json_error(__('Please provide a valid stock symbol.'), 422);
  }

  $range = strtolower(trim((string)($_GET['range'] ?? '1mo')));
  $interval = strtolower(trim((string)($_GET['interval'] ?? '1d')));

  $allowedRanges = ['1d','5d','1mo','3mo','6mo','1y','2y','5y','10y','ytd','max'];
  $allowedIntervals = ['1m','2m','5m','15m','30m','60m','90m','1h','1d','5d','1wk','1mo','3mo'];

  if (!in_array($range, $allowedRanges, true)) {
    $range = '1mo';
  }

  if (!in_array($interval, $allowedIntervals, true)) {
    $interval = '1d';
  }

  $response = stocks_yahoo_request('/v8/finance/chart/' . rawurlencode($symbol), [
    'range' => $range,
    'interval' => $interval,
    'includePrePost' => 'false',
    'events' => 'div,splits',
    'lang' => 'en-US',
    'region' => 'US',
    'corsDomain' => 'finance.yahoo.com',
    'formatted' => 'false',
  ], 900);

  if (!is_array($response) || empty($response['chart']['result'])) {
    json_error(__('Price history is unavailable right now.'), 502);
  }

  $result = $response['chart']['result'][0] ?? null;
  if (!is_array($result)) {
    json_response(['success' => true, 'history' => null]);
  }

  $timestamps = $result['timestamp'] ?? [];
  $quoteSets = $result['indicators']['quote'][0] ?? [];
  $closesRaw = isset($quoteSets['close']) && is_array($quoteSets['close']) ? $quoteSets['close'] : [];

  $filteredTimestamps = [];
  $filteredCloses = [];

  foreach ($timestamps as $index => $ts) {
    $close = $closesRaw[$index] ?? null;
    if (!is_numeric($ts) || !is_numeric($close)) {
      continue;
    }
    $filteredTimestamps[] = (int)$ts;
    $filteredCloses[] = (float)$close;
  }

  if (empty($filteredTimestamps) || empty($filteredCloses)) {
    json_response(['success' => true, 'history' => null]);
  }

  $meta = isset($result['meta']) && is_array($result['meta']) ? $result['meta'] : [];

  json_response([
    'success' => true,
    'history' => [
      'symbol' => $symbol,
      'currency' => isset($meta['currency']) ? (string)$meta['currency'] : null,
      'timestamps' => $filteredTimestamps,
      'closes' => $filteredCloses,
    ],
  ]);
}

function stocks_yahoo_request(string $path, array $params = [], int $ttlSeconds = 45): ?array {
  $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
  $cacheKey = sha1($path . '?' . $query);
  $cached = stocks_yahoo_cache_fetch($cacheKey);
  $now = time();

  if ($cached && isset($cached['data'], $cached['expires_at']) && $cached['expires_at'] >= $now) {
    return $cached['data'];
  }

  $endpoints = [
    'https://query1.finance.yahoo.com' . $path,
    'https://query2.finance.yahoo.com' . $path,
  ];

  $sessionCandidates = [];
  $initialSession = stocks_yahoo_get_session(false);
  if ($initialSession) {
    $sessionCandidates[] = $initialSession;
  }

  $sessionCandidates[] = '__refresh__';
  $sessionCandidates[] = null; // final attempt without crumb/cookie

  foreach ($sessionCandidates as $candidate) {
    $session = $candidate;
    if ($candidate === '__refresh__') {
      $session = stocks_yahoo_get_session(true);
      if (!$session) {
        continue;
      }
    }

    if ($session === null && empty($endpoints)) {
      continue;
    }

    $queryParams = $params;
    $headers = [];

    if (is_array($session) && !empty($session['crumb'])) {
      $queryParams['crumb'] = $session['crumb'];
    }

    if (is_array($session) && !empty($session['cookie'])) {
      $headers[] = 'Cookie: ' . $session['cookie'];
    }

    $queryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

    foreach ($endpoints as $endpoint) {
      $url = $endpoint . ($queryString ? ('?' . $queryString) : '');
      $status = null;
      $response = stocks_http_get_json($url, ['headers' => $headers], $status);
      if (is_array($response)) {
        if ($candidate === '__refresh__' && $session && isset($session['crumb'])) {
          stocks_yahoo_session_store($session);
        }
        stocks_yahoo_cache_store($cacheKey, $response, $ttlSeconds);
        return $response;
      }

      if (in_array($status, [401, 403], true)) {
        if ($session) {
          stocks_yahoo_session_forget();
        }
        break; // move to next session candidate
      }
    }
  }

  if ($cached && isset($cached['data'])) {
    return $cached['data'];
  }

  return null;
}

function stocks_yahoo_cache_dir(): ?string {
  static $dir = null;
  if ($dir !== null) {
    return $dir;
  }

  $candidate = __DIR__ . '/../storage/cache/yahoo';
  if (!is_dir($candidate)) {
    @mkdir($candidate, 0775, true);
  }

  if (!is_dir($candidate) || !is_writable($candidate)) {
    $dir = null;
  } else {
    $resolved = realpath($candidate);
    $dir = $resolved !== false ? $resolved : $candidate;
  }

  return $dir;
}

function stocks_yahoo_cache_fetch(string $key): ?array {
  $dir = stocks_yahoo_cache_dir();
  if ($dir === null) {
    return null;
  }

  $file = $dir . '/' . $key . '.json';
  if (!is_file($file)) {
    return null;
  }

  $raw = @file_get_contents($file);
  if (!is_string($raw) || $raw === '') {
    return null;
  }

  $decoded = json_decode($raw, true);
  return is_array($decoded) ? $decoded : null;
}

function stocks_yahoo_cache_store(string $key, array $data, int $ttlSeconds): void {
  $dir = stocks_yahoo_cache_dir();
  if ($dir === null) {
    return;
  }

  $payload = [
    'stored_at' => time(),
    'expires_at' => time() + max(0, (int)$ttlSeconds),
    'data' => $data,
  ];

  @file_put_contents(
    $dir . '/' . $key . '.json',
    json_encode($payload, JSON_UNESCAPED_SLASHES),
    LOCK_EX
  );
}

function stocks_yahoo_session_path(): ?string {
  $dir = stocks_yahoo_cache_dir();
  if ($dir === null) {
    return null;
  }

  return $dir . '/session.json';
}

function stocks_yahoo_session_load(): ?array {
  $path = stocks_yahoo_session_path();
  if ($path === null || !is_file($path)) {
    return null;
  }

  $raw = @file_get_contents($path);
  if (!is_string($raw) || $raw === '') {
    return null;
  }

  $decoded = json_decode($raw, true);
  if (!is_array($decoded)) {
    return null;
  }

  $expiresAt = isset($decoded['expires_at']) ? (int)$decoded['expires_at'] : 0;
  if ($expiresAt !== 0 && $expiresAt <= time()) {
    stocks_yahoo_session_forget();
    return null;
  }

  return $decoded;
}

function stocks_yahoo_session_store(array $session): void {
  $path = stocks_yahoo_session_path();
  if ($path === null) {
    return;
  }

  $payload = [
    'cookie' => (string)($session['cookie'] ?? ''),
    'crumb' => (string)($session['crumb'] ?? ''),
    'fetched_at' => time(),
    'expires_at' => time() + 3600,
  ];

  if ($payload['cookie'] === '' || $payload['crumb'] === '') {
    return;
  }

  @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function stocks_yahoo_session_forget(): void {
  $path = stocks_yahoo_session_path();
  if ($path === null || !is_file($path)) {
    return;
  }

  @unlink($path);
}

function stocks_yahoo_get_session(bool $forceRefresh = false): ?array {
  if (!$forceRefresh) {
    $cached = stocks_yahoo_session_load();
    if ($cached) {
      return $cached;
    }
  }

  $fresh = stocks_yahoo_refresh_session();
  if ($fresh) {
    stocks_yahoo_session_store($fresh);
    return $fresh;
  }

  return null;
}

function stocks_yahoo_refresh_session(): ?array {
  $url = 'https://query1.finance.yahoo.com/v1/test/getcrumb';
  $headers = [
    'Accept: text/plain',
    'Accept-Encoding: gzip, deflate, br',
    'User-Agent: MyMoneyMap/1.0 (+https://mymoneymap.app)'
  ];

  $cookie = '';
  $crumb = '';
  $status = null;

  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $error = curl_errno($ch);
    curl_close($ch);

    if ($response === false || $error !== 0 || $status >= 400) {
      return null;
    }

    $rawHeaders = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    $cookie = stocks_yahoo_parse_cookies($rawHeaders);
    $crumb = trim($body);
  } else {
    $context = stream_context_create([
      'http' => [
        'method' => 'GET',
        'header' => implode("\r\n", $headers),
        'timeout' => 10,
        'ignore_errors' => true,
      ],
    ]);

    $handle = @fopen($url, 'rb', false, $context);
    if ($handle === false) {
      return null;
    }

    $metadata = stream_get_meta_data($handle);
    $wrapper = isset($metadata['wrapper_data']) && is_array($metadata['wrapper_data']) ? $metadata['wrapper_data'] : [];
    foreach ($wrapper as $headerLine) {
      if (preg_match('/^HTTP\/\S+\s+(\d+)/i', $headerLine, $matches)) {
        $status = (int)$matches[1];
      }
    }

    $body = stream_get_contents($handle);
    fclose($handle);

    if ($body === false || ($status !== null && $status >= 400)) {
      return null;
    }

    $cookie = stocks_yahoo_parse_cookies($wrapper);
    $crumb = trim($body);
  }

  if ($cookie === '' || $crumb === '') {
    return null;
  }

  return [
    'cookie' => $cookie,
    'crumb' => $crumb,
  ];
}

function stocks_yahoo_parse_cookies($headers): string {
  $lines = [];
  if (is_string($headers)) {
    $lines = preg_split('/\r?\n/', $headers) ?: [];
  } elseif (is_array($headers)) {
    $lines = $headers;
  }

  $cookies = [];
  foreach ($lines as $line) {
    if (!is_string($line)) {
      continue;
    }
    if (stripos($line, 'Set-Cookie:') !== 0) {
      continue;
    }
    $parts = explode(':', $line, 2);
    if (count($parts) < 2) {
      continue;
    }
    $cookiePart = trim($parts[1]);
    $segments = explode(';', $cookiePart);
    $cookieValue = trim($segments[0] ?? '');
    if ($cookieValue !== '') {
      $cookies[] = $cookieValue;
    }
  }

  return implode('; ', $cookies);
}

function stocks_http_get_json(string $url, array $options = [], ?int &$status = null): ?array {
  $status = null;
  $baseHeaders = [
    'Accept: application/json',
    'Accept-Encoding: gzip, deflate, br',
    'User-Agent: MyMoneyMap/1.0 (+https://mymoneymap.app)'
  ];

  $extraHeaders = [];
  if (!empty($options['headers']) && is_array($options['headers'])) {
    foreach ($options['headers'] as $header) {
      if (is_string($header) && $header !== '') {
        $extraHeaders[] = $header;
      }
    }
  }

  $headers = array_merge($baseHeaders, $extraHeaders);
  $timeout = isset($options['timeout']) ? max(1, (int)$options['timeout']) : 10;
  $connectTimeout = isset($options['connect_timeout']) ? max(1, (int)$options['connect_timeout']) : 5;

  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_errno($ch);
    curl_close($ch);

    if ($body === false || $error !== 0 || $status >= 400) {
      return null;
    }

    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : null;
  }

  $context = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' => implode("\r\n", $headers),
      'timeout' => $timeout,
      'ignore_errors' => true,
    ],
  ]);

  $handle = @fopen($url, 'rb', false, $context);
  if (!$handle) {
    return null;
  }

  $metadata = stream_get_meta_data($handle);
  $wrapper = isset($metadata['wrapper_data']) && is_array($metadata['wrapper_data'])
    ? $metadata['wrapper_data']
    : [];
  $encoding = '';
  foreach ($wrapper as $headerLine) {
    if (preg_match('/^HTTP\/\S+\s+(\d+)/i', $headerLine, $matches)) {
      $status = (int)$matches[1];
    }
    if (stripos($headerLine, 'Content-Encoding:') === 0) {
      $parts = explode(',', substr($headerLine, strlen('Content-Encoding:')));
      $encoding = strtolower(trim($parts[0] ?? ''));
    }
  }

  $body = stream_get_contents($handle);
  fclose($handle);

  if ($body === false || ($status !== null && $status >= 400)) {
    return null;
  }

  if ($encoding) {
    $decodedBody = stocks_decode_body($body, $encoding);
    if ($decodedBody !== null) {
      $body = $decodedBody;
    }
  }

  $decoded = json_decode($body, true);
  return is_array($decoded) ? $decoded : null;
}

function stocks_decode_body(string $body, string $encoding): ?string {
  $normalized = strtolower(trim($encoding));

  if ($normalized === 'gzip') {
    $decoded = @gzdecode($body);
    return $decoded === false ? null : $decoded;
  }

  if ($normalized === 'deflate') {
    $decoded = @gzinflate($body);
    if ($decoded !== false) {
      return $decoded;
    }
    $decoded = @gzuncompress($body);
    return $decoded === false ? null : $decoded;
  }

  if ($normalized === 'br' && function_exists('brotli_uncompress')) {
    $decoded = @brotli_uncompress($body);
    return $decoded === false ? null : $decoded;
  }

  return $body;
}
