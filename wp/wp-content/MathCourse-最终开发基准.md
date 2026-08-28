# MathCourse 最终开发基准

> 本文件为项目后续开发的统一基准。内容以 `备忘2-1.md` ～ `备忘2-5.md` 的迭代结论为准：后期结论覆盖前期方案；已被替代的实现不再作为当前开发依据。

## 1. 项目定位

MathCourse 是 WordPress + Tutor LMS Pro 4.0.4 上的数学在线课程扩展插件。核心原则是不重复制造 LMS，而是在 Tutor LMS 之上实现数学课程业务、授权、视频播放及前后台体验。

## 2. Tutor LMS 基准

- 开发基准固定为 Tutor LMS Pro 4.0.4。
- Course / Topic / Lesson 优先复用 Tutor 原生对象和课程结构。
- MathCourse 不直接把 Tutor 内部实现散落到业务代码中。
- 所有 Tutor 相关实现通过 Adapter / Hooks / Service 层隔离。
- 不凭记忆假设 Tutor API、Hook、Model 或数据库结构；涉及具体实现时必须以项目实际安装版本验证。

## 3. 课程结构

最终课程层级：

```text
Course
 └─ Topic
     └─ Lesson
```

大培优课程采用“一个页码对应一个 Lesson”的业务模型。页码不是重新创建一套 LMS 层级，而是 MathCourse 对 Tutor Lesson 的业务扩展。

核心扩展字段包括：

- `page_number`
- `is_trial`
- `video_id`

实际字段落点以最终代码和 Tutor 4.0.4 的兼容实现为准。

## 4. Adapter 业务接口

MathCourse 内部可以使用统一业务接口，例如：

```text
get_course()
get_courses()
create_course()
update_course()

get_topics()
create_topic()

get_lessons()
create_lesson()
update_lesson()
delete_lesson()
```

这些是 MathCourse 的业务接口，不代表 Tutor 4.0.4 必然存在同名函数。真实 Tutor 调用必须在 Adapter 内完成转换。

## 5. 大培优

大培优的核心模型：

```text
一本资料
  ↓
课程
  ↓
章节 Topic
  ↓
页码 Lesson
```

例如：

```text
第1页 → Lesson
第2页 → Lesson
第3页 → Lesson
……
第428页 → Lesson
```

需要支持后续的页码批量创建、排序、编辑以及试听标记，而不是手工逐个创建。

## 6. 学员与授权

课程访问必须经过 MathCourse 的业务授权逻辑。授权服务负责判断用户是否拥有对应课程/内容访问权，并与 Tutor LMS 的课程访问机制衔接。

批量授权、学员管理以及“我的课程”等前后台功能属于完整产品的一部分。

原则：

- 前台不能仅依赖隐藏菜单实现权限。
- Lesson、视频资源等实际内容访问必须再次校验权限。
- 授权逻辑集中在 Service 层，不在模板中散落判断。

## 7. 视频系统

视频系统是 MathCourse 的核心扩展之一，包含：

- 视频资源管理
- HLS 播放
- AES-128 加密
- Token 化访问
- 播放权限检查
- 播放器
- 手机号动态水印

视频 URL 不应直接暴露真实媒体资源。播放器请求必须经过权限和 Token 校验。

## 8. HLS / AES / Token

目标播放链路：

```text
用户打开 Lesson
      ↓
MathCourse / Tutor 权限判断
      ↓
生成受控播放 Token
      ↓
播放器请求 HLS
      ↓
校验 Token / 权限
      ↓
返回播放资源
      ↓
AES-128 Key 受控获取
      ↓
正常播放
```

Token 必须具有有效期和访问约束；AES Key 不应作为公开静态资源直接暴露。

## 9. 播放器与水印

播放器由 MathCourse 控制，支持课程视频播放及动态水印。

水印最终目标是显示与当前登录学员相关的信息（例如手机号），并具备动态位置/防简单裁剪能力。具体前端实现必须与后端授权链路一致。

## 10. Tutor Hooks

MathCourse 通过 Tutor Hooks 与 Tutor LMS 的课程/课程内容访问流程整合。

重点包括：

- Lesson Access
- 课程内容访问判断
- MathCourse 视频播放器注入
- 试听 Lesson 的访问规则

不得通过粗暴覆盖 Tutor 核心文件的方式实现整合。

## 11. 后台

后台采用独立的 MathCourse 管理界面，逐步形成：

- 课程管理
- 学员管理
- 授权管理
- 视频管理
- 系统状态

后台代码采用模块化结构和 PSR-4 风格自动加载，入口文件保持轻量。

## 12. 前台

产品前台目标包括：

- 首页
- 专题课程
- 大培优
- 我的课程
- 课程详情
- 学习页面
- 视频播放

UI 应保持数学教育产品定位，业务数据与展示层分离。

## 13. 安全原则

- 所有后台操作检查登录和能力权限。
- 所有用户输入经过校验/清洗。
- 数据库操作使用 WordPress 安全 API / `$wpdb` 的预处理机制。
- AJAX / REST 操作使用 nonce 等 CSRF 防护。
- 视频播放接口必须检查授权。
- Token 与 AES Key 不作为公开页面数据直接泄露。
- 不把敏感配置、密钥或 Token 写入前端源码或日志。

## 14. 数据设计原则

优先复用 WordPress / Tutor LMS 数据结构。MathCourse 只为确有必要的业务数据增加 Meta 或独立数据结构。

不得因为“以后可能需要”就重复建立 Course、Topic、Lesson 的平行数据体系。

## 15. 当前代码迭代原则

后续开发必须遵守：

1. 先读取本文件。
2. 再检查 GitHub 当前代码。
3. 涉及 Tutor 的具体实现，再核对 Tutor LMS Pro 4.0.4 的实际代码/API。
4. 后期代码结论覆盖前期设计。
5. 不把备忘录中的伪代码直接当成可运行代码。
6. 不重复实现已经存在的功能。
7. 修改完成后保持插件可加载、后台菜单正常、系统状态正常。
8. 出现兼容性问题时优先修复根因，不通过隐藏异常输出的方式掩盖问题。

## 16. 当前开发方向

当前重点不是重新规划产品，而是继续把既有设计落成稳定代码：

```text
Tutor 4.0.4 兼容层
    ↓
Course / Topic / Lesson
    ↓
页码 Meta
    ↓
大培优批量 Lesson
    ↓
授权与 Lesson Access
    ↓
视频 / HLS / AES / Token
    ↓
播放器 / 动态水印
    ↓
完整前后台体验
```

## 17. 开发纪律

以后“继续”默认表示：从 GitHub 当前代码和本文件记录的最后有效迭代点继续，而不是从项目初始版本重新开始。

如历史备忘录与当前代码发生冲突：

- 已验证运行的当前代码优先保留；
- 已明确废弃的旧方案不恢复；
- 需要改变现有行为时，先明确迁移路径并保持向后兼容；
- Tutor 4.0.4 的实际行为优先于历史猜测。
