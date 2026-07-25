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
    $c = '01223456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $n = strlen($c) - 1;
    $id = '';
    for ($i = 0; $i < $len; $i++) $id .= $c[random_int(0, $n)];
    return $id;
}

// IP 速率限制（带文件锁）
function rate_limit($key, $max = 10, $window = 60) {
    $dir = __DIR__ . '/.rate';
    if (!is_dir($dir)) @mkdir($dir, 0755);
    $file = $dir . '/' . md5($key) . '.json';
    $now = time();

    $fp = fopen($file, 'c');
    if (!$fp) return;
    flock($fp, LOCK_EX);

    $data = ['times' => []];
    if (filesize($file) > 0) {
        $raw = file_get_contents($file);
        $data = json_decode($raw, true) ?: $data;
    }

    $data['times'] = array_filter($data['times'], fn($t) => $t > $now - $window);

    if (count($data['times']) >= $max) {
        flock($fp, LOCK_UN);
        fclose($fp);
        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => '操作太频繁，请稍后再试'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data['times'][] = $now;
    ftruncate($fp, 0);
    fwrite($fp, json_encode($data));
    flock($fp, LOCK_UN);
    fclose($fp);
}
