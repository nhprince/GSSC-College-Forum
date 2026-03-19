<?php
declare(strict_types=1);
require_once '../../config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
initSession();
requireLogin();
requireRole('admin');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}
validateCsrf();

$body  = getJsonBody();
$email = trim($body['email'] ?? '');

// Allow open invites (no email required)  store as empty string
$isOpen = ($email === '' || $email === 'open@invite');
if (!$isOpen && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('Invalid email address.', 'VALIDATION_ERROR');
}
$storeEmail = $isOpen ? '' : $email;

try {
    $pdo = getDB();

    // If email provided, check it's not already registered
    if (!$isOpen) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$storeEmail]);
        if ($stmt->fetch()) {
            jsonError('This email is already registered.', 'EMAIL_TAKEN');
        }

        // Check for unexpired unused invite for same email
        $stmt = $pdo->prepare("SELECT id FROM invites WHERE email = ? AND used = 0 AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$storeEmail]);
        if ($stmt->fetch()) {
            jsonError('An active invite already exists for this email.', 'ALREADY_INVITED');
        }
    }

    $token = bin2hex(random_bytes(32));
    $user  = currentUser();

    $pdo->prepare("
        INSERT INTO invites (email, token, invited_by, expires_at)
        VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 48 HOUR))
    ")->execute([$storeEmail, $token, $user['id']]);

    $link = APP_URL . '/register.php?token=' . $token;

    logAction('invite.sent', 'invite', null, ['email' => $storeEmail ?: 'open']);

    jsonSuccess([
        'message'     => $isOpen ? 'Open invite link generated.' : "Invite created for {$storeEmail}",
        'invite_link' => $link,
        'token'       => $token,
        'expires_in'  => '48 hours',
    ]);

} catch (\Throwable $e) {
    error_log('invites.php: ' . $e->getMessage());
    jsonError('Failed to create invite.', 'DB_ERROR', 500);
}