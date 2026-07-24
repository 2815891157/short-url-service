<?php
require_once __DIR__ . '/init.php';
init_db();
$db = get_db();

// 解析请求路径: /api.php/links, /api.php/links/xxx, /api.php/validate-url
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$path = preg_replace('#^.*api\.php#', '', $path);
$path = '/' . trim($path, '/');

$method = $_SERVER['REQUEST_METHOD'];
$body = json_decode(file_get_contents('php://input'), true) ?: [];

// 路由分发
if ($path === '/links' && $method === 'GET') {
    handle_list($db);
} elseif ($path === '/links' && $method === 'POST') {
    handle_create($db, $body);
} elseif (preg_match('#^/links/(.+)$#', $path, $m) && $method === 'DELETE') {
    handle_delete($db, $m[1]);
} elseif ($path === '/validate-url' && $method === 'POST') {
    handle_validate($body);
} else {
    json_response(['error' => '接口不存在'], 404);
}

// ==================== 创建短链接 ====================
function handle_create($db, $body) {
    $url = trim($body['url'] ?? '');
    $slug = trim($body['slug'] ?? '');
    $title = trim($body['title'] ?? '');

    if ($url === '') {
        json_response(['error' => '请输入目标网址'], 400);
    }

    // 验证 URL 格式
    $parsed = parse_url($url);
    if (!$parsed || !isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'])) {
        json_response(['error' => '网址格式不正确，仅支持 http/https'], 400);
    }

    if ($slug !== '') {
        if (strlen($slug) > 32) {
            json_response(['error' => '后缀不能超过 32 个字符'], 400);
        }
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
            json_response(['error' => '后缀只能包含字母、数字、下划线和连字符'], 400);
        }
        $stmt = $db->prepare('SELECT id FROM links WHERE slug = ?');
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            json_response(['error' => '该自定义后缀已被占用'], 400);
        }
        $stmt->close();
    } else {
        // 自动生成，防止冲突
        for ($i = 0; $i < 10; $i++) {
            $slug = nanoid();
            $stmt = $db->prepare('SELECT id FROM links WHERE slug = ?');
            $stmt->bind_param('s', $slug);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                $stmt->close();
                break;
            }
            $stmt->close();
        }
    }

    $stmt = $db->prepare('INSERT INTO links (slug, original_url, title) VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $slug, $url, $title);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

    json_response([
        'id' => (int)$id,
        'slug' => $slug,
        'shortUrl' => $scheme . '://' . $host . '/s/' . $slug,
        'originalUrl' => $url,
        'title' => $title
    ]);
}

// ==================== 获取所有短链接 ====================
function handle_list($db) {
    $result = $db->query('SELECT * FROM links ORDER BY created_at DESC');
    $links = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['visit_count'] = (int)$row['visit_count'];
        $links[] = $row;
    }
    json_response($links);
}

// ==================== 删除短链接 ====================
function handle_delete($db, $slug) {
    $slug = urldecode($slug);
    $stmt = $db->prepare('DELETE FROM links WHERE slug = ?');
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected === 0) {
        json_response(['error' => '链接不存在'], 404);
    }
    json_response(['success' => true]);
}

// ==================== 检测网址有效性 ====================
function handle_validate($body) {
    $url = trim($body['url'] ?? '');

    if ($url === '') {
        json_response(['error' => '请输入网址'], 400);
    }

    $parsed = parse_url($url);
    if (!$parsed || !isset($parsed['host'])) {
        json_response([
            'valid' => false,
            'reason' => '格式错误',
            'status' => null,
            'details' => '输入的不是有效网址，请检查格式（例如 https://example.com）'
        ]);
    }

    $scheme = $parsed['scheme'] ?? '';
    if (!in_array($scheme, ['http', 'https'])) {
        json_response([
            'valid' => false,
            'reason' => '不支持的协议',
            'status' => null,
            'details' => '仅支持 HTTP 和 HTTPS 协议'
        ]);
    }

    $host = $parsed['host'];

    // SSRF 防护：检测内网地址
    $ip = gethostbyname($host);
    if ($ip === $host && !filter_var($ip, FILTER_VALIDATE_IP)) {
        json_response([
            'valid' => false,
            'reason' => '域名不存在',
            'status' => null,
            'details' => "DNS 解析失败：{$host}。该域名可能不存在。"
        ]);
    }

    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        if (preg_match('/^(127\.|10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.|0\.0\.0\.0|169\.254\.)/', $ip)) {
            json_response([
                'valid' => false,
                'reason' => '禁止检测内网地址',
                'status' => null,
                'details' => '出于安全考虑，不允许检测内网或本地地址'
            ]);
        }
    }

    // HEAD 请求检测（不下载页面内容）
    $result = safe_head_request($url);

    if ($result['error']) {
        json_response([
            'valid' => false,
            'reason' => $result['reason'],
            'status' => null,
            'details' => "域名解析到 {$ip}，但 {$result['details']}"
        ]);
    }

    $code = $result['status'];
    $isOk = ($code >= 200 && $code < 400);

    json_response([
        'valid' => $isOk,
        'reason' => $isOk ? '网址可访问' : "HTTP {$code}",
        'status' => $code,
        'details' => $isOk
            ? "域名解析到 {$ip}，服务器返回 HTTP {$code}，网址有效。"
            : "域名解析到 {$ip}，服务器返回 HTTP {$code}，网址可能已失效或被限制访问。"
    ]);
}

// ==================== 安全 HEAD 请求 ====================
function safe_head_request($url) {
    // 优先用 stream context（InfinityFree 通常支持）
    $opts = [
        'http' => [
            'method' => 'HEAD',
            'timeout' => TIMEOUT_SECONDS,
            'follow_location' => true,
            'max_redirects' => 5,
            'ignore_errors' => true,
            'header' => "User-Agent: ShortURL-Validator/1.0\r\n"
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ];
    $ctx = stream_context_create($opts);

    $response = @file_get_contents($url, false, $ctx);

    if ($response === false) {
        // file_get_contents 被禁用或连接失败，尝试 curl
        if (function_exists('curl_init')) {
            return safe_head_curl($url);
        }
        return [
            'error' => true,
            'reason' => '连接失败',
            'details' => '无法连接到目标服务器'
        ];
    }

    // 解析 HTTP 状态码
    $status = 200;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (preg_match('#^HTTP/[\d.]+\s+(\d+)#', $header, $m)) {
                $status = (int)$m[1];
            }
        }
    }

    return ['error' => false, 'status' => $status];
}

function safe_head_curl($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => TIMEOUT_SECONDS,
        CURLOPT_CONNECTTIMEOUT => TIMEOUT_SECONDS,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'ShortURL-Validator/1.0',
        CURLOPT_HEADER => true
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return [
            'error' => true,
            'reason' => '连接失败',
            'details' => 'cURL 错误: ' . $err
        ];
    }

    if ($code === 0) {
        return [
            'error' => true,
            'reason' => '连接失败',
            'details' => '服务器未响应'
        ];
    }

    return ['error' => false, 'status' => (int)$code];
}
