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

// On cPanel shared hosting, long-lived SSE connections hold a PHP-FPM worker
// permanently, starving all other requests (notices, storage, members).
// We immediately tell the client to switch to polling instead.
// The JS chat.js already has a fully working startPoll() fallback - we just use it.
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

while (ob_get_level()) ob_end_clean();

// Send the 'use_polling' event immediately - client will call startPoll()
echo "event: use_polling\n";
echo "data: {}\n\n";
if (ob_get_level()) ob_flush();
flush();
exit;