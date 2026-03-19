# AI Agent Guide

READ THIS FIRST before writing any code for this project.

---

## Project Summary

GSSC Science Official Portal  private group platform for science students
at Govt. Shaheed Suhrawardy College. PHP 8.x + MySQL + Vanilla JS on cPanel.

---

## CRITICAL SERVER RULES (violations will break the site)

### 1. ASCII ONLY  No Unicode characters anywhere
The cPanel server fails to parse files with non-ASCII characters.
This includes em-dashes, box-drawing chars, curly quotes, etc.
Use plain ASCII only in ALL files. Use -- not em-dash, use === not box chars.
After writing any file, verify it contains no characters with ord() > 127.

### 2. NEVER use ob_start(), ob_clean(), ob_end_clean() in ANY file
The server has its own output buffering. Calling these functions wipes
the response buffer and returns 0 bytes to the client.
See BUGS.md Bug #6 and Bug #7.

### 3. NEVER use api_init.php
The file exists at includes/api_init.php but is broken on this server.
Do not require_once it. Do not reference it.

### 4. The .htaccess MUST have this rule before the file-check rule
```apache
RewriteRule ^api/ - [L]
```
Without this, API files are routed through index.php and return HTML.
See BUGS.md Bug #8.

### 5. NEVER use JSON_OBJECTAGG, JSON_ARRAYAGG, or MySQL 5.7.22+ functions
The server runs an older MySQL. Build JSON aggregations in PHP instead.
See BUGS.md Bug #4.

### 6. config.php must always have display_errors = 0
```php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
```
If display_errors is on, PHP warnings corrupt JSON responses.
See BUGS.md Bug #5.

---

## Standard PHP API File Template

Every file in /api/**/*.php must follow this exact pattern:

```php
<?php
declare(strict_types=1);
require_once '../../config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
initSession();
requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}
validateCsrf();

// ... logic here

jsonSuccess(['key' => $value]);
```

Notes:
- require paths are always '../../' because api files are 2 levels deep
- initSession() must come before requireLogin()
- header() must come before any output
- Always use jsonSuccess() and jsonError()  never echo json_encode() directly
- Both jsonSuccess() and jsonError() call exit internally

---

## Database Rules

### Always use PDO prepared statements
```php
// RIGHT
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

// WRONG - never do this
$pdo->query("SELECT * FROM users WHERE id = $id");
```

### Never interpolate user input into SQL
Even for integers, always use ? placeholders.

### Subquery with dynamic user ID
```php
// RIGHT - inject via params array
$stmt = $pdo->prepare("SELECT *, (SELECT 1 FROM reads WHERE post_id = p.id AND user_id = ?) FROM posts p");
$stmt->execute([$userId]);

// WRONG - direct interpolation even for ints
"user_id = {$userId}"
```

### Never hard-delete messages
```php
// RIGHT - soft delete
$pdo->prepare("UPDATE messages SET is_deleted = 1 WHERE id = ?")->execute([$id]);

// WRONG
$pdo->prepare("DELETE FROM messages WHERE id = ?")->execute([$id]);
```

### Always check execute() is called
After prepare(), always call execute(). Missing execute() returns no rows
silently with no error.

---

## Helper Functions Reference (includes/functions.php)

```php
jsonSuccess(array $data = [], int $status = 200): never
jsonError(string $msg, string $code = 'ERROR', int $status = 400): never
getJsonBody(): array          // parse JSON request body
getCsrfToken(): string        // get/generate CSRF token
validateCsrf(): void          // validate token, calls jsonError on fail
getSetting(string $key, string $default = ''): string
updateSetting(string $key, string $value): void
logAction(string $action, ?string $targetType, ?int $targetId, array $meta): void
checkRateLimit(string $id, string $action, int $max, int $windowSec): bool
validateRequired(array $data, array $fields): array
validateEnum(mixed $val, array $allowed): bool
validateLength(string $val, int $min, int $max): bool
sanitizeHtml(string $html): string
timeAgo(string $datetime): string
formatBytes(int $bytes): string
escHtml(string $s): string    // htmlspecialchars wrapper
```

## Auth Functions Reference (includes/auth.php)

```php
initSession(): void           // must be called first in every file
requireLogin(): void          // redirects to login if not authenticated
requireRole(string $minRole): void  // 403 if insufficient role
hasRole(string $minRole): bool
currentUser(): array          // returns session user data
loginUser(array $user): void
logout(): void
```

