<?php
function view(string $name, array $data = []) {
    global $pdo;            // <-- bring the PDO from global scope into this function
    extract($data);         // makes $row, etc. available to the view
    include __DIR__ . '/../views/layout/header.php';
    include __DIR__ . '/../views/' . $name . '.php';
    include __DIR__ . '/../views/layout/footer.php';
}


function redirect(string $to) {
    header('Location: ' . $to);
    exit;
}

function json_response($data, int $status = 200): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $status = 400): void
{
    json_response(['success' => false, 'error' => $message], $status);
}

function pii_crypto_is_configured(): bool
{
    try {
        return pii_encryption_key() !== '';
    } catch (Throwable $e) {
        return false;
    }
}

function pii_encryption_key(): string
{
    static $key;

    if ($key !== null) {
        return $key;
    }

    $config = app_config('security') ?? [];
    $raw = (string)($config['data_key'] ?? '');

    if ($raw === '' && isset($_ENV['MM_DATA_KEY'])) {
        $raw = (string)$_ENV['MM_DATA_KEY'];
    }

    if ($raw === '') {
        $storageDir = __DIR__ . '/../storage';
        $keyFile = $storageDir . '/data_key.php';

        if (is_file($keyFile) && is_readable($keyFile)) {
            $stored = require $keyFile;
            if (is_string($stored)) {
                $raw = $stored;
            }
        }

        if ($raw === '') {
            if (!is_dir($storageDir)) {
                @mkdir($storageDir, 0700, true);
            }

            if (!is_dir($storageDir) || !is_writable($storageDir)) {
                throw new RuntimeException('Sensitive data encryption key missing and storage directory is not writable.');
            }

            $generated = base64_encode(random_bytes(32));
            $raw = $generated;
            $content = "<?php\nreturn " . var_export($raw, true) . ";\n";

            if (file_put_contents($keyFile, $content, LOCK_EX) === false) {
                throw new RuntimeException('Failed to persist generated encryption key.');
            }

            @chmod($keyFile, 0600);
        }
    }

    $decoded = base64_decode($raw, true);
    $material = $decoded !== false && $decoded !== '' ? $decoded : $raw;

    if (function_exists('sodium_crypto_secretbox')) {
        $key = substr(hash('sha256', $material, true), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    } else {
        $key = substr(hash('sha256', $material, true), 0, 32);
    }

    return $key;
}

function pii_encrypt(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    if ($value === '') {
        return '';
    }

    $key = pii_encryption_key();

    if (function_exists('sodium_crypto_secretbox')) {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($value, $nonce, $key);
        return base64_encode($nonce . $cipher);
    }

    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('OpenSSL extension is required when Sodium is unavailable to encrypt sensitive data.');
    }

    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

    if ($cipher === false) {
        throw new RuntimeException('Unable to encrypt sensitive data.');
    }

    return base64_encode($iv . $tag . $cipher);
}

function pii_decrypt(?string $value, ?bool &$wasEncrypted = null): ?string
{
    if ($value === null || $value === '') {
        $wasEncrypted = true;
        return $value;
    }

    $data = base64_decode($value, true);
    if ($data === false) {
        $wasEncrypted = false;
        return $value;
    }

    if (!pii_crypto_is_configured()) {
        $wasEncrypted = false;
        return $value;
    }

    try {
        $key = pii_encryption_key();
    } catch (Throwable $e) {
        $wasEncrypted = false;
        return $value;
    }

    if (function_exists('sodium_crypto_secretbox_open')) {
        if (strlen($data) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            $wasEncrypted = false;
            return $value;
        }
        $nonce = substr($data, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($data, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
        if ($plain === false) {
            $wasEncrypted = false;
            return $value;
        }
        $wasEncrypted = true;
        return $plain;
    }

    if (!function_exists('openssl_decrypt') || strlen($data) <= 28) {
        $wasEncrypted = false;
        return $value;
    }

    $iv = substr($data, 0, 12);
    $tag = substr($data, 12, 16);
    $cipher = substr($data, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

    if ($plain === false) {
        $wasEncrypted = false;
        return $value;
    }

    $wasEncrypted = true;
    return $plain;
}

function read_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        return [];
    }

    return $data;
}

function is_logged_in(): bool { return isset($_SESSION['uid']); }
function require_login() { if (!is_logged_in()) redirect('/login'); }
function uid(): int { return (int)($_SESSION['uid'] ?? 0); }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['csrf'];
}
function verify_csrf() {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) { http_response_code(400); die('Bad CSRF'); }
}

