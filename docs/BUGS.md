# Bugs, Fixes & Debugging Notes

This file documents every issue encountered during development and deployment,
and the fixes applied. Critical reading for AI agents working on this project.

---

## Bug #1  Unicode characters breaking CSS and PHP files
**Symptom:** Black background, unstyled sidebar, CSS variables not resolving.
**Root cause:** All files contained Unicode em-dashes () and box-drawing
characters (===) in comments. The cPanel server's encoding configuration
caused CSS variables to fail parsing entirely.
**Fix:** Strip all non-ASCII characters from every .php, .js, and .css file.
**Prevention:** NEVER use Unicode characters in any project file. Use only
plain ASCII in all code and comments. Use -- instead of em-dash, === instead
of box-drawing, etc.
**Files affected:** All 60 files.

---

## Bug #2  Google Fonts @import in CSS breaking stylesheet
**Symptom:** Fonts not loading, CSS partially broken on some connections.
**Root cause:** @import url() at top of CSS file was being blocked or causing
parse failure on the server.
**Fix:** Removed @import from main.css entirely. Fonts now loaded via
<link> tags in app.php HTML head.
**Prevention:** Never use @import in CSS on shared cPanel hosting. Always
use <link> tags in HTML.

---

## Bug #3  api/chat/messages.php fatal error (csrf.php missing)
**Symptom:** "Could not load messages" toast, chat page stuck on skeleton.
**Root cause:** The file had require_once '../../includes/csrf.php' but
that file does not exist. CSRF functions live in functions.php.
**Fix:** Removed the bad require. CSRF validation uses validateCsrf() from
functions.php.
**Prevention:** There is no csrf.php. validateCsrf() is in functions.php.

---

## Bug #4  JSON_OBJECTAGG MySQL compatibility
**Symptom:** Messages API returning 500 error on older MySQL versions.
**Root cause:** JSON_OBJECTAGG() requires MySQL 5.7.22+. The cPanel server
runs an older version.
**Fix:** Replaced with a separate PHP-side query that builds the reactions
array manually using GROUP BY.
**Prevention:** Never use JSON_OBJECTAGG(), JSON_ARRAYAGG(), or other
MySQL 5.7.22+ JSON aggregate functions. Build JSON in PHP instead.

---

## Bug #5  PHP display_errors corrupting JSON responses
**Symptom:** "Server error (invalid response)" in browser. Network tab
showed response starting with PHP warning text before the JSON.
**Root cause:** config.php had APP_ENV = 'development' which set
display_errors = 1. PHP warnings were printed before JSON output,
making the response unparseable.
**Fix:** config.php now always sets display_errors = 0 and
display_startup_errors = 0 regardless of environment.
**Prevention:** Always set display_errors = 0 on live server. Errors go
to the log file only: /logs/php_errors.log.

---

## Bug #6  ob_clean() causing 0 bytes response
**Symptom:** Network tab showed "0 B transferred" and "Provisional headers
are shown". All API responses were empty.
**Root cause:** Added ob_clean() to jsonSuccess() and jsonError() to clear
PHP warning output before JSON. But the cPanel server has its own output
buffering layer active (ob_get_level() > 0). Calling ob_clean() inside
cPanel's buffer wiped the entire response including the JSON.
**Fix:** Removed all ob_clean() calls. The display_errors = 0 fix (Bug #5)
prevents warnings from appearing, so ob_clean() is unnecessary.
**Prevention:** Never call ob_clean(), ob_end_clean(), or ob_start() in
API files on this server. The cPanel hosting has its own buffering.
The api_init.php file was created for this purpose and should NOT be used.

---

## Bug #7  api_init.php breaking all API responses
**Symptom:** Every API endpoint returning 0 bytes or empty response.
**Root cause:** api_init.php called ob_start() which conflicted with
cPanel's buffering (same root cause as Bug #6).
**Fix:** Reverted all API files from using api_init.php back to the
original 3 separate require_once statements.
**Prevention:** DO NOT USE includes/api_init.php. It is incompatible with
this server. The file exists on disk but should be ignored.

---

## Bug #8  .htaccess routing API files through index.php
**Symptom:** "Provisional headers are shown", 0 B transferred. Network
showed requests being made but receiving no response at all.
**Root cause:** The .htaccess RewriteCond %{REQUEST_FILENAME} -f check
was failing for subdirectory PHP files on this cPanel server configuration.
API files were being routed to index.php which returned HTML instead of JSON.
The JS fetch() received HTML, failed to parse as JSON, and threw
"Server error (invalid response)".
**Fix:** Added explicit rule before the file-check condition:
  RewriteRule ^api/ - [L]
This bypasses all rewriting for any URL starting with /api/ and serves
the PHP file directly.
**Prevention:** This rule must always be present and must come BEFORE the
general file-check condition. Do not remove or reorder it.

---

## Bug #9  posts/index.php SQL injection and missing execute()
**Symptom:** Notice board skeleton never resolving.
**Root cause 1:** $userId was interpolated directly into SQL string:
  "user_id = {$userId}"  while safe (it's an int), it bypasses
  the PDO prepared statement system.
**Root cause 2:** $stmt->execute($params) was missing after building the
query with array_splice(). The query was prepared but never executed,
returning no rows.
**Fix:** Used array_splice() to insert $userId into the params array at
the correct position, then called $stmt->execute($params) properly.
**Prevention:** Never interpolate variables into SQL strings. Always use
? placeholders and pass values via execute(). Always verify execute() is
called after prepare().

---

## Bug #10  jsonSuccess/jsonError missing header() call
**Symptom:** API responses occasionally missing Content-Type header.
**Root cause:** Early versions of jsonSuccess/jsonError did not call
header('Content-Type: application/json') internally.
**Fix:** Both functions now always set the Content-Type header.
**Prevention:** Both functions in functions.php are self-contained 
they set the status code, set the header, encode, and exit. Do not
call header() manually before calling these functions.

---

## Debugging Checklist

When an API endpoint returns unexpected results:

1. Open browser DevTools -> Network tab
2. Click the failing request
3. Check "Response" tab:
   - If empty/0 bytes: .htaccess routing issue (Bug #8)
   - If starts with "Warning:" or "Notice:": display_errors not off (Bug #5)
   - If HTML: API file is being routed through index.php (Bug #8)
   - If valid JSON with success:false: read the error and code fields
4. Check Response Headers:
   - "Provisional headers are shown" = request blocked before server responds
   - No Content-Type: application/json = PHP fatal error before header() ran
5. Check cPanel Error Logs: cPanel -> Metrics -> Errors
6. Check /logs/php_errors.log on server

---

## Server Environment Notes

- Host: cPanel shared hosting (gssc.stuckstudio.com)
- PHP: 8.x
- MySQL: older version (no JSON_OBJECTAGG support)
- Output buffering: cPanel has its own ob layer active at all times
- File encoding: must be pure ASCII (no Unicode)
- .htaccess: mod_rewrite enabled, but subdirectory file checks unreliable
- Sessions: PHP sessions work normally
- SSE: Server-Sent Events work but server may kill connections after ~4 min
