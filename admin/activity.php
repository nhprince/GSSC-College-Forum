<?php
declare(strict_types=1);
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
initSession();
requireLogin();
requireRole('moderator');

$pdo  = getDB();
$logs = $pdo->query("
    SELECT a.*, u.full_name, u.nickname
    FROM activity_log a
    LEFT JOIN users u ON u.id = a.user_id
    ORDER BY a.created_at DESC
    LIMIT 200
")->fetchAll();

$pageTitle  = 'Activity Log';
$activePage = 'activity';
require_once 'includes/layout.php';
?>

<div class="a-card">
  <div class="a-card-title">Activity log (last 200 entries)</div>
<div class="a-table-wrap">  <table class="a-table">
    <thead><tr><th>User</th><th>Action</th><th>Target</th><th>IP</th><th>Time</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $l):
      $colorMap = ['post.created'=>'badge-green','post.deleted'=>'badge-red','user.banned'=>'badge-red','chat.toggled'=>'badge-blue','storage.approved'=>'badge-green','storage.rejected'=>'badge-red','invite.sent'=>'badge-blue'];
      $badgeClass = $colorMap[$l['action']] ?? 'badge-grey';
    ?>
    <tr>
      <td><?= escHtml($l['nickname'] ?: $l['full_name'] ?: 'System') ?></td>
      <td><span class="badge <?= $badgeClass ?>"><?= escHtml($l['action']) ?></span></td>
      <td style="font-size:12px;color:var(--txt-3)"><?= $l['target_type'] ? escHtml($l['target_type'].'#'.$l['target_id']) : '' ?></td>
      <td style="font-size:11px;color:var(--txt-3);font-family:monospace"><?= escHtml($l['ip_address'] ?? '') ?></td>
      <td style="font-size:12px;color:var(--txt-3);white-space:nowrap"><?= timeAgo($l['created_at']) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$logs): ?><tr><td colspan="5" style="text-align:center;padding:24px;color:var(--txt-3)">No activity yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
</div>

<?php require_once 'includes/layout_end.php'; ?>