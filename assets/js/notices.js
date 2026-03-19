'use strict';

const Notices = {
  _loaded: false,
  _q: '',
  _page: 1,
  _hasMore: true,

  load() {
    if (this._loaded) return;
    this._loaded = true;
    this._page = 1;
    this._hasMore = true;
    this.fetch(true);
    this.bindScroll();
    this.bindCreatePost();
  },

  search(q) {
    this._q = q; this._loaded = false; this._page = 1; this.load();
  },

  reload() {
    this._loaded = false; this._page = 1; this._hasMore = true; this.load();
  },

  bindScroll() {
    const feed = document.getElementById('notices-feed');
    if (!feed) return;
    feed.addEventListener('scroll', () => {
      if (!this._hasMore) return;
      if (feed.scrollTop + feed.clientHeight >= feed.scrollHeight - 120) {
        this._page++;
        this.fetch(false);
      }
    });
  },

  // ── Create Post Modal ──────────────────────────────────────
  bindCreatePost() {
    const fab      = document.getElementById('btn-create-post');
    const backdrop = document.getElementById('create-post-backdrop');
    const cancel   = document.getElementById('cp-cancel');
    if (!fab || !backdrop) return;

    const openModal  = () => { this.resetCreateForm(); backdrop.classList.add('open'); };
    const closeModal = () => backdrop.classList.remove('open');

    fab.addEventListener('click', openModal);
    cancel.addEventListener('click', closeModal);
    backdrop.addEventListener('click', e => { if (e.target === backdrop) closeModal(); });

    // Type tabs
    document.querySelectorAll('.cp-type-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.cp-type-btn').forEach(b => {
          b.style.background = 'var(--bg)'; b.style.color = 'var(--txt-2)'; b.style.border = '1.5px solid var(--border)';
        });
        btn.style.background = 'var(--red)'; btn.style.color = '#fff'; btn.style.border = '1.5px solid var(--red)';
        const type = btn.dataset.type;
        document.getElementById('cp-type').value = type;
        document.getElementById('cp-image-wrap').style.display = type === 'announcement' ? '' : 'none';
        document.getElementById('cp-event-wrap').style.display = type === 'event'        ? '' : 'none';
        document.getElementById('cp-poll-wrap').style.display  = type === 'poll'         ? '' : 'none';
      });
    });

    // Add poll option
    document.getElementById('cp-add-opt')?.addEventListener('click', () => {
      const container = document.getElementById('cp-poll-options');
      const count = container.querySelectorAll('.cp-poll-opt').length;
      if (count >= 6) return;
      const input = document.createElement('input');
      input.type = 'text'; input.className = 'cp-poll-opt';
      input.placeholder = `Option ${count + 1}`;
      input.style.cssText = 'width:100%;padding:9px 14px;background:var(--bg);border:1.5px solid var(--border);border-radius:var(--r-pill);font-size:13px;color:var(--txt);font-family:var(--fb);outline:none;margin-bottom:6px';
      container.appendChild(input);
    });

    // Submit
    document.getElementById('cp-submit')?.addEventListener('click', () => this.submitPost());
  },

  resetCreateForm() {
    ['cp-title','cp-body','cp-event-date','cp-event-time','cp-poll-ends'].forEach(id => {
      const el = document.getElementById(id); if (el) el.value = '';
    });
    document.getElementById('cp-priority').value = 'general';
    document.getElementById('cp-pin').checked = false;
    document.getElementById('cp-image').value = '';
    document.getElementById('cp-poll-anon').checked = false;
    document.getElementById('cp-error').style.display = 'none';
    // Reset poll options to 2
    const container = document.getElementById('cp-poll-options');
    container.innerHTML = '';
    [1, 2].forEach(n => {
      const input = document.createElement('input');
      input.type = 'text'; input.className = 'cp-poll-opt';
      input.placeholder = `Option ${n}`;
      input.style.cssText = 'width:100%;padding:9px 14px;background:var(--bg);border:1.5px solid var(--border);border-radius:var(--r-pill);font-size:13px;color:var(--txt);font-family:var(--fb);outline:none;margin-bottom:6px';
      container.appendChild(input);
    });
    // Reset tabs to announcement
    document.querySelectorAll('.cp-type-btn').forEach(b => {
      const isAnn = b.dataset.type === 'announcement';
      b.style.background = isAnn ? 'var(--red)' : 'var(--bg)';
      b.style.color = isAnn ? '#fff' : 'var(--txt-2)';
      b.style.border = isAnn ? '1.5px solid var(--red)' : '1.5px solid var(--border)';
    });
    document.getElementById('cp-type').value = 'announcement';
    document.getElementById('cp-image-wrap').style.display = '';
    document.getElementById('cp-event-wrap').style.display = 'none';
    document.getElementById('cp-poll-wrap').style.display  = 'none';
  },

  async submitPost() {
    const type  = document.getElementById('cp-type').value;
    const title = document.getElementById('cp-title').value.trim();
    const btn   = document.getElementById('cp-submit');
    const spin  = document.getElementById('cp-spinner');
    const txt   = document.getElementById('cp-submit-text');

    document.getElementById('cp-error').style.display = 'none';

    if (!title) { this.cpError('Title is required.'); return; }
    if (type === 'event' && !document.getElementById('cp-event-date').value) {
      this.cpError('Event date is required.'); return;
    }
    if (type === 'poll') {
      const opts = [...document.querySelectorAll('.cp-poll-opt')].map(i => i.value.trim()).filter(Boolean);
      if (opts.length < 2) { this.cpError('Add at least 2 poll options.'); return; }
    }

    btn.disabled = true; spin.style.display = 'inline-block'; txt.textContent = 'Publishing...';

    try {
      const fd = new FormData();
      fd.append('post_type', type);
      fd.append('title', title);
      fd.append('body', document.getElementById('cp-body').value.trim());
      fd.append('priority', document.getElementById('cp-priority').value);
      fd.append('is_pinned', document.getElementById('cp-pin').checked ? '1' : '0');

      if (type === 'announcement') {
        const img = document.getElementById('cp-image').files[0];
        if (img) fd.append('image', img);
      }
      if (type === 'event') {
        fd.append('event_date', document.getElementById('cp-event-date').value);
        fd.append('event_time', document.getElementById('cp-event-time').value);
        fd.append('event_type', document.getElementById('cp-event-type').value);
      }
      if (type === 'poll') {
        const opts = [...document.querySelectorAll('.cp-poll-opt')].map(i => i.value.trim()).filter(Boolean);
        fd.append('poll_options', JSON.stringify(opts));
        fd.append('poll_anon', document.getElementById('cp-poll-anon').checked ? '1' : '0');
        fd.append('poll_ends_at', document.getElementById('cp-poll-ends').value);
      }

      const res  = await fetch('/api/posts/index.php', {
        method: 'POST',
        headers: { 'X-CSRF-Token': getCsrf() },
        body: fd,
        credentials: 'same-origin'
      });
      const data = await res.json();

      if (data.success) {
        document.getElementById('create-post-backdrop').classList.remove('open');
        showToast('Post published!', 'success');
        this.reload();
      } else {
        this.cpError(data.error || 'Failed to publish.');
      }
    } catch(e) {
      this.cpError('Connection error. Try again.');
    } finally {
      btn.disabled = false; spin.style.display = 'none'; txt.textContent = 'Publish';
    }
  },

  cpError(msg) {
    const el = document.getElementById('cp-error');
    if (el) { el.textContent = msg; el.style.display = 'block'; }
  },

  async fetch(replace) {
    const feed = document.getElementById('notices-feed');
    if (!feed) return;
    if (replace) feed.innerHTML = this.skelHTML();

    try {
      const params = new URLSearchParams({ page: this._page, limit: 20 });
      if (this._q) params.set('search', this._q);
      const data = await api('posts/index.php?' + params.toString());
      const posts = data.posts || [];

      if (replace) feed.innerHTML = '';

      if (!posts.length && replace) {
        feed.innerHTML = `<div class="empty-state">
          <div class="empty-icon">&#x1F4CB;</div>
          <div class="empty-title">No notices yet</div>
          <div class="empty-sub">Check back later for official announcements.</div>
        </div>`;
        return;
      }

      posts.forEach(p => {
        feed.insertAdjacentHTML('beforeend', this.renderCard(p));
        if (!p.read) setTimeout(() => this.markRead(p.id), 2500);
      });

      this._hasMore = this._page * 20 < (data.total || 0);

    } catch(err) {
      const feed2 = document.getElementById('notices-feed');
      if (feed2 && replace) {
        feed2.innerHTML = `<div class="empty-state">
          <div class="empty-icon">&#x26A0;</div>
          <div class="empty-title">Could not load notices</div>
          <div class="empty-sub">${err.message || 'Please refresh the page.'}</div>
        </div>`;
      }
    }
  },

  async markRead(postId) {
    try {
      await api('posts/read.php', { method:'POST', body: JSON.stringify({ post_id: postId }) });
      const title = document.querySelector(`.post-card[data-id="${postId}"] .pc-title`);
      if (title) title.classList.remove('unread');
    } catch(_) {}
  },

  async vote(pollId, optionId, cardEl) {
    try {
      const data = await api('polls/vote.php', {
        method: 'POST', body: JSON.stringify({ poll_id: pollId, option_id: optionId })
      });
      // Re-render the whole poll block in-place, preserving all poll metadata from response
      const pollBlock = cardEl.querySelector('.poll-block');
      if (pollBlock) {
        pollBlock.outerHTML = renderPollBlock({
          id:                   pollId,
          is_closed:            data.is_closed    || false,
          is_anonymous:         data.is_anonymous || false,
          ends_at:              data.ends_at      || null,
          options:              data.options,
          total_votes:          data.total_votes,
          user_voted_option_id: data.user_voted_option_id,
        });
      }
    } catch(err) {
      showToast(err.message, 'error');
    }
  },

  renderCard(p) {
    const date = p.created_at
      ? new Date(p.created_at).toLocaleDateString('en-GB', {weekday:'short', day:'2-digit', month:'short', year:'2-digit'})
      : '';
    const priClass = {urgent:'post-card--urgent', info:'post-card--info', general:'post-card--general'}[p.priority] || 'post-card--general';
    const pinClass = p.is_pinned ? ' post-card--pinned' : '';
    const unread   = !p.read ? ' unread' : '';
    const badges   = [
      p.is_pinned             ? '<span class="badge badge-pin">&#x1F4CC; Pinned</span>' : '',
      p.priority === 'urgent' ? '<span class="badge badge-urgent">Urgent</span>'        : '',
      p.priority === 'info'   ? '<span class="badge badge-info">Info</span>'            : '',
      p.post_type === 'event' ? '<span class="badge badge-event">&#x1F4C5; Event</span>': '',
      p.post_type === 'poll'  ? '<span class="badge badge-poll">&#x1F4CA; Poll</span>'  : '',
    ].filter(Boolean).join('');

    const image = p.image_path
      ? `<img class="pc-image" src="/uploads/${esc(p.image_path)}" alt="" loading="lazy">` : '';

    const eventBlock = (p.post_type === 'event' && p.event_date)
      ? `<div class="event-block"><span class="event-icon">&#x1F4C5;</span>
          <span>${fmtEventDate(p.event_date, p.event_time)}</span>
          ${p.event_type ? `<span class="event-type-tag">${p.event_type}</span>` : ''}
        </div>` : '';

    const pollBlock = (p.post_type === 'poll' && p.poll) ? renderPollBlock(p.poll) : '';

    const readInfo = (p.read_count !== null && p.read_count !== undefined)
      ? `<span style="font-size:11px;color:var(--txt-3)">${p.read_count} read</span>` : '';

    return `<div class="post-card ${priClass}${pinClass}" data-id="${p.id}">
      <div class="pc-header">
        <div class="pc-logo"><div class="pc-logo-text">GSSC</div></div>
        <div class="pc-meta">
          <div class="pc-author">GSSC-science official</div>
          <div class="pc-date">${date}</div>
        </div>
        <div class="pc-badges">${badges}</div>
      </div>
      <div class="pc-body">
        <div class="pc-title${unread}">${esc(p.title)}</div>
        ${p.body ? `<div class="pc-text">${esc(p.body)}</div>` : ''}
        ${image}${eventBlock}${pollBlock}
      </div>
      <div class="pc-footer">${readInfo}</div>
    </div>`;
  },

  skelHTML() {
    return [1,2,3].map(() => `<div class="post-card">
      <div class="pc-header">
        <div class="skeleton" style="width:36px;height:36px;border-radius:50%;flex-shrink:0"></div>
        <div style="flex:1;display:flex;flex-direction:column;gap:5px">
          <div class="skeleton" style="width:130px;height:12px;border-radius:6px"></div>
          <div class="skeleton" style="width:80px;height:10px;border-radius:6px"></div>
        </div>
      </div>
      <div class="pc-body">
        <div class="skeleton" style="width:75%;height:16px;border-radius:6px;margin-bottom:8px"></div>
        <div class="skeleton" style="width:100%;height:11px;border-radius:6px;margin-bottom:4px"></div>
        <div class="skeleton" style="width:60%;height:11px;border-radius:6px"></div>
      </div>
    </div>`).join('');
  }
};

