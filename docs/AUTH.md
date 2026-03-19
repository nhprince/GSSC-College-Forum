# Authentication & Sessions

## Session Structure
After login, $_SESSION contains:
```
user_id, full_name, nickname, roll_no, email, role, avatar,
notif_enabled, sound_enabled, logged_in(true), csrf_token,
last_active(timestamp), last_db_ping(timestamp)
```

## Login Flow
1. POST to login.php with email + password
2. Rate limit check: 5 attempts per 15 min per IP
3. Query users WHERE email=? AND is_active=1 AND is_approved=1
4. password_verify() against stored bcrypt hash
5. On success: session_regenerate_id(true), set all session vars
6. Return {success:true, redirect:'/'} -> JS redirects

## Registration Flow (invite-only)
1. Admin generates invite link: /register.php?token=abc123
2. Token is 48-hour, single-use, stored in invites table
3. User fills form: full_name, nickname, roll_no, gender, email, password
4. Server validates token, checks email uniqueness
5. Inserts user with is_approved=1 (invite = pre-approved)
6. Marks invite as used, auto-logs in user

## Open Invites
If email field is empty in admin invite form, an open invite is generated
(email stored as empty string). Anyone with the link can register.
The register.php only checks email match if invite.email is not empty.

## Password Reset Flow
1. User submits email to forgot-password.php
2. Token created in password_resets table (1 hour expiry)
3. Reset link logged to error log (email not sent  no mailer set up)
4. User visits /reset-password?token=X, submits new password
5. Hash updated, redirect to login

## CSRF Protection
Every POST/PUT/DELETE API request must include header:
  X-CSRF-Token: {token from meta[name="csrf-token"]}

The token is set once per session in loginUser() and stored in $_SESSION['csrf_token'].
validateCsrf() checks header X-CSRF-Token OR POST field csrf_token.
Uses hash_equals() for timing-safe comparison.

In app.php HTML head:
  <meta name="csrf-token" content="<?= getCsrfToken() ?>">

In JS (handled by api() wrapper automatically):
  headers: { 'X-CSRF-Token': getCsrf() }

## Session Security Settings
- session.cookie_httponly = 1 (no JS access)
- session.cookie_samesite = Strict
- session.cookie_secure = 1 only if APP_ENV = 'production'
- session_regenerate_id(true) on login (prevents session fixation)

## last_seen_at Updates
requireLogin() in auth.php updates users.last_seen_at via DB
every 60 seconds (tracked via $_SESSION['last_db_ping']).
Used to determine online status: last_seen_at within 5 minutes = online.
