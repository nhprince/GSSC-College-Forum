'use strict';

const Chat = {
  lastId: 0,
  eventSource: null,
  pollTimer: null,
  replyTo: null,
  _initialized: false,
  _suppressNextClick: false,
  _lastRenderedUid: 0,  // uid of last rendered msg — used for grouping

  EMOJIS: [
    { e: '👍', label: 'Like'     },
    { e: '❤️', label: 'Love'     },
    { e: '😂', label: 'Haha'     },
    { e: '😮', label: 'Wow'      },
    { e: '😢', label: 'Sad'      },
    { e: '😡', label: 'Angry'    },
    { e: '🔥', label: 'Fire'     },
    { e: '🎉', label: 'Congrats' },
    { e: '📚', label: 'Study'    },
    { e: '🙏', label: 'Thanks'   },
  ],

  // ─────────────────────────────────────────────────────────
  init() {
    if (this._initialized) return;
    this._initialized = true;
    this.loadHistory();
    this.bindInput();
    this.bindInteractions();
  },

  // ── History ───────────────────────────────────────────────
  async loadHistory() {
    const area = document.getElementById('chat-messages');
    if (!area) return;
    area.innerHTML = this.skelHTML();
    try {
      const data = await api('chat/messages.php?limit=50');
      area.innerHTML = '';
      this._lastRenderedUid = 0;

      if (!data.chat_enabled) { this.showDisabled(); return; }

      if (!data.messages || !data.messages.length) {
        area.innerHTML = `<div class="empty-state">
          <div class="empty-icon">&#x1F4AC;</div>
          <div class="empty-title">No messages yet</div>
          <div class="empty-sub">Be the first to say something!</div>
        </div>`;
      } else {
        let prevDate = null;
        const msgs = data.messages;
        msgs.forEach((m, i) => {
          const d = (m.created_at || '').substring(0, 10);
          if (d && d !== prevDate) {
            area.insertAdjacentHTML('beforeend', this.dateDivider(m.created_at));
            prevDate = d;
            this._lastRenderedUid = 0; // date break resets grouping
          }
          const nextMsg  = msgs[i + 1];
          const nextUid  = nextMsg ? (nextMsg.user?.id || 0) : 0;
          const thisUid  = m.user?.id || 0;
          const isFirst  = thisUid !== this._lastRenderedUid;
          const isLast   = thisUid !== nextUid || d !== (nextMsg?.created_at || '').substring(0, 10);
          area.insertAdjacentHTML('beforeend', this.renderMsg(m, isFirst, isLast));
          this._lastRenderedUid = thisUid;
        });
        this.lastId = data.last_id || 0;
        this.scrollBottom();
        setTimeout(() => this.scrollBottom(), 120);
        setTimeout(() => this.scrollBottom(), 400);
      }
      this.connectSSE();
    } catch(err) {
      const area2 = document.getElementById('chat-messages');
      if (area2) area2.innerHTML = `<div class="empty-state">
        <div class="empty-icon">&#x26A0;</div>
        <div class="empty-title">Could not load messages</div>
        <div class="empty-sub">${err.message || 'Please refresh the page.'}</div>
      </div>`;
    }
  },

  showDisabled() {
    document.getElementById('chat-input-bar')?.style.setProperty('display', 'none');
    const dis = document.getElementById('chat-disabled-bar');
    if (dis) dis.style.display = 'block';
  },

  // ── SSE / Polling ─────────────────────────────────────────
  connectSSE() {
    if (this.pollTimer) { clearInterval(this.pollTimer); this.pollTimer = null; }
    this.eventSource?.close();
    if (!window.EventSource) { this.startPoll(); return; }

    this.eventSource = new EventSource('/api/chat/stream.php?last_id=' + this.lastId);
    this.eventSource.addEventListener('message', ev => {
      try { const msg = JSON.parse(ev.data); this.appendMsg(msg); this.lastId = msg.id; } catch(_) {}
    });
    this.eventSource.addEventListener('reconnect', () => {
      this.eventSource.close();
      setTimeout(() => this.connectSSE(), 1500);
    });
    this.eventSource.onerror = () => { this.eventSource.close(); this.startPoll(); };
  },

  startPoll() {
    if (this.pollTimer) clearInterval(this.pollTimer);
    this.pollTimer = setInterval(async () => {
      try {
        const d = await api('chat/messages.php?since_id=' + this.lastId);
        if (!d.chat_enabled) { this.showDisabled(); clearInterval(this.pollTimer); return; }
        (d.messages || []).forEach(m => { this.appendMsg(m); this.lastId = m.id; });
      } catch(_) {}
    }, 3000);
  },

  appendMsg(msg) {
    const area = document.getElementById('chat-messages');
    if (!area) return;
    const empty = area.querySelector('.empty-state');
    if (empty) { area.innerHTML = ''; this._lastRenderedUid = 0; }

    const wasAtBottom = area.scrollHeight - area.scrollTop - area.clientHeight < 80;

    // Date break?
    const msgDate = (msg.created_at || '').substring(0, 10);
    const lastDiv = area.querySelector('.date-divider:last-of-type');
    if (msgDate && (!lastDiv || lastDiv.dataset.date !== msgDate)) {
      area.insertAdjacentHTML('beforeend', this.dateDivider(msg.created_at));
      this._lastRenderedUid = 0;
    }

    const thisUid = msg.user?.id || 0;
    const isFirst = thisUid !== this._lastRenderedUid;
    // When appending live we don't know next msg — so always isLast=true.
    // If the same sender sends another, we'll patch the previous one.
    const isLast = true;

    // If the previous rendered message was from the same sender,
    // patch it to remove its "last bubble" styling (square bottom corner → rounded).
    if (!isFirst) {
      const prevRow = area.querySelector('.msg-row:last-of-type');
      if (prevRow) prevRow.classList.remove('msg-last');
    }

    area.insertAdjacentHTML('beforeend', this.renderMsg(msg, isFirst, isLast));
    this._lastRenderedUid = thisUid;

    if (wasAtBottom) this.scrollBottom();
    this.updateScrollBtn();
  },

  // ── Render one message row ────────────────────────────────
  // isFirst → show avatar + sender name
  // isLast  → show timestamp; bubble gets the "tail" corner radius
  renderMsg(msg, isFirst, isLast) {
    const isOwn = msg.user && msg.user.id === CURRENT_USER.id;
    const time  = msg.created_at
      ? new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
      : '';
    const name = msg.user && (msg.user.nickname || msg.user.full_name || '?');
    const init = name ? name[0].toUpperCase() : '?';
    const uid  = msg.user ? msg.user.id : 0;

    // Build CSS classes for grouping
    const rowClasses = [
      'msg-row',
      isOwn   ? 'own'       : '',
      isFirst ? 'msg-first' : 'msg-mid',
      isLast  ? 'msg-last'  : '',
    ].filter(Boolean).join(' ');

    // Avatar column: only shown for others on first message of group.
    // On non-first rows, a spacer keeps alignment.
    let avatarCol = '';
    if (!isOwn) {
      if (isFirst) {
        avatarCol = `<div class="msg-avatar-col">
          <div class="msg-avatar">${msg.user?.avatar
            ? `<img src="/uploads/avatars/${e(msg.user.avatar)}" alt="">`
            : init}</div>
        </div>`;
      } else {
        avatarCol = `<div class="msg-avatar-col"></div>`; // spacer
      }
    }

    // Sender name: only first message of a group, only for others
    const senderLine = (!isOwn && isFirst && name)
      ? `<div class="msg-sender">${e(name)}</div>`
      : '';

    const body = msg.is_deleted
      ? `<em style="opacity:.45;font-size:12px">Message deleted</em>`
      : this.renderBody(msg);

    // Quoted reply block — renders INSIDE the bubble, above the message text
    const quotedBlock = (!msg.is_deleted && msg.reply_quoted)
      ? this.renderQuoted(msg.reply_quoted, isOwn)
      : '';

    const myReactions = msg.my_reactions || [];
    const reactions   = msg.reactions   || {};

    // Time: only on last message of group (like WhatsApp/Messenger)
    const timeEl = isLast ? `<div class="msg-time">${time}</div>` : '';

    const reactBtn = `<button class="msg-react-btn" data-id="${msg.id}" title="React" type="button">😊</button>`;
    // Reply button — always beside the react button, same hide/show behaviour
    const replyBtnSvg = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>`;
    const replyBtn = `<button class="msg-reply-btn" data-id="${msg.id}" title="Reply" type="button">${replyBtnSvg}</button>`;

    // Action buttons order:
    //   Others: [reply] [react] [bubble]
    //   Own:    [bubble] [react] [reply]  (flex row-reverse handles it)
    return `<div class="${rowClasses}" data-id="${msg.id}" data-uid="${uid}">
      ${avatarCol}
      <div class="msg-content">
        ${senderLine}
        <div class="msg-bubble-wrap">
          ${replyBtn}
          ${reactBtn}
          <div class="msg-bubble" data-id="${msg.id}">${quotedBlock}${body}</div>
        </div>
        ${this.renderReactionBar(reactions, myReactions, msg.id)}
        ${timeEl}
      </div>
    </div>`;
  },

  renderQuoted(q, isOwn) {
    let preview = '';
    if (q.type === 'image') {
      preview = '📷 Photo';
    } else if (q.type === 'file') {
      preview = '📎 ' + e(q.file_name || 'File');
    } else {
      preview = e((q.body || '').substring(0, 80));
    }
    return `<div class="msg-reply-quote" data-reply-id="${q.id}">
      <span class="mrq-sender">${e(q.sender || '')}</span>
      <span class="mrq-text">${preview}</span>
    </div>`;
  },

  renderBody(msg) {
    if (!msg.type || msg.type === 'text') return e(msg.body || '').replace(/\n/g, '<br>');
    if (msg.type === 'image') return `<img
      src="/uploads/${e(msg.file_path)}"
      alt=""
      class="msg-img"
      data-src="/uploads/${e(msg.file_path)}"
      style="max-width:220px;border-radius:12px;display:block;cursor:pointer">`;
    return `<a href="/api/storage/download.php?id=${msg.id}" style="color:inherit;display:flex;align-items:center;gap:6px">&#x1F4CE; ${e(msg.file_name || 'File')}</a>`;
  },

  // ── Reaction bar ──────────────────────────────────────────
  renderReactionBar(reactions, myReactions, msgId) {
    const entries = Object.entries(reactions || {});
    const state = `data-reactions='${JSON.stringify(reactions)}' data-my-reactions='${JSON.stringify(myReactions)}'`;
    if (!entries.length) return `<div class="reaction-bar" data-msg="${msgId}" ${state}></div>`;
    const pills = entries.map(([emoji, count]) => {
      const isMine = myReactions.includes(emoji);
      return `<button class="reaction-pill${isMine ? ' own' : ''}"
        data-msg="${msgId}" data-emoji="${emoji}"
        title="${count} ${count === 1 ? 'reaction' : 'reactions'}"
        type="button">${emoji}<span class="r-count">${count}</span></button>`;
    }).join('');
    return `<div class="reaction-bar" data-msg="${msgId}" ${state}>${pills}</div>`;
  },

  refreshReactionBar(msgId, reactions, myReactions) {
    const bar = document.querySelector(`.reaction-bar[data-msg="${msgId}"]`);
    if (!bar) return;
    bar.dataset.reactions   = JSON.stringify(reactions);
    bar.dataset.myReactions = JSON.stringify(myReactions);
    const entries = Object.entries(reactions || {});
    if (!entries.length) { bar.innerHTML = ''; return; }
    bar.innerHTML = entries.map(([emoji, count]) => {
      const isMine = myReactions.includes(emoji);
      return `<button class="reaction-pill${isMine ? ' own' : ''}"
        data-msg="${msgId}" data-emoji="${emoji}"
        title="${count} ${count === 1 ? 'reaction' : 'reactions'}"
        type="button">${emoji}<span class="r-count">${count}</span></button>`;
    }).join('');
  },

  // ── Toggle reaction ───────────────────────────────────────
  async toggleReact(msgId, emoji) {
    try {
      const data = await api('chat/react.php', {
        method: 'POST',
        body: JSON.stringify({ message_id: msgId, emoji }),
      });
      this.refreshReactionBar(msgId, data.reactions || {}, data.my_reactions || []);
    } catch(err) { showToast(err.message, 'error'); }
  },

  // ── Picker ────────────────────────────────────────────────
  // Returns true if the device is a touch-primary device
  _isTouchDevice() {
    return window.matchMedia('(hover: none) and (pointer: coarse)').matches;
  },

  openReactPicker(msgId, anchorEl, isTouch) {
    this.closeReactPicker();

    const bar = document.querySelector(`.reaction-bar[data-msg="${msgId}"]`);
    let myReactions = [];
    try { myReactions = JSON.parse(bar?.dataset.myReactions || '[]'); } catch(_) {}

    // On touch devices → full bottom sheet with large emoji grid
    // On desktop     → compact floating pill
    if (this._isTouchDevice() || isTouch) {
      this._openReactSheet(msgId, myReactions);
    } else {
      this._openReactPill(msgId, myReactions, anchorEl);
    }
  },

  // ── MOBILE: bottom-sheet emoji picker ─────────────────────
  _openReactSheet(msgId, myReactions) {
    // Backdrop
    const backdrop = document.createElement('div');
    backdrop.id = 'react-picker';
    backdrop.className = 'react-sheet-backdrop';
    backdrop.dataset.msgId = String(msgId);

    // Sheet
    const sheet = document.createElement('div');
    sheet.className = 'react-sheet';

    // Handle bar
    const handle = document.createElement('div');
    handle.className = 'react-sheet-handle';
    sheet.appendChild(handle);

    // Label
    const label = document.createElement('div');
    label.className = 'react-sheet-label';
    label.textContent = 'React';
    sheet.appendChild(label);

    // Emoji grid
    const grid = document.createElement('div');
    grid.className = 'react-sheet-grid';

    this.EMOJIS.forEach(({ e: emoji, label: lbl }) => {
      const btn = document.createElement('button');
      btn.className = 'react-sheet-opt' + (myReactions.includes(emoji) ? ' selected' : '');
      btn.type = 'button';
      btn.dataset.emoji = emoji;
      btn.innerHTML = `<span class="rso-emoji">${emoji}</span><span class="rso-label">${lbl}</span>`;
      grid.appendChild(btn);
    });

    sheet.appendChild(grid);
    backdrop.appendChild(sheet);
    document.body.appendChild(backdrop);

    // Animate in
    requestAnimationFrame(() => {
      backdrop.classList.add('open');
    });

    // Delegated click on grid
    grid.addEventListener('click', ev => {
      const btn = ev.target.closest('.react-sheet-opt');
      if (!btn) return;
      const emoji = btn.dataset.emoji;
      if (!emoji) return;
      this.closeReactPicker();
      this.toggleReact(msgId, emoji);
    });

    // Close on backdrop tap
    backdrop.addEventListener('click', ev => {
      if (ev.target === backdrop) this.closeReactPicker();
    });
  },

  // ── DESKTOP: floating pill emoji picker ───────────────────
  _openReactPill(msgId, myReactions, anchorEl) {
    const picker = document.createElement('div');
    picker.className = 'emoji-reaction-picker';
    picker.id = 'react-picker';
    picker.dataset.msgId = String(msgId);

    this.EMOJIS.forEach(({ e: emoji, label }) => {
      const btn = document.createElement('button');
      btn.className = 'emoji-reaction-opt' + (myReactions.includes(emoji) ? ' selected' : '');
      btn.type = 'button';
      btn.setAttribute('aria-label', label);
      btn.innerHTML = `<span class="ero-emoji">${emoji}</span><span class="ero-label">${label}</span>`;
      btn.dataset.emoji = emoji;
      picker.appendChild(btn);
    });

    picker.addEventListener('click', ev => {
      const btn = ev.target.closest('.emoji-reaction-opt');
      if (!btn) return;
      const emoji = btn.dataset.emoji;
      if (!emoji) return;
      this.closeReactPicker();
      this.toggleReact(msgId, emoji);
    });

    document.body.appendChild(picker);

    requestAnimationFrame(() => {
      this._positionPicker(picker, anchorEl);
    });

    const closeHandler = (ev) => {
      const p = document.getElementById('react-picker');
      if (p && p.contains(ev.target)) return;
      this.closeReactPicker();
    };
    setTimeout(() => {
      document.addEventListener('mousedown', closeHandler);
      this._pickerCloseHandler = closeHandler;
    }, 50);
  },

  _positionPicker(picker, anchorEl) {
    const rect = anchorEl.getBoundingClientRect();
    const pw   = picker.offsetWidth;
    const ph   = picker.offsetHeight;
    let left = rect.left + rect.width / 2 - pw / 2;
    let top  = rect.top - ph - 12;
    left = Math.max(8, Math.min(left, window.innerWidth  - pw - 8));
    if (top < 8) top = rect.bottom + 12;
    top = Math.max(8, Math.min(top, window.innerHeight - ph - 8));
    picker.style.left = left + 'px';
    picker.style.top  = top  + 'px';
  },

  closeReactPicker() {
    const el = document.getElementById('react-picker');
    if (el) {
      // Animate sheet out before removing
      if (el.classList.contains('react-sheet-backdrop')) {
        el.classList.remove('open');
        setTimeout(() => el.remove(), 280);
      } else {
        el.remove();
      }
    }
    if (this._pickerCloseHandler) {
      document.removeEventListener('mousedown', this._pickerCloseHandler);
      this._pickerCloseHandler = null;
    }
  },

  // ── Context menu ──────────────────────────────────────────
  showCtxMenu(x, y, msgId, userId, bubbleText, bubbleEl) {
    this.hideCtxMenu();
    if (!msgId) return;

    const isOwn = userId === CURRENT_USER.id;
    const isMod = CURRENT_USER.role === 'moderator' || CURRENT_USER.role === 'admin';

    const menu = document.createElement('div');
    menu.id = 'ctx-menu';
    menu.className = 'ctx-menu';

    const replyIcon  = `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>`;
    const deleteIcon = `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>`;

    const items = [
      { icon: replyIcon, label: 'Reply', action: () => this.setReply(msgId, bubbleText) },
    ];
    if (isOwn || isMod) items.push({
      icon: deleteIcon, label: 'Delete', danger: true,
      action: async () => {
        try {
          await api('chat/delete.php', { method: 'POST', body: JSON.stringify({ message_id: msgId }) });
          const bubble = document.querySelector(`.msg-row[data-id="${msgId}"] .msg-bubble`);
          if (bubble) bubble.innerHTML = `<em style="opacity:.45;font-size:12px">Message deleted</em>`;
        } catch(err) { showToast(err.message, 'error'); }
      },
    });

    items.forEach(item => {
      const el = document.createElement('div');
      el.className = 'ctx-item' + (item.danger ? ' ctx-item--danger' : '');
      el.innerHTML = `${item.icon}<span>${item.label}</span>`;
      el.addEventListener('pointerdown', ev => { ev.stopPropagation(); this.hideCtxMenu(); item.action(); });
      menu.appendChild(el);
    });

    const mw = 160, mh = items.length * 44 + 10;
    menu.style.left = Math.max(8, Math.min(x - mw / 2, window.innerWidth  - mw - 8)) + 'px';
    menu.style.top  = Math.max(8, Math.min(y,           window.innerHeight - mh - 8)) + 'px';
    document.body.appendChild(menu);

    if (bubbleEl) this.openReactPicker(msgId, bubbleEl, true);
  },

  hideCtxMenu() {
    document.getElementById('ctx-menu')?.remove();
    const mob = document.getElementById('ctx-menu-mobile');
    if (mob) {
      mob.classList.remove('open');
      setTimeout(() => mob.remove(), 280);
    }
    // Note: do NOT close the react picker here — they are independent.
  },

  // ── Event binding ─────────────────────────────────────────
  bindInteractions() {
    const area = document.getElementById('chat-messages');
    if (!area) return;

    // Desktop: hover react button → open picker
    let _pickerHideTimer = null;
    area.addEventListener('mouseover', ev => {
      const btn = ev.target.closest('.msg-react-btn');
      if (!btn) return;
      clearTimeout(_pickerHideTimer);
      const row   = btn.closest('.msg-row');
      const msgId = parseInt(row?.dataset.id);
      if (!msgId) return;
      const existing = document.getElementById('react-picker');
      if (existing && existing.dataset.msgId === String(msgId)) return;
      this.openReactPicker(msgId, btn, false);
    });

    // Close picker when mouse leaves BOTH the bubble-wrap zone AND the picker itself.
    // We use a delegated pointerleave on document so we can check both zones.
    // pointerleave does NOT bubble, so we listen on each element directly when picker opens.
    // Simpler: use mouseleave on the picker itself + on the react-btn's parent.
    // The picker gets its own pointerleave wired in openReactPicker via a data attr flag.
    // Here we just handle the "left the button area without entering picker" case.
    // Picker closes only on outside mousedown (handled inside openReactPicker).
    // No hover-based auto-close timer — that was causing the "click doesn't register" bug.

    // Desktop: right-click → context menu (with reply)
    area.addEventListener('contextmenu', ev => {
      const bubble = ev.target.closest('.msg-bubble');
      if (!bubble) return;
      ev.preventDefault();
      const row     = bubble.closest('.msg-row');
      const msgId   = parseInt(row?.dataset.id);
      const uid     = parseInt(row?.dataset.uid);
      // Get plain text of bubble, excluding the quoted reply block
      const quote   = bubble.querySelector('.msg-reply-quote');
      const cloned  = bubble.cloneNode(true);
      cloned.querySelector('.msg-reply-quote')?.remove();
      const plainText = cloned.innerText || cloned.textContent || '';
      this.showCtxMenu(ev.clientX, ev.clientY, msgId, uid, plainText, null);
    });

    // Delegated click: reaction pills + image viewer
    area.addEventListener('click', ev => {
      const pill = ev.target.closest('.reaction-pill');
      if (pill) {
        const msgId = parseInt(pill.dataset.msg);
        const emoji = pill.dataset.emoji;
        if (msgId && emoji) this.toggleReact(msgId, emoji);
        return;
      }
      const img = ev.target.closest('.msg-img');
      if (img && !this._suppressNextClick) window.open(img.dataset.src || img.src);
    });

    // Mobile: long-press → picker above + action menu below
    let pressTimer  = null;
    let didLongPress = false;

    area.addEventListener('touchstart', ev => {
      const bubble = ev.target.closest('.msg-bubble');
      if (!bubble) return;
      didLongPress = false;
      this._suppressNextClick = false;

      pressTimer = setTimeout(() => {
        didLongPress = true;
        this._suppressNextClick = true;
        const row   = bubble.closest('.msg-row');
        const msgId = parseInt(row?.dataset.id);
        const uid   = parseInt(row?.dataset.uid);
        if (!msgId) return;
        if (navigator.vibrate) navigator.vibrate(36);

        // Picker above bubble
        this.openReactPicker(msgId, bubble, true);

        // Action menu below bubble
        const rect = bubble.getBoundingClientRect();
        this._showMobileMenu(rect.left + rect.width / 2, rect.bottom + 8, msgId, uid, bubble.innerText);
      }, 480);
    }, { passive: true });

    area.addEventListener('touchmove', () => {
      clearTimeout(pressTimer); pressTimer = null;
    }, { passive: true });

    area.addEventListener('touchend', ev => {
      clearTimeout(pressTimer); pressTimer = null;
      if (didLongPress) {
        ev.preventDefault();
        didLongPress = false;
        setTimeout(() => { this._suppressNextClick = false; }, 400);
      }
    });

    // React button tap on touch devices → open emoji sheet
    area.addEventListener('click', ev => {
      const reactBtn = ev.target.closest('.msg-react-btn');
      if (reactBtn) {
        const row   = reactBtn.closest('.msg-row');
        const msgId = parseInt(row?.dataset.id);
        if (msgId) this.openReactPicker(msgId, reactBtn, false);
        return;
      }
    });

    // Reply button tap → set reply
    area.addEventListener('click', ev => {
      const btn = ev.target.closest('.msg-reply-btn');
      if (!btn) return;
      const row = btn.closest('.msg-row');
      const msgId = parseInt(row?.dataset.id);
      if (!msgId) return;
      // Get clean bubble text (exclude quoted block)
      const bubble = row.querySelector('.msg-bubble');
      const cloned = bubble?.cloneNode(true);
      cloned?.querySelector('.msg-reply-quote')?.remove();
      const plainText = cloned?.innerText || cloned?.textContent || '';
      this.setReply(msgId, plainText);
    });

    // Close menus on outside click — but NOT when clicking inside the react picker
    document.addEventListener('click', ev => {
      if (ev.target.closest('#react-picker')) return;  // let picker handle its own clicks
      if (!ev.target.closest('#ctx-menu') && !ev.target.closest('#ctx-menu-mobile')) {
        this.hideCtxMenu();
      }
    });
  },

  _showMobileMenu(cx, cy, msgId, userId, bubbleText) {
    document.getElementById('ctx-menu-mobile')?.remove();
    const isOwn = userId === CURRENT_USER.id;
    const isMod = CURRENT_USER.role === 'moderator' || CURRENT_USER.role === 'admin';

    // Bottom-sheet action menu for mobile
    const backdrop = document.createElement('div');
    backdrop.id = 'ctx-menu-mobile';
    backdrop.className = 'action-sheet-backdrop';

    const sheet = document.createElement('div');
    sheet.className = 'action-sheet';

    const handle = document.createElement('div');
    handle.className = 'action-sheet-handle';
    sheet.appendChild(handle);

    const replyIcon  = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>`;
    const deleteIcon = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>`;

    const items = [
      { icon: replyIcon, label: 'Reply', action: () => { this.hideCtxMenu(); this.setReply(msgId, bubbleText); } },
    ];
    if (isOwn || isMod) items.push({
      icon: deleteIcon, label: 'Delete', danger: true,
      action: async () => {
        this.hideCtxMenu();
        try {
          await api('chat/delete.php', { method: 'POST', body: JSON.stringify({ message_id: msgId }) });
          const bubble = document.querySelector(`.msg-row[data-id="${msgId}"] .msg-bubble`);
          if (bubble) bubble.innerHTML = `<em style="opacity:.45;font-size:12px">Message deleted</em>`;
        } catch(err) { showToast(err.message, 'error'); }
      },
    });

    items.forEach(item => {
      const el = document.createElement('button');
      el.className = 'action-sheet-item' + (item.danger ? ' danger' : '');
      el.type = 'button';
      el.innerHTML = `<span class="asi-icon">${item.icon}</span><span class="asi-label">${item.label}</span>`;
      el.addEventListener('click', ev => { ev.stopPropagation(); item.action(); });
      sheet.appendChild(el);
    });

    backdrop.appendChild(sheet);
    document.body.appendChild(backdrop);

    requestAnimationFrame(() => backdrop.classList.add('open'));

    // Close on backdrop tap
    backdrop.addEventListener('click', ev => {
      if (ev.target === backdrop) this.hideCtxMenu();
    });
  },

  // ── Input / send ─────────────────────────────────────────
  bindInput() {
    const input = document.getElementById('chat-input');
    const send  = document.getElementById('chat-send');
    send?.addEventListener('click', () => this.submit());
    input?.addEventListener('keydown', ev => {
      if (ev.key === 'Enter' && !ev.shiftKey) { ev.preventDefault(); this.submit(); }
    });
    input?.addEventListener('input', () => {
      input.style.height = 'auto';
      input.style.height = Math.min(input.scrollHeight, 100) + 'px';
    });
    document.getElementById('scroll-down-btn')?.addEventListener('click', () => this.scrollBottom());
    document.getElementById('chat-messages')?.addEventListener('scroll', () => this.updateScrollBtn());
    document.getElementById('chat-attach')?.addEventListener('click', () => document.getElementById('file-input')?.click());
    document.getElementById('chat-image')?.addEventListener('click', () => document.getElementById('image-input')?.click());
    document.getElementById('file-input')?.addEventListener('change', ev => { const f = ev.target.files[0]; if (f) this.sendFile(f); });
    document.getElementById('image-input')?.addEventListener('change', ev => { const f = ev.target.files[0]; if (f) this.sendFile(f); });
  },

  async submit() {
    const input = document.getElementById('chat-input');
    const body  = input?.value.trim();
    if (!body) return;
    input.value = ''; input.style.height = 'auto';
    try {
      const resp = await api('chat/messages.php', {
        method: 'POST',
        body: JSON.stringify({ body, type: 'text', reply_to_id: this.replyTo || null }),
      });
      this.clearReply();
      if (resp?.message) { this.appendMsg(resp.message); this.lastId = resp.message.id; }
    } catch(err) { showToast(err.message, 'error'); input.value = body; }
  },

  async sendFile(file) {
    const fd = new FormData();
    fd.append('file', file);
    if (this.replyTo) fd.append('reply_to_id', this.replyTo);
    showToast('Uploading...');
    try {
      const res  = await fetch('/api/chat/messages.php', {
        method: 'POST', headers: { 'X-CSRF-Token': getCsrf() },
        body: fd, credentials: 'same-origin',
      });
      const data = await res.json();
      if (data.success) {
        this.clearReply();
        if (data.data?.message) { this.appendMsg(data.data.message); this.lastId = data.data.message.id; }
      } else { showToast(data.error || 'Upload failed', 'error'); }
    } catch(_) { showToast('Upload failed', 'error'); }
  },

  // ── Reply ─────────────────────────────────────────────────
  setReply(msgId, text) {
    this.replyTo = msgId;
    let preview = document.getElementById('reply-preview');
    if (!preview) {
      preview = document.createElement('div');
      preview.id = 'reply-preview';
      preview.className = 'reply-preview';
      document.getElementById('chat-input-bar')?.parentNode.insertBefore(
        preview, document.getElementById('chat-input-bar')
      );
    }
    preview.innerHTML = `<div class="reply-preview-text">&#x21A9; ${e(text).substring(0, 80)}</div>
      <span class="reply-preview-close" onclick="Chat.clearReply()">&#x2715;</span>`;
  },

  clearReply() {
    this.replyTo = null;
    document.getElementById('reply-preview')?.remove();
  },

  // ── Scroll ────────────────────────────────────────────────
  updateScrollBtn() {
    const area = document.getElementById('chat-messages');
    if (!area) return;
    const atBottom = area.scrollHeight - area.scrollTop - area.clientHeight < 80;
    document.getElementById('scroll-down-btn')?.classList.toggle('show', !atBottom);
  },

  scrollBottom() {
    const area = document.getElementById('chat-messages');
    if (area) area.scrollTop = area.scrollHeight;
  },

  autoScroll() { this.updateScrollBtn(); },

  dateDivider(dateStr) {
    const date = (dateStr || '').substring(0, 10);
    const d    = new Date(dateStr);
    const now  = new Date();
    const diff = Math.floor((now - d) / 86400000);
    const label = diff === 0 ? 'Today'
      : diff === 1 ? 'Yesterday'
      : d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' });
    return `<div class="date-divider" data-date="${date}"><span>${label}</span></div>`;
  },

  skelHTML() {
    return `<div style="padding:10px 12px">
      <div class="date-divider"><span>Today</span></div>
      <div class="msg-row msg-first msg-last" style="margin-bottom:10px">
        <div class="msg-avatar-col"><div class="skeleton" style="width:30px;height:30px;border-radius:50%"></div></div>
        <div style="display:flex;flex-direction:column;gap:5px">
          <div class="skeleton" style="width:80px;height:11px;border-radius:6px"></div>
          <div class="skeleton" style="width:200px;height:38px;border-radius:18px 18px 18px 4px"></div>
        </div>
      </div>
      <div class="msg-row own msg-first msg-last" style="margin-bottom:10px">
        <div style="margin-left:auto"><div class="skeleton" style="width:160px;height:38px;border-radius:18px 18px 4px 18px;background:#e8c0c0"></div></div>
      </div>
    </div>`;
  },
};

function e(s) {
  return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}