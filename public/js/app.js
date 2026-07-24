// ============ 标签页切换 ============
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

// ============ 工具函数 ============
function escapeAttr(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

function escapeHtml(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return d.toLocaleDateString('zh-CN', { month: 'short', day: 'numeric', year: 'numeric' });
}

function copyToClipboard(text) {
  if (navigator.clipboard && navigator.clipboard.writeText) {
    return navigator.clipboard.writeText(text).then(() => true).catch(() => false);
  }
  // 降级方案
  const ta = document.createElement('textarea');
  ta.value = text;
  ta.style.position = 'fixed';
  ta.style.left = '-9999px';
  document.body.appendChild(ta);
  ta.select();
  try {
    document.execCommand('copy');
    document.body.removeChild(ta);
    return Promise.resolve(true);
  } catch (e) {
    document.body.removeChild(ta);
    return Promise.resolve(false);
  }
}

// ============ 创建短链接 ============
const createForm = document.getElementById('create-form');
const createResult = document.getElementById('create-result');
const resultUrl = document.getElementById('result-url');
const copyBtn = document.getElementById('copy-btn');

createForm.addEventListener('submit', async (e) => {
  e.preventDefault();

  const url = document.getElementById('original-url').value.trim();
  const slug = document.getElementById('custom-slug').value.trim();
  const title = document.getElementById('page-title').value.trim();

  if (!url) return;

  try {
    const resp = await fetch('/api/links', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ url, slug: slug || undefined, title: title || undefined })
    });

    const data = await resp.json();

    if (!resp.ok) {
      alert(data.error || '创建失败');
      return;
    }

    resultUrl.textContent = data.shortUrl;
    createResult.classList.remove('hidden');

    document.getElementById('original-url').value = '';
    document.getElementById('custom-slug').value = '';
    document.getElementById('page-title').value = '';

    loadLinks();
  } catch (err) {
    alert('网络错误：' + err.message);
  }
});

copyBtn.addEventListener('click', () => {
  const url = resultUrl.textContent;
  if (!url) return;
  copyToClipboard(url).then(ok => {
    if (ok) {
      copyBtn.innerHTML = '<i class="ph ph-check"></i> 已复制';
      setTimeout(() => { copyBtn.innerHTML = '<i class="ph ph-copy"></i> 复制'; }, 2000);
    }
  });
});

// ============ 管理短链接 ============
const linksList = document.getElementById('links-list');
const noLinks = document.getElementById('no-links');
const refreshBtn = document.getElementById('refresh-btn');

// 存储链接数据，用于事件委托
let cachedLinks = [];

async function loadLinks() {
  try {
    const resp = await fetch('/api/links');
    const links = await resp.json();

    cachedLinks = links;

    if (links.length === 0) {
      linksList.innerHTML = '';
      noLinks.classList.remove('hidden');
      return;
    }

    noLinks.classList.add('hidden');

    linksList.innerHTML = links.map((link, i) => `
      <div class="link-item" data-index="${i}">
        <div class="link-header">
          <span class="link-slug">
            <a href="/s/${escapeHtml(link.slug)}" target="_blank">/s/${escapeHtml(link.slug)}</a>
          </span>
          <div class="link-actions">
            <button class="btn btn-secondary btn-sm" data-action="copy" data-index="${i}">
              <i class="ph ph-copy"></i> 复制
            </button>
            <button class="btn btn-secondary btn-sm" data-action="validate" data-index="${i}">
              <i class="ph ph-magnifying-glass"></i> 检测
            </button>
            <button class="btn btn-danger btn-sm" data-action="delete" data-index="${i}">
              <i class="ph ph-trash"></i> 删除
            </button>
          </div>
        </div>
        <div class="link-original">
          <a href="${escapeHtml(link.original_url)}" target="_blank">${escapeHtml(link.original_url)}</a>
        </div>
        <div class="link-meta">
          <span><i class="ph ph-eye"></i> ${link.visit_count} 次访问</span>
          <span><i class="ph ph-calendar"></i> ${formatDate(link.created_at)}</span>
        </div>
      </div>
    `).join('');
  } catch (err) {
    linksList.innerHTML = '<div class="empty-state"><i class="ph ph-warning-circle"></i> 加载失败</div>';
  }
}

// 事件委托 —— 替代 inline onclick，防止 XSS
linksList.addEventListener('click', async (e) => {
  const btn = e.target.closest('[data-action]');
  if (!btn) return;

  const action = btn.dataset.action;
  const index = parseInt(btn.dataset.index, 10);
  const link = cachedLinks[index];
  if (!link) return;

  if (action === 'copy') {
    const fullUrl = window.location.origin + '/s/' + link.slug;
    copyToClipboard(fullUrl).then(ok => {
      if (ok) {
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="ph ph-check"></i> 已复制';
        setTimeout(() => { btn.innerHTML = original; }, 1500);
      }
    });
  }

  if (action === 'validate') {
    document.getElementById('validate-url').value = link.original_url;
    navTabs.forEach(t => t.classList.remove('active'));
    tabContents.forEach(c => c.classList.remove('active'));
    document.querySelector('[data-tab="validate"]').classList.add('active');
    document.getElementById('tab-validate').classList.add('active');
    validateForm.dispatchEvent(new Event('submit'));
  }

  if (action === 'delete') {
    if (!confirm('确定要删除这条短链接吗？')) return;
    try {
      await fetch('/api/links/' + encodeURIComponent(link.slug), { method: 'DELETE' });
      loadLinks();
    } catch (err) {
      alert('删除失败');
    }
  }
});

refreshBtn.addEventListener('click', loadLinks);

// ============ 检测网址 ============
const validateForm = document.getElementById('validate-form');
const validateResult = document.getElementById('validate-result');

validateForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const url = document.getElementById('validate-url').value.trim();
  if (!url) return;

  validateResult.classList.remove('hidden');
  validateResult.innerHTML = `
    <div class="validate-status" style="border-left-color: var(--accent);">
      <div class="status-title" style="color: var(--accent);">
        <i class="ph ph-spinner"></i> 检测中...
      </div>
      <div class="status-detail">正在解析 DNS 并发送 HEAD 请求...</div>
    </div>
  `;

  try {
    const resp = await fetch('/api/validate-url', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ url })
    });

    const data = await resp.json();

    if (data.error) {
      validateResult.innerHTML = `
        <div class="validate-status invalid">
          <div class="status-title"><i class="ph ph-x-circle"></i> 错误</div>
          <div class="status-detail">${escapeHtml(data.error)}</div>
        </div>
      `;
      return;
    }

    const icon = data.valid ? 'ph-check-circle' : 'ph-x-circle';
    const cls = data.valid ? 'valid' : 'invalid';
    const label = data.valid ? '有效' : '无效';

    validateResult.innerHTML = `
      <div class="validate-status ${cls}">
        <div class="status-title"><i class="ph ${icon}"></i> ${label} - ${escapeHtml(data.reason)}</div>
        ${data.status ? `<div class="status-detail">HTTP 状态码: ${data.status}</div>` : ''}
        <div class="status-detail">${escapeHtml(data.details)}</div>
      </div>
    `;
  } catch (err) {
    validateResult.innerHTML = `
      <div class="validate-status invalid">
        <div class="status-title"><i class="ph ph-warning-circle"></i> 网络错误</div>
        <div class="status-detail">${escapeHtml(err.message)}</div>
      </div>
    `;
  }
});

// ============ 初始化 ============
loadLinks();
