<?php
require_once __DIR__ . '/init.php';
$db = get_db();

$uri = $_SERVER['REQUEST_URI'];
$path = preg_replace('#^.*api\.php#', '', parse_url($uri, PHP_URL_PATH));
$path = '/' . trim($path, '/');
$method = $_SERVER['REQUEST_METHOD'];
$body = json_decode(file_get_contents('php://input'), true) ?: [];

if ($path === '/links' && $method === 'GET') {
    $result = $db->query('SELECT * FROM links ORDER BY created_at DESC');
    $links = [];
    while ($row = $result->fetch_assoc()) { $row['id'] = (int)$row['id']; $row['visit_count'] = (int)$row['visit_count']; $links[] = $row; }
    json_response($links);

} elseif ($path === '/links' && $method === 'POST') {
    $url = trim($body['url'] ?? '');
    $slug = trim($body['slug'] ?? '');
    $title = trim($body['title'] ?? '');
    if ($url === '') json_response(['error' => '请输入目标网址'], 400);
    $parsed = parse_url($url);
    if (!$parsed || !in_array($parsed['scheme'] ?? '', ['http', 'https'])) json_response(['error' => '网址格式不正确，仅支持 http/https'], 400);

    if ($slug !== '') {
        if (strlen($slug) > 32) json_response(['error' => '后缀不能超过 32 个字符'], 400);
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) json_response(['error' => '后缀只能包含字母、数字、下划线和连字符'], 400);
        $st = $db->prepare('SELECT id FROM links WHERE slug = ?'); $st->bind_param('s', $slug); $st->execute();
        if ($st->get_result()->num_rows > 0) { $st->close(); json_response(['error' => '该自定义后缀已被占用'], 400); }
        $st->close();
    } else {
        for ($i = 0; $i < 10; $i++) {
            $slug = nanoid();
            $st = $db->prepare('SELECT id FROM links WHERE slug = ?'); $st->bind_param('s', $slug); $st->execute();
            if ($st->get_result()->num_rows === 0) { $st->close(); break; }
            $st->close();
        }
    }

    $st = $db->prepare('INSERT INTO links (slug, original_url, title) VALUES (?, ?, ?)');
    $st->bind_param('sss', $slug, $url, $title); $st->execute();
    $id = $st->insert_id; $st->close();

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    json_response(['id' => (int)$id, 'slug' => $slug, 'shortUrl' => $scheme . '://' . $host . '/s/' . $slug, 'originalUrl' => $url, 'title' => $title]);

} elseif (preg_match('#^/links/(.+)$#', $path, $m) && $method === 'DELETE') {
    $slug = urldecode($m[1]);
    $st = $db->prepare('DELETE FROM links WHERE slug = ?'); $st->bind_param('s', $slug); $st->execute();
    $affected = $st->affected_rows; $st->close();
    if ($affected === 0) json_response(['error' => '链接不存在'], 404);
    json_response(['success' => true]);

} elseif ($path === '/validate-url' && $method === 'POST') {
    $url = trim($body['url'] ?? '');
    if ($url === '') json_response(['error' => '请输入网址'], 400);

    $parsed = parse_url($url);
    if (!$parsed || !isset($parsed['host'])) json_response(['valid' => false, 'reason' => '格式错误', 'status' => null, 'details' => '输入的不是有效网址，请检查格式（例如 https://example.com）']);
    if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) json_response(['valid' => false, 'reason' => '不支持的协议', 'status' => null, 'details' => '仅支持 HTTP 和 HTTPS 协议']);

    $host = $parsed['host'];
    $ip = gethostbyname($host);
    if ($ip === $host && !filter_var($ip, FILTER_VALIDATE_IP)) json_response(['valid' => false, 'reason' => '域名不存在', 'status' => null, 'details' => "DNS 解析失败：{$host}，该域名可能不存在。"]);
    if (filter_var($ip, FILTER_VALIDATE_IP) && preg_match('/^(127\.|10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.|0\.0\.0\.0|169\.254\.)/', $ip))
        json_response(['valid' => false, 'reason' => '禁止检测内网地址', 'status' => null, 'details' => '出于安全考虑，不允许检测内网或本地地址']);

    // HEAD 请求
    $code = 0; $err = '';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false, CURLOPT_USERAGENT => 'ShortURL-Validator/1.0']);
        curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['method' => 'HEAD', 'timeout' => 8, 'follow_location' => true, 'max_redirects' => 5, 'ignore_errors' => true, 'header' => "User-Agent: ShortURL-Validator/1.0\r\n"], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp !== false && isset($http_response_header)) {
            foreach ($http_response_header as $h) { if (preg_match('#^HTTP/[\d.]+\s+(\d+)#', $h, $mm)) $code = (int)$mm[1]; }
        } else { $err = '连接失败'; }
    }

    if ($err) json_response(['valid' => false, 'reason' => '连接失败', 'status' => null, 'details' => "域名解析到 {$ip}，但连接失败"]);
    $isOk = ($code >= 200 && $code < 400);
    json_response(['valid' => $isOk, 'reason' => $isOk ? '网址可访问' : "HTTP {$code}", 'status' => $code, 'details' => $isOk ? "域名解析到 {$ip}，服务器返回 HTTP {$code}，网址有效。" : "域名解析到 {$ip}，服务器返回 HTTP {$code}，网址可能已失效。"]);

} else {
    json_response(['error' => '接口不存在'], 404);
}
