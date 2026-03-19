<?php
declare(strict_types=1);
require_once '../../config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/uploader.php';
initSession();
requireLogin();
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed','METHOD_NOT_ALLOWED',405);
validateCsrf();

$isMultipart = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data');
$data = $isMultipart ? $_POST : getJsonBody();

$fullName = trim($data['full_name'] ?? '');
$nickname = trim($data['nickname']  ?? '');
$gender   = trim($data['gender']    ?? '');

if ($fullName && !validateLength($fullName, 2, 100)) jsonError('Name must be 2100 characters.','VALIDATION_ERROR');
if ($gender && !validateEnum($gender, ['male','female','other'])) jsonError('Invalid gender.','VALIDATION_ERROR');

try {
    $user    = currentUser();
    $pdo     = getDB();
    $updates = [];
    $params  = [];

    if ($fullName) { $updates[] = 'full_name = ?'; $params[] = $fullName; }
    if ($nickname) { $updates[] = 'nickname = ?';  $params[] = $nickname; }
    if ($gender)   { $updates[] = 'gender = ?';    $params[] = $gender; }

    // Avatar upload
    if (!empty($_FILES['avatar'])) {
        $up = handleUpload($_FILES['avatar'], 'avatars', ['jpg','jpeg','png','webp'], 2 * 1024 * 1024);

        // Delete old avatar
        if ($user['avatar']) {
            $old = __DIR__ . '/../../uploads/avatars/' . basename($user['avatar']);
            if (file_exists($old)) @unlink($old);
        }

        $updates[] = 'avatar = ?';
        $params[]  = basename($up['file_path']);
        $_SESSION['avatar'] = basename($up['file_path']);
    }

    if (!$updates) jsonError('Nothing to update.','VALIDATION_ERROR');

    $params[] = $user['id'];
    $pdo->prepare("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);

    // Update session
    if ($fullName) $_SESSION['full_name'] = $fullName;
    if ($nickname) $_SESSION['nickname']  = $nickname;

    jsonSuccess(['message' => 'Profile updated.']);
} catch (\RuntimeException $e) {
    jsonError($e->getMessage(),'UPLOAD_ERROR');
} catch (\Throwable $e) {
    error_log($e->getMessage());
    jsonError('Update failed.','DB_ERROR',500);
}
