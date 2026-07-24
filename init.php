<?php
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

function init_db() {
    $db = get_db();
    $sql = "CREATE TABLE IF NOT EXISTS `links` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `slug` VARCHAR(32) NOT NULL UNIQUE,
        `original_url` TEXT NOT NULL,
        `title` VARCHAR(255) DEFAULT '',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `visit_count` INT UNSIGNED DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->query($sql);
}

function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function nanoid($length = NANOID_LEN) {
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz_-';
    $max = strlen($chars) - 1;
    $id = '';
    for ($i = 0; $i < $length; $i++) {
        $id .= $chars[random_int(0, $max)];
    }
    return $id;
}

function escape_attr($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
