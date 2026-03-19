/* ============================================================
   chat.js  Full messenger with SSE, reactions, replies
   ============================================================ */
'use strict';

const Chat = {
  lastId: 0,
  eventSource: null,
  pollTimer: null,
  replyTo: null,
  _initialized: false,

  init() {
    if (this._initialized) return;
    this._initialized = true;
    this.loadHistory();
    this.bindInput();
    this.bindContextMenu();
  },

  async loadHistory() {
    const area = document.getElementById('chat-messages');
    if (!area) return;

    try {
      const data = await api('chat/messages.php?limit=50');

      if (!data.chat_enabled) {
        this.showDisabled();
        return;
      }

      area.innerHTML = '';

      if (!data.messages.length) {
        area.innerHTML = `<div class="empty-state">
          <div class="empty-icon"></div>
          <div class="empty-title">No messages yet</div>
          <div class="empty-sub">Be the first to say something!</div>
        </div>`;
      } else {
        let prevDate = null;
        data.messages.forEach(m => {
          const msgDate = m.created_at.substring(0, 10);
          if (msgDate !== prevDate) {
            area.insertAdjacentHTML('beforeend', this.dateDivider(m.created_at));
            prevDate = msgDate;
          }
          area.insertAdjacentHTML('beforeend', this.renderMsg(m));
        });
        this.lastId = data.last_id || 0;
        this.scrollBottom();
      }

      this.connectSSE();

    } catch (e) {
      showToast('Could not load messages', 'error');
    }
  },

  showDisabled() {
    document.getElementById('chat-input-bar').style.display = 'none';
    document.getElementById('chat-disabled-bar').style.display = 'block';
  },

  connectSSE() {
    if (this.pollTimer) clearInterval(this.pollTimer);
    if (!window.EventSource) { this.startPoll(); return; }

    this.eventSource?.close();
    this.eventSource = new EventSource(`/api/chat/stream.php?last_id=${this.lastId}`);

    this.eventSource.addEventListener('message', e => {
      const msg = JSON.parse(e.data);
      this.appendMsg(msg);
      this.lastId = msg.id;
    });
    this.eventSource.addEventListener('reconnect', () => {
      this.eventSource.close();
      setTimeout(() => this.connectSSE(), 1200);
    });
    this.eventSource.onerror = () => {
      this.eventSource.close();
      this.startPoll();
    };
  },

  startPoll() {
    this.pollTimer = setInterval(async () => {
      try {
        const d = await api(`chat/messages.php?since_id=${this.lastId}`);
        if (!d.chat_enabled) { this.showDisabled(); clearInterval(this.pollTimer); return; }
        d.messages?.forEach(m => { this.appendMsg(m); this.lastId = m.id; });
      } catch (_) {}
    }, 3000);
  },

  appendMsg(msg) {
    const area = document.getElementById('chat-messages');
    if (!area) return;
    const isEmpty = area.querySelector('.empty-state');
    if (isEmpty) area.innerHTML = '';

    // Date divider if needed
    const lastDivider = area.querySelector('.date-divider:last-of-type');
    const msgDate = msg.created_at.substring(0, 10);
    if (!lastDivider || lastDivider.dataset.date !== msgDate) {
      area.insertAdjacentHTML('beforeend', this.dateDivider(msg.created_at));
    }

    area.insertAdjacentHTML('beforeend', this.renderMsg(msg));
    this.autoScroll();
  },

  renderMsg(msg) {
    const isOwn = msg.user.id === CURRENT_USER.id;
    const time  = new Date(msg.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    const init  = (msg.user.nickname || msg.user.full_name || '?')[0].toUpperCase();

    const avatarHtml = isOwn ? '' : `
      <div class="msg-avatar-col">
        <div class="msg-avatar">${msg.user.avatar
          ? `<img src="/uploads/avatars/${e(msg.user.avatar)}" alt="">`
          : init}</div>
      </div>`;

    const senderLine = isOwn ? '' :
      `<div class="msg-sender">${e(msg.user.nickname || msg.user.full_name)}</div>`;

    const replyQuote = msg.reply_to_id
      ? `<div class="msg-reply-quote"> Replying to message</div>` : '';

    const bubbleBody = msg.is_deleted
      ? `<em style="opacity:.45;font-size:12px">Message deleted</em>`
      : this.renderBody(msg);

    const reactions = this.renderReactions(msg.reactions || {}, msg.id);

    return `
    <div class="msg-row${isOwn ? ' own' : ''}" data-id="${msg.id}" data-uid="${msg.user.id}">
      ${avatarHtml}
      <div class="msg-content">
        ${senderLine}
        <div class="msg-bubble" data-id="${msg.id}">
          ${replyQuote}
          ${bubbleBody}
        </div>
        ${reactions}
        <div class="msg-time">${time}</div>
      </div>
    </div>`;
  },

  renderBody(msg) {
    if (msg.type === 'text')  return e(msg.body).replace(/\n/g, '<br>');
    if (msg.type === 'image') return `<img src="/uploads/${e(msg.file_path)}" alt="${e(msg.file_name)}" style="max-width:200px;border-radius:10px;display:block;cursor:pointer" onclick="window.open(this.src)">`;
    return `<a href="/api/storage/download.php?id=${msg.id}" style="color:inherit;display:flex;align-items:center;gap:6px;text-decoration:underline"> ${e(msg.file_name || 'File')}</a>`;
  },

  renderReactions(reactions, msgId) {
    const entries = Object.entries(reactions);
    if (!entries.length) return `<div class="reaction-bar" data-msg="${msgId}"></div>`;
    const pills = entries.map(([emoji, count]) =>
      `<button class="reaction-pill" onclick="Chat.toggleReact(${msgId},'${emoji}')" title="${emoji}">
        ${emoji} <span class="r-count">${count}</span>
      </button>`
    ).join('');
    return `<div class="reaction-bar" data-msg="${msgId}">${pills}</div>`;
  },

  async toggleReact(msgId, emoji) {
    try {
      const { reactions } = await api('chat/react.php', {
        method: 'POST',
        body: JSON.stringify({ message_id: msgId, emoji })
      });
      // Update reaction bar
      const bar = document.querySelector(`.reaction-bar[data-msg="${msgId}"]`);
      if (bar) {
        const entries = Object.entries(reactions);
        bar.innerHTML = entries.map(([em, cnt]) =>
          `<button class="reaction-pill" onclick="Chat.toggleReact(${msgId},'${em}')">${em} <span class="r-count">${cnt}</span></button>`
        ).join('');
      }
    } catch (e) { showToast(e.message, 'error'); }
  },

  dateDivider(dateStr) {
    const d    = new Date(dateStr);
    const now  = new Date();
    const diff = Math.floor((now - d) / 86400000);
    const label = diff === 0 ? 'Today' : diff === 1 ? 'Yesterday'
      : d.toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'2-digit'});
    const date = dateStr.substring(0, 10);
    return `<div class="date-divider" data-date="${date}"><span>${label}</span></div>`;
  },

  autoScroll() {
    const area = document.getElementById('chat-messages');
    if (!area) return;
    const atBottom = area.scrollHeight - area.scrollTop - area.clientHeight < 140;
    if (atBottom) this.scrollBottom();
    const btn = document.getElementById('scroll-down-btn');
    if (btn) btn.classList.toggle('show', !atBottom);
  },

  scrollBottom() {
    const area = document.getElementById('chat-messages');
    if (area) area.scrollTop = area.scrollHeight;
  },

  setReply(msgId, text) {
    this.replyTo = msgId;
    let preview = document.getElementById('reply-preview');
    if (!preview) {
      preview = document.createElement('div');
      preview.id = 'reply-preview';
      preview.className = 'reply-preview';
      const bar = document.getElementById('chat-input-bar');
      bar.parentNode.insertBefore(preview, bar);
    }
    preview.innerHTML = `
      <div class="reply-preview-text"> ${e(text).substring(0, 80)}</div>
      <span class="reply-preview-close" onclick="Chat.clearReply()"></span>`;
  },

  clearReply() {
    this.replyTo = null;
    document.getElementById('reply-preview')?.remove();
  },

  bindInput() {
    const input = document.getElementById('chat-input');
    const send  = document.getElementById('chat-send');

    send?.addEventListener('click', () => this.submit());
    input?.addEventListener('keydown', e => {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); this.submit(); }
    });
    input?.addEventListener('input', () => {
      input.style.height = 'auto';
      input.style.height = Math.min(input.scrollHeight, 100) + 'px';
    });

    document.getElementById('scroll-down-btn')?.addEventListener('click', () => this.scrollBottom());
    document.getElementById('chat-messages')?.addEventListener('scroll', () => this.autoScroll());

    // File attachments
    document.getElementById('chat-attach')?.addEventListener('click', () =>
      document.getElementById('file-input').click());
    document.getElementById('chat-image')?.addEventListener('click', () =>
      document.getElementById('image-input').click());

    document.getElementById('file-input')?.addEventListener('change', e => {
      const f = e.target.files[0]; if (f) this.sendFile(f);
    });
    document.getElementById('image-input')?.addEventListener('change', e => {
      const f = e.target.files[0]; if (f) this.sendFile(f);
    });
  },

  bindContextMenu() {
    const area = document.getElementById('chat-messages');
    if (!area) return;

    area.addEventListener('contextmenu', ev => {
      const bubble = ev.target.closest('.msg-bubble');
      if (!bubble) return;
      ev.preventDefault();
      const row = bubble.closest('.msg-row');
      const msgId  = parseInt(row?.dataset.id);
      const userId = parseInt(row?.dataset.uid);
      const text   = bubble.innerText;
      if (!msgId) return;
      this.showCtxMenu(ev.clientX, ev.clientY, msgId, userId, text);
    });

    // Long press for mobile
    let pressTimer;
    area.addEventListener('touchstart', ev => {
      const bubble = ev.target.closest('.msg-bubble');
      if (!bubble) return;
      pressTimer = setTimeout(() => {
        const row   = bubble.closest('.msg-row');
        const msgId = parseInt(row?.dataset.id);
        const uid   = parseInt(row?.dataset.uid);
        const t     = ev.changedTouches[0];
        this.showCtxMenu(t.clientX, t.clientY, msgId, uid, bubble.innerText);
      }, 500);
    });
    area.addEventListener('touchend', () => clearTimeout(pressTimer));

    document.addEventListener('click', () => this.hideCtxMenu());
  },

  showCtxMenu(x, y, msgId, userId, text) {
    this.hideCtxMenu();
    const isOwn = userId === CURRENT_USER.id;
    const isMod = CURRENT_USER.role === 'moderator' || CURRENT_USER.role === 'admin';

    const menu = document.createElement('div');
    menu.id = 'ctx-menu';
    menu.className = 'ctx-menu';

    const items = [
      { icon:'', label:'Reply', action: () => this.setReply(msgId, text) },
      { icon:'', label:'React', action: () => this.showEmojiPicker(msgId, menu) },
    ];
    if (isOwn || isMod) {
      items.push({ icon:'', label:'Delete', danger: true, action: async () => {
        try {
          await api('chat/delete.php', { method:'POST', body:JSON.stringify({message_id:msgId}) });
          const row = document.querySelector(`.msg-row[data-id="${msgId}"]`);
          if (row) row.querySelector('.msg-bubble').innerHTML = `<em style="opacity:.45;font-size:12px">Message deleted</em>`;
        } catch(e) { showToast(e.message,'error'); }
      }});
    }

    items.forEach(item => {
      const el = document.createElement('div');
      el.className = 'ctx-item' + (item.danger ? ' ctx-item--danger' : '');
      el.innerHTML = `<span>${item.icon}</span> ${item.label}`;
      el.addEventListener('click', (ev) => { ev.stopPropagation(); this.hideCtxMenu(); item.action(); });
      menu.appendChild(el);
    });

    // Position
    menu.style.left = Math.min(x, window.innerWidth - 170) + 'px';
    menu.style.top  = Math.min(y, window.innerHeight - 150) + 'px';
    document.body.appendChild(menu);
  },

  showEmojiPicker(msgId, anchor) {
    const emojis = ['','','','','','','','','',''];
    const picker = document.createElement('div');
    picker.className = 'emoji-picker';
    picker.style.position = 'fixed';
    const rect = anchor.getBoundingClientRect();
    picker.style.left = rect.left + 'px';
    picker.style.top  = (rect.top - 60) + 'px';
    emojis.forEach(em => {
      const btn = document.createElement('span');
      btn.className = 'emoji-opt';
      btn.textContent = em;
      btn.addEventListener('click', ev => {
        ev.stopPropagation();
        picker.remove();
        this.toggleReact(msgId, em);
      });
      picker.appendChild(btn);
    });
    document.body.appendChild(picker);
    setTimeout(() => document.addEventListener('click', () => picker.remove(), { once: true }), 100);
  },

  hideCtxMenu() {
    document.getElementById('ctx-menu')?.remove();
  },

  async submit() {
    const input = document.getElementById('chat-input');
    const body  = input?.value.trim();
    if (!body) return;
    input.value = '';
    input.style.height = 'auto';
    try {
      await api('chat/messages.php', {
        method: 'POST',
        body: JSON.stringify({ body, type: 'text', reply_to_id: this.replyTo || null })
      });
      this.clearReply();
    } catch (err) {
      showToast(err.message, 'error');
      input.value = body;
    }
  },

  async sendFile(file) {
    const fd = new FormData();
    fd.append('file', file);
    if (this.replyTo) fd.append('reply_to_id', this.replyTo);
    showToast('Uploading');
    try {
      const res  = await fetch('/api/chat/messages.php', {
        method: 'POST',
        headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content },
        body: fd,
        credentials: 'same-origin'
      });
      const data = await res.json();
      if (!data.success) showToast(data.error, 'error');
      else this.clearReply();
    } catch (_) { showToast('Upload failed', 'error'); }
  }
};

function e(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
