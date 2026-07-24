# 短链接服务

基于 PHP + MySQL 的短链接服务，兼容 InfinityFree 免费主机。

## 功能

- 创建短链接（自定义后缀 + 标题）
- 访问短链接直接 302 跳转，无中间页面
- 管理所有短链接（查看、删除、访问计数）
- 网址有效性检测（DNS + HEAD 请求，SSRF 防护）
- 全中文界面，无 emoji，直角设计，Phosphor 图标

## 部署到 InfinityFree

### 第一步：创建数据库

1. 登录 InfinityFree 控制面板
2. 进入 **MySQL Databases**，创建一个数据库
3. 记下数据库名、用户名、密码
4. 点击 **phpMyAdmin** 进入数据库管理
5. 点击 **SQL** 标签页，粘贴 `database.sql` 的内容并执行

### 第二步：修改配置

编辑 `config.php`，填入你的数据库信息：

```php
define('DB_HOST', 'sql123.infinityfree.com');   // MySQL 主机名
define('DB_NAME', 'if0_12345678_shorturl');      // 数据库名
define('DB_USER', 'if0_12345678');               // 数据库用户名
define('DB_PASS', 'your_password_here');          // 数据库密码
```

### 第三步：上传文件

将所有文件（不含本 README 和 database.sql 说明部分）上传到网站根目录。

需要上传的文件：
```
.htaccess
config.php
init.php
api.php
s.php
index.php
public/
  index.html
  404.html
  css/style.css
  js/app.js
```

### 第四步：访问

打开你的域名即可使用。首次访问会自动创建数据表。

## 本地测试

如果本地有 PHP 环境（如 XAMPP / MAMP），可以直接：

```bash
cd 项目目录
php -S localhost:8000
# 打开 http://localhost:8000
```

## 文件说明

| 文件 | 用途 |
|------|------|
| `config.php` | 数据库配置 |
| `init.php` | 数据库连接 + 建表 + 工具函数 |
| `api.php` | 所有 API 接口 |
| `s.php` | 短链接跳转处理 |
| `index.php` | 主页面入口 |
| `.htaccess` | URL 重写规则 |
| `database.sql` | 建表 SQL（手动建表用）|
| `public/` | 前端静态文件 |
