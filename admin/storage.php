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
$tab  = $_GET['tab'] ?? 'pending';

$pending = $pdo->query("
    SELECT sf.*, u.full_name, u.nickname
    FROM storage_files sf JOIN users u ON u.id = sf.uploaded_by
    WHERE sf.is_approved = 0
    ORDER BY sf.created_at DESC
")->fetchAll();

$allFiles = $pdo->query("
    SELECT sf.*, u.full_name, u.nickname
    FROM storage_files sf JOIN users u ON u.id = sf.uploaded_by
    ORDER BY sf.created_at DESC LIMIT 100
")->fetchAll();

$pageTitle = 'Storage';
$activePage = 'storage';
require_once 'includes/layout.php';
?>

<!-- Tabs -->
<div style="display:flex;gap:6px;margin-bottom:16px">
  <button class="btn-sm <?= $tab==='pending'?'btn-red':'btn-ghost' ?>" onclick="switchTab('pending')">
    Pending <?= count($pending) ? '('.count($pending).')' : '' ?>
  </button>
  <button class="btn-sm <?= $tab==='all'?'btn-red':'btn-ghost' ?>" onclick="switchTab('all')">All files</button>
</div>

<!-- Pending tab -->
<div id="tab-pending" <?= $tab!=='pending'?'style="display:none"':'' ?>>
  <div class="a-card">
    <div class="a-card-title">Pending approval (<?= count($pending) ?>)</div>
    <?php if ($pending): ?>
    <table class="a-table">
      <thead><tr><th>File</th><th>Uploaded by</th><th>Size</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($pending as $f): ?>
      <tr>
        <td>
          <div style="font-weight:600"><?= escHtml($f['title']) ?></div>
          <div style="font-size:11px;color:var(--txt-3)"><?= strtoupper(escHtml($f['file_type'])) ?>  <?= escHtml($f['category']) ?></div>
        </td>
        <td><?= escHtml($f['nickname'] ?: $f['full_name']) ?></td>
        <td style="font-size:12px"><?= formatBytes((int)$f['file_size']) ?></td>
        <td style="font-size:12px;color:var(--txt-3)"><?= timeAgo($f['created_at']) ?></td>
        <td>
          <div style="display:flex;gap:6px">
            <a href="/api/storage/download.php?id=<?= $f['id'] ?>" class="btn-sm btn-ghost" target="_blank">Preview</a>
            <button class="btn-sm btn-green" onclick="fileAction(<?= $f['id'] ?>,'approve')"> Approve</button>
            <button class="btn-sm btn-red" onclick="fileAction(<?= $f['id'] ?>,'reject')"> Reject</button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <p style="color:var(--txt-3);text-align:center;padding:24px">No files pending approval. </p>
    <?php endif; ?>
  </div>
</div>

<!-- All files tab -->
<div id="tab-all" <?= $tab!=='all'?'style="display:none"':'' ?>>
  <div class="a-card">
    <div class="a-card-title">All files</div>
    <table class="a-table">
      <thead><tr><th>File</th><th>Category</th><th>Uploader</th><th>Downloads</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($allFiles as $f): ?>
      <tr>
        <td>
          <div style="font-weight:600"><?= escHtml($f['title']) ?></div>
          <div style="font-size:11px;color:var(--txt-3)"><?= strtoupper(escHtml($f['file_type'])) ?>  <?= formatBytes((int)$f['file_size']) ?></div>
        </td>
        <td><span class="badge badge-grey"><?= escHtml($f['category']) ?></span></td>
        <td><?= escHtml($f['nickname'] ?: $f['full_name']) ?></td>
        <td style="text-align:center"><?= $f['download_count'] ?></td>
        <td><?= $f['is_approved'] ? '<span class="badge badge-green">Approved</span>' : '<span class="badge badge-orange">Pending</span>' ?></td>
        <td>
          <div style="display:flex;gap:4px">
            <a href="/api/storage/download.php?id=<?= $f['id'] ?>" class="btn-sm btn-ghost" target="_blank"></a>
            <button class="btn-sm btn-red" onclick="fileAction(<?= $f['id'] ?>,'delete')">Delete</button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$allFiles): ?><tr><td colspan="6" style="text-align:center;padding:24px;color:var(--txt-3)">No files yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function switchTab(t) {
  document.getElementById('tab-pending').style.display = t==='pending' ? '' : 'none';
  document.getElementById('tab-all').style.display     = t==='all'     ? '' : 'none';
}
async function fileAction(id, action) {
  if (action === 'delete' && !confirm_('Delete this file permanently?')) return;
  if (action === 'reject' && !confirm_('Reject and delete this file?')) return;
  try {
    await api('storage/approve.php', { method:'POST', body:JSON.stringify({file_id:id, action}) });
    showToast(action === 'approve' ? 'File approved!' : 'File removed', 'success');
    setTimeout(()=>location.reload(), 600);
  } catch(e) { showToast(e.message,'error'); }
}
</script>

<?php require_once 'includes/layout_end.php'; ?>
