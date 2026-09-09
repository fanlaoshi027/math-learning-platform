# 墨算（Mosuan）板书软件开发进展记录

**日期：2026-09-09**  
**当前分支：`mosuan-board-dev`**  
**项目：`fanlaoshi027/math-learning-platform`**

---

## 一、项目定位

墨算（Mosuan）定位为：

> **一块好用的数字书写板。**

核心方向：

- 数字书写体验
- 板书效率
- 课堂演示
- 录课
- 动态几何
- 后续再扩展其他教学能力

技术方向：

- Swift / SwiftUI
- Metal
- macOS 原生优先
- 后续 iPadOS、Windows 等平台共享几何与数据模型

原则：

> **书写体验 > 板书效率 > 课堂演示 > 录课 > 扩展课件**

保持产品简洁，不堆无关功能。

---

## 二、动态几何总体设计

核心思想：

> **任何一个有结构的图形，都不是一张死图，而是由“点 + 线 + 角度 + 长度 + 约束”组成的可运动图形。**

统一模型：

```text
图形
│
├── 点
│   ├── 固定
│   ├── 可拖动
│   └── 可绑定其他点
│
├── 线 / 边
│   ├── 长度固定
│   ├── 长度可变
│   ├── 直线
│   ├── 射线
│   └── 线段
│
├── 角
│   ├── 固定角度
│   └── 可拖动改变
│
└── 约束
    ├── 点在线上
    ├── 点在线段上
    ├── 两边相等
    ├── 平行
    ├── 垂直
    ├── 点绑定
    └── 绕固定点旋转
```

更底层的抽象：

```text
Geometry Object
    ↓
Geometry Graph
    ↓
Constraints
    ↓
Parameters
    ↓
Solver
    ↓
实时更新图形姿态
```

---

## 三、目前已经完成的动态角核心

### 1. GeometryParameter

已经支持：

- 当前值
- 最小值
- 最大值
- 步长
- 是否可动画
- 动画速度
- 动画循环方式

循环方式：

```text
pingPong
restart
```

其中默认推荐：

> **Ping-Pong：最小值 → 最大值 → 最小值**

避免动画循环时突然跳回起点。

### 2. GeometryAngle

已经支持：

- 角顶点
- 起始点
- 终止点
- 角度计算
- 角度标签
- 度数显示
- 名称显示
- 名称 + 度数
- 固定角度
- 参数控制角度

例如：

```text
α = 45°
```

### 3. GeometryLine

已经支持：

- line
- segment
- ray

线段长度可以固定，也可以绑定 GeometryParameter。

### 4. GeometryConstraintSolver

已经建立统一约束求解框架，目前处理：

- 固定点
- 点绑定
- 点在线
- 点在线段
- 固定长度
- 等长
- 长度比例
- 固定角
- 等角
- 角度比例
- 参数化长度
- 参数化角度

暂需继续完善：

- 点在圆
- 旋转约束
- 平行
- 垂直

这些已进入统一约束模型，但部分 solver 尚未完成。

---

## 四、DynamicAngle

已经建立独立的 `DynamicAngle`，它基于：

```text
GeometryModel
+
GeometryParameter
+
GeometryAngle
+
GeometryConstraintSolver
```

目前支持：

- 创建动态角
- 起点/终点
- 顶点固定
- 角度参数
- 角度范围
- 步长
- 拖动端点改变角度
- 数值设置角度
- 播放
- 暂停
- 循环
- 保存/加载

默认示例：

```text
α = 45°
范围：10° ～ 170°
```

---

## 五、InkRenderer 已经接入动态角

Metal Renderer 已加入：

- 动态角两条射线
- 角弧
- 选中状态
- 顶点控制点
- 终点控制点

动态角已经进入：

```text
GraphicObject.Kind.dynamicAngle
```

因此动态角已经属于正式图形对象体系，而不是 SwiftUI 临时演示。

---

## 六、GraphicObjectStore

已经接入：

- `addDynamicAngle`
- `setDynamicAngle`
- `dynamicAngleParameter`
- `dragDynamicAngleEndpoint`

同时保留 selection、hit test、transform、export/import 等基础能力。

---

## 七、InkMetalView 交互

已经接入：

```text
BoardTool.dynamicAngle
```

当前交互：

### 创建

选择“动态角”工具后，在画布创建动态角。

### 拖动

