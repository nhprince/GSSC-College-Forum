# Roles & Permissions

## Role Hierarchy
student < moderator < admin

Checked via hasRole($minRole) which compares numeric levels:
  student=1, moderator=2, admin=3

## Permission Matrix

| Feature | Student | Moderator | Admin |
|---|---|---|---|
| View chat | YES | YES | YES |
| Send messages | YES | YES | YES |
| Delete own message | YES | YES | YES |
| Delete any message | NO | YES | YES |
| Toggle chat on/off | NO | NO | YES |
| Clear all messages | NO | NO | YES |
| View notices | YES | YES | YES |
| Create post | NO | YES | YES |
| Pin/delete post | NO | YES | YES |
| View read receipts | NO | YES | YES |
| Vote in poll | YES | YES | YES |
| Close poll | NO | YES | YES |
| Upload files | YES | YES | YES |
| Download files | YES | YES | YES |
| Approve files | NO | YES | YES |
| View members | YES | YES | YES |
| Edit own profile | YES | YES | YES |
| Access admin panel | NO | YES | YES |
| View activity log | NO | YES | YES |
| Manage members | NO | NO | YES |
| Change user roles | NO | NO | YES |
| Send invites | NO | NO | YES |
| Change site settings | NO | NO | YES |

## Role Assignment Rules
1. First user inserted manually via SQL with role='admin'
2. New registrations default to role='student'
3. Only admins can promote students to moderators
4. Only admins can demote moderators to students
5. 'admin' role can only be assigned via direct SQL (no UI)
6. Admins cannot change their own role (prevents lockout)

## Banned / Pending Users
- is_active=0: Cannot log in. Login query requires is_active=1.
- is_approved=0: Cannot log in. Login query requires is_approved=1.
- Banned users' content (messages, files) is preserved.
- Admin can reactivate from /admin/members.php.
