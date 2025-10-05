<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/../fx.php';

function email_brand_logo_url(): string
{
    return app_url('/logo.png');
}

function email_wrap_html(string $title, string $content): string
{
    $logoUrl = htmlspecialchars(email_brand_logo_url(), ENT_QUOTES, 'UTF-8');
    $titleSafe = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

    return '<!DOCTYPE html>' .
        '<html lang="en">' .
        '<head>' .
        '<meta charset="utf-8" />' .
        '<meta name="viewport" content="width=device-width,initial-scale=1" />' .
        '<title>' . $titleSafe . '</title>' .
        '<style>' .
        'body{margin:0;background:#f8fafc;color:#0f172a;font-family:\'Inter\',\'Segoe UI\',Helvetica,Arial,sans-serif;}' .
        'a{color:#2563eb;}' .
        '@media (prefers-color-scheme:dark){body{background:#0f172a;color:#e2e8f0;}' .
        '.email-card{background:#0b1120 !important;border-color:#1e293b !important;}' .
        '.email-title{color:#38bdf8 !important;}' .
        '.email-paragraph{color:#e2e8f0 !important;}' .
        '.email-muted{color:#94a3b8 !important;}' .
        '.email-button{background:#38bdf8 !important;color:#0b1120 !important;}' .
        '}' .
        '</style>' .
        '</head>' .
        '<body>' .
        '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="padding:32px 0;">' .
        '<tr><td align="center">' .
        '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;padding:0 20px;">' .
        '<tr><td style="text-align:center;padding-bottom:20px;">' .
        '<img src="' . $logoUrl . '" alt="MyMoneyMap" style="height:48px;width:auto;" />' .
        '</td></tr>' .
        '<tr><td>' .
        '<div class="email-card" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:24px;padding:32px;box-shadow:0 20px 40px rgba(15,23,42,0.08);">' .
        '<h1 class="email-title" style="margin:0 0 16px;font-size:24px;font-weight:600;color:#0f172a;">' . $titleSafe . '</h1>' .
        '<div class="email-paragraph" style="font-size:15px;line-height:1.6;color:#1e293b;">' .
        $content .
        '</div>' .
        '</div>' .
        '</td></tr>' .
        '<tr><td style="text-align:center;padding:28px 12px 0;">' .
        '<p class="email-muted" style="margin:0;font-size:13px;line-height:1.5;color:#64748b;">You are receiving this email because you have a MyMoneyMap account.</p>' .
        '</td></tr>' .
        '</table>' .
        '</td></tr>' .
        '</table>' .
        '</body>' .
        '</html>';
}

function email_render_button(string $href, string $label): string
{
    return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" ' .
        'class="email-button" style="display:inline-block;padding:12px 24px;margin:12px 0;border-radius:999px;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:600;">' .
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

    $body = '<p style="margin:0 0 16px;">Hi ' . htmlspecialchars($name) . ',</p>' .
        '<p style="margin:0 0 16px;">Thanks for creating a MyMoneyMap account. Please confirm your email address so we can keep your data safe and share updates with you.</p>' .
        '<p style="margin:0 0 16px;text-align:center;">' . email_render_button($link, 'Verify my email') . '</p>' .
        '<p style="margin:0 0 16px;">If the button does not work, copy and paste this link into your browser:<br /><a href="' . htmlspecialchars($link) . '" style="word-break:break-all;color:#2563eb;">' . htmlspecialchars($link) . '</a></p>' .
        '<p style="margin:0;">See you soon,<br />The MyMoneyMap team</p>';

    $html = email_wrap_html('Verify your email', $body);

    $text = "Hi {$name},\n\n" .
        "Thanks for creating a MyMoneyMap account. Please confirm your email address so we can keep your data safe and share updates with you.\n\n" .
        "Verify your email: {$link}\n\n" .
        "See you soon,\nThe MyMoneyMap team";

    return send_app_email((string)$user['email'], 'Verify your email address', $html, $text, [
        'to_name' => $name,
    ]);
}

