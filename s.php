<?php
require_once __DIR__ . '/store.php';

$slug = $_GET['slug'] ?? '';
if ($slug === '') { header('Location: /'); exit; }

$slug = urldecode($slug);
$links = load_data();

$found = null;
foreach ($links as $l) { if ($l['slug'] === $slug) { $found = $l; break; } }

if (!$found) { http_response_code(404); readfile(__DIR__ . '/404.html'); exit; }

// 增加访问计数
foreach ($links as &$l) {
    if ($l['slug'] === $slug) { $l['visit_count']++; break; }
}
unset($l);
save_data($links);

header('HTTP/1.1 302 Found');
header('Location: ' . $found['original_url']);
exit;
