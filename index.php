<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(1, min(100, (int)($config['app']['items_per_page'] ?? 20)));

$where = '';
$params = [];
if ($q !== '') {
    $where = ' WHERE host LIKE :q OR username LIKE :q OR CAST(port AS CHAR) LIKE :q ';
    $params[':q'] = '%' . $q . '%';
}
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM proxies' . $where);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$sql = 'SELECT id, host, port, username, password, created_at FROM proxies' .
    $where . ' ORDER BY id ASC LIMIT :limit OFFSET :offset';
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) $stmt->bindValue($key, $value, PDO::PARAM_STR);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$proxies = $stmt->fetchAll();
function page_url(int $p, string $q): string {
    return '?' . http_build_query(['q' => $q, 'page' => $p]);
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($config['app']['name'] ?? 'SOCKS5 代理列表') ?></title>
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" href="assets/style.css">
<script defer src="https://cdn.jsdelivr.net/npm/qrcode@1.5.4/build/qrcode.min.js"></script>
<script defer src="assets/app.js"></script>
</head>
<body>
<header class="topbar">
  <a class="brand" href="index.php"><?= e($config['app']['name'] ?? 'SOCKS5 代理列表') ?></a>
  <a class="admin-link" href="admin.php">管理员入口</a>
</header>
<main class="container">
  <div class="page-heading">
    <div><p class="eyebrow">PROXY DIRECTORY</p><h1>代理列表</h1><p class="muted">共 <?= number_format($total) ?> 条已发布代理</p></div>
    <form class="search" method="get">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="搜索 IP、端口或账号">
      <button type="submit">搜索</button>
    </form>
  </div>
  <?php if (!$proxies): ?>
    <div class="empty">暂无已发布代理<?= $q !== '' ? '，请尝试其他搜索词。' : '。' ?></div>
  <?php endif; ?>
  <section class="proxy-list">
  <?php foreach ($proxies as $i => $proxy): $uri = proxy_uri($proxy); ?>
    <article class="proxy-card">
      <div class="proxy-details">
        <div class="proxy-title">名称：Proxy <?= (int)$proxy['id'] ?></div>
        <p>代理类型：socks5</p>
        <p>代理主机：<?= e($proxy['host']) ?></p>
        <p>代理端口：<?= (int)$proxy['port'] ?></p>
        <p>账号：<?= e($proxy['username']) ?></p>
        <p>密码：<?= e($proxy['password']) ?></p>
        <button class="copy-button" type="button" data-copy="<?= e($uri) ?>">复制 SOCKS5 地址</button>
      </div>
      <div class="qr-area">
        <canvas class="proxy-qr" data-qr="<?= e($uri) ?>" aria-label="Proxy <?= (int)$proxy['id'] ?> 二维码"></canvas>
        <span>扫码导入代理</span>
      </div>
    </article>
  <?php endforeach; ?>
  </section>
  <?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="分页">
      <?php if ($page > 1): ?><a href="<?= e(page_url($page - 1, $q)) ?>">上一页</a><?php endif; ?>
      <span>第 <?= $page ?> / <?= $pages ?> 页</span>
      <?php if ($page < $pages): ?><a href="<?= e(page_url($page + 1, $q)) ?>">下一页</a><?php endif; ?>
    </nav>
  <?php endif; ?>
</main>
<footer class="footer">SOCKS5 Proxy Directory</footer>
</body>
</html>
