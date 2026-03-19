<?php
declare(strict_types=1);
require_once '../../config.php';
require_once '../../includes/api_init.php';
initSession();
requireLogin();

ob_clean();
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ob_clean();
    jsonError('Method not allowed','METHOD_NOT_ALLOWED',405);
}

$page     = max(1, (int)($_GET['page']     ?? 1));
$limit    = min(50, max(1, (int)($_GET['limit'] ?? 30)));
$offset   = ($page - 1) * $limit;
$category = $_GET['category'] ?? '';
$search   = trim($_GET['search'] ?? '');

try {
    $pdo    = getDB();
    $user   = currentUser();
    $where  = ['(sf.is_approved = 1 OR sf.uploaded_by = ?)'];
    $params = [$user['id']];

    if ($category && validateEnum($category, ['notes','syllabus','assignment','slides','result','other'])) {
        $where[] = 'sf.category = ?'; $params[] = $category;
    }
    if ($search) {
        $where[] = '(sf.title LIKE ? OR sf.description LIKE ?)';
        $params[] = "%{$search}%"; $params[] = "%{$search}%";
    }

    $whereStr = implode(' AND ', $where);

    $cStmt = $pdo->prepare("SELECT COUNT(*) FROM storage_files sf WHERE {$whereStr}");
    $cStmt->execute($params);
    $total = (int)$cStmt->fetchColumn();

    $queryParams   = $params;
    $queryParams[] = $limit;
    $queryParams[] = $offset;

    $stmt = $pdo->prepare("
        SELECT sf.id, sf.title, sf.description, sf.file_type, sf.file_size,
               sf.category, sf.is_approved, sf.download_count, sf.created_at,
               u.id AS uploader_id, u.full_name AS uploader_name, u.nickname AS uploader_nick
        FROM storage_files sf
        JOIN users u ON u.id = sf.uploaded_by
        WHERE {$whereStr}
        ORDER BY sf.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($queryParams);
    $rows = $stmt->fetchAll();

    $files = array_map(fn($r) => [
        'id'            => (int)$r['id'],
        'title'         => $r['title'],
        'description'   => $r['description'],
        'file_type'     => $r['file_type'],
        'file_size'     => (int)$r['file_size'],
        'category'      => $r['category'],
        'is_approved'   => (bool)$r['is_approved'],
        'download_count'=> (int)$r['download_count'],
        'created_at'    => $r['created_at'],
        'uploaded_by'   => [
            'id'       => (int)$r['uploader_id'],
            'full_name'=> $r['uploader_name'],
            'nickname' => $r['uploader_nick'] ?: $r['uploader_name'],
        ],
    ], $rows);

    ob_clean();
    jsonSuccess(['files' => $files, 'total' => $total, 'page' => $page]);
} catch (\Throwable $e) {
    error_log($e->getMessage());
    ob_clean();
    jsonError('Failed to load files.','DB_ERROR',500);
}