<?php
function handle_register(PDO $pdo) {
    require_once __DIR__ . '/helpers.php';
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $name = trim($_POST['full_name'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
        $_SESSION['flash'] = __('Invalid email or password too short');
        redirect('/register');
    }
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users(email,password_hash,full_name) VALUES(?,?,?) RETURNING id');
    try {
        $stmt->execute([$email,$hash,$name]);
        $uid = (int)$stmt->fetchColumn();
        $_SESSION['uid'] = $uid;
        // Add default currencies
        $pdo->prepare('INSERT INTO user_currencies(user_id,code,is_main) VALUES(?,?,true)')->execute([$uid,'HUF']);
        $pdo->prepare('INSERT INTO user_currencies(user_id,code,is_main) VALUES(?,?,false) ON CONFLICT DO NOTHING')->execute([$uid,'EUR']);
        $pdo->prepare('INSERT INTO user_currencies(user_id,code,is_main) VALUES(?,?,false) ON CONFLICT DO NOTHING')->execute([$uid,'USD']);
        // Initialize baby steps
        for ($i=1;$i<=7;$i++) { $pdo->prepare('INSERT INTO baby_steps(user_id,step,status) VALUES(?,?,?)')->execute([$uid,$i,'in_progress']); }
        redirect('/');
    } catch (PDOException $e) {
        $_SESSION['flash'] = __('Registration failed.');
        redirect('/register');
    }
}

function handle_login(PDO $pdo) {
    require_once __DIR__ . '/helpers.php';
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if ($row && password_verify($pass, $row['password_hash'])) {
        $_SESSION['uid'] = (int)$row['id'];
        $needs = $pdo->prepare('SELECT needs_tutorial FROM users WHERE id=?');
        $needs->execute([$uid]);
        if ((bool)$needs->fetchColumn()) {
        redirect('/tutorial');
        } else {
        redirect('/'); // dashboard
        }
    }
    $_SESSION['flash'] = __('Invalid credentials');
    redirect('/login');
}

function handle_logout() {
    session_destroy();
    header('Location: /login');
    exit;
}