# Xournal++ 参考研究记录

## 目的

Xournal++ 只作为技术参考，不直接复制其 UI 或把整个项目作为 Mosuan Board 的代码基础。

Xournal++ 本身是成熟的跨平台 PDF 手写批注软件，支持压感笔、输入稳定、PDF 批注、图形、橡皮擦、页面和图层等能力，因此非常适合用来验证 Mosuan Board 的底层设计方向。

## 第一阶段重点参考

1. 输入设备抽象
2. Stroke 数据结构
3. 压感处理
4. 输入稳定 / smoothing
5. 增量绘制与重绘策略
6. 橡皮擦与选择
7. Undo / Redo 命令模型
8. PDF 与批注层分离

## Mosuan Board 的取舍

Xournal++ 的成熟经验会被吸收，但 Mosuan Board 不直接继承其 GTK/C++ UI 架构。

Mosuan Board 采用：

```text
Platform Input
      ↓
PointerEvent
      ↓
InputSampler
      ↓
StrokeBuilder
      ↓
PressureProcessor
      ↓
Smoothing
      ↓
Stroke / GraphicObject
      ↓
Renderer
```

macOS 第一阶段使用 AppKit 原生事件作为输入适配层；SwiftUI 只负责应用级 UI，不承担高频笔迹采样。

## 当前原型

当前 macOS 原型已经加入：

- Swift Package Manager 工程骨架
- SwiftUI 应用入口
- AppKit 原生绘图 NSView
- 鼠标 / 手写笔事件入口
- `NSEvent.pressure` 压感读取
- 笔画点保存 x/y/pressure/timestamp
- 当前笔画实时绘制
- 已完成笔画保留

当前仍然是 Phase 0 原型，暂时没有宣称达到最终低延迟目标。

## 下一步

### P0-A：真实输入链

- 建立 `PointerEvent`
- 区分 mouse / pen / touch
- 记录 pressure、timestamp、tilt
- 统一坐标系

### P0-B：笔迹质量

- pressure curve
- pressure normalization
- low-latency smoothing
- velocity-aware smoothing
- fast stroke / slow stroke 对比测试

### P0-C：渲染

- 动态层 / 静态层
- 避免整页重绘
- GPU rendering 原型
- 后续评估 Metal

### P0-D：编辑基础

- whole-stroke eraser
- selection
- undo / redo
- stroke hit testing

只有 P0 完成并在真实设备上书写体验合格后，才进入 PDF、图库、几何和函数工具。
