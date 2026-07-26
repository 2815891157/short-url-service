<?php
// 用户页面
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>短链接生成</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css">
  <link rel="stylesheet" href="style.css">
  <style>
  .bili-icon{width:16px;height:16px;vertical-align:middle;flex-shrink:0}
  .header-inner{display:flex;align-items:center;justify-content:space-between}
  .header-left{display:flex;align-items:center;gap:8px}
  .header-right .btn{font-size:.8rem;padding:6px 14px;border-radius:6px;background:rgba(59,130,246,.1);color:var(--accent);border:1px solid rgba(59,130,246,.2)}
  .header-right .btn:hover{background:rgba(59,130,246,.18)}
  .credit{text-align:center;margin-top:40px;font-size:.82rem;color:var(--text-muted);display:flex;align-items:center;justify-content:center;gap:6px}
  .credit a{color:var(--accent);text-decoration:none}
  .credit a:hover{text-decoration:underline}
  </style>
</head>
<body>
  <header class="header">
    <div class="container">
      <div class="header-inner">
        <div class="header-left">
          <i class="ph ph-link logo-icon" style="color:var(--accent);font-size:1.3rem"></i>
          <span style="font-size:1.1rem;font-weight:700">短链接生成</span>
        </div>
        <div class="header-right">
          <a href="https://m.bilibili.com/space/3546651914406849" target="_blank" class="btn">
            <svg class="bili-icon" viewBox="0 0 1024 1024" fill="currentColor"><path d="M804.7 287.2H712.4c20.6-56.2 13.4-119.2-21.4-158.8-35.2-40-90.2-50.8-140.6-30.2l-18.2 7.4c-13 5.2-19.4 19.6-14.2 32.6l5.4 13.4c25.4 62.8-0.6 129.4-53.4 173-27.8 22.8-62.4 35-97.6 35.8l-19.4.6c-8.2.2-14.8 7-14.6 15.2 0 .4 0 .8.2 1.2l6.6 136.4c.8 16.6 14.4 29.4 31 28.8l23-.8c152.4-6.2 289.6 67.8 367.4 187 42.2 64.8 67 141.2 67 219.4v52.6c0 89.8-142.2 162.6-317.6 162.6S191.6 820 191.6 730.2v-52.6c0-78.2 24.8-154.6 67-219.4 77.8-119.2 215-193.2 367.4-187l23 .8c16.6.6 30.2-12.2 31-28.8l6.6-136.4c.2-8.2-6.4-15-14.6-15.2l-19.4-.6c-35.2-.8-69.8-13-97.6-35.8-52.8-43.6-78.8-110.2-53.4-173l5.4-13.4c5.2-13-1.2-27.4-14.2-32.6l-18.2-7.4c-50.4-20.6-105.4-9.8-140.6 30.2-34.8 39.6-42 102.6-21.4 158.8H201.2c-28 0-50.6 22.8-50.4 50.8l1.4 164.6c.2 58.4 29.2 113.2 78.2 146.8 38.4 26.4 84.4 40.8 131.4 41.6l27.6.6c73.4 1.6 144.6-22.4 203.6-67.8 42.2-32.4 74.2-75.6 93.4-125l2.8-7.2c18.4-47.4 58-85.6 107.4-108.2 34.4-15.8 72-24.4 110.2-24.4h21.2c48.4-.6 95 11.4 137.2 34.8 45 25 79.6 63.2 100.6 109.4l5.6 12.4c8 17.6 27.4 26.6 45.4 20l16.4-6.2c17.8-6.8 26.2-26.2 19.8-43.6l-26.6-72.4c-36-96.8-110.8-170.6-205.6-207.8-38.4-15-79.2-23.2-120.6-23.6h-22zM382.8 539.4c-53.2 0-96.4 43.6-96.4 97.2s43.2 97.2 96.4 97.2 96.4-43.6 96.4-97.2-43.2-97.2-96.4-97.2zm258.4 0c-53.2 0-96.4 43.6-96.4 97.2s43.2 97.2 96.4 97.2 96.4-43.6 96.4-97.2-43.2-97.2-96.4-97.2z"/></svg>
            作者主页
          </a>
        </div>
      </div>
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

    <div class="credit">
      <svg class="bili-icon" viewBox="0 0 1024 1024" fill="currentColor"><path d="M804.7 287.2H712.4c20.6-56.2 13.4-119.2-21.4-158.8-35.2-40-90.2-50.8-140.6-30.2l-18.2 7.4c-13 5.2-19.4 19.6-14.2 32.6l5.4 13.4c25.4 62.8-0.6 129.4-53.4 173-27.8 22.8-62.4 35-97.6 35.8l-19.4.6c-8.2.2-14.8 7-14.6 15.2 0 .4 0 .8.2 1.2l6.6 136.4c.8 16.6 14.4 29.4 31 28.8l23-.8c152.4-6.2 289.6 67.8 367.4 187 42.2 64.8 67 141.2 67 219.4v52.6c0 89.8-142.2 162.6-317.6 162.6S191.6 820 191.6 730.2v-52.6c0-78.2 24.8-154.6 67-219.4 77.8-119.2 215-193.2 367.4-187l23 .8c16.6.6 30.2-12.2 31-28.8l6.6-136.4c.2-8.2-6.4-15-14.6-15.2l-19.4-.6c-35.2-.8-69.8-13-97.6-35.8-52.8-43.6-78.8-110.2-53.4-173l5.4-13.4c5.2-13-1.2-27.4-14.2-32.6l-18.2-7.4c-50.4-20.6-105.4-9.8-140.6 30.2-34.8 39.6-42 102.6-21.4 158.8H201.2c-28 0-50.6 22.8-50.4 50.8l1.4 164.6c.2 58.4 29.2 113.2 78.2 146.8 38.4 26.4 84.4 40.8 131.4 41.6l27.6.6c73.4 1.6 144.6-22.4 203.6-67.8 42.2-32.4 74.2-75.6 93.4-125l2.8-7.2c18.4-47.4 58-85.6 107.4-108.2 34.4-15.8 72-24.4 110.2-24.4h21.2c48.4-.6 95 11.4 137.2 34.8 45 25 79.6 63.2 100.6 109.4l5.6 12.4c8 17.6 27.4 26.6 45.4 20l16.4-6.2c17.8-6.8 26.2-26.2 19.8-43.6l-26.6-72.4c-36-96.8-110.8-170.6-205.6-207.8-38.4-15-79.2-23.2-120.6-23.6h-22zM382.8 539.4c-53.2 0-96.4 43.6-96.4 97.2s43.2 97.2 96.4 97.2 96.4-43.6 96.4-97.2-43.2-97.2-96.4-97.2zm258.4 0c-53.2 0-96.4 43.6-96.4 97.2s43.2 97.2 96.4 97.2 96.4-43.6 96.4-97.2-43.2-97.2-96.4-97.2z"/></svg>
      by:有兽焉_辟邪本尊
    </div>
  </main>
  <footer class="footer"><div class="container">&copy;MC星辰团队</div></footer>
  <script>
  const RATE_KEY='shorturl_create_times',RATE_MAX=25,RATE_WINDOW=86400000;
  function getRateTimes(){try{return JSON.parse(localStorage.getItem(RATE_KEY))||[]}catch{return[]}}
  function checkRate(){const n=Date.now();const t=getRateTimes().filter(x=>n-x<RATE_WINDOW);localStorage.setItem(RATE_KEY,JSON.stringify(t));return t.length<RATE_MAX}
  function recordRate(){const t=getRateTimes();t.push(Date.now());localStorage.setItem(RATE_KEY,JSON.stringify(t))}
  const f=document.getElementById('create-form'),res=document.getElementById('create-result'),urlEl=document.getElementById('result-url'),copyBtn=document.getElementById('copy-btn'),submitBtn=document.getElementById('submit-btn');
  f.addEventListener('submit',async e=>{
    e.preventDefault();if(!checkRate()){alert('今天创建次数已达上限（每天 '+RATE_MAX+' 次），请明天再试');return}
    const url=document.getElementById('original-url').value.trim();if(!url)return;
    submitBtn.disabled=true;submitBtn.innerHTML='<i class="ph ph-spinner"></i> 生成中...';
    try{const r=await fetch('api.php/links',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({url})});
    if(!r.ok){const t=await r.text();try{alert(JSON.parse(t).error||'创建失败')}catch{alert('服务器错误')}submitBtn.disabled=false;submitBtn.innerHTML='<i class="ph ph-magic-wand"></i> 生成短链接';return}
    recordRate();const d=await r.json();urlEl.textContent=d.shortUrl;res.classList.remove('hidden');document.getElementById('original-url').value=''}
    catch{alert('网络连接失败')};submitBtn.disabled=false;submitBtn.innerHTML='<i class="ph ph-magic-wand"></i> 生成短链接'});
  copyBtn.addEventListener('click',()=>{const t=urlEl.textContent;if(!t)return;const done=()=>{copyBtn.innerHTML='<i class="ph ph-check"></i> 已复制';setTimeout(()=>{copyBtn.innerHTML='<i class="ph ph-copy"></i> 复制'},2000)};if(navigator.clipboard)navigator.clipboard.writeText(t).then(done);else{const a=document.createElement('textarea');a.value=t;a.style.cssText='position:fixed;left:-9999px';document.body.appendChild(a);a.select();document.execCommand('copy');document.body.removeChild(a);done()}});
  </script>
</body>
</html>
