<?php
declare(strict_types=1);
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
initSession();
requireLogin();
requireRole('admin');

$pdo    = getDB();
$search = trim($_GET['search'] ?? '');
$role   = $_GET['role']   ?? '';
$status = $_GET['status'] ?? '';

$where  = ['1=1'];
$params = [];
if ($search) { $where[] = '(full_name LIKE ? OR nickname LIKE ? OR roll_no LIKE ? OR email LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]); }
if ($role && in_array($role,['student','moderator','admin'],true)) { $where[] = 'role = ?'; $params[] = $role; }
if ($status === 'active')   { $where[] = 'is_active=1 AND is_approved=1'; }
if ($status === 'pending')  { $where[] = 'is_approved=0'; }
if ($status === 'inactive') { $where[] = 'is_active=0'; }

$members = $pdo->prepare("SELECT * FROM users WHERE " . implode(' AND ',$where) . " ORDER BY created_at DESC");
$members->execute($params);
$members = $members->fetchAll();

$pageTitle  = 'Members';
$activePage = 'members';
require_once 'includes/layout.php';
?>

<div class="a-card">
  <div class="a-card-title">
    All members (<?= count($members) ?>)
    <button class="btn-primary" id="invite-btn" onclick="openInviteModal()"> Send invite</button>
  </div>

  <!-- Filters -->
  <form method="GET" class="filter-row">
    <div class="search-wrap">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" name="search" value="<?= escHtml($search) ?>" placeholder="Search name, roll, email">
    </div>
    <select class="a-input a-select" name="role" style="width:130px">
      <option value="">All roles</option>
      <option value="student"   <?= $role==='student'   ?'selected':'' ?>>Student</option>
      <option value="moderator" <?= $role==='moderator' ?'selected':'' ?>>Moderator</option>
      <option value="admin"     <?= $role==='admin'     ?'selected':'' ?>>Admin</option>
    </select>
    <select class="a-input a-select" name="status" style="width:130px">
      <option value="">All status</option>
      <option value="active"   <?= $status==='active'   ?'selected':'' ?>>Active</option>
      <option value="pending"  <?= $status==='pending'  ?'selected':'' ?>>Pending</option>
      <option value="inactive" <?= $status==='inactive' ?'selected':'' ?>>Inactive</option>
    </select>
    <button type="submit" class="btn-sm btn-ghost">Filter</button>
    <a href="/admin/members.php" class="btn-sm btn-ghost">Reset</a>
  </form>
<div class="a-table-wrap">
  <table class="a-table">
    <thead><tr><th>Member</th><th>Roll</th><th>Role</th><th>Status</th><th>ID Card</th><th>Last seen</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($members as $m):
      $statusBadge = !$m['is_active'] ? '<span class="badge badge-red">Banned</span>'
        : (!$m['is_approved'] ? '<span class="badge badge-orange">Pending</span>'
        : '<span class="badge badge-green">Active</span>');
      $roleBadge = $m['role'] === 'admin' ? '<span class="badge badge-red">Admin</span>'
        : ($m['role'] === 'moderator' ? '<span class="badge badge-blue">Mod</span>'
        : '<span class="badge badge-grey">Student</span>');
      $isSelf = (int)$m['id'] === (int)currentUser()['id'];
    ?>
    <tr<?= (!$m['is_approved'] && $m['is_active']) ? ' style="background:#FFFBEB"' : '' ?>>
      <td>
        <div style="display:flex;align-items:center;gap:8px">
          <div style="width:30px;height:30px;border-radius:50%;background:var(--red);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;overflow:hidden;flex-shrink:0">
            <?php if ($m['avatar']): ?><img src="/uploads/avatars/<?= escHtml($m['avatar']) ?>" style="width:100%;height:100%;object-fit:cover" alt="">
            <?php else: echo strtoupper(substr($m['nickname']?:$m['full_name'],0,1)); endif; ?>
          </div>
          <div>
            <div style="font-weight:600"><?= escHtml($m['full_name']) ?></div>
            <div style="font-size:11px;color:var(--txt-3)"><?= escHtml($m['email']) ?></div>
          </div>
        </div>
      </td>
      <td><span style="font-family:monospace;font-size:12px"><?= escHtml($m['roll_no']) ?></span></td>
      <td><?= $roleBadge ?></td>
      <td><?= $statusBadge ?></td>
      <td>
        <?php if (!empty($m['id_card'])): ?>
          <img src="/uploads/<?= escHtml($m['id_card']) ?>"
               alt="ID card"
               onclick="viewIdCard('/uploads/<?= escHtml($m['id_card']) ?>')"
               style="width:48px;height:36px;object-fit:cover;border-radius:6px;cursor:pointer;border:1.5px solid var(--border);transition:transform .15s"
               onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform=''">
        <?php else: ?>
          <span style="font-size:11px;color:var(--txt-3)">None</span>
        <?php endif; ?>
      </td>
      <td style="font-size:12px;color:var(--txt-3)"><?= $m['last_seen_at'] ? timeAgo($m['last_seen_at']) : 'Never' ?></td>
      <td>
        <?php if (!$isSelf): ?>
        <div style="display:flex;gap:4px;flex-wrap:wrap">
          <?php if (!$m['is_approved']): ?>
          <button class="btn-sm btn-green" onclick="memberAction(<?= $m['id'] ?>,'approve')">Approve</button>
          <?php endif; ?>
          <?php if ($m['is_active'] && $m['role'] === 'student'): ?>
          <button class="btn-sm btn-ghost" onclick="changeRole(<?= $m['id'] ?>,'moderator')"> Mod</button>
          <?php elseif ($m['role'] === 'moderator'): ?>
          <button class="btn-sm btn-ghost" onclick="changeRole(<?= $m['id'] ?>,'student')"> Student</button>
          <?php endif; ?>
          <?php if ($m['is_active']): ?>
          <button class="btn-sm btn-red" onclick="memberAction(<?= $m['id'] ?>,'deactivate')">Ban</button>
          <?php else: ?>
          <button class="btn-sm btn-green" onclick="memberAction(<?= $m['id'] ?>,'activate')">Unban</button>
          <?php endif; ?>
        </div>
        <?php else: echo '<span style="font-size:12px;color:var(--txt-3)">You</span>'; endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$members): ?><tr><td colspan="7" style="text-align:center;padding:24px;color:var(--txt-3)">No members found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
