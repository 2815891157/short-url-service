<?php
require_once __DIR__ . '/init.php';
$db = get_db();

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$path = preg_replace('#^.*api\.php#', '', $path);
$path = '/' . trim($path, '/');

$method = $_SERVER['REQUEST_METHOD'];
$body = json_decode(file_get_contents('php://input'), true) ?: [];

if ($path === '/links' && $method === 'GET') {
    $res = $db->query('SELECT * FROM links ORDER BY created_at DESC');
    $links = [];
    while ($row = $res->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['visit_count'] = (int)$row['visit_count'];
        $links[] = $row;
    }
    json_resp($links);

} elseif ($path === '/links' && $method === 'POST') {
    $url = trim($body['url'] ?? '');
    $slug = trim($body['slug'] ?? '');
    $title = trim($body['title'] ?? '');

    if ($url === '') json_resp(['error' => '请输入目标网址'], 400);

    $p = parse_url($url);
    if (!$p || !isset($p['scheme']) || !in_array($p['scheme'], ['http', 'https'])) {
        json_resp(['error' => '网址格式不正确，仅支持 http/https'], 400);
    }

    if ($slug !== '') {
        if (strlen($slug) > 32) json_resp(['error' => '后缀不能超过 32 个字符'], 400);
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) json_resp(['error' => '后缀只能包含字母、数字、下划线和连字符'], 400);
        $s = $db->prepare('SELECT id FROM links WHERE slug = ?');
        $s->bind_param('s', $slug);
        $s->execute();
        if ($s->get_result()->num_rows > 0) json_resp(['error' => '该自定义后缀已被占用'], 400);
        $s->close();
    } else {
        for ($i = 0; $i < 10; $i++) {
            $slug = nanoid();
            $s = $db->prepare('SELECT id FROM links WHERE slug = ?');
            $s->bind_param('s', $slug);
            $s->execute();
            if ($s->get_result()->num_rows === 0) { $s->close(); break; }
            $s->close();
        }
    }

    $s = $db->prepare('INSERT INTO links (slug, original_url, title) VALUES (?, ?, ?)');
    $s->bind_param('sss', $slug, $url, $title);
    $s->execute();
    $id = $s->insert_id;
    $s->close();

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    json_resp([
        'id' => (int)$id, 'slug' => $slug,
        'shortUrl' => $scheme . '://' . $host . '/s/' . $slug,
        'originalUrl' => $url, 'title' => $title
    ]);

} elseif (preg_match('#^/links/(.+)$#', $path, $m) && $method === 'DELETE') {
    $slug = urldecode($m[1]);
    $s = $db->prepare('DELETE FROM links WHERE slug = ?');
    $s->bind_param('s', $slug);
    $s->execute();
    $n = $s->affected_rows;
    $s->close();
    if ($n === 0) json_resp(['error' => '链接不存在'], 404);
    json_resp(['success' => true]);

} elseif ($path === '/validate-url' && $method === 'POST') {
    $url = trim($body['url'] ?? '');
    if ($url === '') json_resp(['error' => '请输入网址'], 400);

    $p = parse_url($url);
    if (!$p || !isset($p['host'])) {
        json_resp(['valid' => false, 'reason' => '格式错误', 'status' => null,
            'details' => '输入的不是有效网址，请检查格式（例如 https://example.com）']);
    }
    if (!in_array($p['scheme'] ?? '', ['http', 'https'])) {
        json_resp(['valid' => false, 'reason' => '不支持的协议', 'status' => null,
            'details' => '仅支持 HTTP 和 HTTPS 协议']);
    }

    $host = $p['host'];
    $ip = @gethostbyname($host);

    // SSRF 防护
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        if (preg_match('/^(127\.|10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.|0\.0\.0\.0|169\.254\.)/', $ip)) {
            json_resp(['valid' => false, 'reason' => '禁止检测内网地址', 'status' => null,
                'details' => '出于安全考虑，不允许检测内网或本地地址']);
        }
    }
    if ($ip === $host && !filter_var($ip, FILTER_VALIDATE_IP)) {
        json_resp(['valid' => false, 'reason' => '域名不存在', 'status' => null,
            'details' => "DNS 解析失败：{$host}。该域名可能不存在。"]);
    }

    // HEAD 请求检测
    $result = safe_head($url);
    if ($result['error']) {
        json_resp(['valid' => false, 'reason' => $result['reason'], 'status' => null,
            'details' => "域名解析到 {$ip}，但 {$result['details']}"]);
    }

    $code = $result['status'];
    $ok = ($code >= 200 && $code < 400);
    json_resp([
        'valid' => $ok,
        'reason' => $ok ? '网址可访问' : "HTTP {$code}",
        'status' => $code,
        'details' => $ok
            ? "域名解析到 {$ip}，服务器返回 HTTP {$code}，网址有效。"
            : "域名解析到 {$ip}，服务器返回 HTTP {$code}，网址可能已失效或被限制访问。"
    ]);

} else {
    json_resp(['error' => '接口不存在'], 404);
}

// ==================== 安全 HEAD 请求 ====================
function safe_head($url) {
    $opts = [
        'http' => [
            'method' => 'HEAD',
            'timeout' => 8,
            'follow_location' => true,
            'max_redirects' => 5,
            'ignore_errors' => true,
            'header' => "User-Agent: ShortURL-Validator/1.0\r\n"
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ];
    $ctx = stream_context_create($opts);
    $resp = @file_get_contents($url, false, $ctx);

    if ($resp !== false && isset($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('#^HTTP/[\d.]+\s+(\d+)#', $h, $m)) {
                return ['error' => false, 'status' => (int)$m[1]];
            }
        }
        return ['error' => false, 'status' => 200];
    }

    // 降级到 curl
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'ShortURL-Validator/1.0', CURLOPT_HEADER => true
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) return ['error' => true, 'reason' => '连接失败', 'details' => 'cURL: ' . $err];
        if ($code === 0) return ['error' => true, 'reason' => '连接失败', 'details' => '服务器未响应'];
        return ['error' => false, 'status' => (int)$code];
    }

    return ['error' => true, 'reason' => '连接失败', 'details' => '无法连接到目标服务器'];
}
