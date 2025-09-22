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

    return $meta['name'] ?? $slug;
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
    $available = available_locales();
    if (isset($available[$locale])) {
        $_SESSION['locale'] = $locale;
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
