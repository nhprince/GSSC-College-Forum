<?php
declare(strict_types=1);
require_once '../../config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
initSession();
requireLogin();

$fileId = (int)($_GET['id'] ?? 0);
if (!$fileId) { http_response_code(400); echo 'Missing file ID.'; exit; }

try {
    $user = currentUser();
    $pdo  = getDB();

    $stmt = $pdo->prepare("
        SELECT * FROM storage_files
        WHERE id = ? AND (is_approved = 1 OR uploaded_by = ?)
        LIMIT 1
    ");
    $stmt->execute([$fileId, $user['id']]);
    $file = $stmt->fetch();

    if (!$file) { http_response_code(404); echo 'File not found.'; exit; }

    $path = __DIR__ . '/../../uploads/' . $file['file_path'];
    if (!file_exists($path)) { http_response_code(404); echo 'File missing on server.'; exit; }

    // Increment download count
    $pdo->prepare("UPDATE storage_files SET download_count = download_count + 1 WHERE id = ?")
        ->execute([$fileId]);

    // Safe filename for download header
    $safeOriginal = preg_replace('/[^a-zA-Z0-9._\- ]/', '_', $file['file_name']);

    // Detect MIME
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($path) ?: 'application/octet-stream';

    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $safeOriginal . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');

    readfile($path);
    exit;

} catch (\Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo 'Server error.';
}
