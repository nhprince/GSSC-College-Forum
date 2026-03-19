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
$password = $body['password'] ?? '';
if (!$password) jsonError('Password required.','VALIDATION_ERROR');

try {
    $user = currentUser();
    $pdo  = getDB();

    // Verify password
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user['id']]);
    $row  = $stmt->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) {
        jsonError('Incorrect password.','WRONG_PASSWORD',403);
    }

    // Soft delete
    $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?")->execute([$user['id']]);
    logAction('account.deleted','user',$user['id']);

    logout(); // destroys session + redirects
} catch (\Throwable $e) {
    error_log($e->getMessage());
    jsonError('Failed to delete account.','DB_ERROR',500);
}
