'use strict';

const Storage_ = {
  _loaded: false,
  _q: '',
  _category: '',

  load() {
    if (this._loaded) return;
    this._loaded = true;
    this.fetch();
    this.bindUpload();
    this.bindCategoryTabs();
  },

  search(q) { this._q = q; this._loaded = false; this.fetch(); },

  // Force a fresh reload (e.g. after upload)
  reload() { this._loaded = false; this.fetch(); },

  bindCategoryTabs() {
    document.querySelectorAll('[data-storage-cat]').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('[data-storage-cat]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        this._category = btn.dataset.storageCat;
        this._loaded = false;
        this.fetch();
      });
    });
  },

  async fetch() {
    const list = document.getElementById('storage-list');
    if (!list) return;
    list.innerHTML = this.skelHTML();
    try {
      const params = new URLSearchParams({ page: 1, limit: 30 });
      if (this._q) params.set('search', this._q);
      if (this._category) params.set('category', this._category);
      const data = await api('storage/index.php?' + params.toString());
      const files = data.files || [];
      list.innerHTML = '';
      if (!files.length) {
        list.innerHTML = `<div class="empty-state">
          <div class="empty-icon">&#x1F5C2;</div>
          <div class="empty-title">No files here yet</div>
          <div class="empty-sub">Upload the first file using the button below.</div>
        </div>`;
        return;
      }
      files.forEach(f => list.insertAdjacentHTML('beforeend', this.renderRow(f)));
    } catch(err) {
      // Reset _loaded so the user can retry by clicking Storage again
      this._loaded = false;

      const list2 = document.getElementById('storage-list');
      if (list2) {
        list2.innerHTML = `<div class="empty-state">
          <div class="empty-icon">&#x26A0;</div>
          <div class="empty-title">Could not load files</div>
          <div class="empty-sub">${err.message || 'Tap here to retry.'}</div>
        </div>`;
        const errEl = list2.querySelector('.empty-state');
        if (errEl) {
          errEl.style.cursor = 'pointer';
          errEl.addEventListener('click', () => { this.reload(); });
        }
      }
    }
  },

  renderRow(f) {
    const icons = {
      pdf:['&#x1F4C4;','file-icon--pdf'], doc:['&#x1F4DD;','file-icon--doc'],
      docx:['&#x1F4DD;','file-icon--doc'], ppt:['&#x1F4CA;','file-icon--ppt'],
      pptx:['&#x1F4CA;','file-icon--ppt'], jpg:['&#x1F5BC;','file-icon--img'],
      jpeg:['&#x1F5BC;','file-icon--img'], png:['&#x1F5BC;','file-icon--img'],
      zip:['&#x1F4E6;','file-icon--zip']
    };
    const [icon, cls] = icons[f.file_type] || ['&#x1F4C1;','file-icon--default'];
    const size = fmtBytes(f.file_size || 0);
    const date = f.created_at
      ? new Date(f.created_at).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'2-digit'}) : '';
    const pend = !f.is_approved
      ? '<span class="badge badge-poll" style="margin-left:6px;font-size:10px">Pending</span>' : '';
    return `<div class="storage-row" data-id="${f.id}">
      <div class="file-icon ${cls}">${icon}</div>
      <div class="file-info">
        <div class="file-title">${esc(f.title || '')}${pend}</div>
        <div class="file-meta">${esc((f.file_type || '').toUpperCase())} &middot; ${size} &middot; ${date} &middot; &#x2B07; ${f.download_count || 0}</div>
      </div>
      <button class="file-dl-btn" onclick="window.location='/api/storage/download.php?id=${f.id}'" title="Download">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="8,17 12,21 16,17"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
      </button>
    </div>`;
  },

  bindUpload() {
    document.getElementById('btn-upload')?.addEventListener('click', () => this.openUpload());
  },

  openUpload() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.zip';
    input.onchange = async ev => {
      const file = ev.target.files[0];
      if (!file) return;
      const title = prompt('File title (required):', file.name.replace(/\.[^.]+$/, ''));
      if (!title || !title.trim()) return;
      const fd = new FormData();
      fd.append('file', file);
      fd.append('title', title.trim());
      fd.append('category', 'notes');
      showToast('Uploading...');
      try {
        const res  = await fetch('/api/storage/upload.php', {
          method: 'POST',
          headers: { 'X-CSRF-Token': getCsrf() },
          body: fd, credentials: 'same-origin'
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.data.message || 'Uploaded!', 'success');
          this.reload();
        } else { showToast(data.error || 'Upload failed', 'error'); }
      } catch(_) { showToast('Upload failed', 'error'); }
    };
    input.click();
  },

  skelHTML() {
    return [1,2,3,4].map(() => `<div class="storage-row" style="pointer-events:none">
      <div class="skeleton" style="width:40px;height:40px;border-radius:10px;flex-shrink:0"></div>
      <div style="flex:1;display:flex;flex-direction:column;gap:5px">
        <div class="skeleton" style="width:55%;height:13px;border-radius:6px"></div>
        <div class="skeleton" style="width:38%;height:10px;border-radius:6px"></div>
      </div>
      <div class="skeleton" style="width:34px;height:34px;border-radius:50%;flex-shrink:0"></div>
    </div>`).join('');
  }
};

function fmtBytes(b) {
  if (!b) return '0 B';
  if (b < 1024) return b + ' B';
  if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
  return (b/1048576).toFixed(1) + ' MB';
}

function esc(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}