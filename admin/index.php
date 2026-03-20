<?php
declare(strict_types=1);
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
initSession();
requireLogin();
requireRole('moderator');

$pdo = getDB();
$totalMembers  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active=1 AND is_approved=1")->fetchColumn();
$totalPosts    = (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE is_published=1")->fetchColumn();
$todayMessages = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE DATE(created_at)=CURDATE() AND is_deleted=0")->fetchColumn();
$pendingFiles  = (int)$pdo->query("SELECT COUNT(*) FROM storage_files WHERE is_approved=0")->fetchColumn();
$chatEnabled   = getSetting('chat_enabled','1') === '1';

$recentActivity = $pdo->query("
    SELECT a.*, u.full_name, u.nickname
    FROM activity_log a
    LEFT JOIN users u ON u.id = a.user_id
    ORDER BY a.created_at DESC LIMIT 15
")->fetchAll();

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require_once 'includes/layout.php';
?>

<div class="stats-grid">
  <div class="stat-box"><div class="stat-box-num"><?= $totalMembers ?></div><div class="stat-box-lbl">Total members</div></div>
  <div class="stat-box"><div class="stat-box-num"><?= $totalPosts ?></div><div class="stat-box-lbl">Total posts</div></div>
  <div class="stat-box"><div class="stat-box-num"><?= $todayMessages ?></div><div class="stat-box-lbl">Messages today</div></div>
  <div class="stat-box" style="<?= $pendingFiles ? 'border:2px solid var(--red)' : '' ?>">
    <div class="stat-box-num" style="color:<?= $pendingFiles ? 'var(--red)' : 'var(--txt-3)' ?>"><?= $pendingFiles ?></div>
    <div class="stat-box-lbl">Pending files</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">

  <!-- Chat status card -->
  <div class="a-card">
    <div class="a-card-title">Chat status
      <?php if (hasRole('admin')): ?>
      <label class="a-toggle" title="Toggle chat">
        <input type="checkbox" id="chat-toggle" <?= $chatEnabled ? 'checked' : '' ?>>
        <span class="a-tslider"></span>
      </label>
      <?php endif; ?>
    </div>
    <p style="font-size:13px;color:var(--txt-2)">
      Chat is currently <strong style="color:<?= $chatEnabled ? '#16a34a' : 'var(--red)' ?>"><?= $chatEnabled ? 'ENABLED' : 'DISABLED' ?></strong>
    </p>
  </div>

  <!-- Quick actions -->
  <div class="a-card">
    <div class="a-card-title">Quick actions</div>
    <div style="display:flex;flex-wrap:wrap;gap:8px">
      <a href="/admin/noticeboard.php?action=create" class="btn-sm btn-red">+ New post</a>
      <?php if (hasRole('admin')): ?>
      <button class="btn-sm btn-ghost" id="invite-btn"> Send invite</button>
      <?php endif; ?>
      <a href="/admin/storage.php?tab=pending" class="btn-sm btn-ghost" <?= $pendingFiles ? 'style="border-color:var(--red);color:var(--red)"' : '' ?>>
         Pending <?= $pendingFiles ? "($pendingFiles)" : '' ?>
      </a>
    </div>
  </div>
</div>

<!-- Activity log -->
<div class="a-card">
  <div class="a-card-title">Recent activity</div>
<div class="a-table-wrap">  <table class="a-table">
    <thead><tr><th>User</th><th>Action</th><th>Time</th></tr></thead>
    <tbody>
    <?php foreach ($recentActivity as $log): ?>
    <tr>
      <td><?= escHtml($log['nickname'] ?: $log['full_name'] ?: 'System') ?></td>
      <td><span class="badge badge-grey"><?= escHtml($log['action']) ?></span></td>
      <td style="color:var(--txt-3);font-size:12px"><?= timeAgo($log['created_at']) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$recentActivity): ?><tr><td colspan="3" style="color:var(--txt-3);text-align:center;padding:20px">No activity yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
</div>

<!-- Send invite modal -->
<div id="invite-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center">
  <div style="background:var(--surface);border-radius:var(--r-lg);padding:24px;width:100%;max-width:380px;margin:20px">
    <h3 style="font-family:var(--fh);font-size:16px;margin-bottom:16px">Send invite</h3>
    <div class="a-form-group">
      <label class="a-label">Email address</label>
      <input class="a-input" type="email" id="invite-email" placeholder="student@example.com">
    </div>
    <div style="display:flex;gap:8px;justify-content:flex-end">
      <button class="btn-sm btn-ghost" onclick="document.getElementById('invite-modal').style.display='none'">Cancel</button>
      <button class="btn-sm btn-red" id="invite-send-btn">Send invite</button>
    </div>
  </div>
</div>

<script>
<?php if (hasRole('admin')): ?>
document.getElementById('chat-toggle')?.addEventListener('change', async function() {
  try {
    await api('chat/toggle.php', { method:'POST', body:JSON.stringify({enabled: this.checked}) });
    showToast('Chat ' + (this.checked ? 'enabled' : 'disabled'), 'success');
  } catch(e) { showToast(e.message,'error'); this.checked = !this.checked; }
});

document.getElementById('invite-btn')?.addEventListener('click', () => {
  document.getElementById('invite-modal').style.display = 'flex';
  document.getElementById('invite-email').focus();
});

document.getElementById('invite-send-btn')?.addEventListener('click', async () => {
  const email = document.getElementById('invite-email').value.trim();
  if (!email) { showToast('Enter an email','warn'); return; }
  try {
    const d = await api('admin/invites.php', { method:'POST', body:JSON.stringify({email}) });
    showToast('Invite sent to ' + email, 'success');
    document.getElementById('invite-modal').style.display = 'none';
    document.getElementById('invite-email').value = '';
  } catch(e) { showToast(e.message,'error'); }
});
<?php endif; ?>
</script>

<?php require_once 'includes/layout_end.php'; ?>