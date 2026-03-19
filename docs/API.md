# API Reference

All endpoints: /api/**/*.php
All accept and return JSON.
All require authenticated session (except auth endpoints).
All POST/PUT/DELETE require X-CSRF-Token header.

Standard response format:
  Success: {"success": true, "data": {...}}
  Error:   {"success": false, "error": "message", "code": "ERROR_CODE"}

---

## Auth

### POST /api/auth/logout.php
Destroy session and redirect.
Response: redirects to /login.php

---

## Chat

### GET /api/chat/messages.php
Fetch message history.
Params: ?limit=50, ?before_id=X (older), ?since_id=X (newer, for polling)
Response: {messages:[...], chat_enabled:bool, last_id:int}
Message shape: {id, body, type, file_path, file_name, reply_to_id,
                is_deleted, created_at, user:{id,full_name,nickname,avatar},
                reactions:{emoji:count}}

### POST /api/chat/messages.php
Send a message. CSRF required.
Body (JSON): {body:string, type:'text', reply_to_id:int|null}
Body (multipart): file field + optional reply_to_id
Response: {message:{...full message object}}
Rate limit: 2 per 2 seconds per user.

### GET /api/chat/stream.php
Server-Sent Events. Streams new messages in real-time.
Params: ?last_id=X
Events: 'message' (new message JSON), 'reconnect' (client should reconnect)
Falls back to polling /api/chat/messages.php?since_id=X every 3 seconds.

### POST /api/chat/react.php
Toggle emoji reaction. CSRF required.
Body: {message_id:int, emoji:string}
Response: {reactions:{emoji:count}, toggled:emoji, added:bool}

### POST /api/chat/delete.php
Soft delete a message. Own message or moderator+. CSRF required.
Body: {message_id:int}
Response: {message_id:int}

### POST /api/chat/toggle.php
Enable or disable chat. Admin only. CSRF required.
Body: {enabled:bool}
Response: {chat_enabled:bool}

---

## Posts (Notice Board)

### GET /api/posts/index.php
Paginated post feed.
Params: ?page=1&limit=20&type=announcement|event|poll&search=text
Response: {posts:[...], total:int, page:int, limit:int}
Post shape: {id, post_type, title, body, image_path, priority, is_pinned,
             event_date, event_time, event_type, read:bool, read_count:int,
             created_at, poll:{id,is_closed,is_anonymous,ends_at,options:[],
             total_votes,user_voted_option_id}}

### POST /api/posts/index.php
Create a post. Moderator+. CSRF required. Multipart form.
Fields: post_type, title, body(opt), priority, is_pinned(opt),
        image(file,opt), event_date, event_time, event_type,
        poll_options(JSON array), poll_anon, poll_ends_at
Response: {id:int}

### POST /api/posts/read.php
Mark a post as read. CSRF required.
Body: {post_id:int}

### POST /api/posts/pin.php
Pin/unpin a post. Moderator+. CSRF required.
Body: {post_id:int, pinned:bool}
Response: {is_pinned:bool}

### POST /api/posts/delete.php
Soft delete a post. Moderator+. CSRF required.
Body: {post_id:int}

### GET /api/posts/unread.php
Get badge counts.
Response: {unread_posts:int, pending_storage:int}

---

## Polls

### POST /api/polls/vote.php
Cast a vote. CSRF required.
Body: {poll_id:int, option_id:int}
Response: {options:[{id,text,votes}], total_votes:int, user_voted_option_id:int}
Error ALREADY_VOTED (409) if already voted.
Error POLL_CLOSED (409) if poll is closed.

---

## Storage

### GET /api/storage/index.php
List files. Shows approved files + own pending files.
Params: ?category=notes|syllabus|assignment|slides|result|other&search=text&page=1
Response: {files:[{id,title,description,file_type,file_size,category,
           is_approved,download_count,created_at,uploaded_by:{...}}], total:int}

