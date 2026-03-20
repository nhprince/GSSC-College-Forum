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

$method = $_SERVER['REQUEST_METHOD'];

// GET: list active (unused, unexpired) invites
if ($method === 'GET') {
    try {
        $pdo  = getDB();
        $stmt = $pdo->query("
            SELECT i.id, i.email, i.token, i.used, i.expires_at, i.created_at,
                   u.full_name AS invited_by_name
            FROM invites i
            JOIN users u ON u.id = i.invited_by
            WHERE i.used = 0 AND i.expires_at > NOW()
            ORDER BY i.created_at DESC
            LIMIT 50
        ");
        $invites = array_map(fn($r) => [
            'id'              => (int)$r['id'],
            'email'           => $r['email'] ?: null,
            'is_open'         => ($r['email'] === ''),
            'token'           => $r['token'],
            'expires_at'      => $r['expires_at'],
            'created_at'      => $r['created_at'],
            'invited_by_name' => $r['invited_by_name'],
            'link'            => APP_URL . '/register.php?token=' . $r['token'],
        ], $stmt->fetchAll());
        jsonSuccess(['invites' => $invites]);
    } catch (\Throwable $e) {
        error_log('invites GET: ' . $e->getMessage());
        jsonError('Failed to load invites.', 'DB_ERROR', 500);
    }
}

// POST: create new invite
elseif ($method === 'POST') {
    validateCsrf();

    $body  = getJsonBody();
    $email = trim($body['email'] ?? '');

    $isOpen = ($email === '');
    if (!$isOpen && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('Invalid email address.', 'VALIDATION_ERROR');
    }
    $storeEmail = $isOpen ? '' : $email;

    try {
        $pdo = getDB();

        if (!$isOpen) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$storeEmail]);
            if ($stmt->fetch()) jsonError('This email is already registered.', 'EMAIL_TAKEN');

            $stmt = $pdo->prepare("SELECT id FROM invites WHERE email = ? AND used = 0 AND expires_at > NOW() LIMIT 1");
            $stmt->execute([$storeEmail]);
            if ($stmt->fetch()) jsonError('An active invite already exists for this email.', 'ALREADY_INVITED');
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
        error_log('invites POST: ' . $e->getMessage());
        jsonError('Failed to create invite.', 'DB_ERROR', 500);
    }
}

// DELETE: revoke an invite
elseif ($method === 'DELETE') {
    validateCsrf();

    $body     = getJsonBody();
    $inviteId = (int)($body['invite_id'] ?? 0);
    if (!$inviteId) jsonError('invite_id required.', 'VALIDATION_ERROR');

    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT id FROM invites WHERE id = ? AND used = 0 LIMIT 1");
        $stmt->execute([$inviteId]);
        if (!$stmt->fetch()) jsonError('Invite not found or already used.', 'NOT_FOUND', 404);

        $pdo->prepare("DELETE FROM invites WHERE id = ?")->execute([$inviteId]);
        logAction('invite.revoked', 'invite', $inviteId);
        jsonSuccess(['message' => 'Invite revoked.']);
    } catch (\Throwable $e) {
        error_log('invites DELETE: ' . $e->getMessage());
        jsonError('Failed to revoke invite.', 'DB_ERROR', 500);
    }
}

else {
    jsonError('Method not allowed.', 'METHOD_NOT_ALLOWED', 405);
}