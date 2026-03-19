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
    <a href="?action=invite" class="btn-primary" id="invite-btn"> Send invite</a>
  </div>

  <!-- Filters -->
  <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
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

  <table class="a-table">
    <thead><tr><th>Member</th><th>Roll</th><th>Role</th><th>Status</th><th>Last seen</th><th>Actions</th></tr></thead>
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
    <tr>
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
    <?php if (!$members): ?><tr><td colspan="6" style="text-align:center;padding:24px;color:var(--txt-3)">No members found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<script>
async function memberAction(id, action) {
  if (!confirm_(`${action} this member?`)) return;
  try {
    await api('admin/members.php', { method:'POST', body:JSON.stringify({user_id:id, action}) });
    showToast('Done!','success');
    setTimeout(()=>location.reload(),600);
  } catch(e) { showToast(e.message,'error'); }
}
async function changeRole(id, role) {
  if (!confirm_(`Change role to ${role}?`)) return;
  try {
    await api('admin/roles.php', { method:'POST', body:JSON.stringify({user_id:id, role}) });
    showToast('Role updated','success');
    setTimeout(()=>location.reload(),600);
  } catch(e) { showToast(e.message,'error'); }
}
</script>

<?php require_once 'includes/layout_end.php'; ?>
