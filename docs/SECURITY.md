# Security Rules

## Non-Negotiable Rules

### 1. PDO Prepared Statements Only
Never concatenate user input into SQL. Always use ? placeholders.
Exception: IN() clauses can use array_map('intval', $ids) for integer arrays.

### 2. htmlspecialchars on all PHP output
```php
echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
// or use the helper:
echo escHtml($value);
```

### 3. textContent not innerHTML in JS
```javascript
el.textContent = userValue;  // safe
el.innerHTML = userValue;    // NEVER for user data
```

### 4. CSRF on all mutations
validateCsrf() at top of every POST/PUT/DELETE endpoint.
JS api() wrapper sends X-CSRF-Token header automatically.

### 5. Role check on every restricted endpoint
requireLogin() + requireRole('moderator') or requireRole('admin') at top.

### 6. File uploads
handleUpload() in uploader.php validates:
- PHP upload error code
- File size (max 10MB from config)
- Extension against allowlist
- MIME type via finfo (secondary check)
- Stores with random hex filename (never original name)
- Path stored relative to uploads/

uploads/.htaccess blocks PHP execution inside the uploads directory.
Never serve uploaded files with readfile() without auth check.

### 7. Password hashing
password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12])
password_verify($input, $hash) for checking.
Never compare hashes directly.
Never log or store plaintext passwords.

### 8. Error display off
config.php: ini_set('display_errors', '0') always.
Errors logged to /logs/php_errors.log only.

### 9. Rate limiting
checkRateLimit($identifier, $action, $max, $windowSeconds) in functions.php.
Used on: login (5/15min/IP), chat messages (2/2sec/user).

### 10. Input validation
validateRequired(), validateEnum(), validateLength() in functions.php.
All user input validated before touching the database.