### POST /api/storage/upload.php
Upload a file. CSRF required. Multipart form.
Fields: title, description(opt), category, file(binary)
Max size: 10MB. Allowed: pdf,doc,docx,ppt,pptx,jpg,jpeg,png,zip
Response: {id:int, pending_approval:bool, message:string}

### GET /api/storage/download.php?id=X
Secure file download. Increments download_count. Streams file.

### GET /api/storage/pending.php (implicit via index with is_approved=0)

### POST /api/storage/approve.php
Approve, reject, or delete a file. Moderator+. CSRF required.
Body: {file_id:int, action:'approve'|'reject'|'delete'}
Response: {message:string}

---

## Members

### GET /api/members/index.php
Member directory split by gender.
Params: ?search=text, ?counts_only=1 (lightweight, returns just counts)
Response: {male:[...], female:[...], other:[...], online_count:int, total:int}
Member shape: {id, full_name, nickname, roll_no, gender, avatar, role,
               is_online:bool, last_seen:datetime}

### GET /api/members/profile.php?id=X
Single member profile.
Response: {member:{...plus post_count, storage_count}}

### POST /api/members/update.php
Update own profile. CSRF required. Multipart form.
Fields: full_name(opt), nickname(opt), gender(opt), avatar(file,opt)
Response: {message:string}

---

## Settings

### POST /api/settings/notifications.php
Save notification preferences. CSRF required.
Body: {notif_enabled:bool, sound_enabled:bool}

### POST /api/settings/change-email.php
Change email. CSRF required.
Body: {new_email, confirm_email, current_password}
Error EMAIL_TAKEN (409) if taken.
Error WRONG_PASSWORD (403) if wrong password.

### POST /api/settings/delete-account.php
Soft delete own account. CSRF required.
Body: {password:string}
Redirects to /login.php on success.

---

## Admin

### GET /api/admin/settings.php
Get all site settings. Admin only.
Response: flat object of all key-value pairs.

### POST /api/admin/settings.php
Update site settings. Admin only. CSRF required.
Body: object with any subset of setting keys.
Allowed keys: site_name, college_name, about_us, rules,
              registration_mode, chat_enabled, storage_approval_required,
              maintenance_mode

### POST /api/admin/invites.php
Generate invite link. Admin only. CSRF required.
Body: {email:string}  empty string or 'open@invite' for open invite.
Response: {message, invite_link, token, expires_in}

### POST /api/admin/members.php
Manage a user account. Admin only. CSRF required.
Body: {user_id:int, action:'approve'|'deactivate'|'activate'|'ban'}
Cannot act on own account.

### POST /api/admin/roles.php
Change user role. Admin only. CSRF required.
Body: {user_id:int, role:'student'|'moderator'}
Cannot change own role. Cannot assign 'admin' role.

### POST /api/admin/clear-chat.php
Delete all messages. Admin only. CSRF required.
Body: {confirm:true}

---

## Error Codes Reference

| Code | Meaning |
|---|---|
| AUTH_REQUIRED | Not logged in |
| FORBIDDEN | Insufficient role |
| INVALID_CSRF | CSRF token mismatch |
| VALIDATION_ERROR | Input failed validation |
| NOT_FOUND | Resource not found |
| RATE_LIMITED | Too many requests |
| CHAT_DISABLED | Chat turned off by admin |
| ALREADY_VOTED | User already voted |
| POLL_CLOSED | Poll no longer accepting votes |
| INVITE_INVALID | Token expired/used/not found |
| FILE_TOO_LARGE | Upload exceeds 10MB |
| FILE_TYPE_DENIED | Extension not allowed |
| WRONG_PASSWORD | Password verification failed |
| EMAIL_TAKEN | Email already registered |
| METHOD_NOT_ALLOWED | Wrong HTTP method |
| DB_ERROR | Database query failed |
| UPLOAD_ERROR | File upload failed |
| EXCEPTION | Unhandled PHP exception |
| FATAL | PHP fatal error |
