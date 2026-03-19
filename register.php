<?php
declare(strict_types=1);
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
initSession();

if (!empty($_SESSION['logged_in'])) { header('Location: /'); exit; }

// Validate invite token
$token = trim($_GET['token'] ?? '');
$invite = null;
$tokenError = '';

if (!$token) {
    $tokenError = 'No invite token provided. Please use the invite link sent to your email.';
} else {
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM invites WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $invite = $stmt->fetch();
        if (!$invite) $tokenError = 'This invite link is invalid or has expired. Please contact your admin.';
    } catch (\Throwable $e) {
        $tokenError = 'Server error. Please try again.';
    }
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $body = getJsonBody();
    $errors = validateRequired($body, ['token','full_name','roll_no','gender','email','password','password_confirm']);
    if ($errors) { echo json_encode(['success'=>false,'error'=>'All fields are required.']); exit; }

    $tok   = trim($body['token']);
    $name  = trim($body['full_name']);
    $nick  = trim($body['nickname'] ?? '') ?: explode(' ', $name)[0];
    $roll  = trim($body['roll_no']);
    $gen   = $body['gender'];
    $email = trim($body['email']);
    $pass  = $body['password'];
    $conf  = $body['password_confirm'];

    if (!validateEnum($gen, ['male','female','other'])) { echo json_encode(['success'=>false,'error'=>'Invalid gender.']); exit; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))     { echo json_encode(['success'=>false,'error'=>'Invalid email address.']); exit; }
    if (strlen($pass) < 8)                              { echo json_encode(['success'=>false,'error'=>'Password must be at least 8 characters.']); exit; }
    if ($pass !== $conf)                                { echo json_encode(['success'=>false,'error'=>'Passwords do not match.']); exit; }

    try {
        $pdo  = getDB();

        // Re-validate token
        $stmt = $pdo->prepare("SELECT * FROM invites WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$tok]);
        $inv  = $stmt->fetch();
        if (!$inv)  { echo json_encode(['success'=>false,'error'=>'Invalid or expired invite token.']); exit; }
        if (strtolower($inv['email']) !== strtolower($email)) {
            echo json_encode(['success'=>false,'error'=>'This invite was sent to a different email address.']); exit;
        }

        // Check uniqueness
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR roll_no = ? LIMIT 1");
        $stmt->execute([$email, $roll]);
        if ($stmt->fetch()) { echo json_encode(['success'=>false,'error'=>'Email or roll number already registered.']); exit; }

        $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);

        $pdo->prepare("
            INSERT INTO users (full_name, nickname, roll_no, gender, email, password_hash, role, is_active, is_approved)
            VALUES (?, ?, ?, ?, ?, ?, 'student', 1, 1)
        ")->execute([$name, $nick, $roll, $gen, $email, $hash]);

        $userId = (int)$pdo->lastInsertId();

        // Mark invite used
        $pdo->prepare("UPDATE invites SET used = 1 WHERE token = ?")->execute([$tok]);

        // Auto login
        $user = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $user->execute([$userId]);
        loginUser($user->fetch());

        echo json_encode(['success'=>true,'redirect'=>'/']);
    } catch (\Throwable $e) {
        error_log($e->getMessage());
        echo json_encode(['success'=>false,'error'=>'Registration failed. Please try again.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#C0000C">
  <title>Register  GSSC Science Official</title>
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
    .logo-placeholder{font-family:var(--fh);font-size:19px;font-weight:700;color:#fff}
    .card-title{font-family:var(--fh);font-size:17px;font-weight:700;color:#fff;text-align:center}
    .card-subtitle{font-size:12px;color:rgba(255,255,255,.75);text-align:center;margin-top:-4px}
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
    .error-msg{background:var(--red-lt);color:var(--red);font-size:13px;font-weight:500;padding:10px 14px;border-radius:10px;margin-bottom:14px;display:none}
    .error-msg.show{display:block}
    .btn-reg{width:100%;padding:13px;background:var(--red);color:#fff;border:none;border-radius:var(--r-pill);font-family:var(--fh);font-size:15px;font-weight:600;cursor:pointer;transition:background .18s,transform .12s;box-shadow:var(--sh-red);display:flex;align-items:center;justify-content:center;gap:8px}
    .btn-reg:hover:not(:disabled){background:var(--red-dark)}
    .btn-reg:disabled{opacity:.7;cursor:not-allowed}
    .spinner{width:18px;height:18px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:none}
    .btn-reg.loading .spinner{display:block}
    @keyframes spin{to{transform:rotate(360deg)}}
    .card-footer{padding:0 28px 22px;text-align:center;font-size:13px;color:var(--txt-3)}
    .card-footer a{color:var(--red);font-weight:600}
    .token-error{background:var(--red-lt);color:var(--red);padding:20px 28px;font-size:14px;line-height:1.6;text-align:center}
    .strength-bar{height:3px;border-radius:3px;margin-top:5px;background:var(--border);transition:all .3s}
    @media(max-width:440px){.card-body{padding:20px}.form-row{grid-template-columns:1fr}}
  </style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <div class="logo-circle"><div class="logo-placeholder">GSSC</div></div>
    <div>
      <div class="card-title">Create your account</div>
      <div class="card-subtitle">GSSC-science official</div>
    </div>
  </div>
  <div class="college-strip">Govt. Shaheed Suhrawardy College  Science Department</div>

  <?php if ($tokenError): ?>
  <div class="token-error">
     <?= escHtml($tokenError) ?><br>
    <a href="/login.php" style="color:var(--red);font-weight:600;margin-top:8px;display:inline-block;"> Back to login</a>
  </div>

  <?php else: ?>
  <div class="card-body">
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
        <input type="email" id="email" value="<?= escHtml($invite['email'] ?? '') ?>" placeholder="your@email.com">
      </div>
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

    <button class="btn-reg" id="reg-btn" type="button">
      <div class="spinner"></div>
      <span class="btn-text">Create Account</span>
    </button>
  </div>

  <div class="card-footer">
    Already have an account? <a href="/login.php">Sign in</a>
  </div>
  <?php endif; ?>
</div>

<script>
const TOKEN = <?= json_encode($token) ?>;

// Password strength
document.getElementById('password')?.addEventListener('input', function() {
  const bar = document.getElementById('strength-bar');
  const v = this.value;
  let s = 0;
  if (v.length >= 8) s++;
  if (/[A-Z]/.test(v)) s++;
  if (/[0-9]/.test(v)) s++;
  if (/[^A-Za-z0-9]/.test(v)) s++;
  const colors = ['','#e74c3c','#e67e22','#f1c40f','#2ecc71'];
  const widths = ['0%','30%','55%','75%','100%'];
  bar.style.background = colors[s] || '';
  bar.style.width = widths[s];
});

// Auto-fill nickname from full name
document.getElementById('full_name')?.addEventListener('input', function() {
  const nick = document.getElementById('nickname');
  if (!nick.value) nick.value = this.value.split(' ')[0];
});

async function doRegister() {
  const btn = document.getElementById('reg-btn');
  const err = document.getElementById('error-msg');
  err.classList.remove('show');

  const payload = {
    token:            TOKEN,
    full_name:        document.getElementById('full_name').value.trim(),
    nickname:         document.getElementById('nickname').value.trim(),
    roll_no:          document.getElementById('roll_no').value.trim(),
    gender:           document.getElementById('gender').value,
    email:            document.getElementById('email').value.trim(),
    password:         document.getElementById('password').value,
    password_confirm: document.getElementById('password_confirm').value,
  };

  btn.disabled = true; btn.classList.add('loading');

  try {
    const res  = await fetch('/register.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload), credentials:'same-origin' });
    const data = await res.json();
    if (data.success) {
      btn.querySelector('.btn-text').textContent = 'Success! Redirecting';
      setTimeout(() => window.location.href = data.redirect || '/', 500);
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
document.querySelectorAll('input').forEach(el => el.addEventListener('keydown', e => { if(e.key==='Enter') doRegister(); }));
</script>
</body>
</html>
