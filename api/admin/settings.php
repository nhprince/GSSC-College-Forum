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

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    $pdo  = getDB();
    $rows = $pdo->query("SELECT `key`,`value` FROM site_settings")->fetchAll();
    $out  = [];
    foreach ($rows as $r) $out[$r['key']] = $r['value'];
    jsonSuccess($out);
}
if ($method === 'POST') {
    validateCsrf();
    $body = getJsonBody();
    $allowed = ['site_name','college_name','about_us','rules','registration_mode','chat_enabled','storage_approval_required','maintenance_mode'];
    foreach ($allowed as $k) {
        if (isset($body[$k])) updateSetting($k, (string)$body[$k]);
    }
    logAction('settings.updated');
    jsonSuccess(['message' => 'Settings updated.']);
}
jsonError('Method not allowed','METHOD_NOT_ALLOWED',405);
