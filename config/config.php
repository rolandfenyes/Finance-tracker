<?php
return [
    'db' => [
        'host' => getenv('MM_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('MM_DB_PORT') ?: '5432',
        'name' => getenv('MM_DB_NAME') ?: 'moneymap',
        'user' => getenv('MM_DB_USER') ?: 'rolandcsabafenyes',
        'pass' => getenv('MM_DB_PASS') ?: 'asd',
    ],
    'security' => [
        'data_key' => getenv('MM_DATA_KEY') ?: '',
    ],
    'app' => [
        'name' => 'MyMoneyMap',
        'base_url' => '/', // if hosted in subfolder, e.g. '/moneymap/'
        'session_name' => 'moneymap_sess',
        'default_locale' => 'en',
        'locales' => [
            'en' => 'English',
            'hu' => 'Magyar',
            'es' => 'Español',
        ],
    ],
    'stocks' => [
        'provider' => getenv('STOCKS_PROVIDER') ?: 'null',
        'refresh_seconds' => (int)(getenv('STOCKS_REFRESH_SECONDS') ?: 10),
        'overview_cache_seconds' => (int)(getenv('STOCKS_OVERVIEW_CACHE_SECONDS') ?: 20),
        'overview_cache_dir' => getenv('STOCKS_OVERVIEW_CACHE_DIR') ?: __DIR__ . '/../storage/cache/stocks',
        'providers' => [
            'finnhub' => [
                'api_key' => getenv('FINNHUB_API_KEY') ?: '',
                'base_url' => getenv('FINNHUB_BASE_URL') ?: 'https://finnhub.io/api/v1',
            ],
        ],
    ],
];
