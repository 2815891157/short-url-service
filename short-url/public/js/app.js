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
      body: JSON.stringify({ url, slug, title })
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
  navigator.clipboard.writeText(url).then(() => {
    copyBtn.innerHTML = '<i class="ph ph-check"></i> 已复制';
    setTimeout(() => {
      copyBtn.innerHTML = '<i class="ph ph-copy"></i> 复制';
    }, 2000);
  });
});

// ============ 管理短链接 ============
const linksList = document.getElementById('links-list');
const noLinks = document.getElementById('no-links');
const refreshBtn = document.getElementById('refresh-btn');

async function loadLinks() {
  try {
    const resp = await fetch('/api/links');
    const links = await resp.json();

    if (links.length === 0) {
      linksList.innerHTML = '';
      noLinks.classList.remove('hidden');
      return;
    }

    noLinks.classList.add('hidden');

    linksList.innerHTML = links.map(link => `
      <div class="link-item">
        <div class="link-header">
          <span class="link-slug">
            <a href="/s/${escapeHtml(link.slug)}" target="_blank">/s/${escapeHtml(link.slug)}</a>
          </span>
          <div class="link-actions">
            <button class="btn btn-secondary btn-sm" onclick="copyShortUrl('${escapeHtml(link.slug)}')">
              <i class="ph ph-copy"></i> 复制
            </button>
            <button class="btn btn-secondary btn-sm" onclick="validateExisting('${escapeHtml(link.original_url)}')">
              <i class="ph ph-magnifying-glass"></i> 检测
            </button>
            <button class="btn btn-danger btn-sm" onclick="deleteLink('${escapeHtml(link.slug)}')">
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
    linksList.innerHTML = `<div class="empty-state"><i class="ph ph-warning-circle"></i> 加载失败</div>`;
  }
}

function copyShortUrl(slug) {
  const url = window.location.origin + '/s/' + slug;
  navigator.clipboard.writeText(url);
}

async function deleteLink(slug) {
  if (!confirm('确定要删除这条短链接吗？')) return;

  try {
    await fetch('/api/links/' + slug, { method: 'DELETE' });
    loadLinks();
  } catch (err) {
    alert('删除失败');
  }
}

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
        <i class="ph ph-spinner" style="animation: spin 1s linear infinite;"></i> 检测中...
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

function validateExisting(url) {
  document.getElementById('validate-url').value = url;
  navTabs.forEach(t => t.classList.remove('active'));
  tabContents.forEach(c => c.classList.remove('active'));
  document.querySelector('[data-tab="validate"]').classList.add('active');
  document.getElementById('tab-validate').classList.add('active');
  validateForm.dispatchEvent(new Event('submit'));
}

// ============ 工具函数 ============
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

// ============ 初始化 ============
loadLinks();
