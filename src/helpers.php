<?php
function view(string $name, array $data = []) {
    global $pdo;            // <-- bring the PDO from global scope into this function
    extract($data);         // makes $row, etc. available to the view
    ob_start();
    include __DIR__ . '/../views/layout/header.php';
    include __DIR__ . '/../views/' . $name . '.php';
    include __DIR__ . '/../views/layout/footer.php';
    $content = (string)ob_get_clean();
    echo translate_view_content($content);
}

function app_config(?string $section = null)
{
    global $config;
    if (!isset($config)) {
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
        $locales = ['en' => 'English'];
    }
    return $locales;
}

function default_locale(): string
{
    $app = app_config('app') ?? [];
    $default = $app['default_locale'] ?? null;
    $available = available_locales();
    if ($default && isset($available[$default])) {
        return $default;
    }
    return array_key_first($available) ?: 'en';
}

function app_locale(): string
{
    $available = available_locales();
    $stored = $_SESSION['locale'] ?? $_SESSION['lang'] ?? null;
    if ($stored && isset($available[$stored])) {
        return $stored;
    }
    $default = default_locale();
    $_SESSION['locale'] = $default;
    return $default;
}

function set_locale(string $locale): void
{
    $available = available_locales();
    if (isset($available[$locale])) {
        $_SESSION['locale'] = $locale;
    }
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
    $value = load_translations($locale)[$key] ?? load_translations(default_locale())[$key] ?? $key;
    if ($replace) {
        foreach ($replace as $name => $replacement) {
            $value = str_replace(':' . $name, (string)$replacement, $value);
        }
    }
    return $value;
}

function __(string $key, array $replace = []): string
{
    return translate($key, $replace);
}

function translate_view_content(string $content): string
{
    $locale = app_locale();
    $default = default_locale();
    if ($locale === $default) {
        return $content;
    }

    $map = load_translations($locale);
    if (!$map) {
        return $content;
    }

    $search = [];
    $replace = [];
    foreach ($map as $key => $value) {
        if (!is_string($key) || !is_string($value)) {
            continue;
        }
        if (str_starts_with($key, 'month_')) {
            continue;
        }
        if (str_contains($key, ':')) {
            continue;
        }
        $search[] = $key;
        $replace[] = $value;
    }

    if (!$search) {
        return $content;
    }

    return str_replace($search, $replace, $content);
}

function month_name(int $month): string
{
    $key = 'month_' . $month;
    $name = __($key);
    if ($name === $key) {
        return date('F', mktime(0, 0, 0, max(1, min(12, $month)), 1));
    }
    return $name;
}

function month_name_short(int $month): string
{
    $key = 'month_short_' . $month;
    $name = __($key);
    if ($name === $key) {
        return date('M', mktime(0, 0, 0, max(1, min(12, $month)), 1));
    }
    return $name;
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