</div>

<script>
function viewIdCard(src) {
  document.getElementById('id-card-img').src = src;
  document.getElementById('id-card-modal').style.display = 'flex';
}
function closeIdCard() {
  document.getElementById('id-card-modal').style.display = 'none';
  document.getElementById('id-card-img').src = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeIdCard(); });

async function memberAction(id, action) {
  const labels = { activate:'Unban', deactivate:'Ban', approve:'Approve', delete:'Delete' };
  const types  = { deactivate:'danger', delete:'danger', activate:'warn', approve:'warn' };
  const msgs   = {
    activate:   'This member will regain access to the platform.',
    deactivate: 'This member will be banned and lose access.',
    approve:    'This member will be approved and can log in.',
    delete:     'This will permanently delete the member account.'
  };
  const ok = await dialog.confirm({
    type: types[action] || 'warn',
    title: (labels[action] || action) + ' member?',
    message: msgs[action] || `Perform action: ${action}?`,
    confirmText: labels[action] || 'Confirm',
    cancelText: 'Cancel'
  });
  if (!ok) return;
  try {
    await api('admin/members.php', { method:'POST', body:JSON.stringify({user_id:id, action}) });
    showToast('Done!','success');
    setTimeout(()=>location.reload(),600);
  } catch(e) { showToast(e.message,'error'); }
}
async function changeRole(id, role) {
  const ok = await dialog.confirm({
    type: 'warn',
    title: 'Change role?',
    message: `Set this member's role to "${role}". This affects their permissions.`,
    confirmText: 'Change role', cancelText: 'Cancel'
  });
  if (!ok) return;
  try {
    await api('admin/roles.php', { method:'POST', body:JSON.stringify({user_id:id, role}) });
    showToast('Role updated','success');
    setTimeout(()=>location.reload(),600);
  } catch(e) { showToast(e.message,'error'); }
}
</script>

