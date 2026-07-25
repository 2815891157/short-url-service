<?php
// =====================================================
// 管理后台 —— 单文件独立运行，上传即用，删不影响服务
// =====================================================

define('DATA_FILE', __DIR__ . '/data.json');
define('ADMIN_PW_FILE', __DIR__ . '/admin_pw.json');

session_start();

// ---- CSRF Token ----
function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}
function csrf_verify() {
    return isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf_token']);
}

// ---- 数据操作 ----
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
function save_data($d) {
    $fp = fopen(DATA_FILE, 'c');
    if (!$fp) return;
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    flock($fp, LOCK_UN);
    fclose($fp);
}

// ---- 密码系统 ----
function get_pw_hash() {
    if (!file_exists(ADMIN_PW_FILE)) return null;
    $d = json_decode(file_get_contents(ADMIN_PW_FILE), true);
    return $d['hash'] ?? null;
}
function set_pw_hash($hash) {
    file_put_contents(ADMIN_PW_FILE, json_encode(['hash' => $hash]));
}

$pw_hash = get_pw_hash();
$logged_in = !empty($_SESSION['admin']) && $_SESSION['admin'] === true;
$error = '';
$flash = '';

// 处理 POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    // 设置密码（首次）
    if ($act === 'set_password' && !$pw_hash) {
        $pw = $_POST['password'] ?? '';
        if (strlen($pw) < 8) { $error = '密码至少 8 位'; }
        else { set_pw_hash(password_hash($pw, PASSWORD_DEFAULT)); $logged_in = true; $_SESSION['admin'] = true; session_regenerate_id(true); }
    }

    // 登录
    if ($act === 'login' && $pw_hash) {
        if (!csrf_verify()) { $error = '验证失败，请刷新重试'; }
        else {
            $pw = $_POST['password'] ?? '';
            if (password_verify($pw, $pw_hash)) {
                $logged_in = true; $_SESSION['admin'] = true;
                session_regenerate_id(true);
            } else { $error = '密码错误'; }
        }
    }

    // 登出
    if ($act === 'logout') {
        if (!csrf_verify()) { $error = '验证失败'; }
        else { $_SESSION = []; session_destroy(); $logged_in = false; }
    }

    // 删除链接
    if ($act === 'delete' && $logged_in) {
        if (!csrf_verify()) { $error = '验证失败'; }
        else {
            $id = (int)($_POST['id'] ?? 0);
            $links = load_data();
            $links = array_values(array_filter($links, fn($l) => $l['id'] !== $id));
            save_data($links);
            $flash = '删除成功';
        }
    }
}

// Flash message（替代 GET 参数）
if (isset($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['msg'])) {
    $flash = $_GET['msg'] === 'deleted' ? '删除成功' : '';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>管理后台 - 短链接服务</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;border-radius:0!important}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#0f1117;color:#e4e6ef;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.box{background:#1a1d27;border:1px solid #2d3142;padding:40px;max-width:520px;width:100%}