function moneyfmt($amount, $code='') { return number_format((float)$amount, 2) . ($code?" $code":""); }

function normalize_hex(?string $val): string {
    $val = trim((string)$val);

    // Allow shorthand like "abc" -> "#aabbcc"
    if (preg_match('/^#?([0-9a-f]{3})$/i', $val, $m)) {
        $hex = strtolower($m[1]);
        return sprintf('#%s%s%s',
            $hex[0].$hex[0],
            $hex[1].$hex[1],
            $hex[2].$hex[2]
        );
    }

    // Full hex: "#abc123" or "abc123"
    if (preg_match('/^#?([0-9a-f]{6})$/i', $val, $m)) {
        return '#'.strtolower($m[1]);
    }

    // Fallback (Tailwind gray-500)
    return '#6B7280';
}

// Ensure a DB color is output as "#RRGGBB" for inputs/styles.
function color_for_output(?string $v): string {
    return normalize_hex($v); // expands #abc -> #aabbcc, adds '#', defaults to #6B7280
}

function app_config(?string $section = null)
{
    static $config;
    if ($config === null) {
        $config = require __DIR__ . '/../config/config.php';
    }

    if ($section === null) {
        return $config;
    }

    return $config[$section] ?? null;
}

function app_url(string $path = ''): string
{
    $app = app_config('app') ?? [];
    $root = rtrim((string)($app['url'] ?? ''), '/');
    $base = rtrim((string)($app['base_url'] ?? ''), '/');

    $url = $root;
    if ($base !== '' && $base !== '/') {
        $url .= $base;
    }

    $path = (string)$path;
    if ($path !== '') {
        if ($path[0] !== '/' && $path[0] !== '?') {
            $path = '/' . $path;
        }
    }

    if ($url === '') {
        return $path !== '' ? $path : '/';
    }

    return $url . $path;
}

function available_locales(): array
{
    $app = app_config('app') ?? [];
    $locales = $app['locales'] ?? ['en' => 'English'];

    if (!is_array($locales) || !$locales) {
        return ['en' => 'English'];
    }

    return $locales;
}

function available_themes(): array
{
    static $themes;

    if ($themes === null) {
        $file = __DIR__ . '/../config/themes.php';
        $themes = is_file($file) ? require $file : [];
    }

    return $themes;
}

function default_theme_slug(): string
{
    $themes = available_themes();

    foreach ($themes as $slug => $meta) {
        if (!empty($meta['default'])) {
            return $slug;
        }
    }

    return array_key_first($themes) ?: 'verdant-horizon';
}

function theme_meta(string $slug): ?array
{
    $themes = available_themes();

    return $themes[$slug] ?? null;
}

function theme_display_name(?string $slug): string
{
    if (!$slug) {
        return theme_display_name(default_theme_slug());
    }

    $meta = theme_meta($slug);

    if (isset($meta['name'])) {
        return __($meta['name']);
    }

    return $slug;
}

