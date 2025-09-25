<?php
session_start();

$root = __DIR__;
$config = require $root . '/config/config.php';
require $root . '/src/helpers.php';
require $root . '/src/auth.php';

$dbConfig = $config['db'] ?? [];
$pdoConnection = null;

if (!empty($dbConfig['host']) && !empty($dbConfig['name'])) {
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $dbConfig['host'],
        $dbConfig['port'] ?? '5432',
        $dbConfig['name']
    );

    try {
        $pdoConnection = new PDO(
            $dsn,
            $dbConfig['user'] ?? null,
            $dbConfig['pass'] ?? null,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (Throwable $e) {
        $pdoConnection = null;
    }
}

global $pdo;
$pdo = $pdoConnection instanceof PDO ? $pdoConnection : null;

if ($pdo instanceof PDO) {
    attempt_remembered_login($pdo);
}

$themeCatalog = available_themes();
$selectedTheme = current_theme_slug();
$themeMeta = $themeCatalog[$selectedTheme] ?? [];

$lightColor = isset($themeMeta['muted']) ? (string)$themeMeta['muted'] : '#f8fbf9';
$darkColorBase = isset($themeMeta['deep']) ? (string)$themeMeta['deep'] : ($themeMeta['base'] ?? '#0f1e18');
$themeColor = $darkColorBase ?: '#0f1e18';
$backgroundColor = $lightColor ?: '#f8fbf9';

$appConfig = $config['app'] ?? [];
$appName = (string)($appConfig['name'] ?? 'MyMoneyMap');
$shortName = (string)($appConfig['short_name'] ?? $appName);
$baseUrl = rtrim($appConfig['base_url'] ?? '/', '/');
$startUrl = $baseUrl === '' ? '/' : ($baseUrl === '/' ? '/' : $baseUrl . '/');

$manifest = [
    'name' => $appName,
    'short_name' => $shortName,
    'start_url' => $startUrl,
    'scope' => $startUrl,
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => $backgroundColor,
    'theme_color' => $themeColor,
    'icons' => [
        [
            'src' => '/android-chrome-192x192.png?v=2',
            'sizes' => '192x192',
            'type' => 'image/png',
        ],
        [
            'src' => '/android-chrome-512x512.png?v=2',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
    ],
];

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
