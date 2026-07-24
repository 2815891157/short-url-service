<?php
require_once __DIR__ . '/config.php';

// 配置为空 → 显示安装页面（不是跳转，是直接渲染）
if (DB_HOST === '' || DB_PASS === '') {
    // 处理表单提交
    $error = '';
    $done = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $h = trim($_POST['db_host'] ?? '');
        $n = trim($_POST['db_name'] ?? '');
        $u = trim($_POST['db_user'] ?? '');
        $p = $_POST['db_pass'] ?? '';

        if ($h === '' || $n === '' || $u === '' || $p === '') {
            $error = '请填写全部 4 项';
        } else {
            $test = @new mysqli($h, $u, $p, $n);
            if ($test->connect_error) {
                $error = '连接失败：' . $test->connect_error;
            } else {
                $test->set_charset('utf8mb4');
                $ok = $test->query("CREATE TABLE IF NOT EXISTS `links` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `slug` VARCHAR(32) NOT NULL UNIQUE,
                    `original_url` TEXT NOT NULL,
                    `title` VARCHAR(255) DEFAULT '',
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `visit_count` INT UNSIGNED DEFAULT 0
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                if (!$ok) {
                    $error = '建表失败：' . $test->error;
                } else {
                    $cfg = "<?php\n"
                        . "define('DB_HOST', " . var_export($h, true) . ");\n"
                        . "define('DB_NAME', " . var_export($n, true) . ");\n"
                        . "define('DB_USER', " . var_export($u, true) . ");\n"
                        . "define('DB_PASS', " . var_export($p, true) . ");\n";
                    if (file_put_contents(__DIR__ . '/config.php', $cfg)) {
                        $done = true;
                    } else {
                        $error = '写入配置失败，请检查目录权限';
                    }
                }
                $test->close();
            }
        }
    }
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>初始化 - 短链接服务</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css">
<link rel="stylesheet" href="style.css">
<style>
body{display:flex;align-items:center;justify-content:center;min-height:100vh}
.setup-box{max-width:480px;width:100%;padding:40px}
.setup-box h1{font-size:1.4rem;margin-bottom:8px;display:flex;align-items:center;gap:8px}
.setup-box h1 i{color:var(--accent)}
.setup-desc{color:var(--text-secondary);font-size:.9rem;margin-bottom:28px;line-height:1.6}
.setup-box label{display:block;font-size:.85rem;font-weight:600;margin-bottom:5px}
.setup-box .req{color:var(--danger)}
.setup-box input[type=text],.setup-box input[type=password]{width:100%;padding:10px 14px;background:var(--bg-tertiary);border:1px solid var(--border-color);color:var(--text-primary);font-size:.9rem;margin-bottom:16px}
.setup-box input:focus{outline:none;border-color:var(--accent)}
.setup-box .btn{width:100%;justify-content:center}
.err-box{background:#2d1b1b;border:1px solid var(--danger);color:var(--danger);padding:12px 16px;margin-bottom:16px;font-size:.85rem;display:flex;align-items:center;gap:6px}
.ok-box{text-align:center;padding:20px}
.ok-box i{font-size:2.5rem;color:var(--success);display:block;margin-bottom:12px}
.ok-box p{font-size:1rem;margin-bottom:16px}
.ok-box a{color:var(--accent);text-decoration:none;font-weight:600}
.tip{background:var(--bg-tertiary);border-left:3px solid var(--accent);padding:10px 14px;margin-top:20px;font-size:.8rem;color:var(--text-secondary);line-height:1.6}
</style>
</head>
<body>
<div class="card setup-box">
<?php if ($done): ?>
<div class="ok-box">
<i class="ph ph-check-circle"></i>
<p>配置完成！</p>
<a href="/">进入短链接服务 &rarr;</a>
</div>
<?php else: ?>
<h1><i class="ph ph-wrench"></i> 首次使用 - 数据库配置</h1>
<p class="setup-desc">填入你在主机面板申请的数据库信息，点一下就搞定了。</p>
<?php if ($error): ?><div class="err-box"><i class="ph ph-warning-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post">
<label>数据库主机 <span class="req">*</span></label>
<input type="text" name="db_host" placeholder="sql123.infinityfree.com" required value="<?= htmlspecialchars($_POST['db_host'] ?? '') ?>">
<label>数据库名 <span class="req">*</span></label>
<input type="text" name="db_name" placeholder="if0_12345678_shorturl" required value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>">
<label>数据库用户名 <span class="req">*</span></label>
<input type="text" name="db_user" placeholder="if0_12345678" required value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>">
<label>数据库密码 <span class="req">*</span></label>
<input type="password" name="db_pass" placeholder="输入密码" required>
<button type="submit" class="btn btn-primary"><i class="ph ph-lightning"></i> 一键配置</button>
</form>
<div class="tip">
<strong>这些信息在哪找？</strong><br>
登录主机控制面板 → MySQL Databases → 创建数据库 → 页面上会显示主机名、数据库名、用户名
</div>
<?php endif; ?>
</div>
</body>
</html>
<?php
    exit;
}

function get_db() {
    static $db = null;
    if ($db === null) {
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($db->connect_error) {
            http_response_code(500);
            die(json_encode(['error' => '数据库连接失败: ' . $db->connect_error]));
        }
        $db->set_charset('utf8mb4');
        $db->query("CREATE TABLE IF NOT EXISTS `links` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `slug` VARCHAR(32) NOT NULL UNIQUE,
            `original_url` TEXT NOT NULL,
            `title` VARCHAR(255) DEFAULT '',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `visit_count` INT UNSIGNED DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    return $db;
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