.box-full{max-width:1000px;align-items:stretch;display:block;min-height:100vh;padding:32px}
h1{font-size:1.3rem;margin-bottom:20px;display:flex;align-items:center;gap:8px}
h1 i{color:#4f7df7}
label{display:block;font-size:.85rem;font-weight:600;margin-bottom:5px}
.req{color:#e74c3c}
input[type=text],input[type=password]{width:100%;padding:10px 14px;background:#242836;border:1px solid #2d3142;color:#e4e6ef;font-size:.9rem;margin-bottom:16px}
input:focus{outline:none;border-color:#4f7df7}
.btn{padding:10px 20px;border:1px solid #2d3142;font-size:.85rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.btn-primary{background:#4f7df7;color:#fff;border-color:#4f7df7}
.btn-primary:hover{background:#3a6ae8}
.btn-danger{background:#e74c3c;color:#fff;border-color:#e74c3c}
.btn-danger:hover{background:#c0392b}
.btn-sm{padding:5px 10px;font-size:.78rem}
.btn-full{width:100%;justify-content:center}
.err{background:#2d1b1b;border:1px solid #e74c3c;color:#e74c3c;padding:10px 14px;margin-bottom:16px;font-size:.85rem}
.msg{background:#1b2d1b;border:1px solid #2ecc71;color:#2ecc71;padding:10px 14px;margin-bottom:16px;font-size:.85rem}
table{width:100%;border-collapse:collapse;margin-top:16px}
th,td{padding:10px 12px;border-bottom:1px solid #2d3142;text-align:left;font-size:.85rem}
th{background:#242836;font-weight:600;color:#8b8fa3}
td{color:#e4e6ef}
.slug{color:#4f7df7;font-family:monospace;font-weight:600}
.url{color:#8b8fa3;word-break:break-all;max-width:300px}
.meta{color:#5c6073;font-size:.78rem}
.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
.topbar .btn{font-size:.8rem}
.empty{text-align:center;padding:40px;color:#5c6073}
a{color:#4f7df7;text-decoration:none}
a:hover{text-decoration:underline}
</style>
</head>
<body>

<?php if (!$logged_in): ?>
<div class="box">
<h1><i class="ph ph-lock-key"></i> 管理后台</h1>
<?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if (!$pw_hash): ?>
<p style="color:#8b8fa3;margin-bottom:20px;font-size:.9rem">首次使用，请设置管理密码（至少 8 位）。</p>
<form method="post">
  <input type="hidden" name="action" value="set_password">
  <?= csrf_field() ?>
  <label>设置密码 <span class="req">*</span></label>
  <input type="password" name="password" placeholder="至少 8 位" required autofocus minlength="8">
  <button type="submit" class="btn btn-primary btn-full"><i class="ph ph-check"></i> 确认设置</button>
</form>
<?php else: ?>
<form method="post">
  <input type="hidden" name="action" value="login">
  <?= csrf_field() ?>
  <label>输入密码</label>
  <input type="password" name="password" placeholder="管理密码" required autofocus>
  <button type="submit" class="btn btn-primary btn-full"><i class="ph ph-arrow-right"></i> 登录</button>
</form>
<?php endif; ?>
</div>

<?php else: ?>
<div class="box box-full">
<div class="topbar">
  <h1><i class="ph ph-list"></i> 短链接管理</h1>
  <form method="post" style="display:inline">
    <input type="hidden" name="action" value="logout">
    <?= csrf_field() ?>
    <button class="btn btn-sm" style="background:#242836;color:#8b8fa3"><i class="ph ph-sign-out"></i> 登出</button>
  </form>
</div>

<?php if ($flash): ?><div class="msg"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="err"><i class="ph ph-warning-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php
$links = load_data();
usort($links, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
$total = count($links);
$total_visits = array_sum(array_column($links, 'visit_count'));
?>

<div style="display:flex;gap:16px;margin-bottom:20px;font-size:.85rem;color:#8b8fa3">
  <span><i class="ph ph-link"></i> 共 <?= $total ?> 条链接</span>
  <span><i class="ph ph-eye"></i> 共 <?= $total_visits ?> 次访问</span>
</div>

<?php if ($total === 0): ?>
<div class="empty"><i class="ph ph-link-break" style="font-size:2rem;display:block;margin-bottom:8px"></i>暂无短链接</div>
<?php else: ?>
<table>
<thead><tr><th>短链接</th><th>目标网址</th><th>标题</th><th>访问</th><th>创建时间</th><th></th></tr></thead>
<tbody>
<?php foreach ($links as $l): ?>
<tr>
  <td class="slug">/s/<?= htmlspecialchars($l['slug']) ?></td>
  <td class="url"><a href="<?= htmlspecialchars($l['original_url']) ?>" target="_blank"><?= htmlspecialchars($l['original_url']) ?></a></td>
  <td><?= htmlspecialchars($l['title'] ?: '-') ?></td>
  <td class="meta"><?= $l['visit_count'] ?></td>
  <td class="meta"><?= $l['created_at'] ?></td>
  <td>
    <form method="post" style="display:inline" onsubmit="return confirm('确定删除？')">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= $l['id'] ?>">
      <?= csrf_field() ?>
      <button class="btn btn-danger btn-sm"><i class="ph ph-trash"></i></button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>
<?php endif; ?>

</body>
</html>
