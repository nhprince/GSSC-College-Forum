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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed','METHOD_NOT_ALLOWED',405);
validateCsrf();

$body   = getJsonBody();
$userId = (int)($body['user_id'] ?? 0);
$action = trim($body['action'] ?? '');

if (!$userId) jsonError('user_id required.','VALIDATION_ERROR');
if (!validateEnum($action,['approve','deactivate','activate','ban'])) jsonError('Invalid action.','VALIDATION_ERROR');

// Cannot act on own account
if ($userId === (int)currentUser()['id']) jsonError('Cannot modify your own account.','FORBIDDEN',403);

try {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT id,role FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $target = $stmt->fetch();
    if (!$target) jsonError('User not found.','NOT_FOUND',404);

    match($action) {
        'approve'    => $pdo->prepare("UPDATE users SET is_approved=1 WHERE id=?")->execute([$userId]),
        'deactivate',
        'ban'        => $pdo->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$userId]),
        'activate'   => $pdo->prepare("UPDATE users SET is_active=1 WHERE id=?")->execute([$userId]),
    };

    logAction('user.'.$action,'user',$userId);
    jsonSuccess(['message' => 'User updated.']);
} catch (\Throwable $e) {
    error_log($e->getMessage());
    jsonError('Update failed.','DB_ERROR',500);
}
