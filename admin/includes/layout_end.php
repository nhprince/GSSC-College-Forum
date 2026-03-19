<!--  PAGE CONTENT ENDS  -->
  </div><!-- /.a-content -->
</div><!-- /.a-main -->

<div id="toast-container"></div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

async function api(endpoint, options = {}) {
  const isForm = options.body instanceof FormData;
  const headers = { 'X-CSRF-Token': CSRF };
  if (!isForm) headers['Content-Type'] = 'application/json';
  const res  = await fetch('/api/' + endpoint, { credentials:'same-origin', headers:{...headers,...(options.headers||{})}, ...options });
  const data = await res.json();
  if (!data.success) throw new Error(data.error || 'Error');
  return data.data;
}

function showToast(msg, type='info') {
  const el = document.createElement('div');
  el.className = `toast toast--${type}`;
  el.textContent = msg;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(() => el.remove(), 4000);
}

function confirm_(msg) { return window.confirm(msg); }
</script>
</body>
</html>
