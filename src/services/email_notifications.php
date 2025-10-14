<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/../fx.php';
require_once __DIR__ . '/../recurrence.php';

function email_brand_logo_image(): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $logoPath = dirname(__DIR__, 2) . '/logo.png';
    if (is_file($logoPath) && is_readable($logoPath)) {
        $data = base64_encode((string)file_get_contents($logoPath));
        if ($data !== '') {
            return $cache = [
                'src' => 'data:image/png;base64,' . $data,
                'alt' => 'MyMoneyMap',
                'width' => 128,
                'height' => 128,
            ];
        }
    }

    return $cache = [
        'src' => app_url('/logo.png'),
        'alt' => 'MyMoneyMap',
        'width' => 128,
        'height' => 128,
    ];
}

function email_theme_palette(?array $user = null): array
{
    $defaults = [
        'slug' => default_theme_slug(),
        'name' => 'MyMoneyMap',
        'base' => '#2563eb',
        'accent' => '#1d4ed8',
        'muted' => '#eef2ff',
        'deep' => '#0f172a',
    ];

    $catalog = available_themes();
    $slug = '';

    if (is_array($user)) {
        $slug = trim((string)($user['theme'] ?? ''));
    }

    if ($slug === '' || !isset($catalog[$slug])) {
        $slug = $defaults['slug'];
    }

    $meta = $catalog[$slug] ?? null;
    if (!is_array($meta)) {
        return $defaults;
    }

    return array_merge($defaults, $meta, ['slug' => $slug]);
}

function email_support_address(): string
{
    $mail = mailer_config();
    if (is_array($mail)) {
        $support = $mail['support_email'] ?? null;
        if (is_string($support) && $support !== '') {
            return $support;
        }
    }

    $env = getenv('MM_SUPPORT_EMAIL');
    if (is_string($env) && $env !== '') {
        return $env;
    }

    return 'support@mymoneymap.local';
}

function email_feedback_inbox_address(): string
{
    $env = getenv('MM_FEEDBACK_INBOX');
    if (is_string($env) && $env !== '') {
        return $env;
    }

    return 'feedback@mymoneymap.hu';
}

function email_template_base_tokens(): array
{
    return [
        'app_url' => app_url('/'),
        'privacy_url' => app_url('/privacy'),
        'settings_url' => app_url('/settings'),
        'unsubscribe_url' => app_url('/settings/notifications'),
        'support_email' => email_support_address(),
        'year' => (string)date('Y'),
    ];
}

function email_template_escape(string $value): string
{
    $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return str_replace('&amp;nbsp;', '&nbsp;', $escaped);
}

function email_template_normalize_locale(?string $locale): string
{
    if ($locale === null) {
        return '';
    }

    $normalized = strtolower(trim($locale));
    if ($normalized === '') {
        return '';
    }

    return str_replace([' ', '.'], '-', str_replace('_', '-', $normalized));
}

function email_template_candidate_locales(array $tokens): array
{
    $keys = [
        'template_language',
        'language',
        'desired_language',
        'locale',
        'user_language',
        'user_locale',
    ];

    $candidates = [];

    foreach ($keys as $key) {
        if (!isset($tokens[$key])) {
            continue;
        }

        $value = email_template_normalize_locale((string)$tokens[$key]);
        if ($value === '') {
            continue;
        }

        $candidates[] = $value;

        if (strlen($value) > 2) {
            $primary = substr($value, 0, 2);
            if ($primary !== '') {
                $candidates[] = $primary;
            }
        }
    }

    $candidates[] = default_locale();
    $candidates[] = 'en';

    return array_values(array_unique(array_filter($candidates)));
}

function email_template_resolve_locale(array $tokens): string
{
    $basePath = dirname(__DIR__, 2) . '/lang';
    $available = available_locales();
    $candidates = email_template_candidate_locales($tokens);

    foreach ($candidates as $candidate) {
        if (isset($available[$candidate])) {
            return $candidate;
        }

        $path = $basePath . '/' . $candidate . '.php';
        if (is_file($path)) {
            return $candidate;
        }

        if (strlen($candidate) > 2) {
            $primary = substr($candidate, 0, 2);
            if ($primary !== '') {
                if (isset($available[$primary])) {
                    return $primary;
                }

                $primaryPath = $basePath . '/' . $primary . '.php';
                if (is_file($primaryPath)) {
                    return $primary;
                }
            }
        }
    }

    return default_locale();
}

function email_template_translate_value(string $key, string $locale, array $replace = []): string
{
    $translated = load_translations($locale)[$key] ?? null;

    if ($translated === null && $locale !== default_locale()) {
        $translated = load_translations(default_locale())[$key] ?? null;
    }

    if ($translated === null) {
        $translated = $key;
    }

    foreach ($replace as $name => $replacement) {
        if (!is_string($replacement)) {
            $replacement = (string)$replacement;
        }

        $translated = str_replace(':' . $name, $replacement, $translated);
    }

    return $translated;
}

function email_template_prepare_token_value($value, string $locale, bool &$raw = false): string
{
    $raw = false;

    if (is_array($value)) {
        $raw = !empty($value['raw']);

        $key = $value['translate'] ?? $value['key'] ?? null;
        $fallback = $value['value'] ?? null;
        $replace = [];

        if (isset($value['replace']) && is_array($value['replace'])) {
            $replace = $value['replace'];
        }

        if (is_string($key) && $key !== '') {
            return email_template_translate_value($key, $locale, $replace);
        }

        if ($fallback !== null) {
            if (!is_string($fallback)) {
                $fallback = (string)$fallback;
            }

            return email_template_translate_value($fallback, $locale, $replace);
        }

        $value = $value['value'] ?? '';
    }

    if (!is_string($value)) {
        $value = (string)$value;
    }

    return email_template_translate_value($value, $locale);
}

function email_template_extract_title(string $html): ?string
{
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
        $title = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($title !== '') {
            return preg_replace('/\s+/', ' ', $title);
        }
    }

    return null;
}

function email_template_subject(string $html, string $fallbackKey, string $locale, array $replace = []): string
{
    $title = email_template_extract_title($html);
    if ($title !== null && $title !== '') {
        return $title;
    }

    return email_template_translate_value($fallbackKey, $locale, $replace);
}

function email_template_html_to_text(string $html): string
{
    $text = preg_replace('/<head\b.*?<\/head>/is', '', $html);
    $text = preg_replace('/<\s*(script|style)\b.*?<\/\s*\1\s*>/is', '', $text);
    $text = preg_replace('/<\s*br\s*\/?>/i', "\n", $text);
    $text = preg_replace('/<\s*\/\s*(p|div|section|article|tr|table|h[1-6]|ul|ol)\s*>/i', "\n", $text);
    $text = preg_replace('/<\s*li[^>]*>/i', "\n• ", $text);
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/[\t ]+\n/", "\n", $text);
    $text = preg_replace("/\n{3,}/", "\n\n", trim($text));

    return $text;
}

function email_template_render(string $template, array $tokens): string
{
    $basePath = dirname(__DIR__, 2) . '/docs/email_templates';
    $candidates = email_template_candidate_locales($tokens);

    $html = '';

    foreach ($candidates as $language) {
        $path = $basePath . '/' . $language . '/' . $template . '.html';
        if (!is_file($path) || !is_readable($path)) {
            continue;
        }

        $html = (string)file_get_contents($path);
        if ($html !== '') {
            break;
        }
    }

    if ($html === '') {
        throw new RuntimeException('Email template not found: ' . $template);
    }

    $locale = email_template_resolve_locale($tokens);
    $defaults = email_template_base_tokens();
    $replacements = [];

    foreach (array_merge($defaults, $tokens) as $key => $value) {
        $placeholder = '{{' . $key . '}}';
        $isRaw = false;
        $prepared = email_template_prepare_token_value($value, $locale, $isRaw);
        $replacements[$placeholder] = $isRaw ? $prepared : email_template_escape($prepared);
    }

    return strtr($html, $replacements);
}

function email_hex_luminance(string $hex): float
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    if (strlen($hex) !== 6) {
        return 0.0;
    }

    $r = hexdec(substr($hex, 0, 2)) / 255;
    $g = hexdec(substr($hex, 2, 2)) / 255;
    $b = hexdec(substr($hex, 4, 2)) / 255;

    return 0.299 * $r + 0.587 * $g + 0.114 * $b;
}

function email_contrast_color(string $hex, string $light = '#0f172a', string $dark = '#ffffff'): string
{
    return email_hex_luminance($hex) > 0.55 ? $light : $dark;
}

function email_normalize_hex(string $hex): ?string
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    if (strlen($hex) !== 6 || preg_match('/[^0-9a-f]/i', $hex)) {
        return null;
    }

    return strtolower($hex);
}

function email_mix_hex(string $base, string $blend, float $ratio): string
{
    $ratio = max(0.0, min(1.0, $ratio));
    $baseHex = email_normalize_hex($base);
    $blendHex = email_normalize_hex($blend);

    if ($baseHex === null || $blendHex === null) {
        return $base;
    }

    $baseRgb = [
        hexdec(substr($baseHex, 0, 2)),
        hexdec(substr($baseHex, 2, 2)),
        hexdec(substr($baseHex, 4, 2)),
    ];
    $blendRgb = [
        hexdec(substr($blendHex, 0, 2)),
        hexdec(substr($blendHex, 2, 2)),
        hexdec(substr($blendHex, 4, 2)),
    ];

    $mixed = [];
    for ($i = 0; $i < 3; $i++) {
        $mixed[$i] = (int)round(($baseRgb[$i] * (1 - $ratio)) + ($blendRgb[$i] * $ratio));
        $mixed[$i] = max(0, min(255, $mixed[$i]));
    }

    return sprintf('#%02x%02x%02x', $mixed[0], $mixed[1], $mixed[2]);
}

function email_hex_to_rgba(string $hex, float $alpha): string
{
    $alpha = max(0.0, min(1.0, $alpha));
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    if (strlen($hex) !== 6) {
        return 'rgba(15,23,42,' . $alpha . ')';
    }

    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    return 'rgba(' . $r . ',' . $g . ',' . $b . ',' . $alpha . ')';
}

