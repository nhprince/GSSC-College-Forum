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
if (mb_strlen($emoji) > 10) jsonError('Invalid emoji.','VALIDATION_ERROR');

try {
    $user = currentUser();
    $uid  = (int)$user['id'];
    $pdo  = getDB();

    // Verify message exists
    $stmt = $pdo->prepare("SELECT id FROM messages WHERE id = ? AND is_deleted = 0 LIMIT 1");
    $stmt->execute([$messageId]);
    if (!$stmt->fetch()) jsonError('Message not found.','NOT_FOUND',404);

    // What reaction does this user currently have on this message?
    $stmt = $pdo->prepare("SELECT emoji FROM message_reactions WHERE message_id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$messageId, $uid]);
    $current = $stmt->fetchColumn(); // false if none, string emoji if exists

    $pdo->beginTransaction();

    if ($current === $emoji) {
        // Clicked the same emoji they already have → remove it (toggle off)
        $pdo->prepare("DELETE FROM message_reactions WHERE message_id = ? AND user_id = ?")
            ->execute([$messageId, $uid]);
        $added = false;
    } else {
        // Either no reaction yet, or switching to a different emoji.
        // DELETE any existing first (handles both cases cleanly).
        $pdo->prepare("DELETE FROM message_reactions WHERE message_id = ? AND user_id = ?")
            ->execute([$messageId, $uid]);
        // INSERT the new emoji
        $pdo->prepare("INSERT INTO message_reactions (message_id, user_id, emoji) VALUES (?, ?, ?)")
            ->execute([$messageId, $uid, $emoji]);
        $added = true;
    }

    $pdo->commit();

    // Return updated reaction counts for this message
    $stmt = $pdo->prepare("
        SELECT emoji, COUNT(*) AS cnt
        FROM message_reactions
        WHERE message_id = ?
        GROUP BY emoji
        ORDER BY cnt DESC
    ");
    $stmt->execute([$messageId]);
    $reactions = [];
    foreach ($stmt->fetchAll() as $r) {
        $reactions[$r['emoji']] = (int)$r['cnt'];
    }

    // Return this user's current reaction on this message (at most one)
    $stmt = $pdo->prepare("SELECT emoji FROM message_reactions WHERE message_id = ? AND user_id = ?");
    $stmt->execute([$messageId, $uid]);
    $myEmoji     = $stmt->fetchColumn();
    $myReactions = $myEmoji ? [$myEmoji] : [];

    jsonSuccess([
        'reactions'    => empty($reactions) ? new \stdClass() : $reactions,
        'my_reactions' => $myReactions,
        'toggled'      => $emoji,
        'added'        => $added,
    ]);

} catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('react.php error: ' . $e->getMessage());
    jsonError('Failed to react: ' . $e->getMessage(), 'DB_ERROR', 500);
}