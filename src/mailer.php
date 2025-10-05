<?php
require_once __DIR__ . '/helpers.php';

function mailer_config(): array
{
    $config = app_config('mail');
    return is_array($config) ? $config : [];
}

function mailer_format_address(string $email, ?string $name = null): string
{
    $email = trim($email);
    if ($name === null || $name === '') {
        return $email;
    }

    return mailer_encode_header($name) . ' <' . $email . '>';
}

function mailer_encode_header(string $value): string
{
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n", 'UTF-8');
    }

    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function send_app_email(string $toEmail, string $subject, string $htmlBody, ?string $textBody = null, array $options = []): bool
{
    $config = mailer_config();
    $transport = strtolower((string)($config['transport'] ?? 'log'));

    $fromEmail = $options['from_email'] ?? ($config['from_email'] ?? 'no-reply@example.com');
    $fromName  = $options['from_name']  ?? ($config['from_name']  ?? 'MyMoneyMap');

    $replyToEmail = $options['reply_to'] ?? ($config['reply_to'] ?? $fromEmail);
    $replyToName  = $options['reply_to_name'] ?? ($options['from_name'] ?? ($config['from_name'] ?? 'MyMoneyMap'));

    $toName = $options['to_name'] ?? null;

    $fromHeader = mailer_format_address($fromEmail, $fromName);
    $toHeader   = mailer_format_address($toEmail, $toName);
    $replyToHeader = $replyToEmail ? mailer_format_address($replyToEmail, $replyToName) : null;

    if ($textBody === null) {
        $textBody = trim(html_entity_decode(strip_tags($htmlBody), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    $boundary = '=_Part_' . bin2hex(random_bytes(16));
    $dateHeader = 'Date: ' . date('r');

    $mimeHeaders = [
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer: MyMoneyMap Mailer',
    ];

    $commonHeaders = array_merge([$dateHeader, 'From: ' . $fromHeader], $mimeHeaders);
    if ($replyToHeader) {
        $commonHeaders[] = 'Reply-To: ' . $replyToHeader;
    }

    $encodedSubject = mailer_encode_header($subject);

    $bodyParts = [];
    $bodyParts[] = "--{$boundary}\r\n" .
        "Content-Type: text/plain; charset=UTF-8\r\n" .
        "Content-Transfer-Encoding: 8bit\r\n\r\n" .
        $textBody . "\r\n";
    $bodyParts[] = "--{$boundary}\r\n" .
        "Content-Type: text/html; charset=UTF-8\r\n" .
        "Content-Transfer-Encoding: 8bit\r\n\r\n" .
        $htmlBody . "\r\n";
    $bodyParts[] = "--{$boundary}--\r\n";
    $body = implode('', $bodyParts);

    $dataHeaders = array_merge([
        $dateHeader,
        'From: ' . $fromHeader,
        'To: ' . $toHeader,
        'Subject: ' . $encodedSubject,
    ], $replyToHeader ? ['Reply-To: ' . $replyToHeader] : []);
    $dataHeaders = array_merge($dataHeaders, $mimeHeaders);

    switch ($transport) {
        case 'smtp':
            return mailer_send_via_smtp($config, [
                'from_email' => $fromEmail,
                'to_email' => $toEmail,
                'data_headers' => $dataHeaders,
                'body' => $body,
            ]);
        case 'mail':
            return mailer_send_via_mail($toHeader, $encodedSubject, $commonHeaders, $body);
        case 'log':
        default:
            return mailer_log_message($config, $toHeader, $subject, $dataHeaders, $body);
    }
}

function mailer_send_via_mail(string $toHeader, string $encodedSubject, array $headers, string $body): bool
{
    $headersString = implode("\r\n", $headers);
    return mail($toHeader, $encodedSubject, $body, $headersString);
}

function mailer_log_message(array $config, string $toHeader, string $subject, array $dataHeaders, string $body): bool
{
    $path = $config['log']['path'] ?? (__DIR__ . '/../storage/logs/mail.log');
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $entry = str_repeat('=', 60) . "\n" .
        date('c') . "\n" .
        "To: {$toHeader}\n" .
        "Subject: {$subject}\n" .
        implode("\n", $dataHeaders) . "\n\n" .
        $body . "\n";

    return file_put_contents($path, $entry, FILE_APPEND) !== false;
}

function mailer_send_via_smtp(array $config, array $message): bool
{
    $smtp = $config['smtp'] ?? [];
    $host = $smtp['host'] ?? '127.0.0.1';
    $port = (int)($smtp['port'] ?? 25);
    $timeout = max(5, (int)($smtp['timeout'] ?? 15));
    $encryption = strtolower((string)($smtp['encryption'] ?? ''));

    $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $errno = 0;
    $errstr = '';
    $stream = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$stream) {
        error_log('[mail] SMTP connection failed: ' . $errstr);
        return false;
    }

    stream_set_timeout($stream, $timeout);

    try {
        mailer_smtp_expect($stream, [220]);
        $hostname = gethostname() ?: 'localhost';
        mailer_smtp_write($stream, 'EHLO ' . $hostname);
        mailer_smtp_expect($stream, [250]);

        if ($encryption === 'tls') {
            mailer_smtp_write($stream, 'STARTTLS');
            mailer_smtp_expect($stream, [220]);
            $cryptoMethod = defined('STREAM_CRYPTO_METHOD_TLS_CLIENT')
                ? STREAM_CRYPTO_METHOD_TLS_CLIENT
                : (STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0));
            if (!@stream_socket_enable_crypto($stream, true, $cryptoMethod)) {
                throw new RuntimeException('Failed to negotiate TLS with SMTP server.');
            }
            mailer_smtp_write($stream, 'EHLO ' . $hostname);
            mailer_smtp_expect($stream, [250]);
        }

        $username = $smtp['username'] ?? null;
        $password = $smtp['password'] ?? null;
        if ($username) {
            mailer_smtp_write($stream, 'AUTH LOGIN');
            mailer_smtp_expect($stream, [334]);
            mailer_smtp_write($stream, base64_encode((string)$username));
            mailer_smtp_expect($stream, [334]);
            mailer_smtp_write($stream, base64_encode((string)$password));
            mailer_smtp_expect($stream, [235]);
        }

        $from = $message['from_email'] ?? '';
        $to = $message['to_email'] ?? '';
        if (!$from || !$to) {
            throw new RuntimeException('Missing from/to email for SMTP send.');
        }

        mailer_smtp_write($stream, 'MAIL FROM:<' . $from . '>');
        mailer_smtp_expect($stream, [250, 251]);
        mailer_smtp_write($stream, 'RCPT TO:<' . $to . '>');
        mailer_smtp_expect($stream, [250, 251]);
        mailer_smtp_write($stream, 'DATA');
        mailer_smtp_expect($stream, [354]);

        $data = implode("\r\n", $message['data_headers'] ?? []) . "\r\n\r\n" . ($message['body'] ?? '') . "\r\n.\r\n";
        mailer_smtp_write($stream, $data, false);
        mailer_smtp_expect($stream, [250]);
        mailer_smtp_write($stream, 'QUIT');
        mailer_smtp_expect($stream, [221]);
    } catch (Throwable $e) {
        error_log('[mail] SMTP send failed: ' . $e->getMessage());
        fclose($stream);
        return false;
    }

    fclose($stream);
    return true;
}

function mailer_smtp_write($stream, string $command, bool $appendNewline = true): void
{
    $payload = $command;
    if ($appendNewline) {
        $payload .= "\r\n";
    }
    fwrite($stream, $payload);
}

function mailer_smtp_expect($stream, array $expectedCodes): void
{
    $response = '';
    while (($line = fgets($stream, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            $code = (int)substr($line, 0, 3);
            if (!in_array($code, $expectedCodes, true)) {
                throw new RuntimeException('Unexpected SMTP response: ' . trim($response));
            }
            return;
        }
    }

    throw new RuntimeException('SMTP connection closed unexpectedly.');
}