选中动态角后，可以拖动橙色端点改变角度。

### 参数

可以修改动态角的角度参数。

### 播放

动态参数支持：

```text
▶ 播放
⏸ 暂停
🔄 循环
```

---

## 八、最常用的动态几何场景

已经明确：

> **最常用的是“两根线之间的夹角动态变化”。**

因此动态角是动态几何第一优先级。

之后逐步扩展：

1. 动态长度
2. 动态点位置
3. 点在线段上运动
4. 两图形联动
5. 旋转
6. 平行/垂直约束
7. 函数图像联动

---

# 九、当前 CI 问题

之前出现过：

```text
compiler unable to type-check this expression in reasonable time
```

主要涉及动态角绘制中的 SIMD / `cos` / `sin` 混合表达式。

已经验证：明确使用 `Float` 可以避免部分 Swift 类型推导问题，例如：

```swift
let cosAngle = Float(cos(angle))
let sinAngle = Float(sin(angle))
let arcVector = SIMD2<Float>(cosAngle, sinAngle)
let current = v + arcVector * radiusFloat
```

---

## 十、CI 排查过程

连续测试过：

```text
macos-14
macos-15
macos-15-intel
macos-26-intel
```

均出现类似现象：

```text
Job 启动
↓
约 5～10 秒
↓
failure
↓
0 steps
↓
无正常 build log
↓
无 diagnostics artifact
```

因此当前判断：这不像 Swift 源码编译错误，因为 runner 很可能还没有真正进入 Swift build。

同时增加了 build diagnostics 上传机制。

---

## 十一、当前 CI Workflow

当前 macOS Workflow 使用：

```yaml
runs-on: macos-26-intel
```

主要步骤：

```text
Checkout
↓
swift --version
↓
build-macos-app.sh
↓
上传 swift-build.log
↓
上传 App
↓
上传 DMG
```

另外加入了 Ubuntu runner 诊断，用于区分 GitHub Actions 平台问题和 macOS runner 问题。

---

## 十二、当前构建脚本问题

`scripts/build-macos-app.sh` 目前仍存在临时处理：

```text
构建前使用 perl 修改 InkRenderer.swift
```

目的是把动态角 SIMD 表达式自动替换成更容易通过类型检查的形式。

这个方案只适合临时诊断，正式工程必须改成：

```text
源码本身就是正确、明确的 Float 写法
↓
CI 直接 swift build
↓
不修改源码
```

---

## 十三、Package.swift

当前：

```text
Swift tools：5.10
最低 macOS：14
Target：MosuanBoard
资源：Metal/InkShaders.metal
```

---

# 十四、当前 Git / CI 处理原则

当前开发分支固定为：

```text
mosuan-board-dev
```

最近 CI/诊断工作主要围绕：

- Swift 表达式类型检查
- build diagnostics
- macOS runner
- Intel runner
- Ubuntu runner

**不要在 CI 基础问题未解决前继续大范围修改动态角业务代码。**

---

# 十五、下一阶段路线

CI 稳定后正式进入动态几何工具化。

## 第一阶段：动态角

```text
选择动态角
    ↓
拖动端点
    ↓
改变角度
    ↓
参数面板
    ↓
角度数值
    ↓
滑块
    ↓
▶ / ⏸
    ↓
循环
```

## 第二阶段：动态长度

例如：

```text
AB = 100
```

通过拖动或滑块调整：

```text
AB = 50 ～ 300
```

图形实时变化。

## 第三阶段：动态点

例如：

```text
P ∈ AB
```

P 可以拖动，也可以自动运动并来回循环。

## 第四阶段：图形联动

例如：

```text
角度变化
    ↓
三角形变化
    ↓
辅助线同步变化
    ↓
面积/长度/角度实时变化
```

最终形成：

> **墨算动态几何 = 参数驱动的可运动几何图形系统。**

---

# 十六、产品核心方向

最终不是做一个普通的“几何画图软件”，而是：

> **让老师在黑板上画出来的东西，可以真正动起来。**

例如：

```text
画一个三角形
↓
指定 A 点固定
↓
指定 AB = AC
↓
指定 ∠A 可变化
↓
拖动 ∠A
↓
整个三角形自然变化
```

进一步：

```text
按 ▶
↓
∠A：30° → 150° → 30°
↓
三角形连续运动
```

这就是墨算最有价值的教学能力之一。
