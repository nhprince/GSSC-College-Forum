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

$body  = getJsonBody();
$notif = isset($body['notif_enabled']) ? (int)(bool)$body['notif_enabled'] : null;
$sound = isset($body['sound_enabled']) ? (int)(bool)$body['sound_enabled'] : null;

if ($notif === null || $sound === null) jsonError('Missing fields','VALIDATION_ERROR');

try {
    $user = currentUser();
    getDB()->prepare("UPDATE users SET notif_enabled = ?, sound_enabled = ? WHERE id = ?")
           ->execute([$notif, $sound, $user['id']]);
    $_SESSION['notif_enabled'] = (bool)$notif;
    $_SESSION['sound_enabled'] = (bool)$sound;
    jsonSuccess();
} catch(\Throwable $e) {
    error_log($e->getMessage());
    jsonError('Update failed','DB_ERROR',500);
}
