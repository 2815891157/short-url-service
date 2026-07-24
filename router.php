<?php
// 本地测试用路由脚本
// 用法: php -S localhost:8000 router.php

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// /s/xxx -> s.php?slug=xxx
if (preg_match('#^/s/(.+)$#', $path, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/s.php';
    return true;
}

// /api/xxx -> api.php/xxx
if (preg_match('#^/api(/.*)?$#', $path, $m)) {
    $_SERVER['REQUEST_URI'] = $path;
    require __DIR__ . '/api.php';
    return true;
}

// 静态文件
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}

// 默认页面
require __DIR__ . '/index.php';
return true;