function email_wrap_html(string $title, string $content, array $palette): string
{
    $logo = email_brand_logo_image();
    $titleSafe = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

    $background = $palette['muted'] ?? '#f3f4f6';
    $cardBorder = $palette['accent'] ?? '#1d4ed8';
    $headerBg = $palette['base'] ?? '#2563eb';
    $headerAccent = $palette['accent'] ?? email_mix_hex($headerBg, '#000000', 0.12);
    $headerGlow = email_mix_hex($headerBg, '#ffffff', 0.28);
    $headerText = email_contrast_color($headerAccent);
    $bodyText = $palette['deep'] ?? '#0f172a';
    $contentBg = email_mix_hex($background, '#ffffff', 0.55);
    $contentBorder = email_mix_hex($cardBorder, '#ffffff', 0.35);
    $contentAccent = email_mix_hex($cardBorder, $headerBg, 0.24);
    $footerBg = email_mix_hex($palette['accent'] ?? '#1d4ed8', '#0f172a', 0.08);
    $footerText = email_contrast_color($footerBg);
    $linkColor = $palette['base'] ?? '#2563eb';
    $shadow = email_hex_to_rgba($palette['deep'] ?? '#0f172a', 0.18);

    $logoSrc = htmlspecialchars($logo['src'], ENT_QUOTES, 'UTF-8');
    $logoAlt = htmlspecialchars($logo['alt'], ENT_QUOTES, 'UTF-8');
    $logoWidth = (int)($logo['width'] ?? 112);
    $logoHeight = (int)($logo['height'] ?? 112);
    $paletteName = htmlspecialchars((string)($palette['name'] ?? 'MyMoneyMap'), ENT_QUOTES, 'UTF-8');
    $headerGradientStart = htmlspecialchars(email_mix_hex($headerBg, $headerGlow, 0.32), ENT_QUOTES, 'UTF-8');
    $headerGradientEnd = htmlspecialchars(email_mix_hex($headerAccent, '#0f172a', 0.18), ENT_QUOTES, 'UTF-8');
    $headerBeam = htmlspecialchars(email_hex_to_rgba($headerGlow, 0.22), ENT_QUOTES, 'UTF-8');
    $headerHalo = htmlspecialchars(email_hex_to_rgba($headerAccent, 0.35), ENT_QUOTES, 'UTF-8');
    $contentBgSafe = htmlspecialchars($contentBg, ENT_QUOTES, 'UTF-8');
    $contentBorderSafe = htmlspecialchars($contentBorder, ENT_QUOTES, 'UTF-8');
    $contentAccentSafe = htmlspecialchars($contentAccent, ENT_QUOTES, 'UTF-8');
    $footerAccent = htmlspecialchars(email_mix_hex($footerBg, '#000000', 0.1), ENT_QUOTES, 'UTF-8');

    $profileUrl = htmlspecialchars(app_url('/settings/profile'), ENT_QUOTES, 'UTF-8');
    $privacyCenterUrl = htmlspecialchars(app_url('/settings/privacy'), ENT_QUOTES, 'UTF-8');
    $privacyUrl = htmlspecialchars(app_url('/privacy'), ENT_QUOTES, 'UTF-8');
    $termsUrl = htmlspecialchars(app_url('/terms'), ENT_QUOTES, 'UTF-8');
    $year = (int)date('Y');

    return '<!DOCTYPE html>' .
        '<html lang="en">' .
        '<head>' .
        '<meta charset="utf-8" />' .
        '<meta name="viewport" content="width=device-width,initial-scale=1" />' .
        '<title>' . $titleSafe . '</title>' .
        '<style>' .
        'a{color:' . htmlspecialchars($linkColor, ENT_QUOTES, 'UTF-8') . ';}' .
        '</style>' .
        '</head>' .
        '<body style="margin:0;background:' . htmlspecialchars($background, ENT_QUOTES, 'UTF-8') . ';color:' . htmlspecialchars($bodyText, ENT_QUOTES, 'UTF-8') . ';font-family:\'Inter\',\'Segoe UI\',Helvetica,Arial,sans-serif;">' .
        '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="padding:32px 12px;">' .
        '<tr><td align="center">' .
        '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;max-width:640px;">' .
        '<tr><td>' .
        '<div style="background:#ffffff;border:1px solid ' . htmlspecialchars($cardBorder, ENT_QUOTES, 'UTF-8') . ';border-radius:30px;overflow:hidden;box-shadow:0 32px 64px ' . htmlspecialchars($shadow, ENT_QUOTES, 'UTF-8') . ';">' .
        '<div style="padding:46px 48px;background:linear-gradient(135deg,' . $headerGradientStart . ',' . $headerGradientEnd . ');color:' . htmlspecialchars($headerText, ENT_QUOTES, 'UTF-8') . ';position:relative;">' .
        '<div style="position:absolute;inset:0;background:' . $headerHalo . ';mix-blend-mode:soft-light;opacity:0.7;"></div>' .
        '<div style="position:absolute;left:0;right:0;top:0;height:100%;background:radial-gradient(circle at 15% 20%,' . $headerBeam . ',rgba(255,255,255,0));opacity:1;"></div>' .
        '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="position:relative;z-index:1;">' .
        '<tr>' .
        '<td style="width:120px;vertical-align:middle;padding-right:24px;">' .
        '<div style="display:inline-block;padding:18px;border-radius:28px;background:rgba(255,255,255,0.18);backdrop-filter:blur(8px);">' .
        '<img src="' . $logoSrc . '" alt="' . $logoAlt . '" width="' . $logoWidth . '" height="' . $logoHeight . '" style="display:block;height:60px;width:auto;" />' .
        '</div>' .
        '</td>' .
        '<td style="vertical-align:middle;">' .
        '<p style="margin:0;font-size:12px;font-weight:600;letter-spacing:0.3em;text-transform:uppercase;opacity:0.86;">MyMoneyMap</p>' .
        '<p style="margin:12px 0 0;font-size:30px;font-weight:700;letter-spacing:-0.02em;">' . $paletteName . ' Experience</p>' .
        '<p style="margin:16px 0 0;font-size:16px;line-height:1.7;max-width:420px;opacity:0.9;">Personal finance intelligence curated to match your selected theme—precision insights, delivered with polish.</p>' .
        '</td>' .
        '</tr>' .
        '</table>' .
        '</div>' .
        '<div style="padding:40px 44px;background:' . $contentBgSafe . ';color:' . htmlspecialchars($bodyText, ENT_QUOTES, 'UTF-8') . ';font-size:15px;line-height:1.75;">' .
        '<div style="padding:24px 28px;border:1px solid ' . $contentBorderSafe . ';border-radius:22px;background:#ffffff;box-shadow:0 14px 32px ' . htmlspecialchars(email_hex_to_rgba($palette['deep'] ?? '#0f172a', 0.06), ENT_QUOTES, 'UTF-8') . ';">' .
        '<h1 style="margin:0 0 22px;font-size:26px;font-weight:700;letter-spacing:-0.015em;color:' . htmlspecialchars($bodyText, ENT_QUOTES, 'UTF-8') . ';">' . $titleSafe . '</h1>' .
        '<div style="border-left:4px solid ' . $contentAccentSafe . ';padding-left:18px;margin:0 0 22px;font-size:14px;letter-spacing:0.08em;text-transform:uppercase;color:' . htmlspecialchars(email_mix_hex($bodyText, '#64748b', 0.45), ENT_QUOTES, 'UTF-8') . ';">Your personalised briefing</div>' .
        '<div style="font-size:15px;line-height:1.75;color:' . htmlspecialchars($bodyText, ENT_QUOTES, 'UTF-8') . ';">' .
        $content .
        '</div>' .
        '</div>' .
        '</div>' .
        '<div style="background:' . htmlspecialchars($footerBg, ENT_QUOTES, 'UTF-8') . ';color:' . htmlspecialchars($footerText, ENT_QUOTES, 'UTF-8') . ';padding:30px 44px;text-align:left;border-top:1px solid ' . $footerAccent . ';">' .
        '<p style="margin:0 0 12px;font-size:14px;font-weight:600;letter-spacing:0.24em;text-transform:uppercase;">Stay in control</p>' .
        '<p style="margin:0 0 12px;font-size:13px;line-height:1.7;max-width:520px;">You are receiving this tailored update because notifications are enabled for your MyMoneyMap account. Adjust delivery or refresh your profile preferences whenever you need.</p>' .
        '<p style="margin:0;font-size:12px;line-height:1.7;">Access <a href="' . $profileUrl . '" style="color:' . htmlspecialchars($footerText, ENT_QUOTES, 'UTF-8') . ';font-weight:600;">Profile Settings</a> · Visit the <a href="' . $privacyCenterUrl . '" style="color:' . htmlspecialchars($footerText, ENT_QUOTES, 'UTF-8') . ';font-weight:600;">Privacy Centre</a></p>' .
        '</div>' .
        '<div style="padding:24px 44px;background:#ffffff;border-top:1px solid ' . htmlspecialchars(email_mix_hex($cardBorder, '#94a3b8', 0.3), ENT_QUOTES, 'UTF-8') . ';">' .
        '<p style="margin:0 0 10px;font-size:12px;line-height:1.6;color:' . htmlspecialchars(email_mix_hex($bodyText, '#475569', 0.35), ENT_QUOTES, 'UTF-8') . ';">MyMoneyMap Labs Ltd · 221 Innovation Way · Dublin D02 · Ireland · VAT IE1234567A</p>' .
        '<p style="margin:0;font-size:12px;line-height:1.6;color:' . htmlspecialchars(email_mix_hex($bodyText, '#475569', 0.35), ENT_QUOTES, 'UTF-8') . ';">© ' . $year . ' MyMoneyMap Labs Ltd. <a href="' . $privacyUrl . '" style="color:' . htmlspecialchars($linkColor, ENT_QUOTES, 'UTF-8') . ';font-weight:600;">Privacy Policy</a> · <a href="' . $termsUrl . '" style="color:' . htmlspecialchars($linkColor, ENT_QUOTES, 'UTF-8') . ';font-weight:600;">Terms of Service</a></p>' .
        '</div>' .
        '</div>' .
        '<div style="max-width:640px;margin:18px auto 0;font-size:11px;line-height:1.6;color:' . htmlspecialchars(email_mix_hex($bodyText, '#475569', 0.55), ENT_QUOTES, 'UTF-8') . ';">' .
        '<div style="background:#ffffff;border:1px solid ' . htmlspecialchars(email_mix_hex($cardBorder, '#cbd5f5', 0.4), ENT_QUOTES, 'UTF-8') . ';border-radius:20px;padding:18px 22px;box-shadow:0 12px 22px ' . htmlspecialchars(email_hex_to_rgba($palette['deep'] ?? '#0f172a', 0.05), ENT_QUOTES, 'UTF-8') . ';">' .
        '<strong style="display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.18em;font-size:10px;color:' . htmlspecialchars(email_mix_hex($bodyText, '#475569', 0.4), ENT_QUOTES, 'UTF-8') . ';">GDPR Notice</strong>' .
        '<span style="display:block;">We process your personal data as the controller under GDPR solely to provide MyMoneyMap services and essential communications. Manage your data rights from the Privacy Centre or contact our Data Protection Officer at <a href="mailto:privacy@mymoneymap.local" style="color:' . htmlspecialchars($linkColor, ENT_QUOTES, 'UTF-8') . ';font-weight:600;">privacy@mymoneymap.local</a>.</span>' .
        '</div>' .
        '</div>' .
        '</td></tr>' .
        '</table>' .
        '</td></tr>' .
        '</table>' .
        '</body>' .
        '</html>';
}

function email_render_button(string $href, string $label, array $palette): string
{
    $background = htmlspecialchars($palette['base'] ?? '#2563eb', ENT_QUOTES, 'UTF-8');
    $color = htmlspecialchars(email_contrast_color($palette['base'] ?? '#2563eb'), ENT_QUOTES, 'UTF-8');

    return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" ' .
        'style="display:inline-block;padding:12px 24px;margin:12px 0;border-radius:999px;background:' . $background . ';color:' . $color . ';text-decoration:none;font-weight:600;">' .
        htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
}

function email_load_user_profile(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT id, email, full_name, email_verified_at, desired_language FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return null;
    }

    $email = trim((string)($user['email'] ?? ''));
    if ($email === '') {
        return null;
    }

    $user['email'] = $email;
    $user['full_name_plain'] = $user['full_name'] ? pii_decrypt($user['full_name']) : '';

    $preferred = strtolower(trim((string)($user['desired_language'] ?? '')));
    if ($preferred !== '') {
        $preferred = str_replace([' ', '.'], '-', str_replace('_', '-', $preferred));
    }
    $user['desired_language'] = $preferred !== '' ? $preferred : null;

    return $user;
}

function email_user_display_name(array $user): string
{
    $name = trim((string)($user['full_name_plain'] ?? ''));
    if ($name === '' && !empty($user['full_name'])) {
        $name = trim((string)pii_decrypt($user['full_name']));
    }
    if ($name === '') {
        $name = trim((string)($user['email'] ?? ''));
    }

    return $name;
}

function email_user_first_name(array $user): string
{
    $display = email_user_display_name($user);
    if ($display === '') {
        return 'there';
    }

    $parts = preg_split('/\s+/u', $display);
    if (!$parts || $parts[0] === '') {
        return $display;
    }

    return $parts[0];
}

function email_user_locale(array $user): ?string
{
    $keys = ['desired_language', 'locale', 'language'];

    foreach ($keys as $key) {
        if (!isset($user[$key])) {
            continue;
        }

        $value = strtolower(trim((string)$user[$key]));
        if ($value === '') {
            continue;
        }

        $value = str_replace([' ', '.'], '-', str_replace('_', '-', $value));
        if ($value !== '') {
            return $value;
        }
    }

    return null;
}

function email_generate_verification_token(PDO $pdo, int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare('UPDATE users SET email_verification_token = ?, email_verification_sent_at = NOW() WHERE id = ?');
    $stmt->execute([$token, $userId]);
    return $token;
}

function email_send_verification(PDO $pdo, array $user, bool $refreshToken = true): bool
{
    if (!empty($user['email_verified_at'])) {
        return true;
    }

    $token = null;
    if (!$refreshToken && !empty($user['email_verification_token'])) {
        $token = (string)$user['email_verification_token'];
    }

    if ($token === null || $token === '') {
        $token = email_generate_verification_token($pdo, (int)$user['id']);
    }

    $link = app_url('/verify-email?token=' . urlencode($token));
    $name = email_user_display_name($user);
    $firstName = email_user_first_name($user);

    $tokens = [
        'user_first_name' => $firstName,
        'verification_link' => $link,
        'template_language' => email_user_locale($user),
    ];

    $locale = email_template_resolve_locale($tokens);
    $html = email_template_render('email_registration_validation', $tokens);
    $text = email_template_html_to_text($html);
    $subject = email_template_subject($html, 'Verify your email address', $locale);

    return send_app_email((string)$user['email'], $subject, $html, $text, [
        'to_name' => $name,
    ]);
}

function email_send_welcome(array $user): bool
{
    $name = email_user_display_name($user);
    $firstName = email_user_first_name($user);

    $tokens = [
        'user_first_name' => $firstName,
        'template_language' => email_user_locale($user),
    ];

    $locale = email_template_resolve_locale($tokens);
    $html = email_template_render('email_welcome', $tokens);
    $text = email_template_html_to_text($html);
    $subject = email_template_subject($html, 'Welcome to MyMoneyMap', $locale);

    return send_app_email((string)$user['email'], $subject, $html, $text, [
        'to_name' => $name,
    ]);
}

function email_send_registration_bundle(PDO $pdo, array $user): void
{
    try {
        email_send_verification($pdo, $user, true);
    } catch (Throwable $e) {
        error_log('[mail] Failed to send verification email: ' . $e->getMessage());
    }

    try {
        email_send_welcome($user);
    } catch (Throwable $e) {
        error_log('[mail] Failed to send welcome email: ' . $e->getMessage());
    }
}

function email_default_tips(): array
{
    return [
        [
            'title' => 'Categorise with intent',
            'body' => 'Categorise every transaction to understand where your money goes.',
            'link' => app_url('/transactions'),
        ],
        [
            'title' => 'Review weekly',
            'body' => 'Set a weekly review reminder to reconcile your spending and progress.',
            'link' => app_url('/reports/weekly'),
        ],
        [
            'title' => 'Automate your savings',
            'body' => 'Use scheduled payments to automate recurring bills and savings transfers.',
            'link' => app_url('/scheduled'),
        ],
    ];
}

