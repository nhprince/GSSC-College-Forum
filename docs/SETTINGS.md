# Settings Modal

Opened by gear icon in sidebar or Settings button in mobile bottom nav.
Slides up as a bottom sheet from the modal-backdrop overlay.

## Options (from PDF design)

1. Notification and sound
   -> Sub-view with 2 toggles
   -> Notification toggle (green=on) saves to users.notif_enabled
   -> Sound toggle saves to users.sound_enabled
   -> POST /api/settings/notifications.php

2. Change email
   -> Form: new email, confirm email, current password
   -> POST /api/settings/change-email.php
   -> Requires password verification

3. Password forgotten
   -> Sends reset link to registered email
   -> POST /api/auth/forgot-password.php (NOTE: mailer not set up)
   -> Reset link is logged to /logs/php_errors.log instead of email
   -> To set up real email: implement PHPMailer in includes/mailer.php

4. About us
   -> Read-only text from site_settings key 'about_us'
   -> Editable by admin in /admin/settings.php

5. Rules and regulations
   -> Read-only text from site_settings key 'rules'
   -> Editable by admin in /admin/settings.php

6. Delete account (red)
   -> Requires password confirmation
   -> Sets is_active=0 (soft delete, data preserved)
   -> POST /api/settings/delete-account.php
   -> Redirects to login

7. Log out (grey, below delete)
   -> POST /api/auth/logout.php
   -> Redirects to login

## Email / Password Reset Note
PHPMailer is NOT set up. Password reset links are logged to error log.
To enable email: install PHPMailer via composer, configure SMTP in includes/mailer.php,
call it from forgot-password.php instead of error_log().
