<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/fx.php';

echo "curl_init(): "; var_dump(function_exists('curl_init'));
echo "allow_url_fopen: "; var_dump((bool)ini_get('allow_url_fopen'));
echo "openssl: "; var_dump(extension_loaded('openssl'));

$day = date('Y-m-01'); // month-start
echo "Testing EUR->USD on $day\n";
$rate = fx_get_eur_to($pdo, 'USD', $day);
var_dump($rate);

echo "Recent rows:\n";
print_r($pdo->query("SELECT rate_date, base_code, code, rate FROM fx_rates ORDER BY rate_date DESC, code LIMIT 5")->fetchAll(PDO::FETCH_ASSOC));
