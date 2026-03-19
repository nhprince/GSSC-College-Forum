<?php
declare(strict_types=1);
require_once '../../config.php';
require_once '../../includes/api_init.php';
initSession();
requireLogin();
requireRole('moderator');

ob_clean();
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed','METHOD_NOT_ALLOWED',405);
validateCsrf();

$body   = getJsonBody();
$pollId = (int)($body['poll_id'] ?? 0);
if (!$pollId) jsonError('poll_id required.','VALIDATION_ERROR');

try {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT id, is_closed FROM polls WHERE id = ? LIMIT 1");
    $stmt->execute([$pollId]);
    $poll = $stmt->fetch();
    if (!$poll) jsonError('Poll not found.','NOT_FOUND',404);

    $newState = $poll['is_closed'] ? 0 : 1;
    $pdo->prepare("UPDATE polls SET is_closed = ? WHERE id = ?")
        ->execute([$newState, $pollId]);

    logAction($newState ? 'poll.closed' : 'poll.opened', 'poll', $pollId);
    ob_clean();
    jsonSuccess(['is_closed' => (bool)$newState]);
} catch (\Throwable $e) {
    error_log($e->getMessage());
    ob_clean();
    jsonError('Failed to update poll.','DB_ERROR',500);
}