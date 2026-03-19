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
$emoji     = trim($body['emoji'] ?? '');

if (!$messageId || !$emoji) jsonError('message_id and emoji required.','VALIDATION_ERROR');
if (mb_strlen($emoji) > 8)  jsonError('Invalid emoji.','VALIDATION_ERROR');

// Allowed emoji set
$allowed = ['','','','','','','','','',''];
if (!in_array($emoji, $allowed, true)) jsonError('Emoji not allowed.','VALIDATION_ERROR');

try {
    $user = currentUser();
    $pdo  = getDB();

    // Verify message exists
    $stmt = $pdo->prepare("SELECT id FROM messages WHERE id = ? AND is_deleted = 0 LIMIT 1");
    $stmt->execute([$messageId]);
    if (!$stmt->fetch()) jsonError('Message not found.','NOT_FOUND',404);

    // Toggle: insert or delete
    $stmt = $pdo->prepare("SELECT id FROM message_reactions WHERE message_id = ? AND user_id = ? AND emoji = ?");
    $stmt->execute([$messageId, $user['id'], $emoji]);
    $existing = $stmt->fetch();

    if ($existing) {
        $pdo->prepare("DELETE FROM message_reactions WHERE message_id = ? AND user_id = ? AND emoji = ?")
            ->execute([$messageId, $user['id'], $emoji]);
    } else {
        $pdo->prepare("INSERT INTO message_reactions (message_id, user_id, emoji) VALUES (?,?,?)")
            ->execute([$messageId, $user['id'], $emoji]);
    }

    // Return updated reaction counts
    $stmt = $pdo->prepare("
        SELECT emoji, COUNT(*) AS cnt
        FROM message_reactions WHERE message_id = ?
        GROUP BY emoji
    ");
    $stmt->execute([$messageId]);
    $rows = $stmt->fetchAll();
    $reactions = [];
    foreach ($rows as $r) $reactions[$r['emoji']] = (int)$r['cnt'];

    jsonSuccess(['reactions' => $reactions, 'toggled' => $emoji, 'added' => !$existing]);
} catch (\Throwable $e) {
    error_log($e->getMessage());
    jsonError('Failed to react.','DB_ERROR',500);
}
