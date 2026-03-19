<?php
declare(strict_types=1);
require_once '../../config.php';
require_once '../../includes/api_init.php';
initSession();
requireLogin();

ob_clean();
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed','METHOD_NOT_ALLOWED',405);
validateCsrf();

$body     = getJsonBody();
$pollId   = (int)($body['poll_id']   ?? 0);
$optionId = (int)($body['option_id'] ?? 0);
if (!$pollId || !$optionId) jsonError('poll_id and option_id required.','VALIDATION_ERROR');

try {
    $user = currentUser();
    $pdo  = getDB();

    $stmt = $pdo->prepare("SELECT * FROM polls WHERE id = ? LIMIT 1");
    $stmt->execute([$pollId]);
    $poll = $stmt->fetch();
    if (!$poll) jsonError('Poll not found.','NOT_FOUND',404);
    if ($poll['is_closed']) jsonError('This poll is closed.','POLL_CLOSED',409);
    if ($poll['ends_at'] && strtotime($poll['ends_at']) < time()) jsonError('This poll has ended.','POLL_CLOSED',409);

    $stmt = $pdo->prepare("SELECT id FROM poll_options WHERE id = ? AND poll_id = ? LIMIT 1");
    $stmt->execute([$optionId, $pollId]);
    if (!$stmt->fetch()) jsonError('Invalid option.','VALIDATION_ERROR');

    // Check existing vote - allow changing or retracting
    $stmt = $pdo->prepare("SELECT id, option_id FROM poll_votes WHERE poll_id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$pollId, $user['id']]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ((int)$existing['option_id'] === $optionId) {
            // Same option clicked = retract vote
            $pdo->prepare("DELETE FROM poll_votes WHERE poll_id = ? AND user_id = ?")
                ->execute([$pollId, $user['id']]);
            $userVotedOptionId = null;
        } else {
            // Different option = change vote
            $pdo->prepare("UPDATE poll_votes SET option_id = ?, voted_at = NOW() WHERE poll_id = ? AND user_id = ?")
                ->execute([$optionId, $pollId, $user['id']]);
            $userVotedOptionId = $optionId;
        }
    } else {
        $pdo->prepare("INSERT INTO poll_votes (poll_id, option_id, user_id) VALUES (?,?,?)")
            ->execute([$pollId, $optionId, $user['id']]);
        $userVotedOptionId = $optionId;
    }

    $stmt = $pdo->prepare("
        SELECT o.id, o.option_text, COUNT(v.id) AS votes
        FROM poll_options o
        LEFT JOIN poll_votes v ON v.option_id = o.id
        WHERE o.poll_id = ?
        GROUP BY o.id, o.option_text
        ORDER BY o.display_order ASC
    ");
    $stmt->execute([$pollId]);
    $options = $stmt->fetchAll();
    $total   = array_sum(array_column($options, 'votes'));

    ob_clean();
    jsonSuccess([
        'options'              => array_map(fn($o) => [
            'id'    => (int)$o['id'],
            'text'  => $o['option_text'],
            'votes' => (int)$o['votes'],
        ], $options),
        'total_votes'          => (int)$total,
        'user_voted_option_id' => $userVotedOptionId,
        'is_closed'            => (bool)$poll['is_closed'],
        'is_anonymous'         => (bool)$poll['is_anonymous'],
        'ends_at'              => $poll['ends_at'],
    ]);
} catch (\Throwable $e) {
    error_log($e->getMessage());
    ob_clean();
    jsonError('Failed to vote.','DB_ERROR',500);
}