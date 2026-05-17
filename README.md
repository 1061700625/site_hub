# PHP SiteHub

一个轻量级的 PHP 单页服务导航页，用于集中展示同一域名下不同端口或不同路径的服务入口。

适合用于内网导航、服务器服务入口、个人站点工具页、NAS 服务面板、开发环境入口页等场景。

![](https://github.com/1061700625/site_hub/blob/main/cover.png?raw=true)

> 演示网站：https://xfxuezhang.cn/site/

## 功能特性

- 单文件 PHP 部署
- 无需数据库，数据保存到本地 JSON 文件
- 支持服务入口展示
- 支持服务入口添加、编辑、删除
- 支持拖拽排序
- 支持访问量统计
- 支持管理员登录
- 普通用户仅可浏览和跳转
- 登录后才显示管理功能
- 响应式布局，适配桌面端和移动端

## 页面效果

普通用户访问时，只能看到服务入口卡片并点击跳转。

管理员登录后，可以看到：

- 添加入口
- 编辑入口
- 删除入口
- 拖拽排序
- 保存排序
- 退出登录

## 运行环境

- PHP 7.4+
- Apache / Nginx
- 支持 PHP Session
- 网站目录需要允许 PHP 写入本地 JSON 文件

## 快速开始

将 `index.php` 上传到站点目录，例如：

```bash
/var/www/html/hub/index.php
```

然后访问：

```text
http://your-domain.com/hub/
```

首次访问时，程序会自动创建数据文件：

```text
links.json
stats.json
```

如果自动创建失败，请手动创建并配置权限。

## 文件结构

```text
.
├── index.php      # 主程序
├── links.json     # 服务入口数据，自动生成
└── stats.json     # 访问量统计数据，自动生成
```

## Apache 权限配置

进入站点目录：

```bash
cd /path/to/your/site
```

手动创建 JSON 文件：

```bash
sudo touch links.json stats.json
sudo chmod 664 links.json stats.json
```

Ubuntu / Debian 通常使用：

```bash
sudo chown www-data:www-data links.json stats.json
```

CentOS / Rocky / AlmaLinux 通常使用：

```bash
sudo chown apache:apache links.json stats.json
```

如果需要 PHP 自动创建文件，需要确保站点目录本身也可写：

```bash
sudo chgrp www-data .
sudo chmod 775 .
```

CentOS 系统请将 `www-data` 替换为 `apache`。

## SELinux 说明

如果你使用的是 CentOS / Rocky / AlmaLinux，并且 SELinux 处于开启状态，即使 Linux 文件权限正确，也可能无法写入 JSON 文件。

查看 SELinux 状态：

```bash
getenforce
```

如果返回：

```text
Enforcing
```

可以为站点目录设置可写上下文：

```bash
sudo chcon -R -t httpd_sys_rw_content_t /path/to/your/site
```

## 管理员登录

管理员密码在 `index.php` 顶部配置，默认是`1024`。

请部署后立即修改默认密码：

```php
$adminPassword = 'change-your-password';
```

登录成功后，管理状态会通过 PHP Session 保存。

如果登录后刷新页面立刻失效，请检查 PHP Session 目录权限：

```bash
php -i | grep session.save_path
```

## 添加服务入口

登录后点击右上角的“添加入口”，填写：

- 网站名
- 网站地址
- 网站简介

网站地址需要填写完整 URL，例如：

```text
http://xfxuezhang.cn:8080
https://example.com/admin
```

## 编辑和删除入口

登录后，每个服务卡片右上角会显示：

- 编辑
- 删除

普通用户不会看到这些按钮。

## 拖拽排序

登录后，每个卡片左上角会显示拖拽手柄。

拖动卡片调整顺序后，右上角会出现“保存排序”按钮。

点击保存后，新的顺序会写入 `links.json`。

## 数据格式

`links.json` 示例：

```json
[
  {
    "id": "a1b2c3d4e5f6g7h8",
    "name": "示例服务",
    "url": "http://example.com:8080",
    "desc": "这是一个示例服务入口",
    "created_at": "2026-05-17 12:00:00"
  }
]
```

`stats.json` 示例：

```json
{
  "total_views": 1024,
  "last_visit_at": "2026-05-17 12:00:00"
}
```

## 安全建议

本项目适合轻量级个人使用或内网使用。

如果部署到公网，建议：

- 修改默认管理员密码
- 使用 HTTPS
- 不要将真实生产后台暴露到公网
- 限制 JSON 文件的直接访问
- 配置 Web Server 禁止访问 `links.json` 和 `stats.json`
- 定期备份 JSON 数据文件

### Apache 禁止访问 JSON 文件

可以在站点目录添加 `.htaccess`：

```apache
<FilesMatch "\.(json)$">
    Require all denied
</FilesMatch>
```

如果你的 Apache 版本较老，可以使用：

```apache
<FilesMatch "\.(json)$">
    Order allow,deny
    Deny from all
</FilesMatch>
```

### Nginx 禁止访问 JSON 文件

```nginx
location ~* \.json$ {
    deny all;
}
```

## 常见问题

### 保存失败，提示没有写入权限

请检查 `links.json` 是否存在，以及 PHP 进程是否有写权限。

```bash
ls -l links.json stats.json
```

根据实际 Web Server 用户调整属主：

```bash
sudo chown www-data:www-data links.json stats.json
```

或：

```bash
sudo chown apache:apache links.json stats.json
```

### 访问量不增加

请检查 `stats.json` 是否可写。

```bash
sudo chmod 664 stats.json
```

### 登录状态无法保持

请检查 PHP Session 是否正常工作。

```bash
php -i | grep session.save_path
```

确保该目录对 PHP 进程可写。

## 适用场景

- 个人服务器服务导航
- NAS 服务入口页
- Homelab 管理入口
- 内网工具导航
- 开发环境服务汇总
- 多端口 Web 服务入口页

## License

MIT License
