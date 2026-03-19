<?php
declare(strict_types=1);
require_once '../../config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
initSession();
requireLogin();
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed','METHOD_NOT_ALLOWED',405);
validateCsrf();

$body      = getJsonBody();
$messageId = (int)($body['message_id'] ?? 0);
if (!$messageId) jsonError('message_id required.','VALIDATION_ERROR');

try {
    $user = currentUser();
    $pdo  = getDB();

    $stmt = $pdo->prepare("SELECT user_id FROM messages WHERE id = ? AND is_deleted = 0 LIMIT 1");
    $stmt->execute([$messageId]);
    $msg = $stmt->fetch();
    if (!$msg) jsonError('Message not found.','NOT_FOUND',404);

    $isOwn = (int)$msg['user_id'] === (int)$user['id'];
    if (!$isOwn && !hasRole('moderator')) jsonError('Permission denied.','FORBIDDEN',403);

    $pdo->prepare("UPDATE messages SET is_deleted = 1 WHERE id = ?")->execute([$messageId]);

    if (!$isOwn) logAction('message.deleted','message',$messageId);

    jsonSuccess(['message_id' => $messageId]);
} catch (\Throwable $e) {
    error_log($e->getMessage());
    jsonError('Failed to delete.','DB_ERROR',500);
}
