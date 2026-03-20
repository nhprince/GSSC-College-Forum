<?php
declare(strict_types=1);
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
initSession();

if (!empty($_SESSION['logged_in'])) { header('Location: /'); exit; }

$token = trim($_GET['token'] ?? '');
$valid = false;
$tokenErr = '';

if (!$token) {
    $tokenErr = 'No reset token provided.';
} else {
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();
        if ($reset) $valid = true;
        else $tokenErr = 'This reset link is invalid or has expired. Please request a new one.';
    } catch (\Throwable $e) { $tokenErr = 'Server error.'; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $body = getJsonBody();
    $tok  = trim($body['token'] ?? '');
    $pass = $body['password'] ?? '';
    $conf = $body['password_confirm'] ?? '';

    if (strlen($pass) < 8) { echo json_encode(['success'=>false,'error'=>'Password must be at least 8 characters.']); exit; }
    if ($pass !== $conf)   { echo json_encode(['success'=>false,'error'=>'Passwords do not match.']); exit; }

    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$tok]);
        $reset = $stmt->fetch();
        if (!$reset) { echo json_encode(['success'=>false,'error'=>'Invalid or expired token.']); exit; }

        $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?")->execute([$hash, $reset['email']]);
        $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")->execute([$tok]);
        echo json_encode(['success'=>true,'redirect'=>'/login.php']);
    } catch (\Throwable $e) {
        error_log($e->getMessage());
        echo json_encode(['success'=>false,'error'=>'Server error.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2032%2032%22%3E%3Crect%20width%3D%2232%22%20height%3D%2232%22%20rx%3D%227%22%20fill%3D%22%23C0000C%22%2F%3E%3Cpath%20d%3D%22M12%206h8M14%206v8l-4%209a2%202%200%200%200%201.8%203h8.4a2%202%200%200%200%201.8-3l-4-9V6%22%20stroke%3D%22white%22%20stroke-width%3D%221.8%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%20fill%3D%22none%22%2F%3E%3Ccircle%20cx%3D%2219%22%20cy%3D%2219%22%20r%3D%221.2%22%20fill%3D%22white%22%2F%3E%3Ccircle%20cx%3D%2216%22%20cy%3D%2222%22%20r%3D%220.9%22%20fill%3D%22rgba%28255%2C255%2C255%2C0.6%29%22%2F%3E%3C%2Fsvg%3E">
  <title>Reset Password  GSSC</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root{--red:#C0000C;--red-dark:#8B0000;--red-lt:#FFF0F0;--bg:#EFEFEF;--surface:#FFF;--txt:#111;--txt-2:#555;--txt-3:#999;--border:rgba(0,0,0,0.09);--fh:'Poppins',sans-serif;--fb:'DM Sans',sans-serif;--r-pill:999px;--r-lg:20px;--sh-md:0 4px 18px rgba(0,0,0,.10);--sh-red:0 6px 28px rgba(192,0,12,.28)}
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    html,body{height:100%;font-family:var(--fb)}
    body{background:var(--bg);display:flex;align-items:center;justify-content:center;min-height:100dvh;padding:20px}
    .card{background:var(--surface);border-radius:var(--r-lg);box-shadow:var(--sh-md);width:100%;max-width:390px;overflow:hidden}
    .card-header{background:var(--red);padding:22px 28px 18px;text-align:center}
    .card-title{font-family:var(--fh);font-size:17px;font-weight:700;color:#fff;margin-bottom:4px}
    .card-sub{font-size:12px;color:rgba(255,255,255,.75)}
    .card-body{padding:24px 28px}
    .form-label{display:block;font-size:11.5px;font-weight:600;color:var(--txt-2);margin-bottom:5px;text-transform:uppercase;letter-spacing:.06em}
    .form-group{margin-bottom:14px}
    .input-wrap{display:flex;align-items:center;background:var(--bg);border-radius:var(--r-pill);border:1.5px solid var(--border);padding:0 14px;gap:8px;transition:border-color .18s,box-shadow .18s}
    .input-wrap:focus-within{border-color:var(--red);box-shadow:0 0 0 3px rgba(192,0,12,0.1)}
    .input-wrap input{flex:1;padding:12px 0;font-size:14px;color:var(--txt);background:transparent;border:none;outline:none;font-family:var(--fb)}
    .input-wrap input::placeholder{color:var(--txt-3)}
    .msg{font-size:13px;font-weight:500;padding:10px 14px;border-radius:10px;margin-bottom:14px;display:none}
    .msg.show{display:block}
    .msg.error{background:var(--red-lt);color:var(--red)}
    .msg.success{background:#ECFDF5;color:#065F46}
    .btn{width:100%;padding:13px;background:var(--red);color:#fff;border:none;border-radius:var(--r-pill);font-family:var(--fh);font-size:15px;font-weight:600;cursor:pointer;box-shadow:var(--sh-red);transition:background .18s}
    .btn:hover{background:var(--red-dark)}
    .err-block{padding:24px;text-align:center;font-size:14px;color:var(--txt-2);line-height:1.6}
    .err-block a{color:var(--red);font-weight:600}
  </style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <div class="card-title">Set new password</div>
    <div class="card-sub">GSSC-science official</div>
  </div>
  <?php if (!$valid): ?>
  <div class="err-block"> <?= escHtml($tokenErr) ?><br><br><a href="/forgot-password.php">Request new reset link</a></div>
  <?php else: ?>
  <div class="card-body">
    <div class="msg" id="msg"></div>
    <div class="form-group">
      <label class="form-label">New password</label>
      <div class="input-wrap"><input type="password" id="password" placeholder="Min 8 characters" autofocus></div>
    </div>
    <div class="form-group">
      <label class="form-label">Confirm password</label>
      <div class="input-wrap"><input type="password" id="confirm" placeholder="Repeat password"></div>
    </div>
    <button class="btn" id="reset-btn">Set new password</button>
  </div>
  <script>
  document.getElementById('reset-btn').addEventListener('click', async () => {
    const msg = document.getElementById('msg');
    msg.className='msg'; msg.classList.remove('show');
    const p = document.getElementById('password').value;
    const c = document.getElementById('confirm').value;
    try {
      const res  = await fetch('/reset-password.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({token:<?= json_encode($token) ?>,password:p,password_confirm:c}),credentials:'same-origin'});
      const data = await res.json();
      msg.textContent = data.success ? 'Password updated! Redirecting to login' : (data.error || 'Error');
      msg.className = 'msg ' + (data.success?'success':'error') + ' show';
      if (data.success) setTimeout(()=>window.location.href='/login.php',1500);
    } catch(_){ msg.textContent='Error.'; msg.className='msg error show'; }
  });
  </script>
  <?php endif; ?>
</div>
</body>
</html>