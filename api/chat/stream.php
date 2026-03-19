<?php
declare(strict_types=1);
require_once '../../config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
initSession();
requireLogin();

// Check chat enabled
if (getSetting('chat_enabled', '1') !== '1') {
    http_response_code(403);
    echo json_encode(['error' => 'CHAT_DISABLED']);
    exit;
}

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Flush all output buffers
while (ob_get_level()) ob_end_clean();

// Disconnect immediately if connection is already gone
if (connection_aborted()) exit;

$pdo    = getDB();
$lastId = max(0, (int)($_GET['last_id'] ?? 0));

// Max runtime: 90 seconds then force reconnect.
// Shorter than the old 4-minute loop so PHP workers are freed faster on page refresh.
// The client reconnects automatically via the 'reconnect' event.
$maxIterations = 90;
$i = 0;

function sendEvent(string $event, mixed $data, int $id = 0): void {
    if (connection_aborted()) exit;
    if ($id) echo "id: {$id}\n";
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($data) . "\n\n";
    if (ob_get_level()) ob_flush();
    flush();
}

function keepAlive(): void {
    if (connection_aborted()) exit;
    echo ": heartbeat\n\n";
    if (ob_get_level()) ob_flush();
    flush();
}

// Initial heartbeat
keepAlive();

while ($i < $maxIterations) {
    // Check abort at the very top of each loop - frees the worker immediately on refresh
    if (connection_aborted()) exit;

    try {
        $stmt = $pdo->prepare("
            SELECT
                m.id, m.body, m.type, m.file_path, m.file_name,
                m.reply_to_id, m.is_deleted, m.created_at,
                u.id       AS user_id,
                u.full_name AS user_full_name,
                u.nickname  AS user_nickname,
                u.avatar    AS user_avatar
            FROM messages m
            JOIN users u ON u.id = m.user_id
            WHERE m.id > ? AND m.is_deleted = 0
            ORDER BY m.id ASC
            LIMIT 30
        ");
        $stmt->execute([$lastId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            if (connection_aborted()) exit;
            sendEvent('message', [
                'id'          => (int)$r['id'],
                'body'        => $r['body'],
                'type'        => $r['type'],
                'file_path'   => $r['file_path'],
                'file_name'   => $r['file_name'],
                'reply_to_id' => $r['reply_to_id'] ? (int)$r['reply_to_id'] : null,
                'is_deleted'  => false,
                'created_at'  => $r['created_at'],
                'user'        => [
                    'id'        => (int)$r['user_id'],
                    'full_name' => $r['user_full_name'],
                    'nickname'  => $r['user_nickname'] ?: $r['user_full_name'],
                    'avatar'    => $r['user_avatar'],
                ],
                'reactions'   => new \stdClass(),
            ], (int)$r['id']);
            $lastId = (int)$r['id'];
        }

        if (empty($rows)) keepAlive();

    } catch (\Throwable $e) {
        error_log('SSE error: ' . $e->getMessage());
        keepAlive();
    }

    $i++;

    // Sleep in short intervals so connection_aborted() is checked more frequently.
    // 2 x 500ms = 1 second total, but abort is detected within 500ms instead of 1s.
    for ($s = 0; $s < 2; $s++) {
        if (connection_aborted()) exit;
        usleep(500000); // 500ms
    }
}

// Tell client to reconnect
if (!connection_aborted()) {
    sendEvent('reconnect', ['reason' => 'timeout']);
}