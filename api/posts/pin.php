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

$body   = getJsonBody();
$action = basename(__FILE__, '.php'); // 'read', 'pin', or 'delete'
$postId = (int)($body['post_id'] ?? 0);
if (!$postId) jsonError('post_id required.','VALIDATION_ERROR');

try {
    $pdo  = getDB();
    $user = currentUser();

    // Verify post exists
    $stmt = $pdo->prepare("SELECT id, is_pinned FROM posts WHERE id = ? AND is_published = 1 LIMIT 1");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();
    if (!$post) jsonError('Post not found.','NOT_FOUND',404);

    if ($action === 'read') {
        // Mark as read  ignore duplicate
        $pdo->prepare("
            INSERT IGNORE INTO post_reads (post_id, user_id) VALUES (?,?)
        ")->execute([$postId, $user['id']]);
        jsonSuccess();
    }

    if ($action === 'pin') {
        requireRole('moderator');
        $pin = isset($body['pinned']) ? (int)(bool)$body['pinned'] : (int)!$post['is_pinned'];
        $pdo->prepare("UPDATE posts SET is_pinned = ? WHERE id = ?")->execute([$pin, $postId]);
        logAction('post.pinned','post',$postId,['pinned' => (bool)$pin]);
        jsonSuccess(['is_pinned' => (bool)$pin]);
    }

    if ($action === 'delete') {
        requireRole('moderator');
        $pdo->prepare("UPDATE posts SET is_published = 0 WHERE id = ?")->execute([$postId]);
        logAction('post.deleted','post',$postId);
        jsonSuccess();
    }

} catch (\Throwable $e) {
    error_log($e->getMessage());
    jsonError('Operation failed.','DB_ERROR',500);
}