function email_send_welcome(array $user): bool
{
    $name = email_user_display_name($user);
    $body = '<p style="margin:0 0 16px;">Hi ' . htmlspecialchars($name) . ',</p>' .
        '<p style="margin:0 0 16px;">Your MyMoneyMap registration was successful. You can start tracking your finances right away — add accounts, record transactions, and set your goals.</p>' .
        '<ul style="margin:0 0 16px;padding-left:20px;">' .
        '<li style="margin-bottom:8px;">Record today\'s income and spending from the dashboard.</li>' .
        '<li style="margin-bottom:8px;">Invite MyMoneyMap into your routine with weekly reviews.</li>' .
        '<li style="margin-bottom:0;">Explore Baby Steps, emergency funds, and long-term goals.</li>' .
        '</ul>' .
        '<p style="margin:0;">We\'re thrilled to have you on board!<br />— The MyMoneyMap team</p>';

    $html = email_wrap_html('Welcome to MyMoneyMap', $body);

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
        'Categorise every transaction to understand where your money goes.',
        'Set a weekly review reminder to reconcile your spending and progress.',
        'Use scheduled payments to automate recurring bills and savings transfers.',
    ];
}

function email_send_tips(array $user, ?array $tips = null): bool
{
    $tips = $tips && is_array($tips) ? $tips : email_default_tips();
    $name = email_user_display_name($user);

    $body = '<p style="margin:0 0 16px;">Hi ' . htmlspecialchars($name) . ',</p>' .
        '<p style="margin:0 0 16px;">Here are a few tips to help you get even more value from MyMoneyMap:</p>' .
        '<ul style="margin:0 0 16px;padding-left:20px;">';
    foreach ($tips as $tip) {
        $body .= '<li style="margin-bottom:8px;">' . htmlspecialchars($tip) . '</li>';
    }
    $body .= '</ul><p style="margin:0;">Happy tracking!<br />— The MyMoneyMap team</p>';

    $html = email_wrap_html('Tips & tricks for MyMoneyMap', $body);

    $text = "Hi {$name},\n\n" .
        "Here are a few tips to help you get more value from MyMoneyMap:\n" .
        implode("\n", array_map(fn($tip) => '• ' . $tip, $tips)) . "\n\n" .
        "Happy tracking!\n— The MyMoneyMap team";

    return send_app_email((string)$user['email'], 'Tips & tricks for MyMoneyMap', $html, $text, [
        'to_name' => $name,
    ]);
}

