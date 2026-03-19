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

$body     = getJsonBody();
$pollId   = (int)($body['poll_id']   ?? 0);
$optionId = (int)($body['option_id'] ?? 0);
if (!$pollId || !$optionId) jsonError('poll_id and option_id required.','VALIDATION_ERROR');

try {
    $user = currentUser();
    $pdo  = getDB();

    // Get poll
    $stmt = $pdo->prepare("SELECT * FROM polls WHERE id = ? LIMIT 1");
    $stmt->execute([$pollId]);
    $poll = $stmt->fetch();
    if (!$poll) jsonError('Poll not found.','NOT_FOUND',404);
    if ($poll['is_closed']) jsonError('This poll is closed.','POLL_CLOSED',409);
    if ($poll['ends_at'] && strtotime($poll['ends_at']) < time()) jsonError('This poll has ended.','POLL_CLOSED',409);

    // Check already voted
    $stmt = $pdo->prepare("SELECT id FROM poll_votes WHERE poll_id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$pollId, $user['id']]);
    if ($stmt->fetch()) jsonError('You have already voted.','ALREADY_VOTED',409);

    // Validate option belongs to this poll
    $stmt = $pdo->prepare("SELECT id FROM poll_options WHERE id = ? AND poll_id = ? LIMIT 1");
    $stmt->execute([$optionId, $pollId]);
    if (!$stmt->fetch()) jsonError('Invalid option.','VALIDATION_ERROR');

    // Cast vote
    $pdo->prepare("INSERT INTO poll_votes (poll_id, option_id, user_id) VALUES (?,?,?)")
        ->execute([$pollId, $optionId, $user['id']]);

    // Return updated counts
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

    $fmtOpts = array_map(fn($o) => [
        'id'    => (int)$o['id'],
        'text'  => $o['option_text'],
        'votes' => (int)$o['votes'],
    ], $options);

    jsonSuccess([
        'options'              => $fmtOpts,
        'total_votes'          => $total,
        'user_voted_option_id' => $optionId,
    ]);
} catch (\Throwable $e) {
    error_log($e->getMessage());
    jsonError('Failed to vote.','DB_ERROR',500);
}
