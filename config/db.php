<?php
$config = require __DIR__ . '/config.php';
$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $config['db']['host'], $config['db']['port'], $config['db']['name']);
try {
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    die('DB connection failed: ' . htmlspecialchars($e->getMessage()));
}