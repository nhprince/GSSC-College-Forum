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
$endpoint = $body['endpoint'] ?? '';
$p256dh   = $body['p256dh']   ?? '';
$auth     = $body['auth']     ?? '';

if (!$endpoint || !$p256dh || !$auth) jsonError('Missing subscription data','VALIDATION_ERROR');

try {
    $user = currentUser();
    $pdo  = getDB();

    // Remove any existing subscription with the same endpoint to avoid duplicates
    $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?")->execute([$endpoint]);

    // Save new subscription
    $pdo->prepare("INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth) VALUES (?, ?, ?, ?)")
        ->execute([$user['id'], $endpoint, $p256dh, $auth]);

    jsonSuccess(['message' => 'Subscribed to push notifications']);
} catch (\Throwable $e) {
    error_log('push_subscribe: ' . $e->getMessage());
    jsonError('Failed to save subscription','DB_ERROR',500);
}
