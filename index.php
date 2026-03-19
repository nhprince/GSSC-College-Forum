<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
initSession();

// Get clean page slug
$page = trim($_GET['page'] ?? '', '/');
$page = strtolower(preg_replace('/[^a-z0-9\-\/]/', '', $page));

//  Maintenance mode (block non-mods) 
if (getSetting('maintenance_mode', '0') === '1') {
    $isMod = !empty($_SESSION['logged_in']) && hasRole('moderator');
    $isAuthPage = in_array($page, ['login','register','forgot-password','reset-password'], true);
    if (!$isMod && !$isAuthPage) {
        require __DIR__ . '/pages/maintenance.php';
        exit;
    }
}

//  Routing 
switch ($page) {
    case '':
    case 'chat':
    case 'notices':
    case 'storage':
    case 'members':
        requireLogin();
        require __DIR__ . '/app.php';
        break;

    case 'login':
        require __DIR__ . '/login.php';
        break;

    case 'register':
        require __DIR__ . '/register.php';
        break;

    case 'forgot-password':
        require __DIR__ . '/forgot-password.php';
        break;

    case 'reset-password':
        require __DIR__ . '/reset-password.php';
        break;

    case 'profile':
        requireLogin();
        require __DIR__ . '/profile.php';
        break;

    case 'admin':
    case 'admin/':
        requireLogin();
        requireRole('moderator');
        require __DIR__ . '/admin/index.php';
        break;

    case 'admin/noticeboard':
        requireLogin();
        requireRole('moderator');
        require __DIR__ . '/admin/noticeboard.php';
        break;

    case 'admin/chat':
        requireLogin();
        requireRole('moderator');
        require __DIR__ . '/admin/chat.php';
        break;

    case 'admin/storage':
        requireLogin();
        requireRole('moderator');
        require __DIR__ . '/admin/storage.php';
        break;

    case 'admin/members':
        requireLogin();
        requireRole('admin');
        require __DIR__ . '/admin/members.php';
        break;

    case 'admin/settings':
        requireLogin();
        requireRole('admin');
        require __DIR__ . '/admin/settings.php';
        break;

    case 'admin/activity':
        requireLogin();
        requireRole('moderator');
        require __DIR__ . '/admin/activity.php';
        break;

    default:
        http_response_code(404);
        require __DIR__ . '/pages/403.php';
        break;
}
