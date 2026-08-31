# 前端主题开发规则

版本：V1.0

## 1. 主题与插件分工

主题：表现层。

插件：业务层。

主题允许：

- PHP 模板
- HTML
- CSS
- JS
- WordPress 页面模板
- 组件化 UI

插件负责：

- 课程数据
- Tutor LMS 适配
- 课程授权
- 试看
- 学习进度
- 课时完成状态
- 视频权限
- HLS 保护
- 学员相关业务

## 2. 禁止事项

主题开发者不得：

1. 直接查询 Tutor LMS 数据库表。
2. 复制插件中的授权判断逻辑。
3. 自己计算课程完成百分比。
4. 自己读取或拼接 HLS 地址。
5. 绕过试看和授权接口。
6. 把业务数据写死在主题中。
7. 把课程授权规则复制到 JS。
8. 修改现有插件的核心业务代码来适配视觉。
9. 恢复独立课程详情页。
10. 新增专题详情页，除非产品需求明确改变。

## 3. 正确的数据调用方式

推荐：

```php
use MathCourse\Course\Course_Service;

$service = new Course_Service();
$data = $service->get_course_directory(
    $course_id,
    get_current_user_id()
);
```

然后使用：

```php
$data['title']
$data['cover']
$data['grade']
$data['type']
$data['access']
$data['progress']['completed']
$data['progress']['total']
$data['progress']['percent']
$data['topics']
```

## 4. 权限

不要写：

```php
if ( get_user_meta(...) ) { ... }
```

也不要根据某个 Tutor LMS 数据库字段自行判断授权。

应该使用插件已经计算好的：

```php
$data['access']
$lesson['accessible']
$lesson['preview']
```

## 5. 进度

不要自己查询学习记录。

直接使用：

```php
$data['progress']
$lesson['completed']
```

## 6. 视频

主题只负责播放器外观和容器。

视频地址由插件负责提供。

不要在主题中：

- 扫描 uploads 找 m3u8
- 根据课程 ID 拼接 m3u8
- 暴露真实 HLS 路径
- 自行判断 Referer

## 7. URL

课程学习统一进入：

`/learning/?course_id={COURSE_ID}`

指定课时：

`/learning/?course_id={COURSE_ID}&lesson_id={LESSON_ID}`

课程中心卡片和学习中心按钮优先使用插件返回的 URL。

## 8. PHP 编码

遵守 WordPress Coding Standards：

- `defined('ABSPATH') || exit;`
- 输出使用 `esc_html()`、`esc_url()`、`esc_attr()`
- 用户输入使用 `sanitize_*` / `wp_unslash()`
- 不信任 GET/POST 数据
- 不使用未经验证的 HTML 输出

## 9. CSS

建议使用主题自己的命名空间，例如：

```css
.mc-theme-*
.mathcourse-*
```

避免覆盖：

```css
body *
.wp-block-*
.tutor-*
```

除非确实有明确兼容需求。

响应式规则集中管理，不要不断在文件底部重复追加相同 media query。

## 10. JS

JS 只负责交互，例如：

- 章节展开/收起
- UI 状态
- 移动端菜单
- 播放器 UI 辅助

涉及授权、进度、视频权限的判断必须由服务器端插件负责。

## 11. 修改流程

开发新主题时：

1. 阅读本目录全部文档。
2. 阅读现有插件接口。
3. 先确认数据结构。
4. 再设计模板。
5. 再编写 CSS/JS。
6. 不修改插件业务逻辑。
7. 完成后测试游客和登录用户。
8. 测试手机端和微信端。

## 12. 验收标准

### 游客

- 能看到课程中心全部公开课程。
- 不能看到学习中心入口。
- 点击课程直接进入播放器。
- 试看课程正常。
- 非试看课程不能绕过授权。

### 登录学员

- 能看到学习中心。
- 学习中心只显示已授权课程。
- 能看到真实学习进度。
- 点击继续学习进入正确课时。
- 无权限课时不能绕过授权。

### 播放器

- 课程章节正常。
- 章节可展开/收起。
- 长目录可以滚动。
- 倍速、进度、全屏等现有功能不能因主题改版失效。
- 没有视频的课程仍显示播放器及友好提示。

## 13. 最重要的一条

**不要为了做一个新主题而重写现有业务系统。**

如果现有插件提供的数据不够，优先增加一个稳定、明确、可复用的插件服务接口，然后让主题调用它。
