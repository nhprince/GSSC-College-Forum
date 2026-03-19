# Notice Board

## Post Types
All posts are published as "GSSC-science official" (college identity).
The posted_by field stores which admin/mod created it, but is never shown to students.

- announcement: title + optional body + optional image
- event: title + body + event_date + event_time + event_type
- poll: title + linked polls/poll_options/poll_votes tables

Any post can be pinned (is_pinned=1). Pinned posts sort first.

## Feed Order
ORDER BY is_pinned DESC, created_at DESC

## Read Receipts
Auto-marked after 2.5 seconds of the post being visible.
INSERT IGNORE into post_reads (post_id, user_id).
Moderators see read_count on each post card.

## Poll Voting
One vote per user per poll (UNIQUE KEY poll_id + user_id).
After voting, results shown as progress bars with percentages.
Moderators can close polls early.
Anonymous polls: vote counts shown but not who voted.

## Creating Posts
Moderator+ only. Via admin panel (/admin/noticeboard.php) or
the FAB (+) button on the notice board page (visible to moderators).
Supports image upload for announcements (max 5MB, jpg/png/webp).
