# Deployment Guide

## Server Details
- Host: cPanel shared hosting
- Domain: gssc.stuckstudio.com
- Document root: public_html/
- PHP: 8.x
- MySQL: older version (no JSON_OBJECTAGG)
- File manager: cPanel File Manager
- DB manager: phpMyAdmin

---

## Initial Setup (one-time)

### Step 1  Upload files
Upload all project files to public_html/ maintaining the folder structure.
Make sure .htaccess is uploaded (it may be hidden  enable "Show hidden files").

### Step 2  Create database
1. cPanel -> MySQL Databases
2. Create database: e.g. yourusername_gssc
3. Create user with strong password
4. Add user to database with All Privileges

### Step 3  Configure
Edit config.php with your actual values:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'yourusername_gssc');
define('DB_USER', 'yourusername_dbuser');
define('DB_PASS', 'your_strong_password');
define('APP_URL', 'https://gssc.stuckstudio.com');
```

### Step 4  Import schema
phpMyAdmin -> select your database -> Import -> upload schema.sql

### Step 5  Create first admin user
Generate password hash (run in cPanel Terminal or locally):
```
php -r "echo password_hash('YourPassword', PASSWORD_BCRYPT, ['cost'=>12]);"
```
Then in phpMyAdmin SQL tab:
```sql
INSERT INTO users (full_name, nickname, roll_no, email, password_hash, gender, role, is_active, is_approved)
VALUES ('Your Name', 'Admin', '0000', 'your@email.com', 'PASTE_HASH_HERE', 'male', 'admin', 1, 1);
```

### Step 6  Set permissions
```
uploads/      -> 755
uploads/*/    -> 755
logs/         -> 755
```

### Step 7  Test
1. Visit https://gssc.stuckstudio.com -> see login page
2. Log in -> reach app with chat
3. Visit /admin -> reach admin dashboard
4. Generate invite link -> share with a classmate
5. Classmate registers -> appears in Members

---

## Updating Files

For small updates (1-5 files): use cPanel File Manager to upload and overwrite.
For large updates: zip locally, upload, extract in File Manager.

Always hard refresh after updating JS/CSS: Ctrl+Shift+R (or Cmd+Shift+R on Mac).

---

## Critical .htaccess Rules

The file at public_html/.htaccess must contain:

```apache
# This rule MUST come before the file-check rule
RewriteRule ^api/ - [L]
```

Without this, all API requests are routed through index.php and return HTML.
See BUGS.md Bug #8 for full explanation.

---

## SSL / HTTPS

SSL is already set up (https://gssc.stuckstudio.com).
To force HTTPS, uncomment in .htaccess:
```apache
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## Common Issues

| Symptom | Likely cause | Fix |
|---|---|---|
| Black background, no styles | Unicode chars in CSS | Re-upload clean main.css |
| "Could not load" on all pages | display_errors on + PHP warning | Check config.php display_errors=0 |
| 0 bytes in network tab | .htaccess routing API through index.php | Check RewriteRule ^api/ - [L] exists |
| "Server error (invalid response)" | PHP warning before JSON | Check config.php, check error log |
| Skeleton never resolves | API response empty or invalid JSON | Open network tab, click request, check Response tab |
| Login redirect loop | Session not starting | Check session.cookie_secure on HTTP |
| File upload fails | Wrong permissions on uploads/ | chmod 755 uploads/ and subdirs |
| Chat not updating | SSE being killed by server | Falls back to 3s polling automatically |

---

## Error Logs

PHP errors: public_html/logs/php_errors.log
Apache errors: cPanel -> Metrics -> Errors
