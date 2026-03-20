<?php
declare(strict_types=1);
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/uploader.php';
initSession();

if (!empty($_SESSION['logged_in'])) { header('Location: /'); exit; }

$regMode = getSetting('registration_mode', 'invite');
$token   = trim($_GET['token'] ?? '');
$invite  = null;
$tokenError = '';

if ($regMode === 'invite') {
    if (!$token) {
        $tokenError = 'No invite token provided. Please use the invite link sent to you.';
    } else {
        try {
            $pdo  = getDB();
            $stmt = $pdo->prepare("SELECT * FROM invites WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1");
            $stmt->execute([$token]);
            $invite = $stmt->fetch();
            if (!$invite) $tokenError = 'This invite link is invalid or has expired. Please contact your admin.';
        } catch (\Throwable $e) { $tokenError = 'Server error. Please try again.'; }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $mode = getSetting('registration_mode', 'invite');
    if ($mode === 'closed') { echo json_encode(['success'=>false,'error'=>'Registration is currently closed.']); exit; }

    $tok  = trim($_POST['token'] ?? '');
    $name = trim($_POST['full_name'] ?? '');
    $nick = trim($_POST['nickname'] ?? '') ?: explode(' ', $name)[0];
    $roll = trim($_POST['roll_no'] ?? '');
    $gen  = $_POST['gender'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $conf  = $_POST['password_confirm'] ?? '';

    if (!$name || !$roll || !$gen || !$email || !$pass || !$conf) { echo json_encode(['success'=>false,'error'=>'All fields are required.']); exit; }
    if (!validateEnum($gen, ['male','female','other'])) { echo json_encode(['success'=>false,'error'=>'Invalid gender.']); exit; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['success'=>false,'error'=>'Invalid email address.']); exit; }
    if (strlen($pass) < 8) { echo json_encode(['success'=>false,'error'=>'Password must be at least 8 characters.']); exit; }
    if ($pass !== $conf) { echo json_encode(['success'=>false,'error'=>'Passwords do not match.']); exit; }

    $idCardPath = null;
    if (!empty($_FILES['id_card']) && $_FILES['id_card']['error'] !== UPLOAD_ERR_NO_FILE) {
        try {
            $up = handleUpload($_FILES['id_card'], 'id_cards', ['jpg','jpeg','png'], 5 * 1024 * 1024);
            $idCardPath = $up['file_path'];
        } catch (\RuntimeException $e) { echo json_encode(['success'=>false,'error'=>'ID card upload failed: '.$e->getMessage()]); exit; }
    } elseif ($mode === 'open') {
        echo json_encode(['success'=>false,'error'=>'Student ID card image is required.']); exit;
    }

    try {
        $pdo = getDB();
        $inv = null;
        if ($mode === 'invite') {
            $stmt = $pdo->prepare("SELECT * FROM invites WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1");
            $stmt->execute([$tok]);
            $inv = $stmt->fetch();
            if (!$inv) { echo json_encode(['success'=>false,'error'=>'Invalid or expired invite token.']); exit; }
            if ($inv['email'] !== '' && strtolower($inv['email']) !== strtolower($email)) {
                echo json_encode(['success'=>false,'error'=>'This invite was sent to a different email address.']); exit;
            }
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR roll_no = ? LIMIT 1");
        $stmt->execute([$email, $roll]);
        if ($stmt->fetch()) { echo json_encode(['success'=>false,'error'=>'Email or roll number already registered.']); exit; }

        $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
        $isApproved = ($mode === 'invite') ? 1 : 0;

        $pdo->prepare("INSERT INTO users (full_name, nickname, roll_no, gender, email, password_hash, id_card, role, is_active, is_approved) VALUES (?, ?, ?, ?, ?, ?, ?, 'student', 1, ?)")
            ->execute([$name, $nick, $roll, $gen, $email, $hash, $idCardPath, $isApproved]);
        $userId = (int)$pdo->lastInsertId();

        if ($inv) { $pdo->prepare("UPDATE invites SET used = 1 WHERE token = ?")->execute([$tok]); }

        if ($isApproved) {
            $userRow = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
            $userRow->execute([$userId]);
            loginUser($userRow->fetch());
            echo json_encode(['success'=>true,'redirect'=>'/']);
        } else {
            echo json_encode(['success'=>true,'pending'=>true,'redirect'=>'/login.php?registered=1']);
        }
    } catch (\Throwable $e) {
        error_log('register.php: '.$e->getMessage());
        echo json_encode(['success'=>false,'error'=>'Registration failed. Please try again.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2032%2032%22%3E%3Crect%20width%3D%2232%22%20height%3D%2232%22%20rx%3D%227%22%20fill%3D%22%23C0000C%22%2F%3E%3Cpath%20d%3D%22M12%206h8M14%206v8l-4%209a2%202%200%200%200%201.8%203h8.4a2%202%200%200%200%201.8-3l-4-9V6%22%20stroke%3D%22white%22%20stroke-width%3D%221.8%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%20fill%3D%22none%22%2F%3E%3Ccircle%20cx%3D%2219%22%20cy%3D%2219%22%20r%3D%221.2%22%20fill%3D%22white%22%2F%3E%3Ccircle%20cx%3D%2216%22%20cy%3D%2222%22%20r%3D%220.9%22%20fill%3D%22rgba%28255%2C255%2C255%2C0.6%29%22%2F%3E%3C%2Fsvg%3E">
  <meta name="theme-color" content="#C0000C">
  <title>Register – GSSC Science Official</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root{--red:#C0000C;--red-dark:#8B0000;--red-lt:#FFF0F0;--bg:#EFEFEF;--surface:#FFFFFF;--txt:#111;--txt-2:#555;--txt-3:#999;--border:rgba(0,0,0,0.09);--fh:'Poppins',sans-serif;--fb:'DM Sans',sans-serif;--r-pill:999px;--r-lg:20px;--sh-red:0 6px 28px rgba(192,0,12,0.28);--sh-md:0 4px 18px rgba(0,0,0,0.10)}
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    html,body{min-height:100%;font-family:var(--fb)}
    body{background:var(--bg);display:flex;align-items:center;justify-content:center;padding:20px;position:relative;overflow-x:hidden}
    body::before{content:'';position:fixed;width:500px;height:500px;background:radial-gradient(circle,rgba(192,0,12,0.12) 0%,transparent 70%);top:-150px;right:-150px;pointer-events:none;border-radius:50%}
    .card{background:var(--surface);border-radius:var(--r-lg);box-shadow:var(--sh-md);width:100%;max-width:440px;overflow:hidden;position:relative;z-index:1;margin:20px 0}
    .card-header{background:var(--red);padding:24px 28px 20px;display:flex;flex-direction:column;align-items:center;gap:10px}
    .logo-circle{width:62px;height:62px;border-radius:50%;background:rgba(255,255,255,0.18);border:2.5px solid rgba(255,255,255,0.4);display:flex;align-items:center;justify-content:center}
    .logo-txt{font-family:var(--fh);font-size:19px;font-weight:700;color:#fff}
    .card-title{font-family:var(--fh);font-size:17px;font-weight:700;color:#fff;text-align:center}
    .card-sub{font-size:12px;color:rgba(255,255,255,.75);text-align:center;margin-top:-4px}
    .college-strip{background:#5C0006;padding:9px 20px;text-align:center;font-family:var(--fh);font-size:11px;font-weight:600;color:rgba(255,255,255,.7);letter-spacing:.04em}
    .card-body{padding:24px 28px}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .form-group{margin-bottom:14px}
    .form-label{display:block;font-size:11.5px;font-weight:600;color:var(--txt-2);margin-bottom:5px;text-transform:uppercase;letter-spacing:.06em}
    .input-wrap{display:flex;align-items:center;background:var(--bg);border-radius:var(--r-pill);border:1.5px solid var(--border);padding:0 14px;gap:8px;transition:border-color .18s,box-shadow .18s}
    .input-wrap:focus-within{border-color:var(--red);box-shadow:0 0 0 3px rgba(192,0,12,0.1)}
    .input-wrap svg{color:var(--txt-3);flex-shrink:0}
    .input-wrap input,.input-wrap select{flex:1;padding:11px 0;font-size:13.5px;color:var(--txt);background:transparent;border:none;outline:none;font-family:var(--fb)}
    .input-wrap input::placeholder{color:var(--txt-3)}
    .input-wrap select{cursor:pointer}
    .id-upload{border:2px dashed var(--border);border-radius:14px;padding:18px;text-align:center;cursor:pointer;transition:border-color .18s,background .18s;position:relative;overflow:hidden}
    .id-upload:hover,.id-upload.drag{border-color:var(--red);background:var(--red-lt)}
    .id-upload input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
    .id-preview{max-width:100%;max-height:160px;border-radius:10px;margin:10px auto 0;display:none;object-fit:contain}
    .error-msg{background:var(--red-lt);color:var(--red);font-size:13px;font-weight:500;padding:10px 14px;border-radius:10px;margin-bottom:14px;display:none}
    .error-msg.show{display:block}
    .info-banner{background:#EFF6FF;border:1px solid #BFDBFE;color:#1e40af;font-size:13px;padding:10px 14px;border-radius:10px;margin-bottom:14px;display:flex;gap:8px;align-items:flex-start;line-height:1.5}
    .btn-reg{width:100%;padding:13px;background:var(--red);color:#fff;border:none;border-radius:var(--r-pill);font-family:var(--fh);font-size:15px;font-weight:600;cursor:pointer;transition:background .18s;box-shadow:var(--sh-red);display:flex;align-items:center;justify-content:center;gap:8px}
    .btn-reg:hover:not(:disabled){background:var(--red-dark)}
    .btn-reg:disabled{opacity:.7;cursor:not-allowed}
    .spinner{width:18px;height:18px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:none}
    .btn-reg.loading .spinner{display:block}
    @keyframes spin{to{transform:rotate(360deg)}}
    .card-footer{padding:0 28px 22px;text-align:center;font-size:13px;color:var(--txt-3)}
    .card-footer a{color:var(--red);font-weight:600}
    .state-box{padding:28px;text-align:center;display:flex;flex-direction:column;align-items:center;gap:12px}
    .state-icon{width:64px;height:64px;border-radius:50%;background:var(--red-lt);display:flex;align-items:center;justify-content:center;font-size:28px}
    .state-title{font-family:var(--fh);font-size:16px;font-weight:600;color:var(--txt)}
    .state-sub{font-size:13px;color:var(--txt-3);max-width:280px;line-height:1.6}
    .strength-bar{height:3px;border-radius:3px;margin-top:5px;background:var(--border);transition:all .3s}
    .form-hint{font-size:11px;color:var(--txt-3);margin-top:4px;padding-left:2px}
    @media(max-width:480px){
      body { padding: 0; background: var(--red); align-items: flex-end; }
      .card { max-width:100%; border-radius: var(--r-lg) var(--r-lg) 0 0; min-height: 80dvh; }
      body::before,body::after{display:none}
      .card-body{padding:20px}
      .form-row{grid-template-columns:1fr}
      input,select,textarea{font-size:16px} /* prevent iOS zoom */
    }
  </style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <div class="logo-circle"><div class="logo-txt">GSSC</div></div>
    <div>
      <div class="card-title">Create your account</div>
      <div class="card-sub">GSSC-science official</div>
    </div>
  </div>
  <div class="college-strip">Govt. Shaheed Suhrawardy College – Science Department</div>

<?php if ($regMode === 'closed'): ?>
  <div class="state-box">
    <div class="state-icon">🔒</div>
    <div class="state-title">Registration is closed</div>
    <div class="state-sub">New registrations are currently not being accepted. Please contact an admin for access.</div>
    <a href="/login.php" style="color:var(--red);font-weight:600;font-size:13px;margin-top:4px">← Back to sign in</a>
  </div>

<?php elseif ($regMode === 'invite' && $tokenError): ?>
  <div class="state-box">
    <div class="state-icon">⚠️</div>
    <div class="state-title">Invalid invite link</div>
    <div class="state-sub"><?= escHtml($tokenError) ?></div>
    <a href="/login.php" style="color:var(--red);font-weight:600;font-size:13px;margin-top:4px">← Back to sign in</a>
  </div>

<?php else: ?>
  <div class="card-body">
    <?php if ($regMode === 'open'): ?>
    <div class="info-banner">
      <span style="font-size:16px;flex-shrink:0">ℹ️</span>
      <span>Your account will be reviewed by an admin before you can log in. Upload your student ID card to speed up verification.</span>
    </div>
    <?php endif; ?>

    <div class="error-msg" id="error-msg"></div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Full name</label>
        <div class="input-wrap">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <input type="text" id="full_name" placeholder="Your full name" autocomplete="name">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Nickname</label>
        <div class="input-wrap">
          <input type="text" id="nickname" placeholder="Short name" autocomplete="off">
        </div>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Roll number</label>
        <div class="input-wrap">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
          <input type="text" id="roll_no" placeholder="e.g. 2101" autocomplete="off">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Gender</label>
        <div class="input-wrap">
          <select id="gender">
            <option value="">Select</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
            <option value="other">Other</option>
          </select>
        </div>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Email address</label>
      <div class="input-wrap">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,12 2,6"/></svg>
        <?php if ($regMode === 'invite' && !empty($invite['email'])): ?>
          <input type="email" id="email" value="<?= escHtml($invite['email']) ?>" readonly style="opacity:.7;cursor:not-allowed">
        <?php else: ?>
          <input type="email" id="email" placeholder="your@email.com" autocomplete="email">
        <?php endif; ?>
      </div>
      <?php if ($regMode === 'invite' && !empty($invite['email'])): ?>
        <div class="form-hint">This invite is tied to this email address.</div>
      <?php elseif ($regMode === 'invite'): ?>
        <div class="form-hint">Open invite — enter your email address.</div>
      <?php endif; ?>
    </div>

    <div class="form-group">
      <label class="form-label">Password</label>
      <div class="input-wrap">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <input type="password" id="password" placeholder="Min 8 characters" autocomplete="new-password">
      </div>
      <div class="strength-bar" id="strength-bar"></div>
    </div>

    <div class="form-group">
      <label class="form-label">Confirm password</label>
      <div class="input-wrap">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <input type="password" id="password_confirm" placeholder="Repeat password" autocomplete="new-password">
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Student ID card <?= $regMode === 'open' ? '<span style="color:var(--red)">*</span>' : '<span style="color:var(--txt-3);font-weight:400;text-transform:none">(optional)</span>' ?></label>
      <div class="id-upload" id="id-drop">
        <input type="file" id="id_card" accept="image/jpeg,image/png" onchange="previewId(this)">
        <div style="font-size:26px">🪪</div>
        <div style="font-size:13px;color:var(--txt-2);margin-top:6px">Tap to upload your student ID card</div>
        <div style="font-size:11px;color:var(--txt-3);margin-top:3px">JPG or PNG · max 5MB<?= $regMode === 'open' ? ' · required' : '' ?></div>
        <img id="id-preview" class="id-preview" alt="ID card preview">
      </div>
    </div>

    <button class="btn-reg" id="reg-btn" type="button">
      <div class="spinner"></div>
      <span class="btn-text"><?= $regMode === 'open' ? 'Submit for review' : 'Create Account' ?></span>
    </button>
  </div>
  <div class="card-footer">Already have an account? <a href="/login.php">Sign in</a></div>
<?php endif; ?>
</div>

<script>
const TOKEN    = <?= json_encode($token) ?>;
const REG_MODE = <?= json_encode($regMode) ?>;

function previewId(input) {
  const preview = document.getElementById('id-preview');
  if (!input.files[0]) { preview.style.display='none'; return; }
  const r = new FileReader();
  r.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
  r.readAsDataURL(input.files[0]);
}
const dz = document.getElementById('id-drop');
if (dz) {
  ['dragenter','dragover'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('drag'); }));
  ['dragleave','drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('drag'); }));
  dz.addEventListener('drop', e => {
    const f = e.dataTransfer?.files[0]; if (!f) return;
    const dt = new DataTransfer(); dt.items.add(f);
    document.getElementById('id_card').files = dt.files;
    previewId(document.getElementById('id_card'));
  });
}
document.getElementById('password')?.addEventListener('input', function() {
  const bar = document.getElementById('strength-bar'); const v = this.value; let s = 0;
  if (v.length>=8) s++; if (/[A-Z]/.test(v)) s++; if (/[0-9]/.test(v)) s++; if (/[^A-Za-z0-9]/.test(v)) s++;
  bar.style.background = ['','#e74c3c','#e67e22','#f1c40f','#2ecc71'][s]||'';
  bar.style.width = ['0%','30%','55%','75%','100%'][s];
});
document.getElementById('full_name')?.addEventListener('input', function() {
  const n = document.getElementById('nickname'); if (!n.value) n.value = this.value.split(' ')[0];
});
async function doRegister() {
  const btn = document.getElementById('reg-btn');
  const err = document.getElementById('error-msg');
  err.classList.remove('show');
  const fd = new FormData();
  if (REG_MODE === 'invite') fd.append('token', TOKEN);
  fd.append('full_name',        document.getElementById('full_name').value.trim());
  fd.append('nickname',         document.getElementById('nickname').value.trim());
  fd.append('roll_no',          document.getElementById('roll_no').value.trim());
  fd.append('gender',           document.getElementById('gender').value);
  fd.append('email',            document.getElementById('email').value.trim());
  fd.append('password',         document.getElementById('password').value);
  fd.append('password_confirm', document.getElementById('password_confirm').value);
  const idFile = document.getElementById('id_card')?.files[0];
  if (idFile) fd.append('id_card', idFile);
  btn.disabled = true; btn.classList.add('loading');
  try {
    const res  = await fetch('/register.php', { method:'POST', body:fd, credentials:'same-origin' });
    const data = await res.json();
    if (data.success) {
      btn.querySelector('.btn-text').textContent = data.pending ? 'Submitted! Awaiting approval...' : 'Success! Redirecting...';
      setTimeout(() => window.location.href = data.redirect || '/', data.pending ? 1500 : 500);
    } else {
      err.textContent = data.error; err.classList.add('show');
      btn.disabled = false; btn.classList.remove('loading');
    }
  } catch(_) {
    err.textContent = 'Connection error. Try again.'; err.classList.add('show');
    btn.disabled = false; btn.classList.remove('loading');
  }
}
document.getElementById('reg-btn')?.addEventListener('click', doRegister);
document.querySelectorAll('input:not([type=file])').forEach(el => el.addEventListener('keydown', e => { if(e.key==='Enter') doRegister(); }));
</script>
</body>
</html>