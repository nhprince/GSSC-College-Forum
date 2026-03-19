# Storage

## Overview
File sharing section. Students upload, moderators approve, all can download.

## Upload Flow
1. Student clicks FAB (+) button
2. File picker opens (browser native)
3. Prompted for file title
4. POST to /api/storage/upload.php as multipart
5. handleUpload() validates: size, extension, MIME type
6. Stored with random hex filename in uploads/storage/
7. Original filename saved in file_name column for display
8. if storage_approval_required=1: is_approved=0 (pending)
9. Moderators skip approval (is_approved=1 immediately)

## Approval Flow
Pending files visible in /admin/storage.php under "Pending" tab.
Moderator can: Preview (download), Approve, Reject.
On approve: is_approved=1, file appears for all students.
On reject: file deleted from filesystem and DB.

## Download Flow
GET /api/storage/download.php?id=X
- Auth check (must be logged in)
- File must be approved OR uploaded by current user
- Increments download_count
- Streams file with Content-Disposition: attachment
- Uses original file_name for download filename

## Allowed File Types
pdf, doc, docx, ppt, pptx, jpg, jpeg, png, zip
Max size: 10MB (UPLOAD_MAX_SIZE in config.php)

## Categories
notes, syllabus, assignment, slides, result, other