---

## Role System

Hierarchy (lowest to highest): student -> moderator -> admin

```php
requireLogin();           // any authenticated user
requireRole('moderator'); // moderator OR admin
requireRole('admin');     // admin only

hasRole('moderator')      // true if moderator or admin
```

First registered user is manually set to admin via SQL.
Only admins can promote students to moderators.
Never assign 'admin' role through the UI  DB only.

---

## Frontend JS Rules

### Never use innerHTML for user data
```javascript
// RIGHT
el.textContent = userData;

// WRONG
el.innerHTML = userData;

// OK only for trusted server-sanitized HTML
el.innerHTML = sanitizedHtml;
```

### Always use the api() wrapper
```javascript
// RIGHT
const data = await api('posts/index.php?page=1');
await api('posts/read.php', { method:'POST', body: JSON.stringify({post_id: 5}) });

// WRONG
fetch('/api/posts/index.php')  // misses CSRF header
```

### Error states must replace skeletons
When a fetch fails, always replace the skeleton with an error state:
```javascript
async function fetch() {
    const el = document.getElementById('my-feed');
    el.innerHTML = skeletonHTML();
    try {
        const data = await api('...');
        el.innerHTML = '';
        // render data
    } catch(err) {
        el.innerHTML = `<div class="empty-state">
            <div class="empty-title">Could not load</div>
            <div class="empty-sub">${err.message}</div>
        </div>`;
    }
}
```

### Page init guard
Each JS module has a _loaded flag to prevent double-loading:
```javascript
load() {
    if (this._loaded) return;
    this._loaded = true;
    this.fetch();
}
```
To force reload: set `this._loaded = false` then call `this.load()`.

---

## CSS Rules

### No Unicode in CSS
Same as PHP/JS  no Unicode characters anywhere in main.css.

### No @import in CSS
Fonts are loaded via <link> tags in app.php HTML head.
Never add @import url() to main.css.

### CSS variables are in :root
All colors, spacing, fonts defined in :root block at top of main.css.
Use var(--red), var(--bg), var(--surface), etc. everywhere.

### Primary colors
```css
--red:       #C0000C;  /* GSSC brand red */
--red-dark:  #8B0000;
--red-light: #FFF0F0;
--bg:        #EFEFEF;  /* page background */
--surface:   #FFFFFF;  /* card/modal background */
```

---

## File Naming Conventions

| Type | Convention | Example |
|---|---|---|
| PHP pages | lowercase.php | login.php, profile.php |
| PHP API | lowercase.php | messages.php, upload.php |
| PHP includes | lowercase.php | db.php, functions.php |
| CSS | lowercase.css | main.css |
| JS | lowercase.js | app.js, chat.js |
| Docs | UPPERCASE.md | README.md, BUGS.md |

---

## Adding a New API Endpoint

1. Create file at /api/{section}/action.php
2. Use the standard template (see above)
3. Add the endpoint to docs/API.md
4. If it needs a DB change, update docs/DATABASE.md and schema.sql
5. Test: check network tab shows JSON response with Content-Type: application/json
6. Test: verify all 3 roles (student, moderator, admin)
7. Test: verify 403 returned when role is insufficient
8. Test: verify CSRF fails with wrong token on POST requests

---

## Adding a New Frontend Page/Section

1. Add a new .page div in app.php with id="page-{name}"
2. Add nav button in sidebar with data-page="{name}"
3. Add bnav button in bottom nav with data-page="{name}"
4. Add goTo case in app.js goTo() function
5. Create {name}.js with load(), fetch(), renderRow() pattern
6. Add <script src="/assets/js/{name}.js"> in app.php
7. Mobile: add to bottom nav (max 5 items)

---

## Common Mistakes to Avoid

| Mistake | Correct approach |
|---|---|
| Unicode in files | ASCII only |
| @import in CSS | Use <link> in HTML |
| ob_start/ob_clean | Never use these |
| require api_init.php | Never require it |
| JSON_OBJECTAGG in SQL | Build in PHP with fetchAll() |
| display_errors on | Always off in config.php |
| Direct SQL interpolation | Always use ? placeholders |
| Missing execute() after prepare() | Always verify execute() is called |
| innerHTML with user data | Use textContent |
| Silent error in catch | Show empty-state error to user |
| Removing RewriteRule ^api/ | Never remove this line |
