<?php
define('DATA_FILE', __DIR__ . '/data.json');

function load_data() {
    if (!file_exists(DATA_FILE)) return [];
    $fp = fopen(DATA_FILE, 'r');
    if (!$fp) return [];
    flock($fp, LOCK_SH);
    $raw = file_get_contents(DATA_FILE);
    flock($fp, LOCK_UN);
    fclose($fp);
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

function save_data($data) {
    $fp = fopen(DATA_FILE, 'c');
    if (!$fp) return;
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    flock($fp, LOCK_UN);
    fclose($fp);
}

function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function nanoid($len = 7) {
    $c = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $n = strlen($c) - 1;
    $id = '';
    for ($i = 0; $i < $len; $i++) $id .= $c[random_int(0, $n)];
    return $id;
}
