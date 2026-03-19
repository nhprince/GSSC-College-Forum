<?php
declare(strict_types=1);
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
initSession();
requireLogin();
requireRole('moderator');

$pdo   = getDB();
$posts = $pdo->query("
    SELECT p.id, p.post_type, p.title, p.priority, p.is_pinned, p.created_at,
           (SELECT COUNT(*) FROM post_reads WHERE post_id=p.id) AS read_count,
           (SELECT COUNT(*) FROM users WHERE is_active=1 AND is_approved=1) AS total_members
    FROM posts p
    WHERE p.is_published = 1
    ORDER BY p.is_pinned DESC, p.created_at DESC
    LIMIT 100
")->fetchAll();

$pageTitle  = 'Notice Board';
$activePage = 'noticeboard';
require_once 'includes/layout.php';
?>

<div class="a-card">
  <div class="a-card-title">
    All posts (<?= count($posts) ?>)
    <button class="btn-primary" id="create-post-btn">+ New post</button>
  </div>
  <table class="a-table">
    <thead><tr><th>Type</th><th>Title</th><th>Priority</th><th>Read</th><th>Date</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($posts as $p):
      $typeBadge = ['announcement'=>'<span class="badge badge-grey"> Notice</span>','event'=>'<span class="badge badge-green"> Event</span>','poll'=>'<span class="badge badge-blue"> Poll</span>'][$p['post_type']] ?? '';
      $priClass  = ['urgent'=>'badge-red','info'=>'badge-blue','general'=>'badge-grey'][$p['priority']] ?? 'badge-grey';
    ?>
    <tr>
      <td><?= $typeBadge ?> <?= $p['is_pinned'] ? '' : '' ?></td>
      <td style="font-weight:600;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= escHtml($p['title']) ?></td>
      <td><span class="badge <?= $priClass ?>"><?= $p['priority'] ?></span></td>
      <td style="font-size:12px;color:var(--txt-3)"><?= $p['read_count'] ?>/<?= $p['total_members'] ?></td>
      <td style="font-size:12px;color:var(--txt-3);white-space:nowrap"><?= timeAgo($p['created_at']) ?></td>
      <td>
        <div style="display:flex;gap:4px">
          <button class="btn-sm btn-ghost" onclick="togglePin(<?= $p['id'] ?>, <?= $p['is_pinned'] ? 'false' : 'true' ?>)">
            <?= $p['is_pinned'] ? 'Unpin' : 'Pin' ?>
          </button>
          <button class="btn-sm btn-red" onclick="deletePost(<?= $p['id'] ?>)">Delete</button>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$posts): ?><tr><td colspan="6" style="text-align:center;padding:24px;color:var(--txt-3)">No posts yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Create Post Modal -->
<div id="create-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:200;align-items:flex-start;justify-content:center;overflow-y:auto;padding:20px">
  <div style="background:var(--surface);border-radius:var(--r-lg);padding:24px;width:100%;max-width:500px;margin:0 auto">
    <h3 style="font-family:var(--fh);font-size:17px;margin-bottom:18px">Create new post</h3>

    <!-- Type tabs -->
    <div style="display:flex;gap:6px;margin-bottom:16px">
      <button class="btn-sm btn-red post-type-btn active" data-type="announcement"> Announcement</button>
      <button class="btn-sm btn-ghost post-type-btn" data-type="event"> Event</button>
      <button class="btn-sm btn-ghost post-type-btn" data-type="poll"> Poll</button>
    </div>
    <input type="hidden" id="cp-type" value="announcement">

    <div class="a-form-group">
      <label class="a-label">Title *</label>
      <input class="a-input" id="cp-title" placeholder="Post title">
    </div>
    <div class="a-form-group">
      <label class="a-label">Body (optional)</label>
      <textarea class="a-textarea" id="cp-body" placeholder="Details"></textarea>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">
      <div>
        <label class="a-label">Priority</label>
        <select class="a-input a-select" id="cp-priority">
          <option value="general">General</option>
          <option value="info">Info</option>
          <option value="urgent">Urgent</option>
        </select>
      </div>
      <div style="display:flex;align-items:flex-end;gap:8px;padding-bottom:2px">
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
          <input type="checkbox" id="cp-pin"> Pin this post
        </label>
      </div>
    </div>

    <!-- Announcement image -->
    <div id="cp-image-wrap" class="a-form-group">
      <label class="a-label">Image (optional)</label>
      <input type="file" id="cp-image" accept="image/jpeg,image/png,image/webp" style="font-size:13px">
    </div>

    <!-- Event fields -->
    <div id="cp-event-wrap" style="display:none">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">
        <div>
          <label class="a-label">Event date *</label>
          <input class="a-input" type="date" id="cp-event-date">
        </div>
        <div>
          <label class="a-label">Event time</label>
          <input class="a-input" type="time" id="cp-event-time">
        </div>
      </div>
      <div class="a-form-group">
        <label class="a-label">Event type</label>
        <select class="a-input a-select" id="cp-event-type">
          <option value="exam">Exam</option>
          <option value="submission">Submission</option>
          <option value="holiday">Holiday</option>
          <option value="class">Class</option>
          <option value="other">Other</option>
        </select>
      </div>
    </div>

    <!-- Poll fields -->
    <div id="cp-poll-wrap" style="display:none">
      <div class="a-form-group">
        <label class="a-label">Options (min 2)</label>
        <div id="poll-options-container">
          <input class="a-input poll-option-input" placeholder="Option 1" style="margin-bottom:6px">
          <input class="a-input poll-option-input" placeholder="Option 2" style="margin-bottom:6px">
        </div>
        <button type="button" class="btn-sm btn-ghost" id="add-option-btn" style="margin-top:4px">+ Add option</button>
      </div>
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
          <input type="checkbox" id="cp-poll-anon"> Anonymous
        </label>
        <div>
          <label class="a-label" style="display:inline">Ends at</label>
          <input class="a-input" type="datetime-local" id="cp-poll-ends" style="width:auto;display:inline-block;margin-left:8px">
        </div>
      </div>
    </div>

    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:18px">
      <button class="btn-sm btn-ghost" onclick="document.getElementById('create-modal').style.display='none'">Cancel</button>
      <button class="btn-sm btn-red" id="cp-submit-btn">Post</button>
    </div>
  </div>
