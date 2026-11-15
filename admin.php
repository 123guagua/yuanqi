<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$notice = '';
$error = '';
$preview = $_SESSION['import_preview'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    verify_csrf();

    if ($action === 'login') {
        $username = (string)($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $expectedUser = (string)($config['app']['admin_username'] ?? 'admin');
        $hash = (string)($config['app']['admin_password_hash'] ?? '');
        if ($username === $expectedUser && $hash !== '' &&
            $hash !== 'REPLACE_WITH_PASSWORD_HASH' && password_verify($password, $hash)) {
            session_regenerate_id(true);
            $_SESSION['is_admin'] = true;
            $_SESSION['login_time'] = time();
            header('Location: admin.php');
            exit;
        }
        $error = '用户名或密码错误。请检查 config.php 中的管理员设置。';
    } elseif ($action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        header('Location: admin.php');
        exit;
    } else {
        require_admin();

        if ($action === 'preview') {
            if (!isset($_FILES['proxy_file']) || $_FILES['proxy_file']['error'] !== UPLOAD_ERR_OK) {
                $error = '上传失败，请重新选择 TXT 文件。';
            } else {
                $file = $_FILES['proxy_file'];
                $maxBytes = (int)($config['app']['max_upload_bytes'] ?? 2097152);
                if ($file['size'] > $maxBytes || $file['size'] <= 0) {
                    $error = '文件为空或超过上传大小限制。';
                } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'txt') {
                    $error = '只允许上传 .txt 文件。';
                } else {
                    $content = file_get_contents($file['tmp_name']);
                    if ($content === false || str_contains($content, "\0")) {
                        $error = '文件内容无法读取或格式不正确。';
                    } else {
                        $result = parse_proxy_text($content, (int)($config['app']['max_import_rows'] ?? 5000));
                        $_SESSION['import_preview'] = $result;
                        $preview = $result;
                        $notice = '解析完成，请核对后再确认发布。';
                    }
                }
            }
        } elseif ($action === 'publish') {
            $pending = $_SESSION['import_preview'] ?? null;
            if (!is_array($pending) || empty($pending['valid'])) {
                $error = '没有可发布的数据，请重新上传 TXT。';
            } else {
                try {
                    $pdo->beginTransaction();
                    $insert = $pdo->prepare('INSERT INTO proxies (host, port, username, password) VALUES (:host, :port, :username, :password)');
                    $inserted = 0;
                    foreach ($pending['valid'] as $row) {
                        $insert->execute($row);
                        $inserted++;
                    }
                    $pdo->commit();
                    unset($_SESSION['import_preview']);
                    $preview = null;
                    $notice = "成功发布 {$inserted} 条代理。";
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('Proxy import failed: ' . $e->getMessage());
                    $error = '保存失败，请检查数据库配置。';
                }
            }
        } elseif ($action === 'delete') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id) {
                $del = $pdo->prepare('DELETE FROM proxies WHERE id = :id');
                $del->execute([':id' => $id]);
                $notice = $del->rowCount() ? '代理已删除。' : '未找到该代理。';
            }
        } elseif ($action === 'delete_all') {
            $pdo->exec('DELETE FROM proxies');
            $notice = '所有代理已删除。';
        }
    }
}

