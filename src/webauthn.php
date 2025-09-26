<?php

require_once __DIR__ . '/helpers.php';

const WEBAUTHN_SESSION_NAMESPACE = 'webauthn';
const WEBAUTHN_CHALLENGE_TTL = 300; // seconds

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    $data = strtr($data, '-_', '+/');
    $pad = strlen($data) % 4;
    if ($pad) {
        $data .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode($data, true);
    if ($decoded === false) {
        throw new RuntimeException('Invalid base64url data');
    }

    return $decoded;
}

function webauthn_expected_origin(): string
{
    $app = app_config('app') ?? [];
    if (!empty($app['origin'])) {
        return rtrim((string)$app['origin'], '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host;
}

function webauthn_rp_id(): string
{
    $app = app_config('app') ?? [];
    if (!empty($app['rp_id'])) {
        return (string)$app['rp_id'];
    }

    $origin = webauthn_expected_origin();
    $host = parse_url($origin, PHP_URL_HOST);

    return $host ?: 'localhost';
}

function webauthn_store_challenge(string $type, string $challenge, array $extra = []): void
{
    $_SESSION[WEBAUTHN_SESSION_NAMESPACE][$type] = $extra + [
        'challenge' => base64url_encode($challenge),
        'created' => time(),
    ];
}

function webauthn_get_challenge(string $type): ?array
{
    $bucket = $_SESSION[WEBAUTHN_SESSION_NAMESPACE][$type] ?? null;
    if (!$bucket) {
        return null;
    }

    if (($bucket['created'] ?? 0) + WEBAUTHN_CHALLENGE_TTL < time()) {
        unset($_SESSION[WEBAUTHN_SESSION_NAMESPACE][$type]);
        return null;
    }

    return $bucket;
}

function webauthn_clear_challenge(string $type): void
{
    unset($_SESSION[WEBAUTHN_SESSION_NAMESPACE][$type]);
}

function webauthn_registration_options(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new RuntimeException('User not found');
    }

    $displayName = trim((string)pii_decrypt($user['full_name'] ?? null));
    if ($displayName === '') {
        $displayName = (string)$user['email'];
    }

    $challenge = random_bytes(32);
    webauthn_store_challenge('register', $challenge, ['user_id' => $userId]);

    $excludeStmt = $pdo->prepare('SELECT credential_id FROM user_passkeys WHERE user_id = ?');
    $excludeStmt->execute([$userId]);
    $exclude = [];
    foreach ($excludeStmt->fetchAll(PDO::FETCH_COLUMN) as $credentialId) {
        $exclude[] = [
            'type' => 'public-key',
            'id' => $credentialId,
        ];
    }

    $userHandle = (string)$userId;
    $appConfig = app_config('app') ?? [];
    $rpName = $appConfig['name'] ?? 'MyMoneyMap';

    return [
        'publicKey' => [
            'challenge' => base64url_encode($challenge),
            'rp' => [
                'name' => $rpName,
                'id' => webauthn_rp_id(),
            ],
            'user' => [
                'id' => base64url_encode($userHandle),
                'name' => (string)$user['email'],
                'displayName' => $displayName,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],
            ],
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'residentKey' => 'preferred',
                'userVerification' => 'required',
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'excludeCredentials' => $exclude,
        ],
    ];
}

function webauthn_login_options(): array
{
    $challenge = random_bytes(32);
    webauthn_store_challenge('login', $challenge);

    return [
        'publicKey' => [
            'challenge' => base64url_encode($challenge),
            'timeout' => 60000,
            'rpId' => webauthn_rp_id(),
            'userVerification' => 'required',
            'allowCredentials' => [],
        ],
    ];
}