function email_collect_period_summary(PDO $pdo, int $userId, DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $stmt = $pdo->prepare('SELECT t.kind, t.amount, t.currency, t.occurred_on, c.label AS category_label ' .
        'FROM transactions t LEFT JOIN categories c ON c.id = t.category_id ' .
        'WHERE t.user_id = ? AND t.occurred_on BETWEEN ?::date AND ?::date ORDER BY t.occurred_on');
    $stmt->execute([$userId, $start->format('Y-m-d'), $end->format('Y-m-d')]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $mainCurrency = fx_user_main($pdo, $userId);
    $incomeTotal = 0.0;
    $spendingTotal = 0.0;
    $incomeCount = 0;
    $spendingCount = 0;
    $categories = [];

    foreach ($rows as $row) {
        $amount = (float)$row['amount'];
        $currency = $row['currency'] ?: $mainCurrency;
        $date = $row['occurred_on'] ?: $end->format('Y-m-d');
        $converted = $currency === $mainCurrency ? $amount : fx_convert($pdo, $amount, $currency, $mainCurrency, $date);

        if ($row['kind'] === 'income') {
            $incomeTotal += $converted;
            $incomeCount++;
        } else {
            $spendingTotal += $converted;
            $spendingCount++;
            $label = $row['category_label'] ?? 'Other';
            if ($label === null || $label === '') {
                $label = 'Other';
            }
            $categories[$label] = ($categories[$label] ?? 0.0) + $converted;
        }
    }

    arsort($categories, SORT_NUMERIC);
    $topCategories = array_slice($categories, 0, 3, true);

    return [
        'currency' => $mainCurrency,
        'income_total' => $incomeTotal,
        'spending_total' => $spendingTotal,
        'income_count' => $incomeCount,
        'spending_count' => $spendingCount,
        'net' => $incomeTotal - $spendingTotal,
        'top_categories' => $topCategories,
        'transaction_count' => count($rows),
    ];
}

function email_format_amount(float $amount, string $currency): string
{
    return moneyfmt($amount, $currency);
}

function email_period_label(DateTimeImmutable $start, DateTimeImmutable $end): string
{
    return $start->format('M j, Y') . ' – ' . $end->format('M j, Y');
}

function email_send_period_results(PDO $pdo, array $user, DateTimeImmutable $start, DateTimeImmutable $end, string $label): bool
{
    $summary = email_collect_period_summary($pdo, (int)$user['id'], $start, $end);
    $name = email_user_display_name($user);
    $rangeLabel = email_period_label($start, $end);
    $currency = $summary['currency'];

    $body = '<p style="margin:0 0 16px;">Hi ' . htmlspecialchars($name) . ',</p>' .
        '<p style="margin:0 0 16px;">Here is your ' . htmlspecialchars($label) . ' summary for ' . htmlspecialchars($rangeLabel) . '.</p>';

    if ($summary['transaction_count'] === 0) {
        $body .= '<p style="margin:0 0 16px;">No transactions were recorded during this period. Add new entries from your dashboard to keep your finances up to date.</p>';
    } else {
        $body .= '<table style="border-collapse:collapse;width:100%;max-width:480px;margin:16px 0;border-radius:16px;overflow:hidden;">'
            . '<tr style="background:#f1f5f9;"><td style="padding:12px 16px;font-weight:600;color:#0f172a;border:1px solid #e2e8f0;">Income</td><td style="padding:12px 16px;text-align:right;color:#0f172a;border:1px solid #e2e8f0;">'
            . htmlspecialchars(email_format_amount($summary['income_total'], $currency)) . '</td></tr>'
            . '<tr><td style="padding:12px 16px;font-weight:600;color:#0f172a;border:1px solid #e2e8f0;">Spending</td><td style="padding:12px 16px;text-align:right;color:#0f172a;border:1px solid #e2e8f0;">'
            . htmlspecialchars(email_format_amount($summary['spending_total'], $currency)) . '</td></tr>'
            . '<tr style="background:#f8fafc;"><td style="padding:12px 16px;font-weight:600;color:#0f172a;border:1px solid #e2e8f0;">Net</td><td style="padding:12px 16px;text-align:right;color:#0f172a;border:1px solid #e2e8f0;">'
            . htmlspecialchars(email_format_amount($summary['net'], $currency)) . '</td></tr>'
            . '</table>';

        if ($summary['top_categories']) {
            $body .= '<p style="margin:0 0 8px;">Top spending categories:</p><ul style="margin:0 0 16px;padding-left:20px;">';
            foreach ($summary['top_categories'] as $category => $amount) {
                $body .= '<li style="margin-bottom:8px;">' . htmlspecialchars($category) . ' — ' . htmlspecialchars(email_format_amount($amount, $currency)) . '</li>';
            }
            $body .= '</ul>';
        }
    }

    $body .= '<p style="margin:0;">Keep going — every entry moves you closer to your goals.<br />— The MyMoneyMap team</p>';

    $html = email_wrap_html('Your ' . ucfirst($label) . ' MyMoneyMap summary', $body);

    $textLines = [
        "Hi {$name},",
        '',
        "Here is your {$label} summary for {$rangeLabel}.",
    ];
    if ($summary['transaction_count'] === 0) {
        $textLines[] = 'No transactions were recorded during this period. Add new entries from your dashboard to stay on track.';
    } else {
        $textLines[] = 'Income: ' . email_format_amount($summary['income_total'], $currency);
        $textLines[] = 'Spending: ' . email_format_amount($summary['spending_total'], $currency);
        $textLines[] = 'Net: ' . email_format_amount($summary['net'], $currency);
        if ($summary['top_categories']) {
            $textLines[] = '';
            $textLines[] = 'Top spending categories:';
            foreach ($summary['top_categories'] as $category => $amount) {
                $textLines[] = ' • ' . $category . ' — ' . email_format_amount($amount, $currency);
            }
        }
    }
    $textLines[] = '';
    $textLines[] = 'Keep going — every entry moves you closer to your goals.';
    $textLines[] = '— The MyMoneyMap team';

    $subject = 'Your ' . ucfirst($label) . ' MyMoneyMap summary';

    return send_app_email((string)$user['email'], $subject, $html, implode("\n", $textLines), [
        'to_name' => $name,
    ]);
}

function email_send_weekly_results(PDO $pdo, array $user, ?DateTimeImmutable $reference = null): bool
{
    $reference = $reference ?? new DateTimeImmutable('today');
    $end = $reference;
    $start = $end->modify('-6 days');

    return email_send_period_results($pdo, $user, $start, $end, 'weekly');
}

function email_send_monthly_results(PDO $pdo, array $user, ?DateTimeImmutable $reference = null): bool
{
    $reference = $reference ?? new DateTimeImmutable('first day of this month');
    $start = $reference->modify('-1 month');
    $end = $reference->modify('-1 day');

    return email_send_period_results($pdo, $user, $start, $end, 'monthly');
}
