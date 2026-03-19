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

$body   = getJsonBody();
$userId = (int)($body['user_id'] ?? 0);
$role   = trim($body['role'] ?? '');

if (!$userId) jsonError('user_id required.','VALIDATION_ERROR');
if (!validateEnum($role,['student','moderator'])) jsonError('Can only assign student or moderator roles.','VALIDATION_ERROR');
if ($userId === (int)currentUser()['id']) jsonError('Cannot change your own role.','FORBIDDEN',403);

try {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role != 'admin' LIMIT 1");
    $stmt->execute([$userId]);
    if (!$stmt->fetch()) jsonError('User not found or is an admin.','NOT_FOUND',404);

    $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $userId]);
    logAction('user.role_changed','user',$userId,['role' => $role]);
    jsonSuccess(['message' => 'Role updated.']);
} catch (\Throwable $e) {
    error_log($e->getMessage());
    jsonError('Update failed.','DB_ERROR',500);
}
