# 数位板与手写笔兼容策略

## 1. 原则

不要为每一个品牌实现一套独立书写引擎。

统一通过平台输入适配器转换为 `PointerEvent`。

```text
Wacom / HUION / XP-PEN / ...
            ↓
      OS / Driver API
            ↓
      Platform Adapter
            ↓
       PointerEvent
            ↓
       Core Engine
```

## 2. 目标设备

优先验证：

- Wacom
- HUION / 绘王
- XP-PEN

兼容目标：

- GAOMON / 高漫
- VEIKK
- One by Wacom
- 系统标准触控笔设备

## 3. macOS

优先利用系统提供的 Tablet/Pointer 事件能力获取：

- 位置
- 压力
- 倾斜
- 设备信息
- 事件阶段

平台细节不得直接进入核心 StrokeBuilder。

## 4. Windows

后续优先考虑：

- Windows Ink
- 必要时增加 WinTab Adapter

两种输入最终都转换成同一个 PointerEvent。

## 5. Android

后续使用 Android Stylus/MotionEvent 能力。

核心仍然只接受统一 PointerEvent。

## 6. 压力归一化

不同设备的压力曲线可能不同。

因此需要：

- 原始压力读取
- 归一化
- 最小/最大压力设置
- 用户压力曲线
- 平滑

不能通过品牌名称硬编码线宽规则。

## 7. 设备识别

可以记录：

- manufacturer
- model
- deviceId

但核心逻辑不得依赖具体品牌。

## 8. 测试矩阵

实际拥有设备时建立：

| 设备 | 系统 | 压力 | 倾斜 | 快速书写 | 长时间书写 | 状态 |
|---|---|---|---|---|---|---|
| Wacom | macOS | 待测 | 待测 | 待测 | 待测 | 未测试 |
| HUION | macOS | 待测 | 待测 | 待测 | 待测 | 未测试 |
| XP-PEN | macOS | 待测 | 待测 | 待测 | 待测 | 未测试 |
| Windows Tablet | Windows | 后续 | 后续 | 后续 | 后续 | 未测试 |
| Android Stylus | Android | 后续 | 后续 | 后续 | 后续 | 未测试 |

不能在没有真实设备测试时声称完全兼容。
