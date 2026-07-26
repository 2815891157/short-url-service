<?php
// 用户页面 —— 只能创建短链接
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
  <main class="container" style="padding-top:48px;padding-bottom:32px">
    <div class="card">
      <h2 class="card-title"><i class="ph ph-sparkle"></i> 创建短链接</h2>
      <form id="create-form">
        <div class="form-group">
          <label for="original-url">目标网址 <span class="required">*</span></label>
          <input type="url" id="original-url" placeholder="https://example.com/your-long-url" required maxlength="1000" autocomplete="off">
        </div>
        <button type="submit" class="btn btn-primary btn-full" id="submit-btn">
          <i class="ph ph-magic-wand"></i> 生成短链接
        </button>
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
  const RATE_KEY='shorturl_create_times',RATE_MAX=25,RATE_WINDOW=86400000;
  function getRateTimes(){try{return JSON.parse(localStorage.getItem(RATE_KEY))||[]}catch{return[]}}
  function checkRate(){const n=Date.now();const t=getRateTimes().filter(x=>n-x<RATE_WINDOW);localStorage.setItem(RATE_KEY,JSON.stringify(t));return t.length<RATE_MAX}
  function recordRate(){const t=getRateTimes();t.push(Date.now());localStorage.setItem(RATE_KEY,JSON.stringify(t))}

  const f=document.getElementById('create-form');
  const res=document.getElementById('create-result');
  const urlEl=document.getElementById('result-url');
  const copyBtn=document.getElementById('copy-btn');
  const submitBtn=document.getElementById('submit-btn');

  f.addEventListener('submit',async e=>{
    e.preventDefault();
    if(!checkRate()){alert('今天创建次数已达上限（每天 '+RATE_MAX+' 次），请明天再试');return}
    const url=document.getElementById('original-url').value.trim();
    if(!url)return;
    submitBtn.disabled=true;submitBtn.innerHTML='<i class="ph ph-spinner"></i> 生成中...';
    try{
      const r=await fetch('api.php/links',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({url})});
      if(!r.ok){const t=await r.text();try{const j=JSON.parse(t);alert(j.error||'创建失败')}catch{alert('服务器错误')}submitBtn.disabled=false;submitBtn.innerHTML='<i class="ph ph-magic-wand"></i> 生成短链接';return}
      recordRate();const d=await r.json();
      urlEl.textContent=d.shortUrl;res.classList.remove('hidden');
      document.getElementById('original-url').value='';
    }catch(e){alert('网络连接失败')}
    submitBtn.disabled=false;submitBtn.innerHTML='<i class="ph ph-magic-wand"></i> 生成短链接';
  });

  copyBtn.addEventListener('click',()=>{
    const t=urlEl.textContent;if(!t)return;
    const done=()=>{copyBtn.innerHTML='<i class="ph ph-check"></i> 已复制';setTimeout(()=>{copyBtn.innerHTML='<i class="ph ph-copy"></i> 复制'},2000)};
    if(navigator.clipboard){navigator.clipboard.writeText(t).then(done)}
    else{const a=document.createElement('textarea');a.value=t;a.style.cssText='position:fixed;left:-9999px';document.body.appendChild(a);a.select();document.execCommand('copy');document.body.removeChild(a);done()}
  });
  </script>
</body>
</html>
