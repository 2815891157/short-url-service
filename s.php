<?php
require_once __DIR__ . '/init.php';
$db = get_db();

$slug = $_GET['slug'] ?? '';
if ($slug === '') { header('Location: /'); exit; }

$slug = urldecode($slug);
$stmt = $db->prepare('SELECT original_url FROM links WHERE slug = ?');
$stmt->bind_param('s', $slug); $stmt->execute();
$row = $stmt->get_result()->fetch_assoc(); $stmt->close();

if (!$row) { http_response_code(404); readfile(__DIR__ . '/404.html'); exit; }

$upd = $db->prepare('UPDATE links SET visit_count = visit_count + 1 WHERE slug = ?');
$upd->bind_param('s', $slug); $upd->execute(); $upd->close();

header('HTTP/1.1 302 Found');
header('Location: ' . $row['original_url']);
exit;
