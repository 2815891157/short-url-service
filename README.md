# 短链接服务

PHP + MySQL 短链接服务，兼容 InfinityFree 免费主机。

## 功能

- 创建短链接（自定义后缀 + 标题）
- 访问短链接直接 302 跳转
- 管理所有短链接（查看、删除、访问计数）
- 网址有效性检测（DNS + HEAD，SSRF 防护）
- 全中文界面，无 emoji，直角设计

## InfinityFree 部署

### 第一步：上传全部文件

将所有文件上传到网站根目录（不要建子文件夹）。

### 第二步：打开安装向导

浏览器访问 `https://你的域名/setup.php`

填入你在 InfinityFree 控制面板 → MySQL Databases 里创建的数据库信息，点击「一键安装」即可。系统会自动连接数据库并建表。

### 第三步：完成

安装成功后点击链接进入短链接服务，开始使用。

## 文件说明

| 文件 | 用途 |
|------|------|
| `setup.php` | 安装向导（首次使用打开这个）|
| `config.php` | 数据库配置（安装向导自动生成）|
| `init.php` | 数据库连接 + 自动建表 |
| `api.php` | 所有 API 接口 |
| `s.php` | 短链接跳转 |
| `.htaccess` | URL 重写规则 |
| `index.html` | 主页面 |
| `404.html` | 404 页面 |
| `style.css` | 样式 |
| `app.js` | 前端逻辑 |
| `router.php` | 本地测试用（可不传）|

## 本地测试

```bash
php -S localhost:8000 router.php
```
