# Yuanqi SOCKS5 Proxy Directory

一个基于 PHP + MySQL 的 SOCKS5 代理列表管理系统。

## 功能

- 管理员登录后台
- 上传 TXT 文件，格式为每行一条 `IP:端口:用户名:密码`
- 上传后先预览有效数据和错误行，再确认发布
- 使用 MySQL 永久保存已发布代理
- 公开页面展示代理信息并为每条代理生成独立二维码
- 搜索、分页、复制 SOCKS5 URI
- 后台删除单条或批量删除代理
- 使用 PDO 预处理语句、密码哈希和 CSRF Token

## 环境要求

- PHP 8.1 或更高版本
- MySQL 5.7+ / MariaDB 10.4+
- PHP 扩展：PDO、pdo_mysql、fileinfo（非强制）
- Apache（支持 .htaccess）或 Nginx
- 建议全站启用 HTTPS

## 部署步骤

1. 将仓库文件上传到网站根目录。
2. 在主机控制面板创建 MySQL 数据库和数据库用户，并授予该数据库的全部权限。
3. 使用 phpMyAdmin 导入 `database.sql`。
4. 将 `config.example.php` 复制为 `config.php`。
5. 编辑 `config.php`，填写数据库主机、数据库名、数据库用户名和密码。
6. 设置管理员用户名和密码哈希。
7. 确认网站 PHP 版本为 8.1 或更高，然后访问网站首页。
8. 访问 `/admin.php` 登录，上传 TXT，检查预览后点击“确认发布”。

### 生成管理员密码哈希

不要把管理员明文密码写进 GitHub。

在有 PHP CLI 的环境运行：

```bash
php -r "echo password_hash('替换成你自己的强密码', PASSWORD_DEFAULT), PHP_EOL;"
```

将输出结果复制到 config.php 的 `admin_password_hash`。如果主机没有终端，可以在本地 PHP 环境运行同一条命令。生成哈希后不要保留公开的临时密码生成页面。

## TXT 格式

每行一条，格式必须是：

```text
204.143.79.108:3010:username:password
173.213.99.76:3184:username2:password2
```

支持空行和以 `#` 开头的注释行。当前版本校验 IPv4 地址、1–65535 端口，以及非空用户名和密码。格式错误的行不会导入，会在预览页显示行号和原因。

## 管理入口

- 公开列表：网站首页
- 管理后台：`/admin.php`

后台上传后不会立即公开，必须在预览无误后点击“确认发布”。已发布的数据会保存到 MySQL，删除浏览器里的 TXT 文件不会影响数据库。

## 重要安全说明

**公开列表会公开代理 IP、端口、用户名和密码，二维码也包含完整的 SOCKS5 连接 URI。任何访问网站的人都可以查看和使用这些凭证。只上传明确允许公开分享的代理。**

- 请使用 HTTPS。
- 请设置强管理员密码，不要复用其他网站密码。
- 不要将真实的 `config.php` 提交到 GitHub。
- 定期更新 PHP 和服务器系统。
- 当前版本没有多管理员、操作审计和代理可用性检测功能。
- 二维码由浏览器从 CDN 加载 QRCode.js 生成；如果 CDN 无法访问，代理文字仍会显示，但二维码可能无法生成。
- 如果使用 Nginx，需在 Nginx 配置中禁止直接访问 config.php、database.sql 等敏感文件；.htaccess 规则只对 Apache 生效。

## 目录结构

- `index.php`：公开代理列表
- `admin.php`：管理员登录、导入、发布和删除
- `config.example.php`：配置模板，部署时复制为 config.php
- `database.sql`：数据库表结构
- `includes/bootstrap.php`：应用初始化和数据库连接
- `includes/functions.php`：安全、校验和通用函数
- `assets/style.css`：页面样式
- `assets/app.js`：二维码和复制交互
