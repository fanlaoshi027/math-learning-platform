# HLS Nginx 分片加速

MathCourse 的 HLS 网关可以使用 Nginx `X-Accel-Redirect` 让 PHP 只负责权限和签名校验，而由 Nginx 直接发送 `.ts/.m4s/.aac` 分片。

## 1. Nginx 配置

在宝塔对应网站的 Nginx 配置中加入：

```nginx
# MathCourse HLS 内部加速区
# 必须使用 internal，浏览器不能直接访问此地址。
location ^~ /__mathcourse_hls/ {
    internal;
    alias /www/wwwroot/你的站点/wp-content/uploads/;

    types {
        video/mp2t ts;
        video/iso.segment m4s;
        audio/aac aac;
    }

    add_header Cache-Control "public, max-age=3600" always;
}
```

把 `你的站点` 替换成宝塔实际网站根目录，例如：

```text
/www/wwwroot/fanlaoshishu.com/wp-content/uploads/
```

保存后重载 Nginx。

## 2. 开启 PHP 加速模块

在 `wp-config.php` 中加入：

```php
define('MATHCOURSE_HLS_ACCEL', true);
```

建议放在 `/* That's all, stop editing! */` 之前。

## 3. 工作方式

浏览器仍然只看到：

```text
/math-video/课程课时/过期时间/签名/?file=...
```

PHP 首先检查：

- 签名
- 过期时间
- 课程授权
- 试看权限
- uploads 路径限制

校验通过后返回 `X-Accel-Redirect`，Nginx 直接传输实际分片。

因此不会暴露原始 m3u8 地址，也不会取消课程权限控制。

## 4. 注意

m3u8 和子 m3u8 仍然由 PHP 网关重写，因为其中的分片 URL 需要签名；只有最终媒体分片走 Nginx 直传。

如果服务器不是 Nginx，暂时不要开启 `MATHCOURSE_HLS_ACCEL`，现有 PHP 网关仍可继续工作。
