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

const ROLE_GUEST   = 'guest';
const ROLE_FREE    = 'free';
const ROLE_PREMIUM = 'premium';
const ROLE_ADMIN   = 'admin';

function role_default_definitions(): array
{
    return [
        ROLE_FREE => [
            'slug' => ROLE_FREE,
            'name' => __('Free user'),
            'description' => 'Default plan with limited access',
            'is_system' => true,
            'capabilities' => [
                'currencies_limit' => 1,
                'goals_limit' => 2,
                'loans_limit' => 2,
                'categories_limit' => 10,
                'scheduled_payments_limit' => 2,
                'cashflow_rules_edit' => false,
            ],
        ],
        ROLE_PREMIUM => [
            'slug' => ROLE_PREMIUM,
            'name' => __('Premium user'),
            'description' => 'Full access to financial planning tools',
            'is_system' => true,
            'capabilities' => [
                'currencies_limit' => null,
                'goals_limit' => null,
                'loans_limit' => null,
                'categories_limit' => null,
                'scheduled_payments_limit' => null,
                'cashflow_rules_edit' => true,
            ],
        ],
        ROLE_ADMIN => [
            'slug' => ROLE_ADMIN,
            'name' => __('Administrator'),
            'description' => 'Administrative access to manage the platform',
            'is_system' => true,
            'capabilities' => [
                'currencies_limit' => null,
                'goals_limit' => null,
                'loans_limit' => null,
                'categories_limit' => null,
                'scheduled_payments_limit' => null,
                'cashflow_rules_edit' => false,
            ],
        ],
    ];
}

function role_definitions(bool $refresh = false): array
{
    static $cache;

    if ($refresh) {
        $cache = null;
    }

    if ($cache !== null) {
        return $cache;
    }

    $definitions = role_default_definitions();

    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return $cache = $definitions;
    }

    try {
        $stmt = $pdo->query('SELECT slug, name, description, is_system, capabilities FROM roles');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $slug = strtolower(trim((string)($row['slug'] ?? '')));
            if ($slug === '') {
                continue;
            }

            $caps = [];
            $rawCaps = $row['capabilities'] ?? [];
            if (is_string($rawCaps)) {
                $decoded = json_decode($rawCaps, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $caps = $decoded;
                }
            } elseif (is_array($rawCaps)) {
                $caps = $rawCaps;
            }

            $definitions[$slug] = [
                'slug' => $slug,
                'name' => (string)($row['name'] ?? $slug),
                'description' => $row['description'] ?? null,
                'is_system' => (bool)($row['is_system'] ?? false),
                'capabilities' => $caps,
            ];
        }
    } catch (Throwable $e) {
        // ignore and fall back to defaults
    }

    return $cache = $definitions;
}

function role_definition(string $slug): ?array
{
    $slug = strtolower(trim($slug));
    $definitions = role_definitions();

    return $definitions[$slug] ?? null;
}

function reset_role_definitions_cache(): void
{
    role_definitions(true);
}

function role_capability(string $role, string $capability, $default = null)
{
    $definition = role_definition($role);
    if (!$definition) {
        return $default;
    }

    $caps = $definition['capabilities'] ?? [];
    if (!is_array($caps)) {
        return $default;
    }

    return $caps[$capability] ?? $default;
}

function billing_interval_labels(): array
{
    return [
        'weekly' => __('Weekly'),
        'monthly' => __('Monthly'),
        'yearly' => __('Yearly'),
        'lifetime' => __('Lifetime'),
    ];
}

function billing_settings(bool $refresh = false): array
{
    static $cache;

    if ($refresh) {
        $cache = null;
    }

    if ($cache !== null) {
        return $cache;
    }

    $settings = [
        'stripe_secret_key' => null,
        'stripe_publishable_key' => null,
        'stripe_webhook_secret' => null,
        'default_currency' => 'USD',
    ];

    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return $cache = $settings;
    }

    try {
        $stmt = $pdo->query('SELECT stripe_secret_key, stripe_publishable_key, stripe_webhook_secret, default_currency FROM billing_settings WHERE id = 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $settings['stripe_secret_key'] = $row['stripe_secret_key'] ?? null;
            $settings['stripe_publishable_key'] = $row['stripe_publishable_key'] ?? null;
            $settings['stripe_webhook_secret'] = $row['stripe_webhook_secret'] ?? null;
            $defaultCurrency = strtoupper(trim((string)($row['default_currency'] ?? '')));
            $settings['default_currency'] = $defaultCurrency !== '' ? $defaultCurrency : 'USD';
        }
    } catch (Throwable $e) {
        // ignore and keep defaults
    }

    return $cache = $settings;
}

function reset_billing_settings_cache(): void
{
    billing_settings(true);
}

function billing_default_currency(): string
{
    $settings = billing_settings();
    $currency = strtoupper(trim((string)($settings['default_currency'] ?? 'USD')));

    return $currency !== '' ? $currency : 'USD';
}

function billing_has_stripe_keys(): bool
{
    $settings = billing_settings();

    return !empty($settings['stripe_secret_key']) && !empty($settings['stripe_publishable_key']);
}

function role_can(string $capability, ?string $role = null): bool
{
    $role = $role ? strtolower(trim($role)) : current_user_role();
    $value = role_capability($role, $capability, null);

    if ($value === null) {
        return true;
    }

    if (is_bool($value)) {
        return $value;
    }

    if (is_numeric($value)) {
        return (int)$value !== 0;
    }

    return (bool)$value;
}

