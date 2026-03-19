<?php
declare(strict_types=1);
require_once '../../config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
initSession();
requireLogin();
requireRole('moderator');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed','METHOD_NOT_ALLOWED',405);
validateCsrf();

$body   = getJsonBody();
$fileId = (int)($body['file_id'] ?? 0);
$action = trim($body['action'] ?? 'approve'); // approve | reject | delete
if (!$fileId) jsonError('file_id required.','VALIDATION_ERROR');
if (!validateEnum($action,['approve','reject','delete'])) jsonError('Invalid action.','VALIDATION_ERROR');

try {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM storage_files WHERE id = ? LIMIT 1");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();
    if (!$file) jsonError('File not found.','NOT_FOUND',404);

    if ($action === 'approve') {
        $pdo->prepare("UPDATE storage_files SET is_approved = 1 WHERE id = ?")->execute([$fileId]);
        logAction('storage.approved','storage_file',$fileId);
        jsonSuccess(['message' => 'File approved.']);
    }

    if ($action === 'reject' || $action === 'delete') {
        // Delete from filesystem
        $path = __DIR__ . '/../../uploads/' . $file['file_path'];
        if (file_exists($path)) @unlink($path);

        $pdo->prepare("DELETE FROM storage_files WHERE id = ?")->execute([$fileId]);
        logAction($action === 'reject' ? 'storage.rejected' : 'storage.deleted','storage_file',$fileId);
        jsonSuccess(['message' => $action === 'reject' ? 'File rejected.' : 'File deleted.']);
    }

} catch (\Throwable $e) {
    error_log($e->getMessage());
    jsonError('Operation failed.','DB_ERROR',500);
}