function email_send_tips(array $user, ?array $tips = null): bool
{
    $tips = $tips && is_array($tips) ? $tips : email_default_tips();
    $name = email_user_display_name($user);
    $firstName = email_user_first_name($user);

    $normalized = [];
    foreach ($tips as $index => $tip) {
        if (is_array($tip)) {
            $title = trim((string)($tip['title'] ?? 'Tip ' . ($index + 1)));
            $body = trim((string)($tip['body'] ?? ($tip['text'] ?? '')));
            $link = trim((string)($tip['link'] ?? ($tip['href'] ?? '')));
        } else {
            $title = 'Tip ' . ($index + 1);
            $body = trim((string)$tip);
            $link = '';
        }

        if ($body === '') {
            continue;
        }

        $normalized[] = [
            'title' => $title,
            'body' => $body,
            'link' => $link !== '' ? $link : app_url('/learn'),
        ];
    }

    $defaults = email_default_tips();
    $i = 0;
    while (count($normalized) < 3 && $i < count($defaults)) {
        $normalized[] = $defaults[$i++];
    }

    $normalized = array_slice($normalized, 0, 3);

    $tokens = [
        'user_first_name' => $firstName,
        'tip_title_1' => $normalized[0]['title'] ?? 'Stay curious',
        'tip_body_1' => $normalized[0]['body'] ?? 'Explore MyMoneyMap to uncover insights tailored to your habits.',
        'tip_link_1' => $normalized[0]['link'] ?? app_url('/learn'),
        'tip_title_2' => $normalized[1]['title'] ?? 'Log consistently',
        'tip_body_2' => $normalized[1]['body'] ?? 'Frequent updates lead to better guidance and confidence in your data.',
        'tip_link_2' => $normalized[1]['link'] ?? app_url('/reports'),
        'tip_title_3' => $normalized[2]['title'] ?? 'Celebrate wins',
        'tip_body_3' => $normalized[2]['body'] ?? 'Acknowledge milestones to stay motivated and focused on long-term goals.',
        'tip_link_3' => $normalized[2]['link'] ?? app_url('/goals'),
        'promo_title' => 'Pro Guide: Build lasting money habits',
        'promo_body' => 'Download our expert playbook with checklists for budgeting, goal tracking, and investment readiness.',
        'promo_link' => app_url('/guides/pro'),
        'template_language' => email_user_locale($user),
    ];

    $locale = email_template_resolve_locale($tokens);
    $html = email_template_render('email_tips_and_tricks', $tokens);
    $text = email_template_html_to_text($html);
    $subject = email_template_subject($html, 'Tips & tricks for MyMoneyMap', $locale);

    return send_app_email((string)$user['email'], $subject, $html, $text, [
        'to_name' => $name,
    ]);
}


function email_send_cashflow_overspend(PDO $pdo, int $userId, array $status, DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd, float $previousSpent): bool
{
    $budget = max(0.0, (float)($status['budget'] ?? 0.0));
    $spent = max(0.0, (float)($status['spent'] ?? 0.0));
    if ($spent <= 0.0) {
        return false;
    }

    $user = email_load_user_profile($pdo, $userId);
    if (!$user) {
        return false;
    }

    $currency = (string)($status['currency'] ?? '');
    if ($currency === '') {
        $main = fx_user_main($pdo, $userId);
        $currency = $main !== '' ? $main : 'HUF';
    }

    $tolerance = 0.5;
    if ($budget > 0.0) {
        if ($spent <= $budget + $tolerance) {
            return false;
        }

        if ($previousSpent > $budget + $tolerance) {
            return false;
        }
    } else {
        if ($previousSpent > $tolerance) {
            return false;
        }
    }

    $ruleLabel = trim((string)($status['label'] ?? 'Cashflow rule'));
    if ($ruleLabel === '') {
        $ruleLabel = 'Cashflow rule';
    }

    $percent = max(0.0, (float)($status['percent'] ?? 0.0));
    $overAmount = $spent - $budget;
    if ($overAmount <= 0.01 && $budget > 0.0) {
        return false;
    }

    if ($overAmount < 0.0) {
        $overAmount = 0.0;
    }

    $periodLabel = $periodStart->format('F Y');
    if ($periodStart->format('Y-m') !== $periodEnd->format('Y-m')) {
        $periodLabel = email_format_period_label($periodStart, $periodEnd);
    }

    $displayName = email_user_display_name($user);
    $firstName = email_user_first_name($user);

    $percentFormatted = rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');
    if ($percentFormatted === '') {
        $percentFormatted = '0';
    }
    $percentFormatted .= '%';

    $budgetFormatted = email_format_amount($budget, $currency);
    $spentFormatted = email_format_amount($spent, $currency);
    $overFormatted = email_format_amount($overAmount, $currency);

    $overPlain = email_plaintext_amount($overAmount, $currency);
    $budgetPlain = email_plaintext_amount($budget, $currency);
    $spentPlain = email_plaintext_amount($spent, $currency);

    $overRatio = $budget > 0.0 ? ($overAmount / max($budget, 0.01)) : 1.0;

    if ($budget <= 0.0) {
        $primaryTip = 'Set a percentage for this rule so spending has a target each month.';
        $secondaryTip = 'Move purchases into categories with room or update the cashflow rule for a safer plan.';
    } elseif ($overRatio >= 0.25) {
        $primaryTip = 'Pause optional spending tied to this rule for the rest of the month to rebalance it.';
        $secondaryTip = 'Shift large one-off expenses into next month or another rule with available budget.';
    } elseif ($overRatio >= 0.1) {
        $primaryTip = 'Trim at least ' . $overFormatted . ' from upcoming discretionary spending to get back on plan.';
        $secondaryTip = 'Schedule a mid-month review and reallocate from rules that are still under budget.';
    } else {
        $primaryTip = 'Monitor the remaining days closely and log adjustments immediately to stay in control.';
        $secondaryTip = 'Check for subscriptions or renewals you can postpone until next month.';
    }

    $primaryTipText = html_entity_decode($primaryTip, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $secondaryTipText = html_entity_decode($secondaryTip, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $topCategories = $status['top_categories'] ?? [];
    $hiddenStyle = 'display:none; mso-hide:all; line-height:0; font-size:0; height:0; overflow:hidden;';

    $tokens = [
        'user_first_name' => $firstName,
        'period_label' => $periodLabel,
        'rule_label' => $ruleLabel,
        'rule_percent' => $percentFormatted,
        'budget_amount' => $budgetFormatted,
        'spent_amount' => $spentFormatted,
        'over_amount' => $overFormatted,
        'primary_tip' => $primaryTip,
        'secondary_tip' => $secondaryTip,
        'cta_url' => app_url('/cashflow'),
        'cta_label' => 'Review cashflow plan',
        'top_category_fallback_visibility' => $topCategories ? $hiddenStyle : '',
    ];

    for ($i = 1; $i <= 3; $i++) {
        $row = $topCategories[$i - 1] ?? null;
        if ($row) {
            $tokens['top_category_' . $i . '_label'] = (string)$row['label'];
            $tokens['top_category_' . $i . '_amount'] = email_format_amount((float)$row['amount'], $currency);
            $tokens['top_category_' . $i . '_visibility'] = '';
        } else {
            $tokens['top_category_' . $i . '_label'] = '—';
            $tokens['top_category_' . $i . '_amount'] = '—';
            $tokens['top_category_' . $i . '_visibility'] = $hiddenStyle;
        }
    }

    $tokens['template_language'] = email_user_locale($user);

    $locale = email_template_resolve_locale($tokens);
    $html = email_template_render('email_cashflow_overspend', $tokens);
    $text = email_template_html_to_text($html);
    $subject = email_template_subject($html, 'Heads-up: :rule is over budget', $locale, [
        'rule' => $ruleLabel,
    ]);

    return send_app_email((string)$user['email'], $subject, $html, $text, [
        'to_name' => $displayName,
    ]);
}

function email_send_feedback_new_alert(PDO $pdo, int $feedbackId): bool
{
    if ($feedbackId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT f.id, f.user_id, f.title, f.message, f.kind, f.severity, f.created_at, u.email, u.full_name FROM feedback f JOIN users u ON u.id = f.user_id WHERE f.id = ?');
    $stmt->execute([$feedbackId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return false;
    }

    $userRecord = [
        'email' => (string)($row['email'] ?? ''),
        'full_name' => $row['full_name'] ?? null,
        'full_name_plain' => ($row['full_name'] ?? null) ? pii_decrypt($row['full_name']) : '',
    ];

    $userName = email_user_display_name($userRecord);
    if ($userName === '') {
        $userName = 'Unknown user';
    }

    $userEmail = trim((string)($row['email'] ?? ''));
    if ($userEmail === '') {
        $userEmail = 'not provided';
    }

    $title = trim((string)($row['title'] ?? 'Feedback'));
    if ($title === '') {
        $title = 'Feedback';
    }

    $kind = ucfirst(strtolower((string)($row['kind'] ?? 'Idea')));
    $severityRaw = (string)($row['severity'] ?? '');
    $severity = $severityRaw !== '' ? ucfirst(strtolower($severityRaw)) : 'Not set';
    $message = trim((string)($row['message'] ?? ''));
    if ($message === '') {
        $message = '—';
    }

    $createdAt = (string)($row['created_at'] ?? 'now');
    try {
        $created = new DateTimeImmutable($createdAt);
    } catch (Exception $e) {
        $created = new DateTimeImmutable();
    }
    $submittedAt = $created->format('F j, Y H:i');

    $feedbackUrl = app_url('/feedback?highlight=' . $feedbackId);

    $tokens = [
        'user_display_name' => $userName,
        'user_email' => $userEmail,
        'feedback_title' => $title,
        'feedback_kind' => $kind,
        'feedback_severity' => $severity,
        'feedback_message' => $message,
        'feedback_url' => $feedbackUrl,
        'submitted_at' => $submittedAt,
    ];

    $html = email_template_render('email_feedback_new', $tokens);

    $text = "New feedback submitted by {$userName} ({$userEmail}).\n\n"
        . "Title: {$title}\n"
        . "Type: {$kind}\n"
        . "Severity: {$severity}\n"
        . "Received: {$submittedAt}\n\n"
        . "Message:\n{$message}\n\n"
        . 'Open in MyMoneyMap: ' . $feedbackUrl . "\n";

    return send_app_email(email_feedback_inbox_address(), 'New feedback: ' . $title, $html, $text, [
        'to_name' => 'Feedback team',
    ]);
}

function email_send_feedback_resolved(PDO $pdo, int $feedbackId): bool
{
    if ($feedbackId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT id, user_id, title, updated_at FROM feedback WHERE id = ?');
    $stmt->execute([$feedbackId]);
    $feedback = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$feedback) {
        return false;
    }

    $user = email_load_user_profile($pdo, (int)$feedback['user_id']);
    if (!$user) {
        return false;
    }

    $displayName = email_user_display_name($user);
    $firstName = email_user_first_name($user);
    $title = trim((string)($feedback['title'] ?? 'Your feedback'));
    if ($title === '') {
        $title = 'Your feedback';
    }

    $updatedAt = (string)($feedback['updated_at'] ?? 'now');
    try {
        $resolved = new DateTimeImmutable($updatedAt);
    } catch (Exception $e) {
        $resolved = new DateTimeImmutable();
    }

    $resolvedLabel = $resolved->format('F j, Y');

    $resolutionSummary = 'We applied changes that address the issue you reported.';
    $resolutionNextStep = 'Open MyMoneyMap to confirm everything works as expected. Reopen the feedback if you need more help.';

    $tokens = [
        'user_first_name' => $firstName,
        'feedback_title' => $title,
        'resolution_summary' => $resolutionSummary,
        'resolution_next_step' => $resolutionNextStep,
        'cta_url' => app_url('/feedback?highlight=' . (int)$feedback['id']),
        'cta_label' => 'View feedback',
        'resolved_at' => $resolvedLabel,
        'template_language' => email_user_locale($user),
    ];

    $locale = email_template_resolve_locale($tokens);
    $html = email_template_render('email_feedback_resolved', $tokens);
    $text = email_template_html_to_text($html);
    $subject = email_template_subject($html, 'We resolved your feedback: :title', $locale, [
        'title' => $title,
    ]);

    return send_app_email((string)$user['email'], $subject, $html, $text, [
        'to_name' => $displayName,
    ]);
}

function email_collect_period_summary(PDO $pdo, int $userId, DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $stmt = $pdo->prepare('SELECT t.kind, t.amount, t.currency, t.occurred_on, t.category_id, c.label AS category_label ' .
        'FROM transactions t LEFT JOIN categories c ON c.id = t.category_id ' .
        'WHERE t.user_id = ? AND t.occurred_on BETWEEN ?::date AND ?::date ORDER BY t.occurred_on');
    $stmt->execute([$userId, $start->format('Y-m-d'), $end->format('Y-m-d')]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $mainCurrency = fx_user_main($pdo, $userId);
    $incomeTotal = 0.0;
    $spendingTotal = 0.0;
    $incomeCount = 0;
    $spendingCount = 0;
    $categoryTotals = [];
    $categoryAmountsById = [];
    $periodTotals = [];

    foreach ($rows as $row) {
        $amount = (float)$row['amount'];
        $currency = $row['currency'] ?: $mainCurrency;
        $dateString = $row['occurred_on'] ?: $end->format('Y-m-d');
        $dateObj = DateTimeImmutable::createFromFormat('Y-m-d', $dateString) ?: $end;
        $converted = $currency === $mainCurrency ? $amount : fx_convert($pdo, $amount, $currency, $mainCurrency, $dateString);
        $kind = strtolower(trim((string)($row['kind'] ?? '')));
        $absolute = abs($converted);
        $periodKey = $dateObj->format('Y-m');

        if (!isset($periodTotals[$periodKey])) {
            $periodTotals[$periodKey] = ['income' => 0.0, 'spending' => 0.0];
        }

        if ($kind === 'income') {
            $incomeTotal += $absolute;
            $incomeCount++;
            $periodTotals[$periodKey]['income'] += $absolute;
        } elseif ($kind === 'spending') {
            $spendingTotal += $absolute;
            $spendingCount++;
            $periodTotals[$periodKey]['spending'] += $absolute;

            $categoryId = (int)($row['category_id'] ?? 0);
            $label = trim((string)($row['category_label'] ?? 'Other')) ?: 'Other';
            $key = $categoryId > 0 ? 'id:' . $categoryId : 'label:' . strtolower($label);

            if (!isset($categoryTotals[$key])) {
                $categoryTotals[$key] = [
                    'id' => $categoryId > 0 ? $categoryId : null,
                    'label' => $label,
                    'amount' => 0.0,
                ];
            }

            $categoryTotals[$key]['amount'] += $absolute;
            if ($categoryId > 0) {
                $categoryAmountsById[$categoryId] = ($categoryAmountsById[$categoryId] ?? 0.0) + $absolute;
            }
        }
    }

    $categoryList = array_values($categoryTotals);
    usort($categoryList, static fn(array $a, array $b) => $b['amount'] <=> $a['amount']);
    ksort($periodTotals);

    return [
        'currency' => $mainCurrency,
        'income_total' => $incomeTotal,
        'spending_total' => $spendingTotal,
        'income_count' => $incomeCount,
        'spending_count' => $spendingCount,
        'net' => $incomeTotal - $spendingTotal,
        'transaction_count' => count($rows),
        'category_totals' => $categoryList,
        'category_amounts_by_id' => $categoryAmountsById,
        'period_totals' => $periodTotals,
        'top_category' => $categoryList[0] ?? null,
    ];
}

function email_format_amount(float $amount, string $currency): string
{
    $sign = $amount < 0 ? '-' : '';
    $value = number_format(abs($amount), 2, '.', ' ');
    $code = $currency !== '' ? strtoupper($currency) : '';

    if ($code === '') {
        return $sign . $value;
    }

    return $sign . $value . '&nbsp;' . $code;
}

function email_plaintext_amount(float $amount, string $currency): string
{
    return html_entity_decode(email_format_amount($amount, $currency), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function email_format_period_label(DateTimeImmutable $start, DateTimeImmutable $end): string
{
    $startYear = $start->format('Y');
    $endYear = $end->format('Y');
    $startLabel = $start->format('M j');
    $endLabel = $end->format('M j, Y');

    if ($startYear !== $endYear) {
        $startLabel .= ', ' . $startYear;
    }

    return $startLabel . '–' . $endLabel;
}

function email_list_join(array $items, string $conjunction = 'and'): string
{
    $items = array_values(array_filter(array_map('trim', $items), static function ($value): bool {
        return $value !== '';
    }));

    $count = count($items);
    if ($count === 0) {
        return '';
    }

    if ($count === 1) {
        return $items[0];
    }

    if ($count === 2) {
        return $items[0] . ' ' . $conjunction . ' ' . $items[1];
    }

    $last = array_pop($items);

    return implode(', ', $items) . ', ' . $conjunction . ' ' . $last;
}

function email_prepare_top_categories(array $summary, int $limit = 5): array
{
    $total = max(0.0, (float)($summary['spending_total'] ?? 0.0));
    $currency = (string)($summary['currency'] ?? '');
    $categories = $summary['category_totals'] ?? [];
    $result = [];

    foreach (array_slice($categories, 0, $limit) as $category) {
        $amount = (float)$category['amount'];
        $percent = $total > 0 ? round(($amount / $total) * 100) : 0;
        $result[] = [
            'label' => (string)$category['label'],
            'amount' => $amount,
            'amount_formatted' => email_format_amount($amount, $currency),
            'percent_formatted' => $percent . '%',
        ];
    }

    return $result;
}

function email_collect_budget_rows(PDO $pdo, int $userId, array $summary): array
{
    $currency = (string)($summary['currency'] ?? '');
    $incomeTotal = (float)($summary['income_total'] ?? 0.0);
    $categoryAmounts = $summary['category_amounts_by_id'] ?? [];

    $rulesStmt = $pdo->prepare('SELECT id, label, percent FROM cashflow_rules WHERE user_id = ? ORDER BY percent DESC, id');
    $rulesStmt->execute([$userId]);
    $rules = $rulesStmt->fetchAll(PDO::FETCH_ASSOC);

    $categoryRuleStmt = $pdo->prepare('SELECT id, cashflow_rule_id FROM categories WHERE user_id = ?');
    $categoryRuleStmt->execute([$userId]);
    $categoryRules = [];
    foreach ($categoryRuleStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $categoryRules[(int)$row['id']] = (int)($row['cashflow_rule_id'] ?? 0);
    }

    $rows = [];
    foreach ($rules as $rule) {
        $ruleId = (int)$rule['id'];
        $label = trim((string)$rule['label']);
        $percent = max(0.0, (float)$rule['percent']);
        $planned = max(0.0, round(($percent / 100.0) * $incomeTotal, 2));
        $actual = 0.0;

        foreach ($categoryAmounts as $categoryId => $amount) {
            if (($categoryRules[$categoryId] ?? 0) === $ruleId) {
                $actual += $amount;
            }
        }

        $diff = $planned - $actual;
        if ($planned <= 0.0 && $actual <= 0.0) {
            continue;
        }

        if ($planned <= 0.0) {
            $status = 'Track plan in app';
        } elseif ($diff > 0.01) {
            $status = 'Under by ' . email_format_amount($diff, $currency);
        } elseif ($diff < -0.01) {
            $status = 'Over by ' . email_format_amount(abs($diff), $currency);
        } else {
            $status = 'On target';
        }

        $rows[] = [
            'label' => $label,
            'planned' => email_format_amount($planned, $currency),
            'actual' => email_format_amount($actual, $currency),
            'status' => $status,
            'actual_value' => $actual,
        ];
    }

    if (!$rows) {
        $topCategories = email_prepare_top_categories($summary, 3);
        foreach ($topCategories as $category) {
            $rows[] = [
                'label' => $category['label'],
                'planned' => '—',
                'actual' => $category['amount_formatted'],
                'status' => 'Review in dashboard',
                'actual_value' => $category['amount'],
            ];
        }
    }

    usort($rows, static fn(array $a, array $b) => $b['actual_value'] <=> $a['actual_value']);

    return array_slice(array_map(static function (array $row): array {
        unset($row['actual_value']);
        return $row;
    }, $rows), 0, 3);
}

function email_collect_cashflow_rule_status(PDO $pdo, int $userId, int $ruleId, DateTimeImmutable $start, DateTimeImmutable $end): ?array
{
    if ($ruleId <= 0) {
        return null;
    }

    $ruleStmt = $pdo->prepare('SELECT id, label, percent FROM cashflow_rules WHERE id = ? AND user_id = ?');
    $ruleStmt->execute([$ruleId, $userId]);
    $rule = $ruleStmt->fetch(PDO::FETCH_ASSOC);

    if (!$rule) {
        return null;
    }

    $label = trim((string)($rule['label'] ?? ''));
    if ($label === '') {
        $label = 'Cashflow rule';
    }

    $percent = max(0.0, (float)($rule['percent'] ?? 0.0));

    $mainCurrency = fx_user_main($pdo, $userId);
    if (!is_string($mainCurrency) || $mainCurrency === '') {
        $mainCurrency = 'HUF';
    }

    $startDate = $start->format('Y-m-d');
    $endDate = $end->format('Y-m-d');

    $incomeStmt = $pdo->prepare('SELECT amount, currency, occurred_on FROM transactions WHERE user_id = ? AND kind = ? AND occurred_on BETWEEN ?::date AND ?::date');
    $incomeStmt->execute([$userId, 'income', $startDate, $endDate]);
    $incomeTotal = 0.0;
    foreach ($incomeStmt->fetchAll(PDO::FETCH_ASSOC) as $incomeRow) {
        $amount = (float)($incomeRow['amount'] ?? 0.0);
        $currency = (string)($incomeRow['currency'] ?? '');
        $occurred = (string)($incomeRow['occurred_on'] ?? $startDate);

        if ($currency === '' || strtoupper($currency) === strtoupper($mainCurrency)) {
            $incomeTotal += max(0.0, abs($amount));
        } else {
            $converted = fx_convert($pdo, $amount, $currency, $mainCurrency, $occurred);
            $incomeTotal += max(0.0, abs($converted));
        }
    }

    $planned = $percent > 0.0 ? round(($percent / 100.0) * $incomeTotal, 2) : 0.0;

    $spentStmt = $pdo->prepare('SELECT t.amount, t.currency, t.occurred_on, c.label FROM transactions t JOIN categories c ON c.id = t.category_id WHERE t.user_id = ? AND t.kind = ? AND c.cashflow_rule_id = ? AND t.occurred_on BETWEEN ?::date AND ?::date');
    $spentStmt->execute([$userId, 'spending', $ruleId, $startDate, $endDate]);
    $spentTotal = 0.0;
    $categories = [];

    foreach ($spentStmt->fetchAll(PDO::FETCH_ASSOC) as $spentRow) {
        $amount = (float)($spentRow['amount'] ?? 0.0);
        $currency = (string)($spentRow['currency'] ?? '');
        $occurred = (string)($spentRow['occurred_on'] ?? $startDate);
        $labelRaw = trim((string)($spentRow['label'] ?? ''));
        $catLabel = $labelRaw !== '' ? $labelRaw : 'Other';

        if ($currency === '' || strtoupper($currency) === strtoupper($mainCurrency)) {
            $converted = $amount;
        } else {
            $converted = fx_convert($pdo, $amount, $currency, $mainCurrency, $occurred);
        }

        $absolute = max(0.0, abs($converted));
        $spentTotal += $absolute;
        $categories[$catLabel] = ($categories[$catLabel] ?? 0.0) + $absolute;
    }

    $topCategories = [];
    if ($categories) {
        arsort($categories);
        foreach (array_slice($categories, 0, 5, true) as $catLabel => $amount) {
            $topCategories[] = [
                'label' => $catLabel,
                'amount' => $amount,
            ];
        }
    }

    return [
        'rule_id' => (int)$rule['id'],
        'label' => $label,
        'percent' => $percent,
        'budget' => max(0.0, $planned),
        'spent' => $spentTotal,
        'currency' => $mainCurrency,
        'top_categories' => $topCategories,
    ];
}

function email_collect_emergency_status(PDO $pdo, int $userId, string $defaultCurrency): array
{
    $stmt = $pdo->prepare('SELECT total, target_amount, currency FROM emergency_fund WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return [
            'status' => 'No emergency fund yet.',
            'note' => 'Set a target to start building your safety net.',
            'total' => 0.0,
            'target' => 0.0,
            'currency' => $defaultCurrency,
        ];
    }

    $currency = trim((string)($row['currency'] ?? ''));
    if ($currency === '') {
        $currency = $defaultCurrency;
    }

    $total = max(0.0, (float)($row['total'] ?? 0.0));
    $target = max(0.0, (float)($row['target_amount'] ?? 0.0));
    $statusCurrency = $currency !== '' ? $currency : $defaultCurrency;

    if ($target > 0.0) {
        $percent = min(100.0, ($total / max($target, 1e-9)) * 100.0);
        $status = email_format_amount($total, $statusCurrency) . ' of ' . email_format_amount($target, $statusCurrency);
        $status .= ' (' . round($percent) . '%)';

        if ($percent >= 100.0) {
            $note = 'Emergency fund goal achieved—keep it topped up.';
        } else {
            $gap = max(0.0, $target - $total);
            $note = $gap > 0.0
                ? email_format_amount($gap, $statusCurrency) . ' to reach your target.'
                : 'Stay consistent to build an extra buffer.';
        }
    } else {
        $status = email_format_amount($total, $statusCurrency) . ' saved.';
        $note = $total > 0.0
            ? 'Set a target to track progress automatically.'
            : 'Set a target to start building your safety net.';
    }

    return [
        'status' => $status,
        'note' => $note,
        'total' => $total,
        'target' => $target,
        'currency' => $statusCurrency,
    ];
}

function email_project_emergency_cashflow(PDO $pdo, int $userId, string $currency, DateTimeImmutable $startMonth, int $months): array
{
    $currency = strtoupper(trim($currency));
    if ($currency === '') {
        $main = fx_user_main($pdo, $userId);
        $currency = $main !== '' ? $main : 'USD';
    }

    $months = max(1, min(6, $months));

    $monthsMap = [];
    $cursor = $startMonth;
    for ($i = 0; $i < $months; $i++) {
        $key = $cursor->format('Y-m');
        $monthsMap[$key] = [
            'label' => $cursor->format('F Y'),
            'start' => $cursor->format('Y-m-01'),
            'end' => $cursor->format('Y-m-t'),
            'income' => 0.0,
            'obligations' => 0.0,
            'leftover' => 0.0,
        ];
        $cursor = $cursor->modify('+1 month');
        if (!$cursor instanceof DateTimeImmutable) {
            break;
        }
    }

    if (!$monthsMap) {
        return [];
    }

    $keys = array_keys($monthsMap);
    $rangeStart = $monthsMap[$keys[0]]['start'];
    $rangeEnd = $monthsMap[$keys[count($keys) - 1]]['end'];

    $scheduledStmt = $pdo->prepare('SELECT amount, currency, next_due, rrule FROM scheduled_payments WHERE user_id = ?');
    $scheduledStmt->execute([$userId]);

    foreach ($scheduledStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $amount = (float)($row['amount'] ?? 0.0);
        if ($amount <= 0.0) {
            continue;
        }

        $rowCurrency = strtoupper(trim((string)($row['currency'] ?? '')));
        if ($rowCurrency === '') {
            $rowCurrency = $currency;
        }

        $dtStart = trim((string)($row['next_due'] ?? ''));
        if ($dtStart === '') {
            continue;
        }

        $rrule = trim((string)($row['rrule'] ?? ''));
        $occurrences = rrule_expand($dtStart, $rrule, $rangeStart, $rangeEnd);
        if (!$occurrences || !is_array($occurrences)) {
            continue;
        }

        foreach ($occurrences as $dueDate) {
            $key = substr((string)$dueDate, 0, 7);
            if (!isset($monthsMap[$key])) {
                continue;
            }

            $converted = $rowCurrency === $currency
                ? $amount
                : fx_convert($pdo, $amount, $rowCurrency, $currency, (string)$dueDate);

            $monthsMap[$key]['obligations'] += max(0.0, (float)$converted);
        }
    }

    $incomeStmt = $pdo->prepare("SELECT amount, currency, valid_from, COALESCE(valid_to, '') AS valid_to FROM basic_incomes WHERE user_id = ?");
    $incomeStmt->execute([$userId]);
    $incomes = $incomeStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($monthsMap as $key => &$month) {
        $start = $month['start'];
        $end = $month['end'];

        foreach ($incomes as $income) {
            $amount = (float)($income['amount'] ?? 0.0);
            if ($amount <= 0.0) {
                continue;
            }

            $validFrom = trim((string)($income['valid_from'] ?? ''));
            if ($validFrom === '' || $validFrom > $end) {
                continue;
            }

            $validTo = trim((string)($income['valid_to'] ?? ''));
            if ($validTo !== '' && $validTo < $start) {
                continue;
            }

            $incomeCurrency = strtoupper(trim((string)($income['currency'] ?? '')));
            if ($incomeCurrency === '') {
                $incomeCurrency = $currency;
            }

            $converted = $incomeCurrency === $currency
                ? $amount
                : fx_convert($pdo, $amount, $incomeCurrency, $currency, $start);

            $month['income'] += max(0.0, (float)$converted);
        }

        $month['leftover'] = max(0.0, (float)$month['income'] - (float)$month['obligations']);
    }
    unset($month);

    return array_values($monthsMap);
}

function email_prepare_emergency_replenishment_plan(
    PDO $pdo,
    int $userId,
    float $withdrawAmount,
    string $currency,
    DateTimeImmutable $startMonth,
    int $monthsToProject = 3
): array {
    $withdrawAmount = max(0.0, $withdrawAmount);
    $currency = strtoupper(trim($currency));
    if ($currency === '') {
        $main = fx_user_main($pdo, $userId);
        $currency = $main !== '' ? $main : 'USD';
    }

    $monthsToProject = max(1, min(6, $monthsToProject));

    $cashflowMonths = email_project_emergency_cashflow($pdo, $userId, $currency, $startMonth, $monthsToProject);
    if (!$cashflowMonths) {
        $cursor = $startMonth;
        for ($i = 0; $i < $monthsToProject; $i++) {
            $cashflowMonths[] = [
                'label' => $cursor->format('F Y'),
                'income' => 0.0,
                'obligations' => 0.0,
                'leftover' => 0.0,
            ];
            $cursor = $cursor->modify('+1 month');
            if (!$cursor instanceof DateTimeImmutable) {
                break;
            }
        }
    }

    $availableMonths = count($cashflowMonths);
    $maxWindow = max(1, min(3, $availableMonths));
    $defaultPlanMonths = $maxWindow >= 2 ? 2 : 1;

    $planMonths = $defaultPlanMonths;
    $feasible = $withdrawAmount <= 0.0;

    if ($withdrawAmount > 0.0) {
        for ($candidate = $defaultPlanMonths; $candidate <= $maxWindow; $candidate++) {
            $slice = array_slice($cashflowMonths, 0, $candidate);
            $minLeftover = null;

            foreach ($slice as $month) {
                $leftover = max(0.0, (float)($month['leftover'] ?? 0.0));
                $minLeftover = $minLeftover === null ? $leftover : min($minLeftover, $leftover);
            }

            if ($minLeftover === null || $minLeftover <= 0.0) {
                continue;
            }

            $monthlyNeed = $withdrawAmount / $candidate;
            if ($monthlyNeed <= $minLeftover + 0.01) {
                $planMonths = $candidate;
                $feasible = true;
                break;
            }
        }

        if (!$feasible && $maxWindow > $defaultPlanMonths) {
            $planMonths = $maxWindow;
        }
    }

    $slice = array_slice($cashflowMonths, 0, $planMonths);
    if (!$slice) {
        $slice = $cashflowMonths ? [$cashflowMonths[0]] : [];
        $planMonths = count($slice);
    }

    $monthlyAmount = $planMonths > 0 ? $withdrawAmount / $planMonths : 0.0;
    $rows = [];
    $remaining = $withdrawAmount;
    $allocated = 0.0;
    $minLeftover = null;

    foreach ($slice as $index => $month) {
        $label = (string)($month['label'] ?? '');
        $available = max(0.0, (float)($month['leftover'] ?? 0.0));
        $suggested = 0.0;

        if ($withdrawAmount > 0.0) {
            if ($feasible && $planMonths > 0) {
                if ($index === $planMonths - 1) {
                    $suggested = $remaining;
                } else {
                    $suggested = $monthlyAmount;
                }
            } else {
                $remainingMonths = max(1, $planMonths - $index);
                $evenSplit = $remainingMonths > 0 ? $remaining / $remainingMonths : $remaining;
                $suggested = min($available, $evenSplit);
            }
        }

        if ($suggested > $available) {
            $suggested = $available;
        }
        if ($suggested < 0.0) {
            $suggested = 0.0;
        }

        $remaining = max(0.0, $remaining - $suggested);
        $allocated += $suggested;

        $rows[] = [
            'label' => $label,
            'available' => $available,
            'available_formatted' => email_format_amount($available, $currency),
            'available_plain' => email_plaintext_amount($available, $currency),
            'suggested' => $suggested,
            'suggested_formatted' => email_format_amount($suggested, $currency),
            'suggested_plain' => email_plaintext_amount($suggested, $currency),
        ];

        $minLeftover = $minLeftover === null ? $available : min($minLeftover, $available);
    }

    $shortfall = max(0.0, $withdrawAmount - $allocated);

    $headline = $planMonths > 1 ? 'Rebuild over ' . $planMonths . ' months' : 'Rebuild next month';

    if ($withdrawAmount <= 0.0) {
        $summary = 'No repayment needed—your balance stayed level after this withdrawal.';
    } elseif ($shortfall > 0.01) {
        $summary = 'We can stage ' . email_format_amount($allocated, $currency) . ' over ' . $planMonths
            . ' months—plan extra transfers to restore the full ' . email_format_amount($withdrawAmount, $currency) . '.';
    } elseif ($planMonths > 1) {
        $finalLabel = $rows ? ($rows[$planMonths - 1]['label'] ?? '') : $startMonth->format('F Y');
        $summary = 'Set aside ' . email_format_amount($monthlyAmount, $currency) . ' per month to replace the '
            . email_format_amount($withdrawAmount, $currency) . ' by ' . $finalLabel . '.';
    } else {
        $summary = 'Set aside ' . email_format_amount($withdrawAmount, $currency) . ' next month to restore your emergency fund.';
    }

    $detail = '';
    $detailStyle = 'color:#555555;';

    if ($withdrawAmount > 0.0) {
        if ($allocated <= 0.0) {
            $detail = 'We could not detect spare cash after bills—log your income and recurring expenses to refine this plan.';
            $detailStyle = 'color:#B45309;';
        } elseif ($shortfall > 0.01) {
            $detail = 'Recurring income covers about ' . email_format_amount($allocated, $currency)
                . ' of this withdrawal. Trim expenses or extend the plan to close the gap.';
            $detailStyle = 'color:#B45309;';
        } elseif ($minLeftover !== null && $minLeftover > 0.0) {
            $detail = 'We estimate at least ' . email_format_amount($minLeftover, $currency)
                . ' left after bills in each month of this plan.';
        }
    }

    $detailVisible = $detail !== '';

    return [
        'headline' => $headline,
        'summary' => $summary,
        'detail' => $detail,
        'detail_style' => $detailStyle,
        'detail_visible' => $detailVisible,
        'rows' => $rows,
        'plan_months' => $planMonths,
        'feasible' => $feasible && $shortfall <= 0.01,
        'shortfall' => $shortfall,
        'allocated' => $allocated,
        'monthly_amount' => $monthlyAmount,
        'month_labels' => array_column($rows, 'label'),
        'min_leftover' => $minLeftover ?? 0.0,
    ];
}

function email_calculate_next_emergency_goal(PDO $pdo, int $userId, float $currentTarget, string $currency): array
{
    $efCurrency = strtoupper(trim($currency));
    $mainCurrency = fx_user_main($pdo, $userId);

    if ($mainCurrency === '') {
        $mainCurrency = $efCurrency !== '' ? $efCurrency : 'USD';
    }

    if ($efCurrency === '') {
        $efCurrency = $mainCurrency;
    }

    $firstNextMonth = date('Y-m-01', strtotime('first day of next month'));
    $lastNextMonth = date('Y-m-t', strtotime($firstNextMonth));

    $stmt = $pdo->prepare('SELECT amount, currency, next_due, rrule FROM scheduled_payments WHERE user_id = ?');
    $stmt->execute([$userId]);

    $monthlyNeedsEf = 0.0;
    $monthlyNeedsMain = 0.0;

    foreach ($stmt as $row) {
        $amount = (float)($row['amount'] ?? 0.0);
        if ($amount <= 0.0) {
            continue;
        }

        $rowCurrency = strtoupper(trim((string)($row['currency'] ?? '')));
        if ($rowCurrency === '') {
            $rowCurrency = $efCurrency;
        }

        $dtstart = trim((string)($row['next_due'] ?? ''));
        if ($dtstart === '') {
            continue;
        }

        $rrule = trim((string)($row['rrule'] ?? ''));
        $occurrences = rrule_expand($dtstart, $rrule, $firstNextMonth, $lastNextMonth);
        if (!$occurrences) {
            continue;
        }

        foreach ($occurrences as $dueDate) {
            $monthlyNeedsEf += $rowCurrency === $efCurrency
                ? $amount
                : fx_convert($pdo, $amount, $rowCurrency, $efCurrency, $dueDate);
            $monthlyNeedsMain += $rowCurrency === $mainCurrency
                ? $amount
                : fx_convert($pdo, $amount, $rowCurrency, $mainCurrency, $dueDate);
        }
    }

    $today = date('Y-m-d');
    $usd1kEf = fx_convert($pdo, 1000.0, 'USD', $efCurrency, $today);
    $usd1kMain = fx_convert($pdo, 1000.0, 'USD', $mainCurrency, $today);

    $approx = static function (float $x, float $y): bool {
        if ($x <= 0.0 || $y <= 0.0) {
            return false;
        }

        return (abs($x - $y) / max($x, $y)) <= 0.15;
    };

    if ($currentTarget <= 0.0) {
        return [
            'label' => 'Set your starter cushion',
            'note' => '≈ $1,000 gets your emergency fund off the ground.',
            'amount' => $usd1kEf,
            'amount_currency' => $efCurrency,
            'amount_formatted' => email_format_amount($usd1kEf, $efCurrency),
            'equivalent' => $usd1kMain,
            'equivalent_currency' => $mainCurrency,
            'equivalent_formatted' => $mainCurrency !== $efCurrency ? email_format_amount($usd1kMain, $mainCurrency) : '',
            'months' => null,
        ];
    }

    $months = null;
    if ($monthlyNeedsEf > 0.0) {
        if ($approx($currentTarget, $usd1kEf)) {
            $months = 3;
        } else {
            $asMonths = (int)floor(($currentTarget / max($monthlyNeedsEf, 1e-9)) + 0.00001);
            $months = max(4, $asMonths + 1);
        }
    }

    if ($months !== null && $months > 0 && $monthlyNeedsEf > 0.0) {
        if ($months > 9) {
            return [
                'label' => 'You\'re covered',
                'note' => 'You already have roughly 9 months saved—shift focus to investing.',
                'amount' => null,
                'amount_currency' => $efCurrency,
                'amount_formatted' => '',
                'equivalent' => null,
                'equivalent_currency' => $mainCurrency,
                'equivalent_formatted' => '',
                'months' => $months,
            ];
        }

        $amountEf = $monthlyNeedsEf * $months;
        $amountMain = $monthlyNeedsMain * $months;

        return [
            'label' => sprintf('%d months of needs', $months),
            'note' => sprintf('%d× your scheduled bills (run-rate)', $months),
            'amount' => $amountEf,
            'amount_currency' => $efCurrency,
            'amount_formatted' => email_format_amount($amountEf, $efCurrency),
            'equivalent' => $amountMain,
            'equivalent_currency' => $mainCurrency,
            'equivalent_formatted' => ($mainCurrency !== $efCurrency && $amountMain > 0.0)
                ? email_format_amount($amountMain, $mainCurrency)
                : '',
            'months' => $months,
        ];
    }

    if ($monthlyNeedsEf <= 0.0) {
        return [
            'label' => 'Estimate your essentials next',
            'note' => 'Add scheduled bills to calculate the next months-of-expenses target automatically.',
            'amount' => null,
            'amount_currency' => $efCurrency,
            'amount_formatted' => '',
            'equivalent' => null,
            'equivalent_currency' => $mainCurrency,
            'equivalent_formatted' => '',
            'months' => null,
        ];
    }

    return [
        'label' => 'Keep building momentum',
        'note' => 'Stay consistent this month—we will refresh your next milestone after the latest activity settles.',
        'amount' => null,
        'amount_currency' => $efCurrency,
        'amount_formatted' => '',
        'equivalent' => null,
        'equivalent_currency' => $mainCurrency,
        'equivalent_formatted' => '',
        'months' => $months,
    ];
}

function email_collect_goal_status(PDO $pdo, int $userId, string $defaultCurrency): array
{
    $stmt = $pdo->prepare('SELECT title, status, target_amount, current_amount, currency FROM goals WHERE user_id = ?');
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        return [
            'status' => 'Goal tracking ready.',
            'note' => 'Create a goal to give your savings a mission.',
        ];
    }

    $active = 0;
    $paused = 0;
    $completed = 0;
    $topActive = null;
    $recentCompleted = null;

    foreach ($rows as $row) {
        $status = strtolower((string)($row['status'] ?? ''));
        $target = max(0.0, (float)($row['target_amount'] ?? 0.0));
        $current = max(0.0, (float)($row['current_amount'] ?? 0.0));
        $progress = $target > 0.0 ? min(100.0, ($current / max($target, 1e-9)) * 100.0) : null;

        if (in_array($status, ['done', 'completed'], true) || ($progress !== null && $progress >= 100.0)) {
            $completed++;
            if ($recentCompleted === null) {
                $recentCompleted = trim((string)($row['title'] ?? ''));
            }
            continue;
        }

        if ($status === 'paused') {
            $paused++;
        } else {
            $active++;
        }

        if ($progress !== null) {
            if ($topActive === null || $progress > $topActive['progress']) {
                $topActive = [
                    'title' => trim((string)($row['title'] ?? '')),
                    'progress' => $progress,
                ];
            }
        }
    }

    $parts = [];
    if ($active > 0) {
        $parts[] = $active . ' active';
    }
    if ($paused > 0) {
        $parts[] = $paused . ' paused';
    }
    if ($completed > 0) {
        $parts[] = $completed . ' done';
    }

    $statusLine = $parts ? implode(' · ', $parts) : 'Goal tracking ready.';

    if ($recentCompleted) {
        $note = 'Recent win: ' . $recentCompleted;
    } elseif ($topActive) {
        $note = 'Closest to target: ' . $topActive['title'] . ' at ' . round($topActive['progress']) . '%.';
    } elseif ($paused > 0) {
        $note = 'Paused goals are waiting for your attention.';
    } else {
        $note = 'Create a goal to give your savings a mission.';
    }

    return [
        'status' => $statusLine,
        'note' => $note,
    ];
}

function email_collect_loan_status(PDO $pdo, int $userId, string $defaultCurrency): array
{
    $stmt = $pdo->prepare('SELECT name, principal, balance, currency FROM loans WHERE user_id = ?');
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        return [
            'status' => 'No loans tracked.',
            'note' => 'Add your loans to monitor payoff progress.',
        ];
    }

    $active = 0;
    $closed = 0;
    $totalBalance = 0.0;
    $currency = '';
    $topLoan = null;

    foreach ($rows as $row) {
        $balance = $row['balance'];
        $principal = max(0.0, (float)($row['principal'] ?? 0.0));
        $currentBalance = $balance !== null ? max(0.0, (float)$balance) : $principal;
        $loanCurrency = trim((string)($row['currency'] ?? ''));
        if ($currency === '' && $loanCurrency !== '') {
            $currency = $loanCurrency;
        }

        if ($currentBalance <= 0.01) {
            $closed++;
            continue;
        }

        $active++;
        $totalBalance += $currentBalance;

        if ($principal > 0.0) {
            $progress = max(0.0, min(100.0, 100.0 - ($currentBalance / max($principal, 1e-9)) * 100.0));
            if ($topLoan === null || $progress > $topLoan['progress']) {
                $topLoan = [
                    'title' => trim((string)($row['name'] ?? '')),
                    'progress' => $progress,
                ];
            }
        }
    }

    if ($active === 0) {
        $status = 'All loans are paid off.';
        $note = $closed > 0 ? $closed . ' loan(s) fully repaid.' : 'Add your loans to monitor payoff progress.';
    } else {
        $statusCurrency = $currency !== '' ? $currency : $defaultCurrency;
        $status = 'Outstanding: ' . email_format_amount($totalBalance, $statusCurrency) . ' across ' . $active . ' loan(s).';
        if ($topLoan) {
            $note = 'Best progress: ' . $topLoan['title'] . ' ' . round($topLoan['progress']) . '% repaid.';
        } elseif ($closed > 0) {
            $note = $closed . ' loan(s) already cleared.';
        } else {
            $note = 'Plan extra payments to accelerate progress.';
        }
    }

    return [
        'status' => $status,
        'note' => $note,
    ];
}

function email_collect_financial_focus(PDO $pdo, int $userId, string $defaultCurrency): array
{
    return [
        'emergency' => email_collect_emergency_status($pdo, $userId, $defaultCurrency),
        'goals' => email_collect_goal_status($pdo, $userId, $defaultCurrency),
        'loans' => email_collect_loan_status($pdo, $userId, $defaultCurrency),
    ];
}

function email_goal_is_completed_state(array $goal): bool
{
    $status = strtolower((string)($goal['status'] ?? ''));
    if (in_array($status, ['done', 'completed'], true)) {
        return true;
    }

    $target = max(0.0, (float)($goal['target_amount'] ?? 0.0));
    $current = max(0.0, (float)($goal['current_amount'] ?? 0.0));

    return $target > 0.0 && $current >= $target;
}

function email_maybe_send_goal_completion(PDO $pdo, int $userId, int $goalId, ?array $previous = null): void
{
    $stmt = $pdo->prepare('SELECT title, target_amount, current_amount, currency, status FROM goals WHERE id = ? AND user_id = ?');
    $stmt->execute([$goalId, $userId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        return;
    }

    $wasCompleted = $previous ? email_goal_is_completed_state($previous) : false;
    $isCompleted = email_goal_is_completed_state($current);

    if (!$isCompleted || $wasCompleted) {
        return;
    }

    $user = email_load_user_profile($pdo, $userId);
    if (!$user) {
        return;
    }

    $context = [
        'type' => 'goal',
        'name' => (string)($current['title'] ?? 'Savings goal'),
        'target_amount' => (float)($current['target_amount'] ?? 0.0),
        'achieved_amount' => (float)($current['current_amount'] ?? 0.0),
        'currency' => (string)($current['currency'] ?? ''),
    ];

    email_send_completion_notification($pdo, $user, $context);
}

function email_maybe_send_loan_completion(PDO $pdo, int $userId, int $loanId, float $previousBalance): void
{
    $stmt = $pdo->prepare('SELECT name, principal, balance, currency FROM loans WHERE id = ? AND user_id = ?');
    $stmt->execute([$loanId, $userId]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$loan) {
        return;
    }

    $currentBalance = max(0.0, (float)($loan['balance'] ?? 0.0));
    if ($currentBalance > 0.01 || $previousBalance <= 0.01) {
        return;
    }

    $user = email_load_user_profile($pdo, $userId);
    if (!$user) {
        return;
    }

    $context = [
        'type' => 'loan',
        'name' => (string)($loan['name'] ?? 'Loan'),
        'target_amount' => (float)($loan['principal'] ?? 0.0),
        'achieved_amount' => max(0.0, (float)($loan['principal'] ?? 0.0)),
        'currency' => (string)($loan['currency'] ?? ''),
    ];

    email_send_completion_notification($pdo, $user, $context);
}

function email_maybe_send_emergency_completion(PDO $pdo, int $userId, float $previousTotal, float $newTotal, float $target, string $currency): void
{
    if ($target <= 0.0) {
        return;
    }

    if (!($previousTotal < $target && $newTotal >= $target)) {
        return;
    }

    $user = email_load_user_profile($pdo, $userId);
    if (!$user) {
        return;
    }

    $context = [
        'type' => 'emergency',
        'name' => 'Emergency Fund',
        'target_amount' => $target,
        'achieved_amount' => $newTotal,
        'currency' => $currency,
    ];

    email_send_completion_notification($pdo, $user, $context);
}

function email_fetch_primary_goal(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT title, target_amount, current_amount, currency, status FROM goals WHERE user_id = ? ORDER BY CASE WHEN status = 'active' THEN 0 WHEN status = 'completed' THEN 1 ELSE 2 END, priority NULLS LAST, id LIMIT 1");
    $stmt->execute([$userId]);
    $goal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$goal) {
        return null;
    }

    $target = max(0.0, (float)$goal['target_amount']);
    $current = max(0.0, (float)$goal['current_amount']);
    $progress = $target > 0.0 ? min(100.0, ($current / $target) * 100.0) : null;

    return [
        'title' => (string)$goal['title'],
        'target' => $target,
        'current' => $current,
        'currency' => (string)($goal['currency'] ?? ''),
        'status' => (string)($goal['status'] ?? ''),
        'progress_percent' => $progress,
    ];
}

function email_fetch_goal_highlights(PDO $pdo, int $userId, int $limit = 3): array
{
    $stmt = $pdo->prepare('SELECT title, target_amount, current_amount, currency, status FROM goals WHERE user_id = ?');
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $goals = [];
    foreach ($rows as $row) {
        $target = max(0.0, (float)($row['target_amount'] ?? 0.0));
        $current = max(0.0, (float)($row['current_amount'] ?? 0.0));
        $progress = $target > 0.0 ? min(100.0, ($current / $target) * 100.0) : null;
        $goals[] = [
            'title' => trim((string)$row['title']),
            'status' => (string)($row['status'] ?? ''),
            'progress' => $progress,
        ];
    }

    usort($goals, static function (array $a, array $b): int {
        $completedA = strtolower($a['status']) === 'completed' ? 1 : 0;
        $completedB = strtolower($b['status']) === 'completed' ? 1 : 0;
        if ($completedA !== $completedB) {
            return $completedB <=> $completedA;
        }

        return (float)($b['progress'] ?? 0.0) <=> (float)($a['progress'] ?? 0.0);
    });

    return array_slice($goals, 0, $limit);
}

function email_send_weekly_results(PDO $pdo, array $user, ?DateTimeImmutable $reference = null): bool
{
    $reference = $reference ?? new DateTimeImmutable('today');
    $end = $reference;
    $start = $end->modify('-6 days');
    $summary = email_collect_period_summary($pdo, (int)$user['id'], $start, $end);

    $name = email_user_display_name($user);
    $firstName = email_user_first_name($user);
    $currency = (string)$summary['currency'];
    $reportPeriod = email_format_period_label($start, $end);
    $topCategory = $summary['top_category']['label'] ?? 'No spending recorded';
    if (($summary['spending_total'] ?? 0.0) <= 0.0) {
        $topCategory = 'No spending recorded';
    }

    $topCategories = email_prepare_top_categories($summary, 5);
    $tokens = [
        'user_first_name' => $firstName,
        'report_period' => $reportPeriod,
        'total_spent' => email_format_amount($summary['spending_total'], $currency),
        'total_income' => email_format_amount($summary['income_total'], $currency),
        'net_change' => email_format_amount($summary['net'], $currency),
        'top_category' => $topCategory,
        'app_url' => app_url(sprintf(
            '/years/%d/%d?from=%s&to=%s',
            (int)$start->format('Y'),
            (int)$start->format('n'),
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        )),
        'template_language' => email_user_locale($user),
    ];

    for ($i = 1; $i <= 5; $i++) {
        $category = $topCategories[$i - 1] ?? null;
        $tokens['category_name_' . $i] = $category['label'] ?? '—';
        $tokens['category_spent_' . $i] = $category['amount_formatted'] ?? '—';
        $tokens['category_percent_' . $i] = $category['percent_formatted'] ?? '—';
    }

    $locale = email_template_resolve_locale($tokens);
    $html = email_template_render('email_report_weekly', $tokens);
    $text = email_template_html_to_text($html);
    $subject = email_template_subject($html, 'Your weekly MyMoneyMap report', $locale);

    return send_app_email((string)$user['email'], $subject, $html, $text, [
        'to_name' => $name,
    ]);
}

function email_send_monthly_results(PDO $pdo, array $user, ?DateTimeImmutable $reference = null): bool
{
    $reference = $reference ?? new DateTimeImmutable('first day of this month');
    $start = $reference->modify('-1 month');
    $end = $reference->modify('-1 day');
    $summary = email_collect_period_summary($pdo, (int)$user['id'], $start, $end);

    $name = email_user_display_name($user);
    $firstName = email_user_first_name($user);
    $currency = (string)$summary['currency'];
    $reportPeriod = email_format_period_label($start, $end);
    $topCategory = $summary['top_category']['label'] ?? 'No spending recorded';
    $topCategoryAmount = $summary['top_category']['amount'] ?? 0.0;
    if (($summary['spending_total'] ?? 0.0) <= 0.0) {
        $topCategory = 'No spending recorded';
        $topCategoryAmount = 0.0;
    }

    $milestones = [
        $summary['transaction_count'] > 0
            ? $summary['transaction_count'] . ' transactions captured'
            : 'Start logging transactions to unlock monthly insights',
        $summary['income_count'] > 0
            ? $summary['income_count'] . ' income entries totalling ' . email_format_amount($summary['income_total'], $currency)
            : 'No income recorded this month',
        $topCategoryAmount > 0
            ? $topCategory . ' led spending at ' . email_format_amount($topCategoryAmount, $currency)
            : 'Spending insights will appear once expenses are tracked',
    ];

    $goal = email_fetch_primary_goal($pdo, (int)$user['id']);
    if ($goal) {
        if (($goal['progress_percent'] ?? null) === null) {
            $savingsProgress = 'Goal ready to track';
        } elseif ($goal['progress_percent'] >= 100.0) {
            $savingsProgress = 'Goal completed!';
        } else {
            $savingsProgress = round((float)$goal['progress_percent']) . '% funded';
        }
        $savingsGoalName = $goal['title'];
    } else {
        $savingsProgress = 'Goal tracking ready';
        $savingsGoalName = 'your next goal';
    }

    $budgets = email_collect_budget_rows($pdo, (int)$user['id'], $summary);
    $focus = email_collect_financial_focus($pdo, (int)$user['id'], $currency);
    $emergencyStatus = $focus['emergency']['status'] ?? 'Emergency fund ready.';
    $emergencyNote = $focus['emergency']['note'] ?? 'Track your emergency fund progress in the app.';
    $goalsStatus = $focus['goals']['status'] ?? 'Goals overview available in the app.';
    $goalsNote = $focus['goals']['note'] ?? 'Review your goals to stay on course.';
    $loansStatus = $focus['loans']['status'] ?? 'Loan overview available in the app.';
    $loansNote = $focus['loans']['note'] ?? 'Review your payoff plan for more detail.';

    $tokens = [
        'user_first_name' => $firstName,
        'report_period' => $reportPeriod,
        'total_spent' => email_format_amount($summary['spending_total'], $currency),
        'total_income' => email_format_amount($summary['income_total'], $currency),
        'net_change' => email_format_amount($summary['net'], $currency),
        'top_category' => $topCategory,
        'milestone_1' => $milestones[0] ?? 'Keep building your routine',
        'milestone_2' => $milestones[1] ?? 'Review your cashflow regularly',
        'milestone_3' => $milestones[2] ?? 'Celebrate progress and adjust goals',
        'savings_progress' => $savingsProgress,
        'savings_goal_name' => $savingsGoalName,
        'ef_status' => $emergencyStatus,
        'ef_status_note' => $emergencyNote,
        'goals_status' => $goalsStatus,
        'goals_status_note' => $goalsNote,
        'loans_status' => $loansStatus,
        'loans_status_note' => $loansNote,
        'app_url' => app_url(sprintf(
            '/years/%d/%d',
            (int)$start->format('Y'),
            (int)$start->format('n')
        )),
        'template_language' => email_user_locale($user),
    ];

    for ($i = 1; $i <= 3; $i++) {
        $row = $budgets[$i - 1] ?? null;
        $tokens['budget_category_' . $i] = $row['label'] ?? '—';
        $tokens['budget_planned_' . $i] = $row['planned'] ?? '—';
        $tokens['budget_actual_' . $i] = $row['actual'] ?? '—';
        $tokens['budget_status_' . $i] = $row['status'] ?? '—';
    }

    $locale = email_template_resolve_locale($tokens);
    $html = email_template_render('email_report_monthly', $tokens);
    $text = email_template_html_to_text($html);
    $subject = email_template_subject($html, 'Your monthly MyMoneyMap report', $locale);

    return send_app_email((string)$user['email'], $subject, $html, $text, [
        'to_name' => $name,
    ]);
}

function email_send_yearly_results(PDO $pdo, array $user, ?DateTimeImmutable $reference = null): bool
{
    $reference = $reference ?? new DateTimeImmutable('first day of january last year');
    $year = (int)$reference->format('Y');
    $start = new DateTimeImmutable($year . '-01-01');
    $end = new DateTimeImmutable($year . '-12-31');
    $summary = email_collect_period_summary($pdo, (int)$user['id'], $start, $end);

    $name = email_user_display_name($user);
    $firstName = email_user_first_name($user);
    $currency = (string)$summary['currency'];
    $reportPeriod = (string)$year;
    $topCategory = $summary['top_category']['label'] ?? 'No spending recorded';
    $topCategoryAmount = $summary['top_category']['amount'] ?? 0.0;
    if (($summary['spending_total'] ?? 0.0) <= 0.0) {
        $topCategory = 'No spending recorded';
        $topCategoryAmount = 0.0;
    }

    $periodTotals = $summary['period_totals'] ?? [];
    $bestMonthLabel = 'No data logged';
    $bestMonthNet = 0.0;
    $highestIncomeLabel = null;
    $highestIncomeValue = 0.0;

    foreach ($periodTotals as $period => $totals) {
        $income = (float)($totals['income'] ?? 0.0);
        $spending = (float)($totals['spending'] ?? 0.0);
        $net = $income - $spending;
        $label = DateTimeImmutable::createFromFormat('Y-m', $period);
        $monthName = $label ? $label->format('F Y') : $period;

        if ($net > $bestMonthNet || $bestMonthLabel === 'No data logged') {
            $bestMonthNet = $net;
            $bestMonthLabel = $monthName;
        }

        if ($income > $highestIncomeValue) {
            $highestIncomeValue = $income;
            $highestIncomeLabel = $monthName;
        }
    }

    $trendHighlights = [
        $summary['transaction_count'] > 0
            ? $summary['transaction_count'] . ' transactions captured across the year'
            : 'Start recording transactions to build yearly insights',
        $highestIncomeLabel
            ? 'Highest income month: ' . $highestIncomeLabel . ' at ' . email_format_amount($highestIncomeValue, $currency)
            : 'Log income regularly to surface monthly peaks',
        $summary['transaction_count'] > 0
            ? 'Average monthly net: ' . email_format_amount($summary['net'] / max(1, count($periodTotals)), $currency)
            : 'Monthly net trend will appear after you record activity',
    ];

    $savingsAchievements = [];
    $goalHighlights = email_fetch_goal_highlights($pdo, (int)$user['id'], 3);
    foreach ($goalHighlights as $goal) {
        if (strtolower($goal['status']) === 'completed') {
            $savingsAchievements[] = 'Completed goal: ' . $goal['title'];
        } elseif (($goal['progress'] ?? null) !== null) {
            $savingsAchievements[] = round((float)$goal['progress']) . '% funded for ' . $goal['title'];
        }
    }
    if (!$savingsAchievements) {
        $savingsAchievements = [
            'Set a savings goal to see achievements here.',
            'Track contributions throughout the year for richer insights.',
            'Celebrate milestones by updating your goal progress.',
        ];
    }

    $savingsRate = ($summary['income_total'] ?? 0.0) > 0
        ? round(($summary['net'] / max(1.0, $summary['income_total'])) * 100, 1)
        : 0.0;
    $investmentGrowth = 'Net savings rate: ' . $savingsRate . '% of income';

    $tokens = [
        'user_first_name' => $firstName,
        'report_period' => $reportPeriod,
        'total_spent' => email_format_amount($summary['spending_total'], $currency),
        'total_income' => email_format_amount($summary['income_total'], $currency),
        'net_change' => email_format_amount($summary['net'], $currency),
        'top_category' => $topCategory,
        'trend_highlight_1' => $trendHighlights[0] ?? 'Keep logging activity to build trends',
        'trend_highlight_2' => $trendHighlights[1] ?? 'Review your cashflow regularly',
        'trend_highlight_3' => $trendHighlights[2] ?? 'Stay focused on long-term goals',
        'best_month' => $bestMonthLabel . ' (' . email_format_amount($bestMonthNet, $currency) . ')',
        'investment_growth' => $investmentGrowth,
        'savings_achievement_1' => $savingsAchievements[0] ?? 'Celebrate your wins in the app',
        'savings_achievement_2' => $savingsAchievements[1] ?? 'Stay consistent with contributions',
        'savings_achievement_3' => $savingsAchievements[2] ?? 'Plan your next milestone',
        'app_url' => app_url(sprintf('/years/%d', $year)),
        'template_language' => email_user_locale($user),
    ];

    $locale = email_template_resolve_locale($tokens);
    $html = email_template_render('email_report_yearly', $tokens);
    $text = email_template_html_to_text($html);
    $subject = email_template_subject($html, 'Your yearly MyMoneyMap report', $locale);

    return send_app_email((string)$user['email'], $subject, $html, $text, [
        'to_name' => $name,
    ]);
}

function email_send_completion_notification(PDO $pdo, array $user, array $achievement): bool
{
    $type = strtolower((string)($achievement['type'] ?? 'goal'));
    $userId = (int)($user['id'] ?? 0);
    $name = email_user_display_name($user);
    $firstName = email_user_first_name($user);
    $achievementName = trim((string)($achievement['name'] ?? ''));
    $currency = (string)($achievement['currency'] ?? '');
    $achievedValue = (float)($achievement['achieved_amount'] ?? 0.0);
    $targetValue = (float)($achievement['target_amount'] ?? 0.0);
    $achievedAmount = email_format_amount($achievedValue, $currency);
    $targetAmount = $targetValue > 0.0 ? email_format_amount($targetValue, $currency) : '';

    $nextEfVisibility = 'display:none; mso-hide:all; line-height:0; font-size:0; height:0; overflow:hidden;';
    $nextEfLabel = '';
    $nextEfAmount = '';
    $nextEfEquivalent = '';
    $nextEfEquivalentVisibility = 'display:none;';
    $nextEfNote = '';
    $nextEmergencyGoal = null;

    $headline = 'Goal achieved';
    $subheadline = 'You reached your goal.';
    $summary = 'You completed this milestone with ' . $achievedAmount . '.';
    $highlights = [
        'Lock in the win by recording a quick reflection.',
        'Reassign your budget toward the next priority.',
        'Share the news with anyone cheering you on.',
    ];
    $ctaLabel = 'Review your goals';
    $ctaUrl = app_url('/goals');
    $celebrationNote = 'Keep the momentum going—your next milestone is within reach.';
    $subjectKey = $achievementName !== '' ? 'Goal complete: :name' : 'Goal complete!';
    $subjectReplace = $achievementName !== '' ? ['name' => $achievementName] : [];

    switch ($type) {
        case 'loan':
            if ($achievementName !== '') {
                $subjectKey = 'Loan paid off: :name';
                $subjectReplace = ['name' => $achievementName];
            } else {
                $subjectKey = 'Loan paid off!';
                $subjectReplace = [];
            }
            $headline = 'Loan freedom achieved';
            $subheadline = 'You paid off ' . ($achievementName !== '' ? $achievementName : 'your loan') . '.';
            $summary = 'Principal cleared: ' . $achievedAmount . '.';
            $highlights = [
                'Redirect the retired payment toward savings or investing.',
                'Update the loan record with any closing notes or documents.',
                'Celebrate the milestone—you earned this moment.',
            ];
            $ctaLabel = 'Review loan history';
            $ctaUrl = app_url('/loans');
            $celebrationNote = 'Keep the momentum by pointing that cashflow at your next priority.';
            break;
        case 'emergency':
            if ($achievementName !== '') {
                $subjectKey = 'Emergency fund ready: :name';
                $subjectReplace = ['name' => $achievementName];
            } else {
                $subjectKey = 'Emergency fund goal reached!';
                $subjectReplace = [];
            }
            $headline = 'Emergency fund secured';
            $subheadline = 'You filled your safety net to the brim.';
            $summary = 'Emergency fund total: ' . $achievedAmount . ($targetAmount !== '' ? ' (target ' . $targetAmount . ')' : '') . '.';
            $highlights = [
                'Celebrate hitting full strength—this is your peace-of-mind fund.',
                'Schedule a quarterly check-in to keep balances topped up.',
                'Decide which goal to focus on next with your new momentum.',
            ];
            $ctaLabel = 'View emergency fund';
            $ctaUrl = app_url('/emergency');
            $celebrationNote = 'Maintain the habit—redirect fresh savings to your next mission while keeping this buffer full.';
            if ($userId > 0) {
                $nextEmergencyGoal = email_calculate_next_emergency_goal($pdo, $userId, $targetValue, $currency);
                if ($nextEmergencyGoal) {
                    $nextEfVisibility = '';
                    $nextEfLabel = trim((string)($nextEmergencyGoal['label'] ?? ''));
                    $nextEfAmount = trim((string)($nextEmergencyGoal['amount_formatted'] ?? ''));
                    $nextEfNote = trim((string)($nextEmergencyGoal['note'] ?? ''));
                    $nextEfEquivalent = trim((string)($nextEmergencyGoal['equivalent_formatted'] ?? ''));
                    if ($nextEfEquivalent !== '') {
                        $nextEfEquivalentVisibility = 'display:block;';
                    }

                    if ($nextEfLabel !== '') {
                        $celebrationNote .= ' Next target: ' . $nextEfLabel;
                        if ($nextEfAmount !== '') {
                            $celebrationNote .= ' (' . $nextEfAmount;
                            if ($nextEfEquivalent !== '') {
                                $celebrationNote .= ' ≈ ' . $nextEfEquivalent;
                            }
                            $celebrationNote .= ')';
                        }
                        $celebrationNote .= '.';
                    }

                    $trimmedNote = trim($nextEfNote);
                    if ($trimmedNote !== '') {
                        $celebrationNote .= ' ' . $trimmedNote;
                    }
                }
            }
            break;
        default:
            if ($achievementName !== '') {
                $headline = 'Goal complete: ' . $achievementName;
                $subheadline = 'You reached ' . $achievementName . '.';
            }
            $summary = 'Final amount saved: ' . $achievedAmount . ($targetAmount !== '' ? ' (target ' . $targetAmount . ')' : '') . '.';
            $highlights = [
                'Record what worked so you can repeat it.',
                'Take a moment to celebrate the dedication it took.',
                'Pick your next goal while the energy is high.',
            ];
            $ctaLabel = 'Review your goals';
            $ctaUrl = app_url('/goals');
            $celebrationNote = 'Roll that winning streak into your next milestone—your plan is working.';
            break;
    }

    $tokens = [
        'user_first_name' => $firstName,
        'achievement_headline' => $headline,
        'achievement_subheadline' => $subheadline,
        'achievement_summary' => $summary,
        'highlight_1' => $highlights[0] ?? 'Keep tracking your progress inside MyMoneyMap.',
        'highlight_2' => $highlights[1] ?? 'Adjust your plan to reflect the new milestone.',
        'highlight_3' => $highlights[2] ?? 'Share the win with your accountability partner.',
        'cta_label' => $ctaLabel,
        'cta_url' => $ctaUrl,
        'celebration_note' => $celebrationNote,
        'next_ef_goal_visibility' => $nextEfVisibility,
        'next_ef_goal_label' => $nextEfLabel,
        'next_ef_goal_amount' => $nextEfAmount,
        'next_ef_goal_equivalent' => $nextEfEquivalent,
        'next_ef_goal_equivalent_visibility' => $nextEfEquivalentVisibility,
        'next_ef_goal_note' => $nextEfNote,
        'template_language' => email_user_locale($user),
    ];

    $locale = email_template_resolve_locale($tokens);
    $html = email_template_render('email_goal_congratulations', $tokens);
    $text = email_template_html_to_text($html);
    $subject = email_template_subject($html, $subjectKey, $locale, $subjectReplace);

    return send_app_email((string)$user['email'], $subject, $html, $text, [
        'to_name' => $name,
    ]);
}

function email_send_emergency_motivation(PDO $pdo, array $user): bool
{
    $userId = (int)($user['id'] ?? 0);
    if ($userId <= 0) {
        return false;
    }

    $displayName = email_user_display_name($user);
    $firstName = email_user_first_name($user);

    $stmt = $pdo->prepare('SELECT total, target_amount, currency FROM emergency_fund WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $currency = trim((string)($row['currency'] ?? ''));
    if ($currency === '' && function_exists('fx_user_main')) {
        $currency = (string)(fx_user_main($pdo, $userId) ?: '');
    }

    $total = max(0.0, (float)($row['total'] ?? 0.0));
    $target = max(0.0, (float)($row['target_amount'] ?? 0.0));

    if ($target > 0.0 && $total >= $target) {
        // Already complete; skip motivational nudge.
        return false;
    }

    $gap = $target > 0.0 ? max(0.0, $target - $total) : 0.0;
    $percent = $target > 0.0 ? min(99.0, ($total / max($target, 1e-9)) * 100.0) : 0.0;

    if ($target <= 0.0 && $total <= 0.0) {
        $progressStage = 'setup';
    } elseif ($percent < 25.0) {
        $progressStage = 'early';
    } elseif ($percent < 75.0) {
        $progressStage = 'mid';
    } else {
        $progressStage = 'close';
    }

    $tipsByStage = [
        'setup' => [
            'Set a target that covers 3–6 months of essentials.',
            'Schedule a starter transfer so the fund exists by this weekend.',
            'Label the fund clearly so you protect it from impulse spending.',
        ],
        'early' => [
            'Automate deposits the day after payday to stay consistent.',
            'Trim one discretionary category to free up an extra contribution.',
            'Track your weekly streak in MyMoneyMap to stay accountable.',
        ],
        'mid' => [
            'Redirect any windfalls—refunds, bonuses, side gigs—straight to the fund.',
            'Review subscriptions and insurance for quick wins you can reallocate.',
            'Keep surplus cash parked in this account before moving to other goals.',
        ],
        'close' => [
            'Lock in your finish line with one more scheduled transfer.',
            'Plan how you will maintain the fund once it is fully topped up.',
            'Pick the next goal that will receive these automatic contributions.',
        ],
    ];

    $progressNotes = [
        'setup' => 'Set a clear target to unlock richer guidance and tracking.',
        'early' => 'You are off the mark—stay consistent and the fund will grow quickly.',
        'mid' => 'You are over halfway there. Keep tightening the sails for a strong finish.',
        'close' => 'You are within striking distance. One more push will finish the job.',
    ];

    $tips = $tipsByStage[$progressStage];
    $progressNote = $progressNotes[$progressStage];

    $tokens = [
        'user_first_name' => $firstName,
        'ef_current' => email_format_amount($total, $currency),
        'ef_target' => $target > 0.0 ? email_format_amount($target, $currency) : 'Set your target',
        'ef_gap' => $target > 0.0 ? email_format_amount($gap, $currency) : 'Define a target to calculate your gap.',
        'ef_percent' => $target > 0.0 ? round($percent) . '%' : 'Start now',
        'progress_note' => $progressNote,
        'motivation_tip_1' => $tips[0] ?? 'Automate transfers so the fund grows on autopilot.',
        'motivation_tip_2' => $tips[1] ?? 'Review your categories to keep cash flowing in the right direction.',
        'motivation_tip_3' => $tips[2] ?? 'Protect the fund by separating it from everyday spending.',
        'cta_label' => 'Update Emergency Fund',
        'cta_url' => app_url('/emergency'),
        'template_language' => email_user_locale($user),
    ];

    $locale = email_template_resolve_locale($tokens);
    $html = email_template_render('email_emergency_motivation', $tokens);
    $text = email_template_html_to_text($html);
    $subject = email_template_subject($html, 'Keep building your emergency fund', $locale);

    return send_app_email((string)$user['email'], $subject, $html, $text, [
        'to_name' => $displayName,
    ]);
}

function email_send_emergency_withdrawal(PDO $pdo, int $userId, float $amount, string $currency, string $occurredOn, string $note = ''): bool
{
    if ($userId <= 0 || $amount <= 0.0) {
        return false;
    }

    $user = email_load_user_profile($pdo, $userId);
    if (!$user) {
        return false;
    }

    $displayName = email_user_display_name($user);
    $firstName = email_user_first_name($user);

    $currency = trim($currency);
    if ($currency === '') {
        $mainCurrency = fx_user_main($pdo, $userId);
        if (is_string($mainCurrency) && $mainCurrency !== '') {
            $currency = $mainCurrency;
        }
    }
    if ($currency === '') {
        $currency = 'USD';
    }

    try {
        $occurred = new DateTimeImmutable($occurredOn !== '' ? $occurredOn : 'now');
    } catch (Exception $e) {
        $occurred = new DateTimeImmutable();
    }

    $occurredFormatted = $occurred->format('F j, Y');
    $nextMonth = $occurred->modify('first day of next month');
    if (!$nextMonth) {
        $nextMonth = $occurred->modify('+1 month');
    }
    $repayCandidate = $nextMonth ?: $occurred->modify('+1 month');
    if ($repayCandidate instanceof DateTimeImmutable) {
        $repayMonth = $repayCandidate->format('F Y');
    } else {
        $repayMonth = date('F Y', strtotime('+1 month'));
    }

    $withdrawFormatted = email_format_amount($amount, $currency);

    $defaultCurrency = $currency !== '' ? $currency : ((string)(fx_user_main($pdo, $userId) ?: 'USD'));

    try {
        $status = email_collect_emergency_status($pdo, $userId, $defaultCurrency);
    } catch (Throwable $statusError) {
        error_log('Emergency withdraw status failed for user ' . $userId . ': ' . $statusError->getMessage());
        $status = [];
    }

    if (!is_array($status)) {
        $status = [];
    }

    $statusCurrency = (string)($status['currency'] ?? $defaultCurrency);
    if ($statusCurrency === '') {
        $statusCurrency = $defaultCurrency;
    }
    if ($statusCurrency === '') {
        $statusCurrency = 'USD';
    }
    
    $totalAfter = (float)($status['total'] ?? 0.0);
    $balanceAfter = email_format_amount($totalAfter, $statusCurrency);
    $targetValue = max(0.0, (float)($status['target'] ?? 0.0));
    $targetFormatted = $targetValue > 0.0 ? email_format_amount($targetValue, $statusCurrency) : '';
    $targetRowStyle = $targetValue > 0.0
        ? 'background-color:#F9FAFB;'
        : 'display:none; mso-hide:all; line-height:0; font-size:0; height:0; overflow:hidden;';

    $statusSummaryRaw = (string)($status['status'] ?? '');
    $statusSummary = $statusSummaryRaw !== '' ? $statusSummaryRaw : 'Track your emergency fund progress in the app.';
    $statusNoteRaw = (string)($status['note'] ?? '');
    $statusNote = trim($statusNoteRaw) !== '' ? $statusNoteRaw : 'Keep your safety net padded for the unexpected.';

    $noteText = trim($note);
    $noteVisibility = $noteText !== ''
        ? ''
        : 'display:none; mso-hide:all; line-height:0; font-size:0; height:0; overflow:hidden;';

    $planStartCandidate = $nextMonth instanceof DateTimeImmutable ? $nextMonth : ($occurred->modify('+1 month') ?: null);
    if (!$planStartCandidate instanceof DateTimeImmutable) {
        try {
            $planStartCandidate = new DateTimeImmutable('first day of next month');
        } catch (Exception $e) {
            $planStartCandidate = new DateTimeImmutable();
        }
    }

    try {
        $planStart = new DateTimeImmutable($planStartCandidate->format('Y-m-01'));
    } catch (Exception $e) {
        $planStart = $planStartCandidate;
    }

    if (!$planStart instanceof DateTimeImmutable) {
        $planStart = new DateTimeImmutable();
    }

    try {
        $planStart = new DateTimeImmutable($planStart->format('Y-m-01'));
    } catch (Exception $e) {
        // keep original reference if formatting fails
    }

    try {
        $replenishmentPlan = email_prepare_emergency_replenishment_plan($pdo, $userId, $amount, $statusCurrency, $planStart);
    } catch (Throwable $planError) {
        error_log('Emergency withdraw plan failed for user ' . $userId . ': ' . $planError->getMessage());
        $replenishmentPlan = [
            'headline' => 'Plan your repayment',
            'summary' => 'Review your emergency fund dashboard to rebuild this withdrawal.',
            'detail' => '',
            'detail_style' => 'color:#555555;',
            'detail_visible' => false,
            'rows' => [],
            'plan_months' => 0,
            'feasible' => false,
            'shortfall' => $amount,
            'allocated' => 0.0,
            'monthly_amount' => $amount,
            'month_labels' => [],
        ];
    }

    if (!is_array($replenishmentPlan)) {
        $replenishmentPlan = [
            'headline' => 'Plan your repayment',
            'summary' => 'Review your emergency fund dashboard to rebuild this withdrawal.',
            'detail' => '',
            'detail_style' => 'color:#555555;',
            'detail_visible' => false,
            'rows' => [],
            'plan_months' => 0,
            'feasible' => false,
            'shortfall' => $amount,
            'allocated' => 0.0,
            'monthly_amount' => $amount,
            'month_labels' => [],
        ];
    }

    $planRows = $replenishmentPlan['rows'] ?? [];
    $planMonthLabels = $replenishmentPlan['month_labels'] ?? [];
    $planMonthsText = email_list_join($planMonthLabels);
    if ($planMonthsText === '') {
        $planMonthsText = $repayMonth;
    }

    $monthlyContribution = max(0.0, (float)($replenishmentPlan['monthly_amount'] ?? 0.0));
    $monthlyContributionFormatted = email_format_amount($monthlyContribution, $statusCurrency);
    $planShortfall = max(0.0, (float)($replenishmentPlan['shortfall'] ?? 0.0));

    if ($planShortfall > 0.01 && $amount > 0.0) {
        $nextSteps = [
            'Schedule transfers that match the suggested deposits below and adjust if your income shifts.',
            'Plan extra transfers or trim costs to cover the remaining ' . email_format_amount($planShortfall, $statusCurrency) . '.',
            'Log each repayment in MyMoneyMap so your emergency fund updates automatically.',
        ];
    } else {
        $nextSteps = [
            'Schedule transfers for ' . $planMonthsText . ' in advance so funds move back automatically.',
            'Keep discretionary spending aligned so you can set aside ' . $monthlyContributionFormatted . ' each month.',
            'Log each repayment in MyMoneyMap so your emergency fund updates automatically.',
        ];
    }

    $tokens = [
        'user_first_name' => $firstName,
        'withdraw_amount' => $withdrawFormatted,
        'withdraw_date' => $occurredFormatted,
        'withdraw_note_visibility' => $noteVisibility,
        'withdraw_note' => $noteText !== '' ? $noteText : '—',
        'ef_status_summary' => $statusSummary,
        'ef_status_note' => $statusNote,
        'ef_balance' => $balanceAfter,
        'ef_target_row_style' => $targetRowStyle,
        'ef_target' => $targetFormatted,
        'repay_headline' => (string)($replenishmentPlan['headline'] ?? 'Plan your repayment'),
        'repay_summary' => (string)($replenishmentPlan['summary'] ?? $repayMonth),
        'repay_detail' => (string)($replenishmentPlan['detail'] ?? ''),
        'repay_detail_style' => (string)($replenishmentPlan['detail_style'] ?? 'color:#555555;'),
        'repay_detail_visibility' => !empty($replenishmentPlan['detail_visible'])
            ? ''
            : 'display:none; mso-hide:all; line-height:0; font-size:0; height:0; overflow:hidden;',
        'cta_label' => 'Replenish emergency fund',
        'cta_url' => app_url('/emergency'),
        'next_step_1' => $nextSteps[0],
        'next_step_2' => $nextSteps[1],
        'next_step_3' => $nextSteps[2],
        'template_language' => email_user_locale($user),
    ];

    $hiddenRowStyle = 'display:none; mso-hide:all; line-height:0; font-size:0; height:0; overflow:hidden;';
    for ($i = 0; $i < 3; $i++) {
        $index = $i + 1;
        $row = $planRows[$i] ?? null;
        if ($row) {
            $tokens['plan_month_' . $index . '_label'] = (string)($row['label'] ?? '');
            $tokens['plan_month_' . $index . '_available'] = (string)($row['available_formatted'] ?? '');
            $tokens['plan_month_' . $index . '_suggested'] = (string)($row['suggested_formatted'] ?? '');
            $tokens['plan_month_' . $index . '_visibility'] = '';
        } else {
            $tokens['plan_month_' . $index . '_label'] = '';
            $tokens['plan_month_' . $index . '_available'] = '';
            $tokens['plan_month_' . $index . '_suggested'] = '';
            $tokens['plan_month_' . $index . '_visibility'] = $hiddenRowStyle;
        }
    }

    $locale = email_template_resolve_locale($tokens);
    $html = email_template_render('email_emergency_withdrawal', $tokens);
    $text = email_template_html_to_text($html);
    $subject = email_template_subject($html, 'Emergency fund withdrawal: :amount', $locale, [
        'amount' => $withdrawFormatted,
    ]);

    return send_app_email((string)$user['email'], $subject, $html, $text, [
        'to_name' => $displayName,
    ]);
}

