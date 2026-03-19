<?php
declare(strict_types=1);
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
initSession();
requireLogin();

$targetId = (int)($_GET['id'] ?? 0);
if (!$targetId) { header('Location: /'); exit; }

$pdo  = getDB();
$stmt = $pdo->prepare("
    SELECT id, full_name, nickname, roll_no, gender, avatar, role, last_seen_at, created_at,
           (last_seen_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)) AS is_online
    FROM users WHERE id = ? AND is_active = 1 AND is_approved = 1 LIMIT 1
");
$stmt->execute([$targetId]);
$member = $stmt->fetch();
if (!$member) { header('Location: /'); exit; }

$isSelf = ((int)currentUser()['id'] === $targetId);
$init   = strtoupper(substr($member['nickname'] ?: $member['full_name'], 0, 1));
$joined = date('F Y', strtotime($member['created_at']));

// Stats
$s = $pdo->prepare("SELECT COUNT(*) FROM storage_files WHERE uploaded_by = ? AND is_approved = 1");
$s->execute([$targetId]);
$storageCount = (int)$s->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta name="csrf-token" content="<?= getCsrfToken() ?>">
  <meta name="theme-color" content="#C0000C">
  <title><?= escHtml($member['full_name']) ?>  GSSC</title>
  <link rel="stylesheet" href="/assets/css/main.css">
  <style>
    body { overflow: auto; background: var(--bg); }
    #app { display: block; height: auto; min-height: 100dvh; }
    .profile-wrap {
      max-width: 500px; margin: 0 auto;
      padding: 0 0 40px;
    }
    .profile-header {
      background: var(--red);
      padding: 16px 16px 0;
      border-radius: 0 0 var(--r-lg) var(--r-lg);
    }
    .profile-back {
      display: flex; align-items: center; gap: 8px;
      color: rgba(255,255,255,.8); font-size: 13px;
      font-weight: 600; margin-bottom: 20px;
      background: none; border: none; cursor: pointer;
      transition: color .15s;
    }
    .profile-back:hover { color: #fff; }
    .profile-avatar-wrap {
      display: flex; justify-content: center;
      margin-bottom: -36px;
    }
    .profile-avatar {
      width: 80px; height: 80px; border-radius: 50%;
      background: var(--red-dark);
      border: 4px solid var(--bg);
      display: flex; align-items: center; justify-content: center;
      font-family: var(--fh); font-size: 28px; font-weight: 700;
      color: #fff; overflow: hidden;
      box-shadow: 0 4px 16px rgba(0,0,0,0.18);
    }
    .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .profile-body {
      padding: 48px 16px 0;
      text-align: center;
    }
    .profile-name {
      font-family: var(--fh); font-size: 20px; font-weight: 700;
      color: var(--txt); margin-bottom: 4px;
    }
    .profile-meta-row {
      display: flex; align-items: center; justify-content: center;
      gap: 8px; flex-wrap: wrap; margin-bottom: 6px;
    }
    .profile-roll {
      font-size: 13px; color: var(--txt-2);
      font-family: 'Courier New', monospace;
      background: var(--bg); padding: 2px 10px; border-radius: var(--r-pill);
    }
    .role-badge {
      font-size: 10px; font-weight: 700; padding: 3px 10px;
      border-radius: var(--r-pill); text-transform: uppercase; letter-spacing: .05em;
    }
    .role-badge--admin     { background: var(--red-lt); color: var(--red); }
    .role-badge--moderator { background: #EEF4FF; color: var(--info); }
    .role-badge--student   { background: var(--bg); color: var(--txt-3); border: 1px solid var(--border); }
    .online-badge {
      display: inline-flex; align-items: center; gap: 5px;
      font-size: 12px; color: #16a34a; font-weight: 600;
    }
    .joined-txt { font-size: 12px; color: var(--txt-3); margin-top: 4px; }

    /* Stats */
    .stats-row {
      display: grid; grid-template-columns: 1fr 1fr;
      gap: 10px; margin: 24px 16px 0;
    }
    .stat-card {
      background: var(--surface); border-radius: var(--r-md);
      padding: 14px 16px; text-align: center;
      box-shadow: var(--sh-sm);
    }
    .stat-num { font-family: var(--fh); font-size: 24px; font-weight: 700; color: var(--red); }
    .stat-lbl { font-size: 11px; color: var(--txt-3); margin-top: 2px; }

    /* Edit button */
    .edit-btn {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      margin: 20px 16px 0;
      background: var(--red); color: #fff;
      border: none; border-radius: var(--r-pill);
      padding: 13px; font-family: var(--fh); font-size: 14px; font-weight: 600;
      cursor: pointer; box-shadow: var(--sh-red);
      width: calc(100% - 32px);
      transition: background .18s;
    }
    .edit-btn:hover { background: var(--red-dark); }

    /* Edit form */
    .edit-form {
      background: var(--surface); border-radius: var(--r-lg);
      margin: 16px; padding: 20px;
      box-shadow: var(--sh-sm); display: none;
    }
    .edit-form.open { display: block; }
    .ef-label { font-size: 11.5px; font-weight: 600; color: var(--txt-2); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 5px; display: block; }
    .ef-group { margin-bottom: 14px; }
    .ef-input {
      width: 100%; padding: 11px 14px;
      background: var(--bg); border: 1.5px solid var(--border);
      border-radius: var(--r-pill); font-size: 14px; color: var(--txt);
      font-family: var(--fb); transition: border-color .18s;
    }
    .ef-input:focus { outline: none; border-color: var(--red); }
    .ef-input option { background: var(--surface); }
    .ef-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .save-btn {
      width: 100%; padding: 12px; background: var(--red); color: #fff;
      border: none; border-radius: var(--r-pill);
      font-family: var(--fh); font-size: 14px; font-weight: 600;
      cursor: pointer; transition: background .18s;
    }
    .save-btn:hover { background: var(--red-dark); }
    .avatar-upload-row {
      display: flex; align-items: center; gap: 12px; margin-bottom: 16px;
    }
    .avatar-upload-btn {
      padding: 8px 16px; border-radius: var(--r-pill);
      border: 1.5px solid var(--border); background: var(--bg);
      font-size: 13px; font-weight: 500; cursor: pointer; color: var(--txt);
      transition: border-color .15s;
    }
    .avatar-upload-btn:hover { border-color: var(--red); color: var(--red); }
    .avatar-preview {
      width: 44px; height: 44px; border-radius: 50%;
      background: var(--red); overflow: hidden;
      display: flex; align-items: center; justify-content: center;
      font-family: var(--fh); font-weight: 700; color: #fff; font-size: 16px;
    }
    .avatar-preview img { width: 100%; height: 100%; object-fit: cover; }
  </style>
</head>
<body>
<div id="app">
<div class="profile-wrap">

  <!-- Header band -->
  <div class="profile-header">
    <button class="profile-back" onclick="history.back()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15,18 9,12 15,6"/></svg>
      Back
    </button>
    <div class="profile-avatar-wrap">
      <div class="profile-avatar" id="profile-avatar-display">
        <?php if ($member['avatar']): ?>
          <img src="/uploads/avatars/<?= escHtml($member['avatar']) ?>" alt="">
        <?php else: echo $init; endif; ?>
      </div>
    </div>
  </div>

  <!-- Body -->
  <div class="profile-body">
    <div class="profile-name"><?= escHtml($member['full_name']) ?></div>
    <div class="profile-meta-row">
      <span class="profile-roll"><?= escHtml($member['roll_no']) ?></span>
      <span class="role-badge role-badge--<?= escHtml($member['role']) ?>"><?= escHtml($member['role']) ?></span>
    </div>
    <?php if ($member['is_online']): ?>
    <div class="online-badge"><span class="online-dot"></span> Online now</div>
    <?php else: ?>
    <div class="joined-txt">Joined <?= $joined ?></div>
    <?php endif; ?>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-num"><?= $storageCount ?></div>
      <div class="stat-lbl">Files shared</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $member['gender'] === 'male' ? '' : ($member['gender'] === 'female' ? '' : '') ?></div>
      <div class="stat-lbl"><?= ucfirst(escHtml($member['gender'])) ?></div>
    </div>
  </div>

  <?php if ($isSelf): ?>
  <!-- Edit Profile Button -->
  <button class="edit-btn" id="edit-toggle-btn">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
    Edit Profile
  </button>

  <!-- Edit Form -->
  <div class="edit-form" id="edit-form">
    <div class="avatar-upload-row">
      <div class="avatar-preview" id="avatar-preview">
        <?php if ($member['avatar']): ?>
          <img id="avatar-preview-img" src="/uploads/avatars/<?= escHtml($member['avatar']) ?>" alt="">
        <?php else: echo $init; endif; ?>
      </div>
      <button class="avatar-upload-btn" onclick="document.getElementById('avatar-file').click()">Change photo</button>
      <input type="file" id="avatar-file" accept="image/jpeg,image/png,image/webp" style="display:none">
    </div>

    <div class="ef-row">
      <div class="ef-group">
        <label class="ef-label">Full name</label>
        <input class="ef-input" id="ef-fullname" type="text" value="<?= escHtml($member['full_name']) ?>">
      </div>
      <div class="ef-group">
        <label class="ef-label">Nickname</label>
        <input class="ef-input" id="ef-nickname" type="text" value="<?= escHtml($member['nickname'] ?? '') ?>">
      </div>
    </div>

    <div class="ef-group">
      <label class="ef-label">Gender</label>
      <select class="ef-input" id="ef-gender">
        <option value="male"   <?= $member['gender']==='male'   ? 'selected':'' ?>>Male</option>
        <option value="female" <?= $member['gender']==='female' ? 'selected':'' ?>>Female</option>
        <option value="other"  <?= $member['gender']==='other'  ? 'selected':'' ?>>Other</option>
      </select>
    </div>

    <button class="save-btn" id="save-profile-btn">Save changes</button>
  </div>
  <?php endif; ?>

</div><!-- /.profile-wrap -->
</div><!-- /#app -->

<div id="toast-container"></div>

<script src="/assets/js/app.js"></script>
<script>
const CURRENT_USER = { id: <?= (int)currentUser()['id'] ?>, role: <?= json_encode(currentUser()['role']) ?> };
const IS_SELF = <?= $isSelf ? 'true' : 'false' ?>;

if (IS_SELF) {
  document.getElementById('edit-toggle-btn').addEventListener('click', () => {
    document.getElementById('edit-form').classList.toggle('open');
  });

  // Avatar preview
  document.getElementById('avatar-file').addEventListener('change', e => {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = ev => {
      const prev = document.getElementById('avatar-preview');
      prev.innerHTML = `<img id="avatar-preview-img" src="${ev.target.result}" alt="">`;
    };
    reader.readAsDataURL(file);
  });

  document.getElementById('save-profile-btn').addEventListener('click', async () => {
    const btn = document.getElementById('save-profile-btn');
    btn.textContent = 'Saving'; btn.disabled = true;

    try {
      const fd = new FormData();
      fd.append('full_name', document.getElementById('ef-fullname').value.trim());
      fd.append('nickname',  document.getElementById('ef-nickname').value.trim());
      fd.append('gender',    document.getElementById('ef-gender').value);
      const avatarFile = document.getElementById('avatar-file').files[0];
      if (avatarFile) fd.append('avatar', avatarFile);

      const res  = await fetch('/api/members/update.php', {
        method: 'POST',
        headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content },
        body: fd,
        credentials: 'same-origin'
      });
      const data = await res.json();
      if (data.success) {
        showToast('Profile updated!', 'success');
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast(data.error || 'Update failed', 'error');
      }
    } catch(_) {
      showToast('Network error', 'error');
    } finally {
      btn.textContent = 'Save changes'; btn.disabled = false;
    }
  });
}

function showToast(msg, type='info') {
  const el = document.createElement('div');
  el.className = `toast toast--${type}`;
  el.textContent = msg;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(() => el.remove(), 4000);
}
</script>
</body>
</html>