function current_theme_slug(): string
{
    static $slug;

    if ($slug !== null) {
        return $slug;
    }

    $default = default_theme_slug();

    if (!is_logged_in()) {
        return $slug = $_SESSION['guest_theme'] ?? $default;
    }

    global $pdo;

    if (!$pdo) {
        return $slug = $default;
    }

    try {
        $stmt = $pdo->prepare('SELECT theme FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([uid()]);
        $value = (string)$stmt->fetchColumn();

        if ($value && isset(available_themes()[$value])) {
            return $slug = $value;
        }
    } catch (Throwable $e) {
        // fall back to default theme if lookup fails
    }

    return $slug = $default;
}

function default_locale(): string
{
    $available = available_locales();
    $app = app_config('app') ?? [];
    $default = $app['default_locale'] ?? null;

    if ($default && isset($available[$default])) {
        return $default;
    }

    return array_key_first($available) ?: 'en';
}

function detect_locale_from_header(array $available): ?string
{
    $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    if (!$header) {
        return null;
    }

    $parts = explode(',', $header);
    foreach ($parts as $part) {
        $code = strtolower(trim(explode(';', $part)[0] ?? ''));
        if (!$code) {
            continue;
        }

        $primary = substr($code, 0, 2);
        if (isset($available[$code])) {
            return $code;
        }
        if ($primary && isset($available[$primary])) {
            return $primary;
        }
    }

    return null;
}

function set_locale(string $locale): void
{
    $locale = strtolower($locale);
    $available = available_locales();
    if (!isset($available[$locale])) {
        return;
    }

    $_SESSION['locale'] = $locale;

    if (!is_logged_in()) {
        return;
    }

    global $pdo;

    if (!$pdo instanceof PDO) {
        return;
    }

    try {
        $stmt = $pdo->prepare('UPDATE users SET desired_language = ? WHERE id = ?');
        $stmt->execute([$locale, uid()]);
    } catch (Throwable $e) {
        // ignore persistence failures and keep session locale only
    }
}

function url_with_lang(string $locale): string
{
    $available = available_locales();
    if (!isset($available[$locale])) {
        $locale = default_locale();
    }

    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $parts = parse_url($uri);
    $path = $parts['path'] ?? '/';

    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    $query['lang'] = $locale;
    $queryString = http_build_query($query);

    return $path . ($queryString ? '?' . $queryString : '');
}

function app_locale(): string
{
    static $locale;
    if ($locale !== null) {
        return $locale;
    }

    $available = available_locales();

    if (isset($_GET['lang'])) {
        $requested = strtolower((string)$_GET['lang']);
        if (isset($available[$requested])) {
            set_locale($requested);
        }
    }

    $stored = $_SESSION['locale'] ?? null;
    if ($stored && isset($available[$stored])) {
        return $locale = $stored;
    }

    if (is_logged_in()) {
        global $pdo;

        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare('SELECT desired_language FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([uid()]);
                $dbLocale = strtolower((string)$stmt->fetchColumn());

                if ($dbLocale && isset($available[$dbLocale])) {
                    $_SESSION['locale'] = $dbLocale;
                    return $locale = $dbLocale;
                }
            } catch (Throwable $e) {
                // ignore lookup failures and continue with detection
            }
        }
    }

    $detected = detect_locale_from_header($available);
    if ($detected !== null) {
        return $locale = $detected;
    }

    return $locale = default_locale();
}

function load_translations(string $locale): array
{
    static $cache = [];
    if (isset($cache[$locale])) {
        return $cache[$locale];
    }

    $file = __DIR__ . '/../lang/' . $locale . '.php';
    if (is_file($file)) {
        $cache[$locale] = require $file;
    } else {
        $cache[$locale] = [];
    }

    return $cache[$locale];
}

function translate(string $key, array $replace = []): string
{
    $locale = app_locale();

    $value = load_translations($locale)[$key]
        ?? load_translations(default_locale())[$key]
        ?? $key;

    foreach ($replace as $name => $replacement) {
        $value = str_replace(':' . $name, (string)$replacement, $value);
    }

    return $value;
}

function __(string $key, array $replace = []): string
{
    return translate($key, $replace);
}

function month_name(int $month): string
{
    $month = max(1, min(12, $month));
    $key = 'month_' . $month;
    $value = __($key);
    if ($value === $key) {
        return date('F', mktime(0, 0, 0, $month, 1));
    }

    return $value;
}

function month_name_short(int $month): string
{
    $month = max(1, min(12, $month));
    $key = 'month_short_' . $month;
    $value = __($key);
    if ($value === $key) {
        return date('M', mktime(0, 0, 0, $month, 1));
    }

    return $value;
}

function format_month_year(?string $date = null): string
{
    $timestamp = $date ? strtotime($date) : time();
    if (!$timestamp) {
        return '';
    }

    $month = (int)date('n', $timestamp);
    $year = date('Y', $timestamp);

    return month_name($month) . ' ' . $year;
}
