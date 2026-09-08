# 墨算（Mosuan）多平台产品与架构路线

> 本文从现在开始作为墨算多平台开发的长期约束。Mac 不是孤立版本；所有核心数据模型、文档格式、编辑语义和业务能力都应为 macOS / iPadOS / iOS / Windows 预留空间。

## 1. 平台战略

墨算规划四个平台：

| 平台 | 核心定位 | 优先级 |
|---|---|---:|
| macOS | 教师专业白板、备课、录课、桌面教学 | P0 |
| iPadOS | **学生课堂笔记 + 教师移动书写** | P0 |
| Windows | 教室电脑、教师电脑、数位板 | P1 |
| iOS | 笔记查看、轻量编辑、同步与跨设备入口 | P2 |

产品不是四个独立 App，而是一个统一的“墨算”产品体系。

## 2. iPadOS 的核心定位

iPadOS 不只是 Mac 版的缩小版。

第一阶段重点打造：

> **Apple Pencil + iPad = 学生自己的数字课堂笔记本。**

典型流程：

```text
上课 → 打开课程/笔记本 → 当天页面 → 听课 + Apple Pencil 书写 → 讲义/PDF 批注 → 自动保存 → 课后复习
```

重点能力：

- 极低延迟 Apple Pencil 书写
- 手写笔 + 手指双输入
- 手指负责移动/缩放/页面操作
- Apple Pencil 负责书写
- 套索选择
- 移动/缩放/旋转手写内容
- 橡皮擦
- 页面管理
- PDF/讲义批注
- 图片插入
- 页面背景
- 搜索与整理
- `.mosuan` 文档打开与保存

## 3. 学生笔记模式

学生模式与教师白板模式分开设计。

```text
书写体验 > 笔记整理 > 讲义批注 > 复习 > 云同步 > 教学工具
```

学生打开 App 后优先看到：

- 最近笔记
- 我的课程
- 最近页面
- 新建笔记

不默认堆入教师工具栏。

## 4. Mac / iPad 分工

### macOS

面向教师：大屏白板、数位板、键盘快捷键、课堂演示、录课、OBS、PDF 批注、数学图形、精确对象编辑。

### iPadOS

面向学生和移动教师：Apple Pencil、课堂笔记、PDF 批注、讲义批注、手写题目、课堂整理、复习。

同一份 `.mosuan` 文件应该尽可能在 Mac 与 iPad 间无损打开。

## 5. Windows

Windows 版本定位：教师电脑、教室电脑、Windows 平板、Wacom / HUION / XP-PEN 等数位板。

技术方向：

- WinUI 3
- Windows App SDK
- DirectX
- MSIX
- Microsoft Store

不采用传统 UWP 作为新项目核心架构。

## 6. iOS

iPhone 不追求完整替代 iPad，主要用于查看笔记、快速修改、页面管理、云同步、课堂资料查看、分享和跨设备入口。未来根据触控体验再增加轻量书写能力。

## 7. 统一核心层

目标架构：

```text
                 Mosuan Core
                      │
       ┌──────────────┼──────────────┐
       ↓              ↓              ↓
   macOS/iPadOS     Windows         iOS
       │              │              │
     Metal          DirectX        Metal
       │              │              │
   AppKit/SwiftUI   WinUI 3       SwiftUI/UIKit
```

Core 应包含：Document、Page、Stroke、InkPoint、GraphicObject、Transform、Selection、Geometry、Undo/Redo、Clipboard Payload、Background、Annotation、Document Version、Serialization。

Core 不应该依赖 AppKit、UIKit、WinUI、NSEvent 或 Windows Pointer API。

## 8. 输入抽象

统一抽象为平台无关的 `PointerEvent`：

```text
PointerEvent
├── position
├── pressure
├── tilt
├── azimuth
├── phase
├── deviceType
├── buttons
└── modifiers
```

平台负责把 Apple Pencil、鼠标、触控、Wacom、HUION、XP-PEN、Windows Pen 转换成统一输入模型。

## 9. 文档格式

`.mosuan` 必须从第一版就考虑跨平台。禁止把平台私有数据作为唯一真相保存。

保存：页面、原始笔迹采样、压力、样式、GraphicObject、Transform、分组、PDF/Annotation 信息、页面背景、文档版本、元数据。

平台缓存、缩略图、渲染缓存可以另存或重新生成。

## 10. 学生笔记与教师课程

长期可以形成：

```text
教师 → 课程/讲义/课堂资料 → 学生 iPad → 我的课堂笔记 → 课后复习
```

这是墨算区别于普通白板软件的重要方向。

第一阶段不做复杂在线课堂系统，先把“好写、好记、好整理”做好。

## 11. 账号与服务器

统一使用现有网站服务器作为墨算后端基础设施，逐步形成独立 API 层，例如 `api.fanlaoshi.com`。

服务职责：账号、登录、License / Pro 权益、设备管理、云同步、App 版本信息、服务端配置。

WordPress 继续负责网站、课程和运营；墨算 API 不应依赖 WordPress 页面逻辑。

## 12. 授权体系

```text
墨算账号 → 权益中心 → Mac / iPad / iPhone / Windows
```

Windows 通过 Microsoft Store 分发时结合 Store License；Apple 平台结合 App Store 购买体系；网站购买权益是否跨平台使用必须按各平台数字商品规则设计。

不追求绝对防破解，而采用商店正版分发、账号授权、服务端权益校验、设备管理、定期刷新授权和本地缓存授权状态。

## 13. 当前 Mac 开发约束

1. 新数据模型不得绑定 AppKit。
2. 新编辑语义不得绑定鼠标事件。
3. 几何算法放在核心层，而不是 UI 层。
4. Undo/Redo 表达文档内容变化，纯选择状态不应消耗 Undo。
5. `.mosuan` 不保存平台私有渲染对象。
6. 临时预览层与正式文档数据分离。
7. 输入事件逐步抽象，为 Apple Pencil 和 Windows Pen 留接口。
8. 结构化对象继续使用 GraphicObject，而不是截图或不可编辑路径。

## 14. 开发顺序

### Phase 1：macOS

低延迟书写、Stroke、GraphicObject、选择、Transform、Undo/Redo、页面、`.mosuan`、PDF、教学工具。

### Phase 2：iPadOS

优先做学生课堂笔记：Apple Pencil、手指手势、页面笔记本、PDF 批注、套索、橡皮、页面整理、Mac 文件互通。

### Phase 3：Windows

WinUI 3、DirectX、Windows Pen、数位板、MSIX、Microsoft Store、Windows 授权体系。

### Phase 4：iOS

查看、同步、轻量编辑、页面管理、分享。

## 15. 产品原则

> **墨算不是四个版本，而是一套跨设备的数字书写系统。**

Mac 解决教师专业书写与教学；iPad 解决学生课堂笔记与移动书写；Windows 解决教室和 PC 用户；iPhone 解决随时查看与跨设备连接。

所有平台最终共享同一套核心文档、对象语义和账户体系。
