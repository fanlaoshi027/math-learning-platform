# 樊老师数学项目开发准则

> 本文件是 `WP_7` 分支及后续开发的重要开发约束。
>
> **最高原则：任何开发都不能破坏已经验证正常的视频播放。**

## 1. 项目基本原则

### 1.1 稳定优先

功能增加、UI 优化、代码重构都必须建立在现有稳定功能之上。

禁止为了“更漂亮”“更简洁”或“更先进”的实现，直接替换已经验证可用的核心链路。

### 1.2 播放器是核心基础设施

视频播放属于平台核心功能，优先级高于 UI、美化、重构以及非核心新功能。

任何涉及播放器、HLS、视频路由、媒体文件、Nginx 加速、课程学习页的修改，都必须首先考虑播放兼容性。

### 1.3 小步修改

一次修改尽量只解决一个明确问题。

不要在一次提交中同时：

- 修改播放器逻辑
- 重构 HLS 架构
- 修改课程页面
- 修改登录逻辑
- 修改 Nginx 路由
- 修改数据库结构

如果确实需要同时修改，必须明确记录依赖关系，并逐项测试。

---

# 2. 当前已验证的稳定视频链路

当前已经验证可以正常播放的链路为：

```text
ArtPlayer
    ↓
HLS.js / 原生 HLS
    ↓
/index.php?math_video=...
    ↓
Video_Router
    ↓
index.m3u8
    ↓
带签名的 file 请求
    ↓
Hls_Accelerator / PHP fallback
    ↓
Nginx X-Accel-Redirect
    ↓
/www/wwwroot/fanlaoshishu-media/hls/
    ↓
TS / m4s / aac
```

## 2.1 当前稳定基准

截至本文件建立时，最新已验证可播放提交为：

```text
280ae81e7018d7923bdab721886206b01177f3b8
```

提交内容解决了 `/math-video/.../` 被 Nginx 直接返回 `404 Not Found` 的问题，将视频入口和分片网关统一改为 WordPress `index.php` 参数形式。

**后续任何视频链路修改，都必须与该稳定基准进行比较。**

---

# 3. 播放器修改红线

以下文件属于高风险文件：

```text
plugins/math-course/assets/js/player.js
plugins/math-course/includes/Video/class-player.php
plugins/math-course/includes/Video/class-video-router.php
plugins/math-course/includes/Video/class-hls-accelerator.php
plugins/math-course/includes/Video/class-hls-converter.php
plugins/math-course/includes/Video/class-local-hls-source.php
plugins/math-course/math-course.php
```

修改这些文件之前必须：

1. 先读取当前版本。
2. 与最近一次“已验证可播放”版本比较。
3. 明确说明修改为什么不会破坏播放链。
4. 修改后先测试视频，再继续其他功能开发。

## 3.1 禁止随意替换播放器架构

除非当前方案确实无法满足需求，否则不要随意：

- 更换 ArtPlayer
- 更换 HLS.js
- 改变 HLS 初始化方式
- 改变 m3u8 获取方式
- 改变 TS 分片获取方式
- 改变签名算法
- 改变视频 URL 结构
- 改变 Nginx 加速方式
- 删除 PHP fallback
- 删除已经验证可用的兼容逻辑

---

# 4. HLS 调试原则

如果视频无法播放，**禁止凭感觉连续修改代码**。

必须按照真实 HTTP 请求链逐层检查：

```text
① 视频入口
   ↓
② m3u8
   ↓
③ m3u8 中的分片 URL
   ↓
④ 第一个 TS/m4s/aac 请求
   ↓
⑤ Hls_Accelerator
   ↓
⑥ X-Accel-Redirect
   ↓
⑦ Nginx alias
   ↓
⑧ 实际媒体文件
```

重点检查：

- HTTP Status Code
- Request URL
- Response Headers
- Content-Type
- Response 内容
- 是否发生 301/302/403/404/500
- 是否进入 WordPress
- 是否进入 PHP Router
- 是否返回正确的 m3u8
- m3u8 中的分片地址是否正确

## 4.1 特别注意 Nginx 404

如果浏览器出现：

```text
404 Not Found
nginx
```

而不是 WordPress/PHP 返回的业务错误，优先判断为：

> 请求没有进入 WordPress 路由。

此时不要首先修改 `player.js`。

---

# 5. URL 与路由原则

当前稳定的视频入口使用：

```text
/index.php?math_video={lesson_id}&math_video_exp={expires}&math_video_sig={signature}
```

分片使用：

```text
/index.php?file={file}&math_video={lesson_id}&math_video_exp={expires}&math_video_sig={signature}
```

