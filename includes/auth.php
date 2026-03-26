<?php
declare(strict_types=1);

function initSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    if (APP_ENV === 'production') ini_set('session.cookie_secure', '1');

    // To make login persistent (Messenger-style), we set the session cookie
    // to last for a long time (e.g. 30 days).
    ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);
    ini_set('session.cookie_lifetime', (string)SESSION_LIFETIME);

    session_start();

    // If session is empty, try to restore from a "Remember Me" cookie.
    if (empty($_SESSION['logged_in']) && !empty($_COOKIE['remember_me'])) {
        try {
            $pdo = getDB();
            $token = $_COOKIE['remember_me'];
            $tokenHash = hash('sha256', $token);

            $stmt = $pdo->prepare("
                SELECT u.* FROM persistent_logins pl
                JOIN users u ON u.id = pl.user_id
                WHERE pl.token_hash = ? AND pl.expires_at > NOW() AND u.is_active = 1 AND u.is_approved = 1
                LIMIT 1
            ");
            $stmt->execute([$tokenHash]);
            $user = $stmt->fetch();

            if ($user) {
                loginUser($user, false); // Log in without regenerating the persistent token
            } else {
                // Invalid or expired token: clear the cookie
                setcookie('remember_me', '', time() - 3600, '/', '', APP_ENV === 'production', true);
            }
        } catch (\Throwable $_) {}
    }
}

function requireLogin(): void {
    if (empty($_SESSION['logged_in'])) {
        // Check if we are on an API request
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized', 'code' => 'UNAUTHORIZED']);
            exit;
        }
        header('Location: /login.php');
        exit;
    }
    $_SESSION['last_active'] = time();
    // Update last_seen_at in DB periodically (every 60s)
    if (empty($_SESSION['last_db_ping']) || time() - $_SESSION['last_db_ping'] > 60) {
        try {
            $pdo = getDB();
            $pdo->prepare("UPDATE users SET last_seen_at = NOW() WHERE id = ?")
                ->execute([$_SESSION['user_id']]);
            $_SESSION['last_db_ping'] = time();
        } catch (\Throwable $_) {}
    }
}

function requireRole(string $minRole): void {
    requireLogin();
    if (!hasRole($minRole)) {
        http_response_code(403);
        include __DIR__ . '/../pages/403.php';
        exit;
    }
}

function hasRole(string $minRole): bool {
    $h = ['student' => 1, 'moderator' => 2, 'admin' => 3];
    return ($h[$_SESSION['role'] ?? ''] ?? 0) >= ($h[$minRole] ?? 99);
}

function currentUser(): array {
    return [
        'id'            => $_SESSION['user_id'],
        'full_name'     => $_SESSION['full_name'],
        'nickname'      => $_SESSION['nickname'],
        'roll_no'       => $_SESSION['roll_no'],
        'email'         => $_SESSION['email'],
        'role'          => $_SESSION['role'],
        'avatar'        => $_SESSION['avatar'] ?? null,
        'notif_enabled' => $_SESSION['notif_enabled'] ?? true,
        'sound_enabled' => $_SESSION['sound_enabled'] ?? true,
    ];
}

function loginUser(array $user, bool $generateToken = true): void {
    session_regenerate_id(true);
    $_SESSION['user_id']       = $user['id'];
    $_SESSION['full_name']     = $user['full_name'];
    $_SESSION['nickname']      = $user['nickname'];
    $_SESSION['roll_no']       = $user['roll_no'];
    $_SESSION['email']         = $user['email'];
    $_SESSION['role']          = $user['role'];
    $_SESSION['avatar']        = $user['avatar'];
    $_SESSION['notif_enabled'] = (bool)$user['notif_enabled'];
    $_SESSION['sound_enabled'] = (bool)$user['sound_enabled'];
    $_SESSION['logged_in']     = true;
    $_SESSION['csrf_token']    = bin2hex(random_bytes(32));
    $_SESSION['last_active']   = time();
    $_SESSION['last_db_ping']  = time();

    if ($generateToken) {
        try {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiry = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);

            $pdo = getDB();
            $pdo->prepare("INSERT INTO persistent_logins (user_id, token_hash, expires_at) VALUES (?, ?, ?)")
                ->execute([$user['id'], $tokenHash, $expiry]);

            setcookie('remember_me', $token, time() + SESSION_LIFETIME, '/', '', APP_ENV === 'production', true);
        } catch (\Throwable $_) {}
    }
}

function logout(): void {
    if (!empty($_COOKIE['remember_me'])) {
        try {
            $tokenHash = hash('sha256', $_COOKIE['remember_me']);
            getDB()->prepare("DELETE FROM persistent_logins WHERE token_hash = ?")->execute([$tokenHash]);
        } catch (\Throwable $_) {}
        setcookie('remember_me', '', time() - 3600, '/', '', APP_ENV === 'production', true);
    }
    session_unset();
    session_destroy();
    header('Location: /login.php');
    exit;
}