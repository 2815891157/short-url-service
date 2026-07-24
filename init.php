<?php
// 数据库连接 + 工具函数
if (!file_exists(__DIR__ . '/config.php')) {
    header('Location: setup.php');
    exit;
}
require_once __DIR__ . '/config.php';

function get_db() {
    static $db = null;
    if ($db === null) {
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($db->connect_error) {
            http_response_code(500);
            die(json_encode(['error' => '数据库连接失败: ' . $db->connect_error]));
        }
        $db->set_charset('utf8mb4');
    }
    return $db;
}

function json_resp($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function nanoid($len = 7) {
    $c = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz_-';
    $max = strlen($c) - 1;
    $id = '';
    for ($i = 0; $i < $len; $i++) {
        $id .= $c[random_int(0, $max)];
    }
    return $id;
}
