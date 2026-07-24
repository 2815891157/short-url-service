// 标签页切换
const navTabs = document.querySelectorAll('.nav-tab');
const tabContents = document.querySelectorAll('.tab-content');
navTabs.forEach(tab => {
  tab.addEventListener('click', () => {
    navTabs.forEach(t => t.classList.remove('active'));
    tabContents.forEach(c => c.classList.remove('active'));
    tab.classList.add('active');
    document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
  });
});

// 工具
function escapeHtml(s) { if (!s) return ''; const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
function formatDate(s) { if (!s) return ''; return new Date(s).toLocaleDateString('zh-CN', { month: 'short', day: 'numeric', year: 'numeric' }); }
function copyToClipboard(t) {
  if (navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(t).then(() => true).catch(() => false);
  const ta = document.createElement('textarea'); ta.value = t; ta.style.cssText = 'position:fixed;left:-9999px';
  document.body.appendChild(ta); ta.select();
  try { document.execCommand('copy'); document.body.removeChild(ta); return Promise.resolve(true); } catch(e) { document.body.removeChild(ta); return Promise.resolve(false); }
}

// 创建
const createForm = document.getElementById('create-form');
const createResult = document.getElementById('create-result');
const resultUrl = document.getElementById('result-url');
const copyBtn = document.getElementById('copy-btn');

createForm.addEventListener('submit', async e => {
  e.preventDefault();
  const url = document.getElementById('original-url').value.trim();
  const slug = document.getElementById('custom-slug').value.trim();
  const title = document.getElementById('page-title').value.trim();
  if (!url) return;
  try {
    const r = await fetch('api.php/links', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ url, slug: slug || undefined, title: title || undefined }) });
    const d = await r.json();
    if (!r.ok) { alert(d.error || '创建失败'); return; }
    resultUrl.textContent = d.shortUrl;
    createResult.classList.remove('hidden');
    document.getElementById('original-url').value = '';
    document.getElementById('custom-slug').value = '';
    document.getElementById('page-title').value = '';
    loadLinks();
  } catch (err) { alert('网络错误：' + err.message); }
});

copyBtn.addEventListener('click', () => {
  const u = resultUrl.textContent;
  if (!u) return;
  copyToClipboard(u).then(ok => { if (ok) { copyBtn.innerHTML = '<i class="ph ph-check"></i> 已复制'; setTimeout(() => { copyBtn.innerHTML = '<i class="ph ph-copy"></i> 复制'; }, 2000); } });
});

// 管理
const linksList = document.getElementById('links-list');
const noLinks = document.getElementById('no-links');
const refreshBtn = document.getElementById('refresh-btn');
let cachedLinks = [];

async function loadLinks() {
  try {
    const r = await fetch('api.php/links');
    const links = await r.json();
    cachedLinks = links;
    if (links.length === 0) { linksList.innerHTML = ''; noLinks.classList.remove('hidden'); return; }
    noLinks.classList.add('hidden');
    linksList.innerHTML = links.map((l, i) => `
      <div class="link-item" data-index="${i}">
        <div class="link-header">
          <span class="link-slug"><a href="s.php?slug=${escapeHtml(l.slug)}" target="_blank">/s/${escapeHtml(l.slug)}</a></span>
          <div class="link-actions">
            <button class="btn btn-secondary btn-sm" data-action="copy" data-index="${i}"><i class="ph ph-copy"></i> 复制</button>
            <button class="btn btn-secondary btn-sm" data-action="validate" data-index="${i}"><i class="ph ph-magnifying-glass"></i> 检测</button>
            <button class="btn btn-danger btn-sm" data-action="delete" data-index="${i}"><i class="ph ph-trash"></i> 删除</button>
          </div>
        </div>
        <div class="link-original"><a href="${escapeHtml(l.original_url)}" target="_blank">${escapeHtml(l.original_url)}</a></div>
        <div class="link-meta">
          <span><i class="ph ph-eye"></i> ${l.visit_count} 次访问</span>
          <span><i class="ph ph-calendar"></i> ${formatDate(l.created_at)}</span>
        </div>
      </div>`).join('');
  } catch (err) { linksList.innerHTML = '<div class="empty-state"><i class="ph ph-warning-circle"></i> 加载失败</div>'; }
}

linksList.addEventListener('click', async e => {
  const btn = e.target.closest('[data-action]');
  if (!btn) return;
  const action = btn.dataset.action;
  const link = cachedLinks[parseInt(btn.dataset.index)];
  if (!link) return;

  if (action === 'copy') {
    copyToClipboard(window.location.origin + '/s/' + link.slug).then(ok => {
      if (ok) { const o = btn.innerHTML; btn.innerHTML = '<i class="ph ph-check"></i> 已复制'; setTimeout(() => { btn.innerHTML = o; }, 1500); }
    });
  }
  if (action === 'validate') {
    document.getElementById('validate-url').value = link.original_url;
    navTabs.forEach(t => t.classList.remove('active')); tabContents.forEach(c => c.classList.remove('active'));
    document.querySelector('[data-tab="validate"]').classList.add('active');
    document.getElementById('tab-validate').classList.add('active');
    validateForm.dispatchEvent(new Event('submit'));
  }
  if (action === 'delete') {
    if (!confirm('确定要删除这条短链接吗？')) return;
    try { await fetch('api.php/links/' + encodeURIComponent(link.slug), { method: 'DELETE' }); loadLinks(); } catch (err) { alert('删除失败'); }
  }
});

refreshBtn.addEventListener('click', loadLinks);

// 检测
const validateForm = document.getElementById('validate-form');
const validateResult = document.getElementById('validate-result');

validateForm.addEventListener('submit', async e => {
  e.preventDefault();
  const url = document.getElementById('validate-url').value.trim();
  if (!url) return;
  validateResult.classList.remove('hidden');
  validateResult.innerHTML = '<div class="validate-status" style="border-left-color:var(--accent)"><div class="status-title" style="color:var(--accent)"><i class="ph ph-spinner"></i> 检测中...</div><div class="status-detail">正在解析 DNS 并发送 HEAD 请求...</div></div>';
  try {
    const r = await fetch('api.php/validate-url', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ url }) });
    const d = await r.json();
    if (d.error) { validateResult.innerHTML = `<div class="validate-status invalid"><div class="status-title"><i class="ph ph-x-circle"></i> 错误</div><div class="status-detail">${escapeHtml(d.error)}</div></div>`; return; }
    const icon = d.valid ? 'ph-check-circle' : 'ph-x-circle';
    const cls = d.valid ? 'valid' : 'invalid';
    const label = d.valid ? '有效' : '无效';
    validateResult.innerHTML = `<div class="validate-status ${cls}"><div class="status-title"><i class="ph ${icon}"></i> ${label} - ${escapeHtml(d.reason)}</div>${d.status ? `<div class="status-detail">HTTP 状态码: ${d.status}</div>` : ''}<div class="status-detail">${escapeHtml(d.details)}</div></div>`;
  } catch (err) { validateResult.innerHTML = `<div class="validate-status invalid"><div class="status-title"><i class="ph ph-warning-circle"></i> 网络错误</div><div class="status-detail">${escapeHtml(err.message)}</div></div>`; }
});

loadLinks();
