# Architecture

## Folder Structure

```
public_html/
|
|-- index.php                  <- Front controller (routes all page requests)
|-- app.php                    <- Main app shell (sidebar + all 4 pages)
|-- login.php                  <- Login page (handles GET + POST)
|-- register.php               <- Invite-based registration
|-- forgot-password.php        <- Password reset request
|-- reset-password.php         <- Set new password from token
|-- profile.php                <- Member profile page (?id=X)
|-- config.php                 <- DB credentials + app constants
|-- schema.sql                 <- Full MySQL schema (import once)
|-- .htaccess                  <- URL rewriting + security
|
|-- includes/                  <- Shared PHP (never accessed directly)
|   |-- db.php                 <- PDO singleton: getDB()
|   |-- auth.php               <- Session, login, role checks
|   |-- functions.php          <- jsonSuccess, jsonError, getSetting, logAction, etc.
|   |-- uploader.php           <- Secure file upload handler
|   |-- api_init.php           <- (unused - do not use, causes issues on this server)
|
|-- api/                       <- REST endpoints (JSON in/out)
|   |-- auth/
|   |   |-- logout.php
|   |-- chat/
|   |   |-- messages.php       <- GET history + POST send
|   |   |-- stream.php         <- SSE real-time endpoint
|   |   |-- react.php          <- Add/remove emoji reaction
|   |   |-- delete.php         <- Soft delete a message
|   |   |-- toggle.php         <- Admin: enable/disable chat
|   |-- posts/
|   |   |-- index.php          <- GET feed + POST create post
|   |   |-- read.php           <- Mark post as read
|   |   |-- pin.php            <- Pin/unpin post
|   |   |-- delete.php         <- Soft delete post
|   |   |-- unread.php         <- Badge counts for nav
|   |-- polls/
|   |   |-- vote.php           <- Cast a poll vote
|   |-- storage/
|   |   |-- index.php          <- List approved files
|   |   |-- upload.php         <- Upload a file
|   |   |-- download.php       <- Secure file download
|   |   |-- approve.php        <- Approve/reject/delete file
|   |-- members/
|   |   |-- index.php          <- Member directory (male/female split)
|   |   |-- profile.php        <- Single member profile
|   |   |-- update.php         <- Update own profile + avatar
|   |-- settings/
|   |   |-- notifications.php  <- Save notif + sound prefs
|   |   |-- change-email.php   <- Change email address
|   |   |-- delete-account.php <- Soft delete own account
|   |-- admin/
|       |-- invites.php        <- Generate invite links
|       |-- members.php        <- Approve/ban/activate users
|       |-- roles.php          <- Change user role
|       |-- settings.php       <- Read/write site settings
|       |-- clear-chat.php     <- Wipe all messages
|
|-- admin/                     <- Admin panel pages (moderator+ only)
|   |-- index.php              <- Dashboard + invite generator
|   |-- noticeboard.php        <- Create/manage posts
|   |-- chat.php               <- Chat moderation
|   |-- storage.php            <- Approve/reject files
|   |-- members.php            <- Manage users (admin only)
|   |-- settings.php           <- Site settings (admin only)
|   |-- activity.php           <- Audit log
|   |-- includes/
|       |-- layout.php         <- Admin sidebar + topbar HTML
|       |-- layout_end.php     <- Closing tags + admin JS
|
|-- assets/
|   |-- css/
|   |   |-- main.css           <- All styles (single file, no @import)
|   |-- js/
|       |-- app.js             <- Navigation, settings modal, API wrapper, logout
|       |-- chat.js            <- SSE, message render, reactions, context menu
|       |-- notices.js         <- Notice feed, poll voting, event blocks
|       |-- storage.js         <- File list, upload trigger
|       |-- members.js         <- Directory render, online strip
|
|-- pages/
|   |-- 403.php                <- Access denied page
|   |-- maintenance.php        <- Maintenance mode page
|
|-- uploads/                   <- User-uploaded files
|   |-- avatars/               <- Profile photos
|   |-- posts/                 <- Notice board images
|   |-- storage/               <- Uploaded student files
|   |-- chat/                  <- Chat file attachments
|   |-- .htaccess              <- Blocks PHP execution inside uploads
|
|-- logs/                      <- PHP error logs (auto-created)
|-- docs/                      <- This documentation folder
```

## Request Lifecycle

### Page Request
```
Browser GET /notices
  -> .htaccess: URL starts with api/? NO -> check if file exists
  -> file doesn't exist -> rewrite to index.php?page=notices
  -> index.php: initSession(), check maintenance, switch($page)
  -> case 'notices': requireLogin() -> require app.php
  -> app.php: renders full HTML shell with sidebar + all pages
  -> JS: DOMContentLoaded -> goTo('chat') by default
  -> User clicks "Notice Board" -> goTo('notices') -> Notices.load()
  -> Notices.load() -> fetch('/api/posts/index.php')
  -> Response rendered into #notices-feed
```

### API Request
```
JS fetch('/api/posts/index.php?page=1')
  -> .htaccess: URL starts with api/ -> RewriteRule ^api/ - [L] (pass directly)
  -> PHP runs api/posts/index.php
  -> initSession() -> requireLogin() -> header('Content-Type: application/json')
  -> query DB -> jsonSuccess(['posts' => [...]])
  -> JS receives JSON -> renders cards
```

## CRITICAL: .htaccess API Rule

The most important rule in .htaccess:
```apache
RewriteRule ^api/ - [L]
```
This passes ALL /api/* requests directly to PHP without rewriting.
WITHOUT this rule, API files get routed through index.php and return HTML instead of JSON.
DO NOT remove or reorder this rule.

## Routing Table (index.php)

| URL | Handler | Min role |
|---|---|---|
| / or /chat or /notices or /storage or /members | app.php | Student |
| /login | login.php | Guest |
| /register?token=X | register.php | Guest |
| /forgot-password | forgot-password.php | Guest |
| /reset-password?token=X | reset-password.php | Guest |
| /profile?id=X | profile.php | Student |
| /admin | admin/index.php | Moderator |
| /admin/noticeboard | admin/noticeboard.php | Moderator |
| /admin/chat | admin/chat.php | Moderator |
| /admin/storage | admin/storage.php | Moderator |
| /admin/members | admin/members.php | Admin |
| /admin/settings | admin/settings.php | Admin |
| /admin/activity | admin/activity.php | Moderator |
