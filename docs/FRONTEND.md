# Frontend

## Overview
Single HTML shell (app.php) with 4 page sections that show/hide via JS.
No framework. No build step. Plain CSS + Vanilla JS.
Mobile-first responsive. GSSC red/white brand colors.

---

## Design System

### Fonts (loaded via <link> in app.php head)
- Poppins 500/600/700  headings, group name, nav labels
- DM Sans 400/500  body text, UI

### CSS Variables (:root in main.css)
```css
--red:        #C0000C   /* GSSC brand red */
--red-dark:   #8B0000   /* darker hover state */
--red-deeper: #5C0006   /* deepest red */
--red-light:  #FFF0F0   /* red tint background */
--bg:         #EFEFEF   /* page background */
--surface:    #FFFFFF   /* cards, modals */
--dark-bar:   #181818   /* chat input bar */
--dark-row:   #2E2E2E   /* settings modal rows */
--dark-row-h: #3A3A3A   /* settings row hover */
--txt:        #111111
--txt-2:      #555555
--txt-3:      #999999
--txt-inv:    #FFFFFF
--border:     rgba(0,0,0,0.09)
--online:     #22C55E   /* green online dot */
--info:       #1B6FD8
--warn:       #D97706
--r-sm:       8px
--r-md:       14px
--r-lg:       22px
--r-pill:     999px
--sb-w:       240px     /* sidebar width */
--bnav-h:     62px      /* bottom nav height */
--fh:         'Poppins', sans-serif
--fb:         'DM Sans', sans-serif
```

### Key Component Classes
```
.sidebar           Red-dark sidebar (desktop only)
.sb-profile        User avatar + name block
.sb-avatar         44px round avatar in sidebar
.nav-btn           Red pill nav button
.nav-btn.active    Active state (brighter)
.nav-badge         White count badge on nav btn
.group-header      Red pill header (GSSC branding)
.hdr-logo          38px circle with GSSC text
.online-dot        Green pulsing dot
.section-content   Scrollable page content area
.page              Each section (display:none by default)
.page.active       Visible section (display:flex)
.bottom-nav        Mobile bottom navigation
.bnav-btn          Bottom nav icon button
.bnav-btn.active   Active bottom nav item
.modal-backdrop    Settings modal overlay
.settings-modal    Modal sheet
.settings-row      Dark pill setting option
.settings-row--danger  Red delete row
.toggle            Toggle switch
.t-slider          Toggle track
.post-card         Notice board card
.post-card--urgent Red left border
.pc-header         Post card header (logo+name+date)
.pc-logo           GSSC logo circle in post
.pc-title.unread   Bold red unread title
.poll-option--btn  Voting button
.poll-option--result  Result bar with percentage
.poll-option--voted   User's chosen option (red)
.event-block       Event date display
.storage-row       File row in storage
.file-icon         File type icon box
.member-row        Member in directory
.member-avatar     36px round avatar
.member-online-ring  Green online ring
.gender-banner     Male/female header card
.chat-messages     Scrollable message area
.msg-row           Message container
.msg-row.own       Right-aligned own message
.msg-bubble        Message bubble
.chat-input-bar    Dark bottom input area
.reaction-bar      Emoji reactions below message
.reaction-pill     Single emoji reaction button
.ctx-menu          Right-click context menu
.empty-state       Empty/error placeholder
.skeleton          Loading shimmer animation
.fab               Floating action button (red circle)
.toast             Notification toast
```

---

## Layout

### Desktop (>=769px)
```
+--sidebar(240px)--+--main------------------+
| profile          | group-header (red pill) |
| nav buttons      | search bar              |
| online strip     | page content (active)   |
| settings btn     |                         |
| admin panel btn  |                         |
| logout btn       |                         |
+------------------+-------------------------+
```

### Mobile (<768px)
```
+-------------------------------+
| group-header (red pill)       |
| search bar                    |
| page content                  |
+-------------------------------+
| bottom nav (5 icons)          |
| Chat | Notices | Storage |    |
| Members | Settings            |
+-------------------------------+
```

---

## JavaScript Modules

### app.js
- getCsrf()  get CSRF token from meta tag
- api(endpoint, options)  fetch wrapper with CSRF header
- showToast(msg, type)  show notification
- goTo(pageId)  switch visible page section
- openSettings() / closeSettings()  settings modal
- showView(name)  switch settings sub-view
- doLogout()  POST logout, redirect to login
- refreshBadges()  update unread count on nav (every 20s)
- refreshOnlineCount()  update header counts (every 30s)

### chat.js (Chat object)
- Chat.init()  called by goTo('chat'), runs once
- Chat.loadHistory()  fetch last 50 messages
- Chat.connectSSE()  open EventSource stream
- Chat.startPoll()  3s fallback polling
- Chat.appendMsg(msg)  add message to DOM
- Chat.renderMsg(msg)  build message HTML
- Chat.toggleReact(msgId, emoji)  toggle reaction
- Chat.setReply(msgId, text)  set reply context
- Chat.submit()  send text message
- Chat.sendFile(file)  upload file/image

### notices.js (Notices object)
- Notices.load()  called by goTo('notices'), once
- Notices.fetch(replace)  fetch and render posts
- Notices.vote(pollId, optionId, cardEl)  cast poll vote
- Notices.renderCard(post)  build post card HTML

### storage.js (Storage_ object)
- Storage_.load()  called by goTo('storage'), once
- Storage_.fetch()  fetch and render file list
- Storage_.openUpload()  trigger file picker

### members.js (Members object)
- Members.load()  called by goTo('members'), once
- Members.fetch()  fetch and render male/female lists
- Members.updateOnlineStrip(members)  update sidebar strip

---

## Mobile Rules
1. All layouts default to single column
2. Sidebar hidden on mobile, bottom nav shown
3. Settings opens as bottom sheet
4. All tap targets min 44x44px
5. Font size min 14px
6. FAB positioned above bottom nav: bottom: calc(var(--bnav-h) + 12px)
7. Toast positioned above bottom nav
8. group-header margin: 7px (less on mobile)
