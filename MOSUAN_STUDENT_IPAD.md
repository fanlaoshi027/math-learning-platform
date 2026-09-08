# 墨算 iPad 学生课堂笔记规划

## 定位

iPadOS 不是简单的 macOS 移植版。它重点服务学生课堂记录，同时保留教师移动书写能力。

核心目标：

> 听课时快速记，课后能复习，笔记始终可编辑。

## 学生课堂笔记核心流程

```text
进入课程/课堂
 ↓
打开老师资料或 PDF
 ↓
左右/上下自由书写
 ↓
重点标记、圈画、批注
 ↓
课后自动保存
 ↓
按课程/章节/日期查找
```

## 第一版必须支持

- Apple Pencil 低延迟书写
- 手指滚动/缩放，避免误触书写
- 多页笔记本
- PDF 导入与批注
- 空白页、横线、方格、点阵背景
- 钢笔、荧光笔、橡皮
- 选择、移动、复制、删除
- Undo / Redo
- 页面缩略图
- 自动保存
- `.mosuan` 文件导入导出
- iCloud/服务器同步预留接口

## 学生场景增强

### 1. 老师资料 + 学生笔记分层

PDF 原文/老师课件作为只读底层。

学生笔记作为 Annotation Layer：

```text
Document
├── Source Layer
│   └── PDF / Teacher Material
└── Annotation Layer
    ├── Ink Stroke
    ├── Highlight
    ├── Shape
    └── Text/Mark
```

这样不会破坏原始课件，学生可以单独隐藏/显示自己的笔记。

### 2. 课堂模式

课堂模式优先减少 UI 干扰：

- 全屏资料
- 快速笔盘
- 单指/双指导航明确区分
- Apple Pencil 默认书写
- 手指默认导航
- 一键回到老师当前页

### 3. 重点标记

提供比普通荧光笔更适合学生复习的标记：

- 荧光
- 波浪线
- 圈重点
- 星标
- 书签

这些可以优先作为结构化 Annotation，而不是全部变成普通 Stroke。

## 教师与学生联动（后续）

不是第一版必须实现，但架构预留：

```text
教师 Mac/iPad
      ↓
发布课堂资料
      ↓
学生 iPad
      ↓
学生个人 Annotation
      ↓
课后同步
```

未来可以实现：

- 老师更新课堂资料
- 学生笔记自动保留
- 老师发布作业/讲义
- 学生提交笔记或作业
- 按课程组织资料

## 跨平台要求

iPad 学生笔记必须使用与 Mac/Windows 相同的核心文档模型：

- GraphicObject
- Stroke
- Transform
- Selection
- History
- Page
- Annotation Layer

平台差异只存在于：

- Apple Pencil 输入
- UIKit/SwiftUI UI
- Metal 渲染
- iPad 多窗口/分屏
- 系统文件与分享

## 商业模式预留

未来可形成：

- 免费基础笔记
- 墨算 Pro
- 学生版/教育版
- 教师课堂空间
- 学校/班级授权

不要在第一版为了商业化增加复杂账号流程；先把 Apple Pencil 书写体验做到最好。
