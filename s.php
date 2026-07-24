<?php
// 短链接跳转入口
// .htaccess 将 /s/xxx 重写到 s.php?slug=xxx

require_once __DIR__ . '/init.php';
init_db();
$db = get_db();

$slug = $_GET['slug'] ?? '';
if ($slug === '') {
    header('Location: /');
    exit;
}

$slug = urldecode($slug);

$stmt = $db->prepare('SELECT original_url FROM links WHERE slug = ?');
$stmt->bind_param('s', $slug);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    // 404 页面
    http_response_code(404);
    readfile(__DIR__ . '/public/404.html');
    exit;
}

// 增加访问计数
$upd = $db->prepare('UPDATE links SET visit_count = visit_count + 1 WHERE slug = ?');
$upd->bind_param('s', $slug);
$upd->execute();
$upd->close();

// 直接跳转
header('HTTP/1.1 302 Found');
header('Location: ' . $row['original_url']);
exit;