</div>

<script>
// Type tabs
document.querySelectorAll('.post-type-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.post-type-btn').forEach(b => { b.classList.remove('btn-red','active'); b.classList.add('btn-ghost'); });
    btn.classList.add('btn-red','active'); btn.classList.remove('btn-ghost');
    document.getElementById('cp-type').value = btn.dataset.type;
    document.getElementById('cp-image-wrap').style.display  = btn.dataset.type==='announcement' ? '' : 'none';
    document.getElementById('cp-event-wrap').style.display  = btn.dataset.type==='event'        ? '' : 'none';
    document.getElementById('cp-poll-wrap').style.display   = btn.dataset.type==='poll'         ? '' : 'none';
  });
});

document.getElementById('create-post-btn').addEventListener('click', () => {
  document.getElementById('create-modal').style.display = 'flex';
});

document.getElementById('add-option-btn').addEventListener('click', () => {
  const c = document.getElementById('poll-options-container');
  const n = c.querySelectorAll('input').length + 1;
  if (n > 6) return;
  const i = document.createElement('input');
  i.className='a-input poll-option-input'; i.placeholder=`Option ${n}`; i.style.marginBottom='6px';
  c.appendChild(i);
});

document.getElementById('cp-submit-btn').addEventListener('click', async () => {
  const type  = document.getElementById('cp-type').value;
  const title = document.getElementById('cp-title').value.trim();
  if (!title) { showToast('Title required','warn'); return; }

  const fd = new FormData();
  fd.append('post_type', type);
  fd.append('title', title);
  fd.append('body',  document.getElementById('cp-body').value.trim());
  fd.append('priority', document.getElementById('cp-priority').value);
  fd.append('is_pinned', document.getElementById('cp-pin').checked ? '1' : '0');

  if (type==='announcement') {
    const img = document.getElementById('cp-image').files[0];
    if (img) fd.append('image', img);
  }
  if (type==='event') {
    fd.append('event_date', document.getElementById('cp-event-date').value);
    fd.append('event_time', document.getElementById('cp-event-time').value);
    fd.append('event_type', document.getElementById('cp-event-type').value);
  }
  if (type==='poll') {
    const opts = [...document.querySelectorAll('.poll-option-input')].map(i=>i.value.trim()).filter(Boolean);
    if (opts.length < 2) { showToast('Need at least 2 options','warn'); return; }
    fd.append('poll_options', JSON.stringify(opts));
    fd.append('poll_anon', document.getElementById('cp-poll-anon').checked ? '1' : '0');
    fd.append('poll_ends_at', document.getElementById('cp-poll-ends').value);
  }

  try {
    const res  = await fetch('/api/posts/index.php', { method:'POST', headers:{'X-CSRF-Token':CSRF}, body:fd, credentials:'same-origin' });
    const data = await res.json();
    if (data.success) { showToast('Post published!','success'); setTimeout(()=>location.reload(),700); }
    else showToast(data.error,'error');
  } catch(e) { showToast('Failed to post','error'); }
});

async function togglePin(id, pin) {
  try {
    await api('posts/pin.php',{method:'POST',body:JSON.stringify({post_id:id,pinned:pin})});
    showToast(pin?'Post pinned':'Post unpinned','success');
    setTimeout(()=>location.reload(),600);
  } catch(e){showToast(e.message,'error');}
}

async function deletePost(id) {
  if (!confirm_('Delete this post?')) return;
  try {
    await api('posts/delete.php',{method:'POST',body:JSON.stringify({post_id:id})});
    showToast('Post deleted','success');
    setTimeout(()=>location.reload(),600);
  } catch(e){showToast(e.message,'error');}
}
</script>

<?php require_once 'includes/layout_end.php'; ?>
