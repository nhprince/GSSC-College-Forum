# GSSC Science Official Portal  Documentation

## Project Identity
- **Platform name:** GSSC-science official
- **College:** Govt. Shaheed Suhrawardy College, Science Department
- **Live URL:** https://gssc.stuckstudio.com
- **Stack:** PHP 8.x + MySQL + Vanilla JS
- **Hosting:** Shared cPanel (gssc.stuckstudio.com)

## Documentation Index

| File | What it covers |
|---|---|
| README.md | This file |
| ARCHITECTURE.md | Folder structure, routing, how pages load |
| DATABASE.md | Full MySQL schema, all tables explained |
| AUTH.md | Login, sessions, roles, CSRF |
| API.md | Every API endpoint  URL, method, request, response |
| FRONTEND.md | Design system, CSS variables, components, mobile rules |
| CHAT.md | Real-time chat  SSE + polling fallback |
| NOTICEBOARD.md | Post types, polls, events, read receipts |
| STORAGE.md | File upload, download, approval flow |
| MEMBERS.md | Directory, profiles, online status |
| SETTINGS.md | Settings modal, notifications, account management |
| ADMIN.md | Admin panel pages and controls |
| ROLES.md | Role hierarchy and permission matrix |
| SECURITY.md | Security rules, validation, file upload safety |
| DEPLOYMENT.md | cPanel setup, .htaccess, first admin setup |
| BUGS.md | Known issues, fixes applied, debugging notes |
| AI_AGENT_GUIDE.md | Rules for AI agents writing code on this project |

## Quick Facts

```
Language:     PHP 8.2+
Database:     MySQL via PDO (prepared statements only)
Frontend:     HTML5 + CSS3 + Vanilla JS (no framework, no build step)
Auth:         PHP sessions + CSRF tokens
Real-time:    Server-Sent Events (SSE) with 3s AJAX polling fallback
Uploads:      Local server storage under /uploads/
Routing:      .htaccess mod_rewrite -> index.php front controller
API path:     /api/**/*.php (served directly, NOT routed through index.php)
Colors:       Red #C0000C + White (GSSC brand)
Fonts:        Poppins (headings) + DM Sans (body) via Google Fonts
```

## Navigation Structure

```
App has 3 main sections + 1 overlay + 1 modal:

Sidebar / Bottom Nav:
  Conversation  -> /app.php (page-chat)
  Notice Board  -> /app.php (page-notices)
  Storage       -> /app.php (page-storage)

Group header icons:
  Search (magnifier) -> search bar slides down
  Members (people)   -> goes to page-members

Sidebar bottom:
  Settings  -> opens settings modal overlay
  Admin panel -> /admin/ (moderator+ only)
  Log out   -> POST /api/auth/logout.php
```

## File Count Summary

| Area | Files |
|---|---|
| Root pages | 8 (index.php, app.php, login.php, register.php, etc.) |
| Includes (shared PHP) | 5 (db, auth, functions, uploader, api_init) |
| API endpoints | 27 files across 7 folders |
| Admin panel | 7 pages + 2 shared layout files |
| Frontend assets | 6 JS + 1 CSS |
| Config/Schema | 2 (config.php, schema.sql) |
| **Total** | **~60 files** |
