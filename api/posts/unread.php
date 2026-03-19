<?php
declare(strict_types=1);
require_once '../../config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
initSession();
requireLogin();
header('Content-Type: application/json');

try {
    $userId = currentUser()['id'];
    $pdo    = getDB();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM posts
        WHERE is_published = 1
        AND id NOT IN (SELECT post_id FROM post_reads WHERE user_id = ?)
    ");
    $stmt->execute([$userId]);
    $unread = (int)$stmt->fetchColumn();

    $pending = 0;
    if (hasRole('moderator')) {
        $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM storage_files WHERE is_approved = 0");
        $stmt2->execute();
        $pending = (int)$stmt2->fetchColumn();
    }

    jsonSuccess(['unread_posts' => $unread, 'pending_storage' => $pending]);
} catch (\Throwable $e) {
    jsonSuccess(['unread_posts' => 0, 'pending_storage' => 0]);
}
