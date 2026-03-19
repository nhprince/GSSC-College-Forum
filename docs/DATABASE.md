# Database Schema

Engine: MySQL (older version  no JSON_OBJECTAGG support)
Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
Connection: PDO with prepared statements only (see includes/db.php)

Import schema.sql in phpMyAdmin to create all tables.

---

## Tables

### users
Primary user table. First user must be manually inserted as admin.

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| full_name | VARCHAR(100) | |
| nickname | VARCHAR(50) | Display name, shown as "nickname (roll)" |
| roll_no | VARCHAR(30) UNIQUE | College roll number |
| email | VARCHAR(150) UNIQUE | |
| password_hash | VARCHAR(255) | bcrypt cost 12 |
| gender | ENUM('male','female','other') | Determines Members directory column |
| avatar | VARCHAR(255) | Relative path from uploads/ e.g. avatars/abc.jpg |
| role | ENUM('student','moderator','admin') | Default: student |
| is_active | TINYINT(1) | 0 = banned. Check on login. |
| is_approved | TINYINT(1) | 0 = pending. Check on login. |
| notif_enabled | TINYINT(1) | User notification preference |
| sound_enabled | TINYINT(1) | User sound preference |
| last_seen_at | DATETIME | Updated every 60s via requireLogin() |
| created_at | DATETIME | |
| updated_at | DATETIME ON UPDATE | |

### invites
Invite tokens for registration. Tokens expire after 48 hours.
Email field is empty string for open invites (anyone can use).

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | |
| email | VARCHAR(150) | Empty string = open invite |
| token | VARCHAR(64) UNIQUE | bin2hex(random_bytes(32)) |
| invited_by | INT UNSIGNED FK users.id | |
| used | TINYINT(1) | 1 = already registered |
| expires_at | DATETIME | NOW() + 48 hours |
| created_at | DATETIME | |

### password_resets
One-time password reset tokens. Expire after 1 hour.

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | |
| email | VARCHAR(150) | |
| token | VARCHAR(64) UNIQUE | |
| expires_at | DATETIME | |
| used | TINYINT(1) | |
| created_at | DATETIME | |

### posts
All notice board content. post_type controls rendering.
Events use event_date/event_time. Polls link to polls table.

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | |
| post_type | ENUM('announcement','event','poll') | |
| title | VARCHAR(200) | |
| body | TEXT | Optional body text |
| image_path | VARCHAR(255) | Optional: relative from uploads/ |
| priority | ENUM('urgent','info','general') | |
| is_pinned | TINYINT(1) | Pinned posts sort first |
| is_published | TINYINT(1) | 0 = soft deleted |
| posted_by | INT UNSIGNED FK users.id | Never shown to students |
| event_date | DATE | Null unless post_type = event |
| event_time | TIME | Optional |
| event_type | ENUM('exam','submission','holiday','class','other') | |
| created_at | DATETIME | |
| updated_at | DATETIME ON UPDATE | |

### post_reads
Read receipts. One row per post per user.

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | |
| post_id | INT UNSIGNED FK posts.id CASCADE | |
| user_id | INT UNSIGNED FK users.id CASCADE | |
| read_at | DATETIME | |

UNIQUE KEY on (post_id, user_id)  use INSERT IGNORE to mark read.

### polls
One row per poll post. Linked to posts via post_id.

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | |
| post_id | INT UNSIGNED UNIQUE FK posts.id CASCADE | 1:1 with posts |
| is_anonymous | TINYINT(1) | Hide who voted |
| ends_at | DATETIME | NULL = no expiry |
| is_closed | TINYINT(1) | Manually closed by mod |
| created_at | DATETIME | |

### poll_options
Options for each poll.

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | |
| poll_id | INT UNSIGNED FK polls.id CASCADE | |
| option_text | VARCHAR(200) | |
| display_order | TINYINT UNSIGNED | Sort order |

### poll_votes
One vote per user per poll (enforced by UNIQUE KEY).

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | |
| poll_id | INT UNSIGNED FK polls.id CASCADE | |
| option_id | INT UNSIGNED FK poll_options.id CASCADE | |
| user_id | INT UNSIGNED FK users.id CASCADE | |
| voted_at | DATETIME | |

UNIQUE KEY on (poll_id, user_id).

