<?php
// JSON 文件存储 —— 无数据库，上传即用

define('DATA_FILE', __DIR__ . '/data.json');

function load_data() {
    if (!file_exists(DATA_FILE)) return [];
    $raw = file_get_contents(DATA_FILE);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function save_data($data) {
    $tmp = DATA_FILE . '.tmp';
    file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    rename($tmp, DATA_FILE);
}

function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function nanoid($len = 7) {
    $c = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz_-';
    $n = strlen($c) - 1;
    $id = '';
    for ($i = 0; $i < $len; $i++) $id .= $c[random_int(0, $n)];
    return $id;
}
