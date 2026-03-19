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

$body    = getJsonBody();
$newEmail = trim($body['new_email']       ?? '');
$confirm  = trim($body['confirm_email']   ?? '');
$password = $body['current_password']     ?? '';

if (!$newEmail || !$confirm || !$password) jsonError('All fields required.','VALIDATION_ERROR');
if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) jsonError('Invalid email address.','VALIDATION_ERROR');
if ($newEmail !== $confirm) jsonError('Emails do not match.','VALIDATION_ERROR');

try {
    $user = currentUser();
    $pdo  = getDB();

    // Verify password
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) {
        jsonError('Incorrect password.','WRONG_PASSWORD',403);
    }

    // Check email not taken
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
    $stmt->execute([$newEmail, $user['id']]);
    if ($stmt->fetch()) jsonError('This email is already in use.','EMAIL_TAKEN',409);

    $pdo->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$newEmail, $user['id']]);
    $_SESSION['email'] = $newEmail;

    logAction('user.email_changed', 'user', $user['id']);
    jsonSuccess(['message' => 'Email updated successfully.']);
} catch (\Throwable $e) {
    error_log($e->getMessage());
    jsonError('Update failed.','DB_ERROR',500);
}