<!-- ID Card Viewer Modal -->
<div id="id-card-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:300;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)closeIdCard()">
  <div style="position:relative;max-width:90vw;max-height:90vh">
    <img id="id-card-img" src="" alt="ID Card" style="max-width:100%;max-height:85vh;border-radius:12px;display:block;box-shadow:0 8px 40px rgba(0,0,0,.5)">
    <button onclick="closeIdCard()" style="position:absolute;top:-14px;right:-14px;width:32px;height:32px;border-radius:50%;background:#fff;color:#333;font-size:18px;line-height:1;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.3);cursor:pointer;border:none">&times;</button>
  </div>
</div>

<!-- Invite Modal -->
<div id="invite-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:var(--surface);border-radius:var(--r-lg);padding:28px;width:100%;max-width:440px;box-shadow:0 8px 32px rgba(0,0,0,.15)">
    <h3 style="font-family:var(--fh);font-size:17px;font-weight:600;margin-bottom:6px">Send invite</h3>
    <p style="font-size:13px;color:var(--txt-3);margin-bottom:18px">Enter an email to send a targeted invite, or leave blank to generate an open invite link anyone can use.</p>

    <div class="a-form-group">
      <label class="a-label">Email address (optional)</label>
      <input class="a-input" type="email" id="invite-email" placeholder="student@email.com">
    </div>

    <div id="invite-error" style="display:none;background:#FFF0F0;color:var(--red);font-size:13px;font-weight:500;padding:10px 14px;border-radius:10px;margin-bottom:14px"></div>

    <!-- Result box (shown after success) -->
    <div id="invite-result" style="display:none;background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:var(--r-md);padding:14px;margin-bottom:14px">
      <div style="font-size:12px;font-weight:600;color:#15803d;margin-bottom:6px">Invite link ready — copy and share:</div>
      <div style="display:flex;gap:6px;align-items:center">
        <input id="invite-link-input" type="text" readonly style="flex:1;padding:8px 12px;background:var(--bg);border:1.5px solid #86EFAC;border-radius:var(--r-pill);font-size:12px;color:var(--txt);font-family:monospace;outline:none">
        <button onclick="copyInviteLink()" class="btn-sm btn-green">Copy</button>
      </div>
      <div style="font-size:11px;color:var(--txt-3);margin-top:6px">Expires in 48 hours</div>
    </div>

    <div style="display:flex;gap:8px;justify-content:flex-end">
      <button class="btn-sm btn-ghost" onclick="closeInviteModal()">Cancel</button>
      <button class="btn-sm btn-red" id="invite-submit-btn" onclick="sendInvite()">Generate link</button>
    </div>
  </div>
</div>

<script>
function openInviteModal() {
  document.getElementById('invite-email').value = '';
  document.getElementById('invite-error').style.display = 'none';
  document.getElementById('invite-result').style.display = 'none';
  document.getElementById('invite-submit-btn').style.display = '';
  document.getElementById('invite-modal').style.display = 'flex';
  setTimeout(() => document.getElementById('invite-email').focus(), 50);
}
function closeInviteModal() {
  document.getElementById('invite-modal').style.display = 'none';
}
document.getElementById('invite-modal').addEventListener('click', e => {
  if (e.target === document.getElementById('invite-modal')) closeInviteModal();
});
document.getElementById('invite-email').addEventListener('keydown', e => {
  if (e.key === 'Enter') sendInvite();
});

async function sendInvite() {
  const email = document.getElementById('invite-email').value.trim();
  const errEl = document.getElementById('invite-error');
  const btn   = document.getElementById('invite-submit-btn');
  errEl.style.display = 'none';
  btn.disabled = true; btn.textContent = 'Generating...';
  try {
    const data = await api('admin/invites.php', {
      method: 'POST',
      body: JSON.stringify({ email: email || '' })
    });
    document.getElementById('invite-link-input').value = data.invite_link;
    document.getElementById('invite-result').style.display = 'block';
    btn.style.display = 'none';
    showToast(data.message || 'Invite created!', 'success');
  } catch(e) {
    errEl.textContent = e.message || 'Failed to create invite.';
    errEl.style.display = 'block';
  } finally {
    btn.disabled = false; btn.textContent = 'Generate link';
  }
}

function copyInviteLink() {
  const input = document.getElementById('invite-link-input');
  input.select();
  navigator.clipboard?.writeText(input.value).then(() => showToast('Copied!', 'success')).catch(() => {
    document.execCommand('copy');
    showToast('Copied!', 'success');
  });
}
</script>

<?php require_once 'includes/layout_end.php'; ?>