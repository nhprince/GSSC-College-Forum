<?php
declare(strict_types=1);
require_once '../../config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
initSession();
requireLogin();
requireRole('admin');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed','METHOD_NOT_ALLOWED',405);
validateCsrf();

$body = getJsonBody();
if (empty($body['confirm'])) jsonError('Confirmation required.','VALIDATION_ERROR');

try {
    $pdo = getDB();
    // Hard delete all messages and their reactions
    $pdo->exec("DELETE FROM message_reactions");
    $pdo->exec("DELETE FROM messages");
    logAction('chat.cleared');
    jsonSuccess(['message' => 'All messages cleared.']);
} catch (\Throwable $e) {
    error_log($e->getMessage());
    jsonError('Failed to clear messages.','DB_ERROR',500);
}
