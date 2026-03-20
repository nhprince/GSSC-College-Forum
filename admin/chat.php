<?php
declare(strict_types=1);
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
initSession();
requireLogin();
requireRole('moderator');

$pdo         = getDB();
$chatEnabled = getSetting('chat_enabled','1') === '1';
$messages    = $pdo->query("
    SELECT m.*, u.full_name, u.nickname
    FROM messages m
    JOIN users u ON u.id = m.user_id
    WHERE m.is_deleted = 0
    ORDER BY m.created_at DESC
    LIMIT 100
")->fetchAll();

$pageTitle = 'Chat Moderation';
$activePage = 'chat';
require_once 'includes/layout.php';
?>

<!-- Status card -->
<div class="a-card">
  <div class="a-card-title">
    Chat status
    <?php if (hasRole('admin')): ?>
    <label class="a-toggle">
      <input type="checkbox" id="chat-toggle" <?= $chatEnabled?'checked':'' ?>>
      <span class="a-tslider"></span>
    </label>
    <?php endif; ?>
  </div>
  <p style="font-size:13px;color:var(--txt-2)">
    Chat is <strong style="color:<?= $chatEnabled?'#16a34a':'var(--red)' ?>"><?= $chatEnabled?'ENABLED':'DISABLED' ?></strong>
    &nbsp;&nbsp; <?= count($messages) ?> recent messages shown
  </p>
</div>

<!-- Messages -->
<div class="a-card">
  <div class="a-card-title">
    Recent messages
    <?php if (hasRole('admin')): ?>
    <button class="btn-sm btn-red" id="clear-all-btn"> Clear all</button>
    <?php endif; ?>
  </div>
<div class="a-table-wrap">  <table class="a-table">
    <thead><tr><th>User</th><th>Message</th><th>Type</th><th>Time</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($messages as $m): ?>
    <tr id="msg-row-<?= $m['id'] ?>">
      <td style="white-space:nowrap"><?= escHtml($m['nickname']?:$m['full_name']) ?></td>
      <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
        <?= $m['type']==='text' ? escHtml($m['body']) : ' '.$m['file_name'] ?>
      </td>
      <td><span class="badge badge-grey"><?= $m['type'] ?></span></td>
      <td style="font-size:11px;color:var(--txt-3);white-space:nowrap"><?= timeAgo($m['created_at']) ?></td>
      <td><button class="btn-sm btn-red" onclick="deleteMsg(<?= $m['id'] ?>)">Delete</button></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$messages): ?><tr><td colspan="5" style="text-align:center;padding:24px;color:var(--txt-3)">No messages yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
</div>

<script>
<?php if (hasRole('admin')): ?>
document.getElementById('chat-toggle')?.addEventListener('change', async function() {
  try {
    await api('chat/toggle.php',{method:'POST',body:JSON.stringify({enabled:this.checked})});
    showToast('Chat '+(this.checked?'enabled':'disabled'),'success');
    location.reload();
  } catch(e){showToast(e.message,'error');this.checked=!this.checked;}
});

document.getElementById('clear-all-btn')?.addEventListener('click', async () => {
  const code = prompt('Type CONFIRM to clear all messages:');
  if (code !== 'CONFIRM') return;
  try {
    await api('admin/clear-chat.php',{method:'POST',body:JSON.stringify({confirm:true})});
    showToast('All messages cleared','success');
    setTimeout(()=>location.reload(),600);
  } catch(e){showToast(e.message,'error');}
});
<?php endif; ?>

async function deleteMsg(id) {
  try {
    await api('chat/delete.php',{method:'POST',body:JSON.stringify({message_id:id})});
    document.getElementById('msg-row-'+id)?.remove();
    showToast('Message deleted','success');
  } catch(e){showToast(e.message,'error');}
}
</script>

<?php require_once 'includes/layout_end.php'; ?>