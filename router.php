<?php
// 本地测试: php -S localhost:8000 router.php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/s/(.+)$#', $uri, $m)) { $_GET['slug'] = $m[1]; require __DIR__ . '/s.php'; return true; }
if (preg_match('#^/api(/.*)?$#', $uri)) { require __DIR__ . '/api.php'; return true; }
$file = __DIR__ . $uri;
if ($uri !== '/' && is_file($file)) return false;
return false;
