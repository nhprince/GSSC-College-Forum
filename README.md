# GSSC College Forum

A lightweight PHP + MySQL web application for a college department community.

It provides:

- Group chat (text + image/file attachments)
- Notice board (announcements, events, polls)
- Shared storage (files with optional moderator approval)
- Member directory and profiles
- Admin/moderator panel (settings, moderation, activity log)

> This repository is intentionally framework-free: plain PHP, vanilla JS, and MySQL.

---

## Tech Stack

- **Backend:** PHP (procedural, no framework)
- **Database:** MySQL 5.7+ / MariaDB 10.4+
- **Frontend:** Vanilla JavaScript + CSS

---

## Project Structure

```
.
├─ index.php                 # Front controller/router (?page=...)
├─ app.php                   # Main app shell UI (chat/notices/storage/members)
├─ admin/                    # Admin panel (server-rendered pages)
├─ api/                      # JSON endpoints used by frontend JS
├─ includes/                 # Core helpers (DB/auth/utilities/upload)
├─ assets/                   # CSS/JS
├─ uploads/                  # User uploads (created at runtime)
├─ schema.sql                # Database schema + seed hints
└─ config.php                # App/DB configuration
```

---

## Requirements

- PHP **8.1+** recommended
- MySQL **5.7+** or MariaDB **10.4+**
- PHP extensions:
  - `pdo_mysql`
  - `mbstring`
  - `openssl`
  - `fileinfo`
  - `gd` (optional; used for avatar resizing if enabled)

---

## Setup

### 1) Clone

```bash
git clone git@github.com:nhprince/GSSC-College-Forum.git
cd GSSC-College-Forum
```

### 2) Create the database

- Create a database (example: `gssc_forum`)
- Import `schema.sql`:

```bash
mysql -u root -p gssc_forum < schema.sql
```

### 3) Configure the app

Edit `config.php`:

- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `APP_URL` (used in reset links)
- Set `APP_ENV` to `production` on the server

---

## Running Locally

You can use PHP’s built-in server for development:

```bash
php -S localhost:8000
```

Then open:

- App: `http://localhost:8000/`
- Admin: `http://localhost:8000/?page=admin`

---

## Roles

Users have one of the following roles:

- `student`
- `moderator`
- `admin`

Admin features include platform settings and member management.
Moderator features include content moderation.

---

## Features

### Chat

- Text messages
- Image/file upload messages
- Reactions (emoji)
- Admin controls to enable/disable chat globally

### Notice Board

- `announcement` posts (optional image)
- `event` posts (date/time/type)
- `poll` posts (options, anonymous toggle, optional end time)
- Pin/unpin and soft delete (moderator)

### Storage

- Upload academic files (type/size restrictions)
- Optional approval workflow (`storage_approval_required`)

### Settings (Admin)

Stored in `site_settings`, including:

- `site_name`, `college_name`, `about_us`, `rules`
- `registration_mode` (`invite` / `open` / `closed`)
- `chat_enabled`
- `storage_approval_required`
- `maintenance_mode`

---

## API Overview

Endpoints live under `api/` and return JSON:

- `api/chat/messages.php`
- `api/chat/react.php`
- `api/posts/index.php`
- `api/posts/read.php`, `api/posts/pin.php`, `api/posts/delete.php`
- `api/members/update.php`

Most state-changing requests require:

- An authenticated session
- `X-CSRF-Token` header (the token is exposed via a `<meta name="csrf-token">` tag)

---

## Security Notes

- Use **HTTPS** in production.
- Set `APP_ENV` to `production` to disable error display.
- Ensure the web server **does not execute scripts** inside the `uploads/` directory.
- Review and adjust upload allowlists/limits in `config.php`.

---

## Deployment

This app is compatible with typical LAMP/LEMP hosting.

- Point your web root to this project directory.
- Ensure the web server user can write to:
  - `uploads/`
  - `logs/` (if you enable file logging)

---

## Contributing

1. Create a feature branch
2. Make changes
3. Open a pull request

---

## License

Specify a license for this project (MIT/Apache-2.0/etc.).

---

## Credits

Built for the Science Department community of Govt. Shaheed Suhrawardy College (GSSC).
