<?php
require_once __DIR__ . '/store.php';

// Content-Type 校验
$ct = $_SERVER['CONTENT_TYPE'] ?? '';
$is_json = stripos($ct, 'application/json') !== false;

// 路由解析
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$prefix = '/api.php/';
if (strpos($uri, $prefix) === 0) {
    $path = '/' . substr($uri, strlen($prefix));
} elseif ($uri === '/api.php' || $uri === '/api.php/') {
    $path = '/';
} else {
    json_out(['error' => '接口不存在'], 404);
}
$method = $_SERVER['REQUEST_METHOD'];
$body = $is_json ? (json_decode(file_get_contents('php://input'), true) ?: []) : [];

// ---- 创建短链接 ----
if ($path === '/links' && $method === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    rate_limit('create:' . $ip, 10, 60);

    $url = trim($body['url'] ?? '');
    $slug = trim($body['slug'] ?? '');
    $title = trim($body['title'] ?? '');
    if ($url === '') json_out(['error' => '请输入目标网址'], 400);
    $parsed = parse_url($url);
    if (!$parsed || !in_array($parsed['scheme'] ?? '', ['http', 'https'])) json_out(['error' => '仅支持 http/https'], 400);

    $links = load_data();

    if ($slug !== '') {
        if (strlen($slug) > 32) json_out(['error' => '后缀不能超过 32 字符'], 400);
        if (!preg_match('/^[a-zA-Z0-9]+$/', $slug)) json_out(['error' => '后缀只能包含字母和数字'], 400);
        foreach ($links as $l) { if ($l['slug'] === $slug) json_out(['error' => '后缀已被占用'], 400); }
    } else {
        for ($i = 0; $i < 20; $i++) {
            $slug = nanoid();
            $exists = false;
            foreach ($links as $l) { if ($l['slug'] === $slug) { $exists = true; break; } }
            if (!$exists) break;
        }
    }

    $new = [
        'id' => count($links) > 0 ? max(array_column($links, 'id')) + 1 : 1,
        'slug' => $slug, 'original_url' => $url, 'title' => $title,
        'created_at' => date('Y-m-d H:i:s'), 'visit_count' => 0
    ];
    $links[] = $new;
    save_data($links);

    $host = $_SERVER['HTTP_HOST'] ?? '';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    json_out(['id' => $new['id'], 'slug' => $slug, 'shortUrl' => $scheme . '://' . $host . '/s/' . $slug, 'originalUrl' => $url, 'title' => $title]);

// ---- 检测网址 ----
} elseif ($path === '/validate-url' && $method === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    rate_limit('validate:' . $ip, 5, 60);

    $url = trim($body['url'] ?? '');
    if ($url === '') json_out(['error' => '请输入网址'], 400);
    $parsed = parse_url($url);
    if (!$parsed || !isset($parsed['host'])) json_out(['valid' => false, 'reason' => '格式错误', 'status' => null, 'details' => '输入的不是有效网址']);
    if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) json_out(['valid' => false, 'reason' => '不支持的协议', 'status' => null, 'details' => '仅支持 HTTP/HTTPS']);

    $host = $parsed['host'];
    $ips = @getaddrinfo($host, null, AF_UNSPEC, SOCK_STREAM);
    if (!$ips) json_out(['valid' => false, 'reason' => '域名不存在', 'status' => null, 'details' => "DNS 解析失败：{$host}"]);

    $blocked = false;
    $first_ip = '';
    foreach ($ips as $ip_info) {
        $addr = $ip_info['addr'] ?? '';
        if (!$first_ip) $first_ip = $addr;
        if (filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) { $blocked = true; break; }
        if (preg_match('/^(::1$|fc|fd|fe80|::ffff:127|::ffff:10|::ffff:172\.(1[6-9]|2[0-9]|3[01])|::ffff:192\.168)/', $addr)) { $blocked = true; break; }
    }
    if ($blocked) json_out(['valid' => false, 'reason' => '禁止检测内网地址', 'status' => null, 'details' => '不允许检测内网或本地地址']);

    $code = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'ShortURL-Validator/1.0'
        ]);
        curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['method' => 'HEAD', 'timeout' => 5, 'follow_location' => true, 'max_redirects' => 3, 'ignore_errors' => true, 'header' => "User-Agent: ShortURL-Validator/1.0\r\n"], 'ssl' => ['verify_peer' => true]]);
        @file_get_contents($url, false, $ctx);
        if (isset($http_response_header)) { foreach ($http_response_header as $h) { if (preg_match('#^HTTP/[\d.]+\s+(\d+)#', $h, $mm)) $code = (int)$mm[1]; } }
    }
    if ($code === 0) json_out(['valid' => false, 'reason' => '连接失败', 'status' => null, 'details' => "域名解析到 {$first_ip}，连接失败"]);
    $ok = ($code >= 200 && $code < 400);
    json_out(['valid' => $ok, 'reason' => $ok ? '网址可访问' : "HTTP {$code}", 'status' => $code, 'details' => $ok ? "域名 {$first_ip}，HTTP {$code}，有效。" : "域名 {$first_ip}，HTTP {$code}，可能失效。"]);

} else {
    json_out(['error' => '接口不存在'], 404);
}