除非经过充分测试，否则不要恢复为依赖 Nginx rewrite 的：

```text
/math-video/{lesson_id}/{expires}/{signature}/
```

漂亮 URL 不是优先级，**稳定播放才是优先级。**

---

# 6. Nginx / 媒体目录原则

当前媒体目录为：

```text
/www/wwwroot/fanlaoshishu-media/hls/
```

Nginx 加速入口：

```text
/__mathcourse_hls/
```

典型配置：

```nginx
location /__mathcourse_hls/ {
    internal;
    alias /www/wwwroot/fanlaoshishu-media/hls/;
    sendfile on;
    tcp_nopush on;
    add_header Cache-Control "private, max-age=86400, immutable";
}
```

任何涉及这部分的修改，都必须同时考虑：

- `internal`
- `alias`
- `X-Accel-Redirect`
- 文件实际路径
- 权限
- Range 请求
- Content-Type

---

# 7. Git 开发规则

## 7.1 一个修改，一个明确提交

提交信息应说明实际目的，例如：

```text
fix: restore HLS playback
fix: repair video gateway
feat: improve course card layout
docs: update development guidelines
```

不要使用含义不清的提交信息：

```text
update
change
fix bug
test
```

## 7.2 核心修复不要被覆盖

如果某次修改已经解决了播放问题，后续开发不能直接覆盖该文件的核心逻辑。

如必须重构：

1. 先保存当前稳定提交。
2. 修改。
3. 测试。
4. 如果失败，立即回退到稳定版本。

## 7.3 不要为了测试删除稳定代码

测试新方案时，优先增加兼容层或开关，而不是直接删除旧的可用实现。

---

# 8. UI 开发规则

UI 修改原则：

> **播放器外观可以改，播放器播放机制不能随便改。**

以下修改通常可以独立进行：

- 颜色
- 字体
- 按钮样式
- 间距
- 卡片
- 页面布局
- 响应式 CSS
- Header
- Footer

如果只是 UI 修改，应尽量只修改：

```text
.css
.html / PHP 模板
```

不要为了 CSS/UI 需求顺便重构播放器 JS 或 HLS PHP。

---

# 9. 测试标准

## 9.1 视频功能最低测试

每次涉及视频代码的提交，至少测试：

- [ ] 课程页面可以打开
- [ ] 视频可以开始播放
- [ ] 能显示正确时长
- [ ] 能正常播放前几秒
- [ ] 能继续播放多个分片
- [ ] 拖动进度条正常
- [ ] 播放速度正常
- [ ] 播放完成状态正常
- [ ] 浏览器刷新后仍能播放

## 9.2 Network 检查

如果出现播放异常，必须查看 Network：

```text
m3u8
segment-00000.ts
segment-00001.ts
```

不能只根据播放器界面判断原因。

## 9.3 修改后的第一优先测试

如果一次提交同时包含 UI 和视频代码：

```text
第一步：测试视频播放
第二步：测试页面 UI
第三步：测试其他功能
```

而不是先测试 UI。

---

# 10. 故障处理规则

出现回归问题时：

### 第一步：停止继续修改

不要连续叠加修复。

### 第二步：确定最后一个正常提交

通过 Git 比较：

```text
最后正常版本
        ↓
出现问题的版本
```

找出实际发生变化的文件。

### 第三步：优先回滚最近的高风险修改

特别关注：

- HLS Router
- HLS Accelerator
- Player JS
- Player PHP
- Nginx 相关代码
- 视频 URL 生成逻辑

### 第四步：用 Network 验证

确认实际 HTTP 请求恢复正常后，再进行下一步修改。

---

# 11. 新功能开发流程

以后每个功能按照以下流程进行：

```text
需求确认
   ↓
确认是否涉及核心播放链
   ↓
读取当前代码
   ↓
与稳定版本比较
   ↓
设计最小修改方案
   ↓
修改
   ↓
Git 提交
   ↓
测试
   ↓
确认没有影响视频
   ↓
继续下一项功能
```

---

# 12. 最重要的项目规则

请始终牢记以下五条：

> **1. 能播放 > 漂亮。**
>
> **2. 稳定版本 > 新架构。**
>
> **3. 小修改 > 大重构。**
>
> **4. 实际 Network 结果 > 猜测。**
>
> **5. 任何新功能都不能以视频无法播放为代价。**

---

## 当前状态

当前 `WP_7` 已经验证视频播放恢复正常。

**后续开发默认以当前可播放状态为基线，除非明确提出“修改视频播放架构”。**
