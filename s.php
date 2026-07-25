<?php
require_once __DIR__ . '/store.php';

$slug = $_GET['slug'] ?? '';
if ($slug === '') { header('Location: /'); exit; }

$slug = urldecode($slug);

// 跳转速率限制（每 IP 每分钟 60 次）
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
rate_limit('redirect:' . $ip, 60, 60);

$links = load_data();

$found = null;
foreach ($links as $l) { if ($l['slug'] === $slug) { $found = $l; break; } }

if (!$found) { http_response_code(404); readfile(__DIR__ . '/404.html'); exit; }

// 原子更新访问计数
$fp = fopen(DATA_FILE, 'c');
if ($fp) {
    flock($fp, LOCK_EX);
    $raw = file_get_contents(DATA_FILE);
    $all = json_decode($raw, true) ?: [];
    foreach ($all as &$l) {
        if ($l['slug'] === $slug) { $l['visit_count']++; break; }
    }
    unset($l);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($all, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    flock($fp, LOCK_UN);
    fclose($fp);
}

header('HTTP/1.1 307 Temporary Redirect');
header('Location: ' . $found['original_url']);
exit;
