<?php
require_once __DIR__ . '/store.php';

$slug = $_GET['slug'] ?? '';
if ($slug === '') { header('Location: /'); exit; }

$fp = fopen(DATA_FILE, 'c');
if (!$fp) { header('Location: /'); exit; }
flock($fp, LOCK_EX);
$all = json_decode(file_get_contents(DATA_FILE), true) ?: [];

$found = null;
foreach ($all as &$l) {
    if ($l['slug'] === $slug) { $found = &$l; break; }
}
unset($l);

if (!$found) {
    flock($fp, LOCK_UN);
    fclose($fp);
    http_response_code(404);
    readfile(__DIR__ . '/404.html');
    exit;
}

$found['visit_count']++;
ftruncate($fp, 0);
fwrite($fp, json_encode($all, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
flock($fp, LOCK_UN);
fclose($fp);

header('HTTP/1.1 307 Temporary Redirect');
header('Location: ' . $found['original_url']);
exit;
