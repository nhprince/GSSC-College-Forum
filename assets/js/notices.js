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
  },

  search(q) {
    this._q = q; this._loaded = false; this._page = 1; this.load();
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
      const container = cardEl.querySelector('.poll-options');
      if (container) {
        container.innerHTML = (data.options || []).map(o => {
          const pct = data.total_votes ? Math.round((o.votes / data.total_votes) * 100) : 0;
          const voted = o.id === data.user_voted_option_id;
          return `<div class="poll-option poll-option--result ${voted ? 'poll-option--voted' : ''}">
            <div class="poll-option-bar" style="width:${pct}%"></div>
            <span class="poll-option-text">${esc(o.text)}</span>
            <span class="poll-option-pct">${pct}%</span>
          </div>`;
        }).join('');
      }
      const meta = cardEl.querySelector('.poll-meta');
      if (meta) meta.textContent = (data.total_votes || 0) + ' votes';
    } catch(err) { showToast(err.message, 'error'); }
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
  const opts     = (poll.options || []).map(o => {
    const pct = poll.total_votes ? Math.round((o.votes / poll.total_votes) * 100) : 0;
    if (showRes) {
      const voted = o.id === poll.user_voted_option_id;
      return `<div class="poll-option poll-option--result ${voted ? 'poll-option--voted' : ''}">
        <div class="poll-option-bar" style="width:${pct}%"></div>
        <span class="poll-option-text">${esc(o.text)}</span>
        <span class="poll-option-pct">${pct}%</span>
      </div>`;
    }
    return `<button class="poll-option poll-option--btn" onclick="Notices.vote(${poll.id},${o.id},this.closest('.post-card'))">${esc(o.text)}</button>`;
  }).join('');
  const endInfo = poll.ends_at && !isClosed
    ? ' &middot; ends ' + new Date(poll.ends_at).toLocaleDateString('en-GB',{day:'2-digit',month:'short'})
    : isClosed ? ' &middot; closed' : '';
  return `<div class="poll-block">
    <div class="poll-options">${opts}</div>
    <div class="poll-meta">${poll.total_votes || 0} vote${poll.total_votes !== 1 ? 's' : ''}${endInfo}</div>
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