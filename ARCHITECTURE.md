# Mosuan Board 软件架构

## 1. 总体原则

Mosuan Board 必须从第一天起做到“平台无关的核心 + 平台适配层”。

```text
Mosuan Board
│
├── Domain / Core
│   ├── Document
│   ├── Page
│   ├── Stroke
│   ├── GraphicObject
│   ├── Geometry
│   ├── Transform
│   ├── Gallery
│   └── Undo / Redo
│
├── Input
│   ├── PointerEvent
│   ├── InputSampler
│   ├── StrokeBuilder
│   ├── PressureProcessor
│   └── Smoothing
│
├── Rendering
│   ├── Canvas Renderer
│   ├── PDF Renderer
│   ├── Object Renderer
│   └── Display Transform
│
└── Platform Adapters
    ├── macOS
    ├── Windows
    └── Android
```

## 2. 平台策略

macOS 是第一开发平台，但不应让 Swift/AppKit 类型直接进入 Domain。

可以根据实际性能验证选择：

- Swift / SwiftUI / AppKit / Metal
- Kotlin Multiplatform / Compose Multiplatform
- Qt / C++

最终技术选型必须以“低延迟书写 + 数位板兼容 + 跨平台成本”实测为准，而不是仅凭语言偏好。

## 3. 输入链路

```text
OS / Tablet Driver
      ↓
Platform Input Adapter
      ↓
PointerEvent
      ↓
InputSampler
      ↓
StrokeBuilder
      ↓
PressureProcessor
      ↓
Smoothing / Stabilization
      ↓
Renderer
```

PointerEvent 至少包含：

- position
- pressure
- timestamp
- pointerType
- phase
- buttons
- tilt
- deviceId

未来可扩展：

- azimuth
- altitude
- rotation
- tangentialPressure
- eraser tip

## 4. 渲染

书写必须支持增量渲染，避免每次输入事件都重新生成整个页面。

建议区分：

- Static Layer：PDF、已经完成的对象
- Dynamic Layer：当前正在书写的笔画
- Temporary Layer：选择框、激光笔、预览

完成一笔后，将 Dynamic Layer 的结果合并到可缓存的静态渲染数据中。

## 5. 坐标系统

统一使用世界坐标，不直接保存屏幕像素。

```text
Document World Coordinates
          ↓
Page Coordinates
          ↓
Viewport Transform
          ↓
Screen Coordinates
```

因此缩放、旋转、不同分辨率和不同平台不会破坏数据。

## 6. Document 与 Layer

```text
Document
├── Metadata
├── Pages[]
│   ├── PDFContent
│   ├── AnnotationLayer
│   └── TemporaryLayer
└── DocumentState
```

TemporaryLayer 默认不持久化。

## 7. GraphicObject

不要把所有内容都建模成 Stroke。

```text
GraphicObject
├── id
├── type
├── geometry
├── transform
│   ├── position
│   ├── scale
│   ├── rotation
│   └── rotationCenter
├── style
└── metadata
```

这样可以让线段、三角形、圆、函数图像等真正可编辑。

## 8. Undo / Redo

撤销系统应以命令/操作为基本单位，而不是截图。

例如：

- AddObject
- DeleteObject
- UpdateObject
- TransformObject
- GroupObjects
- UngroupObjects
- InsertGalleryDiagram

## 9. 性能原则

- 输入线程和 UI 状态更新解耦。
- 不在高频 pointer move 中执行昂贵的布局。
- 不在每个采样点重新计算整个页面。
- 渲染尽可能使用 GPU。
- 保持当前笔画路径的增量更新。
- 文档数据与屏幕缓存分离。

## 10. 未来扩展

同一套核心能力必须支持：

- PDF
- 固定页面白板
- 无限画布
- 数学对象
- 图库
- 打印
- 录制
- Windows
- Android

核心 API 不应依赖具体 UI 框架。