$loggedIn = is_admin();
$items = [];
$total = 0;
if ($loggedIn) {
    $total = (int)$pdo->query('SELECT COUNT(*) FROM proxies')->fetchColumn();
    $items = $pdo->query('SELECT id, host, port, username, created_at FROM proxies ORDER BY id DESC LIMIT 100')->fetchAll();
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>管理员后台</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar"><a class="brand" href="index.php">SOCKS5 管理后台</a><a class="admin-link" href="index.php">查看公开页面</a></header>
<main class="container admin-container">
  <div class="page-heading"><div><p class="eyebrow">ADMINISTRATION</p><h1>代理管理</h1><p class="muted">TXT 导入 · 预览 · 发布 · 删除</p></div></div>
  <?php if ($notice !== ''): ?><div class="alert success"><?= e($notice) ?></div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

  <?php if (!$loggedIn): ?>
    <section class="panel login-panel">
      <h2>管理员登录</h2>
      <form method="post" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="login">
        <label>管理员用户名<input name="username" autocomplete="username" required></label>
        <label>管理员密码<input type="password" name="password" autocomplete="current-password" required></label>
        <button type="submit">登录后台</button>
      </form>
      <p class="muted small">首次部署请先按 README 配置数据库和管理员密码哈希。</p>
    </section>
  <?php else: ?>
    <section class="panel">
      <div class="panel-heading"><div><h2>上传代理 TXT</h2><p class="muted">格式：IP:端口:用户名:密码，每行一条。</p></div><span class="count"><?= number_format($total) ?> 条已发布</span></div>
      <form method="post" enctype="multipart/form-data" class="upload-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="preview">
        <input type="file" name="proxy_file" accept=".txt,text/plain" required>
        <button type="submit">上传并预览</button>
      </form>
      <p class="warning-text">注意：确认发布后，代理凭证会公开显示在网站前台。请勿上传不允许公开分享的凭证。</p>
    </section>

    <?php if (is_array($preview)): ?>
      <section class="panel">
        <h2>导入预览</h2>
        <p>有效记录：<strong><?= count($preview['valid'] ?? []) ?></strong> 条；错误或跳过：<strong><?= count($preview['errors'] ?? []) ?></strong> 条。</p>
        <?php if (!empty($preview['valid'])): ?>
          <div class="table-wrap"><table><thead><tr><th>IP</th><th>端口</th><th>用户名</th><th>密码</th></tr></thead><tbody>
          <?php foreach (array_slice($preview['valid'], 0, 20) as $row): ?><tr><td><?= e($row['host']) ?></td><td><?= (int)$row['port'] ?></td><td><?= e($row['username']) ?></td><td><?= e($row['password']) ?></td></tr><?php endforeach; ?>
          </tbody></table></div>
          <?php if (count($preview['valid']) > 20): ?><p class="muted">仅展示前 20 条，确认发布会导入全部有效记录。</p><?php endif; ?>
          <form method="post" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="publish">
            <button type="submit" class="success-button">确认发布 <?= count($preview['valid']) ?> 条</button>
          </form>
        <?php endif; ?>
        <?php if (!empty($preview['errors'])): ?>
          <div class="error-list"><strong>错误明细</strong><ul><?php foreach (array_slice($preview['errors'], 0, 50) as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <section class="panel">
      <div class="panel-heading"><div><h2>已发布代理</h2><p class="muted">最近 100 条记录</p></div>
        <form method="post" onsubmit="return confirm('确定删除全部代理吗？此操作不可撤销。')">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="delete_all">
          <button type="submit" class="danger-button">删除全部</button>
        </form>
      </div>
      <?php if (!$items): ?><div class="empty">目前没有已发布代理。</div><?php else: ?>
      <div class="table-wrap"><table><thead><tr><th>ID</th><th>IP</th><th>端口</th><th>用户名</th><th>发布时间</th><th>操作</th></tr></thead><tbody>
      <?php foreach ($items as $item): ?><tr>
        <td><?= (int)$item['id'] ?></td><td><?= e($item['host']) ?></td><td><?= (int)$item['port'] ?></td><td><?= e($item['username']) ?></td><td><?= e($item['created_at']) ?></td>
        <td><form method="post" onsubmit="return confirm('确定删除这条代理吗？')">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
          <button class="danger-button small-button" type="submit">删除</button>
        </form></td>
      </tr><?php endforeach; ?>
      </tbody></table></div><?php endif; ?>
    </section>
    <form method="post" class="logout-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button class="secondary-button">退出登录</button></form>
  <?php endif; ?>
</main>
</body>
</html>
