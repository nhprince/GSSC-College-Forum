<?php
declare(strict_types=1);
require_once '../../config.php';
require_once '../../includes/api_init.php';
require_once '../../includes/uploader.php';
initSession();
requireLogin();

ob_clean();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// GET: fetch post feed
if ($method === 'GET') {
    // counts_only mode for header refresh
    if (!empty($_GET['counts_only'])) {
        try {
            $pdo    = getDB();
            $userId = currentUser()['id'];
            $stmt   = $pdo->prepare("
                SELECT COUNT(*) FROM posts p
                WHERE p.is_published = 1
                AND p.id NOT IN (SELECT post_id FROM post_reads WHERE user_id = ?)
            ");
            $stmt->execute([$userId]);
            $unread = (int)$stmt->fetchColumn();
            ob_clean();
            jsonSuccess(['unread_posts' => $unread]);
        } catch (\Throwable $e) {
            ob_clean();
            jsonSuccess(['unread_posts' => 0]);
        }
    }

    $page   = max(1, (int)($_GET['page']  ?? 1));
    $limit  = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $type   = $_GET['type']   ?? '';
    $search = trim($_GET['search'] ?? '');
    $userId = currentUser()['id'];

    try {
        $pdo    = getDB();
        $where  = ['p.is_published = 1'];
        $params = [];

        if ($type && in_array($type, ['announcement','event','poll'], true)) {
            $where[] = 'p.post_type = ?'; $params[] = $type;
        }
        if ($search) {
            $where[] = '(p.title LIKE ? OR p.body LIKE ?)';
            $params[] = "%{$search}%"; $params[] = "%{$search}%";
        }

        $whereStr = implode(' AND ', $where);

        // Count (uses params without userId/limit/offset)
        $cStmt = $pdo->prepare("SELECT COUNT(*) FROM posts p WHERE {$whereStr}");
        $cStmt->execute($params);
        $total = (int)$cStmt->fetchColumn();

        // Build final params: inject userId for the subquery, then limit/offset
        $queryParams = $params;
        $queryParams[] = $userId;
        $queryParams[] = $limit;
        $queryParams[] = $offset;

        $stmt = $pdo->prepare("
            SELECT
                p.*,
                (SELECT 1 FROM post_reads WHERE post_id = p.id AND user_id = ? LIMIT 1) AS `read`,
                (SELECT COUNT(*) FROM post_reads WHERE post_id = p.id) AS read_count,
                po.id           AS poll_id,
                po.is_closed    AS poll_closed,
                po.is_anonymous AS poll_anon,
                po.ends_at      AS poll_ends_at
            FROM posts p
            LEFT JOIN polls po ON po.post_id = p.id
            WHERE {$whereStr}
            ORDER BY p.is_pinned DESC, p.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($queryParams);
        $rows = $stmt->fetchAll();

        // For poll posts: fetch options + votes
        $pollIds = array_filter(array_column($rows, 'poll_id'));
        $pollData = [];
        if ($pollIds) {
            $in  = implode(',', array_map('intval', $pollIds));
            $opt = $pdo->query("SELECT * FROM poll_options WHERE poll_id IN ({$in}) ORDER BY display_order ASC");
            foreach ($opt->fetchAll() as $o) $pollData[$o['poll_id']]['options'][] = $o;

            $vot = $pdo->prepare("SELECT option_id, COUNT(*) AS cnt FROM poll_votes WHERE poll_id IN ({$in}) GROUP BY option_id");
            $vot->execute();
            foreach ($vot->fetchAll() as $v) $pollData['votes'][$v['option_id']] = (int)$v['cnt'];

            $myV = $pdo->prepare("SELECT poll_id, option_id FROM poll_votes WHERE poll_id IN ({$in}) AND user_id = ?");
            $myV->execute([$userId]);
            foreach ($myV->fetchAll() as $v) $pollData['my_votes'][$v['poll_id']] = (int)$v['option_id'];
        }

        $posts = array_map(fn($r) => formatPost($r, $pollData, hasRole('moderator')), $rows);

        ob_clean();
        jsonSuccess(['posts' => $posts, 'total' => $total, 'page' => $page, 'limit' => $limit]);

    } catch (\Throwable $e) {
        error_log($e->getMessage());
        ob_clean();
        jsonError('Failed to load posts.','DB_ERROR',500);
    }
}

// POST: create post
elseif ($method === 'POST') {
    requireRole('moderator');
    validateCsrf();

    $isMultipart = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data');
    $data = $isMultipart ? $_POST : getJsonBody();

    $postType = trim($data['post_type'] ?? '');
    $title    = trim($data['title']     ?? '');
    $body     = trim($data['body']      ?? '');
    $priority = trim($data['priority']  ?? 'general');
    $isPinned = (int)(bool)($data['is_pinned'] ?? 0);

    if (!$title) jsonError('Title is required.','VALIDATION_ERROR');
    if (!validateEnum($postType, ['announcement','event','poll'])) jsonError('Invalid post type.','VALIDATION_ERROR');
    if (!validateEnum($priority, ['urgent','info','general'])) jsonError('Invalid priority.','VALIDATION_ERROR');

    $imagePath  = null;
    $eventDate  = null;
    $eventTime  = null;
    $eventType  = null;

    // Image upload for announcements
    if ($postType === 'announcement' && !empty($_FILES['image'])) {
        try {
            $up = handleUpload($_FILES['image'], 'posts', ['jpg','jpeg','png','webp'], 5 * 1024 * 1024);
            $imagePath = $up['file_path'];
        } catch (\RuntimeException $e) {
            jsonError($e->getMessage(),'UPLOAD_ERROR');
        }
    }

    // Event fields
    if ($postType === 'event') {
        $eventDate = $data['event_date'] ?? null;
        $eventTime = $data['event_time'] ?? null;
        $eventType = $data['event_type'] ?? 'other';
        if (!$eventDate) jsonError('Event date is required.','VALIDATION_ERROR');
    }

    try {
        $user = currentUser();
        $pdo  = getDB();

        $stmt = $pdo->prepare("
            INSERT INTO posts (post_type, title, body, image_path, priority, is_pinned,
                               posted_by, event_date, event_time, event_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $postType, $title,
            $body      ?: null,
            $imagePath,
            $priority, $isPinned,
            $user['id'],
            $eventDate, $eventTime ?: null, $eventType
        ]);
        $postId = (int)$pdo->lastInsertId();

        // Create poll if needed
        if ($postType === 'poll') {
            $optionsRaw  = $data['poll_options'] ?? '[]';
            $options     = is_string($optionsRaw) ? json_decode($optionsRaw, true) : $optionsRaw;
            $endsAt      = $data['poll_ends_at']  ?? null;
            $isAnonymous = (int)(bool)($data['poll_anon'] ?? 0);

            if (!is_array($options) || count($options) < 2) {
                jsonError('Poll requires at least 2 options.','VALIDATION_ERROR');
            }

            $pdo->prepare("INSERT INTO polls (post_id, is_anonymous, ends_at) VALUES (?,?,?)")
                ->execute([$postId, $isAnonymous, $endsAt ?: null]);
            $pollId = (int)$pdo->lastInsertId();

            $optStmt = $pdo->prepare("INSERT INTO poll_options (poll_id, option_text, display_order) VALUES (?,?,?)");
            foreach (array_values($options) as $i => $opt) {
                $optStmt->execute([$pollId, trim((string)$opt), $i]);
            }
        }

        logAction('post.created', 'post', $postId, ['type' => $postType, 'title' => $title]);

        // Push notification for new notices and announcements
        if ($postType === 'announcement' || $postType === 'event' || $postType === 'poll') {
            $label = ucfirst($postType);
            if ($postType === 'announcement') $label = 'Notice';
            sendPushToAll("New $label: $title", mb_substr(strip_tags($body), 0, 100), "/?page=notices", $user['id']);
        }

        ob_clean();
        jsonSuccess(['id' => $postId], 201);

    } catch (\Throwable $e) {
        error_log($e->getMessage());
        ob_clean();
        jsonError('Failed to create post.','DB_ERROR',500);
    }
}

else {
    ob_clean();
    jsonError('Method not allowed.','METHOD_NOT_ALLOWED',405);
}

// Format helper
function formatPost(array $r, array $pollData, bool $showReadCount): array {
    $post = [
        'id'          => (int)$r['id'],
        'post_type'   => $r['post_type'],
        'title'       => $r['title'],
        'body'        => $r['body'],
        'image_path'  => $r['image_path'],
        'priority'    => $r['priority'],
        'is_pinned'   => (bool)$r['is_pinned'],
        'event_date'  => $r['event_date'],
        'event_time'  => $r['event_time'],
        'event_type'  => $r['event_type'],
        'read'        => (bool)$r['read'],
        'read_count'  => $showReadCount ? (int)$r['read_count'] : null,
        'created_at'  => $r['created_at'],
        'poll'        => null,
    ];

    if ($r['poll_id']) {
        $pollId  = (int)$r['poll_id'];
        $options = $pollData[$pollId]['options'] ?? [];
        $myVote  = $pollData['my_votes'][$pollId] ?? null;
        $total   = 0;

        $fmtOpts = array_map(function($o) use ($pollData, &$total) {
            $cnt   = $pollData['votes'][$o['id']] ?? 0;
            $total += $cnt;
            return ['id' => (int)$o['id'], 'text' => $o['option_text'], 'votes' => $cnt];
        }, $options);

        $post['poll'] = [
            'id'                   => $pollId,
            'is_closed'            => (bool)$r['poll_closed'],
            'is_anonymous'         => (bool)$r['poll_anon'],
            'ends_at'              => $r['poll_ends_at'],
            'options'              => $fmtOpts,
            'total_votes'          => $total,
            'user_voted_option_id' => $myVote,
        ];
    }

    return $post;
}