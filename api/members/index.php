<?php
declare(strict_types=1);
require_once '../../config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
initSession();
requireLogin();
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed','METHOD_NOT_ALLOWED',405);

// Lightweight counts-only mode for header badge refresh
if (!empty($_GET['counts_only'])) {
    try {
        $pdo  = getDB();
        $stmt = $pdo->query("
            SELECT COUNT(*) AS total,
                   SUM(last_seen_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)) AS online_count
            FROM users WHERE is_active = 1 AND is_approved = 1
        ");
        $row = $stmt->fetch();
        jsonSuccess(['online_count' => (int)$row['online_count'], 'total' => (int)$row['total']]);
    } catch(\Throwable $e) {
        jsonSuccess(['online_count' => 0, 'total' => 0]);
    }
}

$search = trim($_GET['search'] ?? '');

try {
    $pdo    = getDB();
    $where  = ['u.is_active = 1', 'u.is_approved = 1'];
    $params = [];

    if ($search) {
        $where[] = '(u.full_name LIKE ? OR u.nickname LIKE ? OR u.roll_no LIKE ?)';
        $params[] = "%{$search}%"; $params[] = "%{$search}%"; $params[] = "%{$search}%";
    }

    $whereStr = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT
            u.id, u.full_name, u.nickname, u.roll_no,
            u.gender, u.avatar, u.role, u.last_seen_at,
            (u.last_seen_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)) AS is_online
        FROM users u
        WHERE {$whereStr}
        ORDER BY is_online DESC, u.full_name ASC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $male   = [];
    $female = [];
    $other  = [];

    foreach ($rows as $r) {
        $member = [
            'id'         => (int)$r['id'],
            'full_name'  => $r['full_name'],
            'nickname'   => $r['nickname'] ?: explode(' ', $r['full_name'])[0],
            'roll_no'    => $r['roll_no'],
            'gender'     => $r['gender'],
            'avatar'     => $r['avatar'],
            'role'       => $r['role'],
            'is_online'  => (bool)$r['is_online'],
            'last_seen'  => $r['last_seen_at'],
        ];
        if ($r['gender'] === 'female') $female[] = $member;
        elseif ($r['gender'] === 'male') $male[]  = $member;
        else $other[] = $member;
    }

    // Get online count and total separately
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(last_seen_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)) AS online_count
        FROM users
        WHERE is_active = 1 AND is_approved = 1
    ");
    $stmt->execute();
    $counts = $stmt->fetch();

    jsonSuccess([
        'male'         => $male,
        'female'       => $female,
        'other'        => $other,
        'online_count' => (int)$counts['online_count'],
        'total'        => (int)$counts['total'],
    ]);

} catch (\Throwable $e) {
    error_log($e->getMessage());
    jsonError('Failed to load members.','DB_ERROR',500);
}