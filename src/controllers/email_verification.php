<?php
require_once __DIR__ . '/../helpers.php';

function email_verification_handle(PDO $pdo): void
{
    $token = trim((string)($_GET['token'] ?? ''));
    if ($token === '') {
        view('auth/verify_email', [
            'status' => 'missing',
        ]);
        return;
    }

    $stmt = $pdo->prepare('SELECT id, email_verified_at FROM users WHERE email_verification_token = ? LIMIT 1');
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        view('auth/verify_email', [
            'status' => 'invalid',
        ]);
        return;
    }

    if (!empty($user['email_verified_at'])) {
        view('auth/verify_email', [
            'status' => 'already',
        ]);
        return;
    }

    $pdo->prepare('UPDATE users SET email_verified_at = NOW(), email_verification_token = NULL WHERE id = ?')
        ->execute([(int)$user['id']]);

    view('auth/verify_email', [
        'status' => 'success',
    ]);
}
