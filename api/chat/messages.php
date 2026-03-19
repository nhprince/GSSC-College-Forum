<?php
declare(strict_types=1);
require_once '../../config.php';
require_once '../../includes/api_init.php';
require_once '../../includes/uploader.php';
initSession();
requireLogin();

ob_clean();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {

    $chatEnabled = getSetting('chat_enabled', '1') === '1';
    $limit       = min(100, max(1, (int)($_GET['limit']    ?? 50)));
    $beforeId    = isset($_GET['before_id']) ? (int)$_GET['before_id'] : null;
    $sinceId     = isset($_GET['since_id'])  ? (int)$_GET['since_id']  : null;

    try {
        $pdo    = getDB();
        $params = [];
        $where  = ['m.is_deleted = 0'];
        if ($beforeId) { $where[] = 'm.id < ?'; $params[] = $beforeId; }
        if ($sinceId)  { $where[] = 'm.id > ?'; $params[] = $sinceId; }
        $whereStr = implode(' AND ', $where);
        $order    = $sinceId ? 'ASC' : 'DESC';

        $stmt = $pdo->prepare("
            SELECT m.id, m.body, m.type, m.file_path, m.file_name,
                   m.reply_to_id, m.is_deleted, m.created_at,
                   u.id AS user_id, u.full_name AS user_full_name,
                   u.nickname AS user_nickname, u.avatar AS user_avatar
            FROM messages m
            JOIN users u ON u.id = m.user_id
            WHERE {$whereStr}
            ORDER BY m.id {$order}
            LIMIT ?
        ");
        $params[] = $limit;
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$sinceId) $rows = array_reverse($rows);

        // Reactions in PHP (avoids JSON_OBJECTAGG version issues)
        $reactions = [];
        if (!empty($rows)) {
            $ids   = implode(',', array_map(fn($r) => (int)$r['id'], $rows));
            $rStmt = $pdo->query("
                SELECT message_id, emoji, COUNT(*) AS cnt
                FROM message_reactions WHERE message_id IN ({$ids})
                GROUP BY message_id, emoji
            ");
            foreach ($rStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $reactions[(int)$r['message_id']][$r['emoji']] = (int)$r['cnt'];
            }
        }

        $messages = array_map(fn($r) => buildMsg($r, $reactions[(int)$r['id']] ?? []), $rows);
        $lastId   = !empty($rows) ? (int)end($rows)['id'] : 0;

        ob_clean();
        echo json_encode(['success' => true, 'data' => [
            'messages'     => $messages,
            'chat_enabled' => $chatEnabled,
            'last_id'      => $lastId,
        ]]);

    } catch (\Throwable $e) {
        error_log('chat/messages GET: ' . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Failed to load messages.', 'code' => 'DB_ERROR']);
    }

} elseif ($method === 'POST') {

    validateCsrf();

    if (getSetting('chat_enabled', '1') !== '1') {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Chat is disabled.', 'code' => 'CHAT_DISABLED']);
        exit;
    }

    $user = currentUser();
    if (!checkRateLimit('msg_' . $user['id'], 'chat_send', 2, 2)) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Slow down!', 'code' => 'RATE_LIMITED']);
        exit;
    }

    $ct          = $_SERVER['CONTENT_TYPE'] ?? '';
    $isMultipart = str_contains($ct, 'multipart/form-data');

    try {
        $pdo = getDB();

        if ($isMultipart) {
            if (empty($_FILES['file'])) {
                ob_clean(); echo json_encode(['success'=>false,'error'=>'No file.','code'=>'VALIDATION_ERROR']); exit;
            }
            $imgExts  = ['jpg','jpeg','png','gif','webp'];
            $fileData = handleUpload($_FILES['file'], 'chat', ALLOWED_FILE_TYPES);
            $type     = in_array($fileData['file_type'], $imgExts, true) ? 'image' : 'file';
            $replyTo  = isset($_POST['reply_to_id']) ? (int)$_POST['reply_to_id'] : null;
            $pdo->prepare("INSERT INTO messages (user_id, type, file_path, file_name, reply_to_id) VALUES (?,?,?,?,?)")
                ->execute([$user['id'], $type, $fileData['file_path'], $fileData['file_name'], $replyTo]);
        } else {
            $raw  = file_get_contents('php://input');
            $body = json_decode($raw ?: '', true);
            if (!is_array($body)) $body = [];
            $text    = trim($body['body'] ?? '');
            $replyTo = isset($body['reply_to_id']) ? (int)$body['reply_to_id'] : null;
            if ($text === '') {
                ob_clean(); echo json_encode(['success'=>false,'error'=>'Message empty.','code'=>'VALIDATION_ERROR']); exit;
            }
            if (mb_strlen($text) > 2000) {
                ob_clean(); echo json_encode(['success'=>false,'error'=>'Too long.','code'=>'VALIDATION_ERROR']); exit;
            }
            $pdo->prepare("INSERT INTO messages (user_id, body, type, reply_to_id) VALUES (?,?,'text',?)")
                ->execute([$user['id'], $text, $replyTo]);
        }

        $msgId = (int)$pdo->lastInsertId();
        $stmt  = $pdo->prepare("
            SELECT m.*, u.id AS user_id, u.full_name AS user_full_name,
                   u.nickname AS user_nickname, u.avatar AS user_avatar
            FROM messages m JOIN users u ON u.id = m.user_id WHERE m.id = ?
        ");
        $stmt->execute([$msgId]);
        $msg = buildMsg($stmt->fetch(PDO::FETCH_ASSOC), []);

        ob_clean();
        http_response_code(201);
        echo json_encode(['success' => true, 'data' => ['message' => $msg]]);

    } catch (\RuntimeException $e) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => $e->getMessage(), 'code' => 'UPLOAD_ERROR']);
    } catch (\Throwable $e) {
        error_log('chat/messages POST: ' . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Failed to send.', 'code' => 'DB_ERROR']);
    }

} else {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Method not allowed.', 'code' => 'METHOD_NOT_ALLOWED']);
}

function buildMsg(array $r, array $rxn): array {
    return [
        'id'          => (int)$r['id'],
        'body'        => $r['body']        ?? null,
        'type'        => $r['type']        ?? 'text',
        'file_path'   => $r['file_path']   ?? null,
        'file_name'   => $r['file_name']   ?? null,
        'reply_to_id' => isset($r['reply_to_id']) && $r['reply_to_id'] ? (int)$r['reply_to_id'] : null,
        'is_deleted'  => !empty($r['is_deleted']),
        'created_at'  => $r['created_at']  ?? null,
        'user' => [
            'id'        => (int)($r['user_id'] ?? 0),
            'full_name' => $r['user_full_name'] ?? '',
            'nickname'  => $r['user_nickname']  ?: ($r['user_full_name'] ?? ''),
            'avatar'    => $r['user_avatar']    ?? null,
        ],
        'reactions' => empty($rxn) ? new \stdClass() : $rxn,
    ];
}