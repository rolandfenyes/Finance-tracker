<?php
// src/fx.php

function fx_user_main(PDO $pdo, int $userId): string {
  $q=$pdo->prepare('SELECT code FROM user_currencies WHERE user_id=? AND is_main=true LIMIT 1');
  $q->execute([$userId]);
  return $q->fetchColumn() ?: 'HUF';
}

/* ---------- tiny HTTP helper (curl with fallback) ---------- */
/* ---------- tiny HTTP helper (uses cURL if present, else fopen) ---------- */
function _fx_http_get(string $url, ?int &$httpCode = null, ?string &$err = null): ?string {
  $httpCode = null; $err = null;

  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CONNECTTIMEOUT => 5,
      CURLOPT_TIMEOUT        => 8,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_USERAGENT      => 'MoneyMap-FX/1.1'
    ]);
    $out = curl_exec($ch);
    if ($out === false) { $err = curl_error($ch); }
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($out !== false && $httpCode === 200) ? $out : null;
  }

  $ctx = stream_context_create([
    'http' => ['timeout' => 8, 'user_agent' => 'MoneyMap-FX/1.1']
  ]);
  $out = @file_get_contents($url, false, $ctx);
  // best-effort http code extraction
  if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
    $httpCode = (int)$m[1];
  }
  if ($out === false) { $err = 'fopen failed'; }
  return ($out !== false && ($httpCode === null || $httpCode === 200)) ? $out : null;
}

/* ---------- fetch EUR->CODE for date with caching & multi-provider fallback ---------- */
function fx_get_eur_to(PDO $pdo, string $code, string $date): ?float {
  $code = strtoupper($code);
  if ($code === 'EUR') return 1.0;

  // 0) try cache (latest <= date)
  $q=$pdo->prepare("
    SELECT rate FROM fx_rates
     WHERE base_code='EUR' AND code=? AND rate_date<=?::date
     ORDER BY rate_date DESC LIMIT 1
  ");
  $q->execute([$code, $date]);
  $rate = $q->fetchColumn();
  if ($rate) return (float)$rate;

  // Providers to try (in order)
  $providers = [
    // exchangerate.host single-date
    function(string $date, string $code) {
      $url = "https://api.exchangerate.host/".rawurlencode($date)."?base=EUR&symbols=".rawurlencode($code);
      $json = _fx_http_get($url, $http, $err);
      if (!$json) { error_log("[FX] exchangerate.host miss http=$http err=$err url=$url"); return null; }
      $data = json_decode($json, true);
      if (isset($data['rates'][$code]) && is_numeric($data['rates'][$code])) {
        return ['rate' => (float)$data['rates'][$code], 'date' => $data['date'] ?? $date];
      }
      return null;
    },
    // Frankfurter (ECB) single-date
    function(string $date, string $code) {
      $url = "https://api.frankfurter.app/".rawurlencode($date)."?from=EUR&to=".rawurlencode($code);
      $json = _fx_http_get($url, $http, $err);
      if (!$json) { error_log("[FX] frankfurter miss http=$http err=$err url=$url"); return null; }
      $data = json_decode($json, true);
      if (isset($data['rates'][$code]) && is_numeric($data['rates'][$code])) {
        return ['rate' => (float)$data['rates'][$code], 'date' => $data['date'] ?? $date];
      }
      return null;
    },
    // exchangerate.host latest as last resort (used for future dates/weekends)
    function(string $date, string $code) {
      $url = "https://api.exchangerate.host/latest?base=EUR&symbols=".rawurlencode($code);
      $json = _fx_http_get($url, $http, $err);
      if (!$json) { error_log("[FX] exchangerate.host latest miss http=$http err=$err url=$url"); return null; }
      $data = json_decode($json, true);
      if (isset($data['rates'][$code]) && is_numeric($data['rates'][$code])) {
        return ['rate' => (float)$data['rates'][$code], 'date' => $date]; // stamp on requested date
      }
      return null;
    },
  ];

  foreach ($providers as $provider) {
    $res = $provider($date, $code);
    if ($res && isset($res['rate'])) {
      $storeDate = $res['date'] ?: $date;
      _fx_store($pdo, $storeDate, $code, (float)$res['rate']);
      return (float)$res['rate'];
    }
  }

  // still nothing
  error_log("[FX] all providers failed for EUR->$code on $date");
  return null;
}


/* ---------- store a rate row idempotently ---------- */
function _fx_store(PDO $pdo, string $date, string $code, float $rate): void {
  $stmt=$pdo->prepare("
    INSERT INTO fx_rates(rate_date,base_code,code,rate)
    VALUES (?::date,'EUR',?,?)
    ON CONFLICT (rate_date,base_code,code) DO UPDATE SET rate=EXCLUDED.rate
  ");
  $stmt->execute([$date, strtoupper($code), $rate]);
}


/* ---------- generic converter using EUR as pivot ---------- */
function fx_convert(PDO $pdo, float $amount, string $from, string $to, string $date): float {
  $from = strtoupper($from); $to = strtoupper($to);
  if ($from === $to) return $amount;

  $eur_to_from = fx_get_eur_to($pdo, $from, $date);
  $eur_to_to   = fx_get_eur_to($pdo, $to,   $date);

  if (!$eur_to_from || !$eur_to_to) return $amount; // graceful fallback
  $amt_eur = $amount / $eur_to_from;
  return $amt_eur * $eur_to_to;
}

/* ---------- basic income conversion: always 1st-of-month (or latest<=) ---------- */
function fx_convert_basic_income(PDO $pdo, float $amount, string $from, string $to, int $year, int $month): float {
  $first = sprintf('%04d-%02d-01', $year, $month);
  return fx_convert($pdo, $amount, $from, $to, $first);
}

/* ---------- prefetch helper when a currency is first added ---------- */
function fx_prefetch_month_starts(PDO $pdo, string $code, int $monthsBack = 18, int $monthsFwd = 6): void {
  $code = strtoupper($code);
  if ($code === 'EUR') return;
  $start = new DateTime('first day of this month');
  // backfill
  for ($i=0; $i<=$monthsBack; $i++) {
    $d = (clone $start)->modify("-$i months")->format('Y-m-d');
    fx_get_eur_to($pdo, $code, $d);
  }
  // small forward cushion (future BI): fetch latest and stamp it on those firsts
  $latest = fx_get_eur_to($pdo, $code, $start->format('Y-m-d')) ?: null;
  if ($latest) {
    for ($j=1; $j<=$monthsFwd; $j++) {
      $d = (clone $start)->modify("+$j months")->format('Y-m-01');
      _fx_store($pdo, $d, $code, $latest);
    }
  }
}

// FROM->TO multiplicative rate for a given date (latest<=date). Returns 1.0 if same currency.
function fx_rate_from_to(PDO $pdo, string $from, string $to, string $date): ?float {
  $from = strtoupper($from); $to = strtoupper($to);
  if ($from === $to) return 1.0;

  $eur_to_from = fx_get_eur_to($pdo, $from, $date); // may auto-fetch/cache
  $eur_to_to   = fx_get_eur_to($pdo, $to,   $date);
  if (!$eur_to_from || !$eur_to_to) return null;
  // amount_in_to = amount_in_from * (EUR->TO / EUR->FROM)
  return $eur_to_to / $eur_to_from;
}
