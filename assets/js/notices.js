/* ============================================================
   notices.js  Notice Board + Poll voting
   ============================================================ */
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
    this.bindInfiniteScroll();
  },

  search(q) {
    this._q = q;
    this._loaded = false;
    this._page = 1;
    this.load();
  },

  bindInfiniteScroll() {
    const feed = document.getElementById('notices-feed');
    if (!feed) return;
    feed.addEventListener('scroll', () => {
      if (!this._hasMore) return;
      if (feed.scrollTop + feed.clientHeight >= feed.scrollHeight - 100) {
        this._page++;
        this.fetch(false);
      }
    });
  },

  async fetch(replace = true) {
    const feed = document.getElementById('notices-feed');
    if (!feed) return;

    if (replace) {
      feed.innerHTML = this.skeletonHTML();
    }

    try {
      const params = new URLSearchParams({
        page: this._page,
        limit: 20,
        ...(this._q && { search: this._q })
      });
      const { posts, total } = await api(`posts/index.php?${params}`);

      if (replace) feed.innerHTML = '';

      if (!posts.length && replace) {
        feed.innerHTML = `<div class="empty-state">
          <div class="empty-icon"></div>
          <div class="empty-title">No notices yet</div>
          <div class="empty-sub">Check back later for official announcements.</div>
        </div>`;
        return;
      }

      posts.forEach(p => {
        feed.insertAdjacentHTML('beforeend', this.renderCard(p));
        // Mark as read after 2s
        if (!p.read) setTimeout(() => this.markRead(p.id), 2000);
      });

      this._hasMore = this._page * 20 < total;

    } catch (e) {
      showToast('Could not load notices', 'error');
    }
  },

  async markRead(postId) {
    try {
      await api('posts/read.php', {
        method: 'POST',
        body: JSON.stringify({ post_id: postId })
      });
      // Remove unread style
      const card = document.querySelector(`.post-card[data-id="${postId}"] .pc-title`);
      if (card) card.classList.remove('unread');
    } catch (_) {}
  },

  async vote(pollId, optionId, cardEl) {
    try {
      const { options, total_votes, user_voted_option_id } = await api('polls/vote.php', {
        method: 'POST',
        body: JSON.stringify({ poll_id: pollId, option_id: optionId })
      });
      this.renderPollResults(cardEl, options, total_votes, user_voted_option_id);
    } catch (e) {
      showToast(e.message, 'error');
    }
  },

  renderPollResults(pollEl, options, total, votedId) {
    const container = pollEl.querySelector('.poll-options');
    if (!container) return;
    container.innerHTML = options.map(o => {
      const pct = total ? Math.round((o.votes / total) * 100) : 0;
      const isVoted = o.id === votedId;
      return `
        <div class="poll-option poll-option--result ${isVoted ? 'poll-option--voted' : ''}">
          <div class="poll-option-bar" style="width:${pct}%"></div>
          <span class="poll-option-text">${esc(o.text)}</span>
          <span class="poll-option-pct">${pct}%</span>
        </div>`;
    }).join('');
    const meta = pollEl.querySelector('.poll-meta');
    if (meta) meta.textContent = `${total} vote${total !== 1 ? 's' : ''}`;
  },

  renderCard(p) {
    const date = new Date(p.created_at).toLocaleDateString('en-GB', {
      weekday: 'short', day: '2-digit', month: 'short', year: '2-digit'
    }).replace(',', '');

    const priorityClass = { urgent: 'post-card--urgent', info: 'post-card--info', general: 'post-card--general' }[p.priority] || '';
    const pinClass = p.is_pinned ? ' post-card--pinned' : '';
    const unread   = !p.read ? ' unread' : '';

    const badges = [
      p.is_pinned             ? '<span class="badge badge-pin"> Pinned</span>'    : '',
      p.priority === 'urgent' ? '<span class="badge badge-urgent">Urgent</span>'    : '',
      p.priority === 'info'   ? '<span class="badge badge-info">Info</span>'        : '',
      p.post_type === 'event' ? '<span class="badge badge-event"> Event</span>'   : '',
      p.post_type === 'poll'  ? '<span class="badge badge-poll"> Poll</span>'     : '',
    ].filter(Boolean).join('');

    const image = p.image_path
      ? `<img class="pc-image" src="/uploads/${esc(p.image_path)}" alt="" loading="lazy">`
      : '';

    const eventBlock = p.post_type === 'event' && p.event_date ? `
      <div class="event-block">
        <span class="event-icon"></span>
        <span>${formatEventDate(p.event_date, p.event_time)}</span>
        ${p.event_type ? `<span class="event-type-tag">${p.event_type}</span>` : ''}
      </div>` : '';

    const pollBlock = p.post_type === 'poll' && p.poll ? renderPollBlock(p.poll) : '';

    const readCount = p.read_count !== null
      ? `<span style="font-size:11px;color:var(--txt-3)">${p.read_count} read</span>` : '';

    return `
    <div class="post-card ${priorityClass}${pinClass}" data-id="${p.id}">
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
        ${image}
        ${eventBlock}
        ${pollBlock}
      </div>
      <div class="pc-footer">${readCount}</div>
    </div>`;
  },

  skeletonHTML() {
    return Array(3).fill(0).map(() => `
      <div class="post-card">
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
  const hasVoted   = poll.user_voted_option_id !== null;
  const isClosed   = poll.is_closed || (poll.ends_at && new Date(poll.ends_at) < new Date());
  const showResults = hasVoted || isClosed;

  const opts = poll.options.map(o => {
    const pct = poll.total_votes ? Math.round((o.votes / poll.total_votes) * 100) : 0;
    if (showResults) {
      const voted = o.id === poll.user_voted_option_id;
      return `<div class="poll-option poll-option--result ${voted ? 'poll-option--voted' : ''}">
        <div class="poll-option-bar" style="width:${pct}%"></div>
        <span class="poll-option-text">${esc(o.text)}</span>
        <span class="poll-option-pct">${pct}%</span>
      </div>`;
    }
    return `<button class="poll-option poll-option--btn" onclick="Notices.vote(${poll.id},${o.id},this.closest('.post-card'))">
      ${esc(o.text)}
    </button>`;
  }).join('');

  const endInfo = poll.ends_at && !isClosed
    ? ` ends ${new Date(poll.ends_at).toLocaleDateString('en-GB',{day:'2-digit',month:'short'})}`
    : isClosed ? ' closed' : '';

  return `
  <div class="poll-block">
    <div class="poll-options">${opts}</div>
    <div class="poll-meta">${poll.total_votes} vote${poll.total_votes !== 1 ? 's' : ''} ${endInfo}</div>
  </div>`;
}

function formatEventDate(dateStr, timeStr) {
  const d = new Date(dateStr);
  const opts = { weekday:'long', day:'numeric', month:'long', year:'numeric' };
  let str = d.toLocaleDateString('en-GB', opts);
  if (timeStr) str += '  ' + timeStr.substring(0, 5);
  return str;
}

function esc(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
