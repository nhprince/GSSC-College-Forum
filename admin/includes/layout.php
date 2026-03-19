<?php
// Called at top of every admin page
// $pageTitle must be set before including this file
$pageTitle = $pageTitle ?? 'Admin Panel';
$activePage = $activePage ?? '';
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta name="csrf-token" content="<?= getCsrfToken() ?>">
  <title><?= escHtml($pageTitle) ?>  Admin  GSSC</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root{--red:#C0000C;--red-dark:#8B0000;--red-lt:#FFF0F0;--bg:#F2F2F2;--surface:#FFF;--dark-row:#2E2E2E;--txt:#111;--txt-2:#555;--txt-3:#999;--border:rgba(0,0,0,0.09);--fh:'Poppins',sans-serif;--fb:'DM Sans',sans-serif;--r-sm:8px;--r-md:12px;--r-lg:18px;--r-pill:999px;--sb-w:220px;--sh-sm:0 1px 4px rgba(0,0,0,.07);--sh-md:0 4px 16px rgba(0,0,0,.10)}
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    html,body{height:100%;font-family:var(--fb);font-size:14px}
    body{background:var(--bg);display:flex;height:100dvh;overflow:hidden}
    a{color:inherit;text-decoration:none}
    button{font-family:var(--fb);cursor:pointer;border:none;background:none}
    /* Sidebar */
    .a-sidebar{width:var(--sb-w);background:var(--red-dark);display:flex;flex-direction:column;padding:16px 10px;gap:2px;overflow-y:auto;flex-shrink:0}
    .a-sidebar::-webkit-scrollbar{display:none}
    .a-logo{padding:8px 8px 16px;border-bottom:1px solid rgba(255,255,255,.12);margin-bottom:8px}
    .a-logo-title{font-family:var(--fh);font-size:13px;font-weight:700;color:#fff}
    .a-logo-sub{font-size:10px;color:rgba(255,255,255,.5);margin-top:2px}
    .a-section-lbl{font-size:9px;font-weight:600;letter-spacing:.09em;text-transform:uppercase;color:rgba(255,255,255,.35);padding:6px 8px 2px;margin-top:4px}
    .a-nav{display:flex;align-items:center;gap:8px;padding:9px 10px;border-radius:var(--r-pill);font-size:13px;font-weight:500;color:rgba(255,255,255,.7);transition:background .18s,color .18s;position:relative}
    .a-nav svg{flex-shrink:0;opacity:.65;transition:opacity .18s}
    .a-nav:hover{background:rgba(255,255,255,.1);color:#fff}
    .a-nav:hover svg{opacity:1}
    .a-nav.active{background:rgba(255,255,255,.18);color:#fff;font-weight:600}
    .a-nav.active svg{opacity:1}
    .a-badge{margin-left:auto;min-width:18px;height:18px;background:#fff;color:var(--red-dark);border-radius:var(--r-pill);font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;padding:0 5px}
    .a-footer{margin-top:auto;padding-top:12px;border-top:1px solid rgba(255,255,255,.1)}
    .a-back{display:flex;align-items:center;gap:8px;padding:9px 10px;border-radius:var(--r-pill);font-size:12px;color:rgba(255,255,255,.55);transition:color .18s;width:100%}
    .a-back:hover{color:#fff}
    /* Main */
    .a-main{flex:1;display:flex;flex-direction:column;overflow:hidden}
    .a-topbar{background:var(--surface);padding:12px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;box-shadow:var(--sh-sm)}
    .a-page-title{font-family:var(--fh);font-size:17px;font-weight:600;color:var(--txt)}
    .a-user{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--txt-2)}
    .a-avatar{width:30px;height:30px;border-radius:50%;background:var(--red);display:flex;align-items:center;justify-content:center;font-family:var(--fh);font-size:11px;font-weight:700;color:#fff;overflow:hidden}
    .a-avatar img{width:100%;height:100%;object-fit:cover}
    .a-content{flex:1;overflow-y:auto;padding:20px;scrollbar-width:thin;scrollbar-color:rgba(0,0,0,.1) transparent}
    .a-content::-webkit-scrollbar{width:4px}
    .a-content::-webkit-scrollbar-thumb{background:rgba(0,0,0,.1);border-radius:4px}
    /* Cards */
    .a-card{background:var(--surface);border-radius:var(--r-lg);box-shadow:var(--sh-sm);padding:20px;margin-bottom:16px}
    .a-card-title{font-family:var(--fh);font-size:15px;font-weight:600;color:var(--txt);margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;gap:10px}
    /* Stats grid */
    .stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin-bottom:20px}
    .stat-box{background:var(--surface);border-radius:var(--r-md);padding:16px;box-shadow:var(--sh-sm);text-align:center}
    .stat-box-num{font-family:var(--fh);font-size:26px;font-weight:700;color:var(--red)}
    .stat-box-lbl{font-size:11px;color:var(--txt-3);margin-top:2px;font-weight:500}
    /* Table */
    .a-table{width:100%;border-collapse:collapse;font-size:13px}
    .a-table th{text-align:left;padding:8px 12px;font-size:10.5px;font-weight:600;color:var(--txt-3);text-transform:uppercase;letter-spacing:.06em;border-bottom:1.5px solid var(--border);white-space:nowrap}
    .a-table td{padding:10px 12px;border-bottom:1px solid var(--border);color:var(--txt);vertical-align:middle}
    .a-table tr:last-child td{border-bottom:none}
    .a-table tr:hover td{background:var(--bg)}
    /* Buttons */
    .btn-sm{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:var(--r-pill);font-size:12px;font-weight:600;cursor:pointer;transition:all .15s;border:none}
    .btn-red{background:var(--red);color:#fff}.btn-red:hover{background:var(--red-dark)}
    .btn-ghost{background:var(--bg);color:var(--txt-2);border:1px solid var(--border)}.btn-ghost:hover{background:var(--border);color:var(--txt)}
    .btn-green{background:#16a34a;color:#fff}.btn-green:hover{background:#15803d}
    .btn-primary{background:var(--red);color:#fff;padding:9px 18px;border-radius:var(--r-pill);font-family:var(--fh);font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;transition:background .15s}
    .btn-primary:hover{background:var(--red-dark)}
    /* Badges */
    .badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:var(--r-pill);font-size:10.5px;font-weight:600}
    .badge-red{background:var(--red-lt);color:var(--red)}
    .badge-green{background:#DCFCE7;color:#15803d}
    .badge-blue{background:#EEF4FF;color:#1B6FD8}
    .badge-grey{background:var(--bg);color:var(--txt-3);border:1px solid var(--border)}
    .badge-orange{background:#FEF3C7;color:#92400E}
    /* Form */
    .a-input{width:100%;padding:9px 14px;background:var(--bg);border:1.5px solid var(--border);border-radius:var(--r-pill);font-size:13px;color:var(--txt);font-family:var(--fb);transition:border-color .18s}
    .a-input:focus{outline:none;border-color:var(--red)}
    .a-input::placeholder{color:var(--txt-3)}
    .a-label{display:block;font-size:11px;font-weight:600;color:var(--txt-2);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px}
    .a-form-group{margin-bottom:14px}
    .a-textarea{width:100%;padding:10px 14px;background:var(--bg);border:1.5px solid var(--border);border-radius:var(--r-md);font-size:13px;color:var(--txt);font-family:var(--fb);resize:vertical;min-height:80px;transition:border-color .18s}
    .a-textarea:focus{outline:none;border-color:var(--red)}
    .a-select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23999' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6,9 12,15 18,9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center}
    /* Toggle */
    .a-toggle{position:relative;width:44px;height:24px;flex-shrink:0}
    .a-toggle input{opacity:0;width:0;height:0;position:absolute}
    .a-tslider{position:absolute;inset:0;border-radius:var(--r-pill);background:var(--border);cursor:pointer;transition:background .2s}
    .a-tslider::before{content:'';position:absolute;width:16px;height:16px;left:4px;top:4px;border-radius:50%;background:#fff;transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.2)}
    .a-toggle input:checked+.a-tslider{background:var(--red)}
    .a-toggle input:checked+.a-tslider::before{transform:translateX(20px)}
    /* Toast */
    #toast-container{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);z-index:999;display:flex;flex-direction:column;align-items:center;gap:8px;pointer-events:none}
    .toast{background:#222;color:#fff;padding:9px 20px;border-radius:var(--r-pill);font-size:13px;font-weight:500;box-shadow:var(--sh-md);animation:ti .25s ease,to .3s ease 3.5s forwards;white-space:nowrap}
    .toast--success{background:#16a34a}.toast--error{background:var(--red)}.toast--warn{background:#D97706}
    @keyframes ti{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
    @keyframes to{from{opacity:1}to{opacity:0}}
    /* Search input */
    .search-wrap{display:flex;align-items:center;gap:6px;background:var(--bg);border:1.5px solid var(--border);border-radius:var(--r-pill);padding:0 12px;max-width:260px}
    .search-wrap svg{color:var(--txt-3);flex-shrink:0}
    .search-wrap input{flex:1;padding:8px 0;font-size:13px;background:transparent;color:var(--txt);font-family:var(--fb)}
    .search-wrap input::placeholder{color:var(--txt-3)}
    .search-wrap:focus-within{border-color:var(--red)}
  </style>
</head>
<body>

<!-- Sidebar -->
<aside class="a-sidebar">
  <div class="a-logo">
    <div class="a-logo-title">GSSC Admin</div>
    <div class="a-logo-sub">Science Department</div>
  </div>

  <div class="a-section-lbl">Content</div>
  <a class="a-nav <?= $activePage==='dashboard'?'active':'' ?>" href="/admin/">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    Dashboard
  </a>
  <a class="a-nav <?= $activePage==='noticeboard'?'active':'' ?>" href="/admin/noticeboard.php">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
    Notice Board
  </a>
  <a class="a-nav <?= $activePage==='chat'?'active':'' ?>" href="/admin/chat.php">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    Chat
  </a>
  <a class="a-nav <?= $activePage==='storage'?'active':'' ?>" href="/admin/storage.php">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
    Storage
    <?php
    $pend = (int)(getDB()->query("SELECT COUNT(*) FROM storage_files WHERE is_approved=0")->fetchColumn());
    if ($pend): ?><span class="a-badge"><?= $pend ?></span><?php endif; ?>
  </a>
  <a class="a-nav <?= $activePage==='activity'?'active':'' ?>" href="/admin/activity.php">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22,12 18,12 15,21 9,3 6,12 2,12"/></svg>
    Activity Log
  </a>

  <?php if (hasRole('admin')): ?>
  <div class="a-section-lbl">Admin Only</div>
  <a class="a-nav <?= $activePage==='members'?'active':'' ?>" href="/admin/members.php">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    Members
  </a>
  <a class="a-nav <?= $activePage==='settings'?'active':'' ?>" href="/admin/settings.php">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
    Settings
  </a>
  <?php endif; ?>

  <div class="a-footer">
    <a class="a-back" href="/">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15,18 9,12 15,6"/></svg>
      Back to site
    </a>
  </div>
</aside>

<!-- Main -->
<div class="a-main">
  <div class="a-topbar">
    <span class="a-page-title"><?= escHtml($pageTitle) ?></span>
    <div class="a-user">
      <div class="a-avatar">
        <?php if ($user['avatar']): ?>
          <img src="/uploads/avatars/<?= escHtml($user['avatar']) ?>" alt="">
        <?php else: echo strtoupper(substr($user['full_name'],0,1)); endif; ?>
      </div>
      <?= escHtml($user['nickname'] ?: $user['full_name']) ?>  <span style="color:var(--red);font-weight:600"><?= escHtml($user['role']) ?></span>
    </div>
  </div>
  <div class="a-content">
<!--  PAGE CONTENT STARTS  -->
