<?php
declare(strict_types=1);
require_once '../../config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';
require_once '../../includes/functions.php';
require_once '../../includes/uploader.php';
initSession();
requireLogin();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

//  GET: fetch messages 
if ($method === 'GET') {
    $chatEnabled = getSetting('chat_enabled', '1') === '1';
    $limit    = min(100, max(1, (int)($_GET['limit']   ?? 50)));
    $beforeId = isset($_GET['before_id']) ? (int)$_GET['before_id'] : null;
    $sinceId  = isset($_GET['since_id'])  ? (int)$_GET['since_id']  : null;

    try {
        $pdo    = getDB();
        $params = [];
        $where  = ['m.is_deleted = 0'];

        if ($beforeId) { $where[] = 'm.id < ?'; $params[] = $beforeId; }
        if ($sinceId)  { $where[] = 'm.id > ?'; $params[] = $sinceId; }

        $whereStr = implode(' AND ', $where);
        $order    = $sinceId ? 'ASC' : 'DESC';

        $stmt = $pdo->prepare("
            SELECT
                m.id, m.body, m.type, m.file_path, m.file_name,
                m.reply_to_id, m.is_deleted, m.created_at,
                u.id   AS user_id,
                u.full_name AS user_full_name,
                u.nickname  AS user_nickname,
                u.avatar    AS user_avatar,
                -- reactions as JSON
                (SELECT JSON_OBJECTAGG(r.emoji, r.cnt)
                 FROM (
                   SELECT emoji, COUNT(*) AS cnt
                   FROM message_reactions
                   WHERE message_id = m.id
                   GROUP BY emoji
                 ) r
                ) AS reactions
            FROM messages m
            JOIN users u ON u.id = m.user_id
            WHERE {$whereStr}
            ORDER BY m.id {$order}
            LIMIT ?
        ");
        $params[] = $limit;
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // If DESC order (normal load), reverse so oldest is first
        if (!$sinceId) $rows = array_reverse($rows);

        $messages = array_map(fn($r) => formatMessage($r), $rows);
        $lastId   = $rows ? (int)end($rows)['id'] : 0;

        jsonSuccess([
            'messages'     => $messages,
            'chat_enabled' => $chatEnabled,
            'last_id'      => $lastId,
        ]);
    } catch (\Throwable $e) {
        error_log($e->getMessage());
        jsonError('Failed to load messages.', 'DB_ERROR', 500);
    }
}

//  POST: send a message 
elseif ($method === 'POST') {
    validateCsrf();

    // Check chat enabled
    if (getSetting('chat_enabled', '1') !== '1') {
        jsonError('Chat is currently disabled.', 'CHAT_DISABLED', 403);
    }

    // Rate limit: 1 message per second per user
    $user = currentUser();
    if (!checkRateLimit('msg_' . $user['id'], 'chat_send', 1, 1)) {
        jsonError('Slow down! One message per second.', 'RATE_LIMITED', 429);
    }

    $isMultipart = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data');
    $type = 'text';

    try {
        $pdo = getDB();

        if ($isMultipart) {
            // File or image upload
            if (empty($_FILES['file'])) jsonError('No file uploaded.', 'VALIDATION_ERROR');
            $imgExts  = ['jpg','jpeg','png','gif','webp'];
            $fileData = handleUpload($_FILES['file'], 'chat', ALLOWED_FILE_TYPES);
            $type     = in_array($fileData['file_type'], $imgExts, true) ? 'image' : 'file';
            $replyTo  = isset($_POST['reply_to_id']) ? (int)$_POST['reply_to_id'] : null;

            $stmt = $pdo->prepare("
                INSERT INTO messages (user_id, body, type, file_path, file_name, reply_to_id)
                VALUES (?, NULL, ?, ?, ?, ?)
            ");
            $stmt->execute([$user['id'], $type, $fileData['file_path'], $fileData['file_name'], $replyTo]);
        } else {
            $body    = getJsonBody();
            $text    = trim($body['body'] ?? '');
            $replyTo = isset($body['reply_to_id']) ? (int)$body['reply_to_id'] : null;

            if ($text === '') jsonError('Message cannot be empty.', 'VALIDATION_ERROR');
            if (mb_strlen($text) > 2000) jsonError('Message too long (max 2000 chars).', 'VALIDATION_ERROR');

            $stmt = $pdo->prepare("
                INSERT INTO messages (user_id, body, type, reply_to_id)
                VALUES (?, ?, 'text', ?)
            ");
            $stmt->execute([$user['id'], $text, $replyTo]);
        }

        $msgId = (int)$pdo->lastInsertId();

        // Fetch the full message back
        $stmt = $pdo->prepare("
            SELECT m.*, u.id AS user_id, u.full_name AS user_full_name,
                   u.nickname AS user_nickname, u.avatar AS user_avatar,
                   NULL AS reactions
            FROM messages m JOIN users u ON u.id = m.user_id
            WHERE m.id = ?
        ");
        $stmt->execute([$msgId]);
        $msg = formatMessage($stmt->fetch());

        jsonSuccess(['message' => $msg], 201);
    } catch (\RuntimeException $e) {
        jsonError($e->getMessage(), 'UPLOAD_ERROR');
    } catch (\Throwable $e) {
        error_log($e->getMessage());
        jsonError('Failed to send message.', 'DB_ERROR', 500);
    }
}

else {
    jsonError('Method not allowed.', 'METHOD_NOT_ALLOWED', 405);
}

//  Helper 
function formatMessage(array $r): array {
    $reactions = $r['reactions'] ? json_decode($r['reactions'], true) : (object)[];
    return [
        'id'          => (int)$r['id'],
        'body'        => $r['body'],
        'type'        => $r['type'],
        'file_path'   => $r['file_path'],
        'file_name'   => $r['file_name'],
        'reply_to_id' => $r['reply_to_id'] ? (int)$r['reply_to_id'] : null,
        'is_deleted'  => (bool)$r['is_deleted'],
        'created_at'  => $r['created_at'],
        'user'        => [
            'id'       => (int)$r['user_id'],
            'full_name'=> $r['user_full_name'],
            'nickname' => $r['user_nickname'] ?: $r['user_full_name'],
            'avatar'   => $r['user_avatar'],
        ],
        'reactions'   => $reactions ?: new \stdClass(),
    ];
}
