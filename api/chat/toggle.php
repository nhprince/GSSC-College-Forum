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

$body    = getJsonBody();
$enabled = isset($body['enabled']) ? (bool)$body['enabled'] : null;
if ($enabled === null) jsonError('enabled field required.','VALIDATION_ERROR');

try {
    updateSetting('chat_enabled', $enabled ? '1' : '0');
    logAction('chat.toggled', null, null, ['enabled' => $enabled]);
    jsonSuccess(['chat_enabled' => $enabled]);
} catch (\Throwable $e) {
    error_log($e->getMessage());
    jsonError('Failed to update.','DB_ERROR',500);
}
