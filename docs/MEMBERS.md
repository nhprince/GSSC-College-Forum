# Members Directory

## Layout
Split into male and female columns (based on users.gender field).
Each column shows: avatar, nickname (roll_no), full_name.
Online members (last_seen within 5 min) shown first with green ring.
Clicking a member opens their profile (/profile.php?id=X).

## Online Status
Online = last_seen_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
Updated every 60 seconds via requireLogin() in auth.php.
Shown as green ring around avatar in directory and sidebar strip.

## Sidebar Online Strip
Shows up to 8 online member avatars in the sidebar.
Updated when Members.load() is called and via refreshOnlineCount() every 30s.

## Profile Page (/profile.php?id=X)
Shows: avatar, full name, roll number, role badge, joined date.
Own profile: shows Edit Profile button.
Edit profile: change full_name, nickname, gender, avatar (max 2MB jpg/png/webp).
Avatar auto-cropped to 200x200 server-side (via handleUpload).

## Search
Search bar filters by full_name, nickname, or roll_no.
Both columns filtered simultaneously.
