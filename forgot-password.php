<?php
declare(strict_types=1);
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
initSession();

if (!empty($_SESSION['logged_in'])) { header('Location: /'); exit; }

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $body  = getJsonBody();
    $email = trim($body['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success'=>false,'error'=>'Invalid email address.']); exit;
    }

    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Delete any existing reset tokens for this email
            $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

            $token = bin2hex(random_bytes(32));
            $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))")
                ->execute([$email, $token]);

            // In production: send email via PHPMailer
            // For now: log the reset link
            error_log("Password reset link: " . APP_URL . "/reset-password.php?token=" . $token);
        }
        // Always return success (don't reveal if email exists)
        echo json_encode(['success'=>true,'message'=>'If your email is registered, a reset link has been sent.']);
    } catch (\Throwable $e) {
        error_log($e->getMessage());
        echo json_encode(['success'=>false,'error'=>'Server error. Please try again.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2032%2032%22%3E%3Crect%20width%3D%2232%22%20height%3D%2232%22%20rx%3D%227%22%20fill%3D%22%23C0000C%22%2F%3E%3Cpath%20d%3D%22M12%206h8M14%206v8l-4%209a2%202%200%200%200%201.8%203h8.4a2%202%200%200%200%201.8-3l-4-9V6%22%20stroke%3D%22white%22%20stroke-width%3D%221.8%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%20fill%3D%22none%22%2F%3E%3Ccircle%20cx%3D%2219%22%20cy%3D%2219%22%20r%3D%221.2%22%20fill%3D%22white%22%2F%3E%3Ccircle%20cx%3D%2216%22%20cy%3D%2222%22%20r%3D%220.9%22%20fill%3D%22rgba%28255%2C255%2C255%2C0.6%29%22%2F%3E%3C%2Fsvg%3E">
  <title>Forgot Password  GSSC</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root{--red:#C0000C;--red-dark:#8B0000;--red-lt:#FFF0F0;--bg:#EFEFEF;--surface:#FFFFFF;--txt:#111;--txt-2:#555;--txt-3:#999;--border:rgba(0,0,0,0.09);--fh:'Poppins',sans-serif;--fb:'DM Sans',sans-serif;--r-pill:999px;--r-lg:20px;--sh-md:0 4px 18px rgba(0,0,0,0.10);--sh-red:0 6px 28px rgba(192,0,12,0.28)}
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    html,body{height:100%;font-family:var(--fb)}
    body{background:var(--bg);display:flex;align-items:center;justify-content:center;min-height:100dvh;padding:20px}
    .card{background:var(--surface);border-radius:var(--r-lg);box-shadow:var(--sh-md);width:100%;max-width:390px;overflow:hidden}
    .card-header{background:var(--red);padding:24px 28px 20px;text-align:center}
    .card-title{font-family:var(--fh);font-size:17px;font-weight:700;color:#fff;margin-bottom:4px}
    .card-sub{font-size:12px;color:rgba(255,255,255,.75)}
    .card-body{padding:24px 28px}
    .form-label{display:block;font-size:11.5px;font-weight:600;color:var(--txt-2);margin-bottom:5px;text-transform:uppercase;letter-spacing:.06em}
    .input-wrap{display:flex;align-items:center;background:var(--bg);border-radius:var(--r-pill);border:1.5px solid var(--border);padding:0 14px;gap:8px;transition:border-color .18s,box-shadow .18s}
    .input-wrap:focus-within{border-color:var(--red);box-shadow:0 0 0 3px rgba(192,0,12,0.1)}
    .input-wrap input{flex:1;padding:12px 0;font-size:14px;color:var(--txt);background:transparent;border:none;outline:none;font-family:var(--fb)}
    .input-wrap input::placeholder{color:var(--txt-3)}
    .msg{font-size:13px;font-weight:500;padding:10px 14px;border-radius:10px;margin-bottom:14px;display:none}
    .msg.show{display:block}
    .msg.error{background:var(--red-lt);color:var(--red)}
    .msg.success{background:#ECFDF5;color:#065F46}
    .btn{width:100%;padding:13px;background:var(--red);color:#fff;border:none;border-radius:var(--r-pill);font-family:var(--fh);font-size:15px;font-weight:600;cursor:pointer;box-shadow:var(--sh-red);margin-top:16px;transition:background .18s}
    .btn:hover{background:var(--red-dark)}
    .back{display:block;text-align:center;margin-top:14px;font-size:13px;color:var(--red);font-weight:600}
  </style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <div class="card-title">Reset your password</div>
    <div class="card-sub">GSSC-science official</div>
  </div>
  <div class="card-body">
    <p style="font-size:13.5px;color:var(--txt-2);margin-bottom:18px;line-height:1.6">Enter your registered email address and we'll send you a link to reset your password.</p>
    <div class="msg" id="msg"></div>
    <label class="form-label">Email address</label>
    <div class="input-wrap">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,12 2,6"/></svg>
      <input type="email" id="email" placeholder="your@email.com" autofocus>
    </div>
    <button class="btn" id="send-btn">Send reset link</button>
    <a class="back" href="/login.php"> Back to sign in</a>
  </div>
</div>
<script>
document.getElementById('send-btn').addEventListener('click', async () => {
  const msg  = document.getElementById('msg');
  const email = document.getElementById('email').value.trim();
  msg.className = 'msg'; msg.classList.remove('show');
  if (!email) { msg.textContent='Please enter your email.'; msg.className='msg error show'; return; }
  try {
    const res  = await fetch('/forgot-password.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email}),credentials:'same-origin'});
    const data = await res.json();
    msg.textContent = data.message || data.error;
    msg.className = 'msg ' + (data.success ? 'success' : 'error') + ' show';
  } catch(_) { msg.textContent='Connection error.'; msg.className='msg error show'; }
});
document.getElementById('email').addEventListener('keydown',e=>{if(e.key==='Enter')document.getElementById('send-btn').click()});
</script>
</body>
</html>