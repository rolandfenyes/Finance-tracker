<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/../fx.php';

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

function email_template_render(string $template, array $tokens): string
{
    $path = dirname(__DIR__, 2) . '/docs/email_templates/' . $template . '.html';
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Email template not found: ' . $template);
    }

    $html = (string)file_get_contents($path);
    $defaults = email_template_base_tokens();
    $replacements = [];

    foreach (array_merge($defaults, $tokens) as $key => $value) {
        $placeholder = '{{' . $key . '}}';
        if (!is_string($value)) {
            $value = (string)$value;
        }
        $replacements[$placeholder] = email_template_escape($value);
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

    $html = email_template_render('email_registration_validation', [
        'user_first_name' => $firstName,
        'verification_link' => $link,
    ]);

    $text = "Hi {$name},\n\n" .
        "Thanks for creating a MyMoneyMap account. Please confirm your email address so we can keep your data safe and share updates with you.\n\n" .
        "Verification link: {$link}\n\n" .
        "See you soon,\nThe MyMoneyMap team";

    return send_app_email((string)$user['email'], 'Verify your email address', $html, $text, [
        'to_name' => $name,
    ]);
}

function email_send_welcome(array $user): bool
{
    $name = email_user_display_name($user);
    $palette = email_theme_palette($user);

    $body = '<p style="margin:0 0 16px;">Hi ' . htmlspecialchars($name) . ',</p>' .
        '<p style="margin:0 0 16px;">Your MyMoneyMap registration was successful. You can start tracking your finances right away — add accounts, record transactions, and set your goals.</p>' .
        '<ul style="margin:0 0 16px;padding-left:20px;">' .
        '<li style="margin-bottom:8px;">Record today\'s income and spending from the dashboard.</li>' .
        '<li style="margin-bottom:8px;">Invite MyMoneyMap into your routine with weekly reviews.</li>' .
        '<li style="margin-bottom:0;">Explore Baby Steps, emergency funds, and long-term goals.</li>' .
        '</ul>' .
        '<p style="margin:0;">We\'re thrilled to have you on board!<br />— The MyMoneyMap team</p>';

    $html = email_wrap_html('Welcome to MyMoneyMap', $body, $palette);

    $text = "Hi {$name},\n\n" .
        "Your MyMoneyMap registration was successful. Start tracking your finances right away: add transactions, review budgets, and set meaningful goals.\n\n" .
        "We\'re thrilled to have you on board!\n— The MyMoneyMap team";

    return send_app_email((string)$user['email'], 'Welcome to MyMoneyMap', $html, $text, [
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

    $html = email_template_render('email_tips_and_tricks', [
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
    ]);

    $textLines = [
        "Hi {$name},",
        '',
        'Here are a few tips to help you get more value from MyMoneyMap:',
    ];

    foreach ($normalized as $tip) {
        $textLines[] = '• ' . $tip['title'] . ': ' . $tip['body'];
    }

    $textLines[] = '';
    $textLines[] = 'Explore more tips: ' . app_url('/learn');
    $textLines[] = '';
    $textLines[] = 'Happy tracking!';
    $textLines[] = '— The MyMoneyMap team';

    return send_app_email((string)$user['email'], 'Tips & tricks for MyMoneyMap', $html, implode("\n", $textLines), [
        'to_name' => $name,
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
    ];

    for ($i = 1; $i <= 5; $i++) {
        $category = $topCategories[$i - 1] ?? null;
        $tokens['category_name_' . $i] = $category['label'] ?? '—';
        $tokens['category_spent_' . $i] = $category['amount_formatted'] ?? '—';
        $tokens['category_percent_' . $i] = $category['percent_formatted'] ?? '—';
    }

    $html = email_template_render('email_report_weekly', $tokens);

    $textLines = [
        "Hi {$name},",
        '',
        'Weekly recap for ' . $reportPeriod . ':',
        '- Income: ' . email_plaintext_amount($summary['income_total'], $currency),
        '- Spending: ' . email_plaintext_amount($summary['spending_total'], $currency),
        '- Net: ' . email_plaintext_amount($summary['net'], $currency),
    ];

    if ($topCategories) {
        $textLines[] = '';
        $textLines[] = 'Top spending categories:';
        foreach ($topCategories as $category) {
            $textLines[] = ' • ' . $category['label'] . ' — ' . email_plaintext_amount($category['amount'], $currency) . ' (' . $category['percent_formatted'] . ')';
        }
    }

    $textLines[] = '';
    $textLines[] = 'Keep logging transactions to sharpen your insights.';
    $textLines[] = '— The MyMoneyMap team';

    return send_app_email((string)$user['email'], 'Your weekly MyMoneyMap report', $html, implode("\n", $textLines), [
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
        'app_url' => app_url(sprintf(
            '/years/%d/%d',
            (int)$start->format('Y'),
            (int)$start->format('n')
        )),
    ];

    for ($i = 1; $i <= 3; $i++) {
        $row = $budgets[$i - 1] ?? null;
        $tokens['budget_category_' . $i] = $row['label'] ?? '—';
        $tokens['budget_planned_' . $i] = $row['planned'] ?? '—';
        $tokens['budget_actual_' . $i] = $row['actual'] ?? '—';
        $tokens['budget_status_' . $i] = $row['status'] ?? '—';
    }

    $html = email_template_render('email_report_monthly', $tokens);

    $textLines = [
        "Hi {$name},",
        '',
        'Monthly performance for ' . $reportPeriod . ':',
        '- Income: ' . email_plaintext_amount($summary['income_total'], $currency),
        '- Spending: ' . email_plaintext_amount($summary['spending_total'], $currency),
        '- Net: ' . email_plaintext_amount($summary['net'], $currency),
        '',
        'Highlights:',
    ];
    foreach ($milestones as $milestone) {
        $textLines[] = ' • ' . $milestone;
    }

    $textLines[] = '';
    $textLines[] = 'Savings focus: ' . $savingsProgress . ' toward ' . $savingsGoalName . '.';
    $textLines[] = '— The MyMoneyMap team';

    return send_app_email((string)$user['email'], 'Your monthly MyMoneyMap report', $html, implode("\n", $textLines), [
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
    ];

    $html = email_template_render('email_report_yearly', $tokens);

    $textLines = [
        "Hi {$name},",
        '',
        'Year in review for ' . $reportPeriod . ':',
        '- Income: ' . email_plaintext_amount($summary['income_total'], $currency),
        '- Spending: ' . email_plaintext_amount($summary['spending_total'], $currency),
        '- Net: ' . email_plaintext_amount($summary['net'], $currency),
        '',
        'Trend highlights:',
    ];
    foreach ($trendHighlights as $highlight) {
        $textLines[] = ' • ' . $highlight;
    }

    $textLines[] = '';
    $textLines[] = 'Best month: ' . $bestMonthLabel . ' (' . email_plaintext_amount($bestMonthNet, $currency) . ')';
    $textLines[] = $investmentGrowth;
    $textLines[] = '';
    $textLines[] = 'Savings achievements:';
    foreach ($savingsAchievements as $achievement) {
        $textLines[] = ' • ' . $achievement;
    }
    $textLines[] = '';
    $textLines[] = 'Thank you for staying committed to your goals.';
    $textLines[] = '— The MyMoneyMap team';

    return send_app_email((string)$user['email'], 'Your yearly MyMoneyMap report', $html, implode("\n", $textLines), [
        'to_name' => $name,
    ]);
}
