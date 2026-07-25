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
function save_data($d) {
    $fp = fopen(DATA_FILE, 'c');
    if (!$fp) return;
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    flock($fp, LOCK_UN);
    fclose($fp);
}

$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $links = load_data();
    $links = array_values(array_filter($links, fn($l) => $l['id'] !== $id));
    save_data($links);
    $flash = '删除成功';
}
if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') $flash = '删除成功';

$links = load_data();
usort($links, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
$total = count($links);
$total_visits = array_sum(array_column($links, 'visit_count'));
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
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#0f1117;color:#e4e6ef;min-height:100vh;padding:32px}
.wrap{max-width:1000px;margin:0 auto}
h1{font-size:1.3rem;margin-bottom:20px;display:flex;align-items:center;gap:8px}
h1 i{color:#4f7df7}
.btn{padding:10px 20px;border:1px solid #2d3142;font-size:.85rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.btn-danger{background:#e74c3c;color:#fff;border-color:#e74c3c}
.btn-danger:hover{background:#c0392b}
.btn-sm{padding:5px 10px;font-size:.78rem}
.msg{background:#1b2d1b;border:1px solid #2ecc71;color:#2ecc71;padding:10px 14px;margin-bottom:16px;font-size:.85rem}
table{width:100%;border-collapse:collapse;margin-top:16px}
th,td{padding:10px 12px;border-bottom:1px solid #2d3142;text-align:left;font-size:.85rem}
th{background:#242836;font-weight:600;color:#8b8fa3}
td{color:#e4e6ef}
.slug{color:#4f7df7;font-family:monospace;font-weight:600}
.url{color:#8b8fa3;word-break:break-all;max-width:400px}
.meta{color:#5c6073;font-size:.78rem}
.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
.empty{text-align:center;padding:40px;color:#5c6073}
a{color:#4f7df7;text-decoration:none}
a:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="wrap">
<div class="topbar">
  <h1><i class="ph ph-list"></i> 短链接管理</h1>
  <a href="index.php" class="btn" style="background:#242836;color:#8b8fa3"><i class="ph ph-arrow-left"></i> 返回前台</a>
</div>

<?php if ($flash): ?><div class="msg"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($flash) ?></div><?php endif; ?>

<div style="display:flex;gap:16px;margin-bottom:20px;font-size:.85rem;color:#8b8fa3">
  <span><i class="ph ph-link"></i> 共 <?= $total ?> 条链接</span>
  <span><i class="ph ph-eye"></i> 共 <?= $total_visits ?> 次访问</span>
</div>

<?php if ($total === 0): ?>
<div class="empty"><i class="ph ph-link-break" style="font-size:2rem;display:block;margin-bottom:8px"></i>暂无短链接</div>
<?php else: ?>
<table>
<thead><tr><th>短链接</th><th>目标网址</th><th>访问</th><th>创建时间</th><th></th></tr></thead>
<tbody>
<?php foreach ($links as $l): ?>
<tr>
  <td class="slug">/s/<?= htmlspecialchars($l['slug']) ?></td>
  <td class="url"><a href="<?= htmlspecialchars($l['original_url']) ?>" target="_blank"><?= htmlspecialchars($l['original_url']) ?></a></td>
  <td class="meta"><?= $l['visit_count'] ?></td>
  <td class="meta"><?= $l['created_at'] ?></td>
  <td>
    <form method="post" style="display:inline" onsubmit="return confirm('确定删除？')">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= $l['id'] ?>">
      <button class="btn btn-danger btn-sm"><i class="ph ph-trash"></i></button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>
</body>
</html>
