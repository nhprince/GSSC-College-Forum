<?php
declare(strict_types=1);
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
initSession();

// Already logged in  go to app
if (!empty($_SESSION['logged_in'])) {
    header('Location: /');
    exit;
}

$error = '';

// Handle POST login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Basic rate limit
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!checkRateLimit($ip, 'login', 5, 900)) {
        echo json_encode(['success' => false, 'error' => 'Too many attempts. Try again in 15 minutes.']);
        exit;
    }

    if (!$email || !$password) {
        echo json_encode(['success' => false, 'error' => 'Please enter email and password.']);
        exit;
    }

    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 AND is_approved = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid credentials.']);
            exit;
        }

        loginUser($user);
        echo json_encode(['success' => true, 'redirect' => '/']);
    } catch (\Throwable $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'error' => 'A server error occurred. Please try again.']);
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
  <title>Login  GSSC Science Official</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --red:      #C0000C;
      --red-dark: #8B0000;
      --red-deep: #5C0006;
      --red-lt:   #FFF0F0;
      --bg:       #EFEFEF;
      --surface:  #FFFFFF;
      --txt:      #111111;
      --txt-2:    #555555;
      --txt-3:    #999999;
      --border:   rgba(0,0,0,0.09);
      --fh: 'Poppins', sans-serif;
      --fb: 'DM Sans', sans-serif;
      --r-pill: 999px;
      --r-lg: 20px;
      --sh-red: 0 6px 28px rgba(192,0,12,0.28);
      --sh-md:  0 4px 18px rgba(0,0,0,0.10);
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { height: 100%; font-family: var(--fb); }
    body {
      background: var(--bg);
      display: flex; align-items: center; justify-content: center;
      min-height: 100dvh; padding: 20px;
      position: relative; overflow: hidden;
    }

    /* Background decorative circles */
    body::before, body::after {
      content: '';
      position: fixed;
      border-radius: 50%;
      pointer-events: none;
    }
    body::before {
      width: 500px; height: 500px;
      background: radial-gradient(circle, rgba(192,0,12,0.12) 0%, transparent 70%);
      top: -150px; right: -150px;
    }
    body::after {
      width: 400px; height: 400px;
      background: radial-gradient(circle, rgba(139,0,0,0.08) 0%, transparent 70%);
      bottom: -100px; left: -100px;
    }

    /* Card */
    .card {
      background: var(--surface);
      border-radius: var(--r-lg);
      box-shadow: var(--sh-md);
      width: 100%; max-width: 400px;
      overflow: hidden;
      position: relative; z-index: 1;
    }

    /* Header band */
    .card-header {
      background: var(--red);
      padding: 28px 28px 24px;
      display: flex; flex-direction: column; align-items: center; gap: 14px;
    }
    .logo-circle {
      width: 72px; height: 72px;
      border-radius: 50%;
      background: rgba(255,255,255,0.18);
      border: 3px solid rgba(255,255,255,0.4);
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 4px 16px rgba(0,0,0,0.2);
    }
    .logo-circle img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
    .logo-placeholder {
      font-family: var(--fh); font-size: 22px; font-weight: 700;
      color: #fff; letter-spacing: -0.5px;
    }
    .card-title {
      font-family: var(--fh); font-size: 18px; font-weight: 700;
      color: #fff; text-align: center; line-height: 1.25;
    }
    .card-subtitle {
      font-size: 12px; color: rgba(255,255,255,0.75);
      text-align: center; margin-top: -8px;
    }

    /* Body */
    .card-body { padding: 28px; }

    .form-label {
      display: block; font-size: 12px; font-weight: 600;
      color: var(--txt-2); margin-bottom: 6px;
      text-transform: uppercase; letter-spacing: 0.06em;
    }
    .form-group { margin-bottom: 16px; }

    .input-wrap {
      display: flex; align-items: center;
      background: var(--bg); border-radius: var(--r-pill);
      border: 1.5px solid var(--border);
      padding: 0 16px; gap: 10px;
      transition: border-color .18s, box-shadow .18s;
    }
    .input-wrap:focus-within {
      border-color: var(--red);
      box-shadow: 0 0 0 3px rgba(192,0,12,0.1);
    }
    .input-wrap svg { color: var(--txt-3); flex-shrink: 0; }
    .input-wrap input {
      flex: 1; padding: 12px 0;
      font-size: 14px; color: var(--txt);
      background: transparent; border: none; outline: none;
      font-family: var(--fb);
    }
    .input-wrap input::placeholder { color: var(--txt-3); }
    .eye-btn {
      background: none; border: none; cursor: pointer;
      color: var(--txt-3); padding: 4px;
      display: flex; align-items: center;
      transition: color .15s;
    }
    .eye-btn:hover { color: var(--txt); }

    /* Error */
    .error-msg {
      background: var(--red-lt); color: var(--red);
      font-size: 13px; font-weight: 500;
      padding: 10px 14px; border-radius: 10px;
      margin-bottom: 16px; display: none;
    }
    .error-msg.show { display: block; }

    /* Submit */
    .btn-login {
      width: 100%; padding: 14px;
      background: var(--red); color: #fff;
      border: none; border-radius: var(--r-pill);
      font-family: var(--fh); font-size: 15px; font-weight: 600;
      cursor: pointer; transition: background .18s, transform .12s;
      box-shadow: var(--sh-red);
      display: flex; align-items: center; justify-content: center; gap: 8px;
      position: relative; overflow: hidden;
    }
    .btn-login:hover:not(:disabled) { background: var(--red-dark); }
    .btn-login:active:not(:disabled) { transform: scale(0.98); }
    .btn-login:disabled { opacity: 0.7; cursor: not-allowed; }
    .btn-login .spinner {
      width: 18px; height: 18px;
      border: 2px solid rgba(255,255,255,0.4);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin .7s linear infinite;
      display: none;
    }
    .btn-login.loading .spinner { display: block; }
    .btn-login.loading .btn-text { opacity: 0.7; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Register link */
    .card-footer {
      padding: 0 28px 24px;
      text-align: center; font-size: 13px; color: var(--txt-3);
    }
    .card-footer a { color: var(--red); font-weight: 600; }
    .card-footer a:hover { text-decoration: underline; }

    /* College name strip */
    .college-strip {
      background: var(--red-deep);
      padding: 10px 20px;
      text-align: center;
      font-family: var(--fh); font-size: 11px; font-weight: 600;
      color: rgba(255,255,255,0.7);
      letter-spacing: 0.04em;
    }

    /* Responsive */
    @media(max-width: 440px) {
      .card-header { padding: 22px 20px 20px; }
      .card-body { padding: 22px 20px; }
      .card-footer { padding: 0 20px 20px; }
    }
  </style>
</head>
<body>

<div class="card">
  <!-- Header -->
  <div class="card-header">
    <div class="logo-circle">
      <div class="logo-placeholder">GSSC</div>
    </div>
    <div>
      <div class="card-title">GSSC-science official</div>
      <div class="card-subtitle">Sign in to your account</div>
    </div>
  </div>

  <!-- College strip -->
  <div class="college-strip">Govt. Shaheed Suhrawardy College  Science Department</div>

  <!-- Form -->
  <div class="card-body">
    <div class="error-msg" id="error-msg"></div>

    <div class="form-group">
      <label class="form-label" for="email">Email address</label>
      <div class="input-wrap">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,12 2,6"/></svg>
        <input type="email" id="email" placeholder="your@email.com" autocomplete="email" autofocus>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="password">Password</label>
      <div class="input-wrap">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <input type="password" id="password" placeholder="" autocomplete="current-password">
        <button class="eye-btn" type="button" id="eye-btn" aria-label="Show password">
          <svg id="eye-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
    </div>

    <div style="text-align:right; margin-bottom:20px; margin-top:-8px;">
      <a href="/forgot-password.php" style="font-size:12px; color:var(--red); font-weight:500;">Forgot password?</a>
    </div>

    <button class="btn-login" id="login-btn" type="button">
      <div class="spinner"></div>
      <span class="btn-text">Sign In</span>
    </button>
  </div>

  <div class="card-footer">
    Don't have an account? <a href="/register.php">Register with invite</a>
  </div>
</div>

<script>
  const emailEl  = document.getElementById('email');
  const passEl   = document.getElementById('password');
  const errorEl  = document.getElementById('error-msg');
  const loginBtn = document.getElementById('login-btn');
  const eyeBtn   = document.getElementById('eye-btn');

  function showError(msg) {
    errorEl.textContent = msg;
    errorEl.classList.add('show');
  }
  function clearError() { errorEl.classList.remove('show'); }

  eyeBtn.addEventListener('click', () => {
    const show = passEl.type === 'password';
    passEl.type = show ? 'text' : 'password';
    eyeBtn.innerHTML = show
      ? `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`
      : `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
  });

  async function doLogin() {
    clearError();
    const email    = emailEl.value.trim();
    const password = passEl.value;
    if (!email || !password) { showError('Please enter your email and password.'); return; }

    loginBtn.disabled = true;
    loginBtn.classList.add('loading');

    try {
      const form = new FormData();
      form.append('email', email);
      form.append('password', password);

      const res  = await fetch('/login.php', { method: 'POST', body: form, credentials: 'same-origin' });
      const data = await res.json();

      if (data.success) {
        loginBtn.querySelector('.btn-text').textContent = 'Welcome! Redirecting';
        setTimeout(() => window.location.href = data.redirect || '/', 400);
      } else {
        showError(data.error || 'Login failed.');
        loginBtn.disabled = false;
        loginBtn.classList.remove('loading');
        passEl.value = '';
        passEl.focus();
      }
    } catch (_) {
      showError('Connection error. Please try again.');
      loginBtn.disabled = false;
      loginBtn.classList.remove('loading');
    }
  }

  loginBtn.addEventListener('click', doLogin);
  [emailEl, passEl].forEach(el => el.addEventListener('keydown', e => {
    if (e.key === 'Enter') doLogin();
  }));
</script>

</body>
</html>
