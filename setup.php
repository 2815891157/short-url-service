<?php
// =====================================================
// 安装向导 —— 首次访问自动进入，填完数据库信息自动建库建表
// =====================================================
session_start();
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['db_host'] ?? '');
    $name = trim($_POST['db_name'] ?? '');
    $user = trim($_POST['db_user'] ?? '');
    $pass = $_POST['db_pass'] ?? '';

    if ($host === '' || $name === '' || $user === '') {
        $error = '请填写所有必填项';
    } else {
        // 测试连接
        $test = @new mysqli($host, $user, $pass, $name);
        if ($test->connect_error) {
            $error = '数据库连接失败：' . $test->connect_error;
        } else {
            $test->set_charset('utf8mb4');

            // 建表
            $sql = "CREATE TABLE IF NOT EXISTS `links` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `slug` VARCHAR(32) NOT NULL UNIQUE,
                `original_url` TEXT NOT NULL,
                `title` VARCHAR(255) DEFAULT '',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `visit_count` INT UNSIGNED DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

            if (!$test->query($sql)) {
                $error = '建表失败：' . $test->error;
            } else {
                // 写入配置文件
                $configContent = "<?php\n"
                    . "// 数据库配置 —— 由安装向导自动生成\n"
                    . "define('DB_HOST', " . var_export($host, true) . ");\n"
                    . "define('DB_NAME', " . var_export($name, true) . ");\n"
                    . "define('DB_USER', " . var_export($user, true) . ");\n"
                    . "define('DB_PASS', " . var_export($pass, true) . ");\n"
                    . "define('SITE_NAME', '短链接服务');\n"
                    . "define('NANOID_LEN', 7);\n"
                    . "define('TIMEOUT_SECONDS', 8);\n";

                if (file_put_contents(__DIR__ . '/config.php', $configContent)) {
                    $success = true;
                } else {
                    $error = '无法写入配置文件，请检查目录权限（需要可写权限）';
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
<title>安装向导 - 短链接服务</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;border-radius:0!important}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#0f1117;color:#e4e6ef;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.box{background:#1a1d27;border:1px solid #2d3142;padding:40px;max-width:520px;width:100%}
h1{font-size:1.4rem;margin-bottom:8px;display:flex;align-items:center;gap:8px}
h1 i{color:#4f7df7}
.desc{color:#8b8fa3;font-size:.9rem;margin-bottom:28px;line-height:1.6}
label{display:block;font-size:.85rem;font-weight:600;margin-bottom:5px;color:#e4e6ef}
label .req{color:#e74c3c}
input[type=text],input[type=password]{width:100%;padding:10px 14px;background:#242836;border:1px solid #2d3142;color:#e4e6ef;font-size:.9rem;font-family:inherit;margin-bottom:16px}
input:focus{outline:none;border-color:#4f7df7}
.btn{width:100%;padding:12px;background:#4f7df7;color:#fff;border:none;font-size:.9rem;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px}
.btn:hover{background:#3a6ae8}
.error{background:#2d1b1b;border:1px solid #e74c3c;color:#e74c3c;padding:12px 16px;margin-bottom:16px;font-size:.85rem;display:flex;align-items:center;gap:6px}
.success{background:#1b2d1b;border:1px solid #2ecc71;color:#2ecc71;padding:16px;text-align:center;font-size:1rem}
.success i{font-size:2rem;display:block;margin-bottom:8px}
.success a{color:#4f7df7;text-decoration:none;font-weight:600}
.tip{background:#242836;border-left:3px solid #4f7df7;padding:10px 14px;margin-top:20px;font-size:.8rem;color:#8b8fa3;line-height:1.6}
</style>
</head>
<body>
<div class="box">
<?php if ($success): ?>
<div class="success">
<i class="ph ph-check-circle"></i>
安装完成！<br><br>
<a href="index.php">点击进入短链接服务</a>
</div>
<?php else: ?>
<h1><i class="ph ph-wrench"></i> 安装向导</h1>
<p class="desc">填入你在 InfinityFree 申请的数据库信息，系统会自动建库建表。</p>

<?php if ($error): ?>
<div class="error"><i class="ph ph-warning-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post">
<label>数据库主机 <span class="req">*</span></label>
<input type="text" name="db_host" placeholder="sql123.infinityfree.com" required
  value="<?= htmlspecialchars($_POST['db_host'] ?? '') ?>">

<label>数据库名 <span class="req">*</span></label>
<input type="text" name="db_name" placeholder="if0_12345678_shorturl" required
  value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>">

<label>数据库用户名 <span class="req">*</span></label>
<input type="text" name="db_user" placeholder="if0_12345678" required
  value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>">

<label>数据库密码 <span class="req">*</span></label>
<input type="password" name="db_pass" placeholder="输入密码" required>

<button type="submit" class="btn">
<i class="ph ph-lightning"></i> 一键安装
</button>
</form>

<div class="tip">
<strong>如何获取这些信息？</strong><br>
1. 登录 InfinityFree 控制面板<br>
2. 左侧菜单 → MySQL Databases<br>
3. 创建数据库，页面会显示主机名、数据库名、用户名<br>
4. 密码是你创建时设置的
</div>
<?php endif; ?>
</div>
</body>
</html>
