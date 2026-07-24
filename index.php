<?php
// 首页入口：检查是否已配置，未配置则跳转安装页
session_start();
$configFile = __DIR__ . '/config.php';

if (!file_exists($configFile)) {
    header('Location: setup.php');
    exit;
}

// 加载配置后检查数据库是否就绪
require_once $configFile;
if (defined('DB_HOST') && DB_HOST !== '') {
    // 数据库已配置，显示主页
    readfile(__DIR__ . '/index.html');
} else {
    header('Location: setup.php');
    exit;
}
