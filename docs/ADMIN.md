# Admin Panel

Access: /admin/  requires moderator or admin role.
Layout: sidebar + topbar + content area.
Sidebar items marked (admin only) require admin role.

## Pages

### /admin/  Dashboard
- Stats: total members, total posts, messages today, pending files
- Chat status card with on/off toggle (admin only)
- Quick action buttons
- INVITE GENERATOR (admin only):
  - Enter email (optional) or leave blank for open invite
  - Click "Generate invite link"
  - Link appears on screen  copy and share with student
  - Link is valid 48 hours, single use
  - Recent invites table shows status of past invites

### /admin/noticeboard.php  Notice Board
- Table of all posts: type, title, priority, read count, date
- Pin/Unpin button per post
- Delete button per post
- "New post" button opens create modal:
  - Type tabs: Announcement / Event / Poll
  - Announcement: title, body, priority, pin, optional image
  - Event: title, body, date, time, event type, priority, pin
  - Poll: title, body, options (2-6), anonymous toggle, end date

### /admin/chat.php  Chat Moderation
- Chat enabled/disabled status + toggle (admin only)
- Table of recent 100 messages
- Delete button per message
- "Clear all" button (admin only, requires typing CONFIRM)

### /admin/storage.php  Storage
- Tab 1 Pending: files awaiting approval
  - Preview (download link), Approve, Reject per file
- Tab 2 All files: all uploaded files
  - Download link, Delete per file

### /admin/members.php  Members (admin only)
- Table with all users: name, email, roll, role, status, last seen
- Filters: search, role, status
- Actions: Approve (if pending), Promote/Demote role, Ban/Unban
- Cannot act on own account

### /admin/settings.php  Settings (admin only)
- Site name, college name
- About us and Rules text (shown in Settings modal)
- Registration mode: invite / open / closed
- Chat enabled toggle
- File approval required toggle
- Maintenance mode toggle

### /admin/activity.php  Activity Log
- Last 200 entries from activity_log table
- Shows: user, action, target, IP, time

## Shared Layout
admin/includes/layout.php  HTML head, sidebar, topbar (opened)
admin/includes/layout_end.php  closing divs, toast container, admin JS
Every admin page: require layout.php at top, require layout_end.php at bottom.
Set $pageTitle and $activePage variables before requiring layout.php.