function webauthn_finish_registration(PDO $pdo, array $payload, ?string $label = null): array
{
    $challenge = webauthn_get_challenge('register');
    if (!$challenge || empty($challenge['user_id'])) {
        return ['success' => false, 'error' => __('Registration challenge expired.')];
    }

    $credential = $payload;

    $rawId = $credential['rawId'] ?? '';
    $attestationObject = $credential['response']['attestationObject'] ?? '';
    $clientDataJSON = $credential['response']['clientDataJSON'] ?? '';

    if (!$rawId || !$attestationObject || !$clientDataJSON) {
        return ['success' => false, 'error' => __('Invalid registration payload.')];
    }

    try {
        $clientData = json_decode(base64url_decode($clientDataJSON), true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        return ['success' => false, 'error' => __('Invalid registration payload.')];
    }

    if (!is_array($clientData) || ($clientData['type'] ?? '') !== 'webauthn.create') {
        return ['success' => false, 'error' => __('Invalid registration type.')];
    }

    if (!isset($clientData['challenge']) || !hash_equals((string)$challenge['challenge'], (string)$clientData['challenge'])) {
        return ['success' => false, 'error' => __('Registration challenge mismatch.')];
    }

    $origin = $clientData['origin'] ?? '';
    if (!hash_equals(webauthn_expected_origin(), rtrim((string)$origin, '/'))) {
        return ['success' => false, 'error' => __('Origin mismatch.')];
    }

    try {
        $attestation = webauthn_cbor_decode(base64url_decode($attestationObject));
    } catch (Throwable $e) {
        return ['success' => false, 'error' => __('Invalid attestation response.')];
    }

    $authData = $attestation['authData'] ?? null;
    if (!is_string($authData)) {
        return ['success' => false, 'error' => __('Invalid attestation response.')];
    }

    try {
        $parsed = webauthn_parse_authenticator_data($authData);
    } catch (Throwable $e) {
        return ['success' => false, 'error' => __('Invalid authenticator data.')];
    }

    $expectedRpIdHash = hash('sha256', webauthn_rp_id(), true);
    if (!hash_equals($expectedRpIdHash, $parsed['rpIdHash'])) {
        return ['success' => false, 'error' => __('RP ID mismatch.')];
    }

    $flags = $parsed['flags'] ?? 0;
    $userPresent = ($flags & 0x01) === 0x01;
    $userVerified = ($flags & 0x04) === 0x04;
    if (!$userPresent || !$userVerified) {
        return ['success' => false, 'error' => __('Biometric verification required.')];
    }

    $credentialId = $parsed['credentialId'] ?? null;
    $publicKeyCose = $parsed['credentialPublicKey'] ?? null;
    if (!is_string($credentialId) || !$publicKeyCose) {
        return ['success' => false, 'error' => __('Invalid attestation response.')];
    }

    $signCount = $parsed['signCount'] ?? 0;

    try {
        $publicKeyPem = webauthn_cose_to_pem($publicKeyCose);
    } catch (Throwable $e) {
        return ['success' => false, 'error' => __('Unsupported authenticator key.')];
    }

    if (!$publicKeyPem) {
        return ['success' => false, 'error' => __('Unsupported authenticator key.')];
    }

    $credentialIdEncoded = base64url_encode($credentialId);

    $check = $pdo->prepare('SELECT id FROM user_passkeys WHERE credential_id = ?');
    $check->execute([$credentialIdEncoded]);
    if ($check->fetchColumn()) {
        return ['success' => false, 'error' => __('This passkey is already registered.')];
    }

    $friendly = $label !== null ? trim($label) : '';
    if ($friendly === '') {
        $platform = $_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($platform) {
            $friendly = $platform . ' passkey';
        } elseif ($ua) {
            $friendly = substr($ua, 0, 80);
        } else {
            $friendly = __('Passkey');
        }
    }
    if (function_exists('mb_substr')) {
        $friendly = mb_substr($friendly, 0, 120);
    } else {
        $friendly = substr($friendly, 0, 120);
    }

    $insert = $pdo->prepare('INSERT INTO user_passkeys (user_id, credential_id, public_key_pem, sign_count, label) VALUES (?, ?, ?, ?, ?) RETURNING id');
    $insert->execute([
        (int)$challenge['user_id'],
        $credentialIdEncoded,
        $publicKeyPem,
        (int)$signCount,
        $friendly,
    ]);

    $passkeyId = (int)$insert->fetchColumn();
    webauthn_clear_challenge('register');

    return [
        'success' => true,
        'id' => $passkeyId,
    ];
}

function webauthn_finish_login(PDO $pdo, array $payload): array
{
    $challenge = webauthn_get_challenge('login');
    if (!$challenge) {
        return ['success' => false, 'error' => __('Login challenge expired.')];
    }

    $credential = $payload;

    $rawId = $credential['rawId'] ?? '';
    $response = $credential['response'] ?? [];
    $clientDataJSON = $response['clientDataJSON'] ?? '';
    $authenticatorData = $response['authenticatorData'] ?? '';
    $signature = $response['signature'] ?? '';

    if (!$rawId || !$clientDataJSON || !$authenticatorData || !$signature) {
        return ['success' => false, 'error' => __('Invalid login payload.')];
    }

    try {
        $clientData = json_decode(base64url_decode($clientDataJSON), true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        return ['success' => false, 'error' => __('Invalid login payload.')];
    }

    if (!is_array($clientData) || ($clientData['type'] ?? '') !== 'webauthn.get') {
        return ['success' => false, 'error' => __('Invalid login type.')];
    }

    if (!isset($clientData['challenge']) || !hash_equals((string)$challenge['challenge'], (string)$clientData['challenge'])) {
        return ['success' => false, 'error' => __('Login challenge mismatch.')];
    }

    $origin = $clientData['origin'] ?? '';
    if (!hash_equals(webauthn_expected_origin(), rtrim((string)$origin, '/'))) {
        return ['success' => false, 'error' => __('Origin mismatch.')];
    }

    $credentialIdEncoded = $credential['id'] ?? '';
    if ($credentialIdEncoded === '') {
        return ['success' => false, 'error' => __('Invalid credential.')];
    }

    $lookup = $pdo->prepare('SELECT id, user_id, public_key_pem, sign_count FROM user_passkeys WHERE credential_id = ?');
    $lookup->execute([$credentialIdEncoded]);
    $row = $lookup->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['success' => false, 'error' => __('Unknown passkey.')];
    }

    $userId = (int)$row['user_id'];

    try {
        $authData = base64url_decode($authenticatorData);
        $parsed = webauthn_parse_authenticator_data($authData);
    } catch (Throwable $e) {
        return ['success' => false, 'error' => __('Invalid authenticator data.')];
    }

    $expectedRpIdHash = hash('sha256', webauthn_rp_id(), true);
    if (!hash_equals($expectedRpIdHash, $parsed['rpIdHash'])) {
        return ['success' => false, 'error' => __('RP ID mismatch.')];
    }

    $flags = $parsed['flags'] ?? 0;
    $userPresent = ($flags & 0x01) === 0x01;
    $userVerified = ($flags & 0x04) === 0x04;
    if (!$userPresent || !$userVerified) {
        return ['success' => false, 'error' => __('Biometric verification required.')];
    }

    try {
        $signatureBinary = base64url_decode($signature);
        $clientDataBinary = base64url_decode($clientDataJSON);
    } catch (Throwable $e) {
        return ['success' => false, 'error' => __('Invalid login payload.')];
    }

    $dataToVerify = $authData . hash('sha256', $clientDataBinary, true);
    $publicKey = $row['public_key_pem'];

    $verified = openssl_verify($dataToVerify, $signatureBinary, $publicKey, OPENSSL_ALGO_SHA256);
    if ($verified !== 1) {
        return ['success' => false, 'error' => __('Signature verification failed.')];
    }

    $signCount = (int)($parsed['signCount'] ?? 0);
    $storedCount = (int)$row['sign_count'];
    if ($signCount > $storedCount) {
        $update = $pdo->prepare('UPDATE user_passkeys SET sign_count = ?, last_used = NOW() WHERE id = ?');
        $update->execute([$signCount, (int)$row['id']]);
    } else {
        $touch = $pdo->prepare('UPDATE user_passkeys SET last_used = NOW() WHERE id = ?');
        $touch->execute([(int)$row['id']]);
    }

    webauthn_clear_challenge('login');

    $userHandle = $response['userHandle'] ?? null;
    if ($userHandle) {
        try {
            $handle = base64url_decode($userHandle);
            if ($handle !== '' && (string)$handle !== (string)$userId) {
                $decoded = trim((string)$handle);
                if ($decoded !== '' && $decoded !== (string)$userId) {
                    return ['success' => false, 'error' => __('User mismatch.')];
                }
            }
        } catch (Throwable $e) {
            // ignore malformed userHandle
        }
    }

    return [
        'success' => true,
        'user_id' => $userId,
    ];
}

function webauthn_parse_authenticator_data(string $data): array
{
    $length = strlen($data);
    if ($length < 37) {
        throw new RuntimeException('Authenticator data too short');
    }

    $offset = 0;
    $rpIdHash = substr($data, $offset, 32);
    $offset += 32;

    $flags = ord($data[$offset]);
    $offset += 1;

    $signCount = unpack('N', substr($data, $offset, 4))[1];
    $offset += 4;

    $result = [
        'rpIdHash' => $rpIdHash,
        'flags' => $flags,
        'signCount' => $signCount,
    ];

    if (($flags & 0x40) === 0x40) {
        $aaguid = substr($data, $offset, 16);
        $offset += 16;

        $len = unpack('n', substr($data, $offset, 2))[1];
        $offset += 2;

        $credentialId = substr($data, $offset, $len);
        $offset += $len;

        $credentialPublicKey = webauthn_cbor_decode($data, $offset);

        $result['aaguid'] = $aaguid;
        $result['credentialId'] = $credentialId;
        $result['credentialPublicKey'] = $credentialPublicKey;
    }

    return $result;
}

function webauthn_cbor_decode(string $data, int &$offset = 0)
{
    if ($offset >= strlen($data)) {
        throw new RuntimeException('Unexpected end of CBOR data');
    }

    $initial = ord($data[$offset]);
    $offset++;

    $major = $initial >> 5;
    $additional = $initial & 0x1f;

    switch ($major) {
        case 0:
            return webauthn_cbor_read_length($data, $offset, $additional);
        case 1:
            $value = webauthn_cbor_read_length($data, $offset, $additional);
            return -1 - $value;
        case 2:
        case 3:
            $length = webauthn_cbor_read_length($data, $offset, $additional);
            $chunk = substr($data, $offset, $length);
            $offset += $length;
            return $chunk;
        case 4:
            $length = webauthn_cbor_read_length($data, $offset, $additional);
            $result = [];
            for ($i = 0; $i < $length; $i++) {
                $result[] = webauthn_cbor_decode($data, $offset);
            }
            return $result;
        case 5:
            $length = webauthn_cbor_read_length($data, $offset, $additional);
            $map = [];
            for ($i = 0; $i < $length; $i++) {
                $key = webauthn_cbor_decode($data, $offset);
                $value = webauthn_cbor_decode($data, $offset);
                $map[$key] = $value;
            }
            return $map;
        case 6:
            webauthn_cbor_read_length($data, $offset, $additional);
            return webauthn_cbor_decode($data, $offset);
        case 7:
            return webauthn_cbor_decode_simple($data, $offset, $additional);
        default:
            throw new RuntimeException('Unsupported CBOR major type');
    }
}

function webauthn_cbor_read_length(string $data, int &$offset, int $additional): int
{
    if ($additional < 24) {
        return $additional;
    }
    if ($additional === 24) {
        if ($offset >= strlen($data)) {
            throw new RuntimeException('Unexpected end of CBOR data');
        }
        $value = ord($data[$offset]);
        $offset += 1;
        return $value;
    }
    if ($additional === 25) {
        $chunk = substr($data, $offset, 2);
        if (strlen($chunk) !== 2) {
            throw new RuntimeException('Unexpected end of CBOR data');
        }
        $offset += 2;
        $arr = unpack('n', $chunk);
        return $arr[1];
    }
    if ($additional === 26) {
        $chunk = substr($data, $offset, 4);
        if (strlen($chunk) !== 4) {
            throw new RuntimeException('Unexpected end of CBOR data');
        }
        $offset += 4;
        $arr = unpack('N', $chunk);
        return (int)$arr[1];
    }
    if ($additional === 27) {
        $chunk = substr($data, $offset, 8);
        if (strlen($chunk) !== 8) {
            throw new RuntimeException('Unexpected end of CBOR data');
        }
        $offset += 8;
        $parts = unpack('N2', $chunk);
        return (int)(($parts[1] << 32) | $parts[2]);
    }

    throw new RuntimeException('Indefinite lengths are not supported');
}

function webauthn_cbor_decode_simple(string $data, int &$offset, int $additional)
{
    return match ($additional) {
        20 => false,
        21 => true,
        22, 23 => null,
        24 => ord($data[$offset++]),
        25 => webauthn_cbor_half_to_float(substr($data, $offset, 2), $offset),
        26 => webauthn_cbor_float(substr($data, $offset, 4), $offset),
        27 => webauthn_cbor_double(substr($data, $offset, 8), $offset),
        default => null,
    };
}

function webauthn_cbor_half_to_float(string $bytes, int &$offset): float
{
    if (strlen($bytes) !== 2) {
        throw new RuntimeException('Unexpected end of CBOR data');
    }
    $offset += 2;
    $data = unpack('n', $bytes)[1];
    $sign = ($data >> 15) & 0x1;
    $exp = ($data >> 10) & 0x1f;
    $mant = $data & 0x3ff;

    if ($exp === 0) {
        $value = $mant * pow(2, -24);
    } elseif ($exp === 0x1f) {
        $value = $mant ? NAN : INF;
    } else {
        $value = ($mant + 1024) * pow(2, $exp - 25);
    }

    return $sign ? -$value : $value;
}

function webauthn_cbor_float(string $bytes, int &$offset): float
{
    if (strlen($bytes) !== 4) {
        throw new RuntimeException('Unexpected end of CBOR data');
    }
    $offset += 4;
    return unpack('G', $bytes)[1];
}

function webauthn_cbor_double(string $bytes, int &$offset): float
{
    if (strlen($bytes) !== 8) {
        throw new RuntimeException('Unexpected end of CBOR data');
    }
    $offset += 8;
    return unpack('E', $bytes)[1];
}

function webauthn_cose_to_pem(array $cose): string
{
    $kty = $cose[1] ?? null;
    $alg = $cose[3] ?? null;
    $x = $cose[-2] ?? null;
    $y = $cose[-3] ?? null;

    if ($kty !== 2 || $alg !== -7 || !is_string($x) || !is_string($y)) {
        throw new RuntimeException('Unsupported COSE key');
    }

    $publicKey = "\x04" . $x . $y;
    $derPrefix = hex2bin('3059301306072A8648CE3D020106082A8648CE3D030107034200');
    if ($derPrefix === false) {
        throw new RuntimeException('Failed to encode key');
    }

    $der = $derPrefix . $publicKey;
    $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";

    return $pem;
}

function webauthn_list_passkeys(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT id, credential_id, label, created_at, last_used FROM user_passkeys WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function webauthn_delete_passkey(PDO $pdo, int $userId, int $passkeyId): bool
{
    $stmt = $pdo->prepare('DELETE FROM user_passkeys WHERE id = ? AND user_id = ?');
    $stmt->execute([$passkeyId, $userId]);

    return $stmt->rowCount() > 0;
}