function role_limit_for(string $role, string $resource): ?int
{
    $map = [
        'currencies' => 'currencies_limit',
        'goals_active' => 'goals_limit',
        'loans_active' => 'loans_limit',
        'categories' => 'categories_limit',
        'scheduled_active' => 'scheduled_payments_limit',
    ];

    $capabilityKey = $map[$resource] ?? $resource;
    $value = role_capability($role, $capabilityKey, null);

    if ($value === null || $value === '') {
        return null;
    }

    if (is_numeric($value)) {
        $int = (int)$value;
        return $int > 0 ? $int : null;
    }

    if (is_bool($value)) {
        return $value ? null : 0;
    }

    return null;
}

function user_limit_for(string $resource): ?int
{
    return role_limit_for(current_user_role(), $resource);
}

const USER_STATUS_ACTIVE = 'active';
const USER_STATUS_INACTIVE = 'inactive';

function normalize_user_role($role, bool $allowGuest = false): string
{
    $role = strtolower(trim((string)$role));

    if ($allowGuest && $role === ROLE_GUEST) {
        return ROLE_GUEST;
    }

    $definition = role_definition($role);
    if ($definition) {
        return $definition['slug'];
    }

    return $allowGuest ? ROLE_GUEST : ROLE_FREE;
}

function normalize_user_status($status): string
{
    $status = strtolower(trim((string)$status));

    if ($status === USER_STATUS_INACTIVE) {
        return USER_STATUS_INACTIVE;
    }

    return USER_STATUS_ACTIVE;
}

function current_user_role(): string
{
    if (!isset($_SESSION['role'])) {
        return ROLE_GUEST;
    }

    $normalized = normalize_user_role($_SESSION['role'], true);
    if ($normalized !== ROLE_GUEST) {
        $_SESSION['role'] = $normalized;
    }

    return $normalized;
}

function current_user_status(): string
{
    if (!isset($_SESSION['status'])) {
        return USER_STATUS_ACTIVE;
    }

    $normalized = normalize_user_status($_SESSION['status']);
    $_SESSION['status'] = $normalized;

    return $normalized;
}

function refresh_user_role(PDO $pdo, int $userId): string
{
    try {
        $stmt = $pdo->prepare('SELECT role, status FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $role = normalize_user_role($row['role'] ?? null);
        $status = normalize_user_status($row['status'] ?? null);
    } catch (Throwable $e) {
        $role = ROLE_FREE;
        $status = USER_STATUS_ACTIVE;
    }

    $_SESSION['role'] = $role;
    $_SESSION['status'] = $status;

    return $role;
}

function is_admin(): bool
{
    return current_user_role() === ROLE_ADMIN;
}

function is_free_user(): bool
{
    return current_user_role() === ROLE_FREE;
}

function is_premium_user(): bool
{
    return current_user_role() === ROLE_PREMIUM;
}

function user_prepare_full_name_fields(?string $name): array
{
    $trimmed = trim((string)$name);
    $search = $trimmed !== '' ? mb_strtolower(preg_replace('/\s+/u', ' ', $trimmed)) : null;

    if ($search !== null && $search === '') {
        $search = null;
    }

    $encrypted = $trimmed !== '' ? pii_encrypt($trimmed) : null;

    return [$encrypted, $search];
}

function log_user_login_activity(PDO $pdo, int $userId, bool $success, string $email = '', string $method = 'password'): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO user_login_activity (user_id, email, success, method, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $stmt->execute([
            $userId,
            $email !== '' ? $email : null,
            $success,
            $method,
            $ip,
            $agent,
        ]);
    } catch (Throwable $e) {
        // Intentionally swallow logging failures.
    }
}

function free_user_limit_for(string $resource): ?int
{
    return role_limit_for(ROLE_FREE, $resource);
}

function free_user_resource_count(PDO $pdo, int $userId, string $resource): int
{
    switch ($resource) {
        case 'currencies':
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM user_currencies WHERE user_id = ?');
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn();

        case 'goals_active':
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM goals WHERE user_id = ? AND archived_at IS NULL');
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn();

        case 'loans_active':
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM loans WHERE user_id = ? AND archived_at IS NULL AND (finished_at IS NULL OR finished_at = \'\')');
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn();

        case 'categories':
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE user_id = ?');
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn();

        case 'scheduled_active':
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM scheduled_payments WHERE user_id = ? AND archived_at IS NULL');
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn();
    }

    return 0;
}

function user_limit_guard(PDO $pdo, string $resource, string $redirect, string $message, ?callable $counter = null, ?string $flashType = 'error'): void
{
    $userId = uid();
    if ($userId <= 0) {
        return;
    }

    $limit = user_limit_for($resource);
    if ($limit === null) {
        return;
    }

    $count = $counter ? (int)$counter($pdo, $userId) : free_user_resource_count($pdo, $userId, $resource);
    if ($count >= $limit) {
        $_SESSION['flash'] = $message;
        if ($flashType !== null) {
            $_SESSION['flash_type'] = $flashType;
        }
        redirect($redirect);
    }
}

function free_user_limit_guard(PDO $pdo, string $resource, string $redirect, string $message, ?callable $counter = null, ?string $flashType = 'error'): void
{
    user_limit_guard($pdo, $resource, $redirect, $message, $counter, $flashType);
}

function admin_allowed_path(string $path, string $method = 'GET'): bool
{
    $method = strtoupper($method);

    if (str_starts_with($path, '/admin')) {
        return true;
    }

    if ($path === '/logout' && $method === 'POST') {
        return true;
    }

    if ($path === '/maintenance/migrations') {
        return true;
    }

    return false;
}

function require_admin(?string $message = null): void
{
    if (!is_logged_in()) {
        redirect('/login');
    }

    if (is_admin()) {
        return;
    }

    http_response_code(403);
    view('errors/403', [
        'pageTitle' => __('Forbidden'),
        'message' => $message,
        'fullWidthMain' => true,
    ]);
    exit;
}

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
