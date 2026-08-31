# Math Learning Platform 前端主题开发接口规范

版本：V1.0
适用分支：`wpui`

## 1. 核心原则

本项目采用“插件负责业务、主题负责表现”的架构。

- 插件负责课程、授权、试看、学习进度、视频权限、播放器数据。
- 主题负责 HTML、CSS、JS、布局、响应式和视觉表现。
- 主题不得直接复制业务逻辑，不得直接依赖 Tutor LMS 数据库结构。
- 如果主题需要当前插件没有提供的数据，应先扩展插件服务接口，再由主题调用。

## 2. 核心服务

课程数据统一通过：

`MathCourse\\Course\\Course_Service`

主要接口：

### `get_course($course_id)`

返回课程基础数据：

```php
array(
    'id'    => 25,
    'title' => '课程名称',
    'type'  => 'topic',
    'grade' => '8',
    'cover' => 'https://...'
)
```

### `get_course_directory($course_id, $user_id = 0)`

这是课程中心、学习中心、课程目录主题最重要的接口。

返回：

```php
array(
    'id'       => 25,
    'title'    => '课程名称',
    'type'     => 'topic',
    'grade'    => '8',
    'cover'    => 'https://...',
    'is_free'  => false,
    'access'   => true,
    'topics'   => array(...),
    'progress' => array(
        'completed'      => 8,
        'total'          => 20,
        'percent'        => 40,
        'last_lesson_id' => 1008
    )
)
```

## 3. 课程字段

| 字段 | 类型 | 用途 |
|---|---|---|
| id | int | 课程 ID |
| title | string | 课程名称 |
| cover | string | 课程封面 URL，可为空 |
| grade | string | `7`、`8`、`9` |
| type | string | `topic` 或 `supplementary` |
| is_free | bool | 是否免费课程 |
| access | bool | 当前用户是否拥有课程授权 |
| topics | array | 章节和课时 |
| progress | array | 当前用户课程进度 |

## 4. 课程类型

内部值固定：

- `topic` = 专题课程
- `supplementary` = 教辅配套

主题可以自由设计显示文字，但不得修改内部值。

## 5. 年级

内部值固定：

- `7` = 七年级
- `8` = 八年级
- `9` = 九年级

主题显示时可转换为中文名称。

## 6. 课程进度

```php
$data['progress']['completed']
$data['progress']['total']
$data['progress']['percent']
$data['progress']['last_lesson_id']
```

主题不得自行查询完成记录，也不得重新计算业务进度。

进度条只负责显示 `percent`。

## 7. 章节与课时

`$data['topics']`：

```php
array(
    array(
        'id' => 101,
        'title' => '第一章 全等三角形',
        'lessons' => array(
            array(
                'id' => 1001,
                'title' => '全等三角形的概念',
                'page_number' => '',
                'video_id' => '',
                'hls_url' => '',
                'url' => '/learning/?course_id=25&lesson_id=1001',
                'completed' => true,
                'preview' => false,
                'accessible' => true
            )
        )
    )
)
```

课时字段：

- `id`：课时 ID
- `title`：课时名称
- `page_number`：教材页码信息
- `video_id`：视频 ID
- `hls_url`：受保护的视频地址；主题不得自行生成
- `url`：安全学习页面地址
- `completed`：当前用户是否完成
- `preview`：是否试看
- `accessible`：当前用户是否可以观看

## 8. 播放器 URL

标准入口：

`/learning/?course_id={COURSE_ID}`

指定课时：

`/learning/?course_id={COURSE_ID}&lesson_id={LESSON_ID}`

主题应优先使用插件返回的 `url`，不要自行拼接权限相关地址。

## 9. 视频安全

主题禁止：

- 从数据库自行读取 HLS 地址
- 自行拼接 m3u8 地址
- 绕过 `can_watch_lesson()`
- 把受保护 HLS 地址写入前端固定配置

插件负责判断：授权、试看、视频存在性和受保护 URL。

## 10. Shortcode

当前前端业务 shortcode：

- `[mathcourse_course_directory]`
- `[mathcourse_course_center]`
- `[mathcourse_learning_center]`

主题可以负责页面模板和样式，但业务数据仍由插件提供。

## 11. 课程中心规则

课程中心展示公开课程列表。

产品规则：

`课程中心 → 课程卡片 → 播放页面`

不再设计独立课程详情页。

## 12. 学习中心规则

学习中心仅面向登录学员。

只显示：

`access === true`

的课程。

每张卡片建议显示：

- 封面
- 课程名称
- 已完成 / 总课时
- 百分比
- 进度条
- 开始学习 / 继续学习

## 13. 空封面

`cover` 可能为空。

主题必须提供美观的默认占位方案，例如色块 + 数学课程文字，而不是显示破图。

## 14. 空视频

课程可能已经建立，但暂时没有视频。

主题仍应显示播放器容器，并显示明确的“视频正在准备中/暂时没有视频”提示，不应把页面渲染成错误页。

## 15. 未来扩展

如果新增字段，优先扩展 `Course_Service` 返回结构，并更新本文件。主题不得直接读取插件内部数据库表作为长期接口。
