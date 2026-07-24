const express = require('express');
const Database = require('better-sqlite3');
const fetch = require('node-fetch');
const dns = require('dns');
const { URL } = require('url');
const path = require('path');
const { nanoid } = require('nanoid');

const app = express();
const PORT = process.env.PORT || 3000;

const db = new Database(path.join(__dirname, 'shortlinks.db'));
db.pragma('journal_mode = WAL');

db.exec(`
  CREATE TABLE IF NOT EXISTS links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT UNIQUE NOT NULL,
    original_url TEXT NOT NULL,
    title TEXT DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    visit_count INTEGER DEFAULT 0
  )
`);

app.use(express.json({ limit: '1mb' }));
app.use(express.static(path.join(__dirname, 'public')));

// 创建短链接
app.post('/api/links', (req, res) => {
  try {
    let { url, slug, title } = req.body;

    if (!url) {
      return res.status(400).json({ error: '请输入目标网址' });
    }

    try {
      new URL(url);
    } catch {
      return res.status(400).json({ error: '网址格式不正确' });
    }

    if (!['http:', 'https:'].includes(new URL(url).protocol)) {
      return res.status(400).json({ error: '仅支持 http 和 https 协议' });
    }

    if (slug) {
      if (slug.length > 32) {
        return res.status(400).json({ error: '后缀不能超过 32 个字符' });
      }
      if (!/^[a-zA-Z0-9_-]+$/.test(slug)) {
        return res.status(400).json({ error: '后缀只能包含字母、数字、下划线和连字符' });
      }
      const existing = db.prepare('SELECT id FROM links WHERE slug = ?').get(slug);
      if (existing) {
        return res.status(400).json({ error: '该自定义后缀已被占用' });
      }
    } else {
      slug = nanoid(7);
    }

    const result = db.prepare(
      'INSERT INTO links (slug, original_url, title) VALUES (?, ?, ?)'
    ).run(slug, url, title || '');

    res.json({
      id: result.lastInsertRowid,
      slug,
      shortUrl: `${req.protocol}://${req.get('host')}/s/${slug}`,
      originalUrl: url,
      title: title || ''
    });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// 获取所有短链接
app.get('/api/links', (req, res) => {
  try {
    const links = db.prepare('SELECT * FROM links ORDER BY created_at DESC').all();
    res.json(links);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// 删除短链接
app.delete('/api/links/:slug', (req, res) => {
  try {
    const result = db.prepare('DELETE FROM links WHERE slug = ?').run(req.params.slug);
    if (result.changes === 0) {
      return res.status(404).json({ error: '链接不存在' });
    }
    res.json({ success: true });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// 检测网址有效性（SSRF 防护 + 安全 HEAD 请求）
app.post('/api/validate-url', async (req, res) => {
  try {
    const { url } = req.body;
    if (!url) {
      return res.status(400).json({ error: '请输入网址' });
    }

    let parsedUrl;
    try {
      parsedUrl = new URL(url);
    } catch {
      return res.json({
        valid: false,
        reason: '格式错误',
        status: null,
        details: '输入的不是有效网址，请检查格式（例如 https://example.com）'
      });
    }

    if (!['http:', 'https:'].includes(parsedUrl.protocol)) {
      return res.json({
        valid: false,
        reason: '不支持的协议',
        status: null,
        details: '仅支持 HTTP 和 HTTPS 协议'
      });
    }

    // SSRF 防护：禁止检测内网地址
    const hostname = parsedUrl.hostname;
    const isPrivate = await new Promise((resolve) => {
      dns.lookup(hostname, (err, address) => {
        if (err) return resolve(false);  // DNS fail -> not private, will be caught below
        // 127.x.x.x, 10.x.x.x, 172.16-31.x.x, 192.168.x.x, 0.0.0.0, 169.254.x.x
        if (/^(127\.|10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.|0\.0\.0\.0|169\.254\.|::1|fc|fd|fe80)/.test(address)) {
          return resolve(true);
        }
        resolve(false);
      });
    });

    if (isPrivate) {
      return res.json({
        valid: false,
        reason: '禁止检测内网地址',
        status: null,
        details: '出于安全考虑，不允许检测内网或本地地址'
      });
    }

    // DNS 解析
    const dnsCheck = await new Promise((resolve) => {
      dns.lookup(hostname, (err, address) => {
        if (err) {
          resolve({ ok: false, error: err.message });
        } else {
          resolve({ ok: true, address });
        }
      });
    });

    if (!dnsCheck.ok) {
      return res.json({
        valid: false,
        reason: '域名不存在',
        status: null,
        details: `DNS 解析失败：${hostname} — ${dnsCheck.error}。该域名可能不存在或 DNS 未配置。`
      });
    }

    // HEAD 请求
    try {
      const controller = new AbortController();
      const timeout = setTimeout(() => controller.abort(), 8000);

      const response = await fetch(url, {
        method: 'HEAD',
        signal: controller.signal,
        redirect: 'follow',
        headers: { 'User-Agent': 'ShortURL-Validator/1.0' }
      });

      clearTimeout(timeout);

      const statusCode = response.status;
      const isOk = statusCode >= 200 && statusCode < 400;

      res.json({
        valid: isOk,
        reason: isOk ? '网址可访问' : `HTTP ${statusCode}`,
        status: statusCode,
        details: isOk
          ? `域名解析到 ${dnsCheck.address}，服务器返回 HTTP ${statusCode}，网址有效。`
          : `域名解析到 ${dnsCheck.address}，服务器返回 HTTP ${statusCode}，网址可能已失效或被限制访问。`
      });
    } catch (fetchErr) {
      let reason = '连接失败';
      let details = '';

      if (fetchErr.name === 'AbortError') {
        reason = '请求超时';
        details = `域名 ${hostname} 已解析到 ${dnsCheck.address}，但服务器在 8 秒内未响应。`;
      } else {
        details = `域名 ${hostname} 已解析到 ${dnsCheck.address}，但连接失败：${fetchErr.message}`;
      }

      res.json({ valid: false, reason, status: null, details });
    }
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// 短链接跳转 —— 直接 302
app.get('/s/:slug', (req, res) => {
  try {
    const link = db.prepare('SELECT original_url FROM links WHERE slug = ?').get(req.params.slug);
    if (!link) {
      return res.status(404).sendFile(path.join(__dirname, 'public', '404.html'));
    }
    db.prepare('UPDATE links SET visit_count = visit_count + 1 WHERE slug = ?').run(req.params.slug);
    res.redirect(302, link.original_url);
  } catch (err) {
    res.status(500).send('服务器内部错误');
  }
});

// SPA fallback —— 非 API、非 /s/ 的请求都返回 index.html
app.get('*', (req, res) => {
  if (req.path.startsWith('/api/') || req.path.startsWith('/s/')) {
    return res.status(404).json({ error: '接口不存在' });
  }
  res.sendFile(path.join(__dirname, 'public', 'index.html'));
});

app.listen(PORT, () => {
  console.log(`短链接服务已启动: http://localhost:${PORT}`);
});
