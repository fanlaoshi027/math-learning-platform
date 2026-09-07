# 低延迟书写 MVP

## 目标

第一阶段唯一核心目标：

> 证明 Mosuan Board 的数位笔书写自然、稳定、低延迟。

## MVP 输入

支持：

- 鼠标作为基础输入设备
- 压力感应数位笔
- PointerEvent
- x/y
- pressure
- timestamp
- pointerType
- tilt 数据模型预留

## 输入流水线

```text
Platform Event
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
Dynamic Renderer
```

## 压力

压力值应首先归一化到统一范围，再根据用户压力曲线映射到线宽。

必须支持：

- 最小压力
- 最大压力
- 最小线宽
- 最大线宽
- 压力曲线

不同品牌设备不应要求不同的 Stroke 算法。

## 平滑

平滑算法必须在稳定性和延迟之间取得平衡。

不要默认使用过重的平滑，否则数学教师快速写公式时会出现明显跟手延迟。

应允许后续加入：

- smoothing strength
- stabilization
- velocity-aware smoothing

## 渲染

采用 Dynamic Layer + Static Layer。

当前笔画写入 Dynamic Layer，笔画结束后进入 Static Layer。

不要在每个 pointer move 后重新渲染整页。

## 橡皮

MVP 实现：

- 整笔擦除
- 基础选择

局部擦除可以在 Stroke 数据模型稳定后加入。

## Undo / Redo

至少支持：

- 新增笔画
- 删除笔画
- 变更对象

Undo/Redo 必须基于结构化操作，而不是整页截图。

## MVP 测试场景

必须实际测试：

1. 慢速横线
2. 快速横线
3. 快速竖线
4. 大圆
5. 小圆
6. 连续数学公式
7. 轻压力
8. 重压力
9. 快速压力变化
10. 长时间连续书写
11. 快速来回涂写
12. 橡皮擦除
13. 撤销/重做

## 成功标准

成功标准优先使用真实设备和主观书写体验 + 可测性能指标共同判断。

不能在没有实测数据的情况下虚构固定毫秒值作为承诺。

重点观察：

- 笔尖移动与墨迹显示是否明显不同步
- 快速书写是否断笔
- 曲线是否出现明显抖动
- 压力变化是否自然
- 长时间书写是否出现性能下降
- CPU/GPU 是否出现持续异常占用

## 暂不开发

- PDF
- 图库
- 函数图像
- 坐标系
- 复杂几何
- 录制
- 云同步
- 账号
- Windows UI
- Android UI

但是所有接口必须允许未来接入这些功能。