### messages
Group chat messages. NEVER hard delete  always soft delete (is_deleted=1).

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | |
| user_id | INT UNSIGNED FK users.id CASCADE | |
| body | TEXT | Null for file/image messages |
| type | ENUM('text','image','file') | |
| file_path | VARCHAR(255) | Relative from uploads/ |
| file_name | VARCHAR(255) | Original filename for display |
| reply_to_id | INT UNSIGNED FK messages.id SET NULL | For threading |
| is_deleted | TINYINT(1) | 1 = soft deleted. Always filter WHERE is_deleted=0 |
| created_at | DATETIME | |

### message_reactions
Emoji reactions on messages.

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | |
| message_id | INT UNSIGNED FK messages.id CASCADE | |
| user_id | INT UNSIGNED FK users.id CASCADE | |
| emoji | VARCHAR(10) | |
| created_at | DATETIME | |

UNIQUE KEY on (message_id, user_id, emoji)  toggle: insert or delete.

### storage_files
Student-uploaded files. Require moderator approval.

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | |
| title | VARCHAR(200) | Display name |
| description | TEXT | Optional |
| file_path | VARCHAR(255) | Random stored name, relative from uploads/ |
| file_name | VARCHAR(255) | Original filename |
| file_type | VARCHAR(20) | Extension e.g. pdf, docx |
| file_size | INT UNSIGNED | Bytes |
| category | ENUM('notes','syllabus','assignment','slides','result','other') | |
| uploaded_by | INT UNSIGNED FK users.id CASCADE | |
| is_approved | TINYINT(1) | 0 = pending. Uploader can see own pending files. |
| download_count | INT UNSIGNED | Incremented on each download |
| created_at | DATETIME | |

### site_settings
Key-value store for all site configuration.

| Column | Type | Notes |
|---|---|---|
| key | VARCHAR(100) PK | |
| value | TEXT | |
| updated_by | INT UNSIGNED FK users.id SET NULL | |
| updated_at | DATETIME ON UPDATE | |

Default rows seeded in schema.sql:
- chat_enabled: '1'
- registration_mode: 'invite'
- storage_approval_required: '1'
- site_name: 'GSSC-science official'
- college_name: 'Govt. Shaheed Suhrawardy College'
- department: 'Science'
- about_us: (text for Settings modal)
- rules: (text for Settings modal)
- maintenance_mode: '0'

### activity_log
Audit trail for all admin/mod actions.

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | |
| user_id | INT UNSIGNED FK users.id SET NULL | Null for system events |
| action | VARCHAR(100) | e.g. 'post.created', 'user.banned' |
| target_type | VARCHAR(50) | e.g. 'post', 'user', 'message' |
| target_id | INT UNSIGNED | ID of affected record |
| meta | JSON | Extra context as JSON |
| ip_address | VARCHAR(45) | |
| created_at | DATETIME | |

### rate_limits
Simple IP/user-based rate limiting.

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK | |
| identifier | VARCHAR(100) | IP address or 'msg_{user_id}' |
| action | VARCHAR(50) | e.g. 'login', 'chat_send' |
| attempts | SMALLINT UNSIGNED | |
| window_start | DATETIME | Reset when window expires |

UNIQUE KEY on (identifier, action).

---

## Important Query Patterns

### Feed query (notice board)
```sql
SELECT p.*,
  (SELECT 1 FROM post_reads WHERE post_id = p.id AND user_id = ? LIMIT 1) AS `read`,
  (SELECT COUNT(*) FROM post_reads WHERE post_id = p.id) AS read_count,
  po.id AS poll_id, po.is_closed AS poll_closed
FROM posts p
LEFT JOIN polls po ON po.post_id = p.id
WHERE p.is_published = 1
ORDER BY p.is_pinned DESC, p.created_at DESC
LIMIT ? OFFSET ?
```
Note: $userId goes as first ? parameter (for the subquery).

### Reactions (PHP-side, not SQL aggregate)
```php
$rStmt = $pdo->query("
    SELECT message_id, emoji, COUNT(*) AS cnt
    FROM message_reactions WHERE message_id IN ($ids)
    GROUP BY message_id, emoji
");
foreach ($rStmt->fetchAll() as $r) {
    $reactions[(int)$r['message_id']][$r['emoji']] = (int)$r['cnt'];
}
```

### Online status check
```sql
last_seen_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
```

### Members ordered by online first
```sql
ORDER BY (last_seen_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)) DESC, full_name ASC
```
