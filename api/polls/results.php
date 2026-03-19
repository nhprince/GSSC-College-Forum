<?php
declare(strict_types=1);
require_once '../../config.php';
require_once '../../includes/api_init.php';
initSession();
requireLogin();
requireRole('moderator');

ob_clean();
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed','METHOD_NOT_ALLOWED',405);

$pollId  = (int)($_GET['poll_id']  ?? 0);
$postId  = (int)($_GET['post_id']  ?? 0);
if (!$pollId && !$postId) jsonError('poll_id or post_id required.','VALIDATION_ERROR');

try {
    $pdo = getDB();

    // Look up by post_id if poll_id not provided
    if (!$pollId && $postId) {
        $stmt = $pdo->prepare("SELECT id FROM polls WHERE post_id = ? LIMIT 1");
        $stmt->execute([$postId]);
        $row = $stmt->fetch();
        if (!$row) jsonError('Poll not found for this post.','NOT_FOUND',404);
        $pollId = (int)$row['id'];
    }

    // Get poll + post info
    $stmt = $pdo->prepare("
        SELECT po.id, po.is_closed, po.is_anonymous, po.ends_at, p.title
        FROM polls po JOIN posts p ON p.id = po.post_id
        WHERE po.id = ? LIMIT 1
    ");
    $stmt->execute([$pollId]);
    $poll = $stmt->fetch();
    if (!$poll) jsonError('Poll not found.','NOT_FOUND',404);

    // Options with vote counts
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

    // Voter breakdown per option (skip if anonymous)
    $voters = [];
    if (!$poll['is_anonymous']) {
        $stmt = $pdo->prepare("
            SELECT v.option_id, u.id AS user_id, u.full_name, u.nickname, u.roll_no, u.avatar, v.voted_at
            FROM poll_votes v
            JOIN users u ON u.id = v.user_id
            WHERE v.poll_id = ?
            ORDER BY v.voted_at ASC
        ");
        $stmt->execute([$pollId]);
        foreach ($stmt->fetchAll() as $row) {
            $voters[$row['option_id']][] = [
                'user_id'   => (int)$row['user_id'],
                'name'      => $row['nickname'] ?: $row['full_name'],
                'full_name' => $row['full_name'],
                'roll_no'   => $row['roll_no'],
                'avatar'    => $row['avatar'],
                'voted_at'  => $row['voted_at'],
            ];
        }
    }

    ob_clean();
    jsonSuccess([
        'poll'    => [
            'id'           => (int)$poll['id'],
            'title'        => $poll['title'],
            'is_closed'    => (bool)$poll['is_closed'],
            'is_anonymous' => (bool)$poll['is_anonymous'],
            'ends_at'      => $poll['ends_at'],
            'total_votes'  => (int)$total,
        ],
        'options' => array_map(fn($o) => [
            'id'     => (int)$o['id'],
            'text'   => $o['option_text'],
            'votes'  => (int)$o['votes'],
            'voters' => $voters[(int)$o['id']] ?? [],
        ], $options),
    ]);
} catch (\Throwable $e) {
    error_log($e->getMessage());
    ob_clean();
    jsonError('Failed to load results.','DB_ERROR',500);
}