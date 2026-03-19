<?php
declare(strict_types=1);
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
initSession();
requireLogin();
requireRole('admin');

$pageTitle  = 'Settings';
$activePage = 'settings';
require_once 'includes/layout.php';
?>

<div class="a-card">
  <div class="a-card-title">General</div>
  <div class="a-form-group">
    <label class="a-label">Site name</label>
    <input class="a-input" id="site_name" value="<?= escHtml(getSetting('site_name','GSSC-science official')) ?>">
  </div>
  <div class="a-form-group">
    <label class="a-label">College name</label>
    <input class="a-input" id="college_name" value="<?= escHtml(getSetting('college_name','Govt. Shaheed Suhrawardy College')) ?>">
  </div>
</div>

<div class="a-card">
  <div class="a-card-title">Content shown in Settings modal</div>
  <div class="a-form-group">
    <label class="a-label">About us</label>
    <textarea class="a-textarea" id="about_us" rows="4"><?= escHtml(getSetting('about_us','')) ?></textarea>
  </div>
  <div class="a-form-group">
    <label class="a-label">Rules and regulations</label>
    <textarea class="a-textarea" id="rules" rows="6"><?= escHtml(getSetting('rules','')) ?></textarea>
  </div>
</div>

<div class="a-card">
  <div class="a-card-title">Platform controls</div>
  <div style="display:flex;flex-direction:column;gap:14px">

    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border)">
      <div>
        <div style="font-weight:600">Registration mode</div>
        <div style="font-size:12px;color:var(--txt-3);margin-top:2px">How new members can join</div>
      </div>
      <select class="a-input a-select" id="registration_mode" style="width:150px">
        <option value="invite"  <?= getSetting('registration_mode')==='invite'  ?'selected':'' ?>>Invite only</option>
        <option value="open"    <?= getSetting('registration_mode')==='open'    ?'selected':'' ?>>Open</option>
        <option value="closed"  <?= getSetting('registration_mode')==='closed'  ?'selected':'' ?>>Closed</option>
      </select>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border)">
      <div>
        <div style="font-weight:600">Group chat</div>
        <div style="font-size:12px;color:var(--txt-3);margin-top:2px">Enable or disable the chat for all members</div>
      </div>
      <label class="a-toggle">
        <input type="checkbox" id="chat_enabled" <?= getSetting('chat_enabled','1')==='1'?'checked':'' ?>>
        <span class="a-tslider"></span>
      </label>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border)">
      <div>
        <div style="font-weight:600">File approval required</div>
        <div style="font-size:12px;color:var(--txt-3);margin-top:2px">Moderators must approve uploads before they're public</div>
      </div>
      <label class="a-toggle">
        <input type="checkbox" id="storage_approval_required" <?= getSetting('storage_approval_required','1')==='1'?'checked':'' ?>>
        <span class="a-tslider"></span>
      </label>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0">
      <div>
        <div style="font-weight:600;color:var(--red)">Maintenance mode</div>
        <div style="font-size:12px;color:var(--txt-3);margin-top:2px">Non-admins see a maintenance page</div>
      </div>
      <label class="a-toggle">
        <input type="checkbox" id="maintenance_mode" <?= getSetting('maintenance_mode','0')==='1'?'checked':'' ?>>
        <span class="a-tslider"></span>
      </label>
    </div>
  </div>
</div>

<button class="btn-primary" id="save-settings-btn" style="margin-bottom:20px"> Save all settings</button>

<script>
document.getElementById('save-settings-btn').addEventListener('click', async () => {
  const btn = document.getElementById('save-settings-btn');
  btn.textContent = 'Saving'; btn.disabled = true;
  const settings = {
    site_name:                  document.getElementById('site_name').value,
    college_name:               document.getElementById('college_name').value,
    about_us:                   document.getElementById('about_us').value,
    rules:                      document.getElementById('rules').value,
    registration_mode:          document.getElementById('registration_mode').value,
    chat_enabled:               document.getElementById('chat_enabled').checked ? '1' : '0',
    storage_approval_required:  document.getElementById('storage_approval_required').checked ? '1' : '0',
    maintenance_mode:           document.getElementById('maintenance_mode').checked ? '1' : '0',
  };
  try {
    await api('admin/settings.php', { method:'POST', body:JSON.stringify(settings) });
    showToast('Settings saved!','success');
  } catch(e) { showToast(e.message,'error'); }
  finally { btn.textContent = ' Save all settings'; btn.disabled = false; }
});
</script>

<?php require_once 'includes/layout_end.php'; ?>
