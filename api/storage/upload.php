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

$title       = trim($_POST['title']       ?? '');
$description = trim($_POST['description'] ?? '');
$category    = trim($_POST['category']    ?? 'other');

if (!$title) jsonError('Title is required.','VALIDATION_ERROR');
if (!validateEnum($category,['notes','syllabus','assignment','slides','result','other'])) {
    jsonError('Invalid category.','VALIDATION_ERROR');
}
if (empty($_FILES['file'])) jsonError('No file uploaded.','VALIDATION_ERROR');

$needsApproval = getSetting('storage_approval_required','1') === '1';

try {
    $up   = handleUpload($_FILES['file'], 'storage');
    $user = currentUser();
    $pdo  = getDB();

    // Moderators skip approval
    $approved = (!$needsApproval || hasRole('moderator')) ? 1 : 0;

    $stmt = $pdo->prepare("
        INSERT INTO storage_files
            (title, description, file_path, file_name, file_type, file_size, category, uploaded_by, is_approved)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $title, $description ?: null,
        $up['file_path'], $up['file_name'],
        $up['file_type'], $up['file_size'],
        $category, $user['id'], $approved
    ]);
    $fileId = (int)$pdo->lastInsertId();

    jsonSuccess([
        'id'              => $fileId,
        'pending_approval'=> !$approved,
        'message'         => $approved
            ? 'File uploaded successfully.'
            : 'File uploaded. Awaiting moderator approval.',
    ], 201);

} catch (\RuntimeException $e) {
    jsonError($e->getMessage(),'UPLOAD_ERROR');
} catch (\Throwable $e) {
    error_log($e->getMessage());
    jsonError('Upload failed.','DB_ERROR',500);
}
