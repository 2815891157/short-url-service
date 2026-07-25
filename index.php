<?php
// 用户页面 —— 只能创建短链接，不能查看/删除别人的
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>短链接服务</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header class="header">
    <div class="container">
      <h1 class="logo"><i class="ph ph-link"></i> 短链接服务</h1>
    </div>
  </header>
  <main class="container" style="padding-top:32px;padding-bottom:32px">
    <div class="card">
      <h2 class="card-title"><i class="ph ph-link"></i> 创建短链接</h2>
      <form id="create-form">
        <div class="form-group">
          <label for="original-url">目标网址 <span class="required">*</span></label>
          <input type="url" id="original-url" placeholder="https://example.com/your-long-url" required>
        </div>
        <div class="form-row">
          <div class="form-group flex-1">
            <label for="custom-slug">自定义后缀 <span class="optional">（可选）</span></label>
            <div class="input-with-prefix">
              <span class="input-prefix">/s/</span>
              <input type="text" id="custom-slug" placeholder="my-link">
            </div>
          </div>
          <div class="form-group flex-1">
            <label for="page-title">标题 <span class="optional">（可选）</span></label>
            <input type="text" id="page-title" placeholder="给链接起个名字">
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-full"><i class="ph ph-magic-wand"></i> 生成短链接</button>
      </form>
      <div id="create-result" class="result-box hidden">
        <div class="result-header"><i class="ph ph-check-circle"></i> 短链接已创建</div>
        <div class="result-body">
          <div class="result-url" id="result-url"></div>
          <button class="btn btn-secondary" id="copy-btn"><i class="ph ph-copy"></i> 复制</button>
        </div>
      </div>
    </div>
  </main>
  <footer class="footer"><div class="container"><i class="ph ph-shield-check"></i> 短链接服务</div></footer>
  <script>
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
      const r = await fetch('api.php/links', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({url, slug: slug||undefined, title: title||undefined}) });
      const d = await r.json();
      if (!r.ok) { alert(d.error||'创建失败'); return; }
      resultUrl.textContent = d.shortUrl;
      createResult.classList.remove('hidden');
      document.getElementById('original-url').value = '';
      document.getElementById('custom-slug').value = '';
      document.getElementById('page-title').value = '';
    } catch(e) { alert('网络错误'); }
  });

  copyBtn.addEventListener('click', () => {
    const t = resultUrl.textContent;
    if (!t) return;
    if (navigator.clipboard) { navigator.clipboard.writeText(t).then(ok); } else { fallback(t); }
    function ok() { copyBtn.innerHTML='<i class="ph ph-check"></i> 已复制'; setTimeout(()=>{copyBtn.innerHTML='<i class="ph ph-copy"></i> 复制'},2000); }
    function fallback(s) { const a=document.createElement('textarea');a.value=s;a.style.cssText='position:fixed;left:-9999px';document.body.appendChild(a);a.select();document.execCommand('copy');document.body.removeChild(a);ok(); }
  });
  </script>
</body>
</html>
