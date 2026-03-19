/* ============================================================
   storage.js
   ============================================================ */
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
      const params = new URLSearchParams({
        page: 1, limit: 30,
        ...(this._q && { search: this._q }),
        ...(this._category && { category: this._category })
      });
      const { files } = await api(`storage/index.php?${params}`);
      list.innerHTML = '';
      if (!files.length) {
        list.innerHTML = `<div class="empty-state">
          <div class="empty-icon"></div>
          <div class="empty-title">No files here yet</div>
          <div class="empty-sub">Be the first to upload a file.</div>
        </div>`;
        return;
      }
      files.forEach(f => list.insertAdjacentHTML('beforeend', this.renderRow(f)));
    } catch (e) { showToast('Could not load files', 'error'); }
  },

  renderRow(f) {
    const iconMap = {
      pdf:' file-icon--pdf', doc:' file-icon--doc', docx:' file-icon--doc',
      ppt:' file-icon--ppt', pptx:' file-icon--ppt',
      jpg:' file-icon--img', jpeg:' file-icon--img', png:' file-icon--img',
      zip:' file-icon--zip'
    };
    const [icon, cls] = (iconMap[f.file_type] || ' file-icon--default').split(' ');
    const size  = fmtBytes(f.file_size);
    const date  = new Date(f.created_at).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'2-digit'});
    const pend  = !f.is_approved ? '<span class="badge badge-poll" style="margin-left:6px">Pending</span>' : '';

    return `
    <div class="storage-row" data-id="${f.id}">
      <div class="file-icon ${cls}">${icon}</div>
      <div class="file-info">
        <div class="file-title">${esc(f.title)}${pend}</div>
        <div class="file-meta">${esc(f.file_type.toUpperCase())}  ${size}  ${date}   ${f.download_count}</div>
      </div>
      <button class="file-dl-btn" onclick="window.location='/api/storage/download.php?id=${f.id}'" title="Download">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="8,17 12,21 16,17"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
      </button>
    </div>`;
  },

  bindUpload() {
    document.getElementById('btn-upload')?.addEventListener('click', () => this.openUploadModal());
  },

  openUploadModal() {
    // Simple inline upload prompt for now
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.zip';
    input.onchange = async (e) => {
      const file = e.target.files[0];
      if (!file) return;
      const title = prompt('File title:', file.name.replace(/\.[^.]+$/, ''));
      if (!title) return;
      const fd = new FormData();
      fd.append('file', file);
      fd.append('title', title);
      fd.append('category', 'notes');
      fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
      try {
        showToast('Uploading', 'info');
        const res  = await fetch('/api/storage/upload.php', { method:'POST', body:fd, credentials:'same-origin', headers:{'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content} });
        const data = await res.json();
        if (data.success) {
          showToast(data.data.message, 'success');
          this._loaded = false;
          this.fetch();
        } else {
          showToast(data.error || 'Upload failed', 'error');
        }
      } catch(_) { showToast('Upload failed', 'error'); }
    };
    input.click();
  },

  skelHTML() {
    return Array(4).fill(0).map(() => `
      <div class="storage-row" style="pointer-events:none">
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
  if (b < 1024) return b + ' B';
  if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
  return (b/1048576).toFixed(1) + ' MB';
}
