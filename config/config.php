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
