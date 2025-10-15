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
        'url' => getenv('MM_APP_URL') ?: 'http://localhost:8080',
        'session_name' => 'moneymap_sess',
        'default_locale' => 'en',
        'locales' => [
            'en' => 'English',
            'hu' => 'Magyar',
            'es' => 'Español',
        ],
    ],
    'mail' => [
        'transport' => getenv('MM_MAIL_TRANSPORT') ?: 'log',
        'from_email' => getenv('MM_MAIL_FROM_ADDRESS') ?: 'no-reply@mymoneymap.local',
        'from_name' => getenv('MM_MAIL_FROM_NAME') ?: 'MyMoneyMap',
        'reply_to' => getenv('MM_MAIL_REPLY_TO') ?: null,
        'smtp' => [
            'host' => getenv('MM_MAIL_SMTP_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('MM_MAIL_SMTP_PORT') ?: 1025),
            'username' => getenv('MM_MAIL_SMTP_USER') ?: null,
            'password' => getenv('MM_MAIL_SMTP_PASS') ?: null,
            'encryption' => getenv('MM_MAIL_SMTP_ENCRYPTION') ?: '', // '', 'tls', or 'ssl'
            'timeout' => (int)(getenv('MM_MAIL_SMTP_TIMEOUT') ?: 15),
        ],
        'log' => [
            'path' => getenv('MM_MAIL_LOG_PATH') ?: __DIR__ . '/../storage/logs/mail.log',
        ],
    ],
    'stocks' => [
        'provider' => getenv('STOCKS_PROVIDER') ?: 'finnhub',
        'refresh_seconds' => (int)(getenv('STOCKS_REFRESH_SECONDS') ?: 10),
        'overview_cache_seconds' => (int)(getenv('STOCKS_OVERVIEW_CACHE_SECONDS') ?: 20),
        'overview_cache_dir' => getenv('STOCKS_OVERVIEW_CACHE_DIR') ?: __DIR__ . '/../storage/cache/stocks',
        'performance_log' => getenv('STOCKS_PERFORMANCE_LOG') ?: __DIR__ . '/../storage/logs/stocks_performance.log',
        'providers' => [
            'finnhub' => [
                'api_key' => getenv('FINNHUB_API_KEY') ?: 'd3edaspr01qrd38ttv9gd3edaspr01qrd38ttva0',
                'base_url' => getenv('FINNHUB_BASE_URL') ?: 'https://finnhub.io/api/v1',
            ],
        ],
    ],
];
