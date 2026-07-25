# 短链接服务

PHP 文件存储，无数据库，上传即用。

## 环境要求

- PHP 7.4+
- Apache + mod_rewrite（AllowOverride All）

## 使用方法

1. 下载 zip，解压，上传用户端文件到主机根目录
2. 打开域名，直接创建短链接

## 文件结构

**用户端（必须上传）：**
```
.htaccess    api.php     index.php    s.php
store.php    style.css   404.html
```

**管理后台（可选，单独上传）：**
```
admin.php
```

## 管理后台

`admin.php` 是独立文件：

- 上传 → 打开 `admin.php` → 查看/删除所有链接
- 删掉 `admin.php` → 服务正常运行，不受影响
- 不上传 → 用户端正常工作

## 安全说明

- 短链接跳转使用 307 临时重定向
- URL 检测仅发 HEAD 请求，禁止内网地址
- 所有写操作使用文件锁防止并发竞态
- 创建链接和 URL 检测有 IP 级速率限制
- `.json` 和 `.log` 文件通过 .htaccess 禁止外部访问
