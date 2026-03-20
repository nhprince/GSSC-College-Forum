<?php
declare(strict_types=1);
// Auth already handled by index.php front controller
$user = currentUser();

// Initials fallback
$initials = strtoupper(substr($user['nickname'] ?: $user['full_name'], 0, 1));
$initial2 = strtoupper(substr(explode(' ', $user['full_name'])[1] ?? '', 0, 1));
$initials .= $initial2;

// Notification prefs
$notif = (bool)($user['notif_enabled'] ?? true);
$sound = (bool)($user['sound_enabled'] ?? true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2032%2032%22%3E%3Crect%20width%3D%2232%22%20height%3D%2232%22%20rx%3D%227%22%20fill%3D%22%23C0000C%22%2F%3E%3Cpath%20d%3D%22M12%206h8M14%206v8l-4%209a2%202%200%200%200%201.8%203h8.4a2%202%200%200%200%201.8-3l-4-9V6%22%20stroke%3D%22white%22%20stroke-width%3D%221.8%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%20fill%3D%22none%22%2F%3E%3Ccircle%20cx%3D%2219%22%20cy%3D%2219%22%20r%3D%221.2%22%20fill%3D%22white%22%2F%3E%3Ccircle%20cx%3D%2216%22%20cy%3D%2222%22%20r%3D%220.9%22%20fill%3D%22rgba%28255%2C255%2C255%2C0.6%29%22%2F%3E%3C%2Fsvg%3E">
  <meta name="csrf-token" content="<?= getCsrfToken() ?>">
  <meta name="theme-color" content="#C0000C">
  <title>GSSC-science official</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/main.css">
  <style>
    html,body{height:100%;margin:0;padding:0;background:#EFEFEF}
    #app{display:grid;grid-template-columns:1fr;height:100dvh;overflow:hidden}
    @media(min-width:769px){#app{grid-template-columns:var(--sb-w,240px) 1fr}}
  </style>
</head>
<body>

<!-- 
     APP SHELL
 -->
<div id="app">

  <!--  SIDEBAR  -->
  <aside class="sidebar">

    <!-- Profile -->
    <a href="/profile.php?id=<?= (int)$user['id'] ?>" class="sb-profile" style="text-decoration:none;cursor:pointer" title="View your profile">
      <div class="sb-avatar">
        <?php if ($user['avatar']): ?>
          <img src="/uploads/avatars/<?= htmlspecialchars($user['avatar'], ENT_QUOTES, 'UTF-8') ?>" alt="">
        <?php else: ?>
          <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
        <?php endif; ?>
      </div>
      <div style="min-width:0">
        <div class="sb-name"><?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?></div>
        <div class="sb-meta"><?= htmlspecialchars(($user['nickname'] ?: '') . ' (' . $user['roll_no'] . ')', ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    </a>

    <!-- Nav -->
    <div class="sb-section-label">Sections</div>

    <button class="nav-btn active" data-page="chat">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      Conversation
    </button>

    <button class="nav-btn" data-page="notices">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10,9 9,9 8,9"/></svg>
      Notice Board
      <span class="nav-badge" style="display:none">0</span>
    </button>

    <button class="nav-btn" data-page="storage">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
      Storage
    </button>

    <!-- Settings + Logout -->
    <div class="sb-footer">
      <button class="settings-btn" id="btn-settings">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        Settings
      </button>
      <?php if (hasRole('moderator')): ?>
      <a href="/admin/" class="settings-btn" style="color:rgba(255,255,255,0.55);text-decoration:none">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        Admin panel
      </a>
      <?php endif; ?>
      <button class="settings-btn" id="btn-logout" style="color:rgba(255,100,100,0.8)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Log out
      </button>
    </div>
  </aside>

  <!--  MAIN CONTENT  -->
  <main class="main">

    <!-- Group Header -->
    <div class="group-header">
      <div class="hdr-identity">
        <div class="hdr-logo">
          <div class="hdr-logo-text">GSSC</div>
        </div>
        <div class="hdr-info">
          <div class="hdr-name">GSSC-science official</div>
          <div class="hdr-meta">
            <span class="online-dot"></span>
            <span id="online-count"></span> online &nbsp;&nbsp;
            <span id="member-count"></span> members
          </div>
        </div>
      </div>
      <div class="hdr-actions">
        <button class="icon-btn" id="btn-search" aria-label="Search">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </button>
        <button class="icon-btn" id="btn-members" aria-label="Members">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </button>
      </div>
    </div>

    <!-- Search bar -->
    <div class="search-bar" id="search-bar">
      <div class="search-input-wrap">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="search-input" placeholder="Search" autocomplete="off">
      </div>
      <button class="search-cancel" id="search-cancel">Cancel</button>
    </div>

    <!--  CHAT PAGE  -->
    <div class="page active" id="page-chat">
      <div class="chat-messages" id="chat-messages">
        <!-- Date divider -->
        <div class="date-divider"><span>Today</span></div>
        <!-- Skeleton messages -->
        <div class="msg-row" style="margin-bottom:8px">
          <div class="msg-avatar skeleton" style="width:30px;height:30px;border-radius:50%;flex-shrink:0"></div>
          <div style="display:flex;flex-direction:column;gap:4px;max-width:60%">
            <div class="skeleton" style="width:80px;height:12px;border-radius:6px"></div>
            <div class="skeleton" style="width:200px;height:38px;border-radius:18px 18px 18px 4px"></div>
          </div>
        </div>
        <div class="msg-row own" style="margin-bottom:8px">
          <div style="display:flex;flex-direction:column;gap:4px;max-width:60%;align-items:flex-end">
            <div class="skeleton" style="width:160px;height:38px;border-radius:18px 18px 4px 18px;background:#e8c0c0"></div>
          </div>
        </div>
        <div class="msg-row" style="margin-bottom:8px">
          <div class="msg-avatar skeleton" style="width:30px;height:30px;border-radius:50%;flex-shrink:0"></div>
          <div style="display:flex;flex-direction:column;gap:4px;max-width:60%">
            <div class="skeleton" style="width:240px;height:48px;border-radius:18px 18px 18px 4px"></div>
          </div>
        </div>
      </div>

      <!-- Scroll-to-bottom -->
      <button class="scroll-down-btn" id="scroll-down-btn" style="position:absolute">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6,9 12,15 18,9"/></svg>
      </button>

      <!-- Input bar -->
      <div class="chat-input-bar" id="chat-input-bar">
        <button class="chat-action-btn" id="chat-announce" title="Post announcement">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l19-9-9 19-2-8-8-2z"/></svg>
        </button>
        <button class="chat-action-btn" id="chat-attach" title="Attach file">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
        </button>
        <button class="chat-action-btn" id="chat-image" title="Send image">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21,15 16,10 5,21"/></svg>
        </button>
        <div class="chat-input-wrap">
          <textarea id="chat-input" rows="1" placeholder="Message"></textarea>
        </div>
        <button class="chat-send" id="chat-send">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22,2 15,22 11,13 2,9"/></svg>
        </button>
        <input type="file" id="file-input" style="display:none" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.zip">
        <input type="file" id="image-input" style="display:none" accept="image/*">
      </div>

      <!-- Chat disabled state (shown by JS when admin disables) -->
      <div class="chat-disabled-bar" id="chat-disabled-bar" style="display:none">
         Chat has been disabled by the administrator
      </div>
    </div>

    <!--  NOTICE BOARD PAGE  -->
    <div class="page" id="page-notices">
      <div class="section-content" id="notices-feed">

        <!-- Skeleton cards -->
        <?php for($i=0;$i<3;$i++): ?>
        <div class="post-card">
          <div class="pc-header">
            <div class="skeleton" style="width:36px;height:36px;border-radius:50%;flex-shrink:0"></div>
            <div style="flex:1;display:flex;flex-direction:column;gap:5px">
              <div class="skeleton" style="width:140px;height:12px;border-radius:6px"></div>
              <div class="skeleton" style="width:80px;height:10px;border-radius:6px"></div>
            </div>
          </div>
          <div class="pc-body">
            <div class="skeleton" style="width:80%;height:16px;border-radius:6px;margin-bottom:8px"></div>
            <div class="skeleton" style="width:100%;height:12px;border-radius:6px;margin-bottom:5px"></div>
            <div class="skeleton" style="width:70%;height:12px;border-radius:6px"></div>
          </div>
        </div>
        <?php endfor; ?>

      </div>

      <!-- FAB for moderators -->
      <?php if (hasRole('moderator')): ?>
      <button class="fab" id="btn-create-post" title="Create post">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      </button>
      <?php endif; ?>
    </div>

    <!--  STORAGE PAGE  -->
    <div class="page" id="page-storage">
      <div class="section-content" id="storage-list">

        <!-- Skeleton rows -->
        <?php for($i=0;$i<5;$i++): ?>
        <div class="storage-row" style="pointer-events:none">
          <div class="skeleton" style="width:40px;height:40px;border-radius:10px;flex-shrink:0"></div>
          <div style="flex:1;display:flex;flex-direction:column;gap:5px">
            <div class="skeleton" style="width:60%;height:13px;border-radius:6px"></div>
            <div class="skeleton" style="width:35%;height:10px;border-radius:6px"></div>
          </div>
          <div class="skeleton" style="width:34px;height:34px;border-radius:50%;flex-shrink:0"></div>
        </div>
        <?php endfor; ?>

      </div>

      <!-- Upload FAB -->
      <button class="fab" id="btn-upload" title="Upload file">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="16,16 12,12 8,16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
      </button>
    </div>

    <!--  MEMBERS PAGE  -->
    <div class="page" id="page-members">
      <div class="section-content">
        <div class="members-grid" id="members-grid">

          <!-- Online now strip -->
          <div class="members-col-header" id="online-now-section" style="display:none">
            <div style="grid-column:1/-1;background:var(--surface);border-radius:var(--r-lg);padding:14px 16px;box-shadow:var(--sh-sm)">
              <div style="font-size:11px;font-weight:600;color:var(--txt-3);text-transform:uppercase;letter-spacing:.07em;margin-bottom:10px;display:flex;align-items:center;gap:6px">
                <span class="online-dot"></span> Online now
              </div>
              <div id="online-members-strip" style="display:flex;flex-wrap:wrap;gap:8px"></div>
            </div>
          </div>
          <div class="members-col-header">
            <div class="gender-banner">
              <div class="gender-icon"><img src="https://cdn-icons-png.flaticon.com/512/13716/13716710.png" width="38" height="38" alt="Male" style="filter:brightness(0) invert(1);display:block"></div>
              <div class="gender-label">Male Students</div>
              <div class="gender-count" id="male-count"> members</div>
            </div>
            <div class="gender-banner">
              <div class="gender-icon"><img src="https://cdn-icons-png.flaticon.com/512/50/50581.png" width="38" height="38" alt="Female" style="filter:brightness(0) invert(1);display:block"></div>
              <div class="gender-label">Female Students</div>
              <div class="gender-count" id="female-count"> members</div>
            </div>
          </div>

          <!-- Male list -->
          <div class="member-list" id="member-list-male">
            <?php for($i=0;$i<4;$i++): ?>
            <div class="member-row" style="pointer-events:none">
              <div class="skeleton" style="width:36px;height:36px;border-radius:50%;flex-shrink:0"></div>
              <div style="flex:1;display:flex;flex-direction:column;gap:4px">
                <div class="skeleton" style="width:55%;height:12px;border-radius:6px"></div>
                <div class="skeleton" style="width:75%;height:10px;border-radius:6px"></div>
              </div>
            </div>
            <?php endfor; ?>
          </div>

          <!-- Female list -->
          <div class="member-list" id="member-list-female">
            <?php for($i=0;$i<4;$i++): ?>
            <div class="member-row" style="pointer-events:none">
              <div class="skeleton" style="width:36px;height:36px;border-radius:50%;flex-shrink:0"></div>
              <div style="flex:1;display:flex;flex-direction:column;gap:4px">
                <div class="skeleton" style="width:55%;height:12px;border-radius:6px"></div>
                <div class="skeleton" style="width:75%;height:10px;border-radius:6px"></div>
              </div>
            </div>
            <?php endfor; ?>
          </div>

        </div>
      </div>
    </div>

  </main><!-- /.main -->
</div><!-- /#app -->

<!--  BOTTOM NAV (mobile)  -->
<nav class="bottom-nav">
  <div class="bnav-inner">
    <button class="bnav-btn active" data-page="chat">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      Chat
    </button>
    <button class="bnav-btn" data-page="notices">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      Notices
      <span class="bnav-dot" style="display:none"></span>
    </button>
    <button class="bnav-btn" data-page="storage">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
      Storage
    </button>
    <button class="bnav-btn" data-page="members">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Members
    </button>
    <button class="bnav-btn" id="btn-settings-mob">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      Settings
    </button>
  </div>
</nav>

<!--  CREATE POST MODAL (moderators only)  -->
<?php if (hasRole('moderator')): ?>
<div class="modal-backdrop" id="create-post-backdrop">
  <div class="settings-modal" id="create-post-modal" style="max-width:480px;padding:20px 20px 32px">
    <div class="modal-handle"></div>
    <div style="font-family:var(--fh);font-size:16px;font-weight:600;color:var(--txt);margin-bottom:16px">New post</div>

    <!-- Type tabs -->
    <div style="display:flex;gap:6px;margin-bottom:16px">
      <button class="cp-type-btn active" data-type="announcement" style="flex:1;padding:8px 4px;border-radius:var(--r-pill);font-size:12px;font-weight:600;background:var(--red);color:#fff;border:none;cursor:pointer;transition:all .15s"> Notice</button>
      <button class="cp-type-btn" data-type="event" style="flex:1;padding:8px 4px;border-radius:var(--r-pill);font-size:12px;font-weight:600;background:var(--bg);color:var(--txt-2);border:1.5px solid var(--border);cursor:pointer;transition:all .15s"> Event</button>
      <button class="cp-type-btn" data-type="poll" style="flex:1;padding:8px 4px;border-radius:var(--r-pill);font-size:12px;font-weight:600;background:var(--bg);color:var(--txt-2);border:1.5px solid var(--border);cursor:pointer;transition:all .15s"> Poll</button>
    </div>
    <input type="hidden" id="cp-type" value="announcement">

    <!-- Title -->
    <div style="margin-bottom:12px">
      <label style="display:block;font-size:11px;font-weight:600;color:var(--txt-2);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px">Title *</label>
      <input id="cp-title" type="text" placeholder="Post title" style="width:100%;padding:10px 14px;background:var(--bg);border:1.5px solid var(--border);border-radius:var(--r-pill);font-size:14px;color:var(--txt);font-family:var(--fb);outline:none;transition:border-color .15s">
    </div>

    <!-- Body -->
    <div style="margin-bottom:12px">
      <label style="display:block;font-size:11px;font-weight:600;color:var(--txt-2);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px">Body (optional)</label>
      <textarea id="cp-body" placeholder="Additional details..." rows="3" style="width:100%;padding:10px 14px;background:var(--bg);border:1.5px solid var(--border);border-radius:14px;font-size:14px;color:var(--txt);font-family:var(--fb);resize:vertical;outline:none;transition:border-color .15s"></textarea>
    </div>

    <!-- Priority + Pin row -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
      <div>
        <label style="display:block;font-size:11px;font-weight:600;color:var(--txt-2);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px">Priority</label>
        <select id="cp-priority" style="width:100%;padding:10px 14px;background:var(--bg);border:1.5px solid var(--border);border-radius:var(--r-pill);font-size:13px;color:var(--txt);font-family:var(--fb);outline:none;appearance:none">
          <option value="general">General</option>
          <option value="info">Info</option>
          <option value="urgent">Urgent</option>
        </select>
      </div>
      <div style="display:flex;align-items:flex-end;padding-bottom:2px">
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--txt-2);cursor:pointer;user-select:none">
          <input type="checkbox" id="cp-pin" style="width:15px;height:15px;accent-color:var(--red)"> Pin this post
        </label>
      </div>
    </div>

    <!-- Announcement: image upload -->
    <div id="cp-image-wrap" style="margin-bottom:12px">
      <label style="display:block;font-size:11px;font-weight:600;color:var(--txt-2);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px">Image (optional)</label>
      <input type="file" id="cp-image" accept="image/jpeg,image/png,image/webp" style="font-size:13px;color:var(--txt-2)">
    </div>

    <!-- Event fields -->
    <div id="cp-event-wrap" style="display:none;margin-bottom:4px">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
        <div>
          <label style="display:block;font-size:11px;font-weight:600;color:var(--txt-2);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px">Date *</label>
          <input class="cp-field" type="date" id="cp-event-date" style="width:100%;padding:10px 14px;background:var(--bg);border:1.5px solid var(--border);border-radius:var(--r-pill);font-size:13px;color:var(--txt);font-family:var(--fb);outline:none">
        </div>
        <div>
          <label style="display:block;font-size:11px;font-weight:600;color:var(--txt-2);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px">Time</label>
          <input class="cp-field" type="time" id="cp-event-time" style="width:100%;padding:10px 14px;background:var(--bg);border:1.5px solid var(--border);border-radius:var(--r-pill);font-size:13px;color:var(--txt);font-family:var(--fb);outline:none">
        </div>
      </div>
      <div style="margin-bottom:12px">
        <label style="display:block;font-size:11px;font-weight:600;color:var(--txt-2);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px">Event type</label>
        <select id="cp-event-type" style="width:100%;padding:10px 14px;background:var(--bg);border:1.5px solid var(--border);border-radius:var(--r-pill);font-size:13px;color:var(--txt);font-family:var(--fb);outline:none;appearance:none">
          <option value="exam">Exam</option>
          <option value="submission">Submission</option>
          <option value="holiday">Holiday</option>
          <option value="class">Class</option>
          <option value="other">Other</option>
        </select>
      </div>
    </div>

    <!-- Poll fields -->
    <div id="cp-poll-wrap" style="display:none;margin-bottom:4px">
      <div style="margin-bottom:12px">
        <label style="display:block;font-size:11px;font-weight:600;color:var(--txt-2);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px">Options (min 2)</label>
        <div id="cp-poll-options">
          <input type="text" class="cp-poll-opt" placeholder="Option 1" style="width:100%;padding:9px 14px;background:var(--bg);border:1.5px solid var(--border);border-radius:var(--r-pill);font-size:13px;color:var(--txt);font-family:var(--fb);outline:none;margin-bottom:6px">
          <input type="text" class="cp-poll-opt" placeholder="Option 2" style="width:100%;padding:9px 14px;background:var(--bg);border:1.5px solid var(--border);border-radius:var(--r-pill);font-size:13px;color:var(--txt);font-family:var(--fb);outline:none;margin-bottom:6px">
        </div>
        <button type="button" id="cp-add-opt" style="font-size:12px;font-weight:600;color:var(--red);background:none;border:none;cursor:pointer;padding:4px 0">+ Add option</button>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:center;margin-bottom:4px">
        <label style="display:flex;align-items:center;gap:7px;font-size:13px;color:var(--txt-2);cursor:pointer;user-select:none">
          <input type="checkbox" id="cp-poll-anon" style="width:15px;height:15px;accent-color:var(--red)"> Anonymous
        </label>
        <div style="display:flex;align-items:center;gap:8px">
          <label style="font-size:11px;font-weight:600;color:var(--txt-2);text-transform:uppercase;letter-spacing:.06em;white-space:nowrap">Ends at</label>
          <input type="datetime-local" id="cp-poll-ends" style="padding:6px 10px;background:var(--bg);border:1.5px solid var(--border);border-radius:var(--r-pill);font-size:12px;color:var(--txt);font-family:var(--fb);outline:none">
        </div>
      </div>
    </div>

    <!-- Error message -->
    <div id="cp-error" style="display:none;background:var(--red-light);color:var(--red);font-size:13px;font-weight:500;padding:10px 14px;border-radius:10px;margin-bottom:12px"></div>

    <!-- Actions -->
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
      <button id="cp-cancel" style="padding:10px 20px;border-radius:var(--r-pill);font-size:13px;font-weight:600;background:var(--bg);color:var(--txt-2);border:1.5px solid var(--border);cursor:pointer">Cancel</button>
      <button id="cp-submit" style="padding:10px 24px;border-radius:var(--r-pill);font-size:13px;font-weight:600;background:var(--red);color:#fff;border:none;cursor:pointer;transition:background .15s;display:flex;align-items:center;gap:8px">
        <span id="cp-submit-text">Publish</span>
        <span id="cp-spinner" style="display:none;width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite"></span>
      </button>
    </div>
  </div>
</div>

<style>
  .cp-type-btn:focus { outline: none; }
  #cp-title:focus, #cp-body:focus, .cp-poll-opt:focus, #cp-event-date:focus, #cp-event-time:focus, #cp-event-type:focus, #cp-priority:focus, #cp-poll-ends:focus {
    border-color: var(--red) !important;
    box-shadow: 0 0 0 3px rgba(192,0,12,0.08);
  }
  @keyframes spin { to { transform: rotate(360deg); } }
</style>
<?php endif; ?>

<!--  SETTINGS MODAL  -->
<div class="modal-backdrop" id="settings-backdrop">
  <div class="settings-modal" id="settings-modal">
    <div class="modal-handle"></div>

    <!-- Main settings list -->
    <div class="settings-view open" id="sv-main">

      <div class="settings-row" data-settings-view="notif-sound">
        <div class="sr-left">
          <div class="sr-icon">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          </div>
          Notification and sound
        </div>
        <span class="sr-arrow">›</span>
      </div>

      <div class="settings-row" data-settings-view="change-email">
        <div class="sr-left">
          <div class="sr-icon">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,12 2,6"/></svg>
          </div>
          Change email
        </div>
        <span class="sr-arrow">›</span>
      </div>

      <div class="settings-row" data-settings-view="forgot-password">
        <div class="sr-left">
          <div class="sr-icon">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </div>
          Password forgotten
        </div>
        <span class="sr-arrow">›</span>
      </div>

      <div class="settings-row" data-settings-view="about">
        <div class="sr-left">
          <div class="sr-icon">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          </div>
          About us
        </div>
        <span class="sr-arrow">›</span>
      </div>

      <div class="settings-row" data-settings-view="rules">
        <div class="sr-left">
          <div class="sr-icon">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          </div>
          Rules and regulations
        </div>
        <span class="sr-arrow">›</span>
      </div>

      <div class="settings-row settings-row--danger" id="btn-delete-account">
        <div class="sr-left">
          <div class="sr-icon">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3,6 5,6 21,6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
          </div>
          Delete account
        </div>
      </div>

      <!-- Logout (visible in settings modal, useful on mobile) -->
      <div class="settings-row" id="btn-logout-modal" style="margin-top:8px;background:#2a2a2a">
        <div class="sr-left">
          <div class="sr-icon">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          </div>
          Log out
        </div>
      </div>

    </div>

    <!-- Notification & Sound sub-view -->
    <div class="settings-view" id="sv-notif-sound">
      <div class="sv-header">
        <button class="sv-back"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15,18 9,12 15,6"/></svg></button>
        <div class="sv-title">Notification and sound</div>
      </div>
      <div class="toggle-row">
        <span class="toggle-label">Notification</span>
        <label class="toggle">
          <input type="checkbox" id="toggle-notif" <?= $notif ? 'checked' : '' ?>>
          <span class="t-slider"></span>
        </label>
      </div>
      <div class="toggle-row">
        <span class="toggle-label">Sound</span>
        <label class="toggle">
          <input type="checkbox" id="toggle-sound" <?= $sound ? 'checked' : '' ?>>
          <span class="t-slider"></span>
        </label>
      </div>
    </div>

    <!-- Change email sub-view -->
    <div class="settings-view" id="sv-change-email">
      <div class="sv-header">
        <button class="sv-back"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15,18 9,12 15,6"/></svg></button>
        <div class="sv-title">Change email</div>
      </div>
      <p style="font-size:13px;color:var(--txt-2);margin-bottom:16px">
        Current: <strong><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></strong>
      </p>
      <div style="display:flex;flex-direction:column;gap:10px">
        <input type="email" class="form-input" id="new-email" placeholder="New email address">
        <input type="email" class="form-input" id="confirm-email" placeholder="Confirm new email">
        <input type="password" class="form-input" id="email-password" placeholder="Current password">
        <button class="btn-red" id="btn-change-email">Save changes</button>
      </div>
    </div>

    <!-- Forgot password sub-view -->
    <div class="settings-view" id="sv-forgot-password">
      <div class="sv-header">
        <button class="sv-back"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15,18 9,12 15,6"/></svg></button>
        <div class="sv-title">Reset password</div>
      </div>
      <p style="font-size:13px;color:var(--txt-2);margin-bottom:16px">
        A reset link will be sent to your registered email address.
      </p>
      <button class="btn-red" id="btn-send-reset">Send reset link</button>
    </div>

    <!-- About us sub-view -->
    <div class="settings-view" id="sv-about">
      <div class="sv-header">
        <button class="sv-back"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15,18 9,12 15,6"/></svg></button>
        <div class="sv-title">About</div>
      </div>

      <!-- App info -->
      <div style="text-align:center;padding:8px 0 20px">
        <div style="width:56px;height:56px;border-radius:16px;background:var(--red);margin:0 auto 12px;display:flex;align-items:center;justify-content:center">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3h6m-4 0v6l-3 7a1.5 1.5 0 0 0 1.4 2h5.2a1.5 1.5 0 0 0 1.4-2l-3-7V3"/><circle cx="14.5" cy="15" r="1" fill="white" stroke="none"/></svg>
        </div>
        <div style="font-family:var(--fh);font-size:16px;font-weight:700;color:var(--txt)">GSSC Science Official</div>
        <div style="font-size:12px;color:var(--txt-3);margin-top:3px">Class XI Science · Govt. Shaheed Suhrawardy College</div>
      </div>

      <p style="font-size:13.5px;color:var(--txt-2);line-height:1.7;margin-bottom:20px">
        <?= nl2br(htmlspecialchars(getSetting('about_us', 'The official communication platform for the Science Department of Govt. Shaheed Suhrawardy College (GSSC), Dhaka.'), ENT_QUOTES, 'UTF-8')) ?>
      </p>

      <!-- Divider -->
      <div style="height:1px;background:var(--border);margin:4px 0 20px"></div>

      <!-- Credits -->
      <div style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--txt-3);margin-bottom:12px">Built by</div>

      <!-- Developer card — NH Prince -->
      <div style="background:var(--dark-row);border-radius:var(--r-lg);padding:16px;margin-bottom:10px;display:flex;align-items:center;gap:14px">
        <div style="width:46px;height:46px;border-radius:50%;background:var(--red);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-family:var(--fh);font-size:15px;font-weight:700;color:#fff;border:2px solid rgba(255,255,255,.18)">N</div>
        <div style="flex:1;min-width:0">
          <div style="font-family:var(--fh);font-size:14px;font-weight:700;color:#fff">NH Prince Pradhan</div>
          <div style="font-size:11px;color:rgba(255,255,255,.55);margin-top:2px">Developer &amp; Designer · Roll 1116 · Class XI</div>
          <div style="font-size:11px;color:rgba(255,255,255,.4);margin-top:1px">Full-Stack Developer · Narayanganj, Dhaka</div>
        </div>
        <a href="https://nhprince.dpdns.org" target="_blank" style="width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .15s" title="Portfolio">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.7)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15,3 21,3 21,9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        </a>
      </div>

      <!-- Contributor card — MD Bijoy -->
      <div style="background:var(--dark-row);border-radius:var(--r-lg);padding:16px;margin-bottom:20px;display:flex;align-items:center;gap:14px">
        <div style="width:46px;height:46px;border-radius:50%;background:#1B6FD8;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-family:var(--fh);font-size:15px;font-weight:700;color:#fff;border:2px solid rgba(255,255,255,.18)">B</div>
        <div style="flex:1;min-width:0">
          <div style="font-family:var(--fh);font-size:14px;font-weight:700;color:#fff">MD Bijoy A</div>
          <div style="font-size:11px;color:rgba(255,255,255,.55);margin-top:2px">Contributor · Idea &amp; Enhancements</div>
          <div style="font-size:11px;color:rgba(255,255,255,.4);margin-top:1px">Suggested key features &amp; improvements</div>
        </div>
        <div style="width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.5)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        </div>
      </div>

      <!-- Tech stack -->
      <div style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--txt-3);margin-bottom:10px">Tech stack</div>
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:20px">
        <?php foreach(['PHP','MySQL','JavaScript','HTML/CSS'] as $t): ?>
        <span style="background:var(--dark-row);color:rgba(255,255,255,.65);font-size:11px;font-weight:600;padding:4px 10px;border-radius:var(--r-pill)"><?= $t ?></span>
        <?php endforeach; ?>
      </div>

      <div style="text-align:center;font-size:11px;color:var(--txt-3)">
        &copy; <?= date('Y') ?> GSSC Science Official &middot; Class XI
      </div>
    </div>

    <!-- Rules sub-view -->
    <div class="settings-view" id="sv-rules">
      <div class="sv-header">
        <button class="sv-back"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15,18 9,12 15,6"/></svg></button>
        <div class="sv-title">Rules and regulations</div>
      </div>
      <p style="font-size:14px;color:var(--txt-2);line-height:1.7">
        <?= nl2br(htmlspecialchars(getSetting('rules', '1. Be respectful to all members.
2. No spam or irrelevant content.
3. Only share academic materials.
4. Follow college guidelines.'), ENT_QUOTES, 'UTF-8')) ?>
      </p>
    </div>

  </div><!-- /.settings-modal -->
</div><!-- /.modal-backdrop -->

<!--  Toast container  -->
<div id="toast-container"></div>

<!--  Inline styles for form elements in settings  -->
<style>
.form-input{
  width:100%;padding:12px 16px;
  background:var(--dark-row);color:var(--txt-inv);
  border-radius:var(--r-pill);
  font-size:14px;
  border:1px solid rgba(255,255,255,0.08);
  transition:border-color .15s;
}
.form-input:focus{border-color:rgba(255,255,255,0.3)}
.form-input::placeholder{color:rgba(255,255,255,0.4)}
.btn-red{
  width:100%;padding:13px;
  background:var(--red);color:#fff;
  border-radius:var(--r-pill);
  font-size:14px;font-weight:600;
  transition:background .15s;
  font-family:var(--fb);
}
.btn-red:hover{background:var(--red-dark)}
</style>

<!--  Scripts  -->
<script src="/assets/js/app.js"></script>
<script src="/assets/js/chat.js"></script>
<script src="/assets/js/notices.js"></script>
<script src="/assets/js/storage.js"></script>
<script src="/assets/js/members.js"></script>

<script>
// Server-side user data for JS modules
const CURRENT_USER = {
  id:       <?= (int)$user['id'] ?>,
  name:     <?= json_encode($user['full_name']) ?>,
  nickname: <?= json_encode($user['nickname'] ?: $user['full_name']) ?>,
  roll:     <?= json_encode($user['roll_no']) ?>,
  avatar:   <?= json_encode($user['avatar']) ?>,
  role:     <?= json_encode($user['role']) ?>,
};
// doLogout is defined in app.js (dialog system included there)
</script>

</body>
</html>