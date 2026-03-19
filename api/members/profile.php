<?php
declare(strict_types=1);
require_once '../../config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
initSession();
requireLogin();
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed','METHOD_NOT_ALLOWED',405);

$targetId = (int)($_GET['id'] ?? 0);
if (!$targetId) jsonError('User ID required.','VALIDATION_ERROR');

try {
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT id, full_name, nickname, roll_no, gender, avatar, role,
               last_seen_at, created_at,
               (last_seen_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)) AS is_online
        FROM users
        WHERE id = ? AND is_active = 1 AND is_approved = 1
        LIMIT 1
    ");
    $stmt->execute([$targetId]);
    $user = $stmt->fetch();
    if (!$user) jsonError('Member not found.','NOT_FOUND',404);

    // Post count (if mod+)
    $postCount = 0;
    if (hasRole('moderator')) {
        $s = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE posted_by = ? AND is_published = 1");
        $s->execute([$targetId]);
        $postCount = (int)$s->fetchColumn();
    }

    // Storage uploads
    $s = $pdo->prepare("SELECT COUNT(*) FROM storage_files WHERE uploaded_by = ? AND is_approved = 1");
    $s->execute([$targetId]);
    $storageCount = (int)$s->fetchColumn();

    jsonSuccess([
        'member' => [
            'id'           => (int)$user['id'],
            'full_name'    => $user['full_name'],
            'nickname'     => $user['nickname'] ?: explode(' ', $user['full_name'])[0],
            'roll_no'      => $user['roll_no'],
            'gender'       => $user['gender'],
            'avatar'       => $user['avatar'],
            'role'         => $user['role'],
            'is_online'    => (bool)$user['is_online'],
            'last_seen_at' => $user['last_seen_at'],
            'joined_at'    => $user['created_at'],
            'post_count'   => $postCount,
            'storage_count'=> $storageCount,
        ]
    ]);
} catch (\Throwable $e) {
    error_log($e->getMessage());
    jsonError('Failed to load profile.','DB_ERROR',500);
}
