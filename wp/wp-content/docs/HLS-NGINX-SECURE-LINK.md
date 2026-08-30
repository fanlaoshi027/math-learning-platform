# MathCourse HLS Nginx Secure Link 部署方案

> 本文件用于生产服务器部署。不要把下面的 `location` 直接复制到生产环境后不测试。

## 目标

MathCourse 负责：

1. 判断登录、课程授权、试看权限。
2. 生成短时签名播放地址。
3. 向前端提供经过保护的播放入口。

Nginx 负责：

1. 校验短时签名。
2. 直接发送 `.m3u8`、`.ts`、`.m4s` 等文件。
3. 不让 PHP 代理每一个视频分片。

这样可以避免目前 PHP `wp_remote_get()` 代理每个分片造成的 CPU、内存和 PHP-FPM 压力。

## 推荐目录

建议视频目录与 WordPress PHP 代码分离，例如：

```text
/www/wwwroot/video-hls/
└── 8shangdapeiyou/
    └── hls/
        └── p1/
            ├── index.m3u8
            ├── segment_000.ts
            ├── segment_001.ts
            └── ...
```

生产环境不要让这个目录通过普通公开 URL 直接访问。

## Nginx 思路

使用 Nginx `secure_link` / `secure_link_md5` 校验短期签名。

示意配置：

```nginx
location ^~ /protected-hls/ {
    secure_link $arg_md5,$arg_expires;
    secure_link_md5 "$secure_link_expires$uri mathcourse-hls-secret";

    if ($secure_link = "") {
        return 403;
    }

    if ($secure_link = "0") {
        return 410;
    }

    alias /www/wwwroot/video-hls/;
    types {
        application/vnd.apple.mpegurl m3u8;
        video/mp2t ts;
        video/iso.segment m4s;
    }
}
```

> `mathcourse-hls-secret` 必须替换为服务器上的高强度随机秘密，并且不能提交到 Git。

## 重要安全要求

### 1. 原始视频目录必须禁止公开访问

如果视频实际位于：

```text
/www/wwwroot/video-hls/
```

不能同时存在一个可以直接访问：

```text
https://example.com/video-hls/...
```

的公开 location。

### 2. 不把密钥写进仓库

Git 中只保存配置模板，例如：

```text
MATHCOURSE_HLS_SECRET=CHANGE_ME
```

真实 secret 应放在服务器 Nginx 配置、环境变量或部署系统中。

### 3. Token 要短时有效

推荐默认有效期：

```text
5～10 分钟
```

不要使用永久 token。

### 4. m3u8 内的分片也必须带签名

不能只保护：

```text
index.m3u8
```

然后让：

```text
segment_001.ts
segment_002.ts
```

可以直接公开访问。

### 5. Referer 只能作为辅助保护

Referer 可以减少普通盗链，但不能作为唯一鉴权机制。

真正的权限判断应由：

```text
课程授权 + 短时 Token
```

负责。

## MathCourse PHP 层的职责边界

最终目标：

```text
学生
 ↓
MathCourse
 ↓
Access Service
 ↓
生成短时 token
 ↓
返回受保护 HLS URL
 ↓
Nginx
 ↓
secure_link 验证
 ↓
直接发送视频文件
```

PHP 不应该在生产环境中逐个 `wp_remote_get()` 视频分片。

## 当前过渡状态

当前 `Video_Router` 已经能够：

- 检查课程/试看权限；
- 使用短时签名；
- 重写 m3u8 中的媒体地址；
- 防止锁定课时直接返回 HLS 地址。

在服务器实际启用 Nginx `secure_link` 之前，不应直接删除现有 PHP Gateway。

部署时应按以下顺序：

1. 备份视频目录和 Nginx 配置。
2. 建立受保护视频目录。
3. 配置 `secure_link`。
4. 用浏览器验证 m3u8 和 ts 正常播放。
5. 验证过期 token 返回 403/410。
6. 验证直接访问原始视频 URL 被拒绝。
7. 再把 MathCourse 的 URL 生成器切换到 Nginx Secure Link。
8. 最后停用 PHP 分片代理。

## 与本项目观看进度的关系

HLS 防盗链与观看进度完全分离。

终端本地：

```text
lesson_id → currentTime
```

只用于断点续播。

服务器：

```text
student_id + lesson_id → completed
```

只用于课程完成进度。

服务器不会因为 HLS 鉴权而保存学生每秒播放位置。
