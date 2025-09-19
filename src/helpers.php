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
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        http_response_code(400);
        die(__('errors.bad_csrf'));
    }
}

function moneyfmt($amount, $code='') { return number_format((float)$amount, 2) . ($code?" $code":""); }

function supported_locales(): array {
    return ['en', 'hu'];
}

function available_locales(): array {
    $labels = trans_array('locales');
    $result = [];
    foreach (supported_locales() as $code) {
        $result[$code] = $labels[$code] ?? $code;
    }
    return $result;
}

function default_locale(): string {
    return 'en';
}

function current_locale(): string {
    $locale = $_SESSION['locale'] ?? default_locale();
    if (!in_array($locale, supported_locales(), true)) {
        $locale = default_locale();
    }
    return $locale;
}

function set_locale(string $locale): void {
    if (in_array($locale, supported_locales(), true)) {
        $_SESSION['locale'] = $locale;
    }
}

function translations(?string $locale = null): array {
    static $cache = [];
    $locale = $locale ?? current_locale();
    if (!isset($cache[$locale])) {
        $path = __DIR__ . '/../lang/' . $locale . '.php';
        $cache[$locale] = file_exists($path) ? require $path : [];
    }
    return $cache[$locale];
}

function translation_lookup(array $translations, string $key) {
    $parts = explode('.', $key);
    $value = $translations;
    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }
    return $value;
}

function trans_raw(string $key) {
    $locale = current_locale();
    $value = translation_lookup(translations($locale), $key);
    if ($value === null && $locale !== default_locale()) {
        $value = translation_lookup(translations(default_locale()), $key);
    }
    return $value;
}

function __(string $key, array $replace = []): string {
    $value = trans_raw($key);
    if ($value === null) {
        $value = $key;
    } elseif (!is_string($value)) {
        $value = is_scalar($value) ? (string)$value : $key;
    }
    if ($replace) {
        foreach ($replace as $k => $v) {
            $value = str_replace(':' . $k, (string)$v, $value);
        }
    }
    return $value;
}

function trans_array(string $key): array {
    $value = trans_raw($key);
    return is_array($value) ? $value : [];
}

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