function renderPollBlock(poll) {
  const hasVoted = poll.user_voted_option_id !== null && poll.user_voted_option_id !== undefined;
  const isClosed = poll.is_closed || (poll.ends_at && new Date(poll.ends_at) < new Date());
  const showRes  = hasVoted || isClosed;
  const total    = poll.total_votes || 0;
  const maxVotes = Math.max(...(poll.options || []).map(o => o.votes), 0);

  const opts = (poll.options || []).map(o => {
    const pct    = total ? Math.round((o.votes / total) * 100) : 0;
    const voted  = o.id === poll.user_voted_option_id;
    const isWin  = o.votes === maxVotes && o.votes > 0;

    if (showRes) {
      const clickable = hasVoted && !isClosed;
      return `<div class="poll-opt-result${voted ? ' poll-opt-voted' : ''}${isWin && isClosed ? ' poll-opt-win' : ''}"
        ${clickable ? `onclick="Notices.vote(${poll.id},${o.id},this.closest('.post-card'))" style="cursor:pointer"` : ''}>
        <div class="poll-opt-bar" style="width:${pct}%"></div>
        <div class="poll-opt-inner">
          <span class="poll-opt-label">
            ${voted ? '<span class="poll-check">&#x2713;</span>' : (isWin && isClosed ? '<span class="poll-win-dot"></span>' : '')}
            ${esc(o.text)}
          </span>
          <span class="poll-opt-pct">${pct}%</span>
        </div>
      </div>`;
    }

    return `<button class="poll-opt-btn" onclick="Notices.vote(${poll.id},${o.id},this.closest('.post-card'))">
      <span class="poll-opt-dot"></span>
      <span>${esc(o.text)}</span>
    </button>`;
  }).join('');

  const endInfo = poll.ends_at && !isClosed
    ? `<span>&#128338; Ends ${new Date(poll.ends_at).toLocaleDateString('en-GB',{day:'2-digit',month:'short'})}</span>`
    : isClosed ? `<span class="poll-closed-tag">Closed</span>` : '';

  const hint = hasVoted && !isClosed
    ? `<span class="poll-change-hint">Tap to change &middot; tap yours to remove</span>` : '';

  return `<div class="poll-block">
    <div class="poll-opts">${opts}</div>
    <div class="poll-footer">
      <span class="poll-total">${total} vote${total !== 1 ? 's' : ''}</span>
      ${endInfo}
      ${hint}
    </div>
  </div>`;
}

function fmtEventDate(dateStr, timeStr) {
  const d = new Date(dateStr);
  let str = d.toLocaleDateString('en-GB', {weekday:'long', day:'numeric', month:'long', year:'numeric'});
  if (timeStr) str += ' at ' + timeStr.substring(0, 5);
  return str;
}

function esc(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}