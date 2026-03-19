<?php
declare(strict_types=1);

/*  CSRF  */
function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrf(): void {
    $token = $_POST['csrf_token']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        jsonError('Invalid CSRF token', 'INVALID_CSRF', 403);
    }
}

/*  JSON responses  */
function jsonSuccess(array $data = [], int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function jsonError(string $msg, string $code = 'ERROR', int $status = 400): never {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $msg, 'code' => $code]);
    exit;
}

/*  Request body  */
function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    $d   = json_decode($raw ?: '', true);
    return is_array($d) ? $d : [];
}

/*  Settings  */
function getSetting(string $key, string $default = ''): string {
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT `value` FROM site_settings WHERE `key` = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $cache[$key] = $row ? $row['value'] : $default;
    } catch (\Throwable $_) {
        return $default;
    }
}

function updateSetting(string $key, string $value): void {
    $pdo  = getDB();
    $user = currentUser();
    $pdo->prepare("
        INSERT INTO site_settings (`key`, `value`, updated_by)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_by = VALUES(updated_by), updated_at = NOW()
    ")->execute([$key, $value, $user['id']]);
}

/*  Activity log  */
function logAction(string $action, ?string $targetType = null, ?int $targetId = null, array $meta = []): void {
    try {
        $user = currentUser();
        getDB()->prepare("
            INSERT INTO activity_log (user_id, action, target_type, target_id, meta, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            $user['id'], $action, $targetType, $targetId,
            $meta ? json_encode($meta) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (\Throwable $_) {}
}

/*  Rate limiting  */
function checkRateLimit(string $id, string $action, int $max, int $windowSec): bool {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT attempts, window_start FROM rate_limits WHERE identifier = ? AND action = ?");
        $stmt->execute([$id, $action]);
        $row = $stmt->fetch();
        $now = time();
        if ($row) {
            if (($now - strtotime($row['window_start'])) > $windowSec) {
                $pdo->prepare("DELETE FROM rate_limits WHERE identifier = ? AND action = ?")->execute([$id, $action]);
            } elseif ($row['attempts'] >= $max) {
                return false;
            } else {
                $pdo->prepare("UPDATE rate_limits SET attempts = attempts + 1 WHERE identifier = ? AND action = ?")->execute([$id, $action]);
                return true;
            }
        }
        $pdo->prepare("INSERT INTO rate_limits (identifier, action, attempts, window_start) VALUES (?, ?, 1, NOW())")->execute([$id, $action]);
        return true;
    } catch (\Throwable $_) {
        return true;
    }
}

/*  Validation  */
function validateRequired(array $data, array $fields): array {
    $errors = [];
    foreach ($fields as $f) {
        if (!isset($data[$f]) || trim((string)$data[$f]) === '') {
            $errors[$f] = ucfirst($f) . ' is required';
        }
    }
    return $errors;
}

function validateEnum(mixed $val, array $allowed): bool {
    return in_array($val, $allowed, true);
}

function validateLength(string $val, int $min, int $max): bool {
    $l = mb_strlen($val);
    return $l >= $min && $l <= $max;
}

function sanitizeHtml(string $html): string {
    return strip_tags($html, '<p><br><strong><em><ul><ol><li><a><h3><h4><blockquote>');
}

/*  Formatting  */
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)    return 'just now';
    if ($diff < 3600)  return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return date('d/m/y', strtotime($datetime));
}

function formatBytes(int $bytes): string {
    if ($bytes < 1024)       return $bytes . ' B';
    if ($bytes < 1048576)    return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

function escHtml(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}