// 标签页切换
document.querySelectorAll('.nav-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    tab.classList.add('active');
    document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
  });
});

// 工具
function esc(s) { if (!s) return ''; const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
function fmtDate(s) { if (!s) return ''; return new Date(s).toLocaleDateString('zh-CN', { month:'short', day:'numeric', year:'numeric' }); }
function copyText(t) {
  if (navigator.clipboard && navigator.clipboard.writeText)
    return navigator.clipboard.writeText(t).then(() => true).catch(() => false);
  const a = document.createElement('textarea'); a.value = t; a.style.cssText = 'position:fixed;left:-9999px';
  document.body.appendChild(a); a.select();
  try { document.execCommand('copy'); document.body.removeChild(a); return Promise.resolve(true); }
  catch(e) { document.body.removeChild(a); return Promise.resolve(false); }
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
    const r = await fetch('/api/links', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ url, slug: slug || undefined, title: title || undefined })
    });
    const d = await r.json();
    if (!r.ok) { alert(d.error || '创建失败'); return; }
    resultUrl.textContent = d.shortUrl;
    createResult.classList.remove('hidden');
    document.getElementById('original-url').value = '';
    document.getElementById('custom-slug').value = '';
    document.getElementById('page-title').value = '';
    loadLinks();
  } catch(e) { alert('网络错误：' + e.message); }
});

copyBtn.addEventListener('click', () => {
  const u = resultUrl.textContent; if (!u) return;
  copyText(u).then(ok => { if (ok) { copyBtn.innerHTML = '<i class="ph ph-check"></i> 已复制'; setTimeout(() => { copyBtn.innerHTML = '<i class="ph ph-copy"></i> 复制'; }, 2000); } });
});

// 管理
const linksList = document.getElementById('links-list');
const noLinks = document.getElementById('no-links');
let cached = [];

async function loadLinks() {
  try {
    const r = await fetch('/api/links');
    const links = await r.json();
    cached = links;
    if (!links.length) { linksList.innerHTML = ''; noLinks.classList.remove('hidden'); return; }
    noLinks.classList.add('hidden');
    linksList.innerHTML = links.map((l, i) => `
      <div class="link-item">
        <div class="link-header">
          <span class="link-slug"><a href="/s/${esc(l.slug)}" target="_blank">/s/${esc(l.slug)}</a></span>
          <div class="link-actions">
            <button class="btn btn-secondary btn-sm" data-act="copy" data-i="${i}"><i class="ph ph-copy"></i> 复制</button>
            <button class="btn btn-secondary btn-sm" data-act="validate" data-i="${i}"><i class="ph ph-magnifying-glass"></i> 检测</button>
            <button class="btn btn-danger btn-sm" data-act="delete" data-i="${i}"><i class="ph ph-trash"></i> 删除</button>
          </div>
        </div>
        <div class="link-original"><a href="${esc(l.original_url)}" target="_blank">${esc(l.original_url)}</a></div>
        <div class="link-meta">
          <span><i class="ph ph-eye"></i> ${l.visit_count} 次访问</span>
          <span><i class="ph ph-calendar"></i> ${fmtDate(l.created_at)}</span>
        </div>
      </div>`).join('');
  } catch(e) { linksList.innerHTML = '<div class="empty-state"><i class="ph ph-warning-circle"></i> 加载失败</div>'; }
}

linksList.addEventListener('click', async e => {
  const btn = e.target.closest('[data-act]');
  if (!btn) return;
  const act = btn.dataset.act, i = +btn.dataset.i, l = cached[i];
  if (!l) return;
  if (act === 'copy') {
    copyText(window.location.origin + '/s/' + l.slug).then(ok => {
      if (ok) { const o = btn.innerHTML; btn.innerHTML = '<i class="ph ph-check"></i> 已复制'; setTimeout(() => btn.innerHTML = o, 1500); }
    });
  }
  if (act === 'validate') {
    document.getElementById('validate-url').value = l.original_url;
    document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.querySelector('[data-tab="validate"]').classList.add('active');
    document.getElementById('tab-validate').classList.add('active');
    document.getElementById('validate-form').dispatchEvent(new Event('submit'));
  }
  if (act === 'delete') {
    if (!confirm('确定要删除这条短链接吗？')) return;
    await fetch('/api/links/' + encodeURIComponent(l.slug), { method: 'DELETE' });
    loadLinks();
  }
});

document.getElementById('refresh-btn').addEventListener('click', loadLinks);

// 检测
const vForm = document.getElementById('validate-form');
const vResult = document.getElementById('validate-result');

vForm.addEventListener('submit', async e => {
  e.preventDefault();
  const url = document.getElementById('validate-url').value.trim();
  if (!url) return;
  vResult.classList.remove('hidden');
  vResult.innerHTML = '<div class="validate-status" style="border-left-color:var(--ac)"><div class="st" style="color:var(--ac)"><i class="ph ph-spinner"></i> 检测中...</div><div class="sd">正在解析 DNS 并发送 HEAD 请求...</div></div>';
  try {
    const r = await fetch('/api/validate-url', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ url }) });
    const d = await r.json();
    if (d.error) { vResult.innerHTML = `<div class="validate-status invalid"><div class="st"><i class="ph ph-x-circle"></i> 错误</div><div class="sd">${esc(d.error)}</div></div>`; return; }
    const ic = d.valid ? 'ph-check-circle' : 'ph-x-circle';
    const cls = d.valid ? 'valid' : 'invalid';
    const lb = d.valid ? '有效' : '无效';
    vResult.innerHTML = `<div class="validate-status ${cls}"><div class="st"><i class="ph ${ic}"></i> ${lb} - ${esc(d.reason)}</div>${d.status ? `<div class="sd">HTTP 状态码: ${d.status}</div>` : ''}<div class="sd">${esc(d.details)}</div></div>`;
  } catch(e) { vResult.innerHTML = `<div class="validate-status invalid"><div class="st"><i class="ph ph-warning-circle"></i> 网络错误</div><div class="sd">${esc(e.message)}</div></div>`; }
});

loadLinks();
